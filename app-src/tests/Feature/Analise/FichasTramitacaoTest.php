<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Analise\TramitacaoProcessoService;
use App\Support\Audit\AuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regras confirmadas pela cliente em 26/10/2026: quem recebe o processo parte
 * da ficha finalizada de quem o encaminhou, cada um finaliza a própria ficha sem
 * tocar na anterior, o encaminhamento à chefia só sai com a ficha finalizada e a
 * tramitação mostra as fichas finalizadas como links e os pareceres de
 * vistoria, de encaminhamento e de conclusão.
 */
class FichasTramitacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function terceirizado(string $nome): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => $nome]);
    }

    private function servidor(string $nome): User
    {
        return User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create(['name' => $nome]);
    }

    private function processoEmAnalise(User $responsavel): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();
        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
            'assigned_user_id' => $responsavel->id,
        ])->save();

        AnalysisRecord::factory()->create([
            'viability_request_id' => $processo->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'analyst_user_id' => null,
            'per_cnae' => [[
                'cnae' => '4712100',
                'cnae_formatado' => '4712-1/00',
                'is_primary' => true,
                'status_sugerido' => 'deferida',
                'status_escolhido' => 'deferida',
                'justificativa' => null,
            ]],
            'parecer' => null,
        ]);

        return $processo->fresh();
    }

    private function preencherEFinalizar(ViabilityRequest $processo, User $analista, string $parecer): void
    {
        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", ['parecer' => $parecer, 'manual' => true])
            ->assertOk();

        $this->actingAs($analista, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/finalizar")
            ->assertOk();
    }

    private function encaminharAChefia(ViabilityRequest $processo, User $analista): TestResponse
    {
        return $this->actingAs($analista, 'gestao')
            ->from("/gestao/processos/{$processo->id}/ficha")
            ->post("/gestao/processos/{$processo->id}/status-analise", ['status' => 'para_conclusao', 'voltar_para_caixa' => '1']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tramitacao(ViabilityRequest $processo): array
    {
        return app(TramitacaoProcessoService::class)->eventos($processo->fresh());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itens(ViabilityRequest $processo, string $titulo): array
    {
        return array_values(array_filter($this->tramitacao($processo), fn (array $e): bool => $e['to_label'] === $titulo));
    }

    public function test_quem_recebe_parte_da_ficha_finalizada_e_finaliza_a_sua_sem_tocar_na_anterior(): void
    {
        $adriana = $this->terceirizado('Adriana Souza');
        $joao = $this->terceirizado('João Lima');
        $processo = $this->processoEmAnalise($adriana);

        $this->preencherEFinalizar($processo, $adriana, 'Parecer de Adriana.');
        $daAdriana = $processo->analysisRecords()->where('analyst_user_id', $adriana->id)->sole();

        $processo->forceFill(['assigned_user_id' => $joao->id])->save();

        $this->actingAs($joao, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.parecer', 'Parecer de Adriana.')
                ->where('ficha.de_outro_analista', true));

        $this->actingAs($joao, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/nova-revisao")
            ->assertOk()
            ->assertJsonPath('ficha.parecer', 'Parecer de Adriana.')
            ->assertJsonPath('ficha.editavel', true);

        $this->preencherEFinalizar($processo, $joao, 'Parecer de João, com ajuste.');

        $daJoao = $processo->analysisRecords()->where('analyst_user_id', $joao->id)->sole();
        $this->assertSame($daAdriana->id, $daJoao->based_on_record_id);
        $this->assertSame(AnalysisRecordStatus::Finalizada, $daJoao->status);
        $this->assertSame('Parecer de João, com ajuste.', $daJoao->parecer);

        $daAdriana->refresh();
        $this->assertSame(AnalysisRecordStatus::Finalizada, $daAdriana->status);
        $this->assertSame('Parecer de Adriana.', $daAdriana->parecer);
        $this->assertSame($adriana->id, $daAdriana->analyst_user_id);
    }

    public function test_cada_ficha_finalizada_tem_o_seu_link_na_tramitacao(): void
    {
        $adriana = $this->terceirizado('Adriana Souza');
        $joao = $this->terceirizado('João Lima');
        $processo = $this->processoEmAnalise($adriana);

        $this->preencherEFinalizar($processo, $adriana, 'Parecer de Adriana.');
        $processo->forceFill(['assigned_user_id' => $joao->id])->save();
        $this->actingAs($joao, 'gestao')->postJson("/gestao/processos/{$processo->id}/ficha/nova-revisao")->assertOk();
        $this->preencherEFinalizar($processo, $joao, 'Parecer de João.');

        $daAdriana = $processo->analysisRecords()->where('analyst_user_id', $adriana->id)->sole();
        $daJoao = $processo->analysisRecords()->where('analyst_user_id', $joao->id)->sole();
        $finalizadas = $this->itens($processo, 'Ficha de análise finalizada');

        $this->assertCount(2, $finalizadas);
        $this->assertSame(
            ["/gestao/processos/{$processo->id}/ficha?revisao={$daAdriana->revision}&somente_leitura=1", "/gestao/processos/{$processo->id}/ficha?revisao={$daJoao->revision}&somente_leitura=1"],
            array_column($finalizadas, 'link'),
        );
        $this->assertSame(['Adriana Souza', 'João Lima'], array_column($finalizadas, 'ator'));
        $this->assertStringContainsString('de Adriana Souza', $finalizadas[0]['reason']);
        $this->assertStringContainsString('Parecer de Adriana.', $finalizadas[0]['reason']);
        $this->assertStringContainsString('Parecer de João.', $finalizadas[1]['reason']);
        $this->assertNotNull($finalizadas[0]['link_label']);

        $this->actingAs($joao, 'gestao')
            ->get($finalizadas[0]['link'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.revision', $daAdriana->revision)
                ->where('ficha.parecer', 'Parecer de Adriana.')
                ->where('ficha.editavel', false));
    }

    public function test_encaminhar_a_chefia_exige_a_ficha_finalizada(): void
    {
        $adriana = $this->terceirizado('Adriana Souza');
        $processo = $this->processoEmAnalise($adriana);

        $this->actingAs($adriana, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", ['parecer' => 'Rascunho.', 'manual' => true])
            ->assertOk();

        $this->encaminharAChefia($processo, $adriana)
            ->assertRedirect("/gestao/processos/{$processo->id}/ficha")
            ->assertSessionHas('error', 'Finalize a sua ficha de análise antes de encaminhar à chefia.');

        $this->assertSame(AnalysisStatus::EmAnalise, $processo->fresh()->analysis_status);
        $this->assertSame(AnalysisRecordStatus::Rascunho, $processo->analysisRecords()->sole()->status);
    }

    public function test_encaminhar_a_chefia_sem_ficha_nenhuma_e_recusado(): void
    {
        $adriana = $this->terceirizado('Adriana Souza');
        $processo = $this->processoEmAnalise($adriana);

        $this->encaminharAChefia($processo, $adriana)
            ->assertSessionHas('error', 'Finalize a sua ficha de análise antes de encaminhar à chefia.');

        $this->assertSame(AnalysisStatus::EmAnalise, $processo->fresh()->analysis_status);
    }

    public function test_encaminhamento_com_ficha_finalizada_fica_na_tramitacao_com_o_parecer_e_o_link(): void
    {
        $adriana = $this->terceirizado('Adriana Souza');
        $processo = $this->processoEmAnalise($adriana);

        $this->preencherEFinalizar($processo, $adriana, 'Atividade compatível; encaminho para conclusão.');
        $daAdriana = $processo->analysisRecords()->where('analyst_user_id', $adriana->id)->sole();

        $this->encaminharAChefia($processo, $adriana)->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(AnalysisStatus::ParaConclusao, $processo->fresh()->analysis_status);

        $encaminhamentos = $this->itens($processo, 'Encaminhado à chefia');
        $this->assertCount(1, $encaminhamentos);
        $this->assertSame('Adriana Souza', $encaminhamentos[0]['ator']);
        $this->assertStringContainsString('Parecer de encaminhamento: Atividade compatível; encaminho para conclusão.', $encaminhamentos[0]['reason']);
        $this->assertSame("/gestao/processos/{$processo->id}/ficha?revisao={$daAdriana->revision}&somente_leitura=1", $encaminhamentos[0]['link']);
        $this->assertSame([], $this->itens($processo, 'Análise: Para conclusão'));
    }

    public function test_quem_conclui_parte_da_ficha_encaminhada_e_a_conclusao_mostra_o_parecer_e_a_ficha(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $adriana = $this->terceirizado('Adriana Souza');
        $joao = $this->servidor('João Lima');
        $processo = $this->processoEmAnalise($adriana);

        $this->preencherEFinalizar($processo, $adriana, 'Parecer de Adriana.');
        $this->encaminharAChefia($processo, $adriana)->assertSessionHasNoErrors()->assertSessionMissing('error');
        $processo->forceFill(['conclusao_encaminhada_para_user_id' => $joao->id])->save();

        $this->actingAs($joao, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.parecer', 'Parecer de Adriana.')
                ->where('ficha.editavel', true));

        $this->preencherEFinalizar($processo, $joao, 'Parecer de João.');

        $this->actingAs($joao, 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", [
                'resultado' => 'deferido',
                'tipo' => 'definitiva',
                'parecer' => 'Deferido conforme a ficha.',
            ])
            ->assertSessionHasNoErrors();

        $daJoao = $processo->analysisRecords()->where('analyst_user_id', $joao->id)->sole();
        $this->assertSame('Parecer de Adriana.', $processo->analysisRecords()->where('analyst_user_id', $adriana->id)->sole()->parecer);

        $conclusoes = $this->itens($processo, 'Análise: '.AnalysisStatus::AnaliseConcluida->label());
        $this->assertCount(1, $conclusoes);
        $this->assertStringContainsString('Deferido conforme a ficha.', $conclusoes[0]['reason']);
        $this->assertSame("/gestao/processos/{$processo->id}/ficha?revisao={$daJoao->revision}&somente_leitura=1", $conclusoes[0]['link']);
    }

    public function test_conclusao_pela_ficha_sem_parecer_no_dialogo_mostra_o_texto_da_decisao(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $joao = $this->servidor('João Lima');
        $processo = $this->processoEmAnalise($joao);

        $this->preencherEFinalizar($processo, $joao, 'Parecer técnico de João.');

        $this->actingAs($joao, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/concluir-processo")
            ->assertOk();

        $conclusoes = $this->itens($processo, 'Análise: '.AnalysisStatus::AnaliseConcluida->label());
        $this->assertCount(1, $conclusoes);
        $this->assertStringContainsString('Parecer de conclusão: Parecer técnico de João.', $conclusoes[0]['reason']);
    }

    public function test_parecer_da_vistoria_e_motivo_do_encaminhamento_aparecem_na_tramitacao(): void
    {
        $adriana = $this->terceirizado('Adriana Souza');
        $processo = $this->processoEmAnalise($adriana);
        $vistoriador = User::factory()->create(['name' => 'Carla Vistoria']);

        app(AuditService::class)->log(
            logName: 'vistoria',
            event: 'vistoria-encaminhar',
            description: "Processo #{$processo->id} encaminhado à vistoria (setor #1)",
            properties: ['viability_request_id' => $processo->id, 'motivo' => 'Conferir a área ocupada no local.'],
            subject: $processo,
        );

        $vistoria = Inspection::factory()->create([
            'viability_request_id' => $processo->id,
            'status' => InspectionStatus::Concluida,
            'vistoriador_user_id' => $vistoriador->id,
            'parecer' => 'Área ocupada confere com a declarada.',
        ]);

        $this->actingAs($vistoriador, 'gestao');
        app(AuditService::class)->log(
            logName: 'vistoria',
            event: 'ficha-conclusao',
            description: "Conclusão da ficha de vistoria do processo #{$processo->id}",
            properties: ['viability_request_id' => $processo->id, 'inspection_id' => $vistoria->id],
            subject: $vistoria,
        );

        $encaminhada = $this->itens($processo, 'Encaminhado à vistoria');
        $this->assertCount(1, $encaminhada);
        $this->assertStringContainsString('Motivo: Conferir a área ocupada no local.', $encaminhada[0]['reason']);

        $concluida = $this->itens($processo, 'Ficha de vistoria concluída');
        $this->assertCount(1, $concluida);
        $this->assertStringContainsString('Parecer da vistoria: Área ocupada confere com a declarada.', $concluida[0]['reason']);
        $this->assertSame("/gestao/processos/{$processo->id}/vistoria/visualizar?ficha={$vistoria->id}", $concluida[0]['link']);
    }

    public function test_servico_parte_da_ultima_ficha_finalizada_para_quem_ainda_nao_tem_ficha(): void
    {
        $adriana = $this->terceirizado('Adriana Souza');
        $processo = $this->processoEmAnalise($adriana);
        $fichas = app(AnalysisRecordService::class);

        $daAdriana = $fichas->autosave($fichas->current($processo, $adriana), ['parecer' => 'Parecer de Adriana.'], $adriana);
        $daAdriana = $fichas->finalizar($daAdriana, $adriana);

        $daCarlos = $fichas->novaRevisao($processo, $this->terceirizado('Carlos'));

        $this->assertSame($daAdriana->id, $daCarlos->based_on_record_id);
        $this->assertSame('Parecer de Adriana.', $daCarlos->parecer);
    }
}
