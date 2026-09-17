<?php

namespace App\Services\Regin;

use App\Enums\Fluxo;
use App\Enums\RiscoMunicipal;
use App\Enums\ViabilityRequestOrigin;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ReginSimulacaoExecucao;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Support\Audit\AuditService;

/**
 * Aplica o motor REAL sobre um protocolo SEDUR, com tipo de imóvel e área
 * tratados como se tivessem chegado do REGIN. Cria o processo real e segue
 * o motor: Alto → análise; Baixo/Médio → expresso (TVL). Não chama o REGIN.
 */
class ReginProtocoloSimulacaoService
{
    public const AVISO = 'Dado aplicado como simulação: tipo de imóvel e área como se tivessem chegado do REGIN. O processo é criado de verdade. A integração REGIN continua indisponível.';

    public const CONTINGENCIA = 'simulacao_protocolo';

    public const CONTINGENCIA_RECEBE = 'regin_recebe';

    /**
     * Polígono de homologação em Salvador — a base GIS oficial ainda não
     * participa desta simulação; o motor de risco é que decide o encaminhamento.
     *
     * @var array{type: string, coordinates: list<list<list<float>>>}
     */
    private const POLIGONO_HOMOLOGACAO = [
        'type' => 'Polygon',
        'coordinates' => [[
            [-38.5108, -12.9711],
            [-38.5108, -12.9709],
            [-38.5106, -12.9709],
            [-38.5106, -12.9711],
            [-38.5108, -12.9711],
        ]],
    ];

