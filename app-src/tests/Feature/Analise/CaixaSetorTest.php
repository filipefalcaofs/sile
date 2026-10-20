<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Models\ViabilityServiceType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Caixa do setor na retaguarda (HU-076/077): o analista vê e assume os processos
 * em análise do(s) seu(s) setor(es) (analisar-processos); o gestor distribui —
 * single ou lote — a um analista do setor (distribuir-processos). A caixa NÃO
 * tira o processo do setor (RN-004); a lista vem ordenada por prazo
 * (analysis_due_at). Sem permissão, a ação é bloqueada e auditada no ponto único
 * (CA-04). Espelha o ResultadoExpressoController (server-driven, props Inertia
 * inspecionadas sem exigir o .tsx — a tela é 10-16).
 */
class CaixaSetorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function analistaDoSetor(Sector $sector): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($sector);

        return $analista;
    }

    private function apoioDoSetor(Sector $sector): User
    {
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($sector);

        return $apoio;
    }

    /**
     * Distribui só nos próprios setores: sem distribuir-qualquer-setor.
     */
    private function gestorDoSetor(Sector $sector): User
    {
        $gestor = $this->gestor();
        $gestor->sectors()->attach($sector);

        return $gestor;
    }

    private function processoNaCaixa(Sector $sector): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create();

        $request->forceFill([
            'sector_id' => $sector->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_stage' => AnalysisStage::Distribuicao,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDays(2),
        ])->save();

        return $request;
    }

    public function test_analista_ve_so_a_caixa_dos_seus_setores(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setorA);

        $doSetorA = $this->processoNaCaixa($setorA);
        $doSetorB = $this->processoNaCaixa($setorB);

        $response = $this->actingAs($analista, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/caixa-setor/index', $page['component']);

        $ids = collect($page['props']['processos']['data'])->pluck('id')->all();
        $this->assertContains($doSetorA->id, $ids);
        $this->assertNotContains($doSetorB->id, $ids);
    }

    public function test_index_expoe_servico_e_cnaes_com_o_principal_primeiro(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $servico = ViabilityServiceType::factory()->create();
        $secundario = Cnae::factory()->create();
        $principal = Cnae::factory()->create();
        $processo->forceFill(['service_type_id' => $servico->id])->save();
        $processo->cnaes()->attach($secundario->id, ['is_primary' => false]);
        $processo->cnaes()->attach($principal->id, ['is_primary' => true]);

        $linha = collect(
            $this->actingAs($analista, 'gestao')
                ->get('/gestao/caixa-setor')
                ->assertOk()
                ->viewData('page')['props']['processos']['data']
        )->firstWhere('id', $processo->id);

        $this->assertSame($servico->name, $linha['servico']);
        $this->assertSame(
            [$principal->formatted_code, $secundario->formatted_code],
            collect($linha['cnaes'])->pluck('codigo')->all(),
        );
        $this->assertTrue($linha['cnaes'][0]['principal']);
    }

    public function test_servico_da_linha_sao_os_eventos_da_juceb_ou_o_tipo_interno(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $servico = ViabilityServiceType::factory()->create();

        $daJuceb = $this->processoNaCaixa($setor);
        $daJuceb->forceFill(['service_type_id' => $servico->id])->save();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $daJuceb->id, 'codigo' => '211', 'nome' => 'Alteração de endereço dentro do mesmo município']);
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $daJuceb->id, 'codigo' => '244', 'nome' => 'Alteração de atividades econômicas (principal e secundárias)']);

        $doPortal = $this->processoNaCaixa($setor);
        $doPortal->forceFill(['service_type_id' => $servico->id])->save();

        $linhas = collect(
            $this->actingAs($analista, 'gestao')
                ->get('/gestao/caixa-setor')
                ->assertOk()
                ->viewData('page')['props']['processos']['data']
        )->keyBy('id');

        $this->assertSame(
            ['Alteração de endereço dentro do mesmo município', 'Alteração de atividades econômicas (principal e secundárias)'],
            $linhas[$daJuceb->id]['servicos'],
        );
        $this->assertSame([$servico->name], $linhas[$doPortal->id]['servicos']);
    }

    public function test_filtro_de_servico_aceita_evento_da_juceb_e_lista_os_eventos_nas_opcoes(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);

        $comEvento = $this->processoNaCaixa($setor);
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $comEvento->id, 'codigo' => '244', 'nome' => 'Alteração de atividades econômicas (principal e secundárias)']);
        $semEvento = $this->processoNaCaixa($setor);

        $props = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?servico=e244')
            ->assertOk()
            ->viewData('page')['props'];

        $ids = collect($props['processos']['data'])->pluck('id')->all();
        $this->assertContains($comEvento->id, $ids);
        $this->assertNotContains($semEvento->id, $ids);
        $this->assertContains(
            ['value' => 'e244', 'label' => 'Alteração de atividades econômicas (principal e secundárias)'],
            $props['servicoOptions'],
        );
    }

    public function test_index_lista_ordenada_por_prazo(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $menosUrgente = $this->processoNaCaixa($setor);
        $menosUrgente->forceFill(['analysis_due_at' => now()->addDays(20)])->save();

        $maisUrgente = $this->processoNaCaixa($setor);
        $maisUrgente->forceFill(['analysis_due_at' => now()->addDay()])->save();

        $response = $this->actingAs($analista, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['processos']['data'])->pluck('id')->all();

        $this->assertLessThan(
            array_search($menosUrgente->id, $ids, true),
            array_search($maisUrgente->id, $ids, true),
            'O processo de prazo mais curto deve vir primeiro na caixa.',
        );
    }

    public function test_index_do_gestor_expoe_analistas_do_setor_e_pode_distribuir(): void
    {
        $setor = Sector::factory()->create();
        $gestor = $this->gestor();
        $gestor->sectors()->attach($setor);
        $analista = $this->analistaDoSetor($setor);
        $this->processoNaCaixa($setor);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertTrue($props['podeDistribuir'], 'O gestor (distribuir-processos) deve poder distribuir.');
        $this->assertTrue($props['podeAssumir'], 'O gestor (analisar-processos) também pode assumir.');

        $analistaIds = collect($props['analistas'])->pluck('id')->all();
        $this->assertContains($analista->id, $analistaIds, 'A lista de analistas do setor deve alimentar o seletor de distribuição.');
    }

    public function test_apoio_ve_a_caixa_e_distribui_mas_nao_assume(): void
    {
        // Perfil Apoio (tramitação): vê a caixa do setor e distribui a um
        // analista específico, mas NÃO assume — assumir é de quem analisa.
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $ids = collect($props['processos']['data'])->pluck('id')->all();
        $this->assertContains($processo->id, $ids, 'O apoio deve ver os processos da caixa do seu setor.');
        $this->assertTrue($props['podeDistribuir'], 'O apoio (distribuir-processos) deve poder distribuir.');
        $this->assertFalse($props['podeAssumir'], 'O apoio (sem analisar-processos) não assume processos.');

        $analistaIds = collect($props['analistas'])->pluck('id')->all();
        $this->assertContains($analista->id, $analistaIds, 'O seletor de distribuição do apoio lista os analistas do setor.');
    }

    public function test_apoio_distribui_processo_a_um_analista_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
    }

    public function test_distribuir_coloca_o_processo_na_caixa_de_entrada_do_analista(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);
        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame(AnalysisStatus::Analisar, $processo->fresh()->analysis_status);

        $fila = $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos/fila')
            ->assertOk();

        $ids = collect($fila->viewData('page')['props']['processos']['data'])->pluck('id')->all();
        $this->assertContains($processo->id, $ids);
        $this->assertSame('meus', $fila->viewData('page')['props']['modo']);
    }

    /**
     * Processo na caixa E no eixo operacional de vistoria (Vistoriar).
     */
    private function processoEmVistoria(Sector $sector): ViabilityRequest
    {
        $processo = $this->processoNaCaixa($sector);
        $processo->forceFill(['analysis_status' => AnalysisStatus::Vistoriar])->save();

        return $processo;
    }

    /**
     * Membro do setor com analisar-processos mas SEM preencher-ficha-vistoria
     * (o papel analista inclui as duas; aqui montamos permissão a permissão).
     */
    private function analistaSemVistoria(Sector $sector): User
    {
        $analista = User::factory()->withAcceptedLgpdTerm()->create();
        $analista->givePermissionTo('analisar-processos');
        $analista->sectors()->attach($sector);

        return $analista;
    }

    private function vistoriadorDoSetor(Sector $sector): User
    {
        $vistoriador = User::factory()->withAcceptedLgpdTerm()->create();
        $vistoriador->givePermissionTo(['analisar-processos', 'preencher-ficha-vistoria']);
        Role::firstOrCreate(['name' => 'Vistoriador', 'guard_name' => 'web']);
        $vistoriador->assignRole('Vistoriador');
        $vistoriador->sectors()->attach($sector);

        return $vistoriador;
    }

    public function test_apoio_distribui_processo_em_vistoria_a_vistoriador(): void
    {
        // Regra da vistoria: processo em Vistoriar só é distribuído a quem tem
        // preencher-ficha-vistoria — a distribuição específica do fluxo.
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $vistoriador = $this->vistoriadorDoSetor($setor);
        $processo = $this->processoEmVistoria($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $vistoriador->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($vistoriador->id, $processo->fresh()->assigned_user_id);
    }

    public function test_apoio_distribuir_processo_em_vistoria_a_nao_vistoriador_volta_com_aviso(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $naoVistoriador = $this->analistaSemVistoria($setor);
        $processo = $this->processoEmVistoria($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $naoVistoriador->id,
            ])
            ->assertSessionHas('warning');

        $this->assertNull($processo->fresh()->assigned_user_id);
    }

    public function test_index_expoe_vistoriadores_do_setor_e_status_operacional_da_linha(): void
    {
        // A caixa alimenta o seletor da distribuição: processos em vistoria
        // distribuem para a lista de vistoriadores (preencher-ficha-vistoria),
        // não para a lista geral de analistas.
        $setor = Sector::factory()->create();
        $gestor = $this->gestor();
        $gestor->sectors()->attach($setor);
        $vistoriador = $this->vistoriadorDoSetor($setor);
        $analistaComum = $this->analistaSemVistoria($setor);
        $analistaComPermissaoDeVistoria = $this->vistoriadorDoSetor($setor);
        $analistaComPermissaoDeVistoria->removeRole('Vistoriador');
        $administrador = $this->vistoriadorDoSetor($setor);
        $administrador->removeRole('Vistoriador');
        $administrador->assignRole(Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']));
        $processo = $this->processoEmVistoria($setor);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $vistoriadoresIds = collect($props['vistoriadores'])->pluck('id')->all();
        $this->assertContains($vistoriador->id, $vistoriadoresIds);
        $this->assertNotContains($analistaComum->id, $vistoriadoresIds);
        $this->assertNotContains($analistaComPermissaoDeVistoria->id, $vistoriadoresIds);
        $this->assertContains($administrador->id, $vistoriadoresIds);

        $linha = collect($props['processos']['data'])->firstWhere('id', $processo->id);
        $this->assertSame('vistoriar', $linha['analysis_status']);
    }

    public function test_apoio_nao_pode_assumir_processo(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertForbidden();

        $this->assertNull($processo->fresh()->assigned_user_id);
    }

    public function test_index_do_analista_nao_expoe_distribuicao(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $this->processoNaCaixa($setor);

        $response = $this->actingAs($analista, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertFalse($props['podeDistribuir'], 'O analista (sem distribuir-processos) não distribui — só assume.');
        $this->assertTrue($props['podeAssumir'], 'O analista (analisar-processos) pode assumir.');
        $this->assertSame([], $props['analistas'], 'Sem distribuição, não há lista de analistas no payload (minimização).');
    }

    public function test_index_exige_analisar_ou_distribuir_processos_e_audita_o_403(): void
    {
        // Usuário acessa a gestão mas NÃO tem analisar-processos nem
        // distribuir-processos (CA-04) — a caixa é de quem analisa ou tramita.
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_distribuir_exige_distribuir_processos(): void
    {
        // O analista tem analisar-processos, mas distribuir é do gestor (CA-04).
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertForbidden();

        $this->assertNull($processo->fresh()->assigned_user_id);
    }

    public function test_gestor_distribui_processo_via_service(): void
    {
        $setor = Sector::factory()->create();
        $gestor = $this->gestor();
        $gestor->sectors()->attach($setor);
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
    }

    public function test_gestor_distribui_em_lote(): void
    {
        $setor = Sector::factory()->create();
        $gestor = $this->gestor();
        $gestor->sectors()->attach($setor);
        $analista = $this->analistaDoSetor($setor);
        $p1 = $this->processoNaCaixa($setor);
        $p2 = $this->processoNaCaixa($setor);

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$p1->id, $p2->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $p1->fresh()->assigned_user_id);
        $this->assertSame($analista->id, $p2->fresh()->assigned_user_id);
    }

    public function test_distribuir_rejeita_processo_de_setor_alheio(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $gestor = $this->gestorDoSetor($setorA);
        $analistaDoSetorB = $this->analistaDoSetor($setorB);
        $processoDoSetorA = $this->processoNaCaixa($setorA);
        $processoDoSetorB = $this->processoNaCaixa($setorB);

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$processoDoSetorA->id, $processoDoSetorB->id],
                'analista_id' => $analistaDoSetorB->id,
            ])
            ->assertSessionHas('error');

        $this->assertNull($processoDoSetorA->fresh()->assigned_user_id);
        $this->assertNull($processoDoSetorB->fresh()->assigned_user_id);
    }

    public function test_distribuir_informa_o_motivo_de_cada_processo_indisponivel(): void
    {
        $setorA = Sector::factory()->create(['name' => 'Setor Alfa']);
        $setorB = Sector::factory()->create(['name' => 'Setor Beta']);
        $gestor = $this->gestorDoSetor($setorA);
        $analista = $this->analistaDoSetor($setorA);
        $alheio = $this->processoNaCaixa($setorB);
        $jaDistribuido = $this->processoNaCaixa($setorA);
        $jaDistribuido->forceFill(['assigned_user_id' => $analista->id, 'assigned_at' => now()])->save();

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir', [
                'request_ids' => [$alheio->id, $jaDistribuido->id],
                'analista_id' => $analista->id,
            ])
            ->assertSessionHas('error', function (string $mensagem) use ($analista): bool {
                return str_contains($mensagem, 'pertence ao setor Setor Beta')
                    && str_contains($mensagem, "já está distribuído para {$analista->name}");
            });
    }

    public function test_analista_assume_processo_da_sua_caixa(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($analista, 'gestao')
            ->post("/gestao/caixa-setor/{$processo->id}/assumir")
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
        $this->assertSame(AnalysisStage::Analise, $processo->fresh()->analysis_stage);
    }

    public function test_visao_padrao_lista_apenas_processos_sem_responsavel(): void
    {
        // Aba "Para distribuir" (padrão): só o que ainda não tem analista.
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $livre = $this->processoNaCaixa($setor);
        $atribuido = $this->processoNaCaixa($setor);
        $atribuido->forceFill(['assigned_user_id' => $analista->id, 'assigned_at' => now()])->save();

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk();

        $props = $response->viewData('page')['props'];
        $ids = collect($props['processos']['data'])->pluck('id')->all();

        $this->assertContains($livre->id, $ids);
        $this->assertNotContains($atribuido->id, $ids, 'Processo já distribuído não aparece na aba Para distribuir.');
        $this->assertSame('para_distribuir', $props['visao']);
        $this->assertSame(1, $props['contadores']['para_distribuir']);
        $this->assertSame(1, $props['contadores']['distribuidos']);
    }

    public function test_visao_distribuidos_lista_atribuidos_com_flag_de_redistribuicao(): void
    {
        // Aba "Distribuídos": só atribuídos; pode_redistribuir é falso após a
        // conclusão da análise (regra única AnalysisStatus::permiteRedistribuicao).
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $emAnalise = $this->processoNaCaixa($setor);
        $emAnalise->forceFill([
            'assigned_user_id' => $analista->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        $concluido = $this->processoNaCaixa($setor);
        $concluido->forceFill([
            'assigned_user_id' => $analista->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::AnaliseConcluida,
        ])->save();

        $livre = $this->processoNaCaixa($setor);

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?visao=distribuidos')
            ->assertOk();

        $props = $response->viewData('page')['props'];
        $itens = collect($props['processos']['data']);

        $this->assertSame('distribuidos', $props['visao']);
        $this->assertFalse($itens->contains('id', $livre->id), 'Processo sem responsável não aparece na aba Distribuídos.');
        $this->assertTrue($itens->firstWhere('id', $emAnalise->id)['pode_redistribuir']);
        $this->assertFalse($itens->firstWhere('id', $concluido->id)['pode_redistribuir']);
    }

    public function test_apoio_redistribui_processo_para_outra_analista_do_setor(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analistaA = $this->analistaDoSetor($setor);
        $analistaB = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);
        $processo->forceFill([
            'assigned_user_id' => $analistaA->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analistaB->id,
            ])
            ->assertSessionHas('status');

        $this->assertSame($analistaB->id, $processo->fresh()->assigned_user_id);
    }

    public function test_redistribuir_rejeita_processo_de_setor_alheio(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $gestor = $this->gestorDoSetor($setorA);
        $analistaDoSetorB = $this->analistaDoSetor($setorB);
        $novoAnalistaDoSetorB = $this->analistaDoSetor($setorB);
        $processo = $this->processoNaCaixa($setorB);
        $processo->forceFill([
            'assigned_user_id' => $analistaDoSetorB->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $novoAnalistaDoSetorB->id,
            ])
            ->assertNotFound();

        $this->assertSame($analistaDoSetorB->id, $processo->fresh()->assigned_user_id);
    }

    public function test_redistribuir_retorna_erro_controlado_quando_a_analise_ja_foi_concluida(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analistaA = $this->analistaDoSetor($setor);
        $analistaB = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);
        $processo->forceFill([
            'assigned_user_id' => $analistaA->id,
            'assigned_at' => now(),
            'analysis_status' => AnalysisStatus::AnaliseConcluida,
        ])->save();

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/redistribuir', [
                'request_ids' => [$processo->id],
                'analista_id' => $analistaB->id,
            ])
            ->assertSessionHas('error');

        $this->assertSame($analistaA->id, $processo->fresh()->assigned_user_id);
    }

    public function test_filtra_por_analysis_status_e_ajusta_contadores(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);

        $paraDistribuir = $this->processoNaCaixa($setor);
        $paraDistribuir->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir])->save();

        $encaminhado = $this->processoNaCaixa($setor);
        $encaminhado->forceFill(['analysis_status' => AnalysisStatus::Encaminhado])->save();

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?analysis_status=encaminhado')
            ->assertOk();

        $props = $response->viewData('page')['props'];
        $ids = collect($props['processos']['data'])->pluck('id')->all();

        $this->assertContains($encaminhado->id, $ids);
        $this->assertNotContains($paraDistribuir->id, $ids);
        $this->assertSame('encaminhado', $props['filtros']['analysis_status']);
        $this->assertSame(1, $props['contadores']['para_distribuir'], 'Os contadores das abas respeitam o filtro.');
    }

    public function test_filtra_por_servico_bap_e_protocolo(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);

        $servico = ViabilityServiceType::query()->first()
            ?? ViabilityServiceType::factory()->create();

        $alvo = $this->processoNaCaixa($setor);
        $alvo->forceFill(['service_type_id' => $servico->id, 'external_reference' => 'BAP-XYZ-1'])->save();

        $outro = $this->processoNaCaixa($setor);
        $outro->forceFill(['external_reference' => 'BAP-OUTRO-2'])->save();

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?bap=XYZ')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['processos']['data'])->pluck('id')->all();
        $this->assertContains($alvo->id, $ids);
        $this->assertNotContains($outro->id, $ids);
    }

    public function test_index_expoe_opcoes_de_servico_e_status_de_tramitacao(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $this->processoNaCaixa($setor);

        $props = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotEmpty($props['analysisStatusOptions']);
        $this->assertArrayHasKey('servicoOptions', $props);
        $this->assertArrayHasKey('analysis_status', $props['filtros']);
    }

    public function test_filtra_por_periodo_de_entrada(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);

        $hoje = now()->toDateString();

        $deHoje = $this->processoNaCaixa($setor);
        $deHoje->forceFill(['protocoled_at' => now()])->save();

        $antigo = $this->processoNaCaixa($setor);
        $antigo->forceFill(['protocoled_at' => now()->subDays(10)])->save();

        $response = $this->actingAs($apoio, 'gestao')
            ->get("/gestao/caixa-setor?data_de={$hoje}")
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['processos']['data'])->pluck('id')->all();

        $this->assertContains($deHoje->id, $ids);
        $this->assertNotContains($antigo->id, $ids);
    }

    public function test_filtra_por_bairro(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);

        $alvo = $this->processoNaCaixa($setor);
        $alvo->forceFill(['address_neighborhood' => 'Barra'])->save();

        $outro = $this->processoNaCaixa($setor);
        $outro->forceFill(['address_neighborhood' => 'Pituba'])->save();

        $response = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor?bairro=Barra')
            ->assertOk();

        $ids = collect($response->viewData('page')['props']['processos']['data'])->pluck('id')->all();

        $this->assertContains($alvo->id, $ids);
        $this->assertNotContains($outro->id, $ids);
    }

    public function test_central_lista_processos_selecionados_e_carga_dos_analistas(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);

        $p1 = $this->processoNaCaixa($setor);
        $p2 = $this->processoNaCaixa($setor);

        // Carga pré-existente do analista (1 processo já atribuído no setor).
        $jaAtribuido = $this->processoNaCaixa($setor);
        $jaAtribuido->forceFill(['assigned_user_id' => $analista->id, 'analysis_status' => AnalysisStatus::EmAnalise])->save();

        $props = $this->actingAs($apoio, 'gestao')
            ->get("/gestao/caixa-setor/central?ids={$p1->id},{$p2->id}")
            ->assertOk()
            ->viewData('page')['props'];

        $idsProcessos = collect($props['processos'])->pluck('id')->all();
        $this->assertContains($p1->id, $idsProcessos);
        $this->assertContains($p2->id, $idsProcessos);
        $this->assertSame(2, $props['totalSelecionados']);

        $linha = collect($props['analistas'])->firstWhere('analista_id', $analista->id);
        $this->assertSame(1, $linha['total'], 'A carga atual do analista aparece na Central.');
    }

    public function test_central_exige_distribuir_processos(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $p = $this->processoNaCaixa($setor);

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/caixa-setor/central?ids={$p->id}")
            ->assertForbidden();
    }

    public function test_central_bloqueia_selecao_de_multiplos_setores(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $gestor = $this->gestorDoSetor($setorA);
        $gestor->sectors()->attach($setorB);
        $processoDoSetorA = $this->processoNaCaixa($setorA);
        $processoDoSetorB = $this->processoNaCaixa($setorB);

        $this->actingAs($gestor, 'gestao')
            ->get("/gestao/caixa-setor/central?ids={$processoDoSetorA->id},{$processoDoSetorB->id}")
            ->assertRedirect('/gestao/caixa-setor')
            ->assertSessionHas('error');
    }

    public function test_central_ignora_ids_fora_dos_setores_do_usuario(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $gestor = $this->gestorDoSetor($setorA);

        $doA = $this->processoNaCaixa($setorA);
        $doB = $this->processoNaCaixa($setorB);

        $props = $this->actingAs($gestor, 'gestao')
            ->get("/gestao/caixa-setor/central?ids={$doA->id},{$doB->id}")
            ->assertOk()
            ->viewData('page')['props'];

        $ids = collect($props['processos'])->pluck('id')->all();
        $this->assertContains($doA->id, $ids);
        $this->assertNotContains($doB->id, $ids, 'Processo de setor alheio não entra na Central.');
    }

    public function test_distribuir_lote_sucesso_redireciona_para_a_caixa(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $a = $this->analistaDoSetor($setor);
        $b = $this->analistaDoSetor($setor);

        $p1 = $this->processoNaCaixa($setor);
        $p2 = $this->processoNaCaixa($setor);
        $p3 = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $a->id, 'request_ids' => [$p1->id, $p2->id]],
                    ['analista_id' => $b->id, 'request_ids' => [$p3->id]],
                ],
            ])
            ->assertRedirect('/gestao/caixa-setor')
            ->assertSessionHas('status');

        $this->assertSame($a->id, $p1->fresh()->assigned_user_id);
        $this->assertSame($a->id, $p2->fresh()->assigned_user_id);
        $this->assertSame($b->id, $p3->fresh()->assigned_user_id);

        // Auditoria de resumo para o histórico (um por analista).
        $this->assertDatabaseHas('activity_log', ['log_name' => 'analise', 'event' => 'distribuir-lote']);
    }

    public function test_distribuir_lote_volta_para_a_caixa_mantendo_os_filtros(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'retorno' => 'bairro=Pituba&visao=para_distribuir&per_page=25&campo_alheio=x',
                'alocacoes' => [
                    ['analista_id' => $analista->id, 'request_ids' => [$processo->id]],
                ],
            ])
            ->assertRedirect('/gestao/caixa-setor?'.http_build_query(['bairro' => 'Pituba', 'visao' => 'para_distribuir', 'per_page' => '25']))
            ->assertSessionHas('status');
    }

    public function test_central_devolve_o_retorno_somente_com_chaves_conhecidas(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $processo = $this->processoNaCaixa($setor);

        $props = $this->actingAs($apoio, 'gestao')
            ->get("/gestao/caixa-setor/central?ids={$processo->id}&retorno=".urlencode('cnpj=123&evil=1'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('cnpj=123', $props['retorno']);
    }

    public function test_distribuir_lote_rejeita_destinatario_sem_permissao_de_analise(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $analista = $this->analistaDoSetor($setor);
        $processoDoAnalista = $this->processoNaCaixa($setor);
        $processoDoApoio = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $analista->id, 'request_ids' => [$processoDoAnalista->id]],
                    ['analista_id' => $apoio->id, 'request_ids' => [$processoDoApoio->id]],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertNull($processoDoAnalista->fresh()->assigned_user_id);
        $this->assertNull($processoDoApoio->fresh()->assigned_user_id);
    }

    public function test_distribuir_lote_e_atomico_quando_um_grupo_falha(): void
    {
        $setorDosProcessos = Sector::factory()->create();
        $outroSetor = Sector::factory()->create();
        $gestor = $this->gestorDoSetor($setorDosProcessos);
        $analistaValido = $this->analistaDoSetor($setorDosProcessos);
        $analistaDeOutroSetor = $this->analistaDoSetor($outroSetor);
        $processoDoGrupoValido = $this->processoNaCaixa($setorDosProcessos);
        $processoDoGrupoInvalido = $this->processoNaCaixa($setorDosProcessos);

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $analistaValido->id, 'request_ids' => [$processoDoGrupoValido->id]],
                    ['analista_id' => $analistaDeOutroSetor->id, 'request_ids' => [$processoDoGrupoInvalido->id]],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertNull($processoDoGrupoValido->fresh()->assigned_user_id);
        $this->assertNull($processoDoGrupoInvalido->fresh()->assigned_user_id);
    }

    public function test_distribuir_lote_rejeita_processo_de_setor_alheio_atomicamente(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $gestor = $this->gestorDoSetor($setorA);
        $a = $this->analistaDoSetor($setorA);

        $doA = $this->processoNaCaixa($setorA);
        $doB = $this->processoNaCaixa($setorB); // fora da caixa do ator

        $this->actingAs($gestor, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $a->id, 'request_ids' => [$doA->id, $doB->id]],
                ],
            ])
            ->assertSessionHas('error');

        $this->assertNull($doA->fresh()->assigned_user_id, 'Lote inválido não grava nada (atômico).');
        $this->assertNull($doB->fresh()->assigned_user_id);
    }

    public function test_distribuir_lote_rejeita_ids_sobrepostos(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $a = $this->analistaDoSetor($setor);
        $b = $this->analistaDoSetor($setor);
        $p = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $a->id, 'request_ids' => [$p->id]],
                    ['analista_id' => $b->id, 'request_ids' => [$p->id]], // mesmo processo em dois grupos
                ],
            ])
            ->assertSessionHas('error');

        $this->assertNull($p->fresh()->assigned_user_id);
    }

    public function test_distribuir_lote_exige_distribuir_processos(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);
        $p = $this->processoNaCaixa($setor);

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [['analista_id' => $analista->id, 'request_ids' => [$p->id]]],
            ])
            ->assertForbidden();

        $this->assertNull($p->fresh()->assigned_user_id);
    }

    public function test_central_expoe_historico_de_distribuicoes(): void
    {
        $setor = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setor);
        $a = $this->analistaDoSetor($setor);
        $p1 = $this->processoNaCaixa($setor);
        $p2 = $this->processoNaCaixa($setor);
        $alvo = $this->processoNaCaixa($setor);

        // Uma distribuição em lote para gerar histórico.
        $this->actingAs($apoio, 'gestao')->post('/gestao/caixa-setor/distribuir-lote', [
            'alocacoes' => [['analista_id' => $a->id, 'request_ids' => [$p1->id, $p2->id]]],
        ])->assertSessionHas('status');

        $props = $this->actingAs($apoio, 'gestao')
            ->get("/gestao/caixa-setor/central?ids={$alvo->id}")
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotEmpty($props['historico']);
        $this->assertSame($a->name, $props['historico'][0]['analista']);
        $this->assertSame(2, $props['historico'][0]['qtd']);
        $this->assertSame($apoio->name, $props['historico'][0]['ator'], 'O histórico deve expor o Apoio que distribuiu (causer).');
        $this->assertNotNull($props['historico'][0]['em']);
        $this->assertSame(['em', 'ator', 'analista', 'qtd'], array_keys($props['historico'][0]));
    }

    public function test_historico_nao_mistura_setores(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $apoio = $this->apoioDoSetor($setorA);
        $apoio->sectors()->attach($setorB);

        $analistaB = $this->analistaDoSetor($setorB);

        $processoA = $this->processoNaCaixa($setorA);
        $processoB1 = $this->processoNaCaixa($setorB);
        $processoB2 = $this->processoNaCaixa($setorB);

        $this->actingAs($apoio, 'gestao')->post('/gestao/caixa-setor/distribuir-lote', [
            'alocacoes' => [['analista_id' => $analistaB->id, 'request_ids' => [$processoB1->id, $processoB2->id]]],
        ])->assertSessionHas('status');

        $props = $this->actingAs($apoio, 'gestao')
            ->get("/gestao/caixa-setor/central?ids={$processoA->id}")
            ->assertOk()
            ->viewData('page')['props'];

        $analistasNoHistorico = collect($props['historico'])->pluck('analista');
        $this->assertNotContains($analistaB->name, $analistasNoHistorico, 'O histórico do setor A não deve incluir distribuições do setor B.');
    }
}
