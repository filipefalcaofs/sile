<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\ExplicacaoCidadaoAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Ai\ExplicacaoCidadaoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-119 (explicar o resultado ao cidadão) — acessível: a IA TRADUZ a decisão
 * técnica (deferida/indeferida) para linguagem cidadã clara, FIEL à decisão e à
 * fundamentação registradas (HU-099/ViabilityDecision), NUNCA prometendo o que a
 * lei não garante. Prova: explicação com fonte quando há decisão vira sugestão
 * sugerida; SEM decisão registrada a função NÃO despacha (não explica o que não
 * existe); toggle features.ia_explicacao OFF ⇒ NÃO chama o provedor. A função
 * GERA TEXTO (capability text, sem anexos). Fake por agente — sem rede, sem custo.
 */
#[Group('ia')]
class ExplicacaoCidadaoTest extends TestCase
{
    use RefreshDatabase;

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
     * Processo DECIDIDO (ViabilityDecision deferida) — a fonte REAL da explicação.
     * requester = $dono para a policy view do portal.
     */
    private function processoDecidido(?User $dono = null): ViabilityRequest
    {
        $atributos = [];

        if ($dono !== null) {
            $atributos['requester_user_id'] = $dono->id;
            $atributos['created_by_user_id'] = $dono->id;
        }

        $processo = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create($atributos);

        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id]);

        return $processo;
    }

    private function service(): ExplicacaoCidadaoService
    {
        return app(ExplicacaoCidadaoService::class);
    }

    public function test_explica_decisao_com_fonte_quando_ha_decisao(): void
    {
        config(['sile.features.ia_explicacao' => true]);
        $this->provedorTextoAtivo();
        $processo = $this->processoDecidido();

        ExplicacaoCidadaoAgent::fake([[
            'explicacao' => 'Sua solicitação foi deferida: a atividade é permitida no endereço informado, conforme a análise do município.',
            'fonte' => 'decisão registrada do processo (resultado consolidado e fundamentação legal)',
        ]]);

        $despachou = $this->service()->processar($processo, userId: null);

        $this->assertTrue($despachou);
        // A decisão REAL (desfecho registrado) vai ao provedor — explicação fiel
        // ao que foi decidido, não fachada.
        ExplicacaoCidadaoAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'Deferida'),
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Explicacao, $sugestao->type);
        // Explicação é apoio revisável, jamais decisão (RN-001/Failure Mode #1).
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame($processo->id, $sugestao->viability_request_id);
        $this->assertNotEmpty($sugestao->output['explicacao']);
        $this->assertSame('explicacao-v1', $sugestao->prompt_version);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-explicacao',
            'result' => 'sucesso',
        ]);
    }

    public function test_sem_decisao_registrada_nao_despacha(): void
    {
        config(['sile.features.ia_explicacao' => true]);
        $this->provedorTextoAtivo();

        // Processo protocolado AINDA EM ANÁLISE (sem ViabilityDecision): não há
        // desfecho a explicar ⇒ a função não despacha e não inventa explicação.
        $processo = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create();

        ExplicacaoCidadaoAgent::fake([[
            'explicacao' => 'qualquer',
            'fonte' => 'decisão',
        ]]);

        $despachou = $this->service()->processar($processo);

        $this->assertFalse($despachou);
        ExplicacaoCidadaoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_explicacao' => false]);
        $this->provedorTextoAtivo();
        $processo = $this->processoDecidido();

        ExplicacaoCidadaoAgent::fake([[
            'explicacao' => 'qualquer',
            'fonte' => 'decisão',
        ]]);

        $despachou = $this->service()->processar($processo);

        $this->assertFalse($despachou);
        ExplicacaoCidadaoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_protocolo_dispara_explicacao_ao_carregar_sugestao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['sile.features.ia_explicacao' => true]);
        $this->provedorTextoAtivo();

        $dono = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        $processo = $this->processoDecidido($dono);

        ExplicacaoCidadaoAgent::fake([[
            'explicacao' => 'Sua solicitação foi deferida: a atividade é permitida no endereço informado.',
            'fonte' => 'decisão registrada do processo',
        ]]);

        // Partial reload da prop deferida explicacaoIa na página de protocolo:
        // ao carregar o card de explicação, o controller dispara a explicação
        // (gated/só com decisão/dedup) e a prop já a entrega.
        $resposta = $this->actingAs($dono)
            ->get(route('portal.solicitacoes.show', $processo), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'portal/solicitacoes/protocolo',
                'X-Inertia-Partial-Data' => 'explicacaoIa',
            ])
            ->assertOk();

        ExplicacaoCidadaoAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Deferida'));

        $sugestao = AiSuggestion::query()
            ->where('type', AiSuggestionType::Explicacao)
            ->sole();
        $this->assertSame($processo->id, $sugestao->viability_request_id);

        $resposta->assertJsonPath('props.explicacaoIa.0.type', 'explicacao');
        $resposta->assertJsonPath('props.explicacaoIa.0.status', 'sugerida');
    }

    public function test_protocolo_nao_dispara_explicacao_sem_decisao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['sile.features.ia_explicacao' => true]);
        $this->provedorTextoAtivo();

        $dono = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
        // Protocolado, sem decisão registrada (em análise): nada a explicar.
        $processo = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create([
            'requester_user_id' => $dono->id,
            'created_by_user_id' => $dono->id,
        ]);

        ExplicacaoCidadaoAgent::fake([[
            'explicacao' => 'não deveria rodar',
            'fonte' => 'decisão',
        ]]);

        $this->actingAs($dono)
            ->get(route('portal.solicitacoes.show', $processo), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'portal/solicitacoes/protocolo',
                'X-Inertia-Partial-Data' => 'explicacaoIa',
            ])
            ->assertOk();

        ExplicacaoCidadaoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->where('type', AiSuggestionType::Explicacao)->count());
    }
}
