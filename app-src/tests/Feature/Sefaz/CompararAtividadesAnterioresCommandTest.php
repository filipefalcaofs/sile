<?php

namespace Tests\Feature\Sefaz;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
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
        $processo = $this->processo(['4711302', '5611201']);

        $this->artisan('processos:comparar-atividades-anteriores --dry-run')
            ->expectsOutputToContain('incluídas 5611201')
            ->assertSuccessful();

        $this->assertNull($processo->fresh()->comparacao_cnaes);
    }

    public function test_ignora_decididos_inscricoes_e_ja_comparados(): void
    {
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

    public function test_refazer_recalcula_os_ja_comparados(): void
    {
        $processo = $this->processo(['4711302', '5611201']);
        $processo->forceFill(['comparacao_cnaes' => ['incluidas' => ['x'], 'excluidas' => [], 'mantidas' => [], 'invalidas' => [], 'sem_viabilidade' => false, 'numero_viabilidade' => null]])->save();

        $this->artisan('processos:comparar-atividades-anteriores --refazer')->assertSuccessful();

        $this->assertSame(['5611201'], $processo->fresh()->comparacao_cnaes['incluidas']);
    }
}
