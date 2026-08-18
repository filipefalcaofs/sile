<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Activity;
use App\Models\Cnae;
use App\Models\DocumentRequirement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CRUD administrável dos requisitos documentais (modelo "Requisito" do SIGVISA
 * — HU-067) e do vínculo N:N com CNAEs: a obrigatoriedade documental por CNAE é
 * DADO administrável (HU-014), não código. Tudo atrás da permissão
 * manter-requisitos-documentais (CA-04), com auditoria (RN-002) e desativação
 * que preserva histórico. A tabela por-CNAE nasce vazia (carga oficial pendente
 * SEDUR) — sem fachada; o resolver (08-08) consome o cadastro.
 */
class DocumentRequirementCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_exige_permissao_manter_requisitos_documentais(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/requisitos-documentais')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_administrador_lista_requisitos(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        DocumentRequirement::factory()->count(3)->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/requisitos-documentais')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/requisitos-documentais/index')
                ->has('requirements.data', 3)
                ->has('requirements.data.0', fn (Assert $item) => $item
                    ->hasAll(['id', 'code', 'name', 'required', 'active', 'cnaes'])
                    ->etc()));
    }

    public function test_cria_requisito_e_audita(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/requisitos-documentais', [
                'code' => 'CONTRATO_SOCIAL',
                'name' => 'Contrato social',
                'description' => 'Documento constitutivo da empresa.',
                'required' => '1',
                'validation_instructions' => 'Conferir CNPJ e objeto social.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('document_requirements', [
            'code' => 'CONTRATO_SOCIAL',
            'name' => 'Contrato social',
            'required' => true,
            'active' => true,
        ]);

        $requirement = DocumentRequirement::where('code', 'CONTRATO_SOCIAL')->firstOrFail();

        $activity = Activity::where('event', 'created')
            ->where('subject_type', DocumentRequirement::class)
            ->where('subject_id', $requirement->id)
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de criação do requisito.');
    }

    public function test_code_unico_e_imutavel_na_edicao(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        DocumentRequirement::factory()->create(['code' => 'CONTRATO_SOCIAL']);

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/requisitos-documentais', [
                'code' => 'CONTRATO_SOCIAL',
                'name' => 'Outro requisito',
                'required' => '1',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, DocumentRequirement::count());

        $requirement = DocumentRequirement::factory()->create(['code' => 'IPTU', 'name' => 'IPTU']);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/requisitos-documentais/{$requirement->id}", [
                'code' => 'OUTRO_CODIGO',
                'name' => 'IPTU atualizado',
                'required' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $requirement->refresh();

        $this->assertSame('IPTU', $requirement->code);
        $this->assertSame('IPTU atualizado', $requirement->name);
        $this->assertFalse($requirement->required);
    }

    public function test_desativar_preserva_o_registro(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $requirement = DocumentRequirement::factory()->create(['active' => true]);

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/requisitos-documentais/{$requirement->id}/toggle")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('document_requirements', ['id' => $requirement->id]);
        $this->assertFalse($requirement->refresh()->active);

        $activity = Activity::where('event', 'updated')
            ->where('subject_type', DocumentRequirement::class)
            ->where('subject_id', $requirement->id)
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de atualização ao desativar.');
    }

    public function test_vincula_cnaes_por_sync_exato_e_audita(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $requirement = DocumentRequirement::factory()->create();
        [$a, $b] = Cnae::factory()->count(2)->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/requisitos-documentais/{$requirement->id}/cnaes", ['cnae_ids' => [$a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $requirement->cnaes()->pluck('cnaes.id')->all());

        // Novo sync com [A] remove o B (conjunto EXATO — padrão syncSecondaries).
        $this->actingAs($admin, 'gestao')
            ->put("/gestao/requisitos-documentais/{$requirement->id}/cnaes", ['cnae_ids' => [$a->id]])
            ->assertRedirect();

        $this->assertSame([$a->id], $requirement->cnaes()->pluck('cnaes.id')->all());

        $activity = Activity::where('log_name', 'solicitacoes')
            ->where('event', 'requisito-cnaes')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity do vínculo requisito-CNAEs.');
        $this->assertSame('sucesso', $activity->result);
        $this->assertEqualsCanonicalizing([$a->code, $b->code], $activity->properties['antes']);
        $this->assertSame([$a->code], $activity->properties['depois']);
    }

    public function test_so_cnaes_ativos_podem_ser_vinculados(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $requirement = DocumentRequirement::factory()->create();
        $inativo = Cnae::factory()->inactive()->create();

        $this->actingAs($admin, 'gestao')
            ->put("/gestao/requisitos-documentais/{$requirement->id}/cnaes", ['cnae_ids' => [$inativo->id]])
            ->assertSessionHasErrors('cnae_ids.0');

        $this->assertSame(0, $requirement->cnaes()->count());
    }

    public function test_busca_de_cnaes_disponiveis_retorna_somente_ativos(): void
    {
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $ativo = Cnae::factory()->create([
            'code' => '5611201',
            'description' => 'Restaurantes e similares',
        ]);
        Cnae::factory()->inactive()->create([
            'code' => '5611202',
            'description' => 'Restaurante inativo de teste',
        ]);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/requisitos-documentais/cnaes-disponiveis?search=restaurante')
            ->assertOk()
            ->assertExactJson([
                [
                    'id' => $ativo->id,
                    'formatted_code' => '5611-2/01',
                    'description' => 'Restaurantes e similares',
                ],
            ]);
    }
}
