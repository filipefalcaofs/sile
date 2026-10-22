<?php

namespace App\Services\Geo;

use App\Enums\EtapaMotor;
use App\Enums\PropertyLocationSource;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Motor\EtapaMotorRegistrador;
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
        private readonly EtapaMotorRegistrador $etapas,
        private readonly GeoServerClassificacaoViariaClient $classificacaoViaria,
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

        $this->registrarEtapasDoTerritorio($request, $territorio);

        return $mudou;
    }

    /**
     * Etapa "Classe viária" pelo CODLOG do processo — a mesma fonte que o
     * enquadramento usa no Quadro 11A. Sem CODLOG, a etapa fica como a busca
     * da via pelo ponto a registrou.
     */
    public function registrarClasseViaria(ViabilityRequest $request): void
    {
        $codLog = is_string($request->cod_log) ? trim($request->cod_log) : '';

        if ($codLog === '') {
            return;
        }

        $hit = $this->classificacaoViaria->consultar($codLog);

        $this->etapas->registrar(
            $request,
            EtapaMotor::Via,
            match ($hit->status) {
                ClassificacaoViariaHit::IDENTIFICADO => SituacaoEtapa::Ok,
                ClassificacaoViariaHit::INDISPONIVEL => SituacaoEtapa::Indisponivel,
                ClassificacaoViariaHit::DESLIGADO => SituacaoEtapa::Pulada,
                default => SituacaoEtapa::Definitiva,
            },
            $hit->classe === null ? $hit->motivo : null,
        );

        $this->gravarViaNoInventario($request, $hit->classe);
    }

    private function gravarViaNoInventario(ViabilityRequest $request, ?string $classe): void
    {
        $camadas = $request->camadas_incidentes;

        if (! is_array($camadas) || ($camadas['via'] ?? null) === $classe) {
            return;
        }

        $camadas['via'] = $classe;

        $request->forceFill(['camadas_incidentes' => $camadas])->save();
    }

    /**
     * Sem polígono e sem ponto, grava o ponto do Cadastro Imobiliário da
     * inscrição. Se o cadastro trouxe logradouro e número sem coordenada, o
     * ponto gravado é o da geocodificação. Outro status não grava nada e
     * fica na auditoria. Devolve o status (LocalizacaoInscricao::*); processo
     * que já tem ponto conta como localizado.
     */
    public function localizarPelaInscricao(ViabilityRequest $request): string
    {
        if ($request->pontoGravadoOuDesenhado() !== null) {
            return LocalizacaoInscricao::LOCALIZADA;
        }

        $localizacao = $this->localizacao->localizar($request->property_registration);

        $this->etapas->registrar(
            $request,
            EtapaMotor::LocalizacaoInscricao,
            match ($localizacao->status) {
                LocalizacaoInscricao::LOCALIZADA => SituacaoEtapa::Ok,
                LocalizacaoInscricao::INDISPONIVEL => SituacaoEtapa::Indisponivel,
                LocalizacaoInscricao::SEM_INSCRICAO => SituacaoEtapa::Pulada,
                default => SituacaoEtapa::Definitiva,
            },
            $localizacao->status === LocalizacaoInscricao::LOCALIZADA
                ? null
                : "Localização pela inscrição: {$localizacao->status}",
        );

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

            return $localizacao->status;
        }

        $request->forceFill([
            'property_point_lat' => $localizacao->lat,
            'property_point_lng' => $localizacao->lng,
            'property_location_source' => $localizacao->origemPonto ?? PropertyLocationSource::CadastroImobiliario,
        ])->save();

        return LocalizacaoInscricao::LOCALIZADA;
    }

    /**
     * O logradouro do endereço de Localização da inscrição é o CODLOG do
     * processo — o tributário só na falta dele (ver
     * LocalizacaoInscricao::codLogDoEndereco). Quando a via mais próxima
     * tinha gravado outro código, a troca fica auditada.
     */
    public function aplicarLogradouroDaInscricao(ViabilityRequest $request): bool
    {
        $localizacao = $this->localizacao->localizar($request->property_registration);
        $codLog = $localizacao->codLogDoEndereco();

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
                description: "Logradouro da inscrição {$codLog} substitui o CODLOG {$anterior} gravado na solicitação #{$request->id}",
                properties: [
                    'viability_request_id' => $request->id,
                    'cod_log_anterior' => $anterior,
                    'cod_log_tributario' => $localizacao->codLogTributario,
                    'cod_log_localizacao' => $localizacao->codLogLocalizacao,
                    'cod_log_aplicado' => $codLog,
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

    /**
     * Zona, via e bairro viram etapas do motor: `indisponivel` é transitória e
     * reprocessável; `nao_encontrado` é definitiva.
     */
    private function registrarEtapasDoTerritorio(ViabilityRequest $request, TerritoryResult $territorio): void
    {
        foreach ([
            'zona' => EtapaMotor::Zona,
            'via' => EtapaMotor::Via,
            'bairro' => EtapaMotor::Bairro,
        ] as $dimensao => $etapa) {
            if ($etapa === EtapaMotor::Via && filled($request->cod_log)) {
                $this->registrarClasseViaria($request);

                continue;
            }

            $dados = $territorio->{$dimensao};

            $this->etapas->registrar(
                $request,
                $etapa,
                match ((string) ($dados['status'] ?? '')) {
                    'identificado' => SituacaoEtapa::Ok,
                    'indisponivel' => SituacaoEtapa::Indisponivel,
                    default => SituacaoEtapa::Definitiva,
                },
                isset($dados['motivo']) ? (string) $dados['motivo'] : null,
            );
        }
    }
}
