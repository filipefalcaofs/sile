<?php

namespace Tests\Feature\Ai;

use App\Enums\ViabilityRequestStatus;
use App\Models\AiConfiguration;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Ai\Embeddings\PrecedentVectorSearch;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Busca por similaridade REAL dos precedentes (HU-142) sobre pgvector — o ranking
 * por distância cosine (`<=>`) que sustenta o assistente do analista (HU-121). É
 * a prova de que a infra de RAG é DIY de verdade (o SimilaritySearch do laravel/ai
 * v0.8.1 não existe): vetores conhecidos inseridos no pgvector, embedding da
 * consulta alinhado a um deles, e a busca retorna esse precedente em primeiro.
 *
 * #[Group('postgis')] explícito (além de herdar de PostgisTestCase) para que
 * `--group=postgis` descubra a classe: roda no pgsql_testing com a extensão
 * vector, skip HONESTO só sem servidor (nunca passa sem rodar o SQL vetorial).
 */
#[Group('postgis')]
class PrecedentVectorSearchPostgisTest extends PostgisTestCase
{
    private const DIMENSIONS = 1536;

    private function provedorEmbeddingsAtivo(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'embeddings',
            'model' => 'text-embedding-3-small',
            'active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Vetor unitário esparso (1 numa posição, 0 no resto) — cosine bem definido:
     * vetores em posições distintas têm distância 1; igual posição, distância 0.
     *
     * @return array<float>
     */
    private function vetorUnitario(int $posicao): array
    {
        $vetor = array_fill(0, self::DIMENSIONS, 0.0);
        $vetor[$posicao] = 1.0;

        return $vetor;
    }

    private function precedenteComVetor(string $protocol, int $posicao): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocol,
            'protocoled_at' => now(),
        ]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'decided_at' => now(),
            // tvl único por protocolo (a factory hardcoda — evita colisão na unique).
            'tvl_product_number' => str_replace('VIA-', 'TVL-', $protocol),
        ]);

        DB::insert(
            'INSERT INTO ai_precedent_embeddings (viability_request_id, content_hash, model, embedding, created_at, updated_at) VALUES (?, ?, ?, ?::vector, now(), now())',
            [
                $request->id,
                hash('sha256', $protocol),
                'text-embedding-3-small',
                '['.implode(',', $this->vetorUnitario($posicao)).']',
            ],
        );

        return $request;
    }

    public function test_retorna_os_precedentes_ordenados_por_similaridade_cosine(): void
    {
        $this->provedorEmbeddingsAtivo();

        // Três precedentes em direções ortogonais distintas.
        $this->precedenteComVetor('VIA-2026-000701', 0);
        $alvo = $this->precedenteComVetor('VIA-2026-000702', 1);
        $this->precedenteComVetor('VIA-2026-000703', 2);

        // A consulta embedda EXATAMENTE na direção do precedente 702 (distância 0).
        Embeddings::fake([[$this->vetorUnitario(1)]]);

        $resultado = app(PrecedentVectorSearch::class)->maisSimilares('atividade na zona', 3);

        // SQL vetorial REAL — não fachada.
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        $this->assertCount(3, $resultado);
        // O mais similar vem primeiro: o precedente alinhado à consulta.
        $this->assertSame($alvo->id, $resultado->first()['viability_request_id']);
        $this->assertSame('VIA-2026-000702', $resultado->first()['protocol_number']);
        $this->assertSame('deferida', $resultado->first()['outcome']);
        // Distância cosine ~0 para o alinhado, ~1 para os ortogonais (ordenado asc).
        $this->assertEqualsWithDelta(0.0, (float) $resultado->first()['distance'], 1e-6);
        $this->assertGreaterThan((float) $resultado->first()['distance'], (float) $resultado->last()['distance']);
    }

    public function test_limite_k_corta_a_lista(): void
    {
        $this->provedorEmbeddingsAtivo();

        $this->precedenteComVetor('VIA-2026-000711', 0);
        $this->precedenteComVetor('VIA-2026-000712', 1);
        $this->precedenteComVetor('VIA-2026-000713', 2);

        Embeddings::fake([[$this->vetorUnitario(1)]]);

        $resultado = app(PrecedentVectorSearch::class)->maisSimilares('atividade na zona', 1);

        $this->assertCount(1, $resultado);
        $this->assertSame('VIA-2026-000712', $resultado->first()['protocol_number']);
    }
}
