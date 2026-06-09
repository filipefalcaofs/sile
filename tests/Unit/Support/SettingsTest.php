<?php

namespace Tests\Unit\Support;

use App\Support\Settings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_le_parametro_de_config_sile(): void
    {
        $this->assertSame(5, Settings::get('security.login.max_attempts'));
    }

    public function test_retorna_default_quando_parametro_ausente(): void
    {
        $this->assertSame('padrao', Settings::get('inexistente.chave', 'padrao'));
    }

    public function test_politica_de_senha_reflete_parametros(): void
    {
        $weak = Validator::make(
            ['password' => 'fraca'],
            ['password' => Password::defaults()],
        );

        $this->assertTrue($weak->fails());

        $longButSimple = Validator::make(
            ['password' => 'somenteminusculas'],
            ['password' => Password::defaults()],
        );

        $this->assertTrue($longButSimple->fails());

        $strong = Validator::make(
            ['password' => 'SenhaForte1'],
            ['password' => Password::defaults()],
        );

        $this->assertFalse($strong->fails());
    }
}
