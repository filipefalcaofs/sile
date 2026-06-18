<?php

namespace Tests\Feature\Ai;

use App\Enums\ViabilityRequestStatus;
use App\Models\AiConfiguration;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Ai\Embeddings\PrecedentIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Infra de RAG (Fase 14, Onda 3) — indexação dos precedentes (HU-142) por
 * embeddings, base da busca por similaridade que sustenta os assistentes
 * (HU-120/121).
 *
 * O que esta suíte prova (anti-fachada + LGPD):
 *  - o vetor é gerado a partir de um RESUMO ANONIMIZADO do precedente (sem nome
 *    do requerente nem logradouro) e persistido (CA da minimização de PII);
 *  - a indexação é IDEMPOTENTE por content_hash: reprocessar o mesmo precedente
 *    inalterado NÃO chama o provedor de novo (custo/quota);
 *  - sem configuração de embeddings ATIVA, a indexação fica INDISPONÍVEL — não
 *    chama o provedor e não persiste (degradação honesta, nunca simulada).
 *
 * Roda em SQLite (embedding em coluna json) com o fake do SDK — sem rede, sem
 * custo. A busca vetorial real (cosine no pgvector) é provada em @group postgis.
 */
#[Group('ia')]
class PrecedentIndexerTest extends TestCase
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
     * Precedente real = solicitação DECIDIDA (HU-142). O requerente tem nome e o
     * imóvel tem logradouro — ambos PII que NÃO podem vazar para o embedding.
     */
    private function precedenteDecidido(string $protocol = 'VIA-2026-000900', bool $deferida = true): ViabilityRequest
    {
        $requester = User::factory()->create(['name' => 'João da Silva Sigiloso']);

        $cnae = Cnae::factory()->create([
            'code' => '4712100',
            'description' => 'Comércio varejista de mercadorias em geral',
        ]);

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocol,
            'protocoled_at' => now(),
            'requester_user_id' => $requester->id,
            'address_street' => 'Rua Secreta do Requerente',
            'address_number' => '123',
            'address_neighborhood' => 'Pituba',
        ]);

        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $factory = $deferida ? ViabilityDecision::factory() : ViabilityDecision::factory()->indeferida();
        $factory->create([
            'viability_request_id' => $request->id,
            'decided_at' => now(),
        ]);

        return $request->fresh();
    }

    private function indexer(): PrecedentIndexer
    {
        return app(PrecedentIndexer::class);
    }

    public function test_gera_e_persiste_o_vetor_do_resumo_anonimizado_sem_pii(): void
    {
        $this->provedorEmbeddingsAtivo();
        $precedente = $this->precedenteDecidido();

        $vetor = [0.10, 0.20, 0.30, 0.40];
        Embeddings::fake([[$vetor]]);

        $resultado = $this->indexer()->index($precedente);

        $this->assertTrue($resultado->available);
        $this->assertTrue($resultado->embedded);

        // Persistiu exatamente um vetor para o precedente, com o modelo usado.
        $this->assertDatabaseHas('ai_precedent_embeddings', [
            'viability_request_id' => $precedente->id,
            'model' => 'text-embedding-3-small',
        ]);

        $persistido = DB::table('ai_precedent_embeddings')
            ->where('viability_request_id', $precedente->id)
            ->value('embedding');

        // Em SQLite o vetor fica em json — decodifica para o vetor conhecido.
        $this->assertSame($vetor, json_decode((string) $persistido, true));

        // LGPD: o conteúdo embeddado tem a substância (CNAE, bairro, desfecho) e
        // NÃO tem PII (nome do requerente nem logradouro).
        Embeddings::assertGenerated(function ($prompt): bool {
            $conteudo = $prompt->inputs[0];

            return str_contains($conteudo, 'Comércio varejista de mercadorias em geral')
                && str_contains($conteudo, 'Pituba')
                && ! str_contains($conteudo, 'Sigiloso')
                && ! str_contains($conteudo, 'Rua Secreta do Requerente');
        });
    }

    public function test_idempotente_por_content_hash_nao_reembedda_o_mesmo_precedente(): void
    {
        $this->provedorEmbeddingsAtivo();
        $precedente = $this->precedenteDecidido();

        $chamadas = 0;
        Embeddings::fake(function () use (&$chamadas): array {
            $chamadas++;

            return [[0.5, 0.5, 0.5, 0.5]];
        });

        $primeiro = $this->indexer()->index($precedente);
        $segundo = $this->indexer()->index($precedente);

        $this->assertTrue($primeiro->embedded);
        // Conteúdo inalterado ⇒ NÃO reembedda (custo/quota): só uma chamada real.
        $this->assertFalse($segundo->embedded);
        $this->assertSame(1, $chamadas);
        $this->assertSame(1, DB::table('ai_precedent_embeddings')->count());
    }

    public function test_sem_config_de_embeddings_nao_chama_o_provedor_nem_persiste(): void
    {
        // Nenhuma AiConfiguration de embeddings ativa (gate fechado).
        $precedente = $this->precedenteDecidido();

        $chamadas = 0;
        Embeddings::fake(function () use (&$chamadas): array {
            $chamadas++;

            return [[0.1, 0.1, 0.1, 0.1]];
        });

        $resultado = $this->indexer()->index($precedente);

        $this->assertFalse($resultado->available);
        $this->assertFalse($resultado->embedded);
        $this->assertSame(0, $chamadas);
        $this->assertSame(0, DB::table('ai_precedent_embeddings')->count());
        Embeddings::assertNothingGenerated();
    }
}
