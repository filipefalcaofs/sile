<?php

namespace Tests\Feature\Sefaz;

use App\Models\Cnae;
use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Sefaz\ConsultarComparacaoCnaesService;
use App\Services\Sefaz\SefazConsultaIndisponivelException;
use App\Services\Sefaz\SefazViabilidadeConsultaGateway;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Sefaz\FakeSefazViabilidadeConsultaGateway;
use Tests\TestCase;

class ConsultarComparacaoCnaesServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CNPJ = '12345678000190';

    private FakeSefazViabilidadeConsultaGateway $sefaz;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.sefaz.consulta.ativa' => true]);

        $this->sefaz = (new FakeSefazViabilidadeConsultaGateway)
            ->com(self::CNPJ, 'VIA-2026-1', ['4711-3/02'])
            ->foraDoAr('99999999000199');
        $this->app->instance(SefazViabilidadeConsultaGateway::class, $this->sefaz);
    }

    /**
     * @param  list<string>  $codigos
     */
    private function pedidoDaJunta(array $codigos, string $cnpj = self::CNPJ, ?string $intencao = null, string $evento = '244'): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => Company::factory()->create(['cnpj' => $cnpj])->id,
        ]);

        foreach ($codigos as $indice => $codigo) {
            $cnae = Cnae::query()->firstOrCreate(['code' => $codigo], Cnae::factory()->make(['code' => $codigo])->toArray());
            $request->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0, 'intencao' => $intencao]);
        }

        ViabilityRequestEvento::factory()->create(['viability_request_id' => $request->id, 'codigo' => $evento, 'nome' => 'Evento']);

        return $request->fresh();
    }

    public function test_pedido_da_junta_grava_a_comparacao(): void
    {
        $request = $this->pedidoDaJunta(['4711302', '5611201']);

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($request);

        $this->assertSame(['5611201'], $resultado?->incluidas);
        $this->assertSame(['4711302'], $resultado?->mantidas);
        $this->assertSame('VIA-2026-1', $request->fresh()->comparacao_cnaes['numero_viabilidade']);
        $this->assertSame(['5611201'], $request->fresh()->comparacao_cnaes['incluidas']);
    }

    public function test_com_a_consulta_desligada_nao_faz_nada(): void
    {
        config(['viabiliza.sefaz.consulta.ativa' => false]);
        $request = $this->pedidoDaJunta(['4711302']);

        $this->assertNull(app(ConsultarComparacaoCnaesService::class)->executar($request));
        $this->assertNull($request->fresh()->comparacao_cnaes);
    }

    public function test_pedido_do_portal_com_intencao_nao_consulta(): void
    {
        $request = $this->pedidoDaJunta(['4711302'], intencao: 'incluir');

        $this->assertNull(app(ConsultarComparacaoCnaesService::class)->executar($request));
        $this->assertNull($request->fresh()->comparacao_cnaes);
    }

    public function test_pedido_que_nao_e_alteracao_da_junta_nao_consulta(): void
    {
        $request = $this->pedidoDaJunta(['4711302'], evento: '101');

        $this->assertNull(app(ConsultarComparacaoCnaesService::class)->executar($request));
    }

    public function test_registra_auditoria_da_consulta_e_da_comparacao(): void
    {
        $request = $this->pedidoDaJunta(['4711302', '5611201']);

        app(ConsultarComparacaoCnaesService::class)->executar($request);

        $auditoria = Activity::query()->where('event', 'sefaz-consulta-viabilidade')->where('subject_id', $request->id)->sole();
        $this->assertSame(['5611201'], $auditoria->properties['incluidas']);
        $this->assertSame('VIA-2026-1', $auditoria->properties['numero_viabilidade']);
    }

    public function test_e_idempotente_e_nao_consulta_de_novo_se_ja_gravada(): void
    {
        $request = $this->pedidoDaJunta(['4711302', '5611201']);
        $servico = app(ConsultarComparacaoCnaesService::class);
        $servico->executar($request);

        $this->sefaz->foraDoAr(self::CNPJ);

        $resultado = $servico->executar($request->fresh());

        $this->assertSame(['5611201'], $resultado?->incluidas);
        $this->assertSame(1, Activity::query()->where('event', 'sefaz-consulta-viabilidade')->count());
    }

    public function test_refazer_consulta_de_novo(): void
    {
        $request = $this->pedidoDaJunta(['4711302', '5611201']);
        $servico = app(ConsultarComparacaoCnaesService::class);
        $servico->executar($request);

        $this->sefaz->com(self::CNPJ, 'VIA-2026-1', ['4711-3/02', '5611-2/01']);

        $resultado = $servico->executar($request->fresh(), refazer: true);

        $this->assertSame([], $resultado?->incluidas);
    }

    public function test_cnpj_sem_viabilidade_trata_tudo_como_incluido(): void
    {
        $request = $this->pedidoDaJunta(['4711302'], cnpj: '00000000000191');

        $resultado = app(ConsultarComparacaoCnaesService::class)->executar($request);

        $this->assertTrue($resultado?->semViabilidade);
        $this->assertSame(['4711302'], $resultado?->incluidas);
    }

    public function test_sefaz_indisponivel_propaga_a_excecao_sem_gravar(): void
    {
        $request = $this->pedidoDaJunta(['4711302'], cnpj: '99999999000199');

        try {
            app(ConsultarComparacaoCnaesService::class)->executar($request);
            $this->fail('Esperava SefazConsultaIndisponivelException');
        } catch (SefazConsultaIndisponivelException) {
            $this->assertNull($request->fresh()->comparacao_cnaes);
        }
    }
}
