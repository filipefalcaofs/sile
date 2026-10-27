<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisRecord;
use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\TramitacaoProcessoService;
use App\Support\Audit\AuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A tramitação do processo mostra quando a ficha de análise foi salva pelo
 * botão "Salvar Ficha" e quando foi finalizada, além da conclusão da ficha de
 * vistoria. O autosave automático não entra na linha do tempo.
 */
class TramitacaoFichaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fichaRascunho(): AnalysisRecord
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();

        return AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [[
                'cnae' => '4712100',
                'cnae_formatado' => '4712-1/00',
                'is_primary' => true,
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'deferida',
            ]],
            'parecer' => 'Parecer em elaboração.',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function eventos(int $processoId): array
    {
        return app(TramitacaoProcessoService::class)->eventos(ViabilityRequest::findOrFail($processoId));
    }

    public function test_salvar_ficha_pelo_botao_entra_na_tramitacao_com_o_analista(): void
    {
        $ficha = $this->fichaRascunho();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Suzana Carvalho dos Reis']);

        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$ficha->viability_request_id}/ficha", ['parecer' => 'Parecer revisado.', 'manual' => true])
            ->assertOk();

        $salvas = array_values(array_filter($this->eventos($ficha->viability_request_id), fn (array $e): bool => $e['to_label'] === 'Ficha de análise salva'));

        $this->assertCount(1, $salvas);
        $this->assertSame('analise', $salvas[0]['tipo']);
        $this->assertSame('Suzana Carvalho dos Reis', $salvas[0]['ator']);
        $this->assertSame('Revisão 1 da ficha.', $salvas[0]['reason']);
        $this->assertSame("/gestao/processos/{$ficha->viability_request_id}/ficha?revisao=1&somente_leitura=1", $salvas[0]['link']);
    }

    public function test_link_da_revisao_abre_a_ficha_somente_para_leitura(): void
    {
        $ficha = $this->fichaRascunho();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processoId = $ficha->viability_request_id;

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processoId}/ficha")
            ->assertInertia(fn (Assert $page) => $page->where('ficha.editavel', true)->where('somenteLeitura', false));

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processoId}/ficha?revisao=1&somente_leitura=1")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.revision', 1)
                ->where('ficha.editavel', false)
                ->where('somenteLeitura', true));
    }

    public function test_link_da_revisao_mostra_a_revisao_pedida_e_nao_a_vigente(): void
    {
        $ficha = $this->fichaRascunho();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processoId = $ficha->viability_request_id;
        $ficha->forceFill(['status' => AnalysisRecordStatus::Finalizada])->save();
        AnalysisRecord::factory()->create(['viability_request_id' => $processoId, 'revision' => 2, 'parecer' => 'Segunda revisão.']);

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processoId}/ficha?revisao=1&somente_leitura=1")
            ->assertInertia(fn (Assert $page) => $page->where('ficha.revision', 1));
    }

    public function test_autosave_automatico_nao_entra_na_tramitacao(): void
    {
        $ficha = $this->fichaRascunho();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$ficha->viability_request_id}/ficha", ['parecer' => 'Parecer revisado.'])
            ->assertOk();

        $titulos = array_column($this->eventos($ficha->viability_request_id), 'to_label');

        $this->assertNotContains('Ficha de análise salva', $titulos);
    }

    public function test_finalizar_ficha_entra_na_tramitacao(): void
    {
        $ficha = $this->fichaRascunho();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Lisa Sousa']);

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$ficha->viability_request_id}/ficha/finalizar")
            ->assertOk();

        $finalizadas = array_values(array_filter($this->eventos($ficha->viability_request_id), fn (array $e): bool => $e['to_label'] === 'Ficha de análise finalizada'));

        $this->assertCount(1, $finalizadas);
        $this->assertSame('Lisa Sousa', $finalizadas[0]['ator']);
        $this->assertSame('Revisão 1 da ficha.', $finalizadas[0]['reason']);
        $this->assertSame("/gestao/processos/{$ficha->viability_request_id}/ficha?revisao=1&somente_leitura=1", $finalizadas[0]['link']);
    }

    public function test_ficha_de_outro_processo_nao_aparece(): void
    {
        $ficha = $this->fichaRascunho();
        $outro = ViabilityRequest::factory()->create();
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$ficha->viability_request_id}/ficha/finalizar")
            ->assertOk();

        $this->assertNotContains('Ficha de análise finalizada', array_column($this->eventos($outro->id), 'to_label'));
    }

    public function test_conclusao_da_ficha_de_vistoria_entra_na_tramitacao(): void
    {
        $vistoria = Inspection::factory()->concluida()->create();

        app(AuditService::class)->log(
            logName: 'vistoria',
            event: 'ficha-conclusao',
            description: "Conclusão da ficha de vistoria do processo #{$vistoria->viability_request_id}",
            properties: ['viability_request_id' => $vistoria->viability_request_id, 'inspection_id' => $vistoria->id],
            subject: $vistoria,
        );

        $concluidas = array_values(array_filter($this->eventos($vistoria->viability_request_id), fn (array $e): bool => $e['to_label'] === 'Ficha de vistoria concluída'));

        $this->assertCount(1, $concluidas);
        $this->assertSame('vistoria', $concluidas[0]['tipo']);
    }

    public function test_conclusao_da_ficha_de_vistoria_traz_o_link_para_a_propria_ficha(): void
    {
        $vistoria = Inspection::factory()->concluida()->create();

        app(AuditService::class)->log(
            logName: 'vistoria',
            event: 'ficha-conclusao',
            description: "Conclusão da ficha de vistoria do processo #{$vistoria->viability_request_id}",
            properties: ['viability_request_id' => $vistoria->viability_request_id, 'inspection_id' => $vistoria->id],
            subject: $vistoria,
        );

        $concluida = collect($this->eventos($vistoria->viability_request_id))->firstWhere('to_label', 'Ficha de vistoria concluída');

        $this->assertSame("/gestao/processos/{$vistoria->viability_request_id}/vistoria/visualizar?ficha={$vistoria->id}", $concluida['link']);
    }
}
