<?php

namespace Tests\Unit\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    public function test_aceita_senha_com_maiuscula_minuscula_numero_e_simbolo(): void
    {
        $validator = Validator::make(
            ['password' => 'Rp@131268'],
            ['password' => Password::default()],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_rejeita_senha_sem_maiuscula_com_mensagem_em_portugues(): void
    {
        $validator = Validator::make(
            ['password' => 'rp@131268'],
            ['password' => Password::default()],
            [],
            ['password' => 'senha'],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'O campo senha deve conter pelo menos uma letra maiúscula e uma minúscula.',
            $validator->errors()->first('password'),
        );
    }
}
