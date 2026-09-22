<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\SugestaoJustificativaAgent;
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
use App\Services\Ai\SugestaoJustificativaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Sugestão de justificativa por IA (regra SEDUR 22/09/2026, item 6 + pedido do
 * usuário): a justificativa NASCE EM BRANCO (manifestação do analista) e a IA
 * só entra como APOIO — e somente DEPOIS de o analista decidir o enquadramento
 * da atividade (deferida/indeferida). Sem decisão do analista a função NÃO
 * despacha (o botão da ficha fica desabilitado e o endpoint recusa com 422).
 * A sugestão se fundamenta EXCLUSIVAMENTE no enquadramento objetivo do motor
 * (per_cnae/engine_snapshot) e atua preenchendo o campo vazio — sempre
 * editável pelo analista, nunca decisão (ViabilityDecision intocado).
 */
#[Group('ia')]
class SugestaoJustificativaTest extends TestCase
{
    use LazilyRefreshDatabase;

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
     * Processo em análise com a ficha pré-analisada pelo motor e a DECISÃO do
     * analista já registrada na atividade (status_escolhido).
     */
    private function processoComDecisao(string $decisao = 'deferida', ?string $justificativa = null): ViabilityRequest
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
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'status_escolhido' => $decisao,
                    'grupo_uso' => 'nR1',
                    'fluxo' => 'analise',
                    'fundamentacao' => [
                        'Lei nº 9.148/2016 (LOUOS) — nR1-01',
                        'Quadro 10 da Lei nº 9.148/2016',
                    ],
                    'justificativa' => $justificativa,
                    'gatilhos' => [],
                    'condicionantes' => [],
                ],
            ],
        ]);

        return $processo;
    }

    private function service(): SugestaoJustificativaService
    {
        return app(SugestaoJustificativaService::class);
    }

    public function test_sugere_justificativa_conforme_decisao_do_analista_e_preenche_campo_vazio(): void
    {
        config(['sile.features.ia_justificativa' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoComDecisao('deferida');

        SugestaoJustificativaAgent::fake([[
            'justificativa' => 'A atividade é locacionalmente permitida na zona, conforme o Quadro 10 da LOUOS, e o analista decidiu pelo deferimento.',
            'fundamentacao' => 'Enquadramento do motor: grupo nR1, Quadro 10 da Lei nº 9.148/2016.',
            'confianca' => 'alta',
            'fonte' => 'pré-análise do motor (engine_snapshot) e decisão do analista na ficha',
        ]]);

        $despachou = $this->service()->processar($processo, '4712100', userId: null);

        $this->assertTrue($despachou);
        // O prompt carrega a decisão do analista e o enquadramento do motor.
        SugestaoJustificativaAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'deferida')
                && str_contains($prompt->prompt, '4712-1/00'),
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Justificativa, $sugestao->type);
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame('justificativa-v1', $sugestao->prompt_version);
        $this->assertSame('4712100', $sugestao->input_ref['cnae']);

        // Atuando: a sugestão preenche o campo vazio da atividade (editável).
        $ficha = $processo->fresh()->currentAnalysisRecord;
        $this->assertStringContainsString(
            'locacionalmente permitida',
            (string) ($ficha->per_cnae[0]['justificativa'] ?? ''),
        );
        // Nunca decisão: o processo segue sem ViabilityDecision.
        $this->assertSame(0, ViabilityDecision::query()->count());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-justificativa',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_decisao_do_analista_nao_despacha_nem_chama_provedor(): void
    {
        config(['sile.features.ia_justificativa' => true]);
        $this->provedorTextoAtivo();

        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'status_escolhido' => null,
                    'grupo_uso' => 'nR1',
                    'fundamentacao' => [],
                    'justificativa' => null,
                ],
            ],
        ]);

        SugestaoJustificativaAgent::fake([[
            'justificativa' => 'qualquer',
            'fundamentacao' => 'qualquer',
            'confianca' => 'alta',
            'fonte' => 'motor',
        ]]);

        $despachou = $this->service()->processar($processo, '4712100');

        $this->assertFalse($despachou);
        SugestaoJustificativaAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_justificativa' => false]);
        $this->provedorTextoAtivo();
        $processo = $this->processoComDecisao();

        SugestaoJustificativaAgent::fake([[
            'justificativa' => 'qualquer',
            'fundamentacao' => 'qualquer',
            'confianca' => 'alta',
            'fonte' => 'motor',
        ]]);

        $despachou = $this->service()->processar($processo, '4712100');

        $this->assertFalse($despachou);
        SugestaoJustificativaAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_nao_sobrescreve_justificativa_ja_escrita_pelo_analista(): void
    {
        config(['sile.features.ia_justificativa' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoComDecisao('indeferida', 'Justificativa já redigida pelo analista.');

        SugestaoJustificativaAgent::fake([[
            'justificativa' => 'Texto novo da IA.',
            'fundamentacao' => 'Motor.',
            'confianca' => 'alta',
            'fonte' => 'pré-análise do motor',
        ]]);

        $this->service()->processar($processo, '4712100');

        $ficha = $processo->fresh()->currentAnalysisRecord;
        $this->assertSame('Justificativa já redigida pelo analista.', $ficha->per_cnae[0]['justificativa']);
        $this->assertSame(0, ViabilityDecision::query()->count());
    }

    public function test_endpoint_exige_decisao_do_analista_antes_de_sugerir(): void
    {
        config(['sile.features.ia_justificativa' => true]);
        $this->provedorTextoAtivo();

        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'status_escolhido' => null,
                    'justificativa' => null,
                ],
            ],
        ]);

        SugestaoJustificativaAgent::fake([[
            'justificativa' => 'qualquer',
            'fundamentacao' => 'qualquer',
            'confianca' => 'alta',
            'fonte' => 'motor',
        ]]);

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/sugerir-justificativa", ['cnae' => '4712100'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Decida o enquadramento da atividade (deferida ou indeferida) antes de pedir a sugestão da IA.');

        SugestaoJustificativaAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_endpoint_sugere_justificativa_e_audita(): void
    {
        config(['sile.features.ia_justificativa' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoComDecisao('deferida');

        SugestaoJustificativaAgent::fake([[
            'justificativa' => 'Minuta de justificativa fundamentada no enquadramento do motor.',
            'fundamentacao' => 'Permitido conforme a pré-análise do motor (Quadro 10 da LOUOS).',
            'confianca' => 'alta',
            'fonte' => 'pré-análise do motor (engine_snapshot)',
        ]]);

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/sugerir-justificativa", ['cnae' => '4712100'])
            ->assertOk()
            ->assertJsonPath('despachou', true);

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Justificativa, $sugestao->type);
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame(0, ViabilityDecision::query()->count());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'ficha-sugerir-justificativa',
        ]);
    }

    public function test_revisao_finalizada_recusa_a_sugestao(): void
    {
        config(['sile.features.ia_justificativa' => true]);
        $this->provedorTextoAtivo();

        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Finalizada,
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'status_escolhido' => 'deferida',
                    'justificativa' => null,
                ],
            ],
        ]);

        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/sugerir-justificativa", ['cnae' => '4712100'])
            ->assertStatus(422);

        $this->assertSame(0, AiSuggestion::query()->count());
    }
}
