<?php

namespace Tests\Feature\Vistoria;

use App\Enums\InspectionStatus;
use App\Models\Activity;
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
 * Ficha de Vistoria do processo: abertura com identificação automática
 * (tipo, data, vistoriador), snapshot da localização, rascunho parcial,
 * parecer OBRIGATÓRIO na conclusão, imutabilidade após concluir, redesenho
 * do polígono com persistência e anexos com streaming autenticado.
 * Tudo gated por preencher-ficha-vistoria e auditado (RN-002).
 */
class InspectionFlowTest extends TestCase
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

    private function processo(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'address_street' => 'RUA CABO ASTROGILDO SALDANHA',
            'address_number' => '135',
            'address_neighborhood' => 'ITAPUÃ',
            'address_zip' => '41620838',
        ]);
    }

    /**
     * Anel fechado de 5 posições [lng, lat] em Salvador — polígono válido.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private function poligonoValido(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5110, -12.9712],
                [-38.5110, -12.9708],
                [-38.5104, -12.9708],
                [-38.5104, -12.9712],
                [-38.5110, -12.9712],
            ]],
        ];
    }

    public function test_abrir_ficha_cria_registro_com_identificacao_e_snapshot_da_localizacao(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/vistoria/show')
                ->has('ficha.id')
                ->has('ficha.tipo')
                ->has('ficha.status')
                ->has('ficha.opened_at')
                ->where('ficha.vistoriador', $vistoriador->name)
                ->where('ficha.logradouro', 'RUA CABO ASTROGILDO SALDANHA')
                ->where('ficha.numero_metrico', '135')
                ->where('ficha.bairro', 'ITAPUÃ')
                ->where('ficha.cep', '41620838')
                ->has('ficha.polygon_geojson')
                ->has('processo.id')
                ->has('tiposImovel')
                ->has('anexos'));

        $ficha = Inspection::query()->sole();

        $this->assertSame($processo->id, $ficha->viability_request_id);
        $this->assertSame($vistoriador->id, $ficha->vistoriador_user_id);
        $this->assertSame(InspectionStatus::EmPreenchimento, $ficha->status);
        $this->assertNotNull($ficha->opened_at);
        $this->assertSame('RUA CABO ASTROGILDO SALDANHA', $ficha->logradouro);
        $this->assertSame($processo->property_polygon_geojson, $ficha->polygon_geojson);
    }

    public function test_reabrir_ficha_nao_duplica_registro(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria")->assertOk();
        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria")->assertOk();

        $this->assertSame(1, Inspection::query()->count());
    }

    public function test_salvar_rascunho_persiste_campos_das_secoes(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($vistoriador, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/vistoria", [
                'ponto_referencia' => 'LOJA DA NIL MODAS',
                'logradouro_correto' => true,
                'tipo_imovel' => 'lote_terreno',
                'acessos' => ['comum', 'independente'],
                'atividade_em_funcionamento' => true,
                'complemento_tipo' => 'lote',
                'complemento_area_m2' => 100.00,
                'area_total_m2' => 100.00,
                'vagas_veiculo_passeio' => 2,
                'patio_carga_descarga' => false,
                'area_terreno_m2' => 100.00,
                'area_total_construida_m2' => 35.00,
                'recuo_m' => 1.5,
                'entorno_residencial_m' => 1.0,
                'entorno_outros_m' => 40.0,
                'instalacoes_eletricas' => 'satisfaz',
                'instalacoes_hidrossanitarias' => 'nao_satisfaz',
                'obras' => ['reforma'],
                'alvara_numero' => '2026-001',
                'num_leitos' => 10,
                'equipamentos' => ['fogao_industrial', 'caldeira'],
                'equipamentos_outros' => 'Exaustor industrial',
                'maquinas_motores' => false,
                'sons_ruidos' => true,
                'sons_ruidos_origem' => 'Compressor',
                'seg_extintores' => true,
                'seg_outros' => 'Saída de emergência',
                'observacoes' => 'Imóvel murado e pavimentado.',
            ])
            ->assertOk()
            ->assertJsonPath('ficha.tipo_imovel', 'lote_terreno');

        $ficha = Inspection::query()->sole();

        $this->assertSame('LOJA DA NIL MODAS', $ficha->ponto_referencia);
        $this->assertTrue($ficha->logradouro_correto);
        $this->assertSame(['comum', 'independente'], $ficha->acessos);
        $this->assertTrue($ficha->atividade_em_funcionamento);
        $this->assertSame(2, $ficha->vagas_veiculo_passeio);
        $this->assertSame('100.00', $ficha->area_total_m2);
        $this->assertSame('nao_satisfaz', $ficha->instalacoes_hidrossanitarias);
        $this->assertSame(['reforma'], $ficha->obras);
        $this->assertSame(['fogao_industrial', 'caldeira'], $ficha->equipamentos);
        $this->assertTrue($ficha->sons_ruidos);
        $this->assertSame('Compressor', $ficha->sons_ruidos_origem);
        $this->assertTrue($ficha->seg_extintores);
        $this->assertSame(InspectionStatus::EmPreenchimento, $ficha->status);
    }

    public function test_rascunho_recusa_valores_fora_do_dominio(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($vistoriador, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/vistoria", [
                'instalacoes_eletricas' => 'mais_ou_menos',
                'acessos' => ['portal_dimensional'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['instalacoes_eletricas', 'acessos.0']);
    }

    public function test_concluir_sem_parecer_e_recusado(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", [
                'data_vistoria' => '2026-09-21',
                'contato_nome' => 'Maria do Socorro',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parecer']);

        $this->assertSame(InspectionStatus::EmPreenchimento, Inspection::query()->sole()->status);
    }

    public function test_concluir_com_parecer_finaliza_a_ficha(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", [
                'parecer' => 'Área murada com 108 botijões de GLP; entorno residencial com escola a 40 m.',
                'data_vistoria' => '2026-09-21',
                'contato_nome' => 'Maria do Socorro',
                'contato_telefone' => '(71) 99999-0000',
            ])
            ->assertOk()
            ->assertJsonPath('ficha.status', InspectionStatus::Concluida->value);

        $ficha = Inspection::query()->sole();

        $this->assertSame(InspectionStatus::Concluida, $ficha->status);
        $this->assertNotNull($ficha->concluded_at);
        $this->assertSame('2026-09-21', $ficha->data_vistoria?->toDateString());
        $this->assertSame('Maria do Socorro', $ficha->contato_nome);
    }

    public function test_ficha_concluida_e_imutavel(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer conclusivo da vistoria.'])
            ->assertOk();

        $this->actingAs($vistoriador, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/vistoria", ['observacoes' => 'tentativa de edição'])
            ->assertUnprocessable();

        $this->assertNull(Inspection::query()->sole()->observacoes);
    }

    public function test_validar_poligono_persiste_novo_desenho_com_area_e_data(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $novo = $this->poligonoValido();

        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/poligono", ['polygon' => $novo])
            ->assertOk()
            ->assertJsonPath('ficha.polygon_geojson.type', 'Polygon');

        $ficha = Inspection::query()->sole();

        $this->assertSame($novo, $ficha->polygon_geojson);
        $this->assertNotNull($ficha->polygon_validated_at);
        $this->assertNotNull($ficha->polygon_area_m2);
        $this->assertGreaterThan(0, (float) $ficha->polygon_area_m2);
    }

    public function test_poligono_invalido_e_recusado(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/poligono", [
                'polygon' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[-38.51, -12.97], [-38.51, -12.96]]],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['polygon']);
    }

    public function test_upload_de_anexo_persiste_arquivo_e_metadados(): void
    {
        Storage::fake('local');

        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");

        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/anexos", [
                'file' => UploadedFile::fake()->image('fachada.jpg', 800, 600),
            ])
            ->assertRedirect();

        $anexo = InspectionAttachment::query()->sole();

        $this->assertSame('fachada.jpg', $anexo->original_name);
        $this->assertSame($vistoriador->id, $anexo->uploaded_by_user_id);
        $this->assertSame(64, strlen((string) $anexo->sha256));
        Storage::disk('local')->assertExists($anexo->path);
    }

    public function test_remover_anexo_exclui_arquivo_e_registro(): void
    {
        Storage::fake('local');

        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/anexos", [
                'file' => UploadedFile::fake()->image('fachada.jpg'),
            ]);

        $anexo = InspectionAttachment::query()->sole();

        $this->actingAs($vistoriador, 'gestao')
            ->delete("/gestao/processos/{$processo->id}/vistoria/anexos/{$anexo->id}")
            ->assertRedirect();

        $this->assertSame(0, InspectionAttachment::query()->count());
        Storage::disk('local')->assertMissing($anexo->path);
    }

    public function test_anexo_de_outro_processo_nao_e_acessivel(): void
    {
        Storage::fake('local');

        $vistoriador = $this->vistoriador();
        $processo = $this->processo();
        $outroProcesso = ViabilityRequest::factory()->create();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->post("/gestao/processos/{$processo->id}/vistoria/anexos", [
                'file' => UploadedFile::fake()->image('fachada.jpg'),
            ]);

        $anexo = InspectionAttachment::query()->sole();

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$outroProcesso->id}/vistoria/anexos/{$anexo->id}")
            ->assertNotFound();
    }

    public function test_usuario_sem_permissao_nao_acessa_a_ficha(): void
    {
        $apoio = User::factory()->withAcceptedLgpdTerm()->create();
        $apoio->assignRole('apoio');

        $processo = $this->processo();

        $this->actingAs($apoio, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertForbidden();

        $this->assertSame(0, Inspection::query()->count());
    }

    public function test_abertura_rascunho_e_conclusao_sao_auditados(): void
    {
        $vistoriador = $this->vistoriador();
        $processo = $this->processo();

        $this->actingAs($vistoriador, 'gestao')->get("/gestao/processos/{$processo->id}/vistoria");
        $this->actingAs($vistoriador, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/vistoria", ['observacoes' => 'Sem intercorrências.']);
        $this->actingAs($vistoriador, 'gestao')
            ->postJson("/gestao/processos/{$processo->id}/vistoria/concluir", ['parecer' => 'Parecer conclusivo da vistoria.']);

        $eventos = Activity::query()
            ->where('log_name', 'vistoria')
            ->pluck('event')
            ->all();

        $this->assertContains('ficha-abertura', $eventos);
        $this->assertContains('ficha-rascunho', $eventos);
        $this->assertContains('ficha-conclusao', $eventos);
    }
}
