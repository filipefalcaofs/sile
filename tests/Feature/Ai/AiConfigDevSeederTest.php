<?php

namespace Tests\Feature\Ai;

use App\Models\AiConfiguration;
use App\Services\Ai\AiConfigResolver;
use Database\Seeders\AiConfigDevSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seed de DEV da configuração de IA (Onda 0): exemplo navegável sobre a LÓGICA
 * REAL (cria uma AiConfiguration de verdade e a ponte passa a refleti-la),
 * gateado a `local`, idempotente e sem credencial real exposta.
 */
class AiConfigDevSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_em_local_cria_o_exemplo_padrao_ativo_que_alimenta_a_ponte(): void
    {
        $this->app['env'] = 'local';

        $this->seed(AiConfigDevSeeder::class);

        $configuration = AiConfiguration::query()->where('name', AiConfigDevSeeder::NAME)->first();

        $this->assertNotNull($configuration);
        $this->assertSame('openai', $configuration->provider);
        $this->assertSame('text', $configuration->capability);
        $this->assertTrue($configuration->active);
        $this->assertTrue($configuration->is_default);

        // Credencial placeholder legível só internamente, NUNCA em claro no banco.
        $this->assertSame('sk-DEV-placeholder-nao-real', $configuration->api_key);
        $this->assertNotSame(
            'sk-DEV-placeholder-nao-real',
            DB::table('ai_configurations')->where('id', $configuration->id)->value('api_key'),
        );

        // Lógica real de ponta a ponta: a ponte reflete o exemplo semeado.
        app(AiConfigResolver::class)->apply();
        $this->assertSame('openai', config('ai.default'));
        $this->assertSame('gpt-4o-mini', config('ai.providers.openai.models.text.default'));
    }

    public function test_e_idempotente_e_preserva_credencial_cadastrada_pelo_dev(): void
    {
        $this->app['env'] = 'local';

        $this->seed(AiConfigDevSeeder::class);

        // O dev cadastra uma chave real pela tela.
        AiConfiguration::query()
            ->where('name', AiConfigDevSeeder::NAME)
            ->firstOrFail()
            ->update(['api_key' => 'sk-real-do-dev']);

        $this->seed(AiConfigDevSeeder::class);

        $this->assertSame(1, AiConfiguration::query()->where('name', AiConfigDevSeeder::NAME)->count());
        $this->assertSame(
            'sk-real-do-dev',
            AiConfiguration::query()->where('name', AiConfigDevSeeder::NAME)->firstOrFail()->api_key,
        );
    }

    public function test_fora_de_local_e_no_op(): void
    {
        $this->seed(AiConfigDevSeeder::class);

        $this->assertSame(0, AiConfiguration::query()->count());
    }
}
