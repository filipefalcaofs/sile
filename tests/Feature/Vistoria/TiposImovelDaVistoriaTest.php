<?php

namespace Tests\Feature\Vistoria;

use App\Models\PropertyType;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\PropertyTypeVistoriaSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Lote, Box, Centro comercial, Shopping e Outros na ficha de vistoria, sem
 * mexer no que o REGIN reconhece nem no encaminhamento do processo.
 */
class TiposImovelDaVistoriaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PropertyTypeSeeder::class);
        $this->seed(PropertyTypeVistoriaSeeder::class);
    }

    public function test_ficha_oferece_os_cinco_novos_tipos(): void
    {
        $vistoriador = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create(['assigned_user_id' => $vistoriador->id]);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tiposImovel', fn ($tipos) => collect($tipos)->pluck('label')->intersect(
                    ['Lote', 'Box', 'Centro comercial', 'Shopping', 'Outros'],
                )->count() === 5));
    }

    public function test_novos_tipos_nao_dirigem_regra_e_o_regin_continua_sem_reconhece_los(): void
    {
        $novos = PropertyType::query()->whereIn('code', ['lote', 'box', 'centro_comercial', 'shopping', 'outros'])->get();

        $this->assertCount(5, $novos);
        $this->assertSame(0, $novos->where('drives_rule', true)->count());
        $this->assertSame(0, $novos->sum(fn (PropertyType $tipo): int => $tipo->aliases()->count()));

        $this->assertSame(0, $novos->where('regin_reconhece', true)->count());

        foreach (['Shopping', 'Box', 'Lote', 'Outros', 'Centro comercial'] as $texto) {
            $this->assertFalse(
                TipoImovel::fromRegin($texto, TipoImovelCatalog::vigente())->permiteDecisaoAutomatica(),
                "{$texto} do REGIN não pode decidir sozinho",
            );
        }
    }

    public function test_seed_e_idempotente_e_respeita_ajuste_da_sedur(): void
    {
        PropertyType::query()->where('code', 'box')->update(['label' => 'Box comercial', 'active' => false]);

        $this->seed(PropertyTypeVistoriaSeeder::class);

        $box = PropertyType::query()->where('code', 'box')->sole();
        $this->assertSame('Box comercial', $box->label);
        $this->assertFalse($box->active);
        $this->assertSame(10, PropertyType::query()->count());
    }
}
