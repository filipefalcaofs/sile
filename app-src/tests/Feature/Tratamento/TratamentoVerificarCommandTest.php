<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoEnquadramento;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TratamentoVerificarCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function cargaParcial(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);

        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
    }

    public function test_carga_sem_metadados_falha_e_aponta_o_comando_de_correcao(): void
    {
        $this->cargaParcial();

        $this->artisan('tratamento:verificar')
            ->expectsOutputToContain('incompleta')
            ->assertFailed();
    }

    public function test_corrigir_aplica_o_complemento_e_passa(): void
    {
        $this->cargaParcial();

        $this->artisan('tratamento:verificar --corrigir')
            ->expectsOutputToContain('completa')
            ->assertSuccessful();

        $this->assertSame(0, TratamentoEnquadramento::query()->whereNull('metadados')->count());
    }

    public function test_sem_versao_vigente_falha(): void
    {
        $this->artisan('tratamento:verificar')->assertFailed();
    }
}
