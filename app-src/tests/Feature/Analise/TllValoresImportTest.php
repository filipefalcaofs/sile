<?php

namespace Tests\Feature\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Services\Analise\TllCalculoService;
use App\Services\Analise\TllValoresImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Carga oficial da tabela TLL 2026 (Simplifica / taxas_tll_2026.xlsx).
 * 29 linhas, inclusive as duas 6.00 (ISENTA e residual). Publica o exercício
 * 2026 como vigente do domínio tll_valores.
 */
class TllValoresImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_importa_as_29_linhas_oficiais_e_publica_2026(): void
    {
        $report = app(TllValoresImportService::class)->import(
            database_path('data/tll/taxas-tll-2026.csv'),
        );

        $this->assertSame(29, $report['lidos']);
        $this->assertSame(29, $report['importados']);
        $this->assertSame([], $report['rejeitados']);
        $this->assertSame(29, TllValor::query()->where('exercicio', 2026)->active()->count());

        $versao = RuleVersion::query()->vigente(RuleDomain::TllValores)->where('version', '2026')->first();
        $this->assertNotNull($versao);
        $this->assertSame(RuleVersionStatus::Vigente, $versao->status);
    }

    public function test_import_e_idempotente_e_preserva_os_hashes_sefaz(): void
    {
        $service = app(TllValoresImportService::class);
        $csv = database_path('data/tll/taxas-tll-2026.csv');

        $service->import($csv);
        $report = $service->import($csv);

        $this->assertSame(29, TllValor::query()->where('exercicio', 2026)->count());
        $this->assertSame(0, $report['importados']);
        $this->assertSame(29, $report['atualizados']);
        $this->assertSame(1, RuleVersion::query()->where('domain', RuleDomain::TllValores->value)->where('version', '2026')->count());

        $varejo = TllValor::query()->where('codigo_tll', '2.02')->where('exercicio', 2026)->sole();
        $this->assertSame('554.33', (string) $varejo->valor);
        $this->assertSame('T45020425', $varejo->codigo_tll_sefaz);
        $this->assertSame('Comércio Varejista', $varejo->especificacao);
    }

    public function test_separa_isencao_e_residual_do_codigo_600(): void
    {
        app(TllValoresImportService::class)->import(database_path('data/tll/taxas-tll-2026.csv'));

        $calculo = app(TllCalculoService::class);

        $isenta = $calculo->calcular([
            ['codigo_tll' => '6.00', 'especificacao_tll' => 'ISENTA'],
        ], 2026);
        $this->assertNotNull($isenta);
        $this->assertSame('0.00', $isenta->valor);
        $this->assertSame('6.00', $isenta->codigo_tll);

        $residual = $calculo->calcular([
            ['codigo_tll' => '6.00'],
        ], 2026);
        $this->assertNotNull($residual);
        $this->assertSame('833.06', $residual->valor);
    }
}
