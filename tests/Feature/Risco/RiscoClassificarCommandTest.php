<?php

namespace Tests\Feature\Risco;

use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comando risco:classificar {cnae} (fechamento da Fase 6): exercita o motor
 * REAL (RiscoClassificationService) sobre o SEED OFICIAL (Decreto 32.636/2020 +
 * planilha VISA + gatilhos), imprimindo o resultado fundamentado — evidência de
 * ponta a ponta, sem fachada. CNAE de baixo risco segue para o expresso; um
 * gatilho de contexto (ex.: ZEIS) derruba para análise; CNAE sem regra vigente
 * segue para análise (exit 0, não é erro); CNAE de formato inválido sai com erro.
 */
class RiscoClassificarCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
        ]);
    }

    public function test_classifica_cnae_baixo_a_real_e_indica_expresso(): void
    {
        $this->artisan('risco:classificar', ['cnae' => '0111301'])
            ->expectsOutputToContain('Baixo Risco A')
            ->expectsOutputToContain('Decreto')
            ->expectsOutputToContain('expresso')
            ->assertSuccessful();
    }

    public function test_classifica_com_gatilho_zeis_indica_analise(): void
    {
        $this->artisan('risco:classificar', [
            'cnae' => '0111301',
            '--gatilho' => ['zeis_especial'],
        ])
            ->expectsOutputToContain('análise')
            ->assertSuccessful();
    }

    public function test_cnae_sem_regra_segue_para_analise_sem_erro(): void
    {
        $this->artisan('risco:classificar', ['cnae' => '9999999'])
            ->expectsOutputToContain('Não classificado')
            ->expectsOutputToContain('análise')
            ->assertSuccessful();
    }

    public function test_cnae_invalido_sai_com_erro(): void
    {
        $this->artisan('risco:classificar', ['cnae' => 'abc'])
            ->expectsOutputToContain('inválido')
            ->assertFailed();
    }
}
