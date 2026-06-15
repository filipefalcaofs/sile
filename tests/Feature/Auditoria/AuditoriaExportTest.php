<?php

namespace Tests\Feature\Auditoria;

use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Superfície HTTP da trilha (Gestao\AuditoriaController): index roteia a fonte
 * (atividade/alteracoes/acessos), pagina server-side e AUDITA a própria
 * consulta; o export CSV respeita os MESMOS filtros, faz streaming com guarda de
 * volume técnica (config) e também é auditado. Meta-auditoria CA-02/RN-002: quem
 * consultou/exportou a trilha de quem fica registrado (personal_data), e o 403
 * sem consultar-auditoria é auditado no ponto único. Export pleno XLSX/PDF fica
 * bloqueado honesto (HU-131/Fase 15).
 */
class AuditoriaExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
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

    public function test_sem_consultar_auditoria_recebe_403_auditado(): void
    {
        // Analista passa o grupo (acessar-gestao + LGPD) mas NÃO tem
        // consultar-auditoria — 403 no gate, auditado no ponto único (CA-04).
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/auditoria')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_export_sem_consultar_auditoria_recebe_403_auditado(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/auditoria/export')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);
    }

    public function test_index_lista_atividades_e_audita_a_propria_consulta(): void
    {
        $gestor = $this->gestor();
        $this->atividade(['log_name' => 'teste', 'event' => 'consulta']);
        $this->atividade(['log_name' => 'teste', 'event' => 'updated', 'attribute_changes' => ['attributes' => ['x' => 1]]]);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria?log_name=teste')
            ->assertOk();

        $page = $response->viewData('page');
        $this->assertSame('gestao/auditoria/index', $page['component']);
        $this->assertSame(2, $page['props']['registros']['total']);
        $this->assertSame('atividade', $page['props']['fonte']);
        $this->assertArrayHasKey('fonteOptions', $page['props']);
        $this->assertArrayHasKey('perPageOptions', $page['props']);

        // Meta-auditoria CA-02: a consulta é auditada com marca de dado pessoal.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auditoria',
            'event' => 'consulta-trilha',
            'result' => 'sucesso',
            'personal_data' => 1,
            'causer_id' => $gestor->id,
        ]);
    }

    public function test_index_fonte_alteracoes_traz_so_registros_com_mudancas(): void
    {
        $gestor = $this->gestor();
        $comMudanca = $this->atividade(['event' => 'updated', 'attribute_changes' => ['attributes' => ['x' => 1]]]);
        $this->atividade(['event' => 'consulta']);

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria?fonte=alteracoes&log_name=teste')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fonte', 'alteracoes')
                ->has('registros.data', 1)
                ->where('registros.data.0.id', $comMudanca->id));
    }

    public function test_index_fonte_acessos_lista_access_logs(): void
    {
        $gestor = $this->gestor();
        AccessLog::factory()->count(2)->create();

        $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria?fonte=acessos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fonte', 'acessos')
                ->has('registros.data', 2));
    }

    public function test_export_csv_respeita_os_filtros_e_e_auditado(): void
    {
        $gestor = $this->gestor();
        $this->atividade(['log_name' => 'teste', 'event' => 'consulta-processos', 'description' => 'Consulta alvo']);
        $this->atividade(['log_name' => 'outro', 'event' => 'consulta', 'description' => 'Fora do filtro']);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?log_name=teste')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $conteudo = $response->streamedContent();
        $this->assertStringContainsString('Consulta alvo', $conteudo);
        $this->assertStringNotContainsString('Fora do filtro', $conteudo);

        // O export também é auditado (meta-auditoria, personal_data).
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auditoria',
            'event' => 'exporta-trilha-csv',
            'result' => 'sucesso',
            'personal_data' => 1,
            'causer_id' => $gestor->id,
        ]);
    }

    public function test_index_com_formato_csv_delega_para_o_export(): void
    {
        $gestor = $this->gestor();
        $this->atividade(['log_name' => 'teste', 'description' => 'Delegado']);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria?formato=csv&log_name=teste')
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Delegado', $response->streamedContent());
    }

    public function test_export_csv_da_fonte_acessos_respeita_a_fonte(): void
    {
        $gestor = $this->gestor();
        $user = User::factory()->create(['name' => 'Eduarda Acesso']);
        AccessLog::factory()->create(['user_id' => $user->id, 'email' => 'eduarda@example.test', 'event' => 'login']);

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?fonte=acessos')
            ->assertOk();

        $conteudo = $response->streamedContent();
        $this->assertStringContainsString('eduarda@example.test', $conteudo);
    }

    public function test_export_csv_aplica_guarda_de_volume_max_linhas(): void
    {
        // Constante técnica de volume (config/sile.php) — limita honesto, não
        // simula. Com max_linhas=2, o CSV traz cabeçalho + no máximo 2 linhas.
        config(['sile.auditoria.export.max_linhas' => 2]);

        $gestor = $this->gestor();

        for ($i = 0; $i < 5; $i++) {
            $this->atividade(['log_name' => 'teste', 'description' => "linha {$i}"]);
        }

        $response = $this->actingAs($gestor, 'gestao')
            ->get('/gestao/auditoria/export?log_name=teste')
            ->assertOk();

        $linhas = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

        // 1 cabeçalho + 2 linhas de dados (guarda de volume aplicada).
        $this->assertCount(3, $linhas);
    }
}
