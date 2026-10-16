<?php

namespace App\Console\Commands;

use App\Models\TvlSequence;
use App\Services\Expresso\TvlNumberGenerator;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Migra os números TVL já emitidos (TVL-AAAA-NNNNNN) para a sequência única
 * viabiliza.expresso.tvl.inicio em diante (TVL-3000000...), que é a que segue à
 * SEFAZ. A ordem é a da decisão (decided_at, id), então a numeração conta a
 * história do sistema. Sem --executar só simula. Idempotente: números já no novo
 * formato são ignorados e uma nova rodada continua do contador. Os vínculos de
 * escritório virtual (virtual_office_hq_tvl_number) acompanham a sede, e o mapa
 * antigo→novo é gravado em CSV porque PDFs já entregues mantêm o número antigo.
 */
class TvlRenumerarCommand extends Command
{
    protected $signature = 'tvl:renumerar {--executar : Grava a renumeração (sem a opção, apenas simula)}';

    protected $description = 'Renumera os TVL existentes para a sequência única a partir de 3000000 (a que segue à SEFAZ)';

    public function handle(TvlNumberGenerator $gerador, AuditService $audit): int
    {
        $legados = DB::table('viability_decisions')
            ->whereNotNull('tvl_product_number')
            ->orderBy('decided_at')
            ->orderBy('id')
            ->get(['id', 'tvl_product_number'])
            ->filter(fn (object $linha): bool => preg_match('/-\d{4}-\d+$/', $linha->tvl_product_number) === 1)
            ->values();

        if ($legados->isEmpty()) {
            $this->info('Nenhum TVL no formato antigo para renumerar.');

            return self::SUCCESS;
        }

        $contador = TvlSequence::query()->where('year', TvlNumberGenerator::CONTADOR_UNICO)->first();
        $proximo = max(
            TvlNumberGenerator::numeroInicial(),
            $contador === null ? 0 : (int) $contador->last_number + 1,
        );

        $mapa = [];
        foreach ($legados as $indice => $linha) {
            $mapa[$linha->tvl_product_number] = [
                'id' => $linha->id,
                'novo' => $gerador->format($proximo + $indice),
            ];
        }
        $ultimo = $proximo + $legados->count() - 1;

        $this->info(sprintf(
            '%d TVL a renumerar: %s -> %s.',
            $legados->count(),
            $gerador->format($proximo),
            $gerador->format($ultimo),
        ));

        if (! $this->option('executar')) {
            $this->warn('Simulação: nada foi gravado. Rode com --executar para aplicar.');

            return self::SUCCESS;
        }

        $arquivo = 'tvl-renumeracao/'.now()->format('Ymd-His').'.csv';
        $csv = "decision_id;numero_antigo;numero_novo\n";
        foreach ($mapa as $antigo => $dados) {
            $csv .= "{$dados['id']};{$antigo};{$dados['novo']}\n";
        }
        Storage::disk('local')->put($arquivo, $csv);

        DB::transaction(function () use ($mapa, $ultimo): void {
            // Duas passagens: o unique(tvl_product_number) nunca vê duplicata,
            // pois os valores novos não colidem com nenhum antigo.
            foreach ($mapa as $antigo => $dados) {
                DB::table('viability_decisions')->where('id', $dados['id'])->update(['tvl_product_number' => $dados['novo']]);
                DB::table('viability_decisions')->where('virtual_office_hq_tvl_number', $antigo)->update(['virtual_office_hq_tvl_number' => $dados['novo']]);
            }

            TvlSequence::query()->updateOrCreate(
                ['year' => TvlNumberGenerator::CONTADOR_UNICO],
                ['last_number' => $ultimo],
            );
        });

        $audit->log(
            'integracoes',
            'tvl-renumeracao',
            sprintf('%d números TVL renumerados para a sequência única', $legados->count()),
            properties: ['quantidade' => $legados->count(), 'ultimo' => $gerador->format($ultimo), 'mapa' => $arquivo],
        );

        $this->info("Renumeração gravada. Mapa antigo -> novo: storage/app/private/{$arquivo}");

        return self::SUCCESS;
    }
}
