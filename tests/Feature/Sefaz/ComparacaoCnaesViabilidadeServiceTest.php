<?php

namespace Tests\Feature\Sefaz;

use App\Models\Cnae;
use App\Services\Sefaz\ComparacaoCnaesViabilidade;
use App\Services\Sefaz\ComparacaoCnaesViabilidadeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ComparacaoCnaesViabilidadeServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['4711302', '4712100', '5611201', '8211300'] as $codigo) {
            Cnae::factory()->create(['code' => $codigo]);
        }
    }

    private function comparar(array $pedido, ?array $viabilidade, ?string $numero = 'VIA-1'): ComparacaoCnaesViabilidade
    {
        return app(ComparacaoCnaesViabilidadeService::class)->comparar($pedido, $viabilidade, $numero);
    }

    public function test_t1_solicitacao_identica_nao_tem_inclusao_exclusao_nem_inconsistencia(): void
    {
        $resultado = $this->comparar(['4711-3/02', '4712-1/00'], ['4711-3/02', '4712-1/00']);

        $this->assertSame([], $resultado->incluidas);
        $this->assertSame([], $resultado->excluidas);
        $this->assertSame([], $resultado->invalidas);
        $this->assertEqualsCanonicalizing(['4711302', '4712100'], $resultado->mantidas);
    }

    public function test_t2_um_cnae_novo_e_incluido(): void
    {
        $resultado = $this->comparar(['4711-3/02', '5611-2/01'], ['4711-3/02']);

        $this->assertSame(['5611201'], $resultado->incluidas);
        $this->assertSame(['4711302'], $resultado->mantidas);
    }

    public function test_t3_varios_cnaes_novos_sao_todos_incluidos(): void
    {
        $resultado = $this->comparar(['4711-3/02', '5611-2/01', '8211-3/00'], ['4711-3/02']);

        $this->assertEqualsCanonicalizing(['5611201', '8211300'], $resultado->incluidas);
    }

    public function test_t4_cnae_que_sai_da_solicitacao_e_excluido(): void
    {
        $resultado = $this->comparar(['4711-3/02'], ['4711-3/02', '4712-1/00']);

        $this->assertSame(['4712100'], $resultado->excluidas);
        $this->assertSame([], $resultado->incluidas);
    }

    public function test_t5_codigo_inexistente_e_invalido_e_nao_conta_como_incluido(): void
    {
        $resultado = $this->comparar(['4711-3/02', '9999-9/99'], ['4711-3/02']);

        $this->assertSame(['9999999'], $resultado->invalidas);
        $this->assertSame([], $resultado->incluidas);
    }

    public function test_codigo_criado_pelo_regin_fora_da_lista_oficial_e_invalido(): void
    {
        Cnae::factory()->create(['code' => '1234567', 'section_description' => 'REGIN']);

        $resultado = $this->comparar(['4711-3/02', '1234-5/67'], ['4711-3/02']);

        $this->assertSame(['1234567'], $resultado->invalidas);
        $this->assertSame([], $resultado->incluidas);
    }

    public function test_t6_mascara_caixa_e_repeticao_nao_geram_falsa_inclusao(): void
    {
        $resultado = $this->comparar(['4711302', ' 4711-3/02 ', '4711.3-02'], ['4711-3/02']);

        $this->assertSame([], $resultado->incluidas);
        $this->assertSame(['4711302'], $resultado->mantidas);
    }

    public function test_t7_combinacao_de_inclusao_exclusao_e_codigo_invalido(): void
    {
        $resultado = $this->comparar(['5611-2/01', '9999-9/99'], ['4711-3/02']);

        $this->assertSame(['5611201'], $resultado->incluidas);
        $this->assertSame(['4711302'], $resultado->excluidas);
        $this->assertSame(['9999999'], $resultado->invalidas);
    }

    public function test_t8_sem_viabilidade_vinculada_trata_tudo_como_incluido(): void
    {
        $resultado = $this->comparar(['4711-3/02', '9999-9/99'], null, null);

        $this->assertTrue($resultado->semViabilidade);
        $this->assertSame(['4711302'], $resultado->incluidas);
        $this->assertSame(['9999999'], $resultado->invalidas);
        $this->assertSame([], $resultado->excluidas);
        $this->assertNull($resultado->numeroViabilidade);
    }

    public function test_to_array_e_from_array_preservam_o_resultado(): void
    {
        $resultado = $this->comparar(['5611-2/01'], ['4711-3/02'], 'VIA-7');

        $this->assertEquals($resultado, ComparacaoCnaesViabilidade::fromArray($resultado->toArray()));
        $this->assertSame('VIA-7', $resultado->toArray()['numero_viabilidade']);
    }
}
