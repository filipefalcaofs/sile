<?php

namespace Tests\Feature\Cidadao;

use App\Models\Activity;
use App\Models\Cidadao;
use App\Models\User;
use App\Services\Auditoria\AuditTrailQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificacoesAuditoriaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migrar(): void
    {
        $migracao = require database_path('migrations/2026_10_16_000450_retipa_notificacoes_e_auditoria.php');
        $migracao->up();
    }

    private function notificar(string $tipo, int $id): string
    {
        $uuid = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $uuid, 'type' => 'x', 'notifiable_type' => $tipo, 'notifiable_id' => $id,
            'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $uuid;
    }

    private function atividade(array $attrs): Activity
    {
        $activity = new Activity;
        $activity->forceFill(array_merge([
            'log_name' => 'teste', 'event' => 'consulta', 'description' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ], $attrs));
        $activity->save();

        return $activity;
    }

    /** Conta de cidadão de antes da separação, já copiada para `cidadaos` com o mesmo id, CPF e senha. */
    private function contaCopiada(): Cidadao
    {
        $conta = User::factory()->create();
        Cidadao::factory()->create(['id' => $conta->id, 'cpf' => $conta->cpf, 'email' => $conta->email]);

        return Cidadao::query()->findOrFail($conta->id);
    }

    public function test_notificacao_antiga_do_cidadao_passa_a_ser_dele(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $servidor = User::factory()->analista()->create();
        $cidadao = $this->contaCopiada();

        $doCidadao = $this->notificar(User::class, $cidadao->id);
        $doServidor = $this->notificar(User::class, $servidor->id);

        $this->migrar();

        $this->assertSame([$doCidadao], $cidadao->notifications()->pluck('id')->all());
        $this->assertSame([$doServidor], $servidor->notifications()->pluck('id')->all());
    }

    public function test_alerta_de_abuso_sobre_conta_copiada_passa_a_apontar_para_o_cidadao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $servidor = User::factory()->analista()->create();
        $cidadao = $this->contaCopiada();
        $alerta = fn (int $id) => DB::table('abuse_alerts')->insertGetId([
            'rule_key' => 'regra', 'severity' => 'alta', 'fingerprint' => "f{$id}",
            'subject_type' => User::class, 'subject_id' => $id, 'detected_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $doCidadao = $alerta($cidadao->id);
        $doServidor = $alerta($servidor->id);

        $this->migrar();

        $this->assertSame(Cidadao::class, DB::table('abuse_alerts')->where('id', $doCidadao)->value('subject_type'));
        $this->assertSame(User::class, DB::table('abuse_alerts')->where('id', $doServidor)->value('subject_type'));
    }

    public function test_espelho_de_servidor_com_password_nulo_nao_e_retipado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $servidor = User::factory()->analista()->create();
        Cidadao::factory()->semSenha()->create(['id' => $servidor->id, 'cpf' => $servidor->cpf, 'email' => $servidor->email]);
        $migrado = $this->contaCopiada();

        $doServidor = $this->notificar(User::class, $servidor->id);
        $doMigrado = $this->notificar(User::class, $migrado->id);
        $atividadeServidor = $this->atividade(['causer_type' => User::class, 'causer_id' => $servidor->id, 'subject_type' => User::class, 'subject_id' => $servidor->id]);
        $atividadeMigrado = $this->atividade(['causer_type' => User::class, 'causer_id' => $migrado->id, 'subject_type' => User::class, 'subject_id' => $migrado->id]);

        $this->migrar();

        $this->assertSame(User::class, DB::table('notifications')->where('id', $doServidor)->value('notifiable_type'));
        $this->assertSame(Cidadao::class, DB::table('notifications')->where('id', $doMigrado)->value('notifiable_type'));

        $servidorLinha = Activity::query()->findOrFail($atividadeServidor->id);
        $this->assertSame(User::class, $servidorLinha->causer_type);
        $this->assertSame(User::class, $servidorLinha->subject_type);

        $migradoLinha = Activity::query()->findOrFail($atividadeMigrado->id);
        $this->assertSame(Cidadao::class, $migradoLinha->causer_type);
        $this->assertSame(Cidadao::class, $migradoLinha->subject_type);
    }

    /** Mesma noção de "conta copiada" da migration final: espelho sem senha e par id igual/CPF diferente não são retipados. */
    public function test_so_retipa_contas_copiadas_com_mesmo_id_e_cpf(): void
    {
        $cidadaoSemUsers = Cidadao::factory()->create(['id' => 9000]);
        $semCopiaDeSenha = User::factory()->create();
        Cidadao::factory()->semSenha()->create(['id' => $semCopiaDeSenha->id, 'cpf' => $semCopiaDeSenha->cpf, 'email' => $semCopiaDeSenha->email]);
        $outroCpf = User::factory()->create();
        Cidadao::factory()->create(['id' => $outroCpf->id, 'cpf' => '52998224725']);
        $copiado = $this->contaCopiada();

        $notificacoes = [];
        foreach ([$cidadaoSemUsers->id, $semCopiaDeSenha->id, $outroCpf->id, $copiado->id] as $id) {
            $notificacoes[$id] = $this->notificar(User::class, $id);
            $this->atividade(['causer_type' => User::class, 'causer_id' => $id, 'subject_type' => User::class, 'subject_id' => $id]);
        }

        $this->migrar();

        $this->assertSame([$copiado->id], DB::table('notifications')->where('notifiable_type', Cidadao::class)->pluck('notifiable_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$copiado->id], DB::table('activity_log')->where('log_name', 'teste')->where('causer_type', Cidadao::class)->pluck('causer_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([$copiado->id], DB::table('activity_log')->where('log_name', 'teste')->where('subject_type', Cidadao::class)->pluck('subject_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_filtro_de_usuario_da_retaguarda_nao_traz_atividades_de_cidadao_com_mesmo_id(): void
    {
        $servidor = User::factory()->create();
        Cidadao::factory()->create(['id' => $servidor->id]);

        $doServidor = $this->atividade(['causer_type' => User::class, 'causer_id' => $servidor->id]);
        $this->atividade(['causer_type' => Cidadao::class, 'causer_id' => $servidor->id]);

        $ids = app(AuditTrailQueryService::class)->filtered(['usuario_id' => $servidor->id])->pluck('id')->all();

        $this->assertSame([$doServidor->id], $ids);
    }
}
