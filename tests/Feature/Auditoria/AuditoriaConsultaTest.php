<?php

namespace Tests\Feature\Auditoria;

use App\Http\Resources\ActivityResource;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\Cnae;
use App\Models\User;
use App\Services\Auditoria\AuditTrailQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consulta unificada da trilha de auditoria (HU-100) e histórico de alterações
 * (HU-098) direto sobre a espinha activity_log, server-driven (espelha o
 * ProcessoQueryService): filtros REAIS por período, usuário (causer), entidade
 * (subject), ação (log_name/event) e resultado, do mais recente ao mais antigo,
 * sem materializar nada. A fonte "alteracoes" recorta só os registros com diff
 * (attribute_changes) e a fonte "acessos" lista o histórico GLOBAL de
 * access_logs. O ActivityResource minimiza o payload (sem despejar properties
 * cruas) e expõe o diff para a HU-098.
 */
class AuditoriaConsultaTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AuditTrailQueryService
    {
        return app(AuditTrailQueryService::class);
    }

    /**
     * Semeia uma activity diretamente na espinha (sem factory dedicada): o model
     * pai usa $guarded = [], então forceFill grava qualquer coluna da RN-002,
     * inclusive created_at controlado para o filtro por período.
     *
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

    public function test_filtra_por_periodo_sobre_created_at(): void
    {
        $antiga = $this->atividade(['created_at' => '2026-01-10 10:00:00']);
        $recente = $this->atividade(['created_at' => '2026-03-20 10:00:00']);

        $deMarco = $this->service()->filtered(['log_name' => 'teste', 'data_de' => '2026-03-01'])->pluck('id')->all();
        $this->assertContains($recente->id, $deMarco);
        $this->assertNotContains($antiga->id, $deMarco);

        $ateFevereiro = $this->service()->filtered(['log_name' => 'teste', 'data_ate' => '2026-02-01'])->pluck('id')->all();
        $this->assertContains($antiga->id, $ateFevereiro);
        $this->assertNotContains($recente->id, $ateFevereiro);
    }

    public function test_filtra_por_usuario_por_id_e_por_nome(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Auditoria']);
        $bruno = User::factory()->create(['name' => 'Bruno Gestor']);

        $daAna = $this->atividade(['causer_type' => $ana->getMorphClass(), 'causer_id' => $ana->id]);
        $this->atividade(['causer_type' => $bruno->getMorphClass(), 'causer_id' => $bruno->id]);

        $porId = $this->service()->filtered(['log_name' => 'teste', 'usuario_id' => $ana->id])->pluck('id')->all();
        $this->assertSame([$daAna->id], $porId);

        // Busca textual case-insensitive no nome do causer (whereHasMorph).
        $porNome = $this->service()->filtered(['log_name' => 'teste', 'usuario' => 'ana audit'])->pluck('id')->all();
        $this->assertSame([$daAna->id], $porNome);
    }

    public function test_filtra_por_entidade_subject_tipo_e_id(): void
    {
        $alvo = $this->atividade(['subject_type' => 'App\\Models\\ViabilityRequest', 'subject_id' => 4242]);
        $outra = $this->atividade(['subject_type' => 'App\\Models\\ViabilityRequest', 'subject_id' => 7]);

        $porId = $this->service()->filtered(['log_name' => 'teste', 'entidade_id' => 4242])->pluck('id')->all();
        $this->assertSame([$alvo->id], $porId);

        // O tipo aceita o basename (subject_type guarda a classe completa).
        $porTipo = $this->service()->filtered(['log_name' => 'teste', 'entidade_tipo' => 'ViabilityRequest'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$alvo->id, $outra->id], $porTipo);
    }

    public function test_filtra_por_acao_log_name_event_e_resultado(): void
    {
        $alvo = $this->atividade(['log_name' => 'analise', 'event' => 'consulta-processos', 'result' => 'sucesso']);
        $this->atividade(['log_name' => 'analise', 'event' => 'decidir', 'result' => 'bloqueado']);

        $porEvento = $this->service()->filtered(['log_name' => 'analise', 'event' => 'consulta-processos'])->pluck('id')->all();
        $this->assertSame([$alvo->id], $porEvento);

        $porResultado = $this->service()->filtered(['log_name' => 'analise', 'resultado' => 'sucesso'])->pluck('id')->all();
        $this->assertSame([$alvo->id], $porResultado);
    }

    public function test_filtered_ordena_do_mais_recente_para_o_mais_antigo(): void
    {
        $primeira = $this->atividade();
        $segunda = $this->atividade();

        $ids = $this->service()->filtered(['log_name' => 'teste'])->pluck('id')->all();

        $this->assertSame([$segunda->id, $primeira->id], $ids);
    }

    public function test_apenas_alteracoes_traz_so_registros_com_attribute_changes(): void
    {
        $comMudanca = $this->atividade([
            'event' => 'updated',
            'attribute_changes' => ['attributes' => ['name' => 'novo'], 'old' => ['name' => 'velho']],
        ]);
        $semMudanca = $this->atividade(['event' => 'consulta']);

        $ids = $this->service()->apenasAlteracoes(['log_name' => 'teste'])->pluck('id')->all();

        $this->assertContains($comMudanca->id, $ids);
        $this->assertNotContains($semMudanca->id, $ids);
    }

    public function test_apenas_alteracoes_captura_diff_real_de_model_com_has_auditoria(): void
    {
        // Anti-fachada: o diff REAL gravado por HasAuditoria num update precisa
        // ser recortado pelo whereNotNull('attribute_changes'), não só dados
        // semeados à mão.
        $cnae = Cnae::factory()->create();
        $cnae->update(['description' => 'Descrição atualizada para auditoria']);

        $semMudanca = $this->atividade(['event' => 'consulta']);

        $registros = $this->service()->apenasAlteracoes([])->get();

        $this->assertNotContains($semMudanca->id, $registros->pluck('id')->all());

        $alteracao = $registros->first(fn (Activity $a): bool => $a->event === 'updated');
        $this->assertNotNull($alteracao, 'Esperava capturar a alteração real de um model com HasAuditoria.');
        $this->assertNotNull($alteracao->attribute_changes);
    }

    public function test_acessos_lista_o_historico_global_de_access_logs(): void
    {
        AccessLog::factory()->count(3)->create();
        AccessLog::factory()->create(['event' => 'bloqueio']);

        $this->assertSame(4, $this->service()->acessos([])->count());

        $bloqueios = $this->service()->acessos(['event' => 'bloqueio'])->pluck('event')->all();
        $this->assertSame(['bloqueio'], $bloqueios);
    }

    public function test_acessos_filtra_por_usuario(): void
    {
        $user = User::factory()->create(['name' => 'Carla Cidadã']);
        AccessLog::factory()->create(['user_id' => $user->id, 'email' => $user->email]);
        AccessLog::factory()->create();

        $porId = $this->service()->acessos(['usuario_id' => $user->id])->pluck('user_id')->all();
        $this->assertSame([$user->id], $porId);

        $porNome = $this->service()->acessos(['usuario' => 'carla'])->pluck('user_id')->all();
        $this->assertSame([$user->id], $porNome);
    }

    public function test_activity_resource_expoe_campos_da_trilha_e_o_diff(): void
    {
        $causer = User::factory()->create(['name' => 'Carla Causer']);
        $representado = User::factory()->create(['name' => 'Diego Representado']);

        $activity = $this->atividade([
            'log_name' => 'empresas',
            'event' => 'updated',
            'description' => 'Atualização de empresa',
            'causer_type' => $causer->getMorphClass(),
            'causer_id' => $causer->id,
            'acting_for_user_id' => $representado->id,
            'subject_type' => 'App\\Models\\Company',
            'subject_id' => 99,
            'result' => 'sucesso',
            'rules_version' => 'lei-9148-2016',
            'ip_address' => '10.0.0.9',
            'channel' => 'gestao',
            'personal_data' => true,
            'attribute_changes' => ['attributes' => ['trade_name' => 'Nova'], 'old' => ['trade_name' => 'Velha']],
        ]);

        $activity->load(['causer', 'actingFor', 'subject']);

        $dados = (new ActivityResource($activity))->resolve();

        $this->assertSame('empresas', $dados['log_name']);
        $this->assertSame('updated', $dados['event']);
        $this->assertSame('Atualização de empresa', $dados['description']);
        $this->assertSame($causer->id, $dados['causer']['id']);
        $this->assertSame('Carla Causer', $dados['causer']['nome']);
        $this->assertSame($representado->id, $dados['acting_for']['id']);
        $this->assertSame('Company', $dados['subject']['type']);
        $this->assertSame(99, $dados['subject']['id']);
        $this->assertSame('sucesso', $dados['result']);
        $this->assertSame('lei-9148-2016', $dados['rules_version']);
        $this->assertSame('10.0.0.9', $dados['ip_address']);
        $this->assertSame('gestao', $dados['channel']);
        $this->assertTrue($dados['personal_data']);
        $this->assertSame(['trade_name' => 'Nova'], $dados['attribute_changes']['attributes']);

        // Minimização LGPD: o payload da trilha não despeja as properties cruas.
        $this->assertArrayNotHasKey('properties', $dados);
    }

    public function test_activity_resource_trata_causer_sistema_como_nulo(): void
    {
        $activity = $this->atividade(['causer_type' => null, 'causer_id' => null, 'subject_type' => null, 'subject_id' => null]);

        $dados = (new ActivityResource($activity))->resolve();

        $this->assertNull($dados['causer']);
        $this->assertNull($dados['subject']);
        $this->assertNull($dados['acting_for']);
    }
}
