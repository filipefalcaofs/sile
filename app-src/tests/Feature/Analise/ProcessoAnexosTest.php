<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\Inspection;
use App\Models\InspectionAttachment;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Lisa, 22/10: todos os anexos do processo ficam visíveis para quem consulta o
 * processo, como parte do histórico.
 */
class ProcessoAnexosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    /**
     * @return array{processo: ViabilityRequest, documento: ViabilityRequestDocument, anexo: InspectionAttachment}
     */
    private function cenario(): array
    {
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $documento = ViabilityRequestDocument::factory()->create([
            'viability_request_id' => $processo->id,
            'original_name' => 'contrato-locacao.pdf',
            'path' => 'solicitacoes/contrato.pdf',
        ]);
        Storage::disk('local')->put('solicitacoes/contrato.pdf', 'conteudo-contrato');

        $ficha = Inspection::factory()->concluida()->create(['viability_request_id' => $processo->id]);
        $anexo = InspectionAttachment::factory()->create([
            'inspection_id' => $ficha->id,
            'original_name' => 'fachada.jpg',
            'path' => 'vistorias/fachada.jpg',
        ]);
        Storage::disk('local')->put('vistorias/fachada.jpg', 'foto');

        return compact('processo', 'documento', 'anexo');
    }

    private function consultor(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_tela_do_processo_lista_documentos_e_anexos_da_vistoria(): void
    {
        ['processo' => $processo] = $this->cenario();

        $this->actingAs($this->consultor(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('anexos', 2)
                ->where('anexos.0.nome', 'contrato-locacao.pdf')
                ->where('anexos.0.origem', 'Documento do processo')
                ->where('anexos.1.nome', 'fachada.jpg')
                ->where('anexos.1.origem', 'Ficha de vistoria'));
    }

    public function test_quem_consulta_baixa_documento_e_anexo_da_vistoria(): void
    {
        ['processo' => $processo, 'documento' => $documento, 'anexo' => $anexo] = $this->cenario();
        $consultor = $this->consultor();

        $this->actingAs($consultor, 'gestao')
            ->get("/gestao/processos/{$processo->id}/anexos/documento/{$documento->id}")
            ->assertOk()
            ->assertDownload('contrato-locacao.pdf');

        $this->actingAs($consultor, 'gestao')
            ->get("/gestao/processos/{$processo->id}/anexos/vistoria/{$anexo->id}")
            ->assertOk()
            ->assertDownload('fachada.jpg');
    }

    public function test_anexo_de_outro_processo_nao_e_baixado(): void
    {
        ['documento' => $documento] = $this->cenario();
        $outro = ViabilityRequest::factory()->create();

        $this->actingAs($this->consultor(), 'gestao')
            ->get("/gestao/processos/{$outro->id}/anexos/documento/{$documento->id}")
            ->assertNotFound();
    }
}
