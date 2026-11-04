<?php

namespace Tests\Feature\Expresso;

use App\Enums\AnalysisPendencyStatus;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisPendency;
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
 * Processos em análise que só excluem atividades, sem mudança de endereço, são
 * deferidos no expresso, sem taxa, mesmo quando um analista já assumiu.
 */
class DeferirExclusaoAtividadeCommandTest extends TestCase
{
    use ConfiguraLegadoSimplifica;
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DecisionTextSeeder::class);
        config(['viabiliza.sefaz.consulta.ativa' => false]);
        $this->configurarLegado();
        Http::fake();
    }

    /**
     * @param  list<string>  $incluidas
     * @param  list<string>  $excluidas
     */
    private function emAnalise(array $incluidas = [], array $excluidas = ['7490104'], ?User $analista = null, string $evento = '244', string $nome = 'Alteração de atividades econômicas (principal e secundárias)'): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'company_id' => Company::factory()->create(['cnpj' => '1234567800019'.($this->seq % 10)])->id,
        ]);
        $cnae = Cnae::query()->firstOrCreate(['code' => '4711302'], Cnae::factory()->make(['code' => '4711302'])->toArray());
        $processo->cnaes()->attach($cnae->id, ['is_primary' => true]);
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $processo->id, 'codigo' => $evento, 'nome' => $nome]);

        $processo->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => $analista ? AnalysisStatus::Analisar : AnalysisStatus::ParaDistribuir,
            'assigned_user_id' => $analista?->id,
            'comparacao_cnaes' => ['incluidas' => $incluidas, 'excluidas' => $excluidas, 'mantidas' => ['4711302'], 'invalidas' => [], 'numero_viabilidade' => '416389', 'sem_viabilidade' => false],
        ])->save();

        return $processo->fresh();
    }

    public function test_defere_sem_taxa_inclusive_o_assumido_e_cancela_o_convite_aberto(): void
    {
        $livre = $this->emAnalise();
        $assumido = $this->emAnalise(analista: User::factory()->create());
        $convite = AnalysisPendency::factory()->create(['viability_request_id' => $assumido->id, 'status' => AnalysisPendencyStatus::Aberta]);

        $this->artisan('processos:deferir-exclusao-atividade')
            ->expectsOutputToContain('deferidos 2')
            ->assertSuccessful();

        foreach ([$livre, $assumido] as $processo) {
            $processo->refresh();
            $this->assertSame(ViabilityRequestStatus::Deferida, $processo->status);
            $this->assertSame(['7490104'], array_column($processo->decision->per_cnae, 'cnae'));
            $this->assertSame([], array_filter(array_column($processo->decision->per_cnae, 'codigo_tll')), 'sem taxa');
        }

        $this->assertNull($assumido->assigned_user_id);
        $this->assertSame(AnalysisPendencyStatus::Cancelada, $convite->fresh()->status);
    }

    public function test_inclusao_mudanca_de_endereco_e_pedido_sem_exclusao_ficam_na_analise(): void
    {
        $comInclusao = $this->emAnalise(incluidas: ['5611201']);
        $semExclusao = $this->emAnalise(excluidas: []);
        $comEndereco = $this->emAnalise();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $comEndereco->id, 'codigo' => '211', 'nome' => 'Alteração de endereço dentro do mesmo município']);

        $this->artisan('processos:deferir-exclusao-atividade')
            ->expectsOutputToContain('deferidos 0')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $comInclusao->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $comEndereco->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $semExclusao->fresh()->status);
    }

    public function test_dry_run_nao_muda_nada(): void
    {
        $analista = User::factory()->create();
        $assumido = $this->emAnalise(analista: $analista);

        $this->artisan('processos:deferir-exclusao-atividade --dry-run')
            ->expectsOutputToContain('seria deferido sem taxa')
            ->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $assumido->fresh()->status);
        $this->assertSame($analista->id, $assumido->fresh()->assigned_user_id);
    }

    public function test_so_os_ids_pedidos(): void
    {
        $pedido = $this->emAnalise();
        $outro = $this->emAnalise();

        $this->artisan('processos:deferir-exclusao-atividade', ['--id' => [$pedido->id]])->assertSuccessful();

        $this->assertSame(ViabilityRequestStatus::Deferida, $pedido->fresh()->status);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $outro->fresh()->status);
    }
}
