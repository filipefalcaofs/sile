<?php

namespace Tests\Feature\Sefaz;

use App\Services\Sefaz\SefazConsultaIndisponivelException;
use App\Services\Sefaz\SefazViabilidadeConsultaGateway;
use App\Services\Sefaz\UnavailableSefazViabilidadeConsultaGateway;
use Tests\TestCase;

class SefazViabilidadeConsultaGatewayTest extends TestCase
{
    public function test_binding_padrao_e_o_indisponivel(): void
    {
        $this->assertInstanceOf(UnavailableSefazViabilidadeConsultaGateway::class, app(SefazViabilidadeConsultaGateway::class));
    }

    public function test_sem_provider_real_a_consulta_nunca_finge_uma_viabilidade(): void
    {
        $this->expectException(SefazConsultaIndisponivelException::class);

        app(SefazViabilidadeConsultaGateway::class)->consultarPorCnpj('12.345.678/0001-90');
    }

    public function test_excecao_guarda_o_cnpj_so_em_digitos(): void
    {
        try {
            app(SefazViabilidadeConsultaGateway::class)->consultarPorCnpj('12345678000190');
            $this->fail('Esperava SefazConsultaIndisponivelException');
        } catch (SefazConsultaIndisponivelException $e) {
            $this->assertSame('12345678000190', $e->cnpj);
        }
    }
}
