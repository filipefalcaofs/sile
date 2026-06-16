<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Jobs\GerarExportacaoJob;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Export\ReportExporter;
use App\Services\Relatorios\Export\Sources\AtividadesReportSource;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Retrofit dos DOIS CSVs históricos (AuditoriaController e ProcessoController) ao
 * contrato único de exportação (HU-131/RN-009): os streamings `fputcsv` cruos
 * passam a delegar ao {@see ReportExporter},
 * eliminando as cópias divergentes. Esta suíte é COMPLEMENTAR às redes
 * anti-regressão (AuditoriaExportTest 12-04 e ProcessoConsultaTest 10-14): prova
 * que (a) o CSV preserva as colunas históricas, (b) as telas ganham XLSX/PDF pelo
 * mesmo `?formato=` e (c) o caminho assíncrono reproduz o MESMO recorte filtrado
 * (RN-005/RN-007 — nunca a base inteira com PII).
 */
class RetrofitCsvAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private const COLUNAS_TRILHA = [
        'Data/hora', 'Fonte', 'Ação', 'Descrição', 'Usuário', 'Em nome de',
        'Entidade', 'Entidade ID', 'Resultado', 'Versão de regras', 'IP', 'Canal',
    ];

    private const COLUNAS_PROCESSOS = [
        'Processo', 'BAP', 'Produto TVL', 'Empresa', 'CNPJ', 'Status',
        'Categoria', 'Analista', 'Prazo',
    ];

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Processo com protocolo único e status informado (espelha o helper do
     * ProcessoConsultaTest 10-14 — a rede anti-regressão).
     */
    private function processo(ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => $status,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function atividade(array $attrs = []): Activity
    {
        $activity = new Activity;

        $activity->forceFill(array_merge([
            'log_name' => 'teste',
            'event' => 'consulta',
            'description' => 'evento de teste',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        $activity->save();

        return $activity;
    }

    /**
     * Cabeçalho (primeira linha) de um CSV exportado, já parseado em colunas.
     *
     * @return list<string>
     */
    private function cabecalhoCsv(string $conteudo): array
    {
        $primeira = strtok(trim($conteudo), "\n");

        return str_getcsv((string) $primeira);
    }

    public function test_export_da_trilha_em_csv_preserva_as_colunas_historicas(): void
    {
        $gestor = $this->gestor();
        $this->atividade(['log_name' => 'teste', 'description' => 'Consulta alvo']);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?fonte=atividade&formato=csv&log_name=teste')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $conteudo = $response->streamedContent();
        $this->assertSame(self::COLUNAS_TRILHA, $this->cabecalhoCsv($conteudo));
        $this->assertStringContainsString('Consulta alvo', $conteudo);
    }

    public function test_export_da_trilha_em_xlsx_agora_streama_planilha(): void
    {
        $gestor = $this->gestor();
        $this->atividade(['log_name' => 'teste', 'description' => 'Linha XLSX']);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?fonte=atividade&formato=xlsx&log_name=teste')
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_export_da_trilha_em_pdf_agora_streama_documento(): void
    {
        $gestor = $this->gestor();
        $this->atividade(['log_name' => 'teste', 'description' => 'Linha PDF']);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?fonte=atividade&formato=pdf&log_name=teste')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_export_da_fonte_acessos_ganha_xlsx_pelo_mesmo_formato(): void
    {
        $gestor = $this->gestor();
        $user = User::factory()->create(['name' => 'Eduarda Acesso']);
        AccessLog::factory()->create(['user_id' => $user->id, 'email' => 'eduarda@example.test', 'event' => 'login']);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?fonte=acessos&formato=xlsx')
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_export_assincrono_da_trilha_preserva_o_recorte_filtrado_rn005_rn007(): void
    {
        // RN-006: acima do limiar a exportação vai ao Job (202). RN-005/RN-007: o
        // bag carrega o filtro e faz round-trip, então o worker reconstrói SÓ o
        // recorte filtrado — nunca a trilha inteira com PII.
        Queue::fake();
        config(['sile.relatorios.export.assincrono_limiar_linhas' => 1]);

        $gestor = $this->gestor();
        // 2 registros DENTRO do filtro (total filtrado > limiar → async) + 1 FORA.
        $alvo1 = $this->atividade(['log_name' => 'teste', 'description' => 'Dentro 1']);
        $alvo2 = $this->atividade(['log_name' => 'teste', 'description' => 'Dentro 2']);
        $fora = $this->atividade(['log_name' => 'outro', 'description' => 'Fora do filtro']);

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?formato=csv&log_name=teste')
            ->assertStatus(202);

        $job = Queue::pushed(GerarExportacaoJob::class)->first();

        $this->assertNotNull($job, 'Acima do limiar a exportação deve ir ao GerarExportacaoJob (RN-006).');
        $this->assertSame(AtividadesReportSource::class, $job->sourceClass);
        $this->assertSame('teste', $job->filtros['log_name'] ?? null);

        // Reconstrução idêntica à do worker: SÓ o recorte filtrado, nunca a base
        // toda com PII (RN-005/RN-007). O registro fora do filtro não aparece.
        $linhas = app(AtividadesReportSource::class)
            ->definition(ReportFilters::fromArray($job->filtros))
            ->builder()
            ->get();

        $ids = $linhas->pluck('id')->all();
        $this->assertCount(2, $linhas);
        $this->assertContains($alvo1->id, $ids);
        $this->assertContains($alvo2->id, $ids);
        $this->assertNotContains($fora->id, $ids);
    }

    public function test_export_de_processos_em_csv_preserva_as_colunas_historicas(): void
    {
        $alvo = $this->processo(ViabilityRequestStatus::EmAnalise);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=csv&status=em_analise')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $conteudo = $response->streamedContent();
        $this->assertSame(self::COLUNAS_PROCESSOS, $this->cabecalhoCsv($conteudo));
        $this->assertStringContainsString($alvo->protocol_number, $conteudo);
    }

    public function test_export_de_processos_em_xlsx_agora_streama_planilha(): void
    {
        $this->processo(ViabilityRequestStatus::EmAnalise);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=xlsx&status=em_analise')
            ->assertOk();

        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_export_de_processos_em_pdf_agora_streama_documento(): void
    {
        $this->processo(ViabilityRequestStatus::EmAnalise);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=pdf&status=em_analise')
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_export_de_processos_reflete_o_filtro_rn005(): void
    {
        $alvo = $this->processo(ViabilityRequestStatus::EmAnalise);
        $fora = $this->processo(ViabilityRequestStatus::Deferida);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/processos?formato=csv&status=em_analise')
            ->assertOk();

        $conteudo = $response->streamedContent();
        $this->assertStringContainsString($alvo->protocol_number, $conteudo);
        $this->assertStringNotContainsString($fora->protocol_number, $conteudo);
    }
}
