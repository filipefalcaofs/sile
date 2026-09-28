<?php

namespace Tests\Feature\Analise;

use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Sector\OfficialSectorCatalogSync;
use Database\Seeders\SectorSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Carga oficial do catálogo de setores (CEIN) a partir do CSV institucional.
 * Idempotente: casa por código/nome, reativa os da planilha e desativa os
 * fora dela quando há dependência (sem apagar vínculo/processo em silêncio).
 */
class OfficialSectorCatalogSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_nao_consulta_code_quando_a_coluna_ainda_nao_existe(): void
    {
        $this->dropSectorCodeColumn();

        $report = app(OfficialSectorCatalogSync::class)->sync();

        $this->assertSame(0, $report['created']);
        $this->assertSame(0, $report['updated']);
        $this->assertSame(0, $report['total']);
        $this->assertFalse(Schema::hasColumn((new Sector)->getTable(), 'code'));
    }

    public function test_seeder_de_setor_nao_quebra_antes_da_coluna_code(): void
    {
        $this->dropSectorCodeColumn();

        $this->seed(SectorSeeder::class);

        $this->assertFalse(Schema::hasColumn((new Sector)->getTable(), 'code'));
        $this->assertSame(0, Sector::query()->count());
    }

    public function test_carrega_setores_do_csv_oficial_com_codigo_cein(): void
    {
        $report = app(OfficialSectorCatalogSync::class)->sync();

        $this->assertGreaterThan(200, $report['created'] + $report['updated'] + $report['reactivated']);
        $this->assertSame(282, Sector::query()->where('active', true)->count());

        $this->assertDatabaseHas('sectors', [
            'code' => '0231400300',
            'name' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
            'active' => true,
        ]);

        $this->assertDatabaseHas('sectors', [
            'code' => '0904000000',
            'name' => 'SEDUR - SECRETARIA ESTADUAL DE DESENVOLVIMENTO URBANO',
            'active' => true,
        ]);
    }

    public function test_e_idempotente_e_preserva_id_ao_casar_por_nome(): void
    {
        $existente = Sector::factory()->create([
            'name' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
            'active' => true,
        ]);

        app(OfficialSectorCatalogSync::class)->sync();
        app(OfficialSectorCatalogSync::class)->sync();

        $this->assertSame(1, Sector::query()->where('name', $existente->name)->count());
        $this->assertSame($existente->id, Sector::query()->where('code', '0231400300')->value('id'));
        $this->assertSame(282, Sector::query()->where('active', true)->whereNotNull('code')->count());
    }

    public function test_desativa_setor_fora_da_planilha_com_dependencia_sem_apagar(): void
    {
        $legado = Sector::factory()->create([
            'name' => 'Setor Legado Com Caixa',
            'active' => true,
        ]);
        $analista = User::factory()->create();
        $legado->analysts()->attach($analista);

        app(OfficialSectorCatalogSync::class)->sync();

        $legado->refresh();
        $this->assertFalse($legado->active);
        $this->assertDatabaseHas('sector_user', [
            'sector_id' => $legado->id,
            'user_id' => $analista->id,
        ]);
    }

    public function test_desativa_setor_fora_da_planilha_com_processo_vinculado(): void
    {
        $legado = Sector::factory()->create([
            'name' => 'Setor Legado Com Processo',
            'active' => true,
        ]);

        ViabilityRequest::factory()->create(['sector_id' => $legado->id]);

        app(OfficialSectorCatalogSync::class)->sync();

        $this->assertFalse($legado->fresh()->active);
        $this->assertDatabaseHas('viability_requests', ['sector_id' => $legado->id]);
    }

    public function test_reativa_setor_da_planilha_que_estava_inativo(): void
    {
        Sector::factory()->create([
            'code' => '0231400300',
            'name' => 'Nome Antigo Qualquer',
            'active' => false,
        ]);

        app(OfficialSectorCatalogSync::class)->sync();

        $this->assertDatabaseHas('sectors', [
            'code' => '0231400300',
            'name' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
            'active' => true,
        ]);
    }

    public function test_preserva_vinculo_de_usuario_quando_setor_equivalente_permanece(): void
    {
        $setor = Sector::factory()->create([
            'name' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
            'active' => true,
        ]);
        $analista = User::factory()->create();
        $setor->analysts()->attach($analista);

        app(OfficialSectorCatalogSync::class)->sync();

        $this->assertSame(
            1,
            DB::table('sector_user')->where('user_id', $analista->id)->where('sector_id', $setor->id)->count(),
        );
        $this->assertSame('0231400300', $setor->fresh()->code);
    }

    private function dropSectorCodeColumn(): void
    {
        $tableName = (new Sector)->getTable();

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
}
