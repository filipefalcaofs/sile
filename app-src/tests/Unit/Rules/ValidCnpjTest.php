<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidCnpj;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidCnpjTest extends TestCase
{
    public function test_cnpj_numerico_valido_e_aceito(): void
    {
        $this->assertTrue($this->validationPasses('00000000000191'));
        $this->assertTrue($this->validationPasses('33000167000101'));
    }

    public function test_cnpj_alfanumerico_valido_e_aceito(): void
    {
        $this->assertTrue($this->validationPasses('12ABC34501DE35'));
        $this->assertTrue($this->validationPasses('12.ABC.345/01DE-35'));
    }

    public function test_cnpj_com_mascara_e_normalizado(): void
    {
        $this->assertTrue($this->validationPasses('00.000.000/0001-91'));
    }

    public function test_cnpj_com_dv_errado_e_rejeitado(): void
    {
        $this->assertFalse($this->validationPasses('00000000000192'));
        $this->assertFalse($this->validationPasses('12ABC34501DE36'));
    }

    public function test_cnpj_com_tamanho_errado_e_rejeitado(): void
    {
        $this->assertFalse($this->validationPasses('0000000000019'));
        $this->assertFalse($this->validationPasses('000000000001911'));
    }

    public function test_cnpj_com_repeticao_uniforme_e_rejeitado(): void
    {
        $this->assertFalse($this->validationPasses('11111111111111'));
    }

    public function test_cnpj_com_letra_nos_digitos_verificadores_e_rejeitado(): void
    {
        $this->assertFalse($this->validationPasses('12ABC34501DEAA'));
    }

    private function validationPasses(string $value): bool
    {
        return Validator::make(['cnpj' => $value], ['cnpj' => [new ValidCnpj]])->passes();
    }
}
