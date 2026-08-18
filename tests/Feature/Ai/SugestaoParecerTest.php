<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\SugestaoParecerAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Ai\SugestaoParecerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-118 (sugerir minuta de parecer) — Failure Mode #1 do AI-SPEC: a IA é APOIO,
 * NUNCA decisão. A minuta nasce sempre como SUGESTÃO revisável; a fundamentação
 * vem do motor REAL (engine_snapshot/LOUOS), nunca de artigo/quadro inventado.
 * Prova: minuta fundamentada no motor vira sugestão e a função NÃO grava parecer
 * na ficha nem decisão no processo (não-decisão); processo SEM pré-análise do
 * motor ⇒ NÃO despacha (escala ao humano, sem fundamentação fabricada); toggle
 * features.ia_parecer OFF ⇒ NÃO chama o provedor. A função GERA TEXTO (capability
 * text, sem anexos). Fake por agente — sem rede, sem custo.
 */
#[Group('ia')]
class SugestaoParecerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function provedorTextoAtivo(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'text',
            'model' => 'gpt-5.4-mini',
            'active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Processo em análise com a ficha pré-analisada pelo motor (engine_snapshot/
     * per_cnae preenchidos) — a fonte REAL da fundamentação do parecer.
     */
    private function processoPreAnalisado(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'used_area_m2' => 50,
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_neighborhood' => 'Centro',
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        return $processo;
    }

    private function service(): SugestaoParecerService
    {
        return app(SugestaoParecerService::class);
    }

    public function test_sugere_minuta_fundamentada_no_motor_como_sugestao_nao_decisao(): void
    {
        config(['sile.features.ia_parecer' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoPreAnalisado();

        SugestaoParecerAgent::fake([[
            'minuta' => 'Trata-se de pedido de viabilidade locacional para comércio varejista no imóvel indicado.',
            'fundamentacao' => 'Enquadramento permitido conforme a pré-análise do motor (Quadro 7 da LOUOS, versão registrada).',
            'confianca' => 'alta',
            'fonte' => 'pré-análise do motor (engine_snapshot) e enquadramento por CNAE da ficha',
        ]]);

        $despachou = $this->service()->processar($processo, userId: null);

        $this->assertTrue($despachou);
        // A fundamentação REAL do motor (enquadramento por CNAE) é levada ao
        // provedor — a minuta se apoia no que existe, não em texto inventado.
        SugestaoParecerAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'Comércio varejista'),
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Parecer, $sugestao->type);
        // Minuta é SUGESTÃO revisável, jamais decisão (RN-001/Failure Mode #1).
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertNotEmpty($sugestao->output['minuta']);
        $this->assertNotEmpty($sugestao->output['fundamentacao']);
        $this->assertSame('parecer-v1', $sugestao->prompt_version);
        $this->assertSame($processo->id, $sugestao->viability_request_id);

        // NÃO-DECISÃO (crítico): a função NÃO grava parecer na ficha nem decisão
        // no processo. A minuta fica só no ledger ai_suggestions, para o analista
        // revisar e, se quiser, aplicar manualmente.
        $this->assertNull(
            AnalysisRecord::query()->where('viability_request_id', $processo->id)->value('parecer'),
        );
        $this->assertSame(0, ViabilityDecision::query()->count());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-parecer',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_pre_analise_do_motor_nao_despacha_e_escala_ao_humano(): void
    {
        config(['sile.features.ia_parecer' => true]);
        $this->provedorTextoAtivo();

        // Ficha em MODO MANUAL (motor indisponível, sem engine_snapshot): não há
        // fundamentação real a citar ⇒ a função não sugere e escala ao humano,
        // que redige o parecer manualmente (anti-fachada, sem artigo inventado).
        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        AnalysisRecord::factory()->semMotor()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        SugestaoParecerAgent::fake([[
            'minuta' => 'qualquer',
            'fundamentacao' => 'qualquer',
            'confianca' => 'alta',
            'fonte' => 'motor',
        ]]);

        $despachou = $this->service()->processar($processo);

        $this->assertFalse($despachou);
        SugestaoParecerAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_parecer' => false]);
        $this->provedorTextoAtivo();
        $processo = $this->processoPreAnalisado();

        SugestaoParecerAgent::fake([[
            'minuta' => 'qualquer',
            'fundamentacao' => 'qualquer',
            'confianca' => 'alta',
            'fonte' => 'motor',
        ]]);

        $despachou = $this->service()->processar($processo);

        $this->assertFalse($despachou);
        SugestaoParecerAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_endpoint_da_ficha_sugere_minuta_sem_gravar_decisao(): void
    {
        config(['sile.features.ia_parecer' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoPreAnalisado();

        SugestaoParecerAgent::fake([[
            'minuta' => 'Minuta de apoio fundamentada no enquadramento do motor.',
            'fundamentacao' => 'Permitido conforme a pré-análise do motor (Quadro 7 da LOUOS, versão registrada).',
            'confianca' => 'alta',
            'fonte' => 'pré-análise do motor (engine_snapshot)',
        ]]);

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        // O botão "Sugerir minuta" da ficha aciona o endpoint real (não é fachada):
        // dispara a função e devolve o estado para a tela recarregar as sugestões.
        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/sugerir-parecer")
            ->assertOk()
            ->assertJsonPath('despachou', true);

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Parecer, $sugestao->type);
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);

        // NÃO-DECISÃO no endpoint (crítico): nem parecer na ficha, nem decisão no
        // processo — a minuta fica só no ledger, para o analista revisar e aplicar.
        $this->assertNull(
            AnalysisRecord::query()->where('viability_request_id', $processo->id)->value('parecer'),
        );
        $this->assertSame(0, ViabilityDecision::query()->count());

        // Auditoria da ação do analista (RN-002), além da auditoria da chamada de IA.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'ficha-sugerir-parecer',
        ]);
    }
}
