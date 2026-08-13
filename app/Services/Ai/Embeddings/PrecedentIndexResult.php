<?php

namespace App\Services\Ai\Embeddings;

use App\Models\AiPrecedentEmbedding;

/**
 * Resultado de uma indexação de precedente (PrecedentIndexer::index). Distingue,
 * sem fachada, os três desfechos honestos:
 *  - available=false: sem provedor de embeddings ativo — não chamou nada;
 *  - available=true, embedded=true: gerou e persistiu (ou atualizou) o vetor;
 *  - available=true, embedded=false: conteúdo inalterado — pulou (idempotência).
 */
final class PrecedentIndexResult
{
    public function __construct(
        public readonly bool $available,
        public readonly bool $embedded,
        public readonly ?AiPrecedentEmbedding $embedding = null,
    ) {}

    public static function unavailable(): self
    {
        return new self(available: false, embedded: false);
    }

    public static function skipped(AiPrecedentEmbedding $embedding): self
    {
        return new self(available: true, embedded: false, embedding: $embedding);
    }

    public static function embedded(AiPrecedentEmbedding $embedding): self
    {
        return new self(available: true, embedded: true, embedding: $embedding);
    }
}
