<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Observatório de Saturação Locacional (Módulo 2): compara a quantidade de
 * estabelecimentos DEFERIDOS por bairro×CNAE com a capacidade recomendada
 * (parâmetro HU-014 administrável) e classifica ok/saturando/saturado pelos
 * limiares parametrizados. Degradação HONESTA: CNAE sem capacidade definida vira
 * "sem_capacidade" — nunca inventa saturação. Recorte por bairro enquanto a zona
 * oficial (GIS) está pendente na SEDUR.
 */
class SaturacaoServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Capacidades e limiares via fallback de config (Settings cai no config
        // quando não há parâmetro no banco). Flush evita cache entre testes.
        Cache::flush();
        config([
            'viabiliza.relatorios.saturacao.capacidades' => ['4712100' => 2, '5611201' => 5],
            'viabiliza.relatorios.saturacao.alerta_percentual' => 80,
            'viabiliza.relatorios.saturacao.bloqueio_percentual' => 100,
        ]);
    }

    private function service(): SaturacaoService
    {
        return app(SaturacaoService::class);
    }

    private function filtros(array $bag = []): ReportFilters
    {
        return ReportFilters::fromArray($bag);
    }

    private function deferidaComCnae(string $bairro, Cnae $cnae, ?string $bairroOficial = null): void
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
            'address_neighborhood' => $bairro,
            'bairro_oficial' => $bairroOficial,
        ]);

        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
            'decided_at' => now(),
            'tvl_product_number' => null,
        ]);
    }

    public function test_classifica_saturado_saturando_ok_e_sem_capacidade(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);   // capacidade 2
        $restaurante = Cnae::factory()->create(['code' => '5611201']); // capacidade 5
        $semCapacidade = Cnae::factory()->create(['code' => '3333333']);

        // Pituba × comércio: 3 deferidos / capacidade 2 = 150% -> saturado
        $this->deferidaComCnae('Pituba', $comercio);
        $this->deferidaComCnae('Pituba', $comercio);
        $this->deferidaComCnae('Pituba', $comercio);

        // Pituba × restaurante: 4 deferidos / capacidade 5 = 80% -> saturando
        $this->deferidaComCnae('Pituba', $restaurante);
        $this->deferidaComCnae('Pituba', $restaurante);
        $this->deferidaComCnae('Pituba', $restaurante);
        $this->deferidaComCnae('Pituba', $restaurante);

        // Barra × comércio: 1 deferido / capacidade 2 = 50% -> ok
        $this->deferidaComCnae('Barra', $comercio);

        // Itapuã × CNAE sem capacidade -> sem_capacidade
        $this->deferidaComCnae('Itapuã', $semCapacidade);

        $linhas = collect($this->service()->porBairroCnae($this->filtros()));

        $saturado = $linhas->first(fn ($l) => $l['bairro'] === 'Pituba' && $l['cnae'] === '4712100');
        $this->assertSame(3, $saturado['ativos']);
        $this->assertSame(2, $saturado['capacidade']);
        $this->assertSame(150.0, $saturado['percentual']);
        $this->assertSame('saturado', $saturado['situacao']);

        $saturando = $linhas->first(fn ($l) => $l['bairro'] === 'Pituba' && $l['cnae'] === '5611201');
        $this->assertSame(80.0, $saturando['percentual']);
        $this->assertSame('saturando', $saturando['situacao']);

        $ok = $linhas->first(fn ($l) => $l['bairro'] === 'Barra' && $l['cnae'] === '4712100');
        $this->assertSame(50.0, $ok['percentual']);
        $this->assertSame('ok', $ok['situacao']);

        $sem = $linhas->first(fn ($l) => $l['cnae'] === '3333333');
        $this->assertNull($sem['capacidade']);
        $this->assertNull($sem['percentual']);
        $this->assertSame('sem_capacidade', $sem['situacao']);
    }

    public function test_resumo_conta_situacoes(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);   // capacidade 2
        $semCapacidade = Cnae::factory()->create(['code' => '3333333']);

        $this->deferidaComCnae('Pituba', $comercio);
        $this->deferidaComCnae('Pituba', $comercio);
        $this->deferidaComCnae('Pituba', $comercio); // saturado (150%)
        $this->deferidaComCnae('Barra', $comercio);  // ok (50%)
        $this->deferidaComCnae('Itapuã', $semCapacidade); // sem_capacidade

        $resumo = $this->service()->resumo($this->filtros());

        $this->assertSame(3, $resumo['pares_avaliados']);
        $this->assertSame(1, $resumo['saturados']);
        $this->assertSame(1, $resumo['sem_capacidade']);
    }

    public function test_apenas_deferidos_contam_como_ativos(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);

        // Protocolada SEM decisão deferida não conta como estabelecimento ativo.
        $protocolo = 'VIA-'.now()->year.'-PEND';
        $req = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
            'address_neighborhood' => 'Pituba',
        ]);
        $req->cnaes()->attach($comercio->id, ['is_primary' => true]);

        $linhas = $this->service()->porBairroCnae($this->filtros());

        $this->assertSame([], $linhas);
    }

    public function test_agrupa_pelo_bairro_oficial_com_o_digitado_de_reserva(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);

        $this->deferidaComCnae('Pituba', $comercio, 'PITUBA');
        $this->deferidaComCnae('Pitub', $comercio, 'PITUBA');
        $this->deferidaComCnae('Barra', $comercio);

        $linhas = collect($this->service()->porBairroCnae($this->filtros()));

        $this->assertSame(['PITUBA', 'Barra'], $linhas->pluck('bairro')->all());
        $this->assertSame(2, $linhas->firstWhere('bairro', 'PITUBA')['ativos']);
        $this->assertSame(1, $linhas->firstWhere('bairro', 'Barra')['ativos']);
    }

    public function test_filtro_de_bairro_usa_o_bairro_oficial_quando_existe(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);

        $this->deferidaComCnae('Ondina', $comercio, 'RIO VERMELHO');
        $this->deferidaComCnae('Barra', $comercio);

        $porOficial = $this->service()->porBairroCnae($this->filtros(['bairro' => 'rio vermelho']));
        $porDigitadoSubstituido = $this->service()->porBairroCnae($this->filtros(['bairro' => 'Ondina']));
        $porDigitadoSemOficial = $this->service()->porBairroCnae($this->filtros(['bairro' => 'barra']));

        $this->assertSame(['RIO VERMELHO'], array_column($porOficial, 'bairro'));
        $this->assertSame([], $porDigitadoSubstituido);
        $this->assertSame(['Barra'], array_column($porDigitadoSemOficial, 'bairro'));
    }

    public function test_filtro_de_cnae_restringe_pares_e_resumo(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);
        $restaurante = Cnae::factory()->create(['code' => '5611201']);

        $this->deferidaComCnae('Pituba', $comercio);
        $this->deferidaComCnae('Pituba', $restaurante);
        $this->deferidaComCnae('Barra', $restaurante);

        $filtros = $this->filtros(['cnae' => '4712-1/00']);

        $this->assertSame(['4712100'], array_values(array_unique(array_column($this->service()->porBairroCnae($filtros), 'cnae'))));
        $this->assertSame(1, $this->service()->resumo($filtros)['pares_avaliados']);
        $this->assertSame(['4712100'], array_column($this->service()->painel($filtros)['maioresSaturacoes'], 'cnae'));
    }

    public function test_resumo_conta_todos_os_pares_mesmo_alem_do_limite_da_tabela(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);     // capacidade 2
        $restaurante = Cnae::factory()->create(['code' => '5611201']);  // capacidade 5

        // Par de maior volume, mas sem saturação: 3/5 = 60%.
        $this->deferidaComCnae('Pituba', $restaurante);
        $this->deferidaComCnae('Pituba', $restaurante);
        $this->deferidaComCnae('Pituba', $restaurante);

        // Par pequeno e saturado: 2/2 = 100%.
        $this->deferidaComCnae('Barra', $comercio);
        $this->deferidaComCnae('Barra', $comercio);

        $painel = $this->service()->painel($this->filtros(), limiteTabela: 1);

        $this->assertCount(1, $painel['porBairroCnae']);
        $this->assertSame('Pituba', $painel['porBairroCnae'][0]['bairro']);
        $this->assertSame(2, $painel['resumo']['pares_avaliados']);
        $this->assertSame(1, $painel['resumo']['saturados']);
        $this->assertSame('Barra', $painel['maioresSaturacoes'][0]['bairro']);
    }

    public function test_maiores_saturacoes_ordena_pelo_percentual_e_ignora_sem_capacidade(): void
    {
        $comercio = Cnae::factory()->create(['code' => '4712100']);      // capacidade 2
        $restaurante = Cnae::factory()->create(['code' => '5611201']);   // capacidade 5
        $semCapacidade = Cnae::factory()->create(['code' => '3333333']);

        foreach (range(1, 4) as $i) {
            $this->deferidaComCnae('Pituba', $restaurante); // 80%
        }
        $this->deferidaComCnae('Barra', $comercio);
        $this->deferidaComCnae('Barra', $comercio);
        $this->deferidaComCnae('Barra', $comercio);         // 150%
        foreach (range(1, 6) as $i) {
            $this->deferidaComCnae('Itapuã', $semCapacidade);
        }
        $this->deferidaComCnae('Ondina', $comercio);        // 50%

        $maiores = $this->service()->painel($this->filtros())['maioresSaturacoes'];

        $this->assertSame([150.0, 80.0, 50.0], array_column($maiores, 'percentual'));
    }
}
