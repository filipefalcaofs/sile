<?php

namespace Tests\Feature\Sefaz;

use App\Services\Sefaz\SefazConsultaIndisponivelException;
use App\Services\Sefaz\SefazViabilidadeConsultaGateway;
use App\Services\Sefaz\SimuladoSefazViabilidadeConsultaGateway;
use App\Services\Sefaz\UnavailableSefazViabilidadeConsultaGateway;
use Tests\TestCase;

class SefazViabilidadeConsultaGatewayTest extends TestCase
{
    private function simulador(): void
    {
        config([
            'viabiliza.sefaz.consulta.driver' => 'simulado',
            'viabiliza.sefaz.consulta.simulador.viabilidades' => [
                '12345678000190' => ['numero' => 'VIA-2026-1', 'cnaes' => ['4711-3/02', '4712-1/00']],
            ],
            'viabiliza.sefaz.consulta.simulador.indisponiveis' => ['99999999000199'],
        ]);
    }

    public function test_simulador_devolve_a_viabilidade_configurada(): void
    {
        $this->simulador();

        $viabilidade = app(SefazViabilidadeConsultaGateway::class)->consultarPorCnpj('12345678000190');

        $this->assertSame('VIA-2026-1', $viabilidade?->numero);
        $this->assertSame(['4711-3/02', '4712-1/00'], $viabilidade?->cnaes);
    }

    public function test_simulador_devolve_null_para_cnpj_sem_viabilidade(): void
    {
        $this->simulador();

        $this->assertNull(app(SefazViabilidadeConsultaGateway::class)->consultarPorCnpj('00000000000191'));
    }

    public function test_simulador_lanca_indisponivel_para_cnpj_marcado(): void
    {
        $this->simulador();

        $this->expectException(SefazConsultaIndisponivelException::class);

        app(SefazViabilidadeConsultaGateway::class)->consultarPorCnpj('99999999000199');
    }

    public function test_cnpj_com_mascara_e_aceito(): void
    {
        $this->simulador();

        $viabilidade = app(SefazViabilidadeConsultaGateway::class)->consultarPorCnpj('12.345.678/0001-90');

        $this->assertSame('VIA-2026-1', $viabilidade?->numero);
    }

    public function test_driver_padrao_e_o_indisponivel_e_nunca_finge_a_consulta(): void
    {
        $this->assertInstanceOf(UnavailableSefazViabilidadeConsultaGateway::class, app(SefazViabilidadeConsultaGateway::class));

        $this->expectException(SefazConsultaIndisponivelException::class);

        app(SefazViabilidadeConsultaGateway::class)->consultarPorCnpj('12345678000190');
    }

    public function test_driver_simulado_resolve_o_simulador(): void
    {
        $this->simulador();

        $this->assertInstanceOf(SimuladoSefazViabilidadeConsultaGateway::class, app(SefazViabilidadeConsultaGateway::class));
    }
}
