<?php

namespace App\Console\Commands;

use App\Models\ViabilityRequest;
use App\Services\Ai\AiFeatureGate;
use App\Services\Ai\Embeddings\PrecedentIndexer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Evidência/operação da infra de RAG (Fase 14, Onda 3): indexa por embeddings os
 * precedentes (HU-142 — solicitações DECIDIDAS) existentes, alimentando a busca
 * por similaridade que sustenta os assistentes (HU-120/121).
 *
 * Anti-fachada (mesmo portão do PrecedentIndexer): SEM provedor de embeddings
 * ATIVO é no-op HONESTO — não chama o provedor, não indexa e avisa que está
 * indisponível (nunca simula). IDEMPOTENTE: reexecutar não reembedda o que não
 * mudou (idempotência por content_hash do PrecedentIndexer) — poupa custo/quota.
 * Lê em lotes (chunkById) para não carregar todos os precedentes na memória.
 */
class IndexarPrecedentesCommand extends Command
{
    protected $signature = 'ai:indexar-precedentes';

    protected $description = 'Indexa por embeddings os precedentes decididos (HU-142) para a busca por similaridade dos assistentes; idempotente e no-op honesto sem provedor';

    public function handle(AiFeatureGate $gate, PrecedentIndexer $indexer): int
    {
        // Portão de disponibilidade (HU-014): sem provedor de embeddings ATIVO a
        // indexação fica indisponível — não chama, não persiste, não simula.
        if (! $gate->embeddingsAvailable()) {
            $this->warn('Indexação de precedentes indisponível: nenhum provedor de embeddings ativo. Nada foi indexado.');

            return self::SUCCESS;
        }

        $total = 0;
        $indexados = 0;
        $reaproveitados = 0;

        // Precedente = solicitação DECIDIDA (HU-142). chunkById pagina por id —
        // ordenação estável e memória limitada mesmo com muitos precedentes.
        ViabilityRequest::query()
            ->has('decision')
            ->chunkById(100, function (Collection $precedentes) use ($indexer, &$total, &$indexados, &$reaproveitados): void {
                foreach ($precedentes as $precedente) {
                    $total++;
                    $resultado = $indexer->index($precedente);

                    if ($resultado->embedded) {
                        $indexados++;
                    } elseif ($resultado->available) {
                        $reaproveitados++;
                    }
                }
            });

        if ($total === 0) {
            $this->info('Nenhum precedente (solicitação decidida) a indexar.');

            return self::SUCCESS;
        }

        $this->info("Indexados (novos/atualizados): {$indexados}; reaproveitados por idempotência: {$reaproveitados}; de {$total} precedente(s).");

        return self::SUCCESS;
    }
}
