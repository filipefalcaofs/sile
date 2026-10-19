<?php

namespace App\Console\Commands;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use App\Models\BuildingFootprint;
use App\Models\BuildingFootprintImport;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class MetricasEdificacoesCommand extends Command
{
    protected $signature = 'geo:edificacoes:metricas
        {--desde= : Data inicial (AAAA-MM-DD); padrão: 30 dias atrás}
        {--json : Saída em JSON}';

    protected $description = 'Cobertura, base, latência e falhas do polígono do lote por fonte (oficiais e edificações complementares)';

    public function handle(): int
    {
        $desde = $this->option('desde')
            ? CarbonImmutable::parse((string) $this->option('desde'))->startOfDay()
            : CarbonImmutable::now()->subDays(30)->startOfDay();

        $metricas = [
            'desde' => $desde->toDateString(),
            ...$this->cobertura($desde),
            'base' => $this->base(),
            'latencia_ms' => $this->latencias($desde),
            'falhas' => $this->falhas($desde),
            'ia' => $this->ia($desde),
            'precisao' => 'sem medida automática; ver a amostragem mensal em docs/integracoes/edificacoes-complementares.md',
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($metricas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

            return self::SUCCESS;
        }

        $this->imprimir($metricas);

        return self::SUCCESS;
    }

    /**
     * @return array{processos: int, cobertura: array<string, float>}
     */
    private function cobertura(CarbonImmutable $desde): array
    {
        $processos = ViabilityRequest::query()->where('created_at', '>=', $desde)->get(['id', 'address_polygon_geojson', 'address_polygon_source']);
        $total = $processos->count();
        $porFonte = $processos->countBy(function (ViabilityRequest $processo): string {
            if (! is_array($processo->address_polygon_geojson) || $processo->address_polygon_geojson === []) {
                return 'sem_poligono';
            }

            return ($processo->address_polygon_source ?? PoligonoFonte::Chave)->value;
        });

        return [
            'processos' => $total,
            'cobertura' => $porFonte->map(fn (int $quantidade): float => $total === 0 ? 0.0 : round($quantidade * 100 / $total, 1))->all(),
        ];
    }

    /**
     * @return array<string, array{ativas: int, duplicadas: int, ultima_importacao: string|null}>
     */
    private function base(): array
    {
        $base = [];

        foreach (FonteEdificacao::cases() as $fonte) {
            $base[$fonte->value] = [
                'ativas' => BuildingFootprint::query()->where('source', $fonte)->whereNull('retired_at')->whereNull('duplicate_of_id')->count(),
                'duplicadas' => BuildingFootprint::query()->where('source', $fonte)->whereNotNull('duplicate_of_id')->count(),
                'ultima_importacao' => BuildingFootprintImport::query()
                    ->where('source', $fonte)
                    ->where('status', BuildingFootprintImport::CONCLUIDA)
                    ->latest('finished_at')
                    ->value('finished_at'),
            ];
        }

        return $base;
    }

    /**
     * @return array<string, array{p50: int, p95: int}>
     */
    private function latencias(CarbonImmutable $desde): array
    {
        return $this->resolucoes($desde)
            ->whereNotNull('latency_ms')
            ->groupBy(fn (LotPolygonResolution $resolucao): string => $resolucao->source->value)
            ->map(fn (Collection $grupo): array => [
                'p50' => $this->percentil($grupo->pluck('latency_ms'), 50),
                'p95' => $this->percentil($grupo->pluck('latency_ms'), 95),
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function falhas(CarbonImmutable $desde): array
    {
        return $this->resolucoes($desde)
            ->filter(fn (LotPolygonResolution $resolucao): bool => in_array($resolucao->status, [StatusResolucaoPoligono::Indisponivel, StatusResolucaoPoligono::NaoResolvido], true))
            ->groupBy(fn (LotPolygonResolution $resolucao): string => $resolucao->source->value)
            ->map(fn (Collection $grupo): array => $grupo->countBy(fn (LotPolygonResolution $resolucao): string => $resolucao->reason ?? 'sem_motivo')->all())
            ->all();
    }

    /**
     * @return array{tentativas: int, encontrados: int, nao_resolvidos: int, indisponiveis: int, latencia_media_ms: int|null}
     */
    private function ia(CarbonImmutable $desde): array
    {
        $ia = $this->resolucoes($desde)
            ->filter(fn (LotPolygonResolution $resolucao): bool => $resolucao->source === PoligonoFonte::Ia && $resolucao->status !== StatusResolucaoPoligono::Pendente);
        $latencias = $ia->whereNotNull('latency_ms')->pluck('latency_ms');

        return [
            'tentativas' => $ia->count(),
            'encontrados' => $ia->where('status', StatusResolucaoPoligono::Encontrado)->count(),
            'nao_resolvidos' => $ia->where('status', StatusResolucaoPoligono::NaoResolvido)->count(),
            'indisponiveis' => $ia->where('status', StatusResolucaoPoligono::Indisponivel)->count(),
            'latencia_media_ms' => $latencias->isEmpty() ? null : (int) round($latencias->avg()),
        ];
    }

    /**
     * @return Collection<int, LotPolygonResolution>
     */
    private function resolucoes(CarbonImmutable $desde): Collection
    {
        return LotPolygonResolution::query()->where('created_at', '>=', $desde)->get(['source', 'status', 'reason', 'latency_ms']);
    }

    /**
     * Percentil pelo posto mais próximo.
     *
     * @param  Collection<int, mixed>  $valores
     */
    private function percentil(Collection $valores, int $percentil): int
    {
        $ordenados = $valores->map(fn (mixed $valor): int => (int) $valor)->sort()->values();

        return $ordenados[max(0, (int) ceil($percentil / 100 * $ordenados->count()) - 1)];
    }

    /**
     * @param  array<string, mixed>  $metricas
     */
    private function imprimir(array $metricas): void
    {
        $this->info("Desde {$metricas['desde']} — {$metricas['processos']} processos");

        $this->line('Cobertura (% dos processos):');
        $this->table(['Fonte', '%'], collect($metricas['cobertura'])->map(fn (float $valor, string $fonte): array => [$fonte, $valor])->values()->all());

        $this->line('Base local:');
        $this->table(['Fonte', 'Ativas', 'Duplicadas', 'Última importação'], collect($metricas['base'])->map(fn (array $linha, string $fonte): array => [$fonte, ...array_values($linha)])->values()->all());

        $this->line('Latência (ms):');
        $this->table(['Fonte', 'p50', 'p95'], collect($metricas['latencia_ms'])->map(fn (array $linha, string $fonte): array => [$fonte, $linha['p50'], $linha['p95']])->values()->all());

        $this->line('Falhas por motivo:');
        foreach ($metricas['falhas'] as $fonte => $motivos) {
            foreach ($motivos as $motivo => $quantidade) {
                $this->line("  {$fonte} / {$motivo}: {$quantidade}");
            }
        }

        $this->line('IA: '.json_encode($metricas['ia'], JSON_UNESCAPED_UNICODE));
        $this->line("Precisão: {$metricas['precisao']}");
    }
}
