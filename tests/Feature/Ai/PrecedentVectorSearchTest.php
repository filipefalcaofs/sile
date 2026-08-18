<?php

namespace Tests\Feature\Ai;

use App\Enums\ViabilityRequestStatus;
use App\Models\AiConfiguration;
use App\Models\ViabilityRequest;
use App\Services\Ai\Embeddings\PrecedentVectorSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Degradação honesta da busca por similaridade em drivers SEM pgvector (SQLite
 * da suíte). A busca vetorial DIY usa o operador `<=>` do pgvector — indisponível
 * fora do PostgreSQL. Aqui provamos que, mesmo com precedentes indexados, a busca
 * devolve VAZIO e NÃO chama o provedor para a consulta (não finge ranking vetorial
 * nem desperdiça quota). O ranking real por cosine é provado em @group postgis.
 */
#[Group('ia')]
class PrecedentVectorSearchTest extends TestCase
{
    use RefreshDatabase;

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
     * Precedente com vetor já persistido em json (sem passar pelo provedor) — só
     * para garantir que há linhas e, ainda assim, a busca degrada para vazio.
     */
    private function precedenteComVetorJson(string $protocol): void
    {
        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocol,
            'protocoled_at' => now(),
        ]);

        DB::table('ai_precedent_embeddings')->insert([
            'viability_request_id' => $request->id,
            'content_hash' => hash('sha256', $protocol),
            'model' => 'text-embedding-3-small',
            'embedding' => json_encode([0.1, 0.2, 0.3, 0.4]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_em_sqlite_a_busca_degrada_para_vazio_sem_fingir_ranking(): void
    {
        $this->provedorEmbeddingsAtivo();
        $this->precedenteComVetorJson('VIA-2026-000801');
        $this->precedenteComVetorJson('VIA-2026-000802');

        $chamadasConsulta = 0;
        Embeddings::fake(function () use (&$chamadasConsulta): array {
            $chamadasConsulta++;

            return [[0.1, 0.2, 0.3, 0.4]];
        });

        $resultado = app(PrecedentVectorSearch::class)->maisSimilares('comércio varejista no centro', 5);

        // Degradação honesta: vazio, sem ranking forjado e sem custo de embedding.
        $this->assertTrue($resultado->isEmpty());
        $this->assertSame(0, $chamadasConsulta);
        Embeddings::assertNothingGenerated();
    }
}
