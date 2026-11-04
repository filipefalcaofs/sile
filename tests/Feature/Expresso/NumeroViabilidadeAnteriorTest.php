<?php

namespace Tests\Feature\Expresso;

use App\Enums\CondicaoServico;
use App\Models\Company;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Expresso\TvlNumberGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Regra da SEDUR (04/11): alteração de endereço gera número novo de
 * viabilidade; revisão, espaço físico e inclusão ou exclusão de atividades
 * sem mudança de endereço mantêm o número da viabilidade anterior.
 */
class NumeroViabilidadeAnteriorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CNPJ = '12345678000190';

    private Company $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Company::factory()->create(['cnpj' => self::CNPJ]);
    }

    private int $sequencia = 0;

    private function protocolado(Company $empresa): ViabilityRequest
    {
        return ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $empresa->id,
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->sequencia, 6, '0', STR_PAD_LEFT),
        ]);
    }

    private function viabilidadeAnterior(string $numero = 'TVL-3000123'): ViabilityDecision
    {
        $anterior = $this->protocolado($this->empresa);

        return ViabilityDecision::factory()->create([
            'viability_request_id' => $anterior->id,
            'tvl_product_number' => $numero,
        ]);
    }

    /**
     * @param  list<array{0: string, 1?: ?CondicaoServico}>  $eventos  nome do evento e condição detectada
     */
    private function processo(array $eventos, ?Company $empresa = null): ViabilityRequest
    {
        $processo = $this->protocolado($empresa ?? $this->empresa);

        foreach ($eventos as $indice => $evento) {
            ViabilityRequestEvento::factory()->create([
                'viability_request_id' => $processo->id,
                'codigo' => (string) (900 + $indice),
                'nome' => $evento[0],
                'condicao' => $evento[1] ?? null,
            ]);
        }

        return $processo->fresh();
    }

    private function numero(ViabilityRequest $processo): string
    {
        return (new TvlNumberGenerator)->paraProcesso($processo);
    }

    public function test_inclusao_de_atividade_mantem_o_numero_anterior(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeIncluido]]);

        $this->assertSame('TVL-3000123', $this->numero($processo));
    }

    public function test_exclusao_de_atividade_mantem_o_numero_anterior(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeExcluido]]);

        $this->assertSame('TVL-3000123', $this->numero($processo));
    }

    public function test_revisao_sem_alteracao_de_atividade_mantem_o_numero_anterior(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeSemAlteracao]]);

        $this->assertSame('TVL-3000123', $this->numero($processo));
    }

    public function test_revisao_de_espaco_mantem_o_numero_anterior(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([['Alteração de endereço dentro do mesmo município', CondicaoServico::EnderecoEspaco]]);

        $this->assertSame('TVL-3000123', $this->numero($processo));
    }

    public function test_alteracao_de_endereco_gera_numero_novo(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([['Alteração de endereço dentro do mesmo município', CondicaoServico::EnderecoLocalizacao]]);

        $this->assertSame('TVL-3000000', $this->numero($processo));
    }

    public function test_alteracao_de_endereco_sem_condicao_detectada_gera_numero_novo(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([['Alteração de endereço entre municípios dentro do mesmo estado']]);

        $this->assertSame('TVL-3000000', $this->numero($processo));
    }

    public function test_alteracao_de_endereco_junto_com_atividade_gera_numero_novo(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([
            ['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeIncluido],
            ['Alteração de endereço dentro do mesmo município', CondicaoServico::EnderecoLocalizacao],
        ]);

        $this->assertSame('TVL-3000000', $this->numero($processo));
    }

    public function test_inscricao_gera_numero_novo(): void
    {
        $this->viabilidadeAnterior();

        $processo = $this->processo([['Inscrição dos demais estabelecimentos']]);

        $this->assertSame('TVL-3000000', $this->numero($processo));
    }

    public function test_processo_sem_evento_da_junta_gera_numero_novo(): void
    {
        $this->viabilidadeAnterior();

        $this->assertSame('TVL-3000000', $this->numero($this->processo([])));
    }

    public function test_alteracao_sem_viabilidade_anterior_gera_numero_novo(): void
    {
        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeIncluido]]);

        $this->assertSame('TVL-3000000', $this->numero($processo));
    }

    public function test_usa_a_ultima_viabilidade_deferida_da_empresa(): void
    {
        $this->viabilidadeAnterior('TVL-3000100');
        $this->viabilidadeAnterior('TVL-3000200');
        ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $this->protocolado($this->empresa)->id,
        ]);

        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeIncluido]]);

        $this->assertSame('TVL-3000200', $this->numero($processo));
    }

    public function test_viabilidade_de_outra_empresa_nao_conta(): void
    {
        $outra = Company::factory()->create(['cnpj' => '99887766000155']);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $this->protocolado($outra)->id,
            'tvl_product_number' => 'TVL-3000999',
        ]);

        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeIncluido]]);

        $this->assertSame('TVL-3000000', $this->numero($processo));
    }

    public function test_sem_viabilidade_no_viabiliza_usa_o_numero_da_base_anterior(): void
    {
        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeIncluido]]);
        $processo->forceFill(['comparacao_cnaes' => ['numero_viabilidade' => '2512345']])->save();

        $this->assertSame('TVL-2512345', $this->numero($processo->fresh()));
    }

    public function test_numero_mantido_pode_repetir_em_outra_decisao(): void
    {
        $anterior = $this->viabilidadeAnterior();
        $processo = $this->processo([['Alteração de atividades econômicas (principal e secundárias)', CondicaoServico::CnaeIncluido]]);

        $decisao = ViabilityDecision::factory()->create([
            'viability_request_id' => $processo->id,
            'tvl_product_number' => $this->numero($processo),
        ]);

        $this->assertSame($anterior->tvl_product_number, $decisao->fresh()->tvl_product_number);
    }
}
