<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\EscritorioVirtual\SedeAtividadesResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Achado 2 da revisão final: `VirtualOfficeActivityCnae::permitidoNoAnexo()`
 * devolve `false` tanto para "CNAE fora do anexo" quanto para "não existe
 * versão vigente das regras" — sem distinguir os dois casos, uma janela sem
 * versão vigente do Anexo A (o `EscritorioVirtualCnaeSeeder` ainda não
 * rodou, ou está publicando uma versão nova que fecha a anterior) reprovaria
 * TODOS os CNAEs da sede, indeferindo em massa sobre dado ausente. O
 * `SedeAtividadesResolver::listaDisponivel()` distingue os casos: lista
 * indisponível não bloqueia o passo — o processo segue para a análise
 * decidir, honesto, sem indeferimento automático (mesmo princípio
 * anti-fachada que o FluxoExpressoService honra em toda parte).
 */
class SedeAtividadesResolverListaIndisponivelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    public function test_lista_disponivel_e_falso_sem_versao_vigente_do_anexo_a(): void
    {
        // EscritorioVirtualCnaeSeeder NÃO rodou: nenhuma RuleVersion vigente
        // do domínio AtividadesEscritorioVirtual.
        $this->assertFalse(app(SedeAtividadesResolver::class)->listaDisponivel());
    }

    public function test_sede_nao_e_bloqueada_quando_a_lista_do_anexo_a_esta_indisponivel(): void
    {
        $user = $this->portalUser();
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'wants_virtual_office_hq' => true,
        ]);
        // Sem EscritorioVirtualCnaeSeeder: nenhuma versão vigente do Anexo A.
        // Um CNAE qualquer, fora do gatilho — antes da correção, TODOS os
        // CNAEs seriam reprovados por "lista vazia", indeferindo a sede.
        $consultoria = Cnae::factory()->create(['code' => '6920-6/01']);

        $this->actingAs($user)
            ->from(route('portal.solicitacoes.index'))
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $consultoria->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $solicitacao->cnaes()->count());
    }
}
