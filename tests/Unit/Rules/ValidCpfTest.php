<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidCpf;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidCpfTest extends TestCase
{
    public function test_aceita_cpf_valido(): void
    {
        $this->assertFalse($this->validationFails('52998224725'));
        $this->assertFalse($this->validationFails('529.982.247-25'));
    }

    public function test_rejeita_cpf_com_digito_verificador_errado(): void
    {
        $this->assertTrue($this->validationFails('52998224724'));
    }

    public function test_rejeita_cpf_com_digitos_repetidos(): void
    {
        $this->assertTrue($this->validationFails('11111111111'));
    }

    public function test_rejeita_cpf_com_tamanho_errado(): void
    {
        $this->assertTrue($this->validationFails('1234567890'));
    }

    private function validationFails(string $value): bool
    {
        return Validator::make(['cpf' => $value], ['cpf' => [new ValidCpf]])->fails();
    }
}
