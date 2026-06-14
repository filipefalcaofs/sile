<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\CompanyLinkRole;
use App\Http\Middleware\ResolveAssistedAttendance;
use App\Models\AssistedAttendance;
use App\Models\Company;
use App\Models\Procuration;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Support\Representation\CurrentRepresentation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Atendimento presencial assistido (HU-150): o atendente autorizado opera o
 * sistema "em nome de" o cidadão presente no balcão (inclusão digital),
 * REUSANDO o mecanismo de representação da Fase 1 — modelo leve
 * AssistedAttendance + middleware análogo populando o MESMO
 * CurrentRepresentation/Context. Toda ação registra ator (atendente) e
 * beneficiário (cidadão); o vínculo expira e exige reabertura; o escopo é
 * limitado por permissão (RN-001/RN-002, CA-01..CA-04).
 */
class AtendimentoPresencialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_escopo_active_ignora_expirado_e_encerrado(): void
    {
        $active = AssistedAttendance::factory()->active()->create();
        $expired = AssistedAttendance::factory()->expired()->create();
        $ended = AssistedAttendance::factory()->ended()->create();

        $activeIds = AssistedAttendance::active()->pluck('id');

        $this->assertTrue($activeIds->contains($active->id));
        $this->assertFalse($activeIds->contains($expired->id));
        $this->assertFalse($activeIds->contains($ended->id));

        $this->assertTrue($active->isActive());
        $this->assertFalse($expired->isActive());
        $this->assertFalse($ended->isActive());
    }

    public function test_representacao_resolve_o_cidadao_atendido(): void
    {
        $citizen = User::factory()->create();
        $attendance = AssistedAttendance::factory()->active()->create([
            'citizen_user_id' => $citizen->id,
        ]);

        $representation = app(CurrentRepresentation::class);
        $representation->setAttendance($attendance);

        $this->assertSame($citizen->id, $representation->grantor()?->id);
        $this->assertSame($attendance->id, $representation->attendance()?->id);

        $representation->clearAttendance();
        $this->assertNull($representation->grantor());
        $this->assertNull($representation->attendance());
    }

    public function test_procuracao_continua_resolvendo_o_outorgante(): void
    {
        $grantor = User::factory()->create();
        $procuration = Procuration::factory()->create(['grantor_user_id' => $grantor->id]);

        $representation = app(CurrentRepresentation::class);
        $representation->set($procuration);

        // O caminho de procuração da Fase 1 NÃO pode regredir ao reusar o
        // CurrentRepresentation para o atendimento presencial.
        $this->assertSame($grantor->id, $representation->grantor()?->id);
    }

    /**
     * Invoca o middleware isoladamente (padrão do projeto: o comportamento da
     * representação é provado pelos fluxos, não há teste de middleware com rota
     * dedicada). Aqui basta uma request com sessão e usuário resolvidos.
     */
    private function runMiddleware(User $attendant, ?int $attendanceIdInSession): Request
    {
        $request = Request::create('/gestao/atendimento', 'GET');
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => $attendant);

        if ($attendanceIdInSession !== null) {
            $request->session()->put('attending_attendance_id', $attendanceIdInSession);
        }

        (new ResolveAssistedAttendance)->handle($request, fn (Request $req): Response => new Response('ok'));

        return $request;
    }

    public function test_middleware_resolve_atendimento_ativo_para_representacao(): void
    {
        $attendant = User::factory()->gestor()->create();
        $citizen = User::factory()->cidadao()->create();
        $attendance = AssistedAttendance::factory()->active()->create([
            'attendant_user_id' => $attendant->id,
            'citizen_user_id' => $citizen->id,
        ]);

        $this->runMiddleware($attendant, $attendance->id);

        $this->assertSame($citizen->id, app(CurrentRepresentation::class)->grantor()?->id);
        $this->assertSame($citizen->id, Context::get('acting_for_user_id'));
    }

    public function test_middleware_limpa_estado_quando_expirado(): void
    {
        $attendant = User::factory()->gestor()->create();
        $attendance = AssistedAttendance::factory()->expired()->create([
            'attendant_user_id' => $attendant->id,
        ]);

        $request = $this->runMiddleware($attendant, $attendance->id);

        $this->assertNull(app(CurrentRepresentation::class)->grantor());
        $this->assertNull(Context::get('acting_for_user_id'));
        // Vínculo expirado some da sessão: a próxima ação exige reabertura (CA-03).
        $this->assertFalse($request->session()->has('attending_attendance_id'));
    }

    public function test_middleware_ignora_atendimento_de_outro_atendente(): void
    {
        $attendant = User::factory()->gestor()->create();
        $outro = User::factory()->gestor()->create();
        $attendance = AssistedAttendance::factory()->active()->create([
            'attendant_user_id' => $outro->id,
        ]);

        $this->runMiddleware($attendant, $attendance->id);

        // Atendimento de outro atendente nunca vira representação do atual.
        $this->assertNull(app(CurrentRepresentation::class)->grantor());
        $this->assertNull(Context::get('acting_for_user_id'));
    }

    /**
     * Atendente autorizado: gestor (papel com a permissão atendimento-presencial
     * + acessar-gestao + termo LGPD aceito para navegar no console).
     */
    private function attendant(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cidadão atendido + empresa com vínculo ATIVO dele (Fase 3) — pré-condição
     * para abrir a solicitação em nome do cidadão.
     *
     * @return array{0: User, 1: Company}
     */
    private function citizenWithCompany(): array
    {
        $citizen = User::factory()->cidadao()->create();
        $company = Company::factory()->create();
        $company->links()->create([
            'user_id' => $citizen->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        return [$citizen, $company];
    }

    public function test_pagina_do_atendimento_renderiza(): void
    {
        $attendant = $this->attendant();

        $this->actingAs($attendant, 'gestao')
            ->get('/gestao/atendimento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('gestao/atendimento/index'));
    }

    public function test_inicia_atendimento_informando_cpf(): void
    {
        $attendant = $this->attendant();
        $citizen = User::factory()->cidadao()->create();

        $this->actingAs($attendant, 'gestao')
            ->post('/gestao/atendimento', ['cpf' => $citizen->cpf])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('assisted_attendances', [
            'attendant_user_id' => $attendant->id,
            'citizen_user_id' => $citizen->id,
            'ended_at' => null,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'atendimento',
            'event' => 'atendimento-iniciado',
            'causer_id' => $attendant->id,
        ]);
    }

    public function test_cpf_sem_conta_de_cidadao_bloqueia_sem_criar(): void
    {
        $attendant = $this->attendant();

        // CPF válido (529.982.247-25), porém sem conta: não fabrica cidadão.
        $this->actingAs($attendant, 'gestao')
            ->from('/gestao/atendimento')
            ->post('/gestao/atendimento', ['cpf' => '52998224725'])
            ->assertSessionHasErrors('cpf');

        $this->assertDatabaseCount('assisted_attendances', 0);
    }

    public function test_acao_em_nome_do_cidadao_registra_os_dois_cpfs(): void
    {
        $attendant = $this->attendant();
        [$citizen, $company] = $this->citizenWithCompany();
        $serviceType = ViabilityServiceType::factory()->create();
        $attendance = AssistedAttendance::factory()->active()->create([
            'attendant_user_id' => $attendant->id,
            'citizen_user_id' => $citizen->id,
        ]);

        $this->actingAs($attendant, 'gestao')
            ->withSession(['attending_attendance_id' => $attendance->id])
            ->post('/gestao/atendimento/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($solicitacao);
        // Beneficiário = cidadão; ator real = atendente (CA-01/RN-001).
        $this->assertSame($citizen->id, $solicitacao->requester_user_id);
        $this->assertSame($attendant->id, $solicitacao->created_by_user_id);
        $this->assertSame('portal', $solicitacao->origin->value);

        // Auditoria com os DOIS CPFs: causer = atendente, acting_for = cidadão.
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $solicitacao->getMorphClass(),
            'event' => 'created',
            'causer_id' => $attendant->id,
            'acting_for_user_id' => $citizen->id,
        ]);
    }

    public function test_origem_balcao_para_relatorios(): void
    {
        $attendant = $this->attendant();
        [$citizen, $company] = $this->citizenWithCompany();
        $serviceType = ViabilityServiceType::factory()->create();
        $attendance = AssistedAttendance::factory()->active()->create([
            'attendant_user_id' => $attendant->id,
            'citizen_user_id' => $citizen->id,
        ]);

        $this->actingAs($attendant, 'gestao')
            ->withSession(['attending_attendance_id' => $attendance->id])
            ->post('/gestao/atendimento/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ]);

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->first();
        $this->assertNotNull($solicitacao);
        // Dimensão balcão (RN-005): a solicitação carrega o vínculo de atendimento.
        $this->assertSame($attendance->id, $solicitacao->assisted_attendance_id);
    }

    public function test_expiracao_exige_reabertura(): void
    {
        $attendant = $this->attendant();
        [$citizen, $company] = $this->citizenWithCompany();
        $serviceType = ViabilityServiceType::factory()->create();
        $expired = AssistedAttendance::factory()->expired()->create([
            'attendant_user_id' => $attendant->id,
            'citizen_user_id' => $citizen->id,
        ]);

        // Atendimento expirado: a ação não opera em nome de ninguém.
        $this->actingAs($attendant, 'gestao')
            ->withSession(['attending_attendance_id' => $expired->id])
            ->from('/gestao/atendimento')
            ->post('/gestao/atendimento/solicitacoes', [
                'service_type_id' => $serviceType->id,
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('viability_requests', 0);

        // Reabrir explicitamente recria um vínculo ATIVO (novo start — CA-03).
        $this->actingAs($attendant, 'gestao')
            ->post('/gestao/atendimento', ['cpf' => $citizen->cpf])
            ->assertRedirect();

        $this->assertTrue(
            AssistedAttendance::active()->where('attendant_user_id', $attendant->id)->exists(),
        );
    }

    public function test_escopo_limitado_por_permissao(): void
    {
        // O atendente (gestor) tem atendimento-presencial, mas NÃO
        // manter-parametros: ações fora do escopo concedido (análise/decisão
        // virão nas Fases 9/10) são barradas e auditadas (CA-02/RN-002).
        $attendant = $this->attendant();

        $this->actingAs($attendant, 'gestao')
            ->get('/gestao/parametros')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $attendant->id,
        ]);
    }

    public function test_exige_permissao_atendimento_presencial(): void
    {
        // Analista acessa a gestão, mas NÃO tem atendimento-presencial: a rota
        // do atendimento bloqueia e audita a tentativa (CA-04).
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/atendimento')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_encerra_atendimento(): void
    {
        $attendant = $this->attendant();
        $citizen = User::factory()->cidadao()->create();
        $attendance = AssistedAttendance::factory()->active()->create([
            'attendant_user_id' => $attendant->id,
            'citizen_user_id' => $citizen->id,
        ]);

        $this->actingAs($attendant, 'gestao')
            ->withSession(['attending_attendance_id' => $attendance->id])
            ->delete('/gestao/atendimento')
            ->assertRedirect();

        $this->assertNotNull($attendance->fresh()->ended_at);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'atendimento',
            'event' => 'atendimento-encerrado',
            'causer_id' => $attendant->id,
        ]);
    }
}
