<?php

namespace Tests\Feature\Integracao;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\Bi\BiDadosBrutos;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class BiDadosBrutosApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    private ?string $tokenEmitido = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.integrations.bi.ativo' => true,
            'viabiliza.integrations.bi.usuario' => 'bi_sedur',
            'viabiliza.integrations.bi.senha' => 'senha-de-teste',
            'viabiliza.integrations.bi.nominal' => false,
            'viabiliza.integrations.bi.identificados' => false,
        ]);
    }

    private function token(): string
    {
        return $this->tokenEmitido ??= (string) $this->postJson('/api_integracao/bi/autenticar', [
            'usuario' => 'bi_sedur',
            'senha' => 'senha-de-teste',
        ])->json('token');
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function tabela(string $tabela, array $query = []): TestResponse
    {
        return $this->getJson(
            "/api_integracao/bi/dados/{$tabela}?".http_build_query($query),
            ['Authorization' => 'Bearer '.$this->token()],
        );
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(array $atributos = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Protocolada,
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now()->subDays(2),
            'solicitante_nome' => 'Fulano de Tal',
            'solicitante_cpf' => '12345678909',
        ], $atributos));
    }

    public function test_sem_token_responde_401(): void
    {
        $this->getJson('/api_integracao/bi/dados')->assertStatus(401);
        $this->getJson('/api_integracao/bi/dados/processos')->assertStatus(401);
    }

    public function test_catalogo_descreve_todas_as_tabelas_e_todas_respondem_vazias(): void
    {
        $catalogo = $this->getJson('/api_integracao/bi/dados', ['Authorization' => 'Bearer '.$this->token()])
            ->assertOk()
            ->json('tabelas');

        $nomes = array_column($catalogo, 'tabela');
        $this->assertSame(array_keys(app(BiDadosBrutos::class)->catalogo()), $nomes);

        foreach ($catalogo as $tabela) {
            $this->assertNotEmpty($tabela['campos']);

            $this->tabela($tabela['tabela'])
                ->assertOk()
                ->assertJsonPath('quantidade', 0)
                ->assertJsonPath('paginacao.tem_mais', false);
        }
    }

    public function test_todas_as_tabelas_respondem_com_dados(): void
    {
        $analista = User::factory()->create();
        $processo = $this->processo(['assigned_user_id' => $analista->id]);
        $processo->cnaes()->attach(Cnae::factory()->create()->id, ['is_primary' => true]);
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id, 'tvl_product_number' => null]);

        foreach (array_keys(app(BiDadosBrutos::class)->catalogo()) as $tabela) {
            $this->tabela($tabela)->assertOk();
        }
    }

    public function test_tabela_inexistente_responde_404_e_parametro_invalido_422(): void
    {
        $this->tabela('nao-existe')->assertNotFound();
        $this->tabela('processos', ['limite' => 99999])->assertStatus(422);
        $this->tabela('processos', ['atualizado_desde' => 'ontem'])->assertStatus(422);
    }

    public function test_processos_saem_sem_dado_pessoal_e_com_coordenada_arredondada(): void
    {
        $cnae = Cnae::factory()->create(['code' => '4712100']);
        $processo = $this->processo([
            'company_id' => Company::factory()->create(['cnpj' => '12345678000195', 'size' => 'MICRO EMPRESA'])->id,
            'property_point_lat' => -12.9811234,
            'property_point_lng' => -38.4521987,
            'address_street' => 'Rua Particular',
            'used_area_m2' => 75,
            'zona_codigo' => 'ZPR-1',
        ]);
        $processo->cnaes()->attach($cnae->id, ['is_primary' => true]);
        ViabilityDecision::factory()->create(['viability_request_id' => $processo->id, 'tvl_product_number' => 'TVL-2026-000123']);

        $linha = $this->tabela('processos')->assertOk()->json('dados.0');

        $this->assertSame($processo->id, $linha['id']);
        $this->assertSame('4712100', $linha['cnae_principal']);
        $this->assertSame('deferida', $linha['decisao_desfecho']);
        $this->assertSame('MICRO EMPRESA', $linha['empresa_porte']);
        $this->assertSame('ZPR-1', $linha['zona']);
        $this->assertEquals(75.0, $linha['area_utilizada_m2']);
        $this->assertEquals(-12.981, $linha['latitude']);

        $json = json_encode($linha);
        $this->assertStringNotContainsString('Fulano', $json);
        $this->assertStringNotContainsString('12345678909', $json);
        $this->assertStringNotContainsString('12345678000195', $json);
        $this->assertStringNotContainsString('Rua Particular', $json);
        $this->assertArrayNotHasKey('cnpj', $linha);

        config(['viabiliza.integrations.bi.identificados' => true]);

        $identificado = $this->tabela('processos')->json('dados.0');
        $this->assertSame('12345678000195', $identificado['cnpj']);
        $this->assertSame('Rua Particular', $identificado['logradouro']);
        $this->assertEquals(-12.9811234, $identificado['latitude']);
        $this->assertStringNotContainsString('Fulano', json_encode($identificado));
    }

    public function test_rascunho_nao_entra_em_processos(): void
    {
        ViabilityRequest::factory()->draft()->create();
        $this->processo();

        $this->assertSame(1, $this->tabela('processos')->json('quantidade'));
    }

    public function test_paginacao_segue_pelo_link_da_proxima_pagina(): void
    {
        $ids = [$this->processo()->id, $this->processo()->id, $this->processo()->id];

        $primeira = $this->tabela('processos', ['limite' => 2])->assertOk();
        $this->assertSame(array_slice($ids, 0, 2), array_column($primeira->json('dados'), 'id'));
        $this->assertTrue($primeira->json('paginacao.tem_mais'));
        $this->assertSame($ids[1], $primeira->json('paginacao.ultimo_id'));

        $proxima = (string) $primeira->json('paginacao.proxima_url');
        $this->assertStringContainsString('apos_id='.$ids[1], $proxima);

        $segunda = $this->getJson($proxima, ['Authorization' => 'Bearer '.$this->token()])->assertOk();
        $this->assertSame([$ids[2]], array_column($segunda->json('dados'), 'id'));
        $this->assertFalse($segunda->json('paginacao.tem_mais'));
        $this->assertNull($segunda->json('paginacao.proxima_url'));
    }

    public function test_carga_incremental_traz_so_o_que_mudou(): void
    {
        $antigo = $this->processo();
        $novo = $this->processo();
        DB::table('viability_requests')->where('id', $antigo->id)->update(['updated_at' => now()->subDays(10)]);

        $dados = $this->tabela('processos', ['atualizado_desde' => now()->subDay()->toDateString()])->json('dados');

        $this->assertSame([$novo->id], array_column($dados, 'id'));
    }

    public function test_decisao_cnaes_abre_o_enquadramento_por_cnae(): void
    {
        $processo = $this->processo();
        $decisao = ViabilityDecision::factory()->indeferida()->create(['viability_request_id' => $processo->id]);

        $linhas = $this->tabela('decisao-cnaes')->json('dados');

        $this->assertCount(1, $linhas);
        $this->assertSame($decisao->id, $linhas[0]['id']);
        $this->assertSame('4712100', $linhas[0]['cnae']);
        $this->assertSame('nao_permitido', $linhas[0]['tendencia']);
        $this->assertSame('indeferida', $linhas[0]['desfecho_decisao']);

        $fundamentos = $this->tabela('decisoes')->json('dados.0.fundamentos');
        $this->assertContains('Decreto nº 32.636/2020 — classificação de risco', $fundamentos);
        $this->assertArrayNotHasKey('reason', $this->tabela('decisoes')->json('dados.0'));
    }

    public function test_filtro_por_processo_nas_tabelas_filhas(): void
    {
        $um = $this->processo();
        $outro = $this->processo();
        ViabilityDecision::factory()->create(['viability_request_id' => $um->id, 'tvl_product_number' => null]);
        ViabilityDecision::factory()->create(['viability_request_id' => $outro->id, 'tvl_product_number' => null, 'outcome' => DecisionOutcome::Deferida]);

        $dados = $this->tabela('decisoes', ['processo_id' => $outro->id])->json('dados');

        $this->assertSame([$outro->id], array_column($dados, 'processo_id'));
    }

    public function test_transicoes_trazem_quem_agiu_sem_identificar_o_requerente(): void
    {
        $processo = $this->processo();
        $servidor = User::factory()->create();
        $processo->transitions()->create(['from_status' => 'protocolada', 'to_status' => 'em_analise', ...$this->autor($servidor)]);
        $processo->transitions()->create(['from_status' => 'em_analise', 'to_status' => 'deferida']);

        $linhas = $this->tabela('transicoes', ['processo_id' => $processo->id])->assertOk()->json('dados');

        $this->assertSame(['servidor', null], array_column($linhas, 'ator_tipo'));
        $this->assertSame([$servidor->id, null], array_column($linhas, 'ator_id'));
    }

    /**
     * Autor da transição na coluna que o esquema tiver.
     *
     * @return array<string, mixed>
     */
    private function autor(User $servidor): array
    {
        return Schema::hasColumn('viability_request_transitions', 'actor_user_id')
            ? ['actor_user_id' => $servidor->id]
            : ['actor_type' => $servidor->getMorphClass(), 'actor_id' => $servidor->id];
    }

    public function test_analistas_so_tem_nome_com_a_liberacao_nominal(): void
    {
        $analista = User::factory()->create(['name' => 'Maria Analista']);
        $this->processo(['assigned_user_id' => $analista->id]);

        $linha = $this->tabela('analistas')->json('dados.0');
        $this->assertSame($analista->id, $linha['id']);
        $this->assertSame('Analista '.$analista->id, $linha['nome']);

        config(['viabiliza.integrations.bi.nominal' => true]);

        $this->assertSame('Maria Analista', $this->tabela('analistas')->json('dados.0.nome'));
    }

    public function test_swagger_documenta_as_tabelas(): void
    {
        $spec = Yaml::parse((string) $this->get('/api_integracao/bi/docs/openapi.yaml')->getContent());

        $this->assertArrayHasKey('/api_integracao/bi/dados', $spec['paths']);

        foreach (array_keys(app(BiDadosBrutos::class)->catalogo()) as $tabela) {
            $this->assertArrayHasKey("/api_integracao/bi/dados/{$tabela}", $spec['paths']);
        }
    }
}
