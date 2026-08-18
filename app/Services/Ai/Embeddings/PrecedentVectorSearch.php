<?php

namespace App\Services\Ai\Embeddings;

use App\Services\Ai\AiFeatureGate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

/**
 * Busca por similaridade DIY dos precedentes (HU-142) — infra de RAG da Onda 3
 * (Fase 14) que sustenta o assistente do analista (HU-121). Implementação PRÓPRIA
 * porque o SimilaritySearch/whereVectorSimilarTo do laravel/ai v0.8.1 não existe.
 *
 * No PostgreSQL: gera o embedding da consulta e ordena os precedentes pela
 * distância cosine (`<=>`) do pgvector, usando o índice HNSW. Fora do pgsql (ex.:
 * SQLite da suíte) ou sem provedor de embeddings ativo, degrada HONESTO — devolve
 * vazio, sem fingir ranking e sem chamar o provedor à toa.
 */
class PrecedentVectorSearch
{
    public function __construct(private readonly AiFeatureGate $gate) {}

    /**
     * Os K precedentes mais similares à consulta, do mais próximo ao mais distante.
     *
     * @return Collection<int, array{viability_request_id: int, protocol_number: ?string, outcome: ?string, distance: float}>
     */
    public function maisSimilares(string $consulta, int $k): Collection
    {
        // Busca vetorial só existe onde há pgvector. Em SQLite degrada para vazio
        // (não finge ranking) — e nem chega a embeddar a consulta (sem custo).
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return new Collection;
        }

        // Sem provedor de embeddings ativo não há como representar a consulta.
        if (! $this->gate->embeddingsAvailable() || $k < 1) {
            return new Collection;
        }

        $vector = Embeddings::for([$consulta])
            ->dimensions($this->dimensions())
            ->generate()
            ->first();

        $literal = VectorLiteral::format($vector);

        // ORDER BY pela EXPRESSÃO do operador (não pelo alias) para o índice HNSW
        // ser usado; a distância também é projetada para o consumidor pontuar.
        $rows = DB::select(
            <<<'SQL'
            SELECT
                vr.id AS viability_request_id,
                vr.protocol_number,
                vd.outcome,
                (ape.embedding <=> ?::vector) AS distance
            FROM ai_precedent_embeddings ape
            JOIN viability_requests vr ON vr.id = ape.viability_request_id
            LEFT JOIN viability_decisions vd ON vd.viability_request_id = vr.id
            ORDER BY ape.embedding <=> ?::vector
            LIMIT ?
            SQL,
            [$literal, $literal, $k],
        );

        return (new Collection($rows))->map(fn (object $row): array => [
            'viability_request_id' => (int) $row->viability_request_id,
            'protocol_number' => $row->protocol_number,
            'outcome' => $row->outcome,
            'distance' => (float) $row->distance,
        ]);
    }

    private function dimensions(): int
    {
        return (int) config('sile.ai.embeddings.dimensions', 1536);
    }
}
