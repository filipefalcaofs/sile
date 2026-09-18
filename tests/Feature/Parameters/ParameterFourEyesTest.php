<?php

namespace Tests\Feature\Parameters;

use App\Enums\ParameterGovernance;
use App\Enums\ParameterProposalStatus;
use App\Models\Activity;
use App\Models\Parameter;
use App\Models\ParameterProposal;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParameterFourEyesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_seeder_classifica_chaves_decisorias(): void
    {
        $decisionKeys = ParameterSeeder::decisionKeys();

        foreach ($decisionKeys as $key) {
            $parameter = Parameter::query()->where('key', $key)->first();
            $this->assertNotNull($parameter);
            $this->assertSame(ParameterGovernance::Decision, $parameter->governance);
        }

        $operational = Parameter::query()->where('key', 'security.login.max_attempts')->first();
        $this->assertNotNull($operational);
        $this->assertSame(ParameterGovernance::Operational, $operational->governance);
        $this->assertFalse($operational->isDecision());
    }

    public function test_put_em_decisorio_abre_proposta_sem_aplicar(): void
    {
        $author = $this->admin();
        $parameter = Parameter::query()->where('key', 'features.fluxo_expresso')->firstOrFail();

        $this->actingAs($author, 'gestao')
            ->put("/gestao/parametros/{$parameter->key}", ['value' => '0'])
            ->assertRedirect();

        $parameter->refresh();
        $this->assertNull($parameter->value);
        $this->assertTrue($parameter->typedValue());

        $proposal = $parameter->pendingProposal;
        $this->assertNotNull($proposal);
        $this->assertSame('0', $proposal->proposed_value);
        $this->assertSame($author->id, $proposal->created_by);
        $this->assertSame(ParameterProposalStatus::Pending, $proposal->status);
    }

    public function test_autor_nao_pode_aprovar(): void
    {
        $author = $this->admin();
        $parameter = Parameter::query()->where('key', 'features.fluxo_expresso')->firstOrFail();

        $this->actingAs($author, 'gestao')
            ->put("/gestao/parametros/{$parameter->key}", ['value' => '0']);

        $this->actingAs($author, 'gestao')
            ->post("/gestao/parametros/{$parameter->key}/aprovar")
            ->assertRedirect()
            ->assertSessionHas('error', 'A publicação por quatro olhos exige um publicador diferente do autor do rascunho.');

        $parameter->refresh();
        $this->assertNull($parameter->value);
        $this->assertSame(ParameterProposalStatus::Pending, $parameter->pendingProposal?->status);
    }

    public function test_segundo_aprovador_aplica_e_audita(): void
    {
        $author = $this->admin();
        $reviewer = $this->admin();
        $parameter = Parameter::query()->where('key', 'features.fluxo_expresso')->firstOrFail();

        $this->actingAs($author, 'gestao')
            ->put("/gestao/parametros/{$parameter->key}", ['value' => '0']);

        $this->actingAs($reviewer, 'gestao')
            ->post("/gestao/parametros/{$parameter->key}/aprovar")
            ->assertRedirect()
            ->assertSessionHas('status');

        $parameter->refresh();
        $this->assertSame('0', $parameter->value);
        $this->assertFalse($parameter->typedValue());
        $this->assertNull($parameter->pendingProposal);

        $this->assertTrue(
            Activity::query()
                ->where('log_name', 'parametros')
                ->where('event', 'parametro-aprovado')
                ->where('subject_id', $parameter->id)
                ->where('properties->autor_id', $author->id)
                ->where('properties->aprovador_id', $reviewer->id)
                ->exists(),
        );
    }

    public function test_rejeitar_preserva_valor(): void
    {
        $author = $this->admin();
        $reviewer = $this->admin();
        $parameter = Parameter::query()->where('key', 'features.fluxo_expresso')->firstOrFail();

        $this->actingAs($author, 'gestao')
            ->put("/gestao/parametros/{$parameter->key}", ['value' => '0']);

        $this->actingAs($reviewer, 'gestao')
            ->post("/gestao/parametros/{$parameter->key}/rejeitar")
            ->assertRedirect();

        $parameter->refresh();
        $this->assertNull($parameter->value);
        $this->assertTrue($parameter->typedValue());
        $this->assertNull($parameter->pendingProposal);
        $this->assertTrue(
            ParameterProposal::query()
                ->where('parameter_id', $parameter->id)
                ->where('status', ParameterProposalStatus::Rejected)
                ->exists(),
        );
    }

    public function test_put_operacional_ainda_aplica_na_hora(): void
    {
        $admin = $this->admin();
        $parameter = Parameter::query()->where('key', 'security.login.max_attempts')->firstOrFail();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/parametros/{$parameter->key}", ['value' => '7'])
            ->assertRedirect();

        $parameter->refresh();
        $this->assertSame('7', $parameter->value);
        $this->assertSame(0, $parameter->proposals()->count());
    }

    public function test_index_traz_governanca_proposta_e_can_approve(): void
    {
        $author = $this->admin();
        $reviewer = $this->admin();
        $parameter = Parameter::query()->where('key', 'features.fluxo_expresso')->firstOrFail();
        $index = Parameter::query()
            ->where('group', 'features')
            ->orderBy('key')
            ->pluck('key')
            ->search('features.fluxo_expresso');

        $this->actingAs($author, 'gestao')
            ->put("/gestao/parametros/{$parameter->key}", ['value' => '0']);

        $this->actingAs($author, 'gestao')
            ->get('/gestao/parametros')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where("groups.features.{$index}.governance", 'decision')
                ->where("groups.features.{$index}.pending_proposal.proposed_value", '0')
                ->where("groups.features.{$index}.can_approve", false));

        $this->actingAs($reviewer, 'gestao')
            ->get('/gestao/parametros')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where("groups.features.{$index}.can_approve", true));
    }
}
