<?php

namespace Tests\Feature\Seeders;

use App\Models\Sector;
use App\Models\TratamentoCondicionante;
use App\Support\CatalogoOficialSchema;
use Database\Seeders\TratamentoRegrasSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogoOficialSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_schema_esta_pronto_apos_todas_as_migrations(): void
    {
        $this->assertTrue(CatalogoOficialSchema::pronto());
    }

    public function test_schema_nao_esta_pronto_sem_tabela_de_condicionantes(): void
    {
        Schema::dropIfExists((new TratamentoCondicionante)->getTable());

        $this->assertFalse(CatalogoOficialSchema::pronto());
    }

    public function test_schema_nao_esta_pronto_sem_coluna_code_em_setores(): void
    {
        $tableName = (new Sector)->getTable();

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        $this->assertFalse(CatalogoOficialSchema::pronto());
    }

    public function test_seeder_de_tratamento_nao_quebra_antes_da_tabela_de_condicionantes(): void
    {
        Schema::dropIfExists((new TratamentoCondicionante)->getTable());

        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertFalse(Schema::hasTable((new TratamentoCondicionante)->getTable()));
    }
}
