<?php

namespace Tests\Feature\Feriados;

use App\Models\Holiday;
use App\Models\User;
use App\Services\Expresso\DatabaseHolidayProvider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Sincronização anual dos feriados de Salvador pela FeriadosAPI: grava só datas
 * novas, nunca sobrescreve cadastro existente e só o feriado municipal da API
 * caracteriza o calendário oficial.
 */
class SincronizarFeriadosCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const URL = 'feriadosapi.test/api/v1/feriados/cidade/2927408*';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.feriados_api.base_url' => 'https://feriadosapi.test',
            'services.feriados_api.token' => 'token-de-teste',
            'services.feriados_api.ibge' => '2927408',
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $feriados  [DD/MM/AAAA, nome, tipo]
     * @return array<string, mixed>
     */
    private function resposta(array $feriados, int $page = 1, int $totalPages = 1): array
    {
        return [
            'cidade' => ['ibge' => 2927408, 'nome' => 'Salvador', 'uf' => 'BA'],
            'feriados' => array_map(fn (array $f): array => [
                'id' => fake()->uuid(),
                'data' => $f[0],
                'nome' => $f[1],
                'tipo' => $f[2],
            ], $feriados),
            'meta' => ['total' => count($feriados), 'page' => $page, 'per_page' => 100, 'total_pages' => $totalPages],
        ];
    }

    public function test_grava_nacional_estadual_e_municipal_como_feriados_especificos(): void
    {
        Http::fake([self::URL => Http::response($this->resposta([
            ['07/09/2027', 'Independência do Brasil', 'NACIONAL'],
            ['02/07/2027', 'Independência da Bahia', 'ESTADUAL'],
            ['29/03/2027', 'Aniversário de Salvador', 'MUNICIPAL'],
        ]))]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();

        $this->assertSame(3, Holiday::query()->count());
        $municipal = Holiday::query()->whereDate('date', '2027-03-29')->firstOrFail();
        $this->assertSame('Aniversário de Salvador', $municipal->name);
        $this->assertSame('api', $municipal->source);
        $this->assertSame('municipal', $municipal->type);
        $this->assertFalse($municipal->recurring_annually);
        $this->assertTrue($municipal->active);
    }

    public function test_ignora_feriado_facultativo(): void
    {
        Http::fake([self::URL => Http::response($this->resposta([
            ['16/02/2027', 'Carnaval', 'FACULTATIVO'],
        ]))]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();

        $this->assertSame(0, Holiday::query()->count());
    }

    public function test_reexecutar_nao_duplica(): void
    {
        Http::fake([self::URL => Http::response($this->resposta([
            ['29/03/2027', 'Aniversário de Salvador', 'MUNICIPAL'],
        ]))]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();
        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();

        $this->assertSame(1, Holiday::query()->count());
    }

    public function test_nao_sobrescreve_data_ja_cadastrada_manualmente(): void
    {
        Holiday::factory()->inactive()->create(['date' => '2027-03-29', 'name' => 'Nome da SEDUR']);
        Http::fake([self::URL => Http::response($this->resposta([
            ['29/03/2027', 'Aniversário de Salvador', 'MUNICIPAL'],
        ]))]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();

        $holiday = Holiday::query()->sole();
        $this->assertSame('Nome da SEDUR', $holiday->name);
        $this->assertFalse($holiday->active);
        $this->assertNull($holiday->source);
    }

    public function test_atualiza_o_nome_de_registro_da_api_sem_reativar_o_inativado(): void
    {
        Holiday::factory()->inactive()->create([
            'date' => '2027-03-29', 'name' => 'Nome antigo', 'source' => 'api', 'type' => 'municipal',
        ]);
        Http::fake([self::URL => Http::response($this->resposta([
            ['29/03/2027', 'Aniversário de Salvador', 'MUNICIPAL'],
        ]))]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();

        $holiday = Holiday::query()->sole();
        $this->assertSame('Aniversário de Salvador', $holiday->name);
        $this->assertFalse($holiday->active);
    }

    public function test_percorre_todas_as_paginas(): void
    {
        Http::fake([
            self::URL => Http::sequence()
                ->push($this->resposta([['01/01/2027', 'Confraternização Universal', 'NACIONAL']], 1, 2))
                ->push($this->resposta([['29/03/2027', 'Aniversário de Salvador', 'MUNICIPAL']], 2, 2)),
        ]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();

        $this->assertSame(2, Holiday::query()->count());
    }

    public function test_sem_argumento_sincroniza_o_ano_corrente_e_o_seguinte(): void
    {
        Carbon::setTestNow('2026-10-20');
        Http::fake([self::URL => Http::response($this->resposta([]))]);

        $this->artisan('feriados:sincronizar')->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request['ano'] == 2026);
        Http::assertSent(fn ($request): bool => $request['ano'] == 2027);
        Http::assertSentCount(2);
    }

    public function test_envia_o_token_como_bearer(): void
    {
        Http::fake([self::URL => Http::response($this->resposta([]))]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer token-de-teste'));
    }

    public function test_sem_token_falha_sem_chamar_a_api(): void
    {
        config(['services.feriados_api.token' => null]);
        Http::fake();

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertFailed();

        Http::assertNothingSent();
        $this->assertSame(0, Holiday::query()->count());
    }

    public function test_falha_em_um_ano_nao_impede_o_outro(): void
    {
        Http::fake([
            self::URL => Http::sequence()
                ->push('erro', 500)
                ->push($this->resposta([['29/03/2027', 'Aniversário de Salvador', 'MUNICIPAL']])),
        ]);

        $this->artisan('feriados:sincronizar', ['ano' => [2026, 2027]])->assertFailed();

        $this->assertSame(1, Holiday::query()->count());
    }

    public function test_so_feriado_municipal_da_api_caracteriza_calendario_oficial(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push($this->resposta([['07/09/2027', 'Independência do Brasil', 'NACIONAL']]))
            ->push($this->resposta([['29/03/2027', 'Aniversário de Salvador', 'MUNICIPAL']])),
        ]);

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();
        $this->assertFalse(app(DatabaseHolidayProvider::class)->hasOfficialCalendar());

        $this->artisan('feriados:sincronizar', ['ano' => [2027]])->assertSuccessful();
        $this->assertTrue(app(DatabaseHolidayProvider::class)->hasOfficialCalendar());
    }

    public function test_gravar_feriado_esquece_o_cache_do_calendario(): void
    {
        $provider = app(DatabaseHolidayProvider::class);
        $this->assertFalse($provider->isHoliday(Carbon::parse('2027-03-29')));

        Holiday::factory()->create(['date' => '2027-03-29']);

        $this->assertTrue($provider->isHoliday(Carbon::parse('2027-03-29')));
    }

    public function test_tela_de_tempo_informa_se_ha_calendario_municipal_oficial(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $gestor = User::factory()->gestor()->withAcceptedLgpdTerm()->create();

        $this->actingAs($gestor, 'gestao')
            ->get(route('gestao.relatorios.tempo'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('calendarioOficial', false));

        Holiday::factory()->create(['date' => '2027-03-29', 'source' => 'api', 'type' => 'municipal']);

        $this->actingAs($gestor, 'gestao')
            ->get(route('gestao.relatorios.tempo'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('calendarioOficial', true));
    }
}
