<?php

namespace Tests\Feature\Vistoria;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\Inspection;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Caixa de vistoria no padrão da Caixa do setor: abas Para distribuir e
 * Distribuídos, coluna Serviço, filtros das caixas e distribuição aos
 * vistoriadores por quem tem a permissão de distribuir.
 */
class VistoriaCaixaDistribuicaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function chefia(Sector $setor): User
    {
        $chefia = User::factory()->gestor()->withAcceptedLgpdTerm()->create();
        $chefia->sectors()->attach($setor);

        return $chefia;
    }

    private function vistoriador(Sector $setor, string $nome = 'Vera Vistoriadora'): User
    {
        $vistoriador = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => $nome]);
        $vistoriador->sectors()->attach($setor);

        return $vistoriador;
    }

    private function processoEmVistoria(Sector $setor, ?User $responsavel = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill([
            'sector_id' => $setor->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::Vistoriar,
            'analysis_stage' => AnalysisStage::Analise,
            'assigned_user_id' => $responsavel?->id,
        ])->save();

        return $processo;
    }

    public function test_quem_distribui_abre_a_caixa_de_vistoria(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/vistorias/index')
                ->where('podeDistribuir', true));
    }

    public function test_aba_para_distribuir_traz_so_os_sem_vistoriador_e_distribuidos_os_atribuidos(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $vera = $this->vistoriador($setor);
        $semVistoriador = $this->processoEmVistoria($setor);
        $comVistoriador = $this->processoEmVistoria($setor, $vera);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias?situacao=designar')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.id', $semVistoriador->id));

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias?situacao=distribuidos')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.id', $comVistoriador->id)
                ->where('vistorias.data.0.responsavel', 'Vera Vistoriadora'));
    }

    public function test_processo_distribuido_sem_ficha_aparece_como_distribuido_e_abre_a_ficha(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $vera = $this->vistoriador($setor);
        $this->processoEmVistoria($setor, $vera);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias')
            ->assertInertia(fn (Assert $page) => $page
                ->where('vistorias.data.0.situacao', 'distribuido')
                ->where('vistorias.data.0.situacao_label', 'Distribuído'));
    }

    public function test_abas_incluem_para_distribuir_e_distribuidos_com_totais(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $this->processoEmVistoria($setor);
        $this->processoEmVistoria($setor, $this->vistoriador($setor));

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias')
            ->assertInertia(fn (Assert $page) => $page
                ->where('abas', fn ($abas) => collect($abas)->pluck('total', 'label')->all() === [
                    'Todas' => 2,
                    'Para distribuir' => 1,
                    'Distribuídos' => 1,
                    'Em campo' => 0,
                    'Concluídas' => 0,
                ]));
    }

    public function test_linha_traz_os_servicos_do_processo(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $processo = $this->processoEmVistoria($setor);
        ViabilityRequestEvento::factory()->create([
            'viability_request_id' => $processo->id,
            'codigo' => '101',
            'nome' => 'Alteração de endereço dentro do mesmo município',
        ]);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias')
            ->assertInertia(fn (Assert $page) => $page
                ->where('vistorias.data.0.servicos', ['Alteração de endereço dentro do mesmo município']));
    }

    public function test_filtros_das_caixas_valem_na_caixa_de_vistoria(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $alvo = $this->processoEmVistoria($setor);
        $alvo->forceFill(['address_complement' => 'Sala 12'])->save();
        $this->processoEmVistoria($setor);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias?complemento=sala')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.id', $alvo->id));

        $cnae = Cnae::factory()->create(['code' => '5611201', 'description' => 'Restaurantes']);
        $alvo->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias?cnae=5611-2/01')
            ->assertInertia(fn (Assert $page) => $page
                ->has('vistorias.data', 1)
                ->where('vistorias.data.0.id', $alvo->id));
    }

    public function test_props_oferecem_vistoriadores_do_setor_para_distribuir(): void
    {
        $setor = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $this->vistoriador($setor, 'Vera do Setor');
        $this->vistoriador($outroSetor, 'Outra de Fora');

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias')
            ->assertInertia(fn (Assert $page) => $page
                ->where('vistoriadores', fn ($lista) => collect($lista)->pluck('name')->contains('Vera do Setor')
                    && ! collect($lista)->pluck('name')->contains('Outra de Fora'))
                ->where('distribuiEmQualquerSetor', false));
    }

    public function test_chefia_distribui_processo_em_vistoria_a_vistoriador_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $vera = $this->vistoriador($setor);
        $processo = $this->processoEmVistoria($setor);

        $this->actingAs($chefia, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $vera->id,
            ])
            ->assertSessionMissing('error');

        $this->assertSame($vera->id, $processo->refresh()->assigned_user_id);
    }

    public function test_vistoriador_de_outro_setor_e_recusado_sem_aviso_de_sucesso(): void
    {
        $setorDaChefia = Sector::factory()->create();
        $setorDoProcesso = Sector::factory()->create();
        $chefia = $this->chefia($setorDaChefia);
        $chefia->sectors()->attach($setorDoProcesso);
        $deFora = $this->vistoriador($setorDaChefia, 'Vera de Outro Setor');
        $processo = $this->processoEmVistoria($setorDoProcesso);

        $this->actingAs($chefia, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $deFora->id,
            ])
            ->assertSessionMissing('status')
            ->assertSessionHas('warning', fn (string $mensagem): bool => str_contains($mensagem, 'Vera de Outro Setor'));

        $this->assertNull($processo->refresh()->assigned_user_id);
    }

    public function test_redistribuir_vistoria_passa_a_ficha_em_preenchimento_ao_novo_vistoriador(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $vera = $this->vistoriador($setor);
        $bia = $this->vistoriador($setor, 'Bia Vistoriadora');
        $processo = $this->processoEmVistoria($setor, $vera);
        $ficha = Inspection::factory()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vera->id,
            'bairro' => 'Rascunho preservado',
        ]);

        $this->actingAs($chefia, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $bia->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($bia->id, $processo->fresh()->assigned_user_id);
        $this->assertSame($bia->id, $ficha->fresh()->vistoriador_user_id);
        $this->assertSame('Rascunho preservado', $ficha->fresh()->bairro);

        $activity = Activity::query()
            ->where('event', 'redistribuir')
            ->where('subject_id', $processo->id)
            ->latest('id')
            ->first();
        $this->assertSame($vera->id, $activity->properties['vistoriador_anterior_id']);
        $this->assertSame($ficha->id, $activity->properties['inspection_id']);
    }

    public function test_redistribuir_vistoria_a_quem_nao_vistoria_e_recusado(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $vera = $this->vistoriador($setor);
        $semVistoria = User::factory()->withAcceptedLgpdTerm()->create();
        $semVistoria->givePermissionTo(['acessar-gestao', 'analisar-processos']);
        $semVistoria->sectors()->attach($setor);
        $processo = $this->processoEmVistoria($setor, $vera);
        $ficha = Inspection::factory()->create(['viability_request_id' => $processo->id, 'vistoriador_user_id' => $vera->id]);

        $this->actingAs($chefia, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $semVistoria->id,
            ])
            ->assertSessionHas('error');

        $this->assertSame($vera->id, $processo->fresh()->assigned_user_id);
        $this->assertSame($vera->id, $ficha->fresh()->vistoriador_user_id);
    }

    public function test_redistribuir_vistoriado_mantem_a_ficha_concluida_com_quem_vistoriou(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $vera = $this->vistoriador($setor);
        $bia = $this->vistoriador($setor, 'Bia Vistoriadora');
        $processo = $this->processoEmVistoria($setor, $vera);
        $processo->forceFill(['analysis_status' => AnalysisStatus::Vistoriado])->save();
        $ficha = Inspection::factory()->concluida()->create(['viability_request_id' => $processo->id, 'vistoriador_user_id' => $vera->id]);

        $this->actingAs($chefia, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $bia->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($bia->id, $processo->fresh()->assigned_user_id);
        $this->assertSame($vera->id, $ficha->fresh()->vistoriador_user_id);
        $this->assertSame(InspectionStatus::Concluida, $ficha->fresh()->status);
    }

    public function test_consulta_marca_distribuidos_e_em_campo_como_redistribuiveis(): void
    {
        $setor = Sector::factory()->create();
        $chefia = $this->chefia($setor);
        $vera = $this->vistoriador($setor);
        $livre = $this->processoEmVistoria($setor);
        $distribuido = $this->processoEmVistoria($setor, $vera);
        $emCampo = $this->processoEmVistoria($setor, $vera);
        Inspection::factory()->create(['viability_request_id' => $emCampo->id, 'vistoriador_user_id' => $vera->id]);

        $this->actingAs($chefia, 'gestao')
            ->get('/gestao/vistorias?situacao=todas')
            ->assertInertia(function (Assert $page) use ($livre, $distribuido, $emCampo): void {
                $linhas = collect($page->toArray()['props']['vistorias']['data'])->keyBy('id');

                $this->assertFalse($linhas[$livre->id]['pode_redistribuir']);
                $this->assertTrue($linhas[$distribuido->id]['pode_redistribuir']);
                $this->assertTrue($linhas[$emCampo->id]['pode_redistribuir']);
            });
    }
}
