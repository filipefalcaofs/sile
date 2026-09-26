<?php

namespace App\Services\Geo;

use App\Enums\PropertyLocationSource;
use App\Models\ViabilityRequest;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Support\Audit\AuditService;

/**
 * Materialização do território no processo (Onda GIS): com o acesso full ao
 * GIS/SEDUR validado em produção, a zona oficial (GeoServer WFS), o bairro
 * oficial (camada GeoSalvador versionada) e o CODLOG do logradouro (atributo
 * da camada de vias) deixam de ser consultados ao vivo e descartados — são
 * GRAVADOS em viability_requests (zona_codigo / bairro_oficial / cod_log) na
 * identificação do imóvel e como rede de segurança no protocolo. Write-once
 * e conservador: só escreve quando a dimensão foi IDENTIFICADA;
 * indisponível/não encontrado NUNCA sobrescreve um valor já materializado
 * (a degradação não apaga dado bom). Sem ponto do imóvel (`pontoDoImovel()`),
 * nada a materializar.
 */
class TerritorioProcessoService
{
    public function __construct(
        private readonly TerritoryService $territory,
        private readonly LocalizacaoInscricaoService $localizacao,
        private readonly AuditService $audit,
    ) {}

    /**
     * Materializa zona/bairro no processo. Reusa um TerritoryResult já
     * computado quando o chamador o tem (a tela do imóvel já identificou —
     * evita a segunda consulta WFS); senão identifica pelo centroide do
     * polígono. Retorna true quando gravou algo.
     */
    public function materializar(ViabilityRequest $request, ?TerritoryResult $territorio = null): bool
    {
        if ($territorio === null) {
            $ponto = $request->pontoDoImovel();

            if ($ponto === null) {
                return false;
            }

            $territorio = $this->territory->identify($ponto['lat'], $ponto['lng']);
        }

        $mudou = false;

        $zona = $this->valorIdentificado($territorio->zona);
        if ($zona !== null && $request->zona_codigo !== $zona) {
            $request->zona_codigo = $zona;
            $mudou = true;
        }

        $bairro = $this->valorIdentificado($territorio->bairro);
        if ($bairro !== null && $request->bairro_oficial !== $bairro) {
            $request->bairro_oficial = $bairro;
            $mudou = true;
        }

        $codLog = $this->codLogIdentificado($territorio->via);
        if ($codLog !== null && $request->cod_log !== $codLog) {
            $request->cod_log = $codLog;
            $mudou = true;
        }

        if ($mudou) {
            // Colunas fora do fillable (escritas pelo sistema, nunca pelo
            // cidadão) — mesmo padrão das colunas de análise.
            $request->forceFill([
                'zona_codigo' => $request->zona_codigo,
                'bairro_oficial' => $request->bairro_oficial,
                'cod_log' => $request->cod_log,
            ])->save();
        }

        return $mudou;
    }

    /**
     * Sem polígono e sem ponto, grava o ponto do Cadastro Imobiliário da
     * inscrição. Outro status (indisponível, sem coordenada, sem inscrição)
     * não grava nada e fica na auditoria — nunca um ponto inventado.
     */
    public function localizarPelaInscricao(ViabilityRequest $request): bool
    {
        if ($request->pontoDoImovel() !== null) {
            return false;
        }

        $localizacao = $this->localizacao->localizar($request->property_registration);

        if ($localizacao->status !== LocalizacaoInscricao::LOCALIZADA) {
            $this->audit->log(
                logName: 'geo',
                event: 'localizacao-inscricao',
                description: "Localização pela inscrição não gravada ({$localizacao->status}) na solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'inscricao' => $request->property_registration,
                    'status' => $localizacao->status,
                ],
                subject: $request,
                result: $localizacao->status,
            );

            return false;
        }

        $request->forceFill([
            'property_point_lat' => $localizacao->lat,
            'property_point_lng' => $localizacao->lng,
            'property_location_source' => PropertyLocationSource::CadastroImobiliario,
        ])->save();

        return true;
    }

    /**
     * O logradouro tributário da inscrição é o CODLOG do processo. Quando a
     * via mais próxima tinha gravado outro código, a troca fica auditada.
     */
    public function aplicarLogradouroTributario(ViabilityRequest $request): bool
    {
        $codLog = $this->localizacao->localizar($request->property_registration)->codLogTributario;

        if ($codLog === null) {
            return false;
        }

        $anterior = is_string($request->cod_log) ? trim($request->cod_log) : '';

        if ($anterior === $codLog) {
            return false;
        }

        if ($anterior !== '') {
            $this->audit->log(
                logName: 'geo',
                event: 'logradouro-tributario-diverge',
                description: "Logradouro tributário {$codLog} substitui o CODLOG {$anterior} da via mais próxima na solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'cod_log_anterior' => $anterior,
                    'cod_log_tributario' => $codLog,
                ],
                subject: $request,
                result: 'divergente',
            );
        }

        $request->forceFill(['cod_log' => $codLog])->save();

        return true;
    }

    /**
     * CODLOG da via identificada (atributo oficial do cadastro municipal nas
     * propriedades da feição). SÓ quando a via foi identificada e o atributo
     * veio preenchido — indisponível/não encontrado/sem atributo devolvem
     * null e nunca sobrescrevem o materializado.
     *
     * @param  array<string, mixed>  $via
     */
    private function codLogIdentificado(array $via): ?string
    {
        if (($via['status'] ?? null) !== 'identificado') {
            return null;
        }

        $codLog = $via['propriedades']['CODLOG'] ?? null;

        if (! is_string($codLog) && ! is_numeric($codLog)) {
            return null;
        }

        $codLog = trim((string) $codLog);

        return $codLog !== '' ? $codLog : null;
    }

    /**
     * Nome/código da dimensão SÓ quando identificado — indisponível ou não
     * encontrado devolvem null (nunca sobrescrevem o materializado).
     *
     * @param  array<string, mixed>  $dim
     */
    private function valorIdentificado(array $dim): ?string
    {
        if (($dim['status'] ?? null) !== 'identificado') {
            return null;
        }

        $nome = $dim['nome'] ?? null;

        return is_string($nome) && trim($nome) !== '' ? trim($nome) : null;
    }
}
