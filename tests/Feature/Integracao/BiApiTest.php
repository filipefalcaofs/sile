<?php

namespace Tests\Feature\Integracao;

use App\Enums\DecisionOutcome;
use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Relatorios\Bi\BiCatalogo;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class BiApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const USUARIO = 'bi_sedur';

    private const SENHA = 'senha-de-teste';

    private int $seq = 0;

    private ?string $tokenEmitido = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'viabiliza.integrations.bi.ativo' => true,
            'viabiliza.integrations.bi.usuario' => self::USUARIO,
            'viabiliza.integrations.bi.senha' => self::SENHA,
            'viabiliza.integrations.bi.validade_minutos' => 60,
            'viabiliza.integrations.bi.nominal' => false,
        ]);
    }

    private function token(): string
    {
        return $this->tokenEmitido ??= (string) $this->postJson('/api_integracao/bi/autenticar', [
            'usuario' => self::USUARIO,
            'senha' => self::SENHA,
        ])->json('token');
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function indicador(string $slug, array $query = []): TestResponse
    {
        return $this->getJson(
            "/api_integracao/bi/indicadores/{$slug}?".http_build_query($query),
            ['Authorization' => 'Bearer '.$this->token()],
        );
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function solicitacao(ViabilityRequestStatus $status, array $atributos = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->create(array_merge([
            'status' => $status,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now()->subDays(3),
        ], $atributos));
    }

    private function decidir(ViabilityRequest $request, DecisionOutcome $desfecho, string $fluxo, float $horas, ?User $analista = null): void
    {
        $fabrica = ViabilityDecision::factory();

        if ($desfecho === DecisionOutcome::Indeferida) {
            $fabrica = $fabrica->indeferida();
        }

        $fabrica->create([
            'viability_request_id' => $request->id,
            'outcome' => $desfecho,
            'flow' => $fluxo,
            'decided_by_user_id' => $analista?->id,
            'decided_at' => now(),
            'tvl_product_number' => null,
        ]);

        ViabilityRequest::whereKey($request->id)->update(['viabilidade_horas_uteis' => $horas]);
    }

    /**
     * Quatro solicitações: duas na Pituba (uma deferida pelo expresso, uma
     * indeferida pela análise técnica), uma deferida na Barra e uma em análise
     * na Barra com prazo vencido.
     *
     * @return array{analista: User}
     */
    private function cenario(): array
    {
        $varejo = Cnae::factory()->create(['code' => '4712100', 'description' => 'Comércio varejista']);
        $restaurante = Cnae::factory()->create(['code' => '5611201', 'description' => 'Restaurantes']);
        $analista = User::factory()->create();

        $a = $this->solicitacao(ViabilityRequestStatus::Deferida, [
            'address_neighborhood' => 'Pituba', 'used_area_m2' => 40,
            'property_point_lat' => -12.9811, 'property_point_lng' => -38.4521,
            'origin' => ViabilityRequestOrigin::Regin, 'zona_codigo' => 'ZPR-1',
            'company_id' => Company::factory()->create(['size' => 'MICRO EMPRESA'])->id,
        ]);
        $a->cnaes()->attach($varejo->id, ['is_primary' => true]);
        ViabilityRequestEvento::create(['viability_request_id' => $a->id, 'codigo' => '101', 'nome' => 'Inscrição de empresa']);
        $this->decidir($a, DecisionOutcome::Deferida, 'expresso', 2);

        $b = $this->solicitacao(ViabilityRequestStatus::Indeferida, [
            'address_neighborhood' => 'Pituba', 'used_area_m2' => 120,
            'property_point_lat' => -12.9812, 'property_point_lng' => -38.4522,
            'origin' => ViabilityRequestOrigin::Regin, 'zona_codigo' => 'ZPR-1',
        ]);
        $b->cnaes()->attach($varejo->id, ['is_primary' => true]);
        $this->decidir($b, DecisionOutcome::Indeferida, 'analise_tecnica', 4, $analista);

        $c = $this->solicitacao(ViabilityRequestStatus::Deferida, ['address_neighborhood' => 'Barra', 'used_area_m2' => null, 'company_id' => null]);
        $c->cnaes()->attach($restaurante->id, ['is_primary' => true]);
        $this->decidir($c, DecisionOutcome::Deferida, 'expresso', 6);

        $d = $this->solicitacao(ViabilityRequestStatus::EmAnalise, [
            'address_neighborhood' => 'Barra', 'used_area_m2' => null,
            'analysis_stage_started_at' => now()->subDays(3),
            'analysis_due_at' => now()->subDay(),
        ]);
        $d->cnaes()->attach($restaurante->id, ['is_primary' => true]);

        return ['analista' => $analista];
    }

    public function test_integracao_desligada_responde_503(): void
    {
        config(['viabiliza.integrations.bi.ativo' => false]);

        $this->getJson('/api_integracao/bi/indicadores')->assertStatus(503);
        $this->postJson('/api_integracao/bi/autenticar', ['usuario' => self::USUARIO, 'senha' => self::SENHA])->assertStatus(503);
    }

    public function test_sem_token_ou_com_token_invalido_responde_401(): void
    {
        $this->getJson('/api_integracao/bi/indicadores')->assertStatus(401);
        $this->getJson('/api_integracao/bi/indicadores', ['Authorization' => 'Bearer lixo'])->assertStatus(401);
        $this->getJson('/api_integracao/bi/indicadores/resumo-executivo')->assertStatus(401);
    }

    public function test_token_de_outra_integracao_nao_vale(): void
    {
        $tvl = Crypt::encryptString('tvl|'.(now()->timestamp + 600));

        $this->getJson('/api_integracao/bi/indicadores', ['Authorization' => "Bearer {$tvl}"])->assertStatus(401);
    }

    public function test_login_com_credencial_errada_responde_401_e_com_certa_devolve_o_token(): void
    {
        $this->postJson('/api_integracao/bi/autenticar', ['usuario' => self::USUARIO, 'senha' => 'errada'])->assertStatus(401);

        $this->postJson('/api_integracao/bi/autenticar', ['usuario' => self::USUARIO, 'senha' => self::SENHA])
            ->assertOk()
            ->assertJsonPath('tipo', 'Bearer')
            ->assertJsonPath('expira_em_segundos', 3600);
    }

    public function test_catalogo_lista_todos_os_grupos_e_indicadores(): void
    {
        $resposta = $this->getJson('/api_integracao/bi/indicadores', ['Authorization' => 'Bearer '.$this->token()])->assertOk();

        $this->assertSame(array_keys(BiCatalogo::GRUPOS), array_keys($resposta->json('grupos')));
        $this->assertCount(count(app(BiCatalogo::class)->indicadores()), $resposta->json('indicadores'));

        $porGrupo = collect($resposta->json('indicadores'))->countBy('grupo')->all();
        $this->assertSame(['estrategico', 'economico', 'territorial', 'operacional', 'automacao'], array_keys($porGrupo));

        $this->getJson('/api_integracao/bi/indicadores?grupo=automacao', ['Authorization' => 'Bearer '.$this->token()])
            ->assertOk()
            ->assertJsonCount($porGrupo['automacao'], 'indicadores');
    }

    public function test_swagger_lista_a_especificacao_e_cobre_todos_os_indicadores(): void
    {
        $this->get('/api_integracao/docs')->assertOk()->assertSee('/api_integracao/bi/docs/openapi.yaml', false);

        $spec = Yaml::parse((string) $this->get('/api_integracao/bi/docs/openapi.yaml')->getContent());

        foreach (array_keys(app(BiCatalogo::class)->indicadores()) as $slug) {
            $this->assertArrayHasKey("/api_integracao/bi/indicadores/{$slug}", $spec['paths']);
        }

        $this->assertArrayHasKey('/api_integracao/bi/autenticar', $spec['paths']);
    }

    public function test_indicador_inexistente_responde_404(): void
    {
        $this->indicador('nao-existe')->assertNotFound();
    }

    public function test_parametros_invalidos_respondem_422_em_json(): void
    {
        $this->indicador('resumo-executivo', ['data_de' => 'ontem'])->assertStatus(422)->assertJsonStructure(['mensagem', 'erros' => ['data_de']]);
        $this->indicador('resumo-executivo', ['agrupamento' => 'ano'])->assertStatus(422);
        $this->indicador('resumo-executivo', ['data_de' => '2026-02-01', 'data_ate' => '2026-01-01'])->assertStatus(422);
    }

    public function test_todos_os_indicadores_respondem_com_o_banco_vazio(): void
    {
        foreach (array_keys(app(BiCatalogo::class)->indicadores()) as $slug) {
            $this->indicador($slug)
                ->assertOk()
                ->assertJsonPath('indicador', $slug)
                ->assertJsonStructure(['indicador', 'grupo', 'titulo', 'gerado_em', 'filtros', 'dados']);
        }
    }

    public function test_todos_os_indicadores_respondem_com_dados_e_com_filtros(): void
    {
        $this->cenario();

        foreach (array_keys(app(BiCatalogo::class)->indicadores()) as $slug) {
            $this->indicador($slug)->assertOk();
            $this->indicador($slug, [
                'data_de' => now()->subYear()->toDateString(),
                'data_ate' => now()->toDateString(),
                'bairro' => 'Pituba',
                'agrupamento' => 'semana',
                'limite' => 5,
            ])->assertOk();
        }
    }

    public function test_resumo_executivo_fecha_os_numeros_do_cenario(): void
    {
        $this->cenario();

        $dados = $this->indicador('resumo-executivo')->assertOk()->json('dados');

        $this->assertSame(4, $dados['protocoladas']);
        $this->assertSame(1, $dados['em_analise']);
        $this->assertSame(2, $dados['deferidas']);
        $this->assertSame(1, $dados['indeferidas']);
        $this->assertSame(3, $dados['decididas']);
        $this->assertEquals(66.7, $dados['taxa_deferimento']);
        $this->assertEquals(4.0, $dados['tempo_medio_analise_horas_uteis']);
        $this->assertSame(1, $dados['processos_fora_do_prazo']);
    }

    public function test_taxa_deferimento_e_nula_sem_decisoes(): void
    {
        $dados = $this->indicador('taxa-deferimento')->assertOk()->json('dados');

        $this->assertSame(0, $dados['total']);
        $this->assertNull($dados['taxa_deferimento']);
    }

    public function test_solicitacoes_por_periodo_e_filtro_de_bairro(): void
    {
        $this->cenario();

        $this->assertSame(4, $this->indicador('solicitacoes-protocoladas')->json('dados.total'));
        $this->assertSame(2, $this->indicador('solicitacoes-protocoladas', ['bairro' => 'Barra'])->json('dados.total'));

        $serie = $this->indicador('solicitacoes-protocoladas', ['agrupamento' => 'mes'])->json('dados.serie');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $serie[0]['periodo']);
    }

    public function test_ranking_de_cnaes_e_indeferimento(): void
    {
        $this->cenario();

        $ranking = $this->indicador('ranking-cnaes')->json('dados');

        $this->assertSame('4712100', $ranking[0]['cnae']);
        $this->assertSame(1, $ranking[0]['posicao']);
        $this->assertSame(2, $ranking[0]['total']);
        $this->assertEquals(50.0, $ranking[0]['taxa_indeferimento']);

        $indeferimento = $this->indicador('atividades-maior-indeferimento', ['min_decididas' => 2])->json('dados');
        $this->assertCount(1, $indeferimento);
        $this->assertSame('4712100', $indeferimento[0]['cnae']);
    }

    public function test_porte_de_area_cobre_todas_as_faixas(): void
    {
        $this->cenario();

        $faixas = collect($this->indicador('solicitacoes-por-porte-area')->json('dados'))->keyBy('faixa');

        $this->assertCount(7, $faixas);
        $this->assertSame(1, $faixas['ate_50']['total']);
        $this->assertSame(1, $faixas['101_250']['total']);
        $this->assertSame(2, $faixas['nao_informado']['total']);
        $this->assertSame(0, $faixas['acima_1000']['total']);
    }

    public function test_mapa_de_decisoes_agrupa_pontos_proximos_na_mesma_celula(): void
    {
        $this->cenario();

        $celulas = $this->indicador('mapa-decisoes', ['precisao' => 3])->json('dados');

        $this->assertCount(1, $celulas);
        $this->assertEquals(-12.981, $celulas[0]['lat']);
        $this->assertSame(2, $celulas[0]['total']);
        $this->assertSame(1, $celulas[0]['deferidas']);
        $this->assertSame(1, $celulas[0]['indeferidas']);
        $this->assertEquals(50.0, $celulas[0]['taxa_indeferimento']);
        $this->assertSame(0, $celulas[0]['em_analise']);
        $this->assertSame('Pituba', $celulas[0]['bairro']);
        $this->assertSame('4712100', $celulas[0]['cnaes_principais'][0]['cnae']);
        $this->assertSame(2, $celulas[0]['cnaes_principais'][0]['total']);
        $this->assertEquals(3.0, $celulas[0]['tempo_medio_horas_uteis']);
    }

    public function test_restricoes_recorrentes_contam_os_fundamentos_das_decisoes(): void
    {
        $this->cenario();

        $restricoes = $this->indicador('restricoes-recorrentes')->json('dados');

        $this->assertSame(3, $restricoes[0]['decisoes']);
        $this->assertSame(1, $restricoes[0]['indeferidas']);
        $this->assertEquals(100.0, $restricoes[0]['participacao_indeferimentos']);
    }

    public function test_automacao_separa_expresso_de_analise_tecnica(): void
    {
        $this->cenario();

        $taxa = $this->indicador('taxa-automacao')->json('dados');
        $this->assertSame(3, $taxa['total']);
        $this->assertSame(2, $taxa['automaticas']);
        $this->assertSame(1, $taxa['analise_tecnica']);
        $this->assertEquals(66.7, $taxa['taxa_automacao']);

        $tempos = $this->indicador('tempo-expresso-x-tecnico')->json('dados');
        $this->assertEquals(4.0, $tempos['expresso']['media']);
        $this->assertEquals(4.0, $tempos['analise_tecnica']['media']);

        $this->assertSame(1, $this->indicador('decisoes-analise-tecnica')->json('dados.total'));
    }

    public function test_produtividade_por_analista_so_traz_o_nome_quando_liberado(): void
    {
        $analista = $this->cenario()['analista'];

        $linha = $this->indicador('produtividade-analista')->json('dados.0');
        $this->assertSame($analista->id, $linha['analista_id']);
        $this->assertSame(1, $linha['decididas']);
        $this->assertArrayNotHasKey('analista', $linha);

        config(['viabiliza.integrations.bi.nominal' => true]);

        $this->assertSame($analista->name, $this->indicador('produtividade-analista')->json('dados.0.analista'));
    }

    public function test_fila_em_aberto_e_sla_vencido(): void
    {
        $this->cenario();

        $fila = $this->indicador('envelhecimento-fila')->json('dados');
        $this->assertSame(1, $fila['total']);
        $this->assertSame(1, collect($fila['por_idade'])->firstWhere('faixa', 'ate_2_dias')['total'] + collect($fila['por_idade'])->firstWhere('faixa', '3_a_5_dias')['total']);

        $this->assertSame(1, $this->indicador('processos-sla-vencido')->json('dados.vencidos'));
        $this->assertSame(1, $this->indicador('solicitacoes-em-analise')->json('dados.total'));
    }

    public function test_mapa_traz_motivo_saturacao_e_tendencia_da_area(): void
    {
        $this->cenario();

        $celula = $this->indicador('mapa-decisoes')->json('dados.0');
        $this->assertCount(2, $celula['motivos_indeferimento']);
        $this->assertSame(1, $celula['motivos_indeferimento'][0]['indeferidas']);
        $this->assertSame('sem_capacidade', $celula['cnaes_principais'][0]['saturacao']);
        $this->assertFalse($celula['alerta_saturacao']);
        $this->assertNull($celula['tendencia']);

        $comPeriodo = $this->indicador('mapa-decisoes', ['data_de' => now()->subDays(10)->toDateString()])->json('dados.0');
        $this->assertSame(0, $comPeriodo['total_periodo_anterior']);
        $this->assertSame('nova', $comPeriodo['tendencia']);

        $leve = $this->indicador('mapa-concentracao')->json('dados.0');
        $this->assertSame(['lat', 'lng', 'total'], array_keys($leve));
    }

    public function test_comparativo_mostra_o_periodo_atual_o_anterior_e_o_ano_passado(): void
    {
        $this->cenario();

        $antigo = $this->solicitacao(ViabilityRequestStatus::Deferida, ['protocoled_at' => now()->subYear()->subDay()]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $antigo->id,
            'decided_at' => now()->subYear(),
            'tvl_product_number' => null,
        ]);

        $dados = $this->indicador('comparativo-periodo')->json('dados');
        $this->assertSame(['atual', 'anterior', 'ano_anterior'], array_keys($dados['periodos']));

        $deferidas = collect($dados['indicadores'])->firstWhere('indicador', 'deferidas');
        $this->assertSame(2, $deferidas['atual']);
        $this->assertSame(0, $deferidas['anterior']);
        $this->assertSame(1, $deferidas['ano_anterior']);
        $this->assertNull($deferidas['variacao_anterior_percentual']);
        $this->assertEquals(100.0, $deferidas['variacao_ano_percentual']);
        $this->assertSame('maior', $deferidas['melhor_quando']);

        $this->assertSame('menor', collect($dados['indicadores'])->firstWhere('indicador', 'tempo_medio_horas_uteis')['melhor_quando']);
    }

    public function test_impacto_economico_soma_area_e_empresas_das_deferidas(): void
    {
        $this->cenario();

        $dados = $this->indicador('impacto-economico')->json('dados');
        $this->assertSame(2, $dados['deferidas']);
        $this->assertSame(1, $dados['empresas_viabilizadas']);
        $this->assertEquals(40.0, $dados['area_liberada_m2']);
        $this->assertEquals(40.0, $dados['area_media_m2']);
        $this->assertSame(1, $dados['deferidas_sem_area_informada']);

        $porte = collect($dados['por_porte'])->keyBy('porte');
        $this->assertSame(1, $porte['MICRO EMPRESA']['deferidas']);
        $this->assertSame(1, $porte['Não informado']['deferidas']);
    }

    public function test_perfil_da_demanda_por_servico_e_origem(): void
    {
        $this->cenario();

        $dados = $this->indicador('perfil-demanda')->json('dados');

        $regin = collect($dados['por_origem'])->firstWhere('origem', 'regin');
        $this->assertSame(2, $regin['total']);
        $this->assertSame('REDESIM/Regin', $regin['rotulo']);

        $servico = collect($dados['por_servico'])->firstWhere('servico', 'Inscrição de empresa');
        $this->assertSame(1, $servico['total']);
        $this->assertSame(1, $servico['deferidas']);

        $this->assertSame(4, array_sum(array_column($dados['escritorio_virtual'], 'total')));
    }

    public function test_indeferimentos_por_zona_e_atividade_trazem_o_fundamento(): void
    {
        $this->cenario();

        $linhas = $this->indicador('indeferimentos-zona-atividade', ['min_decididas' => 1])->json('dados');

        $this->assertCount(1, $linhas);
        $this->assertSame('ZPR-1', $linhas[0]['zona']);
        $this->assertSame('4712100', $linhas[0]['cnae']);
        $this->assertSame(2, $linhas[0]['decididas']);
        $this->assertSame(1, $linhas[0]['indeferidas']);
        $this->assertEquals(50.0, $linhas[0]['taxa_indeferimento']);
        $this->assertNotNull($linhas[0]['fundamento_principal']);

        $this->assertSame([], $this->indicador('indeferimentos-zona-atividade', ['min_decididas' => 3])->json('dados'));
    }

    public function test_benchmark_redesim_responde_sem_competencia_importada(): void
    {
        $dados = $this->indicador('benchmark-redesim')->json('dados');

        $this->assertNull($dados['periodo']['diferenca_horas']);
        $this->assertNotNull($dados['periodo']['motivo']);
        $this->assertArrayHasKey('atual', $dados['mes_corrente']);
    }
}
