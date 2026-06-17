<?php

namespace Tests\Feature\Ai;

use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AiSuggestion;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-115 (integração read-only na ficha de análise — Fase 10): a ficha expõe as
 * sugestões de IA do processo na prop deferida `sugestoesIa` (Inertia::optional),
 * carregada sob demanda. APENAS LEITURA — não toca o AnalysisRecord (ficha
 * finalizada é imutável, RN-003). Prova: o partial reload resolve as
 * inconsistências do processo (campo/declarado/documento/severidade/fonte),
 * escopadas ao processo; o load inicial não carrega a prop (deferida).
 */
#[Group('ia')]
class FichaSugestoesIaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function processoEmAnalise(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => fake()->unique()->numerify('VIA-'.now()->year.'-######'),
            'protocoled_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        return $processo;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function sugestaoInconsistencias(ViabilityRequest $processo, array $atributos = []): AiSuggestion
    {
        return AiSuggestion::factory()->create(array_merge([
            'viability_request_id' => $processo->id,
            'type' => AiSuggestionType::Inconsistencias,
            'prompt_version' => 'inconsistencias-v1',
            'status' => AiSuggestionStatus::Sugerida,
            'confidence' => null,
            'output' => [
                'inconsistencias' => [
                    ['campo' => 'area', 'declarado' => '50 m²', 'documento' => '80 m²', 'severidade' => 'alta'],
                ],
                'fonte' => 'foto da fachada anexada',
            ],
        ], $atributos));
    }

    public function test_partial_reload_resolve_as_sugestoes_de_ia_do_processo(): void
    {
        $processo = $this->processoEmAnalise();
        $this->sugestaoInconsistencias($processo);

        // Sugestão de OUTRO processo não pode vazar para a ficha deste.
        $outro = $this->processoEmAnalise();
        $this->sugestaoInconsistencias($outro);

        $analista = $this->analista();
        $url = "/gestao/processos/{$processo->id}/ficha";

        // Versão real do asset (evita o 409 de versionamento do Inertia no GET
        // parcial), derivada como o middleware faz — sem acoplar ao ambiente.
        $versao = (new HandleInertiaRequests)->version(request());

        // Partial reload do Inertia: a resposta é JSON puro (component/props/...),
        // por isso as asserções vão direto na estrutura, não via assertInertia
        // (que exige o HTML inicial).
        $resposta = $this->actingAs($analista, 'gestao')
            ->get($url, [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $versao,
                'X-Inertia-Partial-Component' => 'gestao/ficha-analise/show',
                'X-Inertia-Partial-Data' => 'sugestoesIa',
            ])
            ->assertOk();

        $resposta->assertJsonPath('component', 'gestao/ficha-analise/show');
        $resposta->assertJsonCount(1, 'props.sugestoesIa');
        $resposta->assertJsonPath('props.sugestoesIa.0.type', 'inconsistencias');
        $resposta->assertJsonPath('props.sugestoesIa.0.status', 'sugerida');
        $resposta->assertJsonPath('props.sugestoesIa.0.output.inconsistencias.0.campo', 'area');
        $resposta->assertJsonPath('props.sugestoesIa.0.output.inconsistencias.0.severidade', 'alta');
        $resposta->assertJsonPath('props.sugestoesIa.0.output.fonte', 'foto da fachada anexada');
    }

    public function test_load_inicial_nao_carrega_a_prop_deferida(): void
    {
        $processo = $this->processoEmAnalise();
        $this->sugestaoInconsistencias($processo);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->missing('sugestoesIa'));
    }
}
