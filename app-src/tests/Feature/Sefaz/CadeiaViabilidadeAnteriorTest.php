<?php

namespace Tests\Feature\Sefaz;

use App\Enums\DecisionOutcome;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Sefaz\ConsultarComparacaoCnaesService;
use App\Services\Sefaz\SefazConsultaIndisponivelException;
use App\Services\Sefaz\SefazViabilidadeConsultaGateway;
use App\Services\Sefaz\UnavailableSefazViabilidadeConsultaGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Sefaz\FakeSefazViabilidadeConsultaGateway;
use Tests\TestCase;

/**
 * Base da comparação de atividades: o processo anterior da empresa no próprio
 * Viabiliza vem primeiro, depois o legado e, por último, a SEFAZ.
 */
class CadeiaViabilidadeAnteriorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CNPJ = '12345678000190';

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.sefaz.consulta.ativa' => true]);
        $this->app->instance(SefazViabilidadeConsultaGateway::class, new UnavailableSefazViabilidadeConsultaGateway);
    }

    /**
     * @param  list<string>  $codigos
     */
    private function processo(array $codigos, string $cnpj = self::CNPJ, bool $alteracaoDaJunta = false): ViabilityRequest
    {
        $empresa = Company::query()->where('cnpj', $cnpj)->first() ?? Company::factory()->create(['cnpj' => $cnpj]);
        $request = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $empresa->id,
            'protocol_number' => 'VIA-2026-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
        ]);

        foreach ($codigos as $indice => $codigo) {
            $cnae = Cnae::query()->firstOrCreate(['code' => $codigo], Cnae::factory()->make(['code' => $codigo])->toArray());
            $request->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        if ($alteracaoDaJunta) {
            ViabilityRequestEvento::factory()->create(['viability_request_id' => $request->id, 'codigo' => '244', 'nome' => 'Evento']);
        }

        return $request->fresh();
    }

    private function decidir(ViabilityRequest $processo, DecisionOutcome $resultado = DecisionOutcome::Deferida, ?string $tvl = null): void
    {
        ViabilityDecision::factory()->create([
            'viability_request_id' => $processo->id,
            'outcome' => $resultado,
            'tvl_product_number' => $tvl ?? 'TVL-2026-'.str_pad((string) fake()->unique()->numberBetween(1000, 999999), 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function test_usa_o_processo_deferido_anterior_da_mesma_empresa(): void
    {
        $anterior = $this->processo(['4711302', '4712100']);
        $this->decidir($anterior, tvl: 'TVL-2026-000100');
        $atual = $this->processo(['4711302', '5611201'], alteracaoDaJunta: true);

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($atual);

        $this->assertSame(['5611201'], $resultado?->incluidas);
        $this->assertSame(['4712100'], $resultado?->excluidas);
        $this->assertSame(['4711302'], $resultado?->mantidas);
        $this->assertFalse($resultado->semViabilidade);
        $this->assertSame('TVL-2026-000100', $atual->fresh()->comparacao_cnaes['numero_viabilidade']);
    }

    public function test_ignora_processo_indeferido_de_outra_empresa_e_o_proprio_pedido(): void
    {
        $this->decidir($this->processo(['4712100']), DecisionOutcome::Indeferida);
        $this->decidir($this->processo(['5611201'], cnpj: '00000000000191'));
        $atual = $this->processo(['4711302'], alteracaoDaJunta: true);
        $this->decidir($atual);

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($atual);

        $this->assertTrue($resultado?->semViabilidade);
        $this->assertSame(['4711302'], $resultado->incluidas);
    }

    public function test_processo_anterior_sem_cnaes_nao_serve_de_base(): void
    {
        $this->decidir($this->processo([]));
        $atual = $this->processo(['4711302'], alteracaoDaJunta: true);

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($atual);

        $this->assertTrue($resultado?->semViabilidade);
    }

    public function test_usa_o_mais_recente_quando_a_empresa_tem_varios(): void
    {
        $antigo = $this->processo(['4712100']);
        $this->decidir($antigo, tvl: 'TVL-2026-000001');
        $recente = $this->processo(['4711302']);
        $this->decidir($recente, tvl: 'TVL-2026-000002');
        $atual = $this->processo(['4711302'], alteracaoDaJunta: true);

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($atual);

        $this->assertSame([], $resultado?->incluidas);
        $this->assertSame('TVL-2026-000002', $atual->fresh()->comparacao_cnaes['numero_viabilidade']);
    }

    public function test_sem_processo_anterior_cai_para_a_sefaz(): void
    {
        $this->app->instance(
            SefazViabilidadeConsultaGateway::class,
            (new FakeSefazViabilidadeConsultaGateway)->com(self::CNPJ, 'VIA-2026-1', ['4711-3/02']),
        );
        $atual = $this->processo(['4711302', '5611201'], alteracaoDaJunta: true);

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($atual);

        $this->assertSame(['5611201'], $resultado?->incluidas);
        $this->assertSame('VIA-2026-1', $atual->fresh()->comparacao_cnaes['numero_viabilidade']);
    }

    public function test_sem_nenhuma_fonte_disponivel_nao_espera_nem_falha(): void
    {
        // Legado e SEFAZ ainda sem integração: não vira espera de 24 h nem exceção.
        $atual = $this->processo(['4711302'], alteracaoDaJunta: true);

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($atual);

        $this->assertTrue($resultado?->semViabilidade);
        $this->assertSame(['4711302'], $resultado->incluidas);
    }

    public function test_sefaz_no_ar_mas_com_falha_continua_propagando_a_excecao(): void
    {
        $this->app->instance(
            SefazViabilidadeConsultaGateway::class,
            (new FakeSefazViabilidadeConsultaGateway)->foraDoAr(self::CNPJ),
        );
        $atual = $this->processo(['4711302'], alteracaoDaJunta: true);

        $this->expectException(SefazConsultaIndisponivelException::class);

        app(ConsultarComparacaoCnaesService::class)->executar($atual);
    }

    public function test_auditoria_informa_de_onde_veio_a_base(): void
    {
        $this->decidir($this->processo(['4712100']));
        $atual = $this->processo(['4711302'], alteracaoDaJunta: true);

        app(ConsultarComparacaoCnaesService::class)->executar($atual);

        $auditoria = Activity::query()->where('event', 'sefaz-consulta-viabilidade')->where('subject_id', $atual->id)->sole();
        $this->assertSame('viabiliza', $auditoria->properties['fonte']);
    }
}
