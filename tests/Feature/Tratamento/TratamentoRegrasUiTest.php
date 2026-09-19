<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TratamentoRegrasUiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pagina_mostra_planilha_vigente(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);
        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));

        $this->actingAs(User::factory()->administrador()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get('/gestao/regras-tratamento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/tratamento/index')
                ->where('versao', 'planilha-20-08-26')
                ->where('contagens.perguntas', 32)
                ->has('perguntas', 32));
    }

    public function test_pagina_sem_versao_nao_inventa_planilha(): void
    {
        $this->actingAs(User::factory()->administrador()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get('/gestao/regras-tratamento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/tratamento/index')
                ->where('versao', null)
                ->where('contagens.cnaes', 0));
    }
}
