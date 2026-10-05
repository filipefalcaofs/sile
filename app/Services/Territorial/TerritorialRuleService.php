<?php

namespace App\Services\Territorial;

use App\Enums\EtapaMotor;
use App\Enums\IntencaoAtividade;
use App\Enums\RuleDomain;
use App\Enums\SituacaoEtapa;
use App\Enums\TerritorialRuleEffect;
use App\Enums\TerritorialRuleType;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Models\ViabilityRequest;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Geo\GeoServerWfsCamadaClient;
use App\Services\Motor\EtapaMotorRegistrador;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Support\Audit\AuditService;

/**
 * Avaliador das regras territoriais (RT-01 a RT-05 — pacote normativo SEDUR
 * 2026-08-28) sobre a solicitação. Lê SOMENTE a versão vigente do domínio
 * `territorial` (dado versionado, RN-002) e produz um de três desfechos:
 *
 * - `indeferir` (RT-01): logradouro × CNAE proibido, com o texto legal
 *   administrável (decision_texts) — tem precedência sobre qualquer
 *   encaminhamento;
 * - `encaminhar_analise` (RT-02/03/04): poligonal incidente com o motivo
 *   exato do requisito. A allow-list de Itaigara NÃO defere — estar na lista
 *   apenas devolve a solicitação ao fluxo normal;
 * - `sem_incidencia`: nenhuma regra ativa casou.
 *
 * Degradação honesta (CA-RT-09): ponto ausente, camada não configurada ou WFS
 * indisponível numa regra de poligonal ATIVA encaminha à análise registrando
 * a indisponibilidade — nunca defere por omissão. As allow-lists de via das
 * observações do Quadro 10 (RT-05 a/b) não passam por aqui: são consultadas
 * pelo enquadramento LOUOS via `observacaoQuadro10Permite()`.
 */
class TerritorialRuleService
{
    public function __construct(
        private DecisionTextCatalog $textos,
        private AuditService $audit,
        private GeoServerWfsCamadaClient $wfs,
        private LocalizacaoInscricaoService $localizacao,
        private EtapaMotorRegistrador $etapas,
    ) {}

    /** Marca que alguma camada de poligonal não respondeu nesta avaliação. */
    private bool $camadaIndisponivel = false;

    /**
     * Avalia as regras territoriais ativas da versão vigente contra a
     * solicitação. `$ponto` é o centroide do imóvel (lat/lng) — null quando
     * não há polígono.
     *
     * @param  array{lat: float, lng: float}|null  $ponto
     */
    public function avaliar(ViabilityRequest $request, ?array $ponto): TerritorialRuleVerdict
    {
        $this->camadaIndisponivel = false;

        $verdict = $this->avaliarRegras($request, $ponto);

        if ($ponto !== null) {
            $this->etapas->registrar(
                $request,
                EtapaMotor::RegraTerritorial,
                $this->camadaIndisponivel ? SituacaoEtapa::Indisponivel : SituacaoEtapa::Ok,
                $this->camadaIndisponivel ? 'Camada territorial indisponível no GeoServer' : null,
            );
        }

        return $verdict;
    }

    /**
     * @param  array{lat: float, lng: float}|null  $ponto
     */
    private function avaliarRegras(ViabilityRequest $request, ?array $ponto): TerritorialRuleVerdict
    {
        $versao = RuleVersion::vigente(RuleDomain::Territorial)->first();

        if ($versao === null) {
            return TerritorialRuleVerdict::semIncidencia();
        }

        $regras = TerritorialRule::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('ativo', true)
            ->with(['cnaes', 'addresses'])
            ->orderBy('id')
            ->get();

        $incidencias = [];
        $encaminhar = null;

        foreach ($regras as $regra) {
            $hit = match ($regra->tipo) {
                TerritorialRuleType::LogradouroCnae => $this->casarLogradouroCnae($regra, $request),
                TerritorialRuleType::PoligonalAnalise => $this->casarPoligonal($regra, $ponto),
                TerritorialRuleType::PoligonalAllowlistExpresso => $this->casarPoligonalAllowlist($regra, $request, $ponto),
                // Allow-list de via do Quadro 10: quem avalia é o enquadramento
                // LOUOS (a observação é da linha do quadro), não este loop.
                TerritorialRuleType::ViaAllowlistZona => null,
            };

            if ($hit === null) {
                continue;
            }

            $incidencias[] = [
                'codigo' => $regra->codigo,
                'efeito' => $hit['efeito']->value,
                'motivo' => $hit['motivo'],
            ];

            // Indeferimento (RT-01) tem precedência: registra e encerra.
            if ($hit['efeito'] === TerritorialRuleEffect::Indeferir) {
                $verdict = new TerritorialRuleVerdict(
                    TerritorialRuleVerdict::INDEFERIR,
                    $regra,
                    $hit['motivo'],
                    $versao->version,
                    $incidencias,
                );

                $this->auditar($request, $verdict);

                return $verdict;
            }

            $encaminhar ??= [$regra, $hit['motivo']];
        }

        if ($encaminhar !== null) {
            $verdict = new TerritorialRuleVerdict(
                TerritorialRuleVerdict::ENCAMINHAR_ANALISE,
                $encaminhar[0],
                $encaminhar[1],
                $versao->version,
                $incidencias,
            );

            $this->auditar($request, $verdict);

            return $verdict;
        }

        return TerritorialRuleVerdict::semIncidencia($versao->version);
    }

