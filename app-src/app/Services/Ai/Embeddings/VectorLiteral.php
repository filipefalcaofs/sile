<?php

namespace App\Services\Ai\Embeddings;

/**
 * Formata um vetor de floats no literal textual do pgvector (`[v1,v2,...]`),
 * usado com o cast `?::vector` na escrita (PrecedentIndexer) e na consulta
 * (PrecedentVectorSearch). Fonte ÚNICA da serialização — precisão num só lugar.
 */
final class VectorLiteral
{
    /**
     * @param  array<float>  $vector
     */
    public static function format(array $vector): string
    {
        return '['.implode(',', array_map(
            fn (float $value): string => rtrim(rtrim(sprintf('%.8f', $value), '0'), '.'),
            $vector,
        )).']';
    }
}
