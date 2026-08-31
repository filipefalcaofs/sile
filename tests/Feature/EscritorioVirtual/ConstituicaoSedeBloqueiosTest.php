<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Indeferimentos automáticos da constituição de SEDE de escritório virtual,
 * como bloqueio no passo de atividades do portal (Decreto 35.062/2021):
 *
 * - RN-C-02: intenção de ABRIGADO com o CNAE gatilho (8211-3/00) — o CNAE
 *   que caracteriza a sede não consta do Anexo B (abrigado), de propósito,
 *   para impedir que uma sede se estabeleça dentro de outro escritório
 *   virtual.
 * - RN-C-01: intenção de SEDE numa inscrição que já tem sede ativa.
 * - RN-C-03: intenção de SEDE com atividade fora de {CNAE gatilho} ∪ Anexo A.
 */
class ConstituicaoSedeBloqueiosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        // Lista EV vigente (8211-3/00, 6204-0/00, etc. — snapshot SEDUR).
        $this->seed(EscritorioVirtualCnaeSeeder::class);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Rascunho de solicitação cujo requerente (beneficiário) é o usuário, com a
     * inscrição imobiliária informada (passo do imóvel, anterior às atividades).
     */
    private function draftFor(User $user, ?string $propertyRegistration, bool $wantsTenant = false, bool $wantsHq = false): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'property_registration' => $propertyRegistration,
            'wants_virtual_office_tenant' => $wantsTenant,
            'wants_virtual_office_hq' => $wantsHq,
        ]);
    }

    /**
     * Trava a inscrição por uma sede de escritório virtual ATIVA (RN-EV-03).
     */
    private function sedeAtivaEm(string $propertyRegistration): void
    {
        VirtualOfficeInscriptionLock::create([
            'property_registration' => $propertyRegistration,
            'sede_viability_request_id' => ViabilityRequest::factory()->protocoled()->create()->id,
            'active' => true,
            'locked_at' => Carbon::now(),
        ]);
    }

    public function test_cnae_de_sede_com_intencao_de_abrigado_e_bloqueado(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, null, wantsTenant: true);
        // 8211-3/00 caracteriza a sede: não consta do Anexo B (abrigado).
        $gatilho = Cnae::factory()->create(['code' => '8211-3/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
            ])
            ->assertSessionHasErrors('principal_cnae_id');
    }

    /**
     * O closure existente (validação geral do Anexo B) só roda quando a
     * inscrição tem sede ativa; o closure novo (RN-C-02) só roda quando NÃO
     * tem. Como o CNAE gatilho não consta do Anexo B, um abrigado numa
     * inscrição COM sede ativa cai nos dois closures se a divisão falhar —
     * este teste prova que cai em só um, contando as mensagens em vez de só
     * checar presença (senão a duplicação passaria despercebida).
     */
    public function test_cnae_de_sede_com_intencao_de_abrigado_em_inscricao_com_sede_ativa_gera_um_unico_erro(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X', wantsTenant: true);
        $this->sedeAtivaEm('X');
        $gatilho = Cnae::factory()->create(['code' => '8211-3/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
            ])
            ->assertSessionHasErrors('principal_cnae_id');

        $errors = $this->app['session']->get('errors')->getBag('default')->get('principal_cnae_id');
        $this->assertCount(1, $errors);
    }

    public function test_sede_em_inscricao_que_ja_tem_sede_e_bloqueada(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X', wantsHq: true);
        $this->sedeAtivaEm('X');
        $consultoria = Cnae::factory()->create(['code' => '6920-6/01']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $consultoria->id,
            ])
            ->assertSessionHasErrors('principal_cnae_id');
    }

    /**
     * O closure pré-existente (validação de Anexo B) assumia "inscrição com
     * sede ativa ⇒ é abrigado" — premissa que não vale mais para quem está
     * CONSTITUINDO sede (intenção Sede). Sem a exclusão dessa intenção no
     * closure existente, um CNAE fora do Anexo B (mas também fora do Anexo A)
     * disparava DOIS pareceres de causas diferentes: o do Anexo B (closure
     * antigo) e o de sede duplicada (RN-C-01, closure novo). Este teste prova
     * que sobra só o de sede duplicada — contando os erros, não só checando
     * presença, para não deixar a duplicação passar despercebida.
     */
    public function test_sede_em_inscricao_que_ja_tem_sede_com_cnae_fora_dos_dois_anexos_gera_um_unico_erro(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, 'X', wantsHq: true);
        $this->sedeAtivaEm('X');
        // 4712-1/00 (minimercado) não consta de nenhum dos dois anexos.
        $foraDosDoisAnexos = Cnae::factory()->create(['code' => '4712-1/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $foraDosDoisAnexos->id,
            ])
            ->assertSessionHasErrors('principal_cnae_id');

        $errors = $this->app['session']->get('errors')->getBag('default')->get('principal_cnae_id');
        $this->assertCount(1, $errors);
        $this->assertSame(
            config('sile.analise.escritorio_virtual.mensagem_sede_duplicada'),
            $errors[0],
        );
    }

    /**
     * O requisito manda identificar TODOS os CNAEs reprovados, não só o
     * primeiro — o foreach da RN-C-03 já faz isso, mas sem este teste um
     * `break` (padrão que o closure vizinho do Anexo B usa) quebraria a
     * regra sem quebrar teste nenhum.
     */
    public function test_sede_com_dois_cnaes_fora_do_anexo_a_nomeia_ambos(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, null, wantsHq: true);
        $gatilho = Cnae::factory()->create(['code' => '8211-3/00']);
        // Nenhum dos dois consta do Anexo A (um só está no Anexo B, o outro em nenhum).
        $foraA1 = Cnae::factory()->create(['code' => '8630-5/99']);
        $foraA2 = Cnae::factory()->create(['code' => '4712-1/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
                'complementares' => [$foraA1->id, $foraA2->id],
            ])
            ->assertSessionHasErrors('principal_cnae_id');

        $errors = $this->app['session']->get('errors')->getBag('default')->get('principal_cnae_id');
        $this->assertCount(2, $errors);
        $this->assertNotEmpty(array_filter($errors, fn ($msg) => str_contains($msg, '8630-5/99')));
        $this->assertNotEmpty(array_filter($errors, fn ($msg) => str_contains($msg, '4712-1/00')));
    }

    public function test_sede_com_atividade_fora_do_anexo_a_e_bloqueada(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, null, wantsHq: true);
        $gatilho = Cnae::factory()->create(['code' => '8211-3/00']);
        // 8630-5/99 só está no Anexo B (abrigado), não no Anexo A (sede).
        $foraDoAnexoA = Cnae::factory()->create(['code' => '8630-5/99']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
                'complementares' => [$foraDoAnexoA->id],
            ])
            ->assertSessionHasErrors('principal_cnae_id');

        $session = $this->app['session'];
        $errors = $session->get('errors')->getBag('default')->get('principal_cnae_id');
        $this->assertNotEmpty(array_filter($errors, fn ($msg) => str_contains($msg, '8630-5/99')));
    }

    public function test_sede_com_atividades_do_anexo_a_passa(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, null, wantsHq: true);
        $gatilho = Cnae::factory()->create(['code' => '8211-3/00']);
        // 6920-6/01 (atividades de contabilidade) consta do Anexo A.
        $doAnexoA = Cnae::factory()->create(['code' => '6920-6/01']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
                'complementares' => [$doAnexoA->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $solicitacao->cnaes()->count());
    }

    public function test_sede_com_apenas_o_cnae_gatilho_passa(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftFor($user, null, wantsHq: true);
        $gatilho = Cnae::factory()->create(['code' => '8211-3/00']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solicitacao->cnaes()->count());
    }

    public function test_solicitacao_sem_intencao_de_escritorio_virtual_nao_sofre_bloqueio(): void
    {
        $user = $this->portalUser();
        // Intenção Nenhum: nem abrigado, nem sede. Inscrição livre (sem trava).
        $solicitacao = $this->draftFor($user, 'Y');
        $qualquerCnae = Cnae::factory()->create(['code' => '8630-5/99']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $qualquerCnae->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solicitacao->cnaes()->count());
    }
}
