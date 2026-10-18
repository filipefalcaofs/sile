<?php

namespace Tests\Feature\Regin;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Listeners\ComunicarResultadoRegin;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginParecerNotifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * O parecer vai à JUCEB assim que o processo é decidido ou concluído, sem
 * esperar a fila, e toda falha vira pendência auditada que o reenvio agendado
 * retoma.
 */
class ParecerImediatoReginTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            'integrations.regin.em_producao' => ['boolean', '0'],
            'integrations.regin.url_homologacao' => ['string', 'http://10.57.247.9:8080/api_integracao'],
            'integrations.regin.usuario' => ['string', 'sedur_integracao'],
        ] as $key => [$type, $value]) {
            Parameter::factory()->create([
                'key' => $key,
                'group' => 'integracoes',
                'type' => $type,
                'value' => $value,
                'default_value' => $value,
            ]);
        }

        Parameter::factory()->sensitive()->create([
            'key' => 'integrations.regin.senha',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'segredo',
            'default_value' => null,
        ]);

        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-1']),
            '*/recebe' => Http::response('RECEBIDO_SUCESSO'),
        ]);
    }

    private function processoReginEmAnalise(string $protocolo): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-'.$protocolo]);
        $processo->forceFill([
            'origin' => ViabilityRequestOrigin::Regin,
            'external_reference' => $protocolo,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        return $processo->fresh();
    }

    private function contarAuditoria(string $resultado): int
    {
        return Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-parecer')
            ->where('result', $resultado)
            ->count();
    }

    public function test_parecer_sai_na_hora_sem_passar_pela_fila(): void
    {
        Queue::fake();

        $processo = $this->processoReginEmAnalise('BAP100');
        $decisao = ViabilityDecision::factory()->create(['viability_request_id' => $processo->id]);

        event(new ResultadoEmitido($processo, $decisao));

        Queue::assertNotPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === ComunicarResultadoRegin::class,
        );
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/recebe') && $r['protocolo'] === 'BAP100');
        $this->assertSame(1, $this->contarAuditoria('sucesso'));
    }

    public function test_erro_inesperado_no_envio_vira_pendencia_sem_quebrar_o_fluxo(): void
    {
        $this->app->instance(ReginParecerNotifier::class, new class implements ReginParecerNotifier
        {
            public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void
            {
                throw new RuntimeException('falha inesperada');
            }
        });

        $processo = $this->processoReginEmAnalise('BAP101');
        $decisao = ViabilityDecision::factory()->create(['viability_request_id' => $processo->id]);

        event(new ResultadoEmitido($processo, $decisao));

        $this->assertSame(1, $this->contarAuditoria('bloqueado'));
        $this->assertSame(0, $this->contarAuditoria('sucesso'));
    }

    public function test_desativado_cassado_e_revogado_em_analise_enviam_parecer_indeferido(): void
    {
        $analista = User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();

        foreach (['desativado', 'cassado', 'revogado'] as $indice => $resultado) {
            $protocolo = 'BAP20'.$indice;
            $processo = $this->processoReginEmAnalise($protocolo);
            AnalysisRecord::factory()->create([
                'viability_request_id' => $processo->id,
                'revision' => 1,
                'analyst_user_id' => $analista->id,
            ]);

            $this->actingAs($analista, 'gestao')
                ->post("/gestao/processos/{$processo->id}/conclusao-analise", ['resultado' => $resultado])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            Http::assertSent(fn ($r) => str_ends_with($r->url(), '/recebe')
                && $r['protocolo'] === $protocolo
                && $r['dadosProcesso']['ANALISES']['AREA'][0]['STATUS_ANALISE'] === 4);
        }

        $this->assertSame(3, $this->contarAuditoria('sucesso'));
    }

    public function test_encerrar_processo_ja_deferido_nao_reenvia_parecer(): void
    {
        $processo = $this->processoReginEmAnalise('BAP300');
        $processo->forceFill(['status' => ViabilityRequestStatus::Deferida])->save();
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id]);

        $this->actingAs(User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->post("/gestao/processos/{$processo->id}/conclusao-analise", ['resultado' => 'cassado'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/recebe'));
    }

    public function test_reenvio_de_pareceres_esta_agendado(): void
    {
        Artisan::call('schedule:list');

        $this->assertStringContainsString('regin:reenviar-pareceres', Artisan::output());
    }

    public function test_retry_after_da_fila_cobre_o_timeout_do_job_de_decisao(): void
    {
        $this->assertGreaterThan(
            (int) config('viabiliza.expresso.job.timeout'),
            (int) config('queue.connections.redis.retry_after'),
        );
    }
}
