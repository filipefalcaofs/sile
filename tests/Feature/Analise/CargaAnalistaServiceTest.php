<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\CargaAnalistaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CargaAnalistaServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analistaDoSetor(Sector $sector): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($sector);

        return $analista;
    }

    private function processoAtribuido(Sector $sector, User $analista, AnalysisStatus $status): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmAnalise]);
        $request->forceFill([
            'sector_id' => $sector->id,
            'assigned_user_id' => $analista->id,
            'analysis_status' => $status,
        ])->save();

        return $request;
    }

    public function test_carga_conta_ativos_por_analista_com_breakdown_por_grupo(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $this->processoAtribuido($setor, $analista, AnalysisStatus::EmAnalise);
        $this->processoAtribuido($setor, $analista, AnalysisStatus::Analisar);
        $this->processoAtribuido($setor, $analista, AnalysisStatus::EmConvite);

        $carga = app(CargaAnalistaService::class)->cargaDosSetores([$setor->id]);
        $linha = collect($carga)->firstWhere('analista_id', $analista->id);

        $this->assertSame(3, $linha['total']);
        $this->assertSame(2, $linha['por_grupo']['Análise']);
        $this->assertSame(1, $linha['por_grupo']['Convite']);
    }

    public function test_analista_do_setor_sem_carga_aparece_com_zero(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $carga = app(CargaAnalistaService::class)->cargaDosSetores([$setor->id]);
        $linha = collect($carga)->firstWhere('analista_id', $analista->id);

        $this->assertNotNull($linha, 'Analista vinculado ao setor aparece mesmo sem carga.');
        $this->assertSame(0, $linha['total']);
    }

    public function test_carga_restrita_aos_setores_informados(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $doA = $this->analistaDoSetor($setorA);
        $doB = $this->analistaDoSetor($setorB);

        $carga = app(CargaAnalistaService::class)->cargaDosSetores([$setorA->id]);
        $ids = collect($carga)->pluck('analista_id')->all();

        $this->assertContains($doA->id, $ids);
        $this->assertNotContains($doB->id, $ids, 'Analista de outro setor não entra na carga.');
    }
}
