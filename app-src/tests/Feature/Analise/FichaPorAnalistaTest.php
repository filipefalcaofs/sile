<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisRecordStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnaliseTecnicaDecisionService;
use App\Services\Analise\AnalysisRecordService;
use App\Services\Analise\FichaDeOutroAnalistaException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * HU.183 — a ficha de análise pertence a quem a preencheu. O analista nunca
 * grava na ficha de outro, a ficha nova parte da última ficha do próprio
 * analista e, sem ficha própria, da base do motor. A decisão guarda a ficha
 * em que se baseou.
 */
class FichaPorAnalistaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fichas(): AnalysisRecordService
    {
        return app(AnalysisRecordService::class);
    }

    private function terceirizado(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    private function servidor(): User
    {
        return User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
    }

    private function processoComBaseDoMotor(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

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
                'codigo_louos' => 'C-1',
            ]],
            'conditions' => ['Condicionante do motor'],
            'parecer' => null,
        ]);

        return $processo;
    }

    /**
     * @return array<string, mixed>
     */
    private function preenchimento(string $parecer): array
    {
        return [
            'per_cnae' => [['cnae' => '4712100', 'status_escolhido' => 'indeferida', 'justificativa' => "Justificativa de {$parecer}"]],
            'parecer' => $parecer,
        ];
    }

    public function test_salvar_a_base_do_motor_torna_o_analista_dono_dela(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();

        $this->actingAs($a, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", $this->preenchimento('Parecer de A'))
            ->assertOk();

        $ficha = $processo->analysisRecords()->sole();
        $this->assertSame($a->id, $ficha->analyst_user_id);
        $this->assertSame('Parecer de A', $ficha->parecer);
    }

    public function test_outro_analista_nao_grava_por_cima_da_ficha_de_quem_comecou(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();
        $b = $this->terceirizado();

        $this->actingAs($a, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", $this->preenchimento('Parecer de A'))
            ->assertOk();

        $this->actingAs($b, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", ['parecer' => 'Parecer de B'])
            ->assertForbidden();

        $this->assertSame('Parecer de A', $processo->analysisRecords()->sole()->parecer);

        $this->actingAs($b, 'gestao')->postJson("/gestao/processos/{$processo->id}/ficha/nova-revisao")->assertOk();
        $this->actingAs($b, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/ficha", ['parecer' => 'Parecer de B'])
            ->assertOk();

        $daA = $processo->analysisRecords()->where('analyst_user_id', $a->id)->sole();
        $daB = $processo->analysisRecords()->where('analyst_user_id', $b->id)->sole();

        $this->assertSame('Parecer de A', $daA->parecer);
        $this->assertSame('Parecer de B', $daB->parecer);
        $this->assertNotSame($daA->id, $daB->id);
    }

    public function test_segundo_analista_parte_da_base_do_motor_sem_as_escolhas_do_primeiro(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();
        $b = $this->terceirizado();

        $this->fichas()->autosave($this->fichas()->current($processo, $a), $this->preenchimento('Parecer de A'), $a);

        $daB = $this->fichas()->novaRevisao($processo, $b);

        $this->assertNull($daB->analyst_user_id);
        $this->assertNull($daB->parecer);
        $this->assertSame('deferida', $daB->per_cnae[0]['status_escolhido']);
        $this->assertNull($daB->per_cnae[0]['justificativa']);
        $this->assertSame('C-1', $daB->per_cnae[0]['codigo_louos']);
        $this->assertSame(['Condicionante do motor'], $daB->conditions);
        $this->assertNull($daB->based_on_record_id);
    }

    public function test_a_base_do_motor_nao_e_recriada_a_cada_abertura(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();
        $b = $this->terceirizado();

        $this->fichas()->autosave($this->fichas()->current($processo, $a), ['parecer' => 'Parecer de A'], $a);

        $primeira = $this->fichas()->novaRevisao($processo, $b);
        $segunda = $this->fichas()->novaRevisao($processo, $b);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(2, $processo->analysisRecords()->count());
    }

    public function test_servico_recusa_gravar_na_ficha_de_outro_analista(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();
        $b = $this->terceirizado();

        $daA = $this->fichas()->autosave($this->fichas()->current($processo, $a), ['parecer' => 'Parecer de A'], $a);

        $this->expectException(FichaDeOutroAnalistaException::class);

        $this->fichas()->autosave($daA, ['parecer' => 'Parecer de B'], $b);
    }

    public function test_servico_recusa_finalizar_a_ficha_de_outro_analista(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();
        $b = $this->terceirizado();

        $daA = $this->fichas()->autosave($this->fichas()->current($processo, $a), ['parecer' => 'Parecer de A'], $a);

        $this->expectException(FichaDeOutroAnalistaException::class);

        $this->fichas()->finalizar($daA, $b);
    }

    public function test_nova_revisao_copia_a_ultima_ficha_do_proprio_analista(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();
        $b = $this->terceirizado();

        $daA = $this->fichas()->autosave($this->fichas()->current($processo, $a), $this->preenchimento('Parecer de A'), $a);
        $daA = $this->fichas()->finalizar($daA, $a);
        $this->fichas()->autosave($this->fichas()->novaRevisao($processo, $b), ['parecer' => 'Parecer de B'], $b);

        $nova = $this->fichas()->novaRevisao($processo, $a);

        $this->assertSame($a->id, $nova->analyst_user_id);
        $this->assertSame('Parecer de A', $nova->parecer);
        $this->assertSame('indeferida', $nova->per_cnae[0]['status_escolhido']);
        $this->assertSame($daA->id, $nova->based_on_record_id);
        $this->assertSame(AnalysisRecordStatus::Rascunho, $nova->status);
        $this->assertSame(AnalysisRecordStatus::Finalizada, $daA->fresh()->status);
    }

    public function test_nova_revisao_com_rascunho_aberto_devolve_o_rascunho(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();

        $rascunho = $this->fichas()->autosave($this->fichas()->current($processo, $a), ['parecer' => 'Parecer de A'], $a);

        $nova = $this->fichas()->novaRevisao($processo, $a);

        $this->assertSame($rascunho->id, $nova->id);
        $this->assertSame(1, $processo->analysisRecords()->count());
    }

    public function test_nova_revisao_pela_rota_usa_a_ficha_de_quem_pede(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();
        $b = $this->terceirizado();

        $daA = $this->fichas()->autosave($this->fichas()->current($processo, $a), ['parecer' => 'Parecer de A'], $a);
        $this->fichas()->finalizar($daA, $a);

        $this->actingAs($b, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/nova-revisao")
            ->assertOk()
            ->assertJsonPath('ficha.parecer', null);
    }

    public function test_ir_para_conclusao_deixa_copia_sem_dono_para_quem_conclui(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();

        $daA = $this->fichas()->autosave($this->fichas()->current($processo, $a), $this->preenchimento('Parecer de A'), $a);

        $copia = $this->fichas()->guardarParaConclusao($processo, $a);

        $this->assertSame(AnalysisRecordStatus::Finalizada, $daA->fresh()->status);
        $this->assertSame($a->id, $daA->fresh()->analyst_user_id);
        $this->assertNull($copia->analyst_user_id);
        $this->assertSame($daA->id, $copia->based_on_record_id);
        $this->assertSame('Parecer de A', $copia->parecer);

        $chefia = $this->servidor();
        $this->assertSame($copia->id, $this->fichas()->current($processo, $chefia)->id);

        $this->fichas()->autosave($copia, ['parecer' => 'Parecer da chefia'], $chefia);
        $this->assertSame($chefia->id, $copia->fresh()->analyst_user_id);
        $this->assertSame('Parecer de A', $daA->fresh()->parecer);
    }

    public function test_copia_de_conclusao_nao_e_oferecida_a_quem_nao_conclui(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();

        $this->fichas()->autosave($this->fichas()->current($processo, $a), $this->preenchimento('Parecer de A'), $a);
        $copia = $this->fichas()->guardarParaConclusao($processo, $a);

        $outro = $this->fichas()->novaRevisao($processo, $this->terceirizado());

        $this->assertNotSame($copia->id, $outro->id);
        $this->assertNull($outro->parecer);
        $this->assertNull($outro->based_on_record_id);
    }

    public function test_com_a_chave_desligada_quem_conclui_parte_da_base_do_motor(): void
    {
        config(['viabiliza.analise.ficha.conclusao_parte_da_ficha_do_analista' => false]);

        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();

        $daA = $this->fichas()->autosave($this->fichas()->current($processo, $a), $this->preenchimento('Parecer de A'), $a);

        $this->fichas()->guardarParaConclusao($processo, $a);

        $this->assertSame(AnalysisRecordStatus::Finalizada, $daA->fresh()->status);

        $chefia = $this->servidor();
        $this->assertSame($daA->id, $this->fichas()->current($processo, $chefia)->id);

        $daChefia = $this->fichas()->novaRevisao($processo, $chefia);

        $this->assertNull($daChefia->parecer);
        $this->assertNull($daChefia->based_on_record_id);
        $this->assertSame('deferida', $daChefia->per_cnae[0]['status_escolhido']);
    }

    public function test_abrir_a_ficha_de_outro_analista_mostra_so_para_leitura_sem_criar_ficha(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();

        $this->fichas()->autosave($this->fichas()->current($processo, $a), ['parecer' => 'Parecer de A'], $a);

        $this->actingAs($this->terceirizado(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.parecer', 'Parecer de A')
                ->where('ficha.editavel', false)
                ->where('ficha.de_outro_analista', true));

        $this->assertSame(1, $processo->analysisRecords()->count());
    }

    public function test_concluir_sobre_a_ficha_de_outro_analista_mantem_o_autor_da_ficha(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComBaseDoMotor();
        $processo->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();
        $a = $this->terceirizado();
        $servidor = $this->servidor();

        $daA = $this->fichas()->autosave($this->fichas()->current($processo, $a), ['parecer' => 'Parecer de A'], $a);

        $this->actingAs($servidor, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/ficha/concluir-processo")
            ->assertOk();

        $this->assertSame(AnalysisRecordStatus::Finalizada, $daA->fresh()->status);
        $this->assertSame($a->id, $daA->fresh()->analyst_user_id);
        $decisao = $processo->fresh()->decision;
        $this->assertSame($servidor->id, $decisao->decided_by_user_id);
        $this->assertSame($daA->id, $decisao->analysis_record_id);
    }

    public function test_decisao_guarda_a_ficha_em_que_se_baseou(): void
    {
        Event::fake([ResultadoEmitido::class]);

        $processo = $this->processoComBaseDoMotor();
        $servidor = $this->servidor();

        $ficha = $this->fichas()->autosave($this->fichas()->current($processo, $servidor), ['parecer' => 'Parecer favorável'], $servidor);
        $ficha = $this->fichas()->finalizar($ficha, $servidor);

        app(AnaliseTecnicaDecisionService::class)->decide($ficha, $servidor);

        $this->assertSame($ficha->id, $processo->fresh()->decision->analysis_record_id);
    }

    public function test_trava_de_um_rascunho_por_analista_no_processo(): void
    {
        $processo = $this->processoComBaseDoMotor();
        $a = $this->terceirizado();

        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 2, 'analyst_user_id' => $a->id]);

        $this->expectException(QueryException::class);

        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 3, 'analyst_user_id' => $a->id]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function rotasDeConclusao(): array
    {
        return [
            ['post', 'ficha/concluir-processo'],
            ['post', 'conclusao-analise'],
            ['post', 'decidir'],
            ['put', 'justificativa-regin'],
        ];
    }

    #[DataProvider('rotasDeConclusao')]
    public function test_terceirizado_e_recusado_nas_rotas_de_conclusao(string $metodo, string $caminho): void
    {
        $processo = $this->processoComBaseDoMotor();
        $processo->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();

        $this->actingAs($this->terceirizado(), 'gestao')
            ->json($metodo, "/gestao/processos/{$processo->id}/{$caminho}", ['resultado' => 'deferido'])
            ->assertForbidden();

        $this->assertNull($processo->fresh()->decision);
    }
}
