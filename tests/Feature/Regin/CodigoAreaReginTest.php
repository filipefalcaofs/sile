<?php

namespace Tests\Feature\Regin;

use App\Models\Parameter;
use App\Services\Regin\ReginIntegrationSettings;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * CODIGO_AREA do parecer: a área da viabilidade de Salvador no REGIN é a 70.
 * O 1 do exemplo do guia da JUCEB deixava o parecer sem área e o protocolo
 * pendente no acompanhamento do REGIN (BAB2601852710).
 */
class CodigoAreaReginTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CHAVE = 'integrations.regin.codigo_area';

    private function migrar(): void
    {
        (require database_path('migrations/2026_10_22_100000_corrige_codigo_area_regin.php'))->up();
        Cache::forget('viabiliza.parameters.'.self::CHAVE);
    }

    public function test_seed_nasce_com_a_area_70(): void
    {
        $this->seed(ParameterSeeder::class);
        Cache::forget('viabiliza.parameters.'.self::CHAVE);

        $this->assertSame('70', Parameter::query()->where('key', self::CHAVE)->value('default_value'));
        $this->assertSame(70, app(ReginIntegrationSettings::class)->codigoArea());
    }

    public function test_sem_parametro_no_banco_usa_a_area_70(): void
    {
        Parameter::query()->where('key', self::CHAVE)->delete();
        Cache::forget('viabiliza.parameters.'.self::CHAVE);

        $this->assertSame(70, app(ReginIntegrationSettings::class)->codigoArea());
    }

    public function test_migration_troca_o_1_gravado_por_70(): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', self::CHAVE)->update(['value' => '1', 'default_value' => '1']);

        $this->migrar();

        $parametro = Parameter::query()->where('key', self::CHAVE)->first();
        $this->assertSame('70', $parametro->value);
        $this->assertSame('70', $parametro->default_value);
        $this->assertSame(70, app(ReginIntegrationSettings::class)->codigoArea());
    }

    public function test_migration_preserva_area_que_o_gestor_gravou(): void
    {
        $this->seed(ParameterSeeder::class);
        Parameter::query()->where('key', self::CHAVE)->update(['value' => '85', 'default_value' => '1']);

        $this->migrar();

        $parametro = Parameter::query()->where('key', self::CHAVE)->first();
        $this->assertSame('85', $parametro->value);
        $this->assertSame('70', $parametro->default_value);
    }

    public function test_migration_sem_o_parametro_nao_falha(): void
    {
        Parameter::query()->where('key', self::CHAVE)->delete();

        $this->migrar();

        $this->assertFalse(Parameter::query()->where('key', self::CHAVE)->exists());
    }
}
