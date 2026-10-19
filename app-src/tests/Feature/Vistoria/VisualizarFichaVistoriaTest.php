<?php

namespace Tests\Feature\Vistoria;

use App\Models\Inspection;
use App\Models\InspectionAttachment;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Consulta da ficha de vistoria a partir da análise: não cria ficha, não edita.
 * A edição continua gated por preencher-ficha-vistoria.
 */
class VisualizarFichaVistoriaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analistaConsulta(): User
    {
        $analista = User::factory()->withAcceptedLgpdTerm()->create();
        $analista->givePermissionTo(['acessar-gestao', 'analisar-processos']);

        return $analista;
    }

    private function vistoriador(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_sem_ficha_informa_ausencia_e_nao_cria_registro(): void
    {
        $analista = $this->analistaConsulta();
        $processo = ViabilityRequest::factory()->create();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria/visualizar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/vistoria/show')
                ->where('ausente', true)
                ->where('somenteLeitura', true)
                ->where('ficha', null));

        $this->assertSame(0, Inspection::query()->count());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'vistoria',
            'event' => 'ficha-visualizacao-ausente',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
        ]);
    }

    public function test_com_ficha_exibe_somente_leitura_sem_alterar_a_autoria(): void
    {
        $analista = $this->analistaConsulta();
        $vistoriador = $this->vistoriador();
        $processo = ViabilityRequest::factory()->create();
        $ficha = Inspection::factory()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vistoriador->id,
            'parecer' => 'Parecer de campo.',
        ]);

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria/visualizar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/vistoria/show')
                ->where('ausente', false)
                ->where('somenteLeitura', true)
                ->where('ficha.id', $ficha->id)
                ->where('ficha.parecer', 'Parecer de campo.')
                ->has('anexos'));

        $this->assertSame(1, Inspection::query()->count());
        $this->assertSame($vistoriador->id, $ficha->fresh()->vistoriador_user_id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'vistoria',
            'event' => 'ficha-visualizacao',
            'subject_type' => $ficha->getMorphClass(),
            'subject_id' => $ficha->id,
        ]);
    }

    public function test_quem_preenche_tambem_consulta_sem_modo_de_edicao(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = ViabilityRequest::factory()->create();
        $ficha = Inspection::factory()->create([
            'viability_request_id' => $processo->id,
            'vistoriador_user_id' => $vistoriador->id,
        ]);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria/visualizar")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('somenteLeitura', true)
                ->where('ficha.id', $ficha->id));
    }

    public function test_sem_permissao_de_analise_nao_visualiza(): void
    {
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();
        Inspection::factory()->create(['viability_request_id' => $processo->id]);

        $this->actingAs($apoio, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria/visualizar")
            ->assertForbidden();
    }

    public function test_consulta_nao_autoriza_alterar_a_ficha(): void
    {
        $analista = $this->analistaConsulta();
        $processo = ViabilityRequest::factory()->create();
        Inspection::factory()->create(['viability_request_id' => $processo->id]);

        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/vistoria", [
                'observacoes' => 'Tentativa de edição pela consulta.',
            ])
            ->assertForbidden();
    }

    public function test_analista_baixa_anexo_sem_permissao_de_preencher(): void
    {
        Storage::fake('local');

        $vistoriador = $this->vistoriador();
        $analista = $this->analistaConsulta();
        $processo = ViabilityRequest::factory()->create(['assigned_user_id' => $vistoriador->id]);

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/anexos", [
                'file' => UploadedFile::fake()->image('fachada.jpg'),
            ])
            ->assertRedirect();

        $anexo = InspectionAttachment::query()->sole();

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria/anexos/{$anexo->id}")
            ->assertOk();
    }

    public function test_ficha_de_analise_informa_se_existe_vistoria(): void
    {
        $analista = $this->analistaConsulta();
        $semFicha = ViabilityRequest::factory()->create();
        $comFicha = ViabilityRequest::factory()->create();
        Inspection::factory()->create(['viability_request_id' => $comFicha->id]);

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$semFicha->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('temFichaVistoria', false));

        $this->actingAs($analista, 'gestao')
            ->get("/gestao/processos/{$comFicha->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('temFichaVistoria', true));
    }
}
