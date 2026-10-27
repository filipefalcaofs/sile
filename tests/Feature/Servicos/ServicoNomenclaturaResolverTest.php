<?php

namespace Tests\Feature\Servicos;

use App\Models\ServicoNomenclatura;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Models\ViabilityServiceType;
use App\Services\Servicos\ServicoNomenclaturaResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regra de exibição do serviço: nome Para, sem repetir, e só os marcados como
 * Exibir quando o processo traz mais de um evento (Observação 1 da SEDUR).
 */
class ServicoNomenclaturaResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function processoCom(string ...$nomes): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create();

        foreach ($nomes as $indice => $nome) {
            ViabilityRequestEvento::factory()->create([
                'viability_request_id' => $processo->id,
                'codigo' => (string) (100 + $indice),
                'nome' => $nome,
            ]);
        }

        return $processo->fresh();
    }

    private function servicos(ViabilityRequest $processo): array
    {
        return app(ServicoNomenclaturaResolver::class)->servicos($processo);
    }

    public function test_troca_o_nome_pelo_para(): void
    {
        $this->assertSame(['Viabilidade de Localização'], $this->servicos($this->processoCom('Inscrição de primeiro estabelecimento')));
    }

    public function test_dois_eventos_com_mesmo_para_aparecem_uma_vez(): void
    {
        $processo = $this->processoCom('Inscrição de primeiro estabelecimento', 'Inscrição dos demais estabelecimentos');

        $this->assertSame(['Viabilidade de Localização'], $this->servicos($processo));
    }

    public function test_esconde_eventos_com_exibir_desligado_quando_ha_algum_ligado(): void
    {
        ServicoNomenclatura::garantir('Alteração de nome empresarial');
        $processo = $this->processoCom('Alteração de nome empresarial', 'Alteração de endereço entre estados');

        $this->assertSame(['Alteração de Endereço'], $this->servicos($processo));
    }

    public function test_sem_nenhum_exibir_ligado_mostra_o_para_de_todos(): void
    {
        ServicoNomenclatura::garantir('Alteração de nome empresarial')->update(['para' => 'Nome Empresarial']);
        ServicoNomenclatura::garantir('Alteração de capital social');
        $processo = $this->processoCom('Alteração de nome empresarial', 'Alteração de capital social');

        $this->assertSame(['Nome Empresarial', 'Alteração de capital social'], $this->servicos($processo));
    }

    public function test_evento_sem_linha_usa_o_proprio_nome(): void
    {
        $this->assertSame(['Evento ainda sem linha'], $this->servicos($this->processoCom('Evento ainda sem linha')));
    }

    public function test_casa_o_nome_sem_considerar_caixa_acento_e_espacos(): void
    {
        $this->assertSame(['Viabilidade de Localização'], $this->servicos($this->processoCom(' INSCRICAO  de primeiro estabelecimento')));
    }

    public function test_sem_eventos_usa_o_tipo_interno(): void
    {
        $tipo = ViabilityServiceType::factory()->create(['name' => 'Renovação']);
        $comTipo = ViabilityRequest::factory()->create(['service_type_id' => $tipo->id]);
        $semTipo = ViabilityRequest::factory()->create(['service_type_id' => null]);

        $this->assertSame(['Renovação'], $this->servicos($comTipo));
        $this->assertSame([], $this->servicos($semTipo));
    }

    public function test_para_com_caixa_diferente_aparece_uma_vez_com_a_primeira_grafia(): void
    {
        ServicoNomenclatura::query()->where('chave', ServicoNomenclatura::chaveDe('Alteração de endereço entre estados'))
            ->update(['para' => 'Alteração de endereço']);
        $processo = $this->processoCom('Alteração de endereço dentro do mesmo município', 'Alteração de endereço entre estados');

        $this->assertSame(['Alteração de Endereço'], $this->servicos($processo));
    }

    public function test_opcoes_agrupam_para_com_caixa_diferente_e_tem_valor_estavel_pelo_nome(): void
    {
        ServicoNomenclatura::query()->where('chave', ServicoNomenclatura::chaveDe('Alteração de endereço entre estados'))
            ->update(['para' => 'Alteração de endereço']);
        $this->processoCom('Alteração de endereço dentro do mesmo município', 'Alteração de endereço entre estados');

        $opcoes = app(ServicoNomenclaturaResolver::class)->opcoesDeEvento();

        $this->assertSame([['value' => 'nalteracao de endereco', 'label' => 'Alteração de Endereço']], $opcoes);
    }

    public function test_carrega_a_tabela_uma_unica_vez(): void
    {
        $processos = collect(range(1, 5))
            ->map(fn () => $this->processoCom('Inscrição de primeiro estabelecimento'))
            ->each->load('eventos');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $processos->each(fn (ViabilityRequest $processo) => $this->servicos($processo));
        $consultas = collect(DB::getQueryLog())->filter(fn (array $q) => Str::contains($q['query'], 'servico_nomenclaturas'));
        DB::disableQueryLog();

        $this->assertCount(1, $consultas);
    }
}
