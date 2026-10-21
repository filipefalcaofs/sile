<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\DicasTempoAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Ai\DicasTempoService;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Dicas por IA para reduzir o tempo de viabilidade. A IA recebe um dossiê
 * AGREGADO (tempo, cauda, gargalos por etapa, indeferimentos, benchmark
 * nacional) — nunca dado pessoal nem protocolo — e devolve sugestões ao
 * gestor. Sem provedor, com o toggle desligado ou sem processo decidido no
 * período, nada é chamado nem simulado.
 */
#[Group('ia')]
class DicasTempoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function fingirResposta(): void
    {
        DicasTempoAgent::fake([[
            'resumo' => 'O tempo médio é puxado por uma cauda de processos indeferidos.',
            'dicas' => [
                [
                    'titulo' => 'Atacar o indeferimento tardio',
                    'acao' => 'Indeferir na entrada os usos não permitidos pela zona.',
                    'evidencia' => 'Indeferidos levam 60 h contra 3 h dos deferidos.',
                    'impacto' => 'alta',
                ],
            ],
            'confianca' => 'alta',
            'fonte' => 'dossiê agregado do período (tempo, gargalos, indeferimentos, benchmark)',
        ]]);
    }

    private function deferido(float $horas, string $decididoEm): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($decididoEm)->subDay(),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => $horas])->save();

        return $request;
    }

    private function indeferido(float $horas, string $decididoEm): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($decididoEm)->subDay(),
        ]);

        ViabilityDecision::factory()->indeferida()->for($request, 'viabilityRequest')->create([
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => $horas])->save();

        return $request;
    }

    private function filtros(): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => '2026-10-01', 'data_ate' => '2026-10-20']);
    }

    private function service(): DicasTempoService
    {
        return app(DicasTempoService::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    public function test_gera_dicas_a_partir_do_dossie_agregado_do_periodo(): void
    {
        config(['viabiliza.features.ia_dicas_tempo' => true]);
        $this->provedorTextoAtivo();
        BenchmarkMunicipalityMetric::factory()->salvador()->create(['ano' => 2026, 'mes' => 9, 'media' => 21.0, 'n' => 600]);
        $this->deferido(2.0, '2026-10-05 10:00:00');
        $this->deferido(4.0, '2026-10-06 10:00:00');
        $indeferido = $this->indeferido(60.0, '2026-10-07 10:00:00');
        $this->fingirResposta();

        $gerou = $this->service()->gerar($this->filtros(), userId: null);

        $this->assertTrue($gerou);
        DicasTempoAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, '"indeferimentos"')
                && str_contains($prompt->prompt, '"gargalos"')
                && str_contains($prompt->prompt, '"benchmark_nacional"')
                && str_contains($prompt->prompt, '"tempo"'),
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::DicasTempo, $sugestao->type);
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame('dicas-tempo-v1', $sugestao->prompt_version);
        $this->assertSame('2026-10-01', $sugestao->input_ref['periodo']['de']);
        $this->assertSame(3, $sugestao->input_ref['tempo']['todas']['n']);
        $this->assertSame(2, $sugestao->input_ref['tempo']['deferidas']['n']);
        $this->assertSame(1, $sugestao->input_ref['indeferimentos']['quantidade']);
        $this->assertNull($sugestao->viability_request_id);

        // Dossiê agregado: nenhum identificador de processo vai ao provedor.
        $this->assertStringNotContainsString($indeferido->protocol_number, json_encode($sugestao->input_ref));
    }

    public function test_sem_processo_decidido_no_periodo_nao_chama_o_provedor(): void
    {
        config(['viabiliza.features.ia_dicas_tempo' => true]);
        $this->provedorTextoAtivo();
        $this->fingirResposta();

        $this->assertFalse($this->service()->gerar($this->filtros(), userId: null));

        DicasTempoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_toggle_desligado_nao_chama_o_provedor(): void
    {
        config(['viabiliza.features.ia_dicas_tempo' => false]);
        $this->provedorTextoAtivo();
        $this->deferido(2.0, '2026-10-05 10:00:00');
        $this->fingirResposta();

        $this->assertFalse($this->service()->gerar($this->filtros(), userId: null));

        DicasTempoAgent::assertNeverPrompted();
    }

    public function test_sem_provedor_ativo_nao_chama_o_provedor(): void
    {
        config(['viabiliza.features.ia_dicas_tempo' => true]);
        $this->deferido(2.0, '2026-10-05 10:00:00');
        $this->fingirResposta();

        $this->assertFalse($this->service()->gerar($this->filtros(), userId: null));

        DicasTempoAgent::assertNeverPrompted();
    }

    public function test_painel_informa_indisponibilidade_e_devolve_a_ultima_geracao(): void
    {
        config(['viabiliza.features.ia_dicas_tempo' => true]);

        $semProvedor = $this->service()->painel();
        $this->assertFalse($semProvedor['disponivel']);
        $this->assertNotNull($semProvedor['motivo']);
        $this->assertNull($semProvedor['ultima']);

        $this->provedorTextoAtivo();
        $this->deferido(2.0, '2026-10-05 10:00:00');
        $this->fingirResposta();
        $this->service()->gerar($this->filtros(), userId: null);

        $painel = $this->service()->painel();

        $this->assertTrue($painel['disponivel']);
        $this->assertNull($painel['motivo']);
        $this->assertSame('Atacar o indeferimento tardio', $painel['ultima']['dicas'][0]['titulo']);
        $this->assertSame('2026-10-01', $painel['ultima']['periodo']['de']);
        $this->assertNotNull($painel['ultima']['gerado_em']);
    }

    public function test_endpoint_dispara_a_geracao_do_periodo_informado(): void
    {
        config(['viabiliza.features.ia_dicas_tempo' => true]);
        $this->provedorTextoAtivo();
        $this->deferido(2.0, '2026-10-05 10:00:00');
        $this->fingirResposta();

        $this->actingAs($this->gestor(), 'gestao')
            ->from(route('gestao.relatorios.benchmark'))
            ->post(route('gestao.relatorios.dicas-tempo'), ['data_de' => '2026-10-01', 'data_ate' => '2026-10-20'])
            ->assertRedirect(route('gestao.relatorios.benchmark'));

        $this->assertSame(1, AiSuggestion::query()->where('type', AiSuggestionType::DicasTempo)->count());
    }

    public function test_endpoint_sem_dados_ou_provedor_volta_com_aviso_e_nao_gera(): void
    {
        config(['viabiliza.features.ia_dicas_tempo' => true]);

        $this->actingAs($this->gestor(), 'gestao')
            ->from(route('gestao.relatorios.benchmark'))
            ->post(route('gestao.relatorios.dicas-tempo'), ['data_de' => '2026-10-01', 'data_ate' => '2026-10-20'])
            ->assertRedirect(route('gestao.relatorios.benchmark'))
            ->assertSessionHas('warning');

        $this->assertSame(0, AiSuggestion::query()->count());
    }

    public function test_endpoint_exige_permissao_de_relatorios(): void
    {
        $user = User::factory()->withAcceptedLgpdTerm()->create();
        $user->givePermissionTo('acessar-gestao');

        $this->actingAs($user, 'gestao')
            ->post(route('gestao.relatorios.dicas-tempo'))
            ->assertForbidden();
    }

    public function test_telas_expoem_o_painel_de_dicas(): void
    {
        $gestor = $this->gestor();

        $this->actingAs($gestor, 'gestao')
            ->get(route('gestao.relatorios.benchmark'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dicas.disponivel')
                ->has('dicas.periodo.de')
                ->where('dicas.ultima', null),
            );

        $this->actingAs($gestor, 'gestao')
            ->get(route('gestao.sala-de-controle'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dicas.disponivel')
                ->has('dicas.periodo'),
            );
    }
}