    public function __construct(
        private ReginProtocoloCatalog $catalogo,
        private RiscoClassificationService $risco,
        private AuditService $audit,
        private ProtocolarSolicitacaoService $protocolar,
        private FluxoExpressoService $expresso,
        private ReginTipoImovelApplier $tipoImovel,
        private DocumentRequirementResolver $documentos,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listar(): array
    {
        return array_map(fn (array $p): array => [
            'codigo' => $p['codigo'],
            'rotulo' => $p['rotulo'],
            'processo' => $p['processo'],
            'servico' => $p['servico'],
            'tipo_imovel' => $p['tipo_imovel'],
            'area_utilizada' => $p['area_utilizada'],
            'zona' => $p['zona'],
            'via' => $p['via'],
            'atividades' => count($p['atividades'] ?? []),
        ], $this->catalogo->todos());
    }

    /**
     * @return array<string, mixed>
     */
    public function simular(string $codigo): array
    {
        $protocolo = $this->catalogo->porCodigo($codigo);
        $relatorio = $this->relatorio($protocolo, origem: self::CONTINGENCIA);

        $processo = $this->processoExistente($relatorio['codigo'], (string) $protocolo['processo'])
            ?? $this->criarProcesso($protocolo, $relatorio, self::CONTINGENCIA);

        $relatorio = $this->anexarProcesso($relatorio, $processo);

        $this->persistir($relatorio, $processo->id);

        return $relatorio;
    }

    public function materializarDoCatalogo(string $codigo, string $contingencia = self::CONTINGENCIA_RECEBE): ?ViabilityRequest
    {
        try {
            $protocolo = $this->catalogo->porCodigo($codigo);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $existente = ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->where('external_reference', (string) $protocolo['processo'])
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return $this->criarProcesso($protocolo, $this->relatorio($protocolo, origem: $contingencia), $contingencia);
    }

    /**
     * Última execução persistida (a simulação que está na tela).
     *
     * @return array<string, mixed>|null
     */
    public function ultima(): ?array
    {
        $execucao = ReginSimulacaoExecucao::query()->latest('id')->first();

        return is_array($execucao?->relatorio) ? $execucao->relatorio : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function execucoes(): array
    {
        return ReginSimulacaoExecucao::query()
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (ReginSimulacaoExecucao $execucao): array {
                $relatorio = is_array($execucao->relatorio) ? $execucao->relatorio : [];

                return [
                    'codigo' => $execucao->codigo,
                    'rotulo' => $relatorio['rotulo'] ?? $execucao->codigo,
                    'processo' => $relatorio['processo'] ?? null,
                    'processo_id' => $relatorio['processo_id'] ?? $execucao->viability_request_id,
                    'protocol_number' => $relatorio['protocol_number'] ?? null,
                    'status' => $relatorio['status'] ?? null,
                    'tvl' => $relatorio['tvl'] ?? null,
                    'consolidado' => $relatorio['consolidado'] ?? null,
                    'atualizado_em' => $execucao->updated_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    public function apagar(string $codigo): void
    {
        $execucao = ReginSimulacaoExecucao::query()->where('codigo', $codigo)->first();

        if ($execucao?->viability_request_id) {
            $this->apagarProcesso((int) $execucao->viability_request_id);
        }

        $execucao?->delete();

        $this->audit->log(
            'risco',
            'simulacao-apagada',
            'Resultado da simulação REGIN e o processo criado foram apagados para refazer',
            ['codigo' => $codigo],
        );
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function persistir(array $relatorio, int $processoId): void
    {
        ReginSimulacaoExecucao::query()->where('codigo', $relatorio['codigo'])->delete();

        ReginSimulacaoExecucao::query()->create([
            'codigo' => $relatorio['codigo'],
            'relatorio' => $relatorio,
            'user_id' => auth('gestao')->id(),
            'viability_request_id' => $processoId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $protocolo
     * @return array<string, mixed>
     */
    private function relatorio(array $protocolo, string $origem): array
    {
        $tipo = TipoImovel::fromRegin(
            isset($protocolo['tipo_imovel']) ? (string) $protocolo['tipo_imovel'] : null,
            TipoImovelCatalog::sedur200826(),
        );
        $area = isset($protocolo['area_utilizada']) ? (float) $protocolo['area_utilizada'] : null;
        $porCnae = [];

        foreach ($protocolo['atividades'] ?? [] as $atividade) {
            $cnae = (string) ($atividade['cnae'] ?? '');
            $respostas = $this->respostas($atividade['perguntas'] ?? []);

            $result = $this->risco->classify(new RiscoInput(
                cnaeCode: $cnae,
                respostasCondicionantes: $respostas,
                areaUtilizada: $area,
                tipoImovel: $tipo,
            ));

            $porCnae[] = [
                'cnae' => $cnae,
                'perguntas' => $atividade['perguntas'] ?? [],
                'risco' => $result->toArray(),
            ];
        }

        return [
            'origem' => $origem,
            'aviso' => $origem === self::CONTINGENCIA ? self::AVISO : 'Processo ingressado pelo REGIN (/recebe).',
            'codigo' => $protocolo['codigo'],
            'rotulo' => $protocolo['rotulo'],
            'processo' => $protocolo['processo'],
            'servico' => $protocolo['servico'],
            'zona' => $protocolo['zona'] ?? null,
            'via' => $protocolo['via'] ?? null,
            'area_utilizada' => $area,
            'tipo_imovel' => $protocolo['tipo_imovel'] ?? null,
            'tipo_imovel_normalized' => $tipo->normalized,
            'tipo_imovel_reconhecimento' => $tipo->reconhecimento->value,
            'tipo_imovel_dirige_regra' => $tipo->dirigeRegra(),
            'tipo_imovel_permite_decisao_automatica' => $tipo->permiteDecisaoAutomatica(),
            'por_cnae' => $porCnae,
            'consolidado' => $this->consolidar($porCnae),
        ];
    }

    private function criarProcesso(array $protocolo, array $relatorio, string $contingencia = self::CONTINGENCIA): ViabilityRequest
    {
        $ator = $this->ator();
        $sedeVirtual = $this->querSedeVirtual($protocolo);

        $solicitacao = ViabilityRequest::query()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'service_type_id' => $this->tipoServico()->id,
            'company_id' => $this->empresaPlaceholder($protocolo)->id,
            'requester_user_id' => $ator->id,
            'created_by_user_id' => $ator->id,
            'used_area_m2' => $relatorio['area_utilizada'] ?? 1.0,
            'address_street' => $contingencia === self::CONTINGENCIA ? 'Simulação REGIN' : 'REGIN',
            'address_number' => 's/n',
            'address_neighborhood' => (string) ($protocolo['zona'] ?? 'Salvador'),
            'address_zip' => '40000000',
            'property_polygon_geojson' => self::POLIGONO_HOMOLOGACAO,
            'is_virtual_office' => $sedeVirtual,
            'wants_virtual_office_hq' => $sedeVirtual,
            'simulation_snapshot' => $relatorio,
            'simulation_resultado' => $relatorio['consolidado']['fluxo'] ?? null,
            'simulated_at' => now(),
            'contingency_reason' => $contingencia,
            'external_reference' => (string) $protocolo['processo'],
        ]);

        foreach (array_values($protocolo['atividades'] ?? []) as $indice => $atividade) {
            $cnae = $this->cnaeDoCatalogo((string) ($atividade['cnae'] ?? ''));
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        $this->tipoImovel->apply(
            $solicitacao,
            isset($protocolo['tipo_imovel']) ? (string) $protocolo['tipo_imovel'] : null,
        );

        $this->anexarDocumentosObrigatorios($solicitacao, $ator);
        $this->protocolar->protocol($solicitacao->fresh() ?? $solicitacao, $ator);
        $this->expresso->decide($solicitacao->fresh() ?? $solicitacao, $ator);

        return $solicitacao->fresh() ?? $solicitacao;
    }

    private function processoExistente(string $codigo, string $referencia): ?ViabilityRequest
    {
        $execucao = ReginSimulacaoExecucao::query()->where('codigo', $codigo)->first();

        if ($execucao?->viability_request_id) {
            $ligado = ViabilityRequest::query()->find($execucao->viability_request_id);

            if ($ligado !== null) {
                return $ligado;
            }
        }

        return ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->where('contingency_reason', self::CONTINGENCIA)
            ->where('external_reference', $referencia)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $relatorio
     * @return array<string, mixed>
     */
    private function anexarProcesso(array $relatorio, ViabilityRequest $processo): array
    {
        $processo->loadMissing('decision');

        $relatorio['processo_id'] = $processo->id;
        $relatorio['protocol_number'] = $processo->protocol_number;
        $relatorio['status'] = $processo->status->value;
        $relatorio['tvl'] = $processo->decision?->tvl_product_number;
        $relatorio['processo_url'] = route('gestao.processos.show', $processo, absolute: false);

        return $relatorio;
    }

    /**
     * @param  array<string, mixed>  $protocolo
     */
    private function empresaPlaceholder(array $protocolo): Company
    {
        return Company::factory()->create([
            'legal_name' => $protocolo['rotulo'].' (simulação REGIN)',
            'trade_name' => (string) $protocolo['processo'],
        ]);
    }

    private function tipoServico(): ViabilityServiceType
    {
        return ViabilityServiceType::query()->firstOrCreate(
            ['code' => 'tvl'],
            [
                'name' => 'Termo de Viabilidade de Localização',
                'flow_hint' => 'expresso',
                'active' => true,
            ],
        );
    }

    private function cnaeDoCatalogo(string $formatado): Cnae
    {
        $code = (string) preg_replace('/\D/', '', $formatado);

        return Cnae::query()->firstOrCreate(
            ['code' => $code],
            [
                'description' => "CNAE {$formatado} (simulação REGIN)",
                'section_code' => 'S',
                'section_description' => 'Simulação REGIN',
                'division_code' => substr($code, 0, 2) ?: '00',
                'division_description' => 'Simulação REGIN',
                'group_code' => substr($code, 0, 3) ?: '000',
                'group_description' => 'Simulação REGIN',
                'class_code' => substr($code, 0, 5) ?: '00000',
                'class_description' => 'Simulação REGIN',
                'active' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $protocolo
     */
    private function querSedeVirtual(array $protocolo): bool
    {
        foreach ($protocolo['atividades'] ?? [] as $atividade) {
            foreach ($atividade['perguntas'] ?? [] as $pergunta) {
                if (($pergunta['codigo'] ?? '') === 'P4' && ($pergunta['valor'] ?? false) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    private function anexarDocumentosObrigatorios(ViabilityRequest $solicitacao, User $ator): void
    {
        foreach ($this->documentos->missing($solicitacao) as $requisito) {
            $solicitacao->documents()->create([
                'requirement_id' => $requisito->id,
                'disk' => 'local',
                'path' => "simulacao-regin/{$solicitacao->id}/{$requisito->code}.pdf",
                'original_name' => $requisito->code.'.pdf',
                'mime_type' => 'application/pdf',
                'size' => 128,
                'sha256' => hash('sha256', "simulacao-regin-{$solicitacao->id}-{$requisito->id}"),
                'uploaded_by_user_id' => $ator->id,
            ]);
        }
    }

    private function ator(): User
    {
        $autenticado = auth('gestao')->user();

        if ($autenticado instanceof User) {
            return $autenticado;
        }

        $existente = User::query()->orderBy('id')->first();

        if ($existente !== null) {
            return $existente;
        }

        return User::factory()->administrador()->create();
    }

    private function apagarProcesso(int $id): void
    {
        $processo = ViabilityRequest::query()->find($id);

        if ($processo === null) {
            return;
        }

        $processo->expressoQuedas()->delete();
        $processo->analysisRecords()->delete();
        $processo->decision()->delete();
        $processo->documents()->delete();
        $processo->analysisStatusTransitions()->delete();
        $processo->transitions()->delete();
        $processo->cnaes()->detach();
        $processo->delete();
    }

    /**
     * Viabilidade do estabelecimento: o CNAE mais gravoso do conjunto governa
     * o nível e o encaminhamento. Classificação por atividade é só composição.
     *
     * @param  list<array<string, mixed>>  $porCnae
     * @return array{cnae: ?string, nivel: ?string, nivel_label: ?string, fluxo: string, motivo: string}
     */
    private function consolidar(array $porCnae): array
    {
        $decisivo = null;
        $maior = -1;
        $fluxo = Fluxo::Expresso->value;

        foreach ($porCnae as $item) {
            $municipal = $item['risco']['municipal'] ?? [];
            $encaminhamento = $item['risco']['encaminhamento'] ?? [];
            $itemFluxo = (string) ($encaminhamento['fluxo'] ?? Fluxo::Analise->value);

            if ($itemFluxo !== Fluxo::Expresso->value) {
                $fluxo = Fluxo::Analise->value;
            }

            $nivel = $municipal['nivel'] ?? null;
            $severidade = 4;

            if (is_string($nivel)) {
                $severidade = RiscoMunicipal::tryFrom($nivel)?->severity() ?? 4;
            }

            if ($severidade > $maior) {
                $maior = $severidade;
                $decisivo = $item;
            }
        }

        $nivel = $decisivo['risco']['municipal']['nivel'] ?? null;
        $cnae = $decisivo['cnae'] ?? null;
        $label = is_string($nivel)
            ? (RiscoMunicipal::tryFrom($nivel)?->label() ?? $decisivo['risco']['municipal']['nivel_label'] ?? null)
            : ($decisivo['risco']['municipal']['nivel_label'] ?? $decisivo['risco']['municipal']['status'] ?? null);

        $motivo = $cnae === null
            ? 'Sem atividades para classificar o conjunto.'
            : "Viabilidade do conjunto: CNAE {$cnae} é o mais gravoso ({$label}) e governa o estabelecimento.";

        return [
            'cnae' => $cnae,
            'nivel' => is_string($nivel) ? $nivel : null,
            'nivel_label' => is_string($label) ? $label : null,
            'fluxo' => $fluxo,
            'motivo' => $motivo,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $perguntas
     * @return array<int|string, bool>
     */
    private function respostas(array $perguntas): array
    {
        $mapa = [];

        foreach ($perguntas as $pergunta) {
            if (! array_key_exists('valor', $pergunta) || ! is_bool($pergunta['valor'])) {
                continue;
            }

            if (! empty($pergunta['codigo'])) {
                $mapa[(string) $pergunta['codigo']] = $pergunta['valor'];
            }

            if (! empty($pergunta['texto'])) {
                $mapa[(string) $pergunta['texto']] = $pergunta['valor'];
            }
        }

        return $mapa;
    }
}
