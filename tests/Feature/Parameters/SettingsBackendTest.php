<?php

namespace Tests\Feature\Parameters;

use App\Models\Parameter;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class SettingsBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_valor_administrado_do_banco(): void
    {
        Parameter::factory()->integer('5')->create([
            'key' => 'security.login.max_attempts',
            'group' => 'seguranca',
            'value' => '7',
        ]);

        $this->assertSame(7, Settings::get('security.login.max_attempts'));
    }

    public function test_fallback_para_config_quando_chave_nao_existe_no_banco(): void
    {
        $this->assertSame(5, Settings::get('security.login.max_attempts'));
    }

    public function test_fallback_para_default_do_catalogo_quando_value_e_nulo(): void
    {
        Parameter::factory()->integer('12')->create([
            'key' => 'ui.access_history.per_page',
            'group' => 'ui',
            'value' => null,
        ]);

        $this->assertSame(12, Settings::get('ui.access_history.per_page'));
    }

    public function test_alteracao_tem_efeito_imediato_na_leitura_seguinte(): void
    {
        $parameter = Parameter::factory()->integer('5')->create([
            'key' => 'security.login.max_attempts',
            'group' => 'seguranca',
            'value' => '7',
        ]);

        $this->assertSame(7, Settings::get('security.login.max_attempts'));

        $parameter->update(['value' => '9']);

        $this->assertSame(9, Settings::get('security.login.max_attempts'));
    }

    public function test_enabled_le_toggle_do_grupo_features(): void
    {
        $parameter = Parameter::factory()->create([
            'key' => 'features.procuracoes',
            'group' => 'features',
            'type' => 'boolean',
            'value' => '0',
            'default_value' => '1',
        ]);

        $this->assertFalse(Settings::enabled('procuracoes'));

        $parameter->delete();

        $this->assertTrue(Settings::enabled('procuracoes'));
    }

    public function test_politica_de_senha_respeita_parametro_do_banco(): void
    {
        Parameter::factory()->integer('8')->create([
            'key' => 'security.password.min_length',
            'group' => 'seguranca',
            'value' => '12',
        ]);

        $tooShort = Validator::make(
            ['password' => 'SenhaForte1'],
            ['password' => Password::defaults()],
        );

        $this->assertTrue($tooShort->fails());

        $longEnough = Validator::make(
            ['password' => 'SenhaForteMuito1'],
            ['password' => Password::defaults()],
        );

        $this->assertFalse($longEnough->fails());
    }
}
