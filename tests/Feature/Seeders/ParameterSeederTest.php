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

        $this->assertSame(14, Parameter::query()->count());
        $this->assertSame(
            ['features', 'integracoes', 'seguranca', 'ui'],
            Parameter::query()->distinct()->orderBy('group')->pluck('group')->all(),
        );

        $maxAttempts = Parameter::query()->where('key', 'security.login.max_attempts')->first();

        $this->assertNotNull($maxAttempts);
        $this->assertSame('integer', $maxAttempts->type);
        $this->assertSame('5', $maxAttempts->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:20'], $maxAttempts->validation_rules);
        $this->assertNull($maxAttempts->value);
    }

    public function test_seeder_registra_parametros_do_cadastro_empresarial(): void
    {
        $this->seed(ParameterSeeder::class);

        $cnpjLookup = Parameter::query()->where('key', 'features.cnpj_lookup')->first();

        $this->assertNotNull($cnpjLookup);
        $this->assertSame('features', $cnpjLookup->group);
        $this->assertSame('boolean', $cnpjLookup->type);
        $this->assertSame('1', $cnpjLookup->default_value);
        $this->assertSame(['required', 'boolean'], $cnpjLookup->validation_rules);

        $baseUrl = Parameter::query()->where('key', 'integrations.cnpj_lookup.base_url')->first();

        $this->assertNotNull($baseUrl);
        $this->assertSame('integracoes', $baseUrl->group);
        $this->assertSame('string', $baseUrl->type);
        $this->assertSame('https://brasilapi.com.br/api/cnpj/v1', $baseUrl->default_value);
        $this->assertSame(['required', 'url'], $baseUrl->validation_rules);
        $this->assertTrue($baseUrl->requires_connection_test);

        $perPage = Parameter::query()->where('key', 'ui.companies.per_page')->first();

        $this->assertNotNull($perPage);
        $this->assertSame('ui', $perPage->group);
        $this->assertSame('integer', $perPage->type);
        $this->assertSame('15', $perPage->default_value);
        $this->assertSame(['required', 'integer', 'min:5', 'max:100'], $perPage->validation_rules);
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

        $this->assertSame(14, Parameter::query()->count());
    }
}
