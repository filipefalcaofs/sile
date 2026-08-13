<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Consulta PÚBLICA do protocolo por link assinado (HU-069) — SEM login. A rota
 * usa middleware signed (TTL parametrizável) + throttle:consulta-protocolo. Abre
 * com o link válido, bloqueia link inválido/expirado (403), NUNCA expõe dados
 * sensíveis (CPF/CNPJ completo/endereço detalhado) nem anexos (LGPD) e é auditada
 * com causer null + IP (RN-002).
 */
class ConsultarProtocoloPublicoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Solicitação PROTOCOLADA com uma transição real (fonte da timeline). Não
     * depende de papéis/seeder: o foco é a consulta pública, não o protocolo.
     */
    private function protocolada(array $overrides = []): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->protocoled()->withPrimaryCnae()->create($overrides);

        $solicitacao->transitions()->create([
            'from_status' => ViabilityRequestStatus::Rascunho,
            'to_status' => ViabilityRequestStatus::Protocolada,
            'public_label' => ViabilityRequestStatus::Protocolada->publicLabel(),
            'actor_user_id' => null,
        ]);

        return $solicitacao;
    }

    private function signedUrl(ViabilityRequest $solicitacao, ?Carbon $expiration = null): string
    {
        return URL::temporarySignedRoute(
            'portal.protocolo.publico',
            $expiration ?? now()->addDays(30),
            ['solicitacao' => $solicitacao->id],
        );
    }

    public function test_link_assinado_abre_sem_login(): void
    {
        // CA-01: o link assinado abre a página pública SEM autenticação, com o
        // status amigável e a timeline pública.
        $solicitacao = $this->protocolada();

        $this->get($this->signedUrl($solicitacao))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/solicitacoes/protocolo-publico')
                ->where('solicitacao.protocol_number', $solicitacao->protocol_number)
                ->where('solicitacao.status.public_label', ViabilityRequestStatus::Protocolada->publicLabel())
                ->has('timeline.etapas', 1)
                ->where('timeline.status_atual.public_label', ViabilityRequestStatus::Protocolada->publicLabel())
            );
    }

    public function test_link_invalido_ou_expirado_bloqueia(): void
    {
        // CA-04/segurança: sem assinatura válida (ou expirada) → 403, nunca 200.
        $solicitacao = $this->protocolada();

        // Sem assinatura.
        $this->get(route('portal.protocolo.publico', $solicitacao))->assertForbidden();

        // Assinatura expirada (validade no passado).
        $this->get($this->signedUrl($solicitacao, now()->subDay()))->assertForbidden();
    }

    public function test_nao_expoe_dados_sensiveis(): void
    {
        // LGPD: a página pública mostra SÓ situação/timeline/prazo — nunca CPF,
        // CNPJ completo, endereço detalhado nem anexos.
        $user = User::factory()->create();
        $company = Company::factory()->create(['legal_name' => 'Empresa Confidencial LTDA']);
        $solicitacao = $this->protocolada([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'company_id' => $company->id,
            'address_street' => 'Rua Secreta dos Testes',
            'address_number' => '777',
        ]);

        $response = $this->get($this->signedUrl($solicitacao))->assertOk();

        // Nada de dado sensível no corpo da resposta.
        $response->assertDontSee($user->cpf);
        $response->assertDontSee($company->cnpj);
        $response->assertDontSee($company->formatted_cnpj);
        $response->assertDontSee('Rua Secreta dos Testes');
        $response->assertDontSee('Empresa Confidencial LTDA');

        // E o payload Inertia não carrega empresa/endereço/CNAEs/anexos.
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('portal/solicitacoes/protocolo-publico')
            ->missing('solicitacao.company')
            ->missing('solicitacao.address')
            ->missing('solicitacao.cnaes')
            ->missing('solicitacao.documents')
        );
    }

    public function test_throttle_aplicado(): void
    {
        // HU-014: o limite por minuto é administrável sem deploy. Com 2/min, a 3ª
        // consulta pública dentro da janela é bloqueada (429).
        $this->seed(ParameterSeeder::class);
        Parameter::query()
            ->where('key', 'seguranca.throttle.consulta_protocolo.por_minuto')
            ->first()
            ->update(['value' => '2']);

        $solicitacao = $this->protocolada();
        $url = $this->signedUrl($solicitacao);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();
        $this->get($url)->assertStatus(429);
    }

    public function test_auditoria_publica_com_causer_null(): void
    {
        // RN-002: a consulta pública é auditada (solicitacoes/consulta-protocolo-
        // publica) com causer null (anônima) + IP registrado pela RecordActivityAction.
        $solicitacao = $this->protocolada();

        $this->get($this->signedUrl($solicitacao))->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'consulta-protocolo-publica',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
            'result' => 'sucesso',
        ]);

        $activity = Activity::query()
            ->where('log_name', 'solicitacoes')
            ->where('event', 'consulta-protocolo-publica')
            ->latest('id')
            ->first();

        $this->assertNull($activity->causer_id);
        $this->assertNotNull($activity->ip_address);
    }
}
