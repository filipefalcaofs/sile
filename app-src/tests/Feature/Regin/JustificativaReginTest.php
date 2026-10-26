<?php

namespace Tests\Feature\Regin;

use App\Enums\AnalysisStatus;
use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\Activity;
use App\Models\Parameter;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A justificativa do parecer à JUCEB vem do banco: o analista a edita na tela do
 * processo e o envio usa o texto gravado. Sem texto gravado, o texto padrão diz o
 * fundamento legal da decisão.
 */
class JustificativaReginTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $decisao
     * @return array{0: ViabilityRequest, 1: ViabilityDecision|null}
     */
    private function processoDeferido(string $protocolo, array $decisao = [], bool $comDecisao = true): array
    {
        $processo = ViabilityRequest::factory()->protocoled()->create(['protocol_number' => 'VIA-'.$protocolo]);
        $processo->forceFill([
            'origin' => ViabilityRequestOrigin::Regin,
            'external_reference' => $protocolo,
            'status' => ViabilityRequestStatus::Deferida,
            'analysis_status' => AnalysisStatus::AnaliseConcluida,
        ])->save();

        $decision = $comDecisao
            ? ViabilityDecision::factory()->create(array_merge([
                'viability_request_id' => $processo->id,
                'outcome' => DecisionOutcome::Deferida,
                'reason' => null,
                'fundamentacao' => [],
            ], $decisao))
            : null;

        return [$processo->fresh(), $decision];
    }

    private function analistaServidor(): User
    {
        return User::factory()->analistaServidor()->withAcceptedLgpdTerm()->create();
    }

    private function url(ViabilityRequest $processo): string
    {
        return "/gestao/processos/{$processo->id}/justificativa-regin";
    }

    public function test_envio_usa_a_justificativa_gravada_no_processo(): void
    {
        [$processo, $decisao] = $this->processoDeferido('BAP9000001', [
            'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — nR2-12'],
        ]);
        $processo->forceFill(['regin_justificativa' => 'Texto escrito pelo analista no processo.'])->save();

        event(new ResultadoEmitido($processo->fresh(), $decisao));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/recebe')
            && $r['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE'] === 'Texto escrito pelo analista no processo.');
    }

    public function test_texto_padrao_sem_motivo_nao_leva_fundamento_nem_condicionantes(): void
    {
        [$processo, $decisao] = $this->processoDeferido('BAP9000002', [
            'fundamentacao' => [
                'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
                'Decreto Municipal nº 32.636/2020, com os Anexos na redação do Decreto Municipal nº 41.758/2026',
                'Lei nº 9.148/2016 (LOUOS) — nR2-12',
                'GeoServer SEDUR indisponível ou resposta incompleta (HTTP 20).',
                'Desde que seja escritório da empresa',
                'Desde que a área utilizada não ultrapasse 1.250m² (mil duzentos e cinquenta metros quadrados).',
            ],
        ]);

        event(new ResultadoEmitido($processo, $decisao));

        $esperado = 'Viabilidade deferida pelo fluxo expresso.';

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/recebe')
            && $r['dadosProcesso']['ANALISES']['AREA'][0]['JUSTIFICATIVA_ANALISE'] === $esperado);
    }

    public function test_analista_servidor_grava_a_justificativa_e_a_alteracao_fica_auditada(): void
    {
        [$processo] = $this->processoDeferido('BAP9000003');

        $this->actingAs($this->analistaServidor(), 'gestao')
            ->put($this->url($processo), ['justificativa' => '  Deferida: a atividade é admitida pelo enquadramento nR2-12 da Lei nº 9.148/2016.  '])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'Deferida: a atividade é admitida pelo enquadramento nR2-12 da Lei nº 9.148/2016.',
            $processo->fresh()->regin_justificativa,
        );
        $this->assertSame(1, Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'justificativa-regin')
            ->where('subject_id', $processo->id)
            ->count());
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/recebe'));
    }

    public function test_perfil_sem_concluir_analise_nao_edita_a_justificativa(): void
    {
        [$processo] = $this->processoDeferido('BAP9000005');

        $this->actingAs(User::factory()->analista()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->put($this->url($processo), ['justificativa' => 'Tentativa sem permissão.'])
            ->assertForbidden();

        $this->assertNull($processo->fresh()->regin_justificativa);
    }

    public function test_justificativa_em_branco_ou_acima_de_10000_caracteres_e_recusada(): void
    {
        [$processo] = $this->processoDeferido('BAP9000006');
        $analista = $this->analistaServidor();

        $this->actingAs($analista, 'gestao')
            ->put($this->url($processo), ['justificativa' => '   '])
            ->assertSessionHasErrors('justificativa');

        $this->actingAs($analista, 'gestao')
            ->put($this->url($processo), ['justificativa' => str_repeat('a', 10001)])
            ->assertSessionHasErrors('justificativa');

        $this->assertNull($processo->fresh()->regin_justificativa);
    }

    public function test_processo_sem_decisao_nao_recebe_justificativa(): void
    {
        [$processo] = $this->processoDeferido('BAP9000007', comDecisao: false);
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        $this->actingAs($this->analistaServidor(), 'gestao')
            ->put($this->url($processo), ['justificativa' => 'Ainda sem decisão.'])
            ->assertSessionHasErrors('justificativa');

        $this->assertNull($processo->fresh()->regin_justificativa);
    }

    public function test_tela_do_processo_expoe_o_texto_que_vai_para_a_juceb(): void
    {
        [$processo] = $this->processoDeferido('BAP9000008', [
            'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — nR2-12'],
        ]);

        $this->actingAs($this->analistaServidor(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/processos/show')
                ->where('justificativaRegin.personalizada', false)
                ->where('justificativaRegin.texto', 'Viabilidade deferida pelo fluxo expresso.'));

        $processo->forceFill(['regin_justificativa' => 'Texto do analista.'])->save();

        $this->actingAs($this->analistaServidor(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('justificativaRegin.personalizada', true)
                ->where('justificativaRegin.texto', 'Texto do analista.'));
    }

    public function test_depois_que_a_juceb_confirma_o_parecer_a_justificativa_nao_muda(): void
    {
        [$processo, $decisao] = $this->processoDeferido('BAP9000009');
        $processo->forceFill(['regin_justificativa' => 'Texto enviado.'])->save();

        event(new ResultadoEmitido($processo->fresh(), $decisao));

        $this->actingAs($this->analistaServidor(), 'gestao')
            ->put($this->url($processo), ['justificativa' => 'Troca depois do envio.'])
            ->assertSessionHasErrors('justificativa');

        $this->assertSame('Texto enviado.', $processo->fresh()->regin_justificativa);

        $this->actingAs($this->analistaServidor(), 'gestao')
            ->get("/gestao/processos/{$processo->id}")
            ->assertInertia(fn (Assert $page) => $page->where('justificativaRegin.enviada', true));
    }

    public function test_parecer_sem_recebimento_confirmado_continua_editavel(): void
    {
        [$processo] = $this->processoDeferido('BAP9000010');

        app(AuditService::class)->log(
            logName: 'integracoes',
            event: 'regin-parecer',
            description: 'Comunicação do parecer ao Regin/Junta pendente',
            subject: $processo,
            result: 'bloqueado',
        );

        $this->actingAs($this->analistaServidor(), 'gestao')
            ->put($this->url($processo), ['justificativa' => 'Ainda pode corrigir.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ainda pode corrigir.', $processo->fresh()->regin_justificativa);
    }
}
