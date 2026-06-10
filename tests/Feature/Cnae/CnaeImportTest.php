<?php

namespace Tests\Feature\Cnae;

use App\Models\Activity;
use App\Models\Cnae;
use App\Services\CnaeImportService;
use Database\Seeders\CnaeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CnaeImportTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICIAL_CSV_HEADER = 'section_code,section_description,division_code,division_description,group_code,group_description,class_code,class_description,subclass_code,subclass_description';

    public function test_importa_as_1331_subclasses_oficiais(): void
    {
        $report = app(CnaeImportService::class)->import(database_path('data/cnaes-subclasses-2-3.csv'));

        $this->assertSame(1331, Cnae::count());
        $this->assertSame(1331, $report['lidos']);
        $this->assertSame(1331, $report['importados']);
        $this->assertSame(0, $report['atualizados']);
        $this->assertSame([], $report['rejeitados']);
    }

    public function test_normaliza_codigo_e_preserva_hierarquia(): void
    {
        app(CnaeImportService::class)->import(database_path('data/cnaes-subclasses-2-3.csv'));

        $cnae = Cnae::where('code', '0111301')->first();

        $this->assertNotNull($cnae, 'Esperava a subclasse 0111-3/01 importada com código normalizado');
        $this->assertSame('Cultivo de arroz', $cnae->description);
        $this->assertSame('01.11-3', $cnae->class_code);
        $this->assertSame('A', $cnae->section_code);
        $this->assertTrue($cnae->active);
        $this->assertSame('0111-3/01', $cnae->formatted_code);
    }

    public function test_import_e_idempotente(): void
    {
        $service = app(CnaeImportService::class);
        $csvPath = database_path('data/cnaes-subclasses-2-3.csv');

        $service->import($csvPath);
        $report = $service->import($csvPath);

        $this->assertSame(1331, Cnae::count());
        $this->assertSame(0, $report['importados']);
        $this->assertSame(1331, $report['atualizados']);
    }

    public function test_relatorio_registra_divergencia_da_publicacao_oficial(): void
    {
        $report = app(CnaeImportService::class)->import(database_path('data/cnaes-subclasses-2-3.csv'));

        $this->assertSame(1332, $report['esperado_publicacao']);
        $this->assertArrayHasKey('9900-8/00', $report['divergencia']);
        $this->assertFalse(
            Cnae::where('code', '9900800')->exists(),
            'A subclasse ausente do arquivo oficial não pode ser inserida silenciosamente',
        );
    }

    public function test_linha_malformada_e_rejeitada_sem_abortar_o_import(): void
    {
        $csvPath = tempnam(sys_get_temp_dir(), 'cnae-test-');

        file_put_contents($csvPath, implode("\n", [
            self::OFFICIAL_CSV_HEADER,
            'A,"AGRICULTURA, PECUÁRIA, PRODUÇÃO FLORESTAL, PESCA E AQUICULTURA",01,"AGRICULTURA, PECUÁRIA E SERVIÇOS RELACIONADOS",01.1,Produção de lavouras temporárias,01.11-3,Cultivo de cereais,0111-3/01,Cultivo de arroz',
            'A,"AGRICULTURA, PECUÁRIA, PRODUÇÃO FLORESTAL, PESCA E AQUICULTURA",01,"AGRICULTURA, PECUÁRIA E SERVIÇOS RELACIONADOS",01.1,Produção de lavouras temporárias,01.11-3,Cultivo de cereais,XXXX,Linha malformada de teste',
        ]));

        try {
            $report = app(CnaeImportService::class)->import($csvPath);
        } finally {
            unlink($csvPath);
        }

        $this->assertCount(1, $report['rejeitados']);
        $this->assertStringContainsString('XXXX', $report['rejeitados'][0]);
        $this->assertSame(1, Cnae::count());
        $this->assertSame(1, $report['importados']);
    }

    public function test_seeder_executa_import_e_audita_o_relatorio(): void
    {
        $this->seed(CnaeSeeder::class);

        $this->assertSame(1331, Cnae::count());

        $activity = Activity::where('log_name', 'cnaes')
            ->where('event', 'importacao-oficial')
            ->first();

        $this->assertNotNull($activity, 'Esperava o relatório do import registrado na auditoria');
        $this->assertSame('cnae-subclasses-2.3', $activity->rules_version);
        $this->assertArrayHasKey('9900-8/00', $activity->properties->get('divergencia', []));
    }
}
