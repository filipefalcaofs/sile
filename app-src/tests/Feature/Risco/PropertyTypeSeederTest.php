<?php

namespace Tests\Feature\Risco;

use App\Models\PropertyType;
use App\Models\PropertyTypeAlias;
use Database\Seeders\PropertyTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O seed replica fielmente o catálogo SEDUR 2026-08-26 hoje embutido em
 * TipoImovelCatalog::sedur200826() — migração sem mudança de comportamento.
 */
class PropertyTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_cria_os_cinco_tipos_com_as_flags_corretas(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $dirigem = PropertyType::query()->where('drives_rule', true)->pluck('code')->sort()->values()->all();
        $comum = PropertyType::query()->where('drives_rule', false)->pluck('code')->sort()->values()->all();

        $this->assertSame(['container', 'edificacao_residencial', 'galpao'], $dirigem);
        $this->assertSame(['edificacao_comercial', 'sala'], $comum);
        $this->assertSame(5, PropertyType::query()->where('active', true)->count());
    }

    public function test_aliases_sao_normalizados_na_gravacao(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $galpao = PropertyType::query()->where('code', 'galpao')->firstOrFail();

        $this->assertTrue($galpao->aliases()->where('alias', 'galpao')->exists());
        // Alias acentuado gravado via model sai normalizado (regra do REGIN).
        $galpao->aliases()->create(['alias' => 'GALPÃO INDUSTRIAL']);
        $this->assertTrue($galpao->aliases()->where('alias', 'galpao industrial')->exists());
    }

    public function test_seed_e_idempotente(): void
    {
        $this->seed(PropertyTypeSeeder::class);
        $this->seed(PropertyTypeSeeder::class);

        $this->assertSame(5, PropertyType::query()->count());
        $this->assertSame(5, PropertyTypeAlias::query()->count());
    }

    public function test_alias_e_unico_entre_tipos(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $sala = PropertyType::query()->where('code', 'sala')->firstOrFail();

        $this->expectException(QueryException::class);
        $sala->aliases()->create(['alias' => 'galpao']);
    }
}
