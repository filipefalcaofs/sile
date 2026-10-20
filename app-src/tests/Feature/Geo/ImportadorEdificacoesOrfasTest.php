<?php

namespace Tests\Feature\Geo;

use App\Enums\FonteEdificacao;
use App\Models\BuildingFootprintImport;
use App\Services\Geo\Edificacoes\ImportadorEdificacoes;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Geo\FakeBuildingFootprintRepository;
use Tests\TestCase;

class ImportadorEdificacoesOrfasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_nova_carga_encerra_a_orfa_antiga_da_mesma_fonte(): void
    {
        $orfa = $this->emAndamento(FonteEdificacao::Overture, horasAtras: 7);

        $this->importar(FonteEdificacao::Overture);

        $orfa->refresh();
        $this->assertSame(BuildingFootprintImport::FALHOU, $orfa->status);
        $this->assertNotNull($orfa->finished_at);
        $this->assertStringContainsString('interrompida', (string) $orfa->error);
    }

    public function test_carga_recente_e_de_outra_fonte_continuam_em_andamento(): void
    {
        $recente = $this->emAndamento(FonteEdificacao::Overture, horasAtras: 1);
        $outraFonte = $this->emAndamento(FonteEdificacao::Microsoft, horasAtras: 30);

        $this->importar(FonteEdificacao::Overture);

        $this->assertSame(BuildingFootprintImport::EM_ANDAMENTO, $recente->refresh()->status);
        $this->assertSame(BuildingFootprintImport::EM_ANDAMENTO, $outraFonte->refresh()->status);
    }

    private function emAndamento(FonteEdificacao $fonte, int $horasAtras): BuildingFootprintImport
    {
        return BuildingFootprintImport::factory()->create([
            'source' => $fonte,
            'status' => BuildingFootprintImport::EM_ANDAMENTO,
            'started_at' => now()->subHours($horasAtras),
            'finished_at' => null,
        ]);
    }

    private function importar(FonteEdificacao $fonte): BuildingFootprintImport
    {
        $importador = new ImportadorEdificacoes(new FakeBuildingFootprintRepository);

        return $importador->importar($fonte, 'release-nova', hash('sha256', 'nova'), [], fn (array $feicao): ?array => null);
    }
}
