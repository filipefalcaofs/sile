<?php

namespace Tests\Feature\Parameters;

use App\Models\Parameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParameterRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_typed_value_faz_cast_por_tipo(): void
    {
        $cases = [
            ['integer', '15', 15],
            ['boolean', '1', true],
            ['boolean', '0', false],
            ['decimal', '2.5', 2.5],
            ['json', '{"a":1}', ['a' => 1]],
            ['string', 'texto', 'texto'],
        ];

        foreach ($cases as [$type, $value, $expected]) {
            $parameter = Parameter::factory()->create(['type' => $type, 'value' => $value]);

            $this->assertSame($expected, $parameter->typedValue());
        }
    }

    public function test_typed_value_usa_default_quando_value_e_nulo(): void
    {
        $parameter = Parameter::factory()->create([
            'type' => 'integer',
            'value' => null,
            'default_value' => '9',
        ]);

        $this->assertSame(9, $parameter->typedValue());
    }

    public function test_valor_sensivel_e_criptografado_no_banco(): void
    {
        $parameter = Parameter::factory()->sensitive()->create(['value' => 'segredo-001']);

        $raw = DB::table('parameters')->where('id', $parameter->id)->value('value');

        $this->assertNotSame('segredo-001', $raw);
        $this->assertStringNotContainsString('segredo-001', $raw);
        $this->assertSame('segredo-001', $parameter->fresh()->value);
    }

    public function test_valor_nao_sensivel_permanece_em_claro(): void
    {
        $parameter = Parameter::factory()->create(['value' => 'aberto']);

        $this->assertSame('aberto', DB::table('parameters')->where('id', $parameter->id)->value('value'));
    }

    public function test_gravacao_invalida_o_cache_da_chave(): void
    {
        Cache::put('sile.parameters.chave.x', 'velho', 60);

        $parameter = Parameter::factory()->create(['key' => 'chave.x']);

        $this->assertFalse(Cache::has('sile.parameters.chave.x'));

        Cache::put('sile.parameters.chave.x', 'velho-de-novo', 60);

        $parameter->update(['value' => 'novo']);

        $this->assertFalse(Cache::has('sile.parameters.chave.x'));

        Cache::put('sile.parameters.chave.x', 'velho-antes-do-delete', 60);

        $parameter->delete();

        $this->assertFalse(Cache::has('sile.parameters.chave.x'));
    }
}
