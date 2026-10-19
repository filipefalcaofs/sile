<?php

namespace Tests\Feature\Expresso;

use App\Enums\ViabilityRequestStatus;
use App\Events\EncaminhadoParaAnalise;
use App\Events\ResultadoEmitido;
use App\Models\DecisionText;
use App\Models\ExpressoQueda;
use App\Models\ViabilityRequest;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Solicitacao\ResolvedViability;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Os motivos do encaminhamento à análise saem do catálogo de textos
 * decisórios e ficam registrados no processo (transição e queda).
 */
class ExpressoMotivosCatalogoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_textos_de_fabrica_dos_motivos_do_expresso(): void
    {
        $defaults = DecisionTextCatalog::defaults();

        $this->assertSame('fluxo expresso desativado', $defaults['expresso.motivo.desativado']);
        $this->assertSame('atividade de alto risco — análise técnica', $defaults['expresso.motivo.alto_risco']);
        $this->assertSame('atividade fora do fluxo expresso (análise técnica)', $defaults['expresso.motivo.fora_do_expresso']);
        $this->assertSame('veredito locacional pendente — :motivo', $defaults['expresso.motivo.veredito_pendente']);
        $this->assertSame('zona urbanística pendente SEDUR', $defaults['expresso.motivo.zona_pendente']);
        $this->assertSame(
            'exclusão do CNAE gatilho sem inscrição imobiliária informada — encaminhado para verificação',
            $defaults['expresso.motivo.exclusao_sede_sem_inscricao'],
        );
        $this->assertSame(
            'exclusão do CNAE gatilho da sede pedida por solicitação que não é a titular do vínculo de sede — encaminhado para verificação',
            $defaults['expresso.motivo.exclusao_sede_nao_titular'],
        );
    }

    public function test_motivo_editado_no_catalogo_fica_registrado_no_processo(): void
    {
        config(['viabiliza.features.fluxo_expresso' => false]);
        Event::fake([ResultadoEmitido::class]);

        DecisionText::query()->create([
            'key' => 'expresso.motivo.desativado',
            'template' => 'Fluxo expresso suspenso pela gestão',
            'description' => 'Edição administrada',
        ]);

        $request = ViabilityRequest::factory()->protocoled()->create();

        $result = app(FluxoExpressoService::class)->decide($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertSame('Fluxo expresso suspenso pela gestão', ExpressoQueda::query()->firstOrFail()->motivo);
        $this->assertSame(
            'Fluxo expresso suspenso pela gestão',
            $request->transitions()->latest('id')->firstOrFail()->reason,
        );
    }

    public function test_veredito_pendente_registra_o_motivo_do_ramo_no_processo(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);

        $resolved = new ResolvedViability(
            por_cnae: [[
                'cnae' => '0111301',
                'cnae_formatado' => '0111-3/01',
                'is_primary' => true,
                'tendencia' => 'pendente',
                'tendencia_label' => 'Pendente de análise técnica',
                'veredito_motivo' => 'Atividade sem enquadramento na planilha vigente — segue para análise técnica',
                'enquadramento_motivo' => 'Pergunta 11 sem resposta — A atividade será exercida no local?',
                'fluxo' => 'expresso',
                'consulta_array' => ['risco' => ['encaminhamento' => ['fluxo' => 'expresso']]],
            ]],
            consolidado: 'pendente',
            rules_versions: [],
            ponto: null,
            area_m2: 40.0,
        );

        $this->app->instance(SolicitacaoViabilityResolver::class, new class($resolved) extends SolicitacaoViabilityResolver
        {
            public function __construct(private ResolvedViability $stub) {}

            public function resolve(ViabilityRequest $request): ResolvedViability
            {
                return $this->stub;
            }
        });

        $request = ViabilityRequest::factory()->protocoled()->create(['used_area_m2' => 40.0]);

        $result = app(FluxoExpressoService::class)->decide($request);

        $esperado = 'veredito locacional pendente — Pergunta 11 sem resposta — A atividade será exercida no local?';

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $result->status);
        $this->assertSame($esperado, $request->transitions()->latest('id')->firstOrFail()->reason);
        $this->assertSame($esperado, ExpressoQueda::query()->firstOrFail()->motivo);
    }
}
