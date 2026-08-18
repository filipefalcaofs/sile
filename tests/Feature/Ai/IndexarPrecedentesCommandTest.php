<?php

namespace Tests\Feature\Ai;

use App\Enums\ViabilityRequestStatus;
use App\Models\AiConfiguration;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Comando de evidência da infra de RAG (Onda 3) — indexa os precedentes
 * existentes (HU-142) por embeddings, base da busca por similaridade dos
 * assistentes (HU-120/121). Idempotente (não reembedda o inalterado) e honesto:
 * sem provedor de embeddings ativo, NÃO chama nada e avisa a indisponibilidade.
 */
#[Group('ia')]
class IndexarPrecedentesCommandTest extends TestCase
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

    private function precedenteDecidido(string $protocol): void
    {
        $request = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocol,
            'protocoled_at' => now(),
        ]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'decided_at' => now(),
            'tvl_product_number' => str_replace('VIA-', 'TVL-', $protocol),
        ]);
    }

    public function test_indexa_os_precedentes_e_e_idempotente(): void
    {
        $this->provedorEmbeddingsAtivo();
        $this->precedenteDecidido('VIA-2026-000601');
        $this->precedenteDecidido('VIA-2026-000602');

        // Conta as chamadas REAIS ao provedor — prova de efeito, independe do
        // texto impresso pelo comando.
        $chamadas = 0;
        Embeddings::fake(function () use (&$chamadas): array {
            $chamadas++;

            return [[0.1, 0.2, 0.3]];
        });

        $this->artisan('ai:indexar-precedentes')->assertSuccessful();

        // Indexou os dois precedentes (uma chamada de embedding por precedente).
        $this->assertSame(2, DB::table('ai_precedent_embeddings')->count());
        $this->assertSame(2, $chamadas);

        // Reexecução: conteúdo inalterado ⇒ nada novo (idempotência), sem custo.
        $this->artisan('ai:indexar-precedentes')->assertSuccessful();

        $this->assertSame(2, DB::table('ai_precedent_embeddings')->count());
        $this->assertSame(2, $chamadas);
    }

    public function test_sem_provedor_de_embeddings_nao_chama_o_provedor_nem_indexa(): void
    {
        $this->precedenteDecidido('VIA-2026-000611');

        $chamadas = 0;
        Embeddings::fake(function () use (&$chamadas): array {
            $chamadas++;

            return [[0.1, 0.2, 0.3]];
        });

        // No-op honesto: sai com sucesso, não chama o provedor e não indexa nada.
        $this->artisan('ai:indexar-precedentes')->assertSuccessful();

        $this->assertSame(0, $chamadas);
        $this->assertSame(0, DB::table('ai_precedent_embeddings')->count());
    }
}
