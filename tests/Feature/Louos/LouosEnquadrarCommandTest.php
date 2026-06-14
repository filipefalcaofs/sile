<?php

namespace Tests\Feature\Louos;

use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\LouosQuadro11Seeder;
use Database\Seeders\LouosQuadro7Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comando louos:enquadrar {cnae} --area= (fechamento da Fase 5): exercita o
 * motor REAL (LouosEnquadramentoService) sobre o SEED OFICIAL (Quadros 7/10/11
 * derivados da Lei nº 9.148/2016), imprimindo o parecer fundamentado — evidência
 * de ponta a ponta, sem fachada.
 *
 * Sem --zona o parecer é honestamente `pendente` (zona urbanística pendente
 * SEDUR); a zona é ENTRADA EXPLÍCITA do operador (hipótese), nunca inventada
 * pelo sistema. Com uma zona proibida no Quadro 10 o veredito é `nao_permitido`.
 * Formato de CNAE inválido ou área ausente/inválida saem com erro (exit 1).
 */
class LouosEnquadrarCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            LouosQuadro7Seeder::class,
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
        ]);
    }

    public function test_enquadra_sem_zona_sai_pendente(): void
    {
        // 4712-1/00 (minimercado) área 200 → grupo nR1 no Quadro 7. Sem zona, o
        // Quadro 10 degrada (indisponível) e o consolidado é pendente — honesto.
        $this->artisan('louos:enquadrar', ['cnae' => '4712100', '--area' => '200'])
            ->expectsOutputToContain('nR1')
            ->expectsOutputToContain('Pendente')
            ->assertSuccessful();
    }

    public function test_enquadra_com_zona_proibida_sai_nao_permitido(): void
    {
        // 1091-1/02 enquadra em nR3; a zona ZPAM (entrada explícita do operador)
        // proíbe nR3 no Quadro 10 real → veredito nao_permitido (RN-005).
        $this->artisan('louos:enquadrar', [
            'cnae' => '1091102',
            '--area' => '200',
            '--zona' => 'ZPAM',
        ])
            ->expectsOutputToContain('nR3')
            ->expectsOutputToContain('Não permitido')
            ->assertSuccessful();
    }

    public function test_cnae_invalido_falha(): void
    {
        $this->artisan('louos:enquadrar', ['cnae' => 'abc', '--area' => '200'])
            ->expectsOutputToContain('inválido')
            ->assertFailed();
    }

    public function test_area_ausente_falha(): void
    {
        // Área é obrigatória: sem --area o motor não tem como aplicar o Quadro 7.
        $this->artisan('louos:enquadrar', ['cnae' => '4712100'])
            ->expectsOutputToContain('área')
            ->assertFailed();
    }
}