    /**
     * Allow-list das observações (a)/(b) do Quadro 10: o uso sujeito à
     * observação só é permitido nas vias/bairros listados. True = na lista;
     * false = fora da lista; null = sem dado para verificar (CODLOG e bairro
     * ausentes) ou regra inativa — o chamador degrada para pendente, nunca
     * inventa permissão.
     */
    public function observacaoQuadro10Permite(string $codigoRegra, ?string $codLog, ?string $bairro): ?bool
    {
        $versao = RuleVersion::vigente(RuleDomain::Territorial)->first();

        if ($versao === null) {
            return null;
        }

        $regra = TerritorialRule::query()
            ->where('rule_version_id', $versao->getKey())
            ->where('codigo', $codigoRegra)
            ->where('ativo', true)
            ->with('addresses')
            ->first();

        if ($regra === null) {
            return null;
        }

        $codLog = is_string($codLog) ? trim($codLog) : '';
        $bairroNorm = is_string($bairro) ? $this->normalizar($bairro) : '';

        if ($codLog === '' && $bairroNorm === '') {
            return null;
        }

        foreach ($regra->addresses as $endereco) {
            if ($codLog !== '' && (string) $endereco->cod_log === $codLog && $endereco->cod_log !== null) {
                return true;
            }

            if ($bairroNorm !== '' && is_string($endereco->bairro) && $this->normalizar($endereco->bairro) === $bairroNorm) {
                return true;
            }
        }

        return false;
    }

    /**
     * RT-01: algum CNAE da lista entre os CNAEs NÃO marcados para exclusão
     * (excluir uma atividade proibida não é instalação) + o logradouro da
     * regra. O CNAE é checado primeiro para só consultar o Cadastro
     * Imobiliário quando a restrição pode de fato incidir.
     *
     * @return array{efeito: TerritorialRuleEffect, motivo: string}|null
     */
    private function casarLogradouroCnae(TerritorialRule $regra, ViabilityRequest $request): ?array
    {
        if (! $this->temCnaeDaRegra($regra, $request)) {
            return null;
        }

        if ($this->mesmoLogradouro($regra, $request)) {
            return [
                'efeito' => TerritorialRuleEffect::Indeferir,
                'motivo' => $this->textoIndeferimento($regra),
            ];
        }

        return match ($this->logradouroTributarioCasa($regra, $request)) {
            true => [
                'efeito' => TerritorialRuleEffect::Indeferir,
                'motivo' => $this->textoIndeferimento($regra),
            ],
            null => [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => $this->textos->render('territorial.motivo.cadastro_indisponivel', [
                    ':regra' => $regra->titulo,
                ]),
            ],
            false => null,
        };
    }

    /**
     * Segunda fonte do CODLOG: o logradouro tributário da inscrição no
     * Cadastro Imobiliário SEFAZ — a mesma numeração usada pela SEDUR na
     * regra. A via mais próxima do centroide no GIS pode ser outra (lote de
     * esquina, polígono deslocado). True = casa; false = outra via ou sem
     * inscrição; null = cadastro indisponível (não dá para descartar).
     */
    private function logradouroTributarioCasa(TerritorialRule $regra, ViabilityRequest $request): ?bool
    {
        if (blank($regra->cod_log)) {
            return false;
        }

        $localizacao = $this->localizacao->localizar($request->property_registration);

        if ($localizacao->status === LocalizacaoInscricao::INDISPONIVEL) {
            return null;
        }

        if ($localizacao->codLogTributario === null) {
            return false;
        }

        return $localizacao->codLogTributario === ltrim(trim((string) $regra->cod_log), '0');
    }

