<?php

namespace Tests\Feature\Sefaz;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use Database\Seeders\DecisionTextSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\Sefaz\ConfiguraLegadoSimplifica;
use Tests\TestCase;

/**
 * Preenche, para os processos de alteração que já estão na análise, a
 * comparação das atividades com a viabilidade anterior da empresa (Simplifica),
 * sem decidir nada.
 */
class CompararAtividadesAnterioresCommandTest extends TestCase
{
    use ConfiguraLegadoSimplifica;
    use LazilyRefreshDatabase;

    private const CNPJ = '12345678000190';

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['viabiliza.sefaz.consulta.ativa' => false]);
        $this->configurarLegado();
    }

    private function legadoPadrao(): void
    {
        Http::fake([
            'legado.test/v1/tvls?*' => Http::response(['data' => [
                ['numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z'],
            ]]),
            'legado.test/v1/tvls/416389' => Http::response(['data' => [
                'numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z',
                'atividades' => [['codigo' => '4711-3/02', 'descricao' => 'Comércio']],
            ]]),
        ]);
    }

    /**
     * @param  list<string>  $codigos
     */
    private function processo(array $codigos, string $evento = 'Alteração de atividades econômicas (principal e secundárias)', ViabilityRequestStatus $status = ViabilityRequestStatus::EmAnalise): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'company_id' => (Company::query()->where('cnpj', self::CNPJ)->first() ?? Company::factory()->create(['cnpj' => self::CNPJ]))->id,
        ]);
        $request->forceFill(['status' => $status])->save();

        foreach ($codigos as $indice => $codigo) {
            $cnae = Cnae::query()->firstOrCreate(['code' => $codigo], Cnae::factory()->make(['code' => $codigo])->toArray());
            $request->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        ViabilityRequestEvento::factory()->create(['viability_request_id' => $request->id, 'codigo' => '244', 'nome' => $evento]);

        return $request->fresh();
    }

    public function test_grava_incluidas_e_existentes_dos_processos_em_analise(): void
    {
        $this->legadoPadrao();
        $processo = $this->processo(['4711302', '5611201']);

        $this->artisan('processos:comparar-atividades-anteriores')
            ->expectsOutputToContain('comparados 1')
            ->assertSuccessful();

        $comparacao = $processo->fresh()->comparacao_cnaes;
        $this->assertSame(['5611201'], $comparacao['incluidas']);
        $this->assertSame(['4711302'], $comparacao['mantidas']);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
    }

    public function test_dry_run_nao_grava(): void
    {
        $this->legadoPadrao();
        $processo = $this->processo(['4711302', '5611201']);

        $this->artisan('processos:comparar-atividades-anteriores --dry-run')
            ->expectsOutputToContain('incluídas 5611201')
            ->assertSuccessful();

        $this->assertNull($processo->fresh()->comparacao_cnaes);
    }

    public function test_ignora_decididos_inscricoes_e_ja_comparados(): void
    {
        $this->legadoPadrao();
        $decidido = $this->processo(['5611201'], status: ViabilityRequestStatus::Deferida);
        $inscricao = $this->processo(['5611201'], evento: 'Inscrição de primeiro estabelecimento');
        $inscricao->eventos()->update(['codigo' => '101']);
        $jaComparado = $this->processo(['5611201']);
        $jaComparado->forceFill(['comparacao_cnaes' => ['incluidas' => ['x'], 'excluidas' => [], 'mantidas' => [], 'invalidas' => [], 'sem_viabilidade' => false, 'numero_viabilidade' => null]])->save();

        $this->artisan('processos:comparar-atividades-anteriores')
            ->expectsOutputToContain('comparados 0')
            ->assertSuccessful();

        $this->assertNull($decidido->fresh()->comparacao_cnaes);
        $this->assertNull($inscricao->fresh()->comparacao_cnaes);
        $this->assertSame(['x'], $jaComparado->fresh()->comparacao_cnaes['incluidas']);
    }

    private function legadoResponde(array $respostas): void
    {
        $sequencia = Http::sequence();
        foreach ($respostas as $resposta) {
            $sequencia->push(...$resposta);
        }

        Http::fake([
            'legado.test/v1/tvls?*' => $sequencia,
            'legado.test/v1/tvls/416389' => Http::response(['data' => [
                'numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z',
                'atividades' => [['codigo' => '4711-3/02', 'descricao' => 'Comércio']],
            ]]),
        ]);
    }

    public function test_falha_do_simplifica_nao_grava_comparacao_sem_base(): void
    {
        $this->legadoResponde(array_fill(0, 5, [['mensagem' => 'Limite de 60 requisições por minuto excedido.'], 429]));
        $processo = $this->processo(['4711302', '5611201']);

        $this->artisan('processos:comparar-atividades-anteriores', ['--espera' => 0])
            ->expectsOutputToContain('erros 1')
            ->assertFailed();

        $this->assertNull($processo->fresh()->comparacao_cnaes);
    }

    public function test_limite_do_simplifica_espera_e_tenta_de_novo(): void
    {
        $this->legadoResponde([
            [['mensagem' => 'Limite de 60 requisições por minuto excedido.'], 429],
            [['data' => [['numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z']]]],
        ]);
        $processo = $this->processo(['4711302', '5611201']);

        $this->artisan('processos:comparar-atividades-anteriores', ['--espera' => 0])
            ->expectsOutputToContain('comparados 1')
            ->assertSuccessful();

        $this->assertSame(['5611201'], $processo->fresh()->comparacao_cnaes['incluidas']);
    }

    public function test_refazer_sem_base_recalcula_so_os_que_ficaram_sem_viabilidade(): void
    {
        $this->legadoPadrao();
        $semBase = $this->processo(['4711302', '5611201']);
        $semBase->forceFill(['comparacao_cnaes' => ['incluidas' => ['4711302', '5611201'], 'excluidas' => [], 'mantidas' => [], 'invalidas' => [], 'sem_viabilidade' => true, 'numero_viabilidade' => null]])->save();
        $comBase = $this->processo(['5611201']);
        $comBase->forceFill(['comparacao_cnaes' => ['incluidas' => ['x'], 'excluidas' => [], 'mantidas' => [], 'invalidas' => [], 'sem_viabilidade' => false, 'numero_viabilidade' => '1']])->save();

        $this->artisan('processos:comparar-atividades-anteriores', ['--refazer-sem-base' => true, '--espera' => 0])
            ->expectsOutputToContain('comparados 1')
            ->assertSuccessful();

        $this->assertSame(['5611201'], $semBase->fresh()->comparacao_cnaes['incluidas']);
        $this->assertSame(['x'], $comBase->fresh()->comparacao_cnaes['incluidas']);
    }

    public function test_refazer_recalcula_os_ja_comparados(): void
    {
        $this->legadoPadrao();
        $processo = $this->processo(['4711302', '5611201']);
        $processo->forceFill(['comparacao_cnaes' => ['incluidas' => ['x'], 'excluidas' => [], 'mantidas' => [], 'invalidas' => [], 'sem_viabilidade' => false, 'numero_viabilidade' => null]])->save();

        $this->artisan('processos:comparar-atividades-anteriores --refazer')->assertSuccessful();

        $this->assertSame(['5611201'], $processo->fresh()->comparacao_cnaes['incluidas']);
    }

    private function legadoComExcluida(): void
    {
        Http::fake([
            'legado.test/v1/tvls?*' => Http::response(['data' => [
                ['numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z'],
            ]]),
            'legado.test/v1/tvls/416389' => Http::response(['data' => [
                'numero' => 416389, 'tipo' => 'provisoria', 'deferida' => true, 'ativa' => true, 'validade' => '2027-01-01T00:00:00Z',
                'atividades' => [['codigo' => '4711-3/02', 'descricao' => 'Comércio'], ['codigo' => '7490-1/04', 'descricao' => 'Intermediação']],
            ]]),
        ]);
    }

    private function naCaixaSemAnalista(ViabilityRequest $processo): ViabilityRequest
    {
        $processo->forceFill(['analysis_status' => AnalysisStatus::ParaDistribuir, 'assigned_user_id' => null])->save();

        return $processo->fresh();
    }

    public function test_so_exclusao_na_caixa_e_deferida_sem_taxa_depois_da_comparacao(): void
    {
        $this->seed(DecisionTextSeeder::class);
        $this->legadoComExcluida();
        $processo = $this->naCaixaSemAnalista($this->processo(['4711302']));

        $this->artisan('processos:comparar-atividades-anteriores')
            ->expectsOutputToContain('deferidos 1')
            ->assertSuccessful();

        $processo->refresh();
        $this->assertSame(ViabilityRequestStatus::Deferida, $processo->status);
        $this->assertSame(['7490104'], array_column($processo->decision->per_cnae, 'cnae'));
        $this->assertSame(['excluir'], array_column($processo->decision->per_cnae, 'intencao'));
        $this->assertSame([], array_filter(array_column($processo->decision->per_cnae, 'codigo_tll')), 'exclusão não gera taxa');
    }

    public function test_dry_run_so_avisa_que_a_exclusao_seria_deferida(): void
    {
        $this->legadoComExcluida();
        $processo = $this->naCaixaSemAnalista($this->processo(['4711302']));

        $this->artisan('processos:comparar-atividades-anteriores --dry-run')
            ->expectsOutputToContain('só exclusão de atividade, seria deferido sem taxa')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
    }

    public function test_exclusao_assumida_por_analista_tambem_e_deferida(): void
    {
        $this->seed(DecisionTextSeeder::class);
        $this->legadoComExcluida();
        $assumido = $this->processo(['4711302']);
        $assumido->forceFill(['analysis_status' => AnalysisStatus::Analisar, 'assigned_user_id' => User::factory()->create()->id])->save();

        $this->artisan('processos:comparar-atividades-anteriores')
            ->expectsOutputToContain('deferidos 1')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::Deferida, $assumido->fresh()->status);
        $this->assertNull($assumido->fresh()->assigned_user_id);
    }

    public function test_mudanca_de_endereco_ou_pedido_sem_exclusao_fica_na_analise(): void
    {
        $this->seed(DecisionTextSeeder::class);
        $this->legadoPadrao();
        $semExclusao = $this->naCaixaSemAnalista($this->processo(['4711302']));

        $this->artisan('processos:comparar-atividades-anteriores')
            ->expectsOutputToContain('deferidos 0')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $semExclusao->fresh()->status);
    }

    public function test_exclusao_com_mudanca_de_endereco_fica_na_analise(): void
    {
        $this->seed(DecisionTextSeeder::class);
        $this->legadoComExcluida();
        $comEndereco = $this->naCaixaSemAnalista($this->processo(['4711302']));
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $comEndereco->id, 'codigo' => '211', 'nome' => 'Alteração de endereço dentro do mesmo município']);

        $this->artisan('processos:comparar-atividades-anteriores')
            ->expectsOutputToContain('deferidos 0')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $comEndereco->fresh()->status);
    }
}
