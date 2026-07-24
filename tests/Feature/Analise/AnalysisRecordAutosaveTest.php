<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Models\AnalysisRecord;
use App\Models\StandardText;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ficha de análise — abertura e autosave (HU-135 RN-008): a ficha abre a partir
 * da revisão vigente (pré-analisada em 10-08) e o analista salva o rascunho por
 * PATCH (status escolhido por CNAE, condicionantes, vagas e parecer), com a
 * biblioteca de textos-padrão ativos disponível para o parecer (HU-085 RN-004).
 * Toda a superfície é gated por analisar-processos (403 auditado no ponto único)
 * e a revisão FINALIZADA é imutável (RN-003): autosave numa revisão finalizada é
 * recusado (422), nunca edita silenciosamente.
 */
class AnalysisRecordAutosaveTest extends TestCase
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

    private function fichaRascunho(): AnalysisRecord
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        return AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'per_cnae' => [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'is_primary' => true,
                    'status_sugerido' => 'deferida',
                    'status_escolhido' => 'deferida',
                ],
            ],
            'parecer' => null,
        ]);
    }

    public function test_show_exige_analisar_processos_e_audita_o_403(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $ficha = $this->fichaRascunho();

        $this->actingAs($semPermissao, 'gestao')
            ->get("/gestao/processos/{$ficha->viability_request_id}/ficha")
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_show_abre_a_revisao_atual_com_textos_padrao_ativos(): void
    {
        $ficha = $this->fichaRascunho();

        StandardText::factory()->create([
            'category' => 'deferimento',
            'content' => 'Parecer favorável com fundamentação na LOUOS.',
            'active' => true,
        ]);
        StandardText::factory()->create([
            'category' => 'indeferimento',
            'content' => 'Texto desativado que não deve aparecer no picker.',
            'active' => false,
        ]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$ficha->viability_request_id}/ficha")
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/ficha-analise/show', $page['component']);

        $this->assertSame($ficha->id, $page['props']['ficha']['id']);
        $this->assertSame(1, $page['props']['ficha']['revision']);
        $this->assertSame('rascunho', $page['props']['ficha']['status']);
        $this->assertTrue($page['props']['ficha']['editavel']);

        $conteudos = collect($page['props']['textosPadrao'])->pluck('content')->all();
        $this->assertContains('Parecer favorável com fundamentação na LOUOS.', $conteudos);
        $this->assertNotContains('Texto desativado que não deve aparecer no picker.', $conteudos);
    }

    public function test_autosave_atualiza_status_escolhido_e_parecer_no_rascunho(): void
    {
        $ficha = $this->fichaRascunho();

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$ficha->viability_request_id}/ficha", [
                'per_cnae' => [
                    ['cnae' => '4712100', 'status_escolhido' => 'indeferida', 'justificativa' => 'Conflito com a zona.'],
                ],
                'parecer' => 'Parecer técnico em elaboração.',
            ])
            ->assertOk();

        $ficha->refresh();

        $item = $ficha->per_cnae[0];
        $this->assertSame('indeferida', $item['status_escolhido']);
        // O sugerido pelo motor é PRESERVADO (insumo da divergência na finalização).
        $this->assertSame('deferida', $item['status_sugerido']);
        $this->assertSame('Parecer técnico em elaboração.', $ficha->parecer);
    }

    public function test_autosave_em_revisao_finalizada_e_recusado_com_422(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create();

        $ficha = AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'parecer' => 'Parecer final imutável.',
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$request->id}/ficha", [
                'parecer' => 'Tentativa de editar uma revisão finalizada.',
            ])
            ->assertStatus(422);

        // RN-003: a revisão finalizada não é alterada.
        $this->assertSame('Parecer final imutável.', $ficha->fresh()->parecer);
    }

    public function test_autosave_persiste_motivos_de_analise_e_endereco_correto(): void
    {
        $ficha = $this->fichaRascunho();

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$ficha->viability_request_id}/ficha", [
                'analysis_reasons' => ['Área zoneamento Semi expresso', 'Áreas - parcelamento'],
                'address_confirmed' => false,
            ])
            ->assertOk();

        $ficha->refresh();

        $this->assertSame(['Área zoneamento Semi expresso', 'Áreas - parcelamento'], $ficha->analysis_reasons);
        $this->assertFalse($ficha->address_confirmed);
    }

    public function test_show_expoe_analysis_reasons_vazio_e_address_confirmed_nulo_por_padrao(): void
    {
        $ficha = $this->fichaRascunho();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$ficha->viability_request_id}/ficha")
            ->assertOk();

        $page = $response->viewData('page');

        $this->assertSame([], $page['props']['ficha']['analysis_reasons']);
        $this->assertNull($page['props']['ficha']['address_confirmed']);
    }
}
