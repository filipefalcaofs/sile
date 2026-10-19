<?php

namespace App\Services\Geo\Edificacoes;

use App\Enums\FonteEdificacao;
use App\Models\BuildingFootprint;
use App\Models\BuildingFootprintImport;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Carga idempotente de edificações complementares. A mesma fonte, release e
 * checksum já concluída não é reimportada. Tudo roda numa transação: falha no
 * meio desfaz a carga e a base anterior continua valendo. Ao fim, o que a
 * fonte não trouxe nesta carga é aposentado.
 */
class ImportadorEdificacoes
{
    public function __construct(private BuildingFootprintRepository $repositorio) {}

    /**
     * @param  iterable<array<string, mixed>>  $feicoes
     * @param  callable(array<string, mixed>): (array{source_id: string, geojson: array<string, mixed>, confidence: float|null, source_datasets: list<array<string, mixed>>|null, properties: array<string, mixed>}|null)  $mapear
     */
    public function importar(FonteEdificacao $fonte, string $release, string $checksum, iterable $feicoes, callable $mapear): BuildingFootprintImport
    {
        $jaConcluida = BuildingFootprintImport::query()
            ->where('source', $fonte)
            ->where('release', $release)
            ->where('checksum', $checksum)
            ->where('status', BuildingFootprintImport::CONCLUIDA)
            ->exists();

        if ($jaConcluida) {
            return BuildingFootprintImport::query()->create([
                'source' => $fonte,
                'release' => $release,
                'checksum' => $checksum,
                'status' => BuildingFootprintImport::IGNORADA,
                'counters' => [],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $importacao = BuildingFootprintImport::query()->create([
            'source' => $fonte,
            'release' => $release,
            'checksum' => $checksum,
            'status' => BuildingFootprintImport::EM_ANDAMENTO,
            'counters' => [],
            'started_at' => now(),
        ]);

        $contadores = [
            'lidos' => 0, 'fora_do_limite' => 0, 'invalidos' => 0, 'corrigidos' => 0, 'duplicados' => 0,
            'inseridos' => 0, 'atualizados' => 0, 'aposentados' => 0, 'aposentadoria_suspensa' => 0,
        ];
        $ativasAntes = BuildingFootprint::query()->where('source', $fonte)->whereNull('retired_at')->count();

        try {
            DB::transaction(function () use ($fonte, $release, $feicoes, $mapear, $importacao, $ativasAntes, &$contadores): void {
                foreach ($feicoes as $feicao) {
                    $contadores['lidos']++;
                    $this->importarFeicao($fonte, $release, $feicao, $mapear, $importacao->id, $contadores);
                }

                // Carga bem menor que a base atual é parcial (um tile, arquivo
                // cortado): aposentar o resto apagaria a cidade.
                $gravadas = $contadores['inseridos'] + $contadores['atualizados'];
                $fracaoMinima = (float) config('viabiliza.edificacoes.aposentadoria_fracao_minima', 0.5);

                if ($ativasAntes > 0 && $gravadas < $ativasAntes * $fracaoMinima) {
                    $contadores['aposentadoria_suspensa'] = 1;

                    return;
                }

                $contadores['aposentados'] = $this->repositorio->aposentarAusentes($fonte, $importacao->id);
            });
        } catch (Throwable $e) {
            $importacao->forceFill([
                'status' => BuildingFootprintImport::FALHOU,
                'counters' => $contadores,
                'finished_at' => now(),
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            return $importacao;
        }

        $importacao->forceFill([
            'status' => BuildingFootprintImport::CONCLUIDA,
            'counters' => $contadores,
            'finished_at' => now(),
        ])->save();

        return $importacao;
    }

    /**
     * @param  array<string, mixed>  $feicao
     * @param  array<string, int>  $contadores
     */
    private function importarFeicao(FonteEdificacao $fonte, string $release, array $feicao, callable $mapear, int $importId, array &$contadores): void
    {
        $dados = $mapear($feicao);

        if ($dados === null) {
            $contadores['invalidos']++;

            return;
        }

        $normal = $this->repositorio->normalizar($dados['geojson']);

        if (! $normal['valida']) {
            $contadores['invalidos']++;

            return;
        }

        if (! $this->repositorio->dentroDoLimite($normal['geojson'])) {
            $contadores['fora_do_limite']++;

            return;
        }

        if (in_array('geometria_corrigida', $normal['notas'], true)) {
            $contadores['corrigidos']++;
        }

        $duplicadoDe = $fonte === FonteEdificacao::Microsoft ? $this->repositorio->duplicadoOverture($normal['geojson']) : null;

        if ($duplicadoDe !== null) {
            $contadores['duplicados']++;
        }

        $resultado = $this->repositorio->upsert(
            fonte: $fonte,
            sourceId: $dados['source_id'],
            release: $release,
            geojson: $normal['geojson'],
            areaM2: (float) $normal['area_m2'],
            confidence: $dados['confidence'],
            sourceDatasets: $dados['source_datasets'],
            properties: $dados['properties'],
            importId: $importId,
            duplicateOfId: $duplicadoDe,
            notas: $normal['notas'],
        );

        $contadores[$resultado['inserido'] ? 'inseridos' : 'atualizados']++;
    }
}
