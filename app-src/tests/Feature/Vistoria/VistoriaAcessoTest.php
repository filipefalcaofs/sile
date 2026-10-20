<?php

namespace Tests\Feature\Vistoria;

use App\Enums\AnalysisStatus;
use App\Models\Inspection;
use App\Models\InspectionAttachment;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Acesso direto às telas de vistoria: o vistoriador só abre o que é dele, quem
 * analisou o processo consulta a ficha de vistoria e quem distribui vê tudo.
 */
class VistoriaAcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $responsavel;

    private User $outroVistoriador;

    private User $analistaQueEncaminhou;

    private ViabilityRequest $processo;

    private InspectionAttachment $anexo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->responsavel = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $this->outroVistoriador = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $this->analistaQueEncaminhou = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->processo = ViabilityRequest::factory()->create();
        $this->processo->forceFill([
            'analysis_status' => AnalysisStatus::Vistoriar,
            'assigned_user_id' => $this->responsavel->id,
        ])->save();
        $this->processo->analysisStatusTransitions()->create([
            'from_status' => AnalysisStatus::EmAnalise,
            'to_status' => AnalysisStatus::Vistoriar,
            'actor_user_id' => $this->analistaQueEncaminhou->id,
        ]);

        $ficha = Inspection::factory()->create([
            'viability_request_id' => $this->processo->id,
            'vistoriador_user_id' => $this->responsavel->id,
        ]);
        $this->anexo = InspectionAttachment::factory()->create(['inspection_id' => $ficha->id]);
        Storage::disk('local')->put($this->anexo->path, 'conteudo');
    }

    public function test_outro_vistoriador_recebe_403_nas_rotas_diretas(): void
    {
        $id = $this->processo->id;
        $this->actingAs($this->outroVistoriador, 'gestao');

        $this->get("/gestao/vistorias/{$id}/consulta")->assertForbidden();
        $this->get("/gestao/vistorias/{$id}/consulta/imprimir")->assertForbidden();
        $this->get("/gestao/processos/{$id}/vistoria")->assertForbidden();
        $this->get("/gestao/processos/{$id}/vistoria/visualizar")->assertForbidden();
        $this->get("/gestao/processos/{$id}/vistoria/anexos/{$this->anexo->id}")->assertForbidden();
    }

    public function test_vistoriador_responsavel_abre_a_ficha_e_a_capa(): void
    {
        $id = $this->processo->id;
        $this->actingAs($this->responsavel, 'gestao');

        $this->get("/gestao/processos/{$id}/vistoria")->assertOk();
        $this->get("/gestao/vistorias/{$id}/consulta")->assertOk();
    }

    public function test_analista_que_encaminhou_visualiza_a_ficha_de_vistoria(): void
    {
        $id = $this->processo->id;
        $this->actingAs($this->analistaQueEncaminhou, 'gestao');

        $this->get("/gestao/processos/{$id}/vistoria/visualizar")->assertOk();
        $this->get("/gestao/processos/{$id}/vistoria")->assertForbidden();
    }
}
