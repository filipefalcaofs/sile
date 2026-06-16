<?php

namespace Tests\Feature\Relatorios;

use App\Models\AccessLog;
use App\Models\Cnae;
use App\Models\Parameter;
use App\Models\User;
use App\Services\Relatorios\Export\Sources\CnaesReportSource;
use App\Services\Relatorios\Export\Sources\UsuariosReportSource;
use App\Services\Relatorios\ReportFilters;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retrofit do export transversal (HU-131/RN-009) nas listagens das Fases 1–2:
 * cada index ganha o branch ?formato= delegando ao contrato único
 * (ReportExporter), sem rota nova. Prova RN-005 (o arquivo reflete EXATAMENTE a
 * listagem filtrada), RN-007 (usuários: cpf_masked por default) e os três
 * formatos do contrato (CSV/XLSX/PDF). Os ReportSources leem os filtros do BAG
 * do ReportFilters (reconstrutíveis via fromArray — síncrono E assíncrono).
 */
class RetrofitListagensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    // ----------------------------------------------------------------------
    // Task 1 — CNAEs e Parâmetros
    // ----------------------------------------------------------------------

    public function test_export_csv_de_cnaes_reflete_o_filtro_de_busca_rn005(): void
    {
        Cnae::factory()->create(['code' => '7777777', 'description' => 'Restaurante Alfa Exportavel']);
        Cnae::factory()->create(['code' => '8888888', 'description' => 'Padaria Beta Excluida']);

        $response = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/cnaes?search=Alfa&formato=csv')
            ->assertOk();

        $this->assertStringContainsString('csv', strtolower((string) $response->headers->get('content-type')));

        $conteudo = $response->streamedContent();

        // RN-005: só o conjunto filtrado entra no arquivo.
        $this->assertStringContainsString('Alfa Exportavel', $conteudo);
        $this->assertStringNotContainsString('Beta Excluida', $conteudo);
    }

    public function test_export_xlsx_e_pdf_de_cnaes_disponiveis(): void
    {
        Cnae::factory()->create();

        $xlsx = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/cnaes?formato=xlsx')
            ->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('content-type'));

        $pdf = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/cnaes?formato=pdf')
            ->assertOk();
        $this->assertStringContainsString('pdf', strtolower((string) $pdf->headers->get('content-type')));
    }

    public function test_cnaes_report_source_reconstruido_do_bag_traz_o_mesmo_recorte(): void
    {
        Cnae::factory()->create(['code' => '7777777', 'description' => 'Restaurante Alfa Exportavel']);
        Cnae::factory()->create(['code' => '8888888', 'description' => 'Padaria Beta Excluida']);

        // Sem estado de construtor: o recorte vem do bag (RN-005 sync E async).
        $definition = app(CnaesReportSource::class)
            ->definition(ReportFilters::fromArray(['search' => 'Alfa']));

        $this->assertSame(['7777777'], $definition->builder()->pluck('code')->all());
    }

    public function test_export_csv_de_parametros_nao_vaza_valor_sensivel_rn009(): void
    {
        Parameter::factory()->create([
            'group' => 'relatorios',
            'key' => 'relatorios.export.publico',
            'value' => 'valor-normal-visivel',
            'default_value' => 'padrao-x',
        ]);

        Parameter::factory()->sensitive()->create([
            'group' => 'integracao',
            'key' => 'integracao.token.secreto',
            'value' => 'SEGREDO-XYZ-123',
            'default_value' => 'padrao-y',
        ]);

        $response = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/parametros?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('csv', strtolower((string) $response->headers->get('content-type')));

        $conteudo = $response->streamedContent();

        $this->assertStringContainsString('relatorios.export.publico', $conteudo);
        $this->assertStringContainsString('valor-normal-visivel', $conteudo);
        $this->assertStringContainsString('integracao.token.secreto', $conteudo);
        // RN-009: valor sensível nunca sai em claro.
        $this->assertStringNotContainsString('SEGREDO-XYZ-123', $conteudo);
        $this->assertStringContainsString('[sensível]', $conteudo);
    }

    // ----------------------------------------------------------------------
    // Task 2 — Usuários (cpf_masked) e Perfis
    // ----------------------------------------------------------------------

    public function test_export_csv_de_usuarios_mascara_cpf_por_default_rn007(): void
    {
        User::factory()->analista()->create(['name' => 'Fulano Pii Alvo', 'cpf' => '52998224725']);

        $response = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/usuarios?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('csv', strtolower((string) $response->headers->get('content-type')));

        $conteudo = $response->streamedContent();

        $this->assertStringContainsString('Fulano Pii Alvo', $conteudo);
        // RN-007: CPF mascarado por default; o número cru nunca sai sem permissão de PII.
        $this->assertStringContainsString('***.***.***-25', $conteudo);
        $this->assertStringNotContainsString('52998224725', $conteudo);
    }

    public function test_usuarios_report_source_com_pii_no_bag_emite_cpf_completo(): void
    {
        User::factory()->analista()->create(['name' => 'Fulano Pii Alvo', 'cpf' => '52998224725']);

        // O gate de PII viaja no BAG (não no construtor): liberado → CPF completo.
        $definition = app(UsuariosReportSource::class)
            ->definition(ReportFilters::fromArray(['pii' => '1', 'tab' => 'gestao']));

        $celulas = $definition->builder()->get()
            ->flatMap(fn ($user) => $definition->mapRow($user))
            ->all();

        $this->assertContains('52998224725', $celulas);
    }

    public function test_export_csv_de_usuarios_reflete_busca_rn005(): void
    {
        User::factory()->analista()->create(['name' => 'Mariana Busca Presente']);
        User::factory()->analista()->create(['name' => 'Carlos Ausente Filtro']);

        $response = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/usuarios?search=Mariana&formato=csv')
            ->assertOk();

        $conteudo = $response->streamedContent();

        // RN-005: só o conjunto filtrado pela busca entra no arquivo.
        $this->assertStringContainsString('Mariana Busca Presente', $conteudo);
        $this->assertStringNotContainsString('Carlos Ausente Filtro', $conteudo);
    }

    public function test_export_de_perfis_traz_os_perfis_e_os_formatos(): void
    {
        $csv = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis?formato=csv')
            ->assertOk();

        $this->assertStringContainsString('csv', strtolower((string) $csv->headers->get('content-type')));

        $conteudo = $csv->streamedContent();
        $this->assertStringContainsString('Perfil', $conteudo);
        $this->assertStringContainsString('administrador', $conteudo);

        $xlsx = $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/perfis?formato=xlsx')
            ->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('content-type'));
    }

    // ----------------------------------------------------------------------
    // Task 3 — Histórico de acessos por usuário
    // ----------------------------------------------------------------------

    public function test_export_csv_de_acessos_traz_so_os_acessos_do_usuario_rn005(): void
    {
        $alvo = User::factory()->create();
        $outro = User::factory()->create();

        AccessLog::factory()->create([
            'user_id' => $alvo->id,
            'email' => $alvo->email,
            'event' => 'login',
            'ip_address' => '10.0.0.7',
        ]);

        AccessLog::factory()->create([
            'user_id' => $outro->id,
            'email' => $outro->email,
            'event' => 'login',
            'ip_address' => '10.9.9.9',
        ]);

        $response = $this->actingAs($this->admin(), 'gestao')
            ->get("/gestao/acessos/{$alvo->id}?formato=csv")
            ->assertOk();

        $this->assertStringContainsString('csv', strtolower((string) $response->headers->get('content-type')));

        $conteudo = $response->streamedContent();

        // RN-005: o `user` viaja no bag; só os acessos daquele usuário entram.
        $this->assertStringContainsString('10.0.0.7', $conteudo);
        $this->assertStringNotContainsString('10.9.9.9', $conteudo);
    }

    public function test_export_de_acessos_e_auditado_rn008(): void
    {
        $alvo = User::factory()->create();

        AccessLog::factory()->create([
            'user_id' => $alvo->id,
            'email' => $alvo->email,
        ]);

        $admin = $this->admin();

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/acessos/{$alvo->id}?formato=csv")
            ->assertOk()
            ->streamedContent();

        // RN-008: o contrato único audita toda exportação (tela/filtros/formato/volume).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'acessos',
            'event' => 'exporta-acessos-usuario-csv',
            'result' => 'sucesso',
            'causer_id' => $admin->id,
        ]);
    }
}
