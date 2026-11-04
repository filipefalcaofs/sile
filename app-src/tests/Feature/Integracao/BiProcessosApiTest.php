<?php

namespace Tests\Feature\Integracao;

use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\Inspection;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class BiProcessosApiTest extends TestCase
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
    private function chamar(string $caminho, array $query = []): TestResponse
    {
        return $this->getJson($caminho.($query === [] ? '' : '?'.http_build_query($query)), ['Authorization' => 'Bearer '.$this->token()]);
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
        $this->getJson('/api_integracao/bi/processos')->assertStatus(401);
        $this->getJson('/api_integracao/bi/processos/1')->assertStatus(401);
    }

    public function test_sem_processos_devolve_lista_vazia(): void
    {
        ViabilityRequest::factory()->draft()->create();

        $this->chamar('/api_integracao/bi/processos')
            ->assertOk()
            ->assertJsonPath('quantidade', 0)
            ->assertJsonPath('paginacao.tem_mais', false);
    }

    public function test_processo_vem_com_ficha_registros_relacionados_e_tramitacao_completa(): void
    {
        $analista = User::factory()->create(['name' => 'Maria Analista']);
        $processo = $this->processo(['assigned_user_id' => $analista->id, 'zona_codigo' => 'ZPR-1']);
        $processo->cnaes()->attach(Cnae::factory()->create(['code' => '4712100'])->id, ['is_primary' => true]);

        $processo->transitions()->create([
            'from_status' => ViabilityRequestStatus::Protocolada,
            'to_status' => ViabilityRequestStatus::EmAnalise,
            'reason' => 'Encaminhado à análise técnica.',
            'created_at' => now()->subDay(),
        ]);
        $processo->transitions()->create([
            'from_status' => ViabilityRequestStatus::EmAnalise,
            'to_status' => ViabilityRequestStatus::Indeferida,
            'created_at' => now(),
        ]);

        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'analyst_user_id' => $analista->id]);
        AnalysisPendency::factory()->create(['viability_request_id' => $processo->id, 'description' => 'Envie a planta baixa.']);
        Inspection::factory()->create(['viability_request_id' => $processo->id]);
        ViabilityDecision::factory()->indeferida()->create([
            'viability_request_id' => $processo->id,
            'flow' => 'analise_tecnica',
            'decided_by_user_id' => $analista->id,
        ]);

        $dados = $this->chamar('/api_integracao/bi/processos')->assertOk()->json('processos.0');

        $this->assertSame($processo->id, $dados['id']);
        $this->assertSame($processo->protocol_number, $dados['ficha']['protocol_number']);
        $this->assertSame('Fulano de Tal', $dados['ficha']['solicitante']);
        $this->assertSame('ZPR-1', $dados['registro']['zona_codigo']);
        $this->assertSame('12345678909', $dados['registro']['solicitante_cpf']);
        $this->assertNotNull($dados['empresa']);
        $this->assertSame('4712100', $dados['cnaes'][0]['codigo']);

        $this->assertSame('indeferida', $dados['decisao']['outcome']);
        $this->assertSame('Maria Analista', $dados['decisao']['decidido_por']);
        $this->assertNotNull($dados['decisao']['parecer']);

        $this->assertSame('Maria Analista', $dados['fichas_analise'][0]['analista']);
        $this->assertSame('Envie a planta baixa.', $dados['convites'][0]['description']);
        $this->assertCount(1, $dados['vistorias']);

        $situacoes = array_values(array_filter($dados['tramitacao'], fn (array $item): bool => $item['tipo'] === 'status'));
        $this->assertSame(['em_analise', 'indeferida'], array_column($situacoes, 'para'));
        $this->assertSame('Encaminhado à análise técnica.', $situacoes[0]['detalhe']);

        $momentos = array_column($dados['tramitacao'], 'em');
        $ordenados = $momentos;
        sort($ordenados);
        $this->assertSame($ordenados, $momentos);

        $json = json_encode($dados);
        $this->assertStringNotContainsString('"password"', $json);
        $this->assertStringNotContainsString('two_factor', $json);
    }

    public function test_paginacao_segue_pelo_link_da_proxima_pagina(): void
    {
        $ids = [$this->processo()->id, $this->processo()->id, $this->processo()->id];

        $primeira = $this->chamar('/api_integracao/bi/processos', ['limite' => 2])->assertOk();
        $this->assertSame(array_slice($ids, 0, 2), array_column($primeira->json('processos'), 'id'));
        $this->assertTrue($primeira->json('paginacao.tem_mais'));

        $segunda = $this->getJson((string) $primeira->json('paginacao.proxima_url'), ['Authorization' => 'Bearer '.$this->token()])->assertOk();
        $this->assertSame([$ids[2]], array_column($segunda->json('processos'), 'id'));
        $this->assertNull($segunda->json('paginacao.proxima_url'));
    }

    public function test_filtros_de_situacao_periodo_e_carga_incremental(): void
    {
        $emAnalise = $this->processo(['status' => ViabilityRequestStatus::EmAnalise]);
        $antigo = $this->processo(['protocoled_at' => now()->subMonths(3)]);
        ViabilityRequest::whereKey($antigo->id)->update(['updated_at' => now()->subMonths(3)]);

        $this->assertSame([$emAnalise->id], array_column($this->chamar('/api_integracao/bi/processos', ['situacao' => 'em_analise'])->json('processos'), 'id'));
        $this->assertSame([$antigo->id], array_column($this->chamar('/api_integracao/bi/processos', ['data_ate' => now()->subMonth()->toDateString()])->json('processos'), 'id'));
        $this->assertNotContains($antigo->id, array_column($this->chamar('/api_integracao/bi/processos', ['atualizado_desde' => now()->subWeek()->toDateString()])->json('processos'), 'id'));

        $this->chamar('/api_integracao/bi/processos', ['situacao' => 'inexistente'])->assertStatus(422);
    }

    public function test_um_processo_pelo_id_ou_pelo_protocolo(): void
    {
        $processo = $this->processo();

        $this->chamar("/api_integracao/bi/processos/{$processo->id}")->assertOk()->assertJsonPath('processo.id', $processo->id);
        $this->chamar("/api_integracao/bi/processos/{$processo->protocol_number}")->assertOk()->assertJsonPath('processo.id', $processo->id);
        $this->chamar('/api_integracao/bi/processos/VIA-0000-000000')->assertNotFound();
    }

    public function test_dados_brutos_nao_existem_mais_e_o_swagger_documenta_os_processos(): void
    {
        $this->chamar('/api_integracao/bi/dados')->assertNotFound();
        $this->chamar('/api_integracao/bi/dados/processos')->assertNotFound();

        $spec = Yaml::parse((string) $this->get('/api_integracao/bi/docs/openapi.yaml')->getContent());

        $this->assertArrayHasKey('/api_integracao/bi/processos', $spec['paths']);
        $this->assertArrayHasKey('/api_integracao/bi/processos/{processo}', $spec['paths']);
        $this->assertArrayNotHasKey('/api_integracao/bi/dados', $spec['paths']);
    }
}
