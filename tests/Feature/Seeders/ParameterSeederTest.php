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

        $this->assertSame(31, Parameter::query()->count());
        $this->assertSame(
            ['features', 'geo', 'integracoes', 'retencao', 'risco', 'seguranca', 'ui'],
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

    public function test_seeder_registra_parametros_da_fundacao_assincrona(): void
    {
        $this->seed(ParameterSeeder::class);

        $retencao = Parameter::query()->where('key', 'retencao.access_logs.dias')->first();

        $this->assertNotNull($retencao);
        $this->assertSame('retencao', $retencao->group);
        $this->assertSame('integer', $retencao->type);
        $this->assertSame('365', $retencao->default_value);
        $this->assertSame(['required', 'integer', 'min:30', 'max:3650'], $retencao->validation_rules);
        $this->assertNull($retencao->value);

        $throttle = Parameter::query()->where('key', 'seguranca.throttle.cnpj_lookup.por_minuto')->first();

        $this->assertNotNull($throttle);
        $this->assertSame('seguranca', $throttle->group);
        $this->assertSame('integer', $throttle->type);
        $this->assertSame('30', $throttle->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:300'], $throttle->validation_rules);

        $retries = Parameter::query()->where('key', 'integrations.cnpj_lookup.retries')->first();

        $this->assertNotNull($retries);
        $this->assertSame('integracoes', $retries->group);
        $this->assertSame('integer', $retries->type);
        $this->assertSame('2', $retries->default_value);
        $this->assertSame(['required', 'integer', 'min:0', 'max:5'], $retries->validation_rules);

        $timeout = Parameter::query()->where('key', 'integrations.cnpj_lookup.timeout')->first();

        $this->assertNotNull($timeout);
        $this->assertSame('integracoes', $timeout->group);
        $this->assertSame('integer', $timeout->type);
        $this->assertSame('8', $timeout->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:30'], $timeout->validation_rules);

        $backoff = Parameter::query()->where('key', 'integrations.cnpj_lookup.backoff_ms')->first();

        $this->assertNotNull($backoff);
        $this->assertSame('integracoes', $backoff->group);
        $this->assertSame('integer', $backoff->type);
        $this->assertSame('200', $backoff->default_value);
        $this->assertSame(['required', 'integer', 'min:0', 'max:5000'], $backoff->validation_rules);
    }

    public function test_seeder_registra_parametros_do_login_govbr(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.govbr_login')->first();

        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('0', $toggle->default_value);
        $this->assertFalse($toggle->sensitive);

        $baseUrl = Parameter::query()->where('key', 'integrations.govbr.base_url')->first();

        $this->assertNotNull($baseUrl);
        $this->assertSame('integracoes', $baseUrl->group);
        $this->assertSame('https://sso.staging.acesso.gov.br', $baseUrl->default_value);
        $this->assertTrue($baseUrl->requires_connection_test);

        $clientId = Parameter::query()->where('key', 'integrations.govbr.client_id')->first();

        $this->assertNotNull($clientId);
        $this->assertTrue($clientId->sensitive);
        $this->assertNull($clientId->default_value);

        $clientSecret = Parameter::query()->where('key', 'integrations.govbr.client_secret')->first();

        $this->assertNotNull($clientSecret);
        $this->assertTrue($clientSecret->sensitive);
        $this->assertNull($clientSecret->default_value);

        $minimumLevel = Parameter::query()->where('key', 'security.govbr.minimum_level')->first();

        $this->assertNotNull($minimumLevel);
        $this->assertSame('seguranca', $minimumLevel->group);
        $this->assertSame('bronze', $minimumLevel->default_value);
        $this->assertSame(['required', 'in:bronze,prata,ouro'], $minimumLevel->validation_rules);
    }

    public function test_seeder_registra_parametros_do_georreferenciamento(): void
    {
        $this->seed(ParameterSeeder::class);

        $toggle = Parameter::query()->where('key', 'features.geocoding')->first();

        $this->assertNotNull($toggle);
        $this->assertSame('features', $toggle->group);
        $this->assertSame('boolean', $toggle->type);
        $this->assertSame('1', $toggle->default_value);
        $this->assertSame(['required', 'boolean'], $toggle->validation_rules);
        $this->assertNull($toggle->value);

        $baseUrl = Parameter::query()->where('key', 'integrations.geocoding.base_url')->first();

        $this->assertNotNull($baseUrl);
        $this->assertSame('integracoes', $baseUrl->group);
        $this->assertSame('string', $baseUrl->type);
        $this->assertSame('https://nominatim.openstreetmap.org', $baseUrl->default_value);
        $this->assertSame(['required', 'url'], $baseUrl->validation_rules);
        $this->assertTrue($baseUrl->requires_connection_test);

        $throttle = Parameter::query()->where('key', 'seguranca.throttle.geocoding.por_minuto')->first();

        $this->assertNotNull($throttle);
        $this->assertSame('seguranca', $throttle->group);
        $this->assertSame('integer', $throttle->type);
        $this->assertSame('60', $throttle->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:300'], $throttle->validation_rules);

        $sobreposicao = Parameter::query()->where('key', 'geo.validacao.sobreposicao_minima')->first();

        $this->assertNotNull($sobreposicao);
        $this->assertSame('geo', $sobreposicao->group);
        $this->assertSame('integer', $sobreposicao->type);
        $this->assertSame('50', $sobreposicao->default_value);
        $this->assertSame(['required', 'integer', 'min:1', 'max:100'], $sobreposicao->validation_rules);
        $this->assertNull($sobreposicao->value);
    }

    public function test_seeder_registra_parametro_da_listagem_de_emails(): void
    {
        $this->seed(ParameterSeeder::class);

        $perPage = Parameter::query()->where('key', 'ui.email_logs.per_page')->first();

        $this->assertNotNull($perPage);
        $this->assertSame('ui', $perPage->group);
        $this->assertSame('integer', $perPage->type);
        $this->assertSame('20', $perPage->default_value);
        $this->assertSame(['required', 'integer', 'min:5', 'max:100'], $perPage->validation_rules);
    }

    public function test_seeder_mantem_flag_sensivel_em_reseed(): void
    {
        $this->seed(ParameterSeeder::class);
        $this->seed(ParameterSeeder::class);

        $clientSecret = Parameter::query()->where('key', 'integrations.govbr.client_secret')->first();

        $this->assertTrue($clientSecret->sensitive);
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

    public function test_seeder_registra_parametros_de_encaminhamento_de_risco(): void
    {
        $this->seed(ParameterSeeder::class);

        $mapa = Parameter::query()->where('key', 'risco.mapa_encaminhamento')->first();

        $this->assertNotNull($mapa);
        $this->assertSame('risco', $mapa->group);
        $this->assertSame('json', $mapa->type);
        $this->assertStringContainsString('baixo_a', $mapa->default_value);
        $this->assertStringContainsString('expresso', $mapa->default_value);
        $this->assertStringContainsString('alto', $mapa->default_value);
        $this->assertStringContainsString('analise', $mapa->default_value);
        $this->assertSame(['required', 'json'], $mapa->validation_rules);
        $this->assertNull($mapa->value);

        // O parâmetro json é decodificado para array em typedValue() — o
        // consumidor (motor 06-05) sempre recebe o mapa como array.
        $this->assertSame(
            ['baixo_a' => 'expresso', 'baixo_b' => 'expresso', 'alto' => 'analise'],
            $mapa->typedValue(),
        );

        $dimensao = Parameter::query()->where('key', 'risco.dimensao_tvl')->first();

        $this->assertNotNull($dimensao);
        $this->assertSame('risco', $dimensao->group);
        $this->assertSame('string', $dimensao->type);
        $this->assertSame('municipal', $dimensao->default_value);
        $this->assertSame(['required', 'in:municipal,sanitario'], $dimensao->validation_rules);
        $this->assertNull($dimensao->value);
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(ParameterSeeder::class);
        $this->seed(ParameterSeeder::class);

        $this->assertSame(31, Parameter::query()->count());
    }
}
