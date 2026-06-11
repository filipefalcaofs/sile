<?php

namespace Tests\Feature\Seeders;

use App\Models\Parameter;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParameterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_catalogo_completo(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertSame(11, Parameter::query()->count());
        $this->assertSame(
            ['features', 'seguranca', 'ui'],
            Parameter::query()->distinct()->orderBy('group')->pluck('group')->all(),
        );

        $maxAttempts = Parameter::query()->where('key', 'security.login.max_attempts')->first();

        $this->assertNotNull($maxAttempts);
        $this->assertSame('integer', $maxAttempts->type);
        $this->assertSame('5', $maxAttempts->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:20'], $maxAttempts->validation_rules);
        $this->assertNull($maxAttempts->value);
    }

    public function test_seeder_preserva_valor_administrado(): void
    {
        $this->seed(ParameterSeeder::class);

        Parameter::query()
            ->where('key', 'ui.access_history.per_page')
            ->first()
            ->update(['value' => '5']);

        $this->seed(ParameterSeeder::class);

        $parameter = Parameter::query()->where('key', 'ui.access_history.per_page')->first();

        $this->assertSame('5', $parameter->value);
        $this->assertSame('Itens por página no histórico de acessos', $parameter->description);
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(ParameterSeeder::class);
        $this->seed(ParameterSeeder::class);

        $this->assertSame(11, Parameter::query()->count());
    }
}
