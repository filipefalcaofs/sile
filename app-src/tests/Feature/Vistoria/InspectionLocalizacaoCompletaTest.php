<?php

namespace Tests\Feature\Vistoria;

use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A ficha de vistoria abre com CodLog e Via preenchidos e com o logradouro
 * acompanhado do tipo (rua, avenida, travessa...), como no cadastro do processo.
 */
class InspectionLocalizacaoCompletaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function vistoriador(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos = [], ?User $vistoriador = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create(array_merge([
            'assigned_user_id' => $vistoriador?->id,
            'address_street' => 'DOUTOR JORGE COSTA ANDRADE',
            'address_number' => '9',
            'address_neighborhood' => 'ÁGUAS CLARAS',
            'address_zip' => '41310260',
        ], $atributos));
        $processo->forceFill([
            'cod_log' => '4521',
            'camadas_incidentes' => ['via' => 'VC I'],
            'origem_declarada' => ['tipo_logradouro' => 'RUA'],
        ])->save();

        return $processo;
    }

    public function test_ficha_nova_traz_codlog_via_e_logradouro_com_tipo(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo(vistoriador: $vistoriador);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.cod_logradouro', '4521')
                ->where('ficha.via', 'VC I')
                ->where('ficha.logradouro', 'RUA DOUTOR JORGE COSTA ANDRADE'));
    }

    public function test_tipo_nao_se_repete_quando_o_logradouro_ja_o_traz(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo(['address_street' => 'Rua Cabo Astrogildo Saldanha'], $vistoriador);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertInertia(fn (Assert $page) => $page->where('ficha.logradouro', 'Rua Cabo Astrogildo Saldanha'));
    }

    public function test_sem_tipo_declarado_o_logradouro_fica_como_no_cadastro(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo(vistoriador: $vistoriador);
        $processo->forceFill(['origem_declarada' => null])->save();

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertInertia(fn (Assert $page) => $page->where('ficha.logradouro', 'DOUTOR JORGE COSTA ANDRADE'));
    }

    public function test_ficha_em_preenchimento_aberta_antes_completa_codlog_via_e_tipo(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();
        Inspection::factory()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vistoriador->id,
            'cod_logradouro' => null,
            'via' => null,
            'logradouro' => 'DOUTOR JORGE COSTA ANDRADE',
        ]);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.cod_logradouro', '4521')
                ->where('ficha.via', 'VC I')
                ->where('ficha.logradouro', 'RUA DOUTOR JORGE COSTA ANDRADE'));
    }

    public function test_ficha_concluida_nao_e_alterada_ao_consultar(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();
        Inspection::factory()->concluida()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vistoriador->id,
            'cod_logradouro' => null,
            'via' => null,
            'logradouro' => 'DOUTOR JORGE COSTA ANDRADE',
        ]);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.cod_logradouro', null)
                ->where('ficha.via', null)
                ->where('ficha.logradouro', 'DOUTOR JORGE COSTA ANDRADE'));
    }
}
