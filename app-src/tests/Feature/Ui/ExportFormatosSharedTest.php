<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExportFormatosSharedTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_inertia_expoe_formatos_habilitados_do_catalogo(): void
    {
        config(['viabiliza.relatorios.export.formatos_habilitados' => ['csv']]);

        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('export_formatos', ['csv']));
    }
}
