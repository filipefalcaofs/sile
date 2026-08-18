<?php

namespace Tests\Feature\Relatorios;

use App\Services\Relatorios\ReportFilters;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Contrato de entrada da exportação (HU-131): o ReportFilters é um BAG
 * serializável que carrega o vocabulário COMPLETO de filtros das telas (não só
 * os campos do indicador). O round-trip toArray/fromArray é a garantia do
 * RN-005 no assíncrono — o GerarExportacaoJob reconstrói EXATAMENTE o conjunto
 * filtrado a partir do bag. toProcessoFiltros recorta só as 17 chaves do
 * ProcessoQueryService (retrofit da consulta de processos); os acessores
 * tipados normalizam como o serviço (datas, inteiros, categoria do whitelist).
 */
class ReportFiltersTest extends TestCase
{
    /**
     * Bag rico com as chaves de processo + uma chave de auditoria (usuario_id):
     * algumas preenchidas, algumas vazias (que devem sumir na normalização).
     *
     * @return array<string, mixed>
     */
    private function bagRico(): array
    {
        return [
            'grupo' => 'em_andamento',
            'status' => '',            // vazio → removido
            'protocolo' => '  2026-0001  ', // trim
            'bap' => null,             // null → removido
            'setor' => '3',
            'analista' => '7',
            'bairro' => 'Pituba',
            'nome' => 'Maria',
            'cnpj' => '00000000000191',
            'cep' => '40000-000',
            'logradouro' => 'Av. Tancredo Neves',
            'data_de' => '2026-01-01',
            'data_ate' => '2026-03-31',
            'categoria' => 'expresso',
            'usuario_id' => '12',      // chave de auditoria — sobrevive ao round-trip, fora de toProcessoFiltros
        ];
    }

    #[Test]
    public function from_array_normaliza_removendo_vazios_e_nulos_e_aplica_trim(): void
    {
        $filtros = ReportFilters::fromArray($this->bagRico());
        $bag = $filtros->toArray();

        // Vazio e null somem; o trim é aplicado às strings preenchidas.
        $this->assertArrayNotHasKey('status', $bag);
        $this->assertArrayNotHasKey('bap', $bag);
        $this->assertSame('2026-0001', $bag['protocolo']);
    }

    #[Test]
    public function to_array_faz_round_trip_completo_do_bag_preservando_o_vocabulario_inteiro(): void
    {
        // RN-005 no assíncrono: fromArray(toArray) é idêntico — é o que o Job
        // serializa e reconstrói. O bag preserva inclusive a chave de auditoria
        // (usuario_id), que NÃO pertence ao recorte de processos.
        $original = ReportFilters::fromArray($this->bagRico());

        $roundTrip = ReportFilters::fromArray($original->toArray());

        $this->assertSame($original->toArray(), $roundTrip->toArray());
        $this->assertArrayHasKey('usuario_id', $roundTrip->toArray());
        $this->assertSame('12', $roundTrip->get('usuario_id'));
    }

    #[Test]
    public function to_processo_filtros_recorta_apenas_as_chaves_do_processo_query_service(): void
    {
        $filtros = ReportFilters::fromArray($this->bagRico());

        $processo = $filtros->toProcessoFiltros();

        // As chaves preenchidas de processo entram...
        $this->assertSame('em_andamento', $processo['grupo']);
        $this->assertSame('3', $processo['setor']);
        $this->assertSame('expresso', $processo['categoria']);
        // ...e a chave de auditoria (usuario_id) NÃO entra (evita dump de PII da trilha).
        $this->assertArrayNotHasKey('usuario_id', $processo);
        // Chave vazia já removida não reaparece.
        $this->assertArrayNotHasKey('status', $processo);
    }

    #[Test]
    public function aplicados_traz_somente_os_filtros_preenchidos(): void
    {
        $filtros = ReportFilters::fromArray($this->bagRico());

        $aplicados = $filtros->aplicados();

        $this->assertArrayNotHasKey('status', $aplicados);
        $this->assertArrayNotHasKey('bap', $aplicados);
        $this->assertSame('Pituba', $aplicados['bairro']);
    }

    #[Test]
    public function acessores_tipados_normalizam_datas_inteiros_e_texto(): void
    {
        $filtros = ReportFilters::fromArray($this->bagRico());

        $this->assertInstanceOf(Carbon::class, $filtros->from());
        $this->assertInstanceOf(Carbon::class, $filtros->to());
        $this->assertSame('2026-01-01 00:00:00', $filtros->from()->toDateTimeString());
        $this->assertSame('2026-03-31 23:59:59', $filtros->to()->toDateTimeString());
        $this->assertSame(3, $filtros->setorId());
        $this->assertSame(7, $filtros->analistaId());
        $this->assertSame('Pituba', $filtros->bairro());
    }

    #[Test]
    public function categoria_fora_do_whitelist_vira_null(): void
    {
        // Whitelist = ProcessoQueryService::CATEGORIAS. Valor inexistente degrada
        // para null (sem inventar categoria).
        $valida = ReportFilters::fromArray(['categoria' => 'expresso']);
        $invalida = ReportFilters::fromArray(['categoria' => 'inexistente']);

        $this->assertSame('expresso', $valida->categoria());
        $this->assertNull($invalida->categoria());
    }

    #[Test]
    public function from_e_to_degradam_para_null_sem_data(): void
    {
        $vazio = ReportFilters::fromArray([]);

        $this->assertNull($vazio->from());
        $this->assertNull($vazio->to());
        $this->assertNull($vazio->setorId());
        $this->assertNull($vazio->categoria());
    }
}
