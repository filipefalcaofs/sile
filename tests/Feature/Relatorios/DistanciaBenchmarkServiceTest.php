<?php

namespace Tests\Feature\Relatorios;

use App\Models\BenchmarkIngestion;
use App\Models\BenchmarkMunicipalityMetric;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\DistanciaBenchmarkService;
use App\Services\Relatorios\ReportFilters;
use App\Support\Capitais;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Distância entre o que o VIABILIZA mede agora e a referência federal.
 *
 * Os dois lados vêm em horas úteis de viabilidade. A ressalva que identifica
 * as duas réguas é devolvida PELO SERVIÇO, junto do número — a tela não tem
 * como renderizar um sem o outro.
 */
class DistanciaBenchmarkServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): DistanciaBenchmarkService
    {
        return app(DistanciaBenchmarkService::class);
    }

    private function filtros(): ReportFilters
    {
        return ReportFilters::fromArray(['data_de' => '2026-03-01', 'data_ate' => '2026-03-31']);
    }

    private function processoInternoCom(float $horas): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse('2026-03-03 00:00:00')->subSeconds((int) round($horas * 3600)),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse('2026-03-03 00:00:00'),
        ]);

        // A comparação com a REDESIM usa horas corridas; as horas úteis
        // gravadas são outras de propósito, para provar que não entram.
        $request->forceFill(['viabilidade_horas_uteis' => 999.0])->save();
    }

    private function referenciaCom(float $media): void
    {
        foreach (Capitais::ufs() as $uf) {
            BenchmarkIngestion::factory()->create(['uf' => $uf, 'ano' => 2026, 'mes' => 8]);
        }

        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => 2026, 'mes' => 8, 'media' => $media,
        ]);
    }

    public function test_compara_o_tempo_interno_com_a_referencia_da_competencia(): void
    {
        $this->processoInternoCom(2.0);
        $this->referenciaCom(20.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(2.0, $distancia['interno'], 0.01);
        $this->assertEqualsWithDelta(20.0, $distancia['referencia'], 0.01);
        $this->assertEqualsWithDelta(-18.0, $distancia['diferenca_horas'], 0.01);
        $this->assertEqualsWithDelta(-90.0, $distancia['diferenca_percentual'], 0.01);
        $this->assertSame(['ano' => 2026, 'mes' => 8], $distancia['competencia']);
    }

    public function test_tempo_interno_maior_que_a_referencia_produz_diferenca_positiva(): void
    {
        $this->processoInternoCom(30.0);
        $this->referenciaCom(20.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(10.0, $distancia['diferenca_horas'], 0.01);
        $this->assertEqualsWithDelta(50.0, $distancia['diferenca_percentual'], 0.01);
    }

    public function test_sem_competencia_de_referencia_nao_calcula_diferenca(): void
    {
        $this->processoInternoCom(2.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertNull($distancia['referencia']);
        $this->assertNull($distancia['diferenca_horas']);
        $this->assertNull($distancia['diferenca_percentual']);
        $this->assertNull($distancia['competencia']);
        $this->assertNotNull($distancia['motivo']);
    }

    public function test_sem_processo_interno_no_periodo_nao_calcula_diferenca(): void
    {
        $this->referenciaCom(20.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertNull($distancia['interno']);
        $this->assertNull($distancia['diferenca_horas']);
        $this->assertNotNull($distancia['motivo']);
    }

    public function test_referencia_zero_nao_produz_divisao_por_zero(): void
    {
        $this->processoInternoCom(2.0);
        $this->referenciaCom(0.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(2.0, $distancia['diferenca_horas'], 0.01);
        $this->assertNull($distancia['diferenca_percentual']);
    }

    public function test_devolve_a_ressalva_metodologica_junto_do_numero(): void
    {
        $this->processoInternoCom(2.0);
        $this->referenciaCom(20.0);

        $ressalva = $this->service()->calcular($this->filtros())['ressalva'];

        $this->assertNotEmpty($ressalva);
        $this->assertStringContainsString('REDESIM', $ressalva);
        $this->assertStringContainsString('VIABILIZA', $ressalva);
    }

    public function test_a_ressalva_acompanha_tambem_o_resultado_sem_referencia(): void
    {
        $this->processoInternoCom(2.0);

        $this->assertNotEmpty($this->service()->calcular($this->filtros())['ressalva']);
    }

    private function processoDecididoEm(float $horas, string $decididoEm): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($decididoEm)->subSeconds((int) round($horas * 3600)),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => 999.0])->save();
    }

    private function redesimDeSalvador(int $ano, int $mes, float $media): void
    {
        BenchmarkMunicipalityMetric::factory()->salvador()->create([
            'ano' => $ano, 'mes' => $mes, 'media' => $media, 'n' => 600,
        ]);
    }

    public function test_evolucao_compara_o_mes_atual_do_viabiliza_com_o_mes_anterior_da_redesim(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);
        $this->processoDecididoEm(2.0, '2026-10-05 10:00:00');
        $this->processoDecididoEm(4.0, '2026-10-06 10:00:00');
        $this->processoDecididoEm(90.0, '2026-09-20 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertSame(['ano' => 2026, 'mes' => 9], [
            'ano' => $evolucao['anterior']['ano'], 'mes' => $evolucao['anterior']['mes'],
        ]);
        $this->assertEqualsWithDelta(21.0, $evolucao['anterior']['media'], 0.01);
        $this->assertSame(['ano' => 2026, 'mes' => 10], [
            'ano' => $evolucao['atual']['ano'], 'mes' => $evolucao['atual']['mes'],
        ]);
        $this->assertEqualsWithDelta(3.0, $evolucao['atual']['media'], 0.01);
        $this->assertSame(2, $evolucao['atual']['n']);
        $this->assertEqualsWithDelta(-18.0, $evolucao['diferenca_horas'], 0.01);
        $this->assertEqualsWithDelta(-85.71, $evolucao['diferenca_percentual'], 0.01);
        $this->assertSame(DistanciaBenchmarkService::RESSALVA, $evolucao['ressalva']);
    }

    public function test_evolucao_atravessa_a_virada_do_ano(): void
    {
        $this->redesimDeSalvador(2025, 12, 30.0);
        $this->processoDecididoEm(6.0, '2026-01-10 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-01-15'));

        $this->assertSame(2025, $evolucao['anterior']['ano']);
        $this->assertSame(12, $evolucao['anterior']['mes']);
        $this->assertEqualsWithDelta(6.0, $evolucao['atual']['media'], 0.01);
    }

    public function test_evolucao_sem_mes_anterior_da_redesim_nao_calcula_diferenca(): void
    {
        $this->processoDecididoEm(2.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertNull($evolucao['anterior']['media']);
        $this->assertSame(0, $evolucao['anterior']['n']);
        $this->assertEqualsWithDelta(2.0, $evolucao['atual']['media'], 0.01);
        $this->assertNull($evolucao['diferenca_horas']);
        $this->assertNull($evolucao['diferenca_percentual']);
    }

    public function test_evolucao_sem_processo_no_mes_atual_nao_calcula_diferenca(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertNull($evolucao['atual']['media']);
        $this->assertSame(0, $evolucao['atual']['n']);
        $this->assertNull($evolucao['diferenca_horas']);
    }

    public function test_evolucao_com_referencia_zero_nao_divide_por_zero(): void
    {
        $this->redesimDeSalvador(2026, 9, 0.0);
        $this->processoDecididoEm(2.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertEqualsWithDelta(2.0, $evolucao['diferenca_horas'], 0.01);
        $this->assertNull($evolucao['diferenca_percentual']);
    }

    public function test_evolucao_traz_a_redesim_do_mes_atual_quando_ja_foi_importada(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);
        $this->redesimDeSalvador(2026, 10, 10.0);
        $this->processoDecididoEm(4.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertSame(10, $evolucao['redesim_atual']['mes']);
        $this->assertEqualsWithDelta(10.0, $evolucao['redesim_atual']['media'], 0.01);
        $this->assertEqualsWithDelta(-6.0, $evolucao['diferenca_mesmo_mes_horas'], 0.01);
        $this->assertEqualsWithDelta(-60.0, $evolucao['diferenca_mesmo_mes_percentual'], 0.01);
        $this->assertEqualsWithDelta(-17.0, $evolucao['diferenca_horas'], 0.01);
    }

    public function test_evolucao_sem_a_redesim_do_mes_atual_deixa_a_terceira_medida_em_aberto(): void
    {
        $this->redesimDeSalvador(2026, 9, 21.0);
        $this->processoDecididoEm(4.0, '2026-10-05 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertSame(10, $evolucao['redesim_atual']['mes']);
        $this->assertNull($evolucao['redesim_atual']['media']);
        $this->assertSame(0, $evolucao['redesim_atual']['n']);
        $this->assertNull($evolucao['diferenca_mesmo_mes_horas']);
        $this->assertNull($evolucao['diferenca_mesmo_mes_percentual']);
    }

    private function processoIndeferidoEm(float $horas, string $decididoEm): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($decididoEm)->subSeconds((int) round($horas * 3600)),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => null,
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => 999.0])->save();
    }

    public function test_evolucao_considera_so_processos_deferidos_como_a_redesim(): void
    {
        $this->processoDecididoEm(2.0, '2026-10-05 10:00:00');
        $this->processoDecididoEm(4.0, '2026-10-06 10:00:00');
        $this->processoIndeferidoEm(60.0, '2026-10-07 10:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertSame(2, $evolucao['atual']['n']);
        $this->assertEqualsWithDelta(3.0, $evolucao['atual']['media'], 0.01);
    }

    public function test_distancia_considera_so_processos_deferidos_como_a_redesim(): void
    {
        $this->processoInternoCom(2.0);
        $this->processoIndeferidoEm(60.0, '2026-03-04 10:00:00');
        $this->referenciaCom(20.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(2.0, $distancia['interno'], 0.01);
    }

    /**
     * Processo da série: o tempo é em horas corridas, do protocolo à decisão,
     * como a REDESIM mede. O valor em horas úteis gravado no processo é outro
     * de propósito, para provar que a série não o usa.
     */
    private function processoCorrido(string $protocoladoEm, string $decididoEm, bool $deferido = true): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse($protocoladoEm),
        ]);

        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => $deferido ? fake()->unique()->numerify('TVL-2026-######') : null,
            'decided_at' => Carbon::parse($decididoEm),
        ]);

        $request->forceFill(['viabilidade_horas_uteis' => 999.0])->save();
    }

    /**
     * @param  array<string, mixed>  $serie
     * @return list<array{0: int, 1: int}>
     */
    private function meses(array $serie): array
    {
        return array_map(fn (array $p) => [$p['ano'], $p['mes']], $serie['pontos']);
    }

    public function test_serie_comeca_no_mes_da_primeira_decisao_deferida_e_vai_ate_o_mes_corrente(): void
    {
        config(['viabiliza.operacao.inicio' => null]);

        $this->redesimDeSalvador(2026, 9, 21.0);
        $this->processoCorrido('2026-09-20 10:00:00', '2026-09-21 10:00:00');
        $this->processoCorrido('2026-10-05 10:00:00', '2026-10-05 12:00:00');
        $this->processoCorrido('2026-10-06 10:00:00', '2026-10-06 14:00:00');

        $serie = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'))['serie'];

        $this->assertSame([[2026, 9], [2026, 10]], $this->meses($serie));
        $this->assertSame(['ano' => 2026, 'mes' => 9], $serie['inicio_operacao']);
        $this->assertEqualsWithDelta(21.0, $serie['pontos'][0]['redesim']['media'], 0.01);
        $this->assertEqualsWithDelta(24.0, $serie['pontos'][0]['viabiliza']['media'], 0.01);
        $this->assertFalse($serie['pontos'][0]['parcial']);
        $this->assertEqualsWithDelta(3.0, $serie['pontos'][1]['viabiliza']['media'], 0.01);
        $this->assertSame(2, $serie['pontos'][1]['viabiliza']['n']);
        $this->assertNull($serie['pontos'][1]['redesim']['media']);
        $this->assertTrue($serie['pontos'][1]['parcial']);
    }

    public function test_serie_conta_horas_corridas_com_fim_de_semana_e_feriado_como_a_redesim(): void
    {
        config(['viabiliza.operacao.inicio' => '2026-10']);

        // Sexta 09/10 10h até terça 13/10 10h: passa pelo fim de semana e pelo
        // feriado de 12/10. A REDESIM conta tudo: 96 horas.
        $this->processoCorrido('2026-10-09 10:00:00', '2026-10-13 10:00:00');

        $ponto = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'))['serie']['pontos'][0];

        $this->assertEqualsWithDelta(96.0, $ponto['viabiliza']['media'], 0.01);
        $this->assertEqualsWithDelta(96.0, $ponto['viabiliza']['p50'], 0.01);
        $this->assertEqualsWithDelta(96.0, $ponto['viabiliza']['p90'], 0.01);
    }

    public function test_serie_mostra_no_maximo_os_ultimos_doze_meses(): void
    {
        config(['viabiliza.operacao.inicio' => null]);

        $this->processoCorrido('2025-01-09 10:00:00', '2025-01-10 10:00:00');
        $this->processoCorrido('2026-10-04 10:00:00', '2026-10-05 10:00:00');

        $serie = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'))['serie'];

        $this->assertCount(12, $serie['pontos']);
        $this->assertSame([2025, 11], $this->meses($serie)[0]);
        $this->assertSame([2026, 10], $this->meses($serie)[11]);
        $this->assertSame(['ano' => 2025, 'mes' => 1], $serie['inicio_operacao']);
    }

    public function test_serie_usa_o_inicio_de_operacao_configurado(): void
    {
        config(['viabiliza.operacao.inicio' => '2026-08']);
        $this->processoCorrido('2026-10-04 10:00:00', '2026-10-05 10:00:00');

        $serie = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'))['serie'];

        $this->assertSame([[2026, 8], [2026, 9], [2026, 10]], $this->meses($serie));
        $this->assertNull($serie['pontos'][0]['viabiliza']['media']);
        $this->assertSame(0, $serie['pontos'][0]['viabiliza']['n']);
    }

    public function test_o_inicio_da_operacao_padrao_e_outubro_de_2026(): void
    {
        $this->processoCorrido('2026-11-02 10:00:00', '2026-11-03 10:00:00');

        $serie = $this->service()->evolucaoMensal(Carbon::parse('2026-11-04'))['serie'];

        $this->assertSame(['ano' => 2026, 'mes' => 10], $serie['inicio_operacao']);
        $this->assertSame([[2026, 10], [2026, 11]], $this->meses($serie));
    }

    public function test_serie_sem_nenhuma_decisao_mostra_so_o_mes_corrente(): void
    {
        config(['viabiliza.operacao.inicio' => null]);

        $serie = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'))['serie'];

        $this->assertNull($serie['inicio_operacao']);
        $this->assertCount(1, $serie['pontos']);
        $this->assertNull($serie['pontos'][0]['viabiliza']['media']);
    }

    public function test_serie_conta_so_deferidas_e_decisoes_do_proprio_mes(): void
    {
        config(['viabiliza.operacao.inicio' => '2026-09']);

        $this->processoCorrido('2026-09-30 21:00:00', '2026-09-30 23:00:00');
        $this->processoCorrido('2026-09-30 20:30:00', '2026-10-01 00:30:00');
        $this->processoCorrido('2026-10-06 10:00:00', '2026-10-07 10:00:00', deferido: false);

        $pontos = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'))['serie']['pontos'];

        $this->assertEqualsWithDelta(2.0, $pontos[0]['viabiliza']['media'], 0.01);
        $this->assertEqualsWithDelta(4.0, $pontos[1]['viabiliza']['media'], 0.01);
        $this->assertSame(1, $pontos[1]['viabiliza']['n']);
    }

    public function test_linha_de_base_e_a_redesim_dos_doze_meses_antes_da_operacao_ponderada_pelas_solicitacoes(): void
    {
        config(['viabiliza.operacao.inicio' => '2026-10']);

        // Fora da janela: antes dos 12 meses e o próprio mês de início.
        BenchmarkMunicipalityMetric::factory()->salvador()->create(['ano' => 2025, 'mes' => 9, 'media' => 500.0, 'n' => 100]);
        BenchmarkMunicipalityMetric::factory()->salvador()->create(['ano' => 2026, 'mes' => 10, 'media' => 500.0, 'n' => 100]);
        // Dentro da janela (10/2025 a 09/2026).
        BenchmarkMunicipalityMetric::factory()->salvador()->create(['ano' => 2025, 'mes' => 10, 'media' => 40.0, 'n' => 300]);
        BenchmarkMunicipalityMetric::factory()->salvador()->create(['ano' => 2026, 'mes' => 9, 'media' => 80.0, 'n' => 100]);

        $base = $this->service()->evolucaoMensal(Carbon::parse('2026-11-04'))['serie']['antes_da_operacao'];

        $this->assertEqualsWithDelta(50.0, $base['media'], 0.01);
        $this->assertSame(400, $base['n']);
        $this->assertSame(2, $base['meses']);
        $this->assertSame(['ano' => 2025, 'mes' => 10], $base['de']);
        $this->assertSame(['ano' => 2026, 'mes' => 9], $base['ate']);
    }

    public function test_sem_redesim_antes_da_operacao_nao_ha_linha_de_base(): void
    {
        config(['viabiliza.operacao.inicio' => '2026-10']);

        $serie = $this->service()->evolucaoMensal(Carbon::parse('2026-11-04'))['serie'];

        $this->assertNull($serie['antes_da_operacao']);
    }

    public function test_a_serie_traz_a_ressalva_da_regua_unica(): void
    {
        $serie = $this->service()->evolucaoMensal(Carbon::parse('2026-11-04'))['serie'];

        $this->assertSame(DistanciaBenchmarkService::RESSALVA_SERIE, $serie['ressalva']);
        $this->assertStringContainsString('horas corridas', $serie['ressalva']);
    }

    public function test_distancia_compara_na_regua_da_redesim_em_horas_corridas(): void
    {
        // Sexta 06/03 18h até segunda 09/03 18h: 72 horas corridas, com o fim
        // de semana. Em horas úteis seriam 24, mas a REDESIM conta tudo.
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => fake()->unique()->numerify('VIA-2026-######'),
            'protocoled_at' => Carbon::parse('2026-03-06 18:00:00'),
        ]);
        ViabilityDecision::factory()->for($request, 'viabilityRequest')->create([
            'tvl_product_number' => fake()->unique()->numerify('TVL-2026-######'),
            'decided_at' => Carbon::parse('2026-03-09 18:00:00'),
        ]);
        $request->forceFill(['viabilidade_horas_uteis' => 24.0])->save();
        $this->referenciaCom(60.0);

        $distancia = $this->service()->calcular($this->filtros());

        $this->assertEqualsWithDelta(72.0, $distancia['interno'], 0.01);
        $this->assertEqualsWithDelta(12.0, $distancia['diferenca_horas'], 0.01);
        $this->assertStringContainsString('horas corridas', $distancia['ressalva']);
    }

    public function test_evolucao_do_mes_compara_na_regua_da_redesim_em_horas_corridas(): void
    {
        $this->redesimDeSalvador(2026, 9, 50.0);
        $this->processoDecididoEm(72.0, '2026-10-05 18:00:00');

        $evolucao = $this->service()->evolucaoMensal(Carbon::parse('2026-10-15'));

        $this->assertEqualsWithDelta(72.0, $evolucao['atual']['media'], 0.01);
        $this->assertEqualsWithDelta(22.0, $evolucao['diferenca_horas'], 0.01);
    }
}