    private function temCnaeDaRegra(TerritorialRule $regra, ViabilityRequest $request): bool
    {
        $codigosRegra = $regra->cnaes->pluck('cnae')->all();

        $cnaes = $request->cnaes()
            ->where(function ($query): void {
                $query->whereNull('viability_request_cnaes.intencao')
                    ->orWhere('viability_request_cnaes.intencao', '!=', IntencaoAtividade::Excluir->value);
            })
            ->get();

        foreach ($cnaes as $cnae) {
            $digitos = (string) preg_replace('/\D/', '', (string) $cnae->code);

            if (in_array($digitos, $codigosRegra, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * RT-02 (Vela Branca) / RT-03 / RT-04: ponto dentro da poligonal →
     * análise com o motivo da regra. Camada ausente/indisponível → análise
     * registrando a indisponibilidade (CA-RT-09).
     *
     * @param  array{lat: float, lng: float}|null  $ponto
     * @return array{efeito: TerritorialRuleEffect, motivo: string}|null
     */
    private function casarPoligonal(TerritorialRule $regra, ?array $ponto): ?array
    {
        if ($ponto === null) {
            return [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => $this->motivoLocalizacaoIndisponivel($regra),
            ];
        }

        if (! is_string($regra->camada) || trim($regra->camada) === '') {
            return [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => $this->motivoCamadaIndisponivel($regra),
            ];
        }

        $hit = $this->wfs->consultar($regra->camada, $ponto['lat'], $ponto['lng'], $regra->camada_filtro);

        if ($hit->indisponivel()) {
            $this->camadaIndisponivel = true;

            return [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => $this->motivoCamadaIndisponivel($regra),
            ];
        }

        if ($hit->dentro()) {
            return [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => (string) $regra->motivo_analise,
            ];
        }

        return null;
    }

    /**
     * RT-02 (Itaigara): dentro da poligonal e FORA da allow-list de endereços
     * → análise com o motivo da regra. Na allow-list, a regra não encaminha —
     * estar na lista não defere, só devolve ao fluxo normal.
     *
     * @param  array{lat: float, lng: float}|null  $ponto
     * @return array{efeito: TerritorialRuleEffect, motivo: string}|null
     */
    private function casarPoligonalAllowlist(TerritorialRule $regra, ViabilityRequest $request, ?array $ponto): ?array
    {
        if ($ponto === null) {
            return [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => $this->motivoLocalizacaoIndisponivel($regra),
            ];
        }

        if (! is_string($regra->camada) || trim($regra->camada) === '') {
            return [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => $this->motivoCamadaIndisponivel($regra),
            ];
        }

        $hit = $this->wfs->consultar($regra->camada, $ponto['lat'], $ponto['lng'], $regra->camada_filtro);

        if ($hit->indisponivel()) {
            $this->camadaIndisponivel = true;

            return [
                'efeito' => TerritorialRuleEffect::EncaminharAnalise,
                'motivo' => $this->motivoCamadaIndisponivel($regra),
            ];
        }

        if (! $hit->dentro()) {
            return null;
        }

        if ($this->enderecoNaAllowlist($regra, $request)) {
            return null;
        }

        return [
            'efeito' => TerritorialRuleEffect::EncaminharAnalise,
            'motivo' => (string) $regra->motivo_analise,
        ];
    }

    /**
     * Casamento do logradouro: com CODLOG materializado, o código oficial
     * decide (o nome digitado não sobrepõe o dado espacial); sem CODLOG, cai
     * no nome normalizado do logradouro (parte distintiva, sem o tipo de via).
     */
    private function mesmoLogradouro(TerritorialRule $regra, ViabilityRequest $request): bool
    {
        $codLog = $request->cod_log;

        if (is_string($codLog) && trim($codLog) !== '') {
            return trim($codLog) === (string) $regra->cod_log;
        }

        return $this->nomeCasa($regra->logradouro_nome, $request->address_street);
    }

    /**
     * Allow-list de endereços (Itaigara): número de porta obrigatório nos dois
     * lados; o logradouro casa por CODLOG quando ambos têm, senão pelo nome
     * normalizado (cobre a entrada sem CODLOG — Hiperideal, OPEN-RT-2).
     */
    private function enderecoNaAllowlist(TerritorialRule $regra, ViabilityRequest $request): bool
    {
        $numero = $this->numeroPorta($request->address_number);

        if ($numero === null) {
            return false;
        }

        foreach ($regra->addresses as $endereco) {
            $numeroLista = $this->numeroPorta($endereco->numero_porta);

            if ($numeroLista === null || $numeroLista !== $numero) {
                continue;
            }

            $codLogLista = is_string($endereco->cod_log) ? trim($endereco->cod_log) : '';
            $codLog = is_string($request->cod_log) ? trim($request->cod_log) : '';

            if ($codLogLista !== '' && $codLog !== '') {
                if ($codLog === $codLogLista) {
                    return true;
                }

                continue;
            }

            if ($this->nomeCasa($endereco->logradouro_nome, $request->address_street)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Número de porta comparável: os dígitos iniciais do campo ("135 A" casa
     * com "135"), normalizado sem zeros à esquerda.
     */
    private function numeroPorta(?string $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        if (preg_match('/\d+/', $valor, $match) !== 1) {
            return null;
        }

        return (string) (int) $match[0];
    }

    /**
     * O nome casa quando o endereço contém a parte distintiva do logradouro
     * da regra (sem o tipo de via: "Avenida", "Rua"...). Normalização em
     * maiúsculas sem acento cobre "Av. Lafayette Coutinho" × "Avenida
     * Lafayette Coutinho".
     */
    private function nomeCasa(?string $nomeRegra, ?string $endereco): bool
    {
        if (! is_string($nomeRegra) || ! is_string($endereco)) {
            return false;
        }

        $distintivo = $this->nomeDistintivo($nomeRegra);

        if ($distintivo === '') {
            return false;
        }

        return str_contains($this->normalizar($endereco), $distintivo);
    }

    private function nomeDistintivo(string $nome): string
    {
        $norm = $this->normalizar($nome);
        $tipos = ['AVENIDA', 'RUA', 'ESTRADA', 'RODOVIA', 'TRAVESSA', 'ALAMEDA', 'PRACA', 'LARGO', 'LADEIRA', 'PARQUE'];
        $primeira = explode(' ', $norm)[0] ?? '';

        if (in_array($primeira, $tipos, true)) {
            $norm = trim(substr($norm, strlen($primeira)));
        }

        return $norm;
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));

        $texto = strtr($texto, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ]);

        return (string) preg_replace('/\s+/', ' ', $texto);
    }

    /**
     * Texto legal do indeferimento (RT-01): template administrável vinculado à
     * regra (decision_texts), com o alvo e o fundamento lidos da própria
     * regra. Sem vínculo, cai no template de fábrica do catálogo.
     */
    private function textoIndeferimento(TerritorialRule $regra): string
    {
        $alvo = trim('logradouro '.(string) $regra->cod_log.' – '.(string) $regra->logradouro_nome);

        $template = $regra->decisionText?->template
            ?? $this->textos->get('territorial.indeferimento.logradouro_cnae');

        return strtr($template, [
            ':alvo' => $alvo,
            ':fundamento' => (string) $regra->base_legal,
        ]);
    }

    private function motivoLocalizacaoIndisponivel(TerritorialRule $regra): string
    {
        return $this->textos->render('territorial.motivo.localizacao_indisponivel', [
            ':regra' => $regra->titulo,
        ]);
    }

    private function motivoCamadaIndisponivel(TerritorialRule $regra): string
    {
        return $this->textos->render('territorial.motivo.camada_indisponivel', [
            ':regra' => $regra->titulo,
        ]);
    }

    /**
     * Trilha RN-002 da avaliação: toda incidência (indeferir ou encaminhar)
     * é auditada com a versão do dado territorial.
     */
    private function auditar(ViabilityRequest $request, TerritorialRuleVerdict $verdict): void
    {
        $this->audit->log(
            logName: 'regras',
            event: 'regra-territorial',
            description: "Regra territorial {$verdict->regra?->codigo} ({$verdict->status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'status' => $verdict->status,
                'regra' => $verdict->regra?->codigo,
                'motivo' => $verdict->motivo,
                'incidencias' => $verdict->incidencias,
            ],
            subject: $request,
            result: $verdict->status,
            rulesVersion: $verdict->versaoRegra,
        );
    }
}
