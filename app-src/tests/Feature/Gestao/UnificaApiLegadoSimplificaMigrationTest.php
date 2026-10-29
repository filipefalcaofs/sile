<?php

namespace Tests\Feature\Gestao;

use App\Models\Parameter;
use App\Support\Settings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A comparação de atividades passou a usar a conexão da aba Simplifica (legado):
 * a migration leva para ela o que estava nos parâmetros antigos, sem sobrescrever.
 */
class UnificaApiLegadoSimplificaMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function antigo(string $chave, ?string $valor, bool $sensivel = false): void
    {
        DB::table('parameters')->insert([
            'key' => $chave,
            'group' => 'integracoes',
            'governance' => 'operational',
            'type' => 'string',
            'value' => $valor !== null && $sensivel ? Crypt::encryptString($valor) : $valor,
            'default_value' => null,
            'validation_rules' => json_encode(['nullable', 'string']),
            'sensitive' => $sensivel,
            'description' => 'antigo',
            'requires_connection_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rodar(): void
    {
        $migration = require database_path('migrations/2026_10_29_150000_unifica_api_legado_simplifica.php');
        $migration->up();
        Cache::flush();
    }

    public function test_leva_url_e_token_antigos_para_a_aba_unica_e_remove_os_antigos(): void
    {
        $this->antigo('integrations.legado_simplifica.url', 'http://simplifica-legado.test');
        $this->antigo('integrations.legado_simplifica.token', 'lg_token_antigo', true);

        $this->rodar();

        $this->assertSame('http://simplifica-legado.test', Parameter::query()->where('key', 'integrations.legado_api.url_homologacao')->value('value'));
        $this->assertSame('http://simplifica-legado.test', Parameter::query()->where('key', 'integrations.legado_api.base_url')->value('value'));
        $this->assertSame('lg_token_antigo', Parameter::query()->where('key', 'integrations.legado_api.token')->first()->value);
        $this->assertFalse(DB::table('parameters')->where('key', 'like', 'integrations.legado_simplifica.%')->exists());
    }

    public function test_usa_a_url_de_producao_quando_o_interruptor_esta_em_producao(): void
    {
        Parameter::query()->where('key', 'integrations.legado_api.em_producao')->update(['value' => '1']);
        $this->antigo('integrations.legado_simplifica.url', 'http://simplifica-prd.test');

        $this->rodar();

        $this->assertSame('http://simplifica-prd.test', Parameter::query()->where('key', 'integrations.legado_api.url_producao')->value('value'));
        $this->assertNull(Parameter::query()->where('key', 'integrations.legado_api.url_homologacao')->value('value'));
    }

    public function test_nao_sobrescreve_o_que_a_aba_unica_ja_tem(): void
    {
        Parameter::query()->where('key', 'integrations.legado_api.url_homologacao')->update(['value' => 'https://ja-configurada.test']);
        $this->antigo('integrations.legado_simplifica.url', 'http://simplifica-legado.test');

        $this->rodar();

        $this->assertSame('https://ja-configurada.test', Parameter::query()->where('key', 'integrations.legado_api.url_homologacao')->value('value'));
        $this->assertFalse(DB::table('parameters')->where('key', 'integrations.legado_simplifica.url')->exists());
    }

    public function test_sem_parametros_antigos_nao_faz_nada(): void
    {
        $this->rodar();

        $this->assertSame('', trim((string) Settings::get('integrations.legado_api.token', '')));
    }
}
