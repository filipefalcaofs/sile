<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\IntencaoAtividade;
use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Passo de atividades do portal (Task 3): marcação de exclusão por CNAE
 * (RN-AA-05b), pergunta vinculada e confirmação de perda da condição de sede
 * (RN-AA-04). Cobre as duas armadilhas já corrigidas antes neste projeto: o
 * `sync()` do controller descartando a `intencao` do pivot, e a ausência da
 * chave de confirmação virando negativa em vez de preservar o valor atual.
 */
class AtividadesPortalEscritorioVirtualTest extends TestCase
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

    /**
     * Código do CNAE gatilho lido da fonte única (nunca fixado como literal
     * no teste).
     */
    private function gatilho(): Cnae
    {
        $codigo = app(SedeEscritorioVirtualGatilho::class)->cnaeGatilho();

        return Cnae::query()->where('code', $codigo)->first() ?? Cnae::factory()->create(['code' => $codigo]);
    }

    private function alteracaoAtividade(): ViabilityServiceType
    {
        return ViabilityServiceType::query()->firstOrCreate(
            ['code' => 'alteracao-atividade'],
            ['name' => 'Alteração de atividade', 'active' => true],
        );
    }

    private function draftAlteracaoAtividade(User $user): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'service_type_id' => $this->alteracaoAtividade()->id,
        ]);
    }

    public function test_payload_entrega_os_textos_e_o_cnae_gatilho(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftAlteracaoAtividade($user);
        $gatilho = $this->gatilho();

        $response = $this->actingAs($user)->get(route('portal.solicitacoes.edit', $solicitacao));

        $response->assertInertia(fn ($page) => $page
            ->where('solicitacao.escritorio_virtual.pergunta_vinculada', Settings::get(
                'analise.escritorio_virtual.pergunta_vinculada',
                config('sile.analise.escritorio_virtual.pergunta_vinculada'),
            ))
            ->where('solicitacao.escritorio_virtual.mensagem_confirma_perda_sede', Settings::get(
                'analise.escritorio_virtual.mensagem_confirma_perda_sede',
                config('sile.analise.escritorio_virtual.mensagem_confirma_perda_sede'),
            ))
            ->where('solicitacao.escritorio_virtual.cnae_gatilho_id', $gatilho->id));
    }

    public function test_marcacao_de_exclusao_e_persistida_na_intencao_do_pivot(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftAlteracaoAtividade($user);
        $principal = Cnae::factory()->create();
        $complementar = Cnae::factory()->create();

        $this->actingAs($user)
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
                'complementares' => [$complementar->id],
                'exclusoes' => [$complementar->id],
            ])
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame(
            IntencaoAtividade::Excluir->value,
            $solicitacao->cnaes()->find($complementar->id)->pivot->intencao,
        );
        $this->assertSame(
            IntencaoAtividade::Incluir->value,
            $solicitacao->cnaes()->find($principal->id)->pivot->intencao,
        );
    }

    public function test_solicitacao_que_nao_e_alteracao_de_atividade_nao_recebe_intencao(): void
    {
        $user = $this->portalUser();
        // Primeiro estabelecimento — não é alteração de atividade.
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);
        $principal = Cnae::factory()->create();
        $complementar = Cnae::factory()->create();

        $this->actingAs($user)
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $principal->id,
                'complementares' => [$complementar->id],
                'exclusoes' => [$complementar->id],
            ])
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertNull($solicitacao->cnaes()->find($principal->id)->pivot->intencao);
        $this->assertNull($solicitacao->cnaes()->find($complementar->id)->pivot->intencao);
    }

    public function test_regravar_as_atividades_preserva_a_marcacao(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftAlteracaoAtividade($user);
        $principal = Cnae::factory()->create();
        $complementar = Cnae::factory()->create();

        $payload = [
            'principal_cnae_id' => $principal->id,
            'complementares' => [$complementar->id],
            'exclusoes' => [$complementar->id],
        ];

        $this->actingAs($user)->put(route('portal.solicitacoes.atividades', $solicitacao), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('portal.solicitacoes.atividades', $solicitacao), $payload)
            ->assertSessionHasNoErrors();

        $solicitacao->refresh();
        $this->assertSame(
            IntencaoAtividade::Excluir->value,
            $solicitacao->cnaes()->find($complementar->id)->pivot->intencao,
        );
    }

    public function test_confirmacao_de_perda_da_condicao_de_sede_e_persistida(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftAlteracaoAtividade($user);
        $gatilho = $this->gatilho();

        $this->actingAs($user)
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
                'exclusoes' => [$gatilho->id],
                'confirma_perda_condicao_sede' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($solicitacao->refresh()->confirma_perda_condicao_sede);
    }

    public function test_confirmacao_ausente_nao_vira_negativa(): void
    {
        $user = $this->portalUser();
        $solicitacao = $this->draftAlteracaoAtividade($user);
        $solicitacao->update(['confirma_perda_condicao_sede' => true]);
        $gatilho = $this->gatilho();

        $this->actingAs($user)
            ->put(route('portal.solicitacoes.atividades', $solicitacao), [
                'principal_cnae_id' => $gatilho->id,
                'exclusoes' => [$gatilho->id],
                // Sem 'confirma_perda_condicao_sede' — ausência preserva.
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($solicitacao->refresh()->confirma_perda_condicao_sede);
    }
}
