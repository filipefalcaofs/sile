<?php

namespace Tests\Feature\Relatorios;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SaturacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Capacidades e limiares via fallback de config (Settings cai no config
        // quando não há parâmetro no banco). Flush evita cache entre testes.
        Cache::flush();
        config([
            'sile.relatorios.saturacao.capacidades' => ['4712100' => 2, '5611201' => 5],
            'sile.relatorios.saturacao.alerta_percentual' => 80,
            'sile.relatorios.saturacao.bloqueio_percentual' => 100,
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

    private function deferidaComCnae(string $bairro, Cnae $cnae): void
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        $request = ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
            'address_neighborhood' => $bairro,
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
}
