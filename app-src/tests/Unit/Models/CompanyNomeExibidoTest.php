<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use PHPUnit\Framework\TestCase;

class CompanyNomeExibidoTest extends TestCase
{
    public function test_prefere_o_nome_fantasia(): void
    {
        $empresa = new Company(['trade_name' => 'Padaria Sol', 'legal_name' => 'Sol Alimentos Ltda']);

        $this->assertSame('Padaria Sol', $empresa->nomeExibido());
    }

    public function test_sem_nome_fantasia_usa_a_razao_social(): void
    {
        $empresa = new Company(['trade_name' => null, 'legal_name' => 'Sol Alimentos Ltda']);

        $this->assertSame('Sol Alimentos Ltda', $empresa->nomeExibido());
    }

    public function test_empresa_a_constituir_nao_e_exibida(): void
    {
        $empresa = new Company(['trade_name' => null, 'legal_name' => ' EMPRESA A CONSTITUIR ']);

        $this->assertNull($empresa->nomeExibido());
    }

    public function test_marcador_no_nome_fantasia_cede_lugar_a_razao_social(): void
    {
        $empresa = new Company(['trade_name' => 'Empresa a constituir', 'legal_name' => 'Sol Alimentos Ltda']);

        $this->assertSame('Sol Alimentos Ltda', $empresa->nomeExibido());
    }
}
