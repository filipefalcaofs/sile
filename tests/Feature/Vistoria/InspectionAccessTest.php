<?php

namespace Tests\Feature\Vistoria;

use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionAttachment;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Autoria e integração de status da ficha de vistoria: quem abre a ficha é o
 * vistoriador (auto-atribuição quando o processo não tem responsável); com
 * responsável atribuído, só ELE abre/edita — os demais leem. Concluir a ficha
 * de um processo em vistoria (Vistoriar) transiciona o eixo operacional para
 * Vistoriado pela state machine (auditado); fora de Vistoriar, o status não
 * é tocado.
 */
class InspectionAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function vistoriador(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_concluir_ficha_em_processo_em_vistoria_transiciona_para_vistoriado(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill(['analysis_status' => AnalysisStatus::Vistoriar])->save();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer conclusivo da vistoria.'])
            ->assertOk();

        $this->assertSame(AnalysisStatus::Vistoriado, $processo->refresh()->analysis_status);
        $this->assertTrue(
            $processo->analysisStatusTransitions()
                ->where('to_status', AnalysisStatus::Vistoriado->value)
                ->where('actor_user_id', $vistoriador->id)
                ->exists()
        );
    }

    public function test_concluir_ficha_fora_de_vistoriar_nao_toca_no_status(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill(['analysis_status' => AnalysisStatus::EmAnalise])->save();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer conclusivo da vistoria.'])
            ->assertOk();

        $this->assertSame(AnalysisStatus::EmAnalise, $processo->refresh()->analysis_status);
    }

    public function test_abertura_com_processo_atribuido_a_outro_e_recusada(): void
    {
        $responsavel = $this->vistoriador();
        $intruso = $this->vistoriador();

        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill(['assigned_user_id' => $responsavel->id])->save();

        $this->actingAs($intruso, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertForbidden();

        $this->assertSame(0, Inspection::query()->count());
    }

    public function test_abertura_pelo_responsavel_atribuido_cria_a_ficha(): void
    {
        $responsavel = $this->vistoriador();

        $processo = ViabilityRequest::factory()->create();
        $processo->forceFill(['assigned_user_id' => $responsavel->id])->save();

        $this->actingAs($responsavel, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertOk();

        $this->assertSame($responsavel->id, Inspection::query()->sole()->vistoriador_user_id);
    }

    public function test_quem_nao_e_o_vistoriador_le_mas_nao_edita(): void
    {
        $vistoriador = $this->vistoriador();
        $outro = $this->vistoriador();

        $processo = ViabilityRequest::factory()->create();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        // Leitura permitida, mas somente leitura (editavel=false).
        $this->actingAs($outro, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('ficha.editavel', false));

        $this->actingAs($outro, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/vistoria", ['observacoes' => 'edição alheia'])
            ->assertForbidden();

        $this->actingAs($outro, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer de terceiro.'])
            ->assertForbidden();

        $this->assertNull(Inspection::query()->sole()->observacoes);
        $this->assertSame(InspectionStatus::EmPreenchimento, Inspection::query()->sole()->status);
    }

    public function test_anexo_so_o_vistoriador_envia(): void
    {
        Storage::fake('local');

        $vistoriador = $this->vistoriador();
        $outro = $this->vistoriador();

        $processo = ViabilityRequest::factory()->create();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($outro, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/anexos", [
                'file' => UploadedFile::fake()->image('fachada.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame(0, InspectionAttachment::query()->count());
    }
}
