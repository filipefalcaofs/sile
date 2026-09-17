<?php

namespace App\Services\Regin;

use App\Enums\Fluxo;
use App\Enums\RiscoMunicipal;
use App\Models\ReginSimulacaoExecucao;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Support\Audit\AuditService;

/**
 * Aplica o motor REAL sobre um protocolo SEDUR, com tipo de imóvel e área
 * tratados como se tivessem chegado do REGIN. Não chama o REGIN — a origem
 * no relatório é sempre simulacao_protocolo.
 */
class ReginProtocoloSimulacaoService
{
    public const AVISO = 'Dado aplicado como simulação: tipo de imóvel e área como se tivessem chegado do REGIN. A integração REGIN continua indisponível.';

    public function __construct(
        private ReginProtocoloCatalog $catalogo,
        private RiscoClassificationService $risco,
        private AuditService $audit,
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

        $relatorio = [
            'origem' => 'simulacao_protocolo',
            'aviso' => self::AVISO,
            'codigo' => $protocolo['codigo'],
            'rotulo' => $protocolo['rotulo'],
            'processo' => $protocolo['processo'],
            'servico' => $protocolo['servico'],
            'zona' => $protocolo['zona'] ?? null,
            'via' => $protocolo['via'] ?? null,
            'area_utilizada' => $area,
            'tipo_imovel' => $protocolo['tipo_imovel'],
            'tipo_imovel_normalized' => $tipo->normalized,
            'tipo_imovel_reconhecimento' => $tipo->reconhecimento->value,
            'tipo_imovel_dirige_regra' => $tipo->dirigeRegra(),
            'tipo_imovel_permite_decisao_automatica' => $tipo->permiteDecisaoAutomatica(),
            'por_cnae' => $porCnae,
            'consolidado' => $this->consolidar($porCnae),
        ];

        $this->persistir($relatorio);

        return $relatorio;
    }

    /**
     * Última execução persistida (a simulação que está na tela).
     *
     * @return array<string, mixed>|null
     */
    public function ultima(): ?array
    {
        $execucao = ReginSimulacaoExecucao::query()->latest('updated_at')->first();

        return is_array($execucao?->relatorio) ? $execucao->relatorio : null;
    }

    /**
     * Só o que foi simulado — nunca processo real.
     *
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
                    'consolidado' => $relatorio['consolidado'] ?? null,
                    'atualizado_em' => $execucao->updated_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    public function apagar(string $codigo): void
    {
        ReginSimulacaoExecucao::query()->where('codigo', $codigo)->delete();

        $this->audit->log(
            'risco',
            'simulacao-apagada',
            'Resultado da simulação REGIN apagado para refazer',
            ['codigo' => $codigo],
        );
    }

    /**
     * @param  array<string, mixed>  $relatorio
     */
    private function persistir(array $relatorio): void
    {
        ReginSimulacaoExecucao::query()->updateOrCreate(
            ['codigo' => $relatorio['codigo']],
            [
                'relatorio' => $relatorio,
                'user_id' => auth('gestao')->id(),
            ],
        );
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
