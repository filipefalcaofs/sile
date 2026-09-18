<?php

namespace Tests\Feature\Ui;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        Parameter::query()->create([
            'key' => 'relatorios.export.formatos_habilitados',
            'group' => 'relatorios',
            'type' => 'json',
            'value' => '["csv"]',
            'default_value' => '["csv","xlsx","pdf"]',
            'validation_rules' => ['required', 'json'],
            'description' => 'Formatos de exportação habilitados',
        ]);
        Cache::flush();

        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->get('/gestao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('export_formatos', ['csv']));
    }
}
