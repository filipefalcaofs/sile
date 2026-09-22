<?php

namespace Tests\Feature\Ui;

use App\Enums\AnalysisCategory;
use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RiscoMunicipal;
use App\Enums\RiscoSanitario;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VocabularioMetadataTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    public function test_endpoint_devolve_rotulos_dos_enums_de_negocio(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->getJson('/gestao/metadados')
            ->assertOk()
            ->assertJsonPath('risco_municipal.0.value', RiscoMunicipal::BaixoA->value)
            ->assertJsonPath('risco_municipal.0.label', RiscoMunicipal::BaixoA->label())
            ->assertJsonPath('risco_sanitario.1.value', RiscoSanitario::Medio->value)
            ->assertJsonPath('risco_sanitario.1.label', RiscoSanitario::Medio->label())
            ->assertJsonCount(count(AnalysisCategory::cases()), 'analysis_category')
            ->assertJsonCount(count(ResultadoViabilidade::cases()), 'resultado_viabilidade')
            ->assertJsonCount(count(Quadro10Permissao::cases()), 'quadro10_permissao')
            ->assertJsonMissingPath('analysis_category.2')
            ->assertJsonFragment([
                'value' => ResultadoViabilidade::PermitidoComCondicoes->value,
                'label' => ResultadoViabilidade::PermitidoComCondicoes->label(),
            ]);
    }

    public function test_convidado_nao_acessa_metadados(): void
    {
        $this->getJson('/gestao/metadados')->assertRedirect('/gestao/login');
    }

    public function test_inertia_compartilha_o_mesmo_vocabulario(): void
    {
        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('vocabulario.risco_municipal')
                ->has('vocabulario.risco_sanitario')
                ->has('vocabulario.analysis_category')
                ->has('vocabulario.resultado_viabilidade')
                ->has('vocabulario.quadro10_permissao')
                ->where('vocabulario.risco_municipal.0.value', RiscoMunicipal::BaixoA->value)
                ->where('vocabulario.risco_municipal.0.label', RiscoMunicipal::BaixoA->label()));
    }
}
