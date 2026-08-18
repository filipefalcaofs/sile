<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retrofit do CSV da consulta de processos (HU-082) para o contrato único de
 * exportação (HU-131/RN-009): o ProcessoController::index passa a delegar ao
 * ReportExporter via ProcessosReportSource. A rede anti-regressão
 * (ProcessoConsultaTest, 10-14) garante o conjunto filtrado e a meta-auditoria;
 * este teste prova a PRESERVAÇÃO das 9 colunas históricas do SAPS e o GANHO
 * (XLSX/PDF pelo mesmo ?formato=), além do recorte filtrado (RN-005).
 */
class RetrofitCsvProcessosTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function processo(array $attrs = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attrs));
    }

    public function test_export_csv_preserva_as_colunas_historicas_do_processo(): void
    {
        $alvo = $this->processo();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=csv')
            ->assertOk();

        $conteudo = $response->streamedContent();
        $cabecalho = strtok($conteudo, "\n");

        foreach (['Processo', 'BAP', 'Produto TVL', 'Empresa', 'CNPJ', 'Status', 'Categoria', 'Analista', 'Prazo'] as $coluna) {
            $this->assertStringContainsString($coluna, (string) $cabecalho);
        }

        $this->assertStringContainsString($alvo->protocol_number, $conteudo);
    }

    public function test_export_csv_reflete_o_filtro_de_status_rn005(): void
    {
        $emAnalise = $this->processo(['status' => ViabilityRequestStatus::EmAnalise]);
        $deferida = $this->processo(['status' => ViabilityRequestStatus::Deferida]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?status=em_analise&formato=csv')
            ->assertOk();

        $conteudo = $response->streamedContent();

        // RN-005: só o conjunto filtrado entra no arquivo.
        $this->assertStringContainsString($emAnalise->protocol_number, $conteudo);
        $this->assertStringNotContainsString($deferida->protocol_number, $conteudo);
    }

    public function test_export_xlsx_agora_disponivel_nos_processos(): void
    {
        $this->processo();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=xlsx')
            ->assertOk();

        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
    }

    public function test_export_pdf_agora_disponivel_nos_processos(): void
    {
        $this->processo();

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=pdf')
            ->assertOk();

        $this->assertStringContainsString('pdf', strtolower((string) $response->headers->get('content-type')));
    }

    public function test_export_de_processos_e_auditado_com_o_evento_historico(): void
    {
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=csv')
            ->assertOk()
            ->streamedContent();

        // Meta-auditoria preservada: log_name/evento idênticos ao CSV histórico.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'exporta-processos-csv',
            'result' => 'sucesso',
        ]);
    }
}
