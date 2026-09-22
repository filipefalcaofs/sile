<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoRegraRamo;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Ramos curados de fluxo da planilha de tratamento (relatório SEDUR 21/09,
 * itens 18/20/21/25 + decisão 22/09): o fluxo expresso/semiexpresso de cada
 * ramo é dado versionado extraído dos textos oficiais das regras — nunca
 * heurística. O import lê o regras-fluxo.csv junto aos demais arquivos da
 * planilha, idempotente.
 */
class TratamentoRegraRamoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_import_carrega_os_ramos_curados_das_regras(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-teste',
        ]);

        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));

        // Regra 1 (item 20): P11=SIM → crítica do analista (semiexpresso).
        $this->assertDatabaseHas('treatment_regra_ramos', [
            'rule_version_id' => $versao->id,
            'regra' => 1,
            'pergunta' => 11,
            'resposta' => true,
            'fluxo' => 'semiexpresso',
        ]);

        // Regra 51 (item 25): P2=NÃO e área ≤ 1.250 m² → expresso MESMO com
        // ALTO RISCO — a planilha prevalece sobre a RN-041-B.
        $this->assertDatabaseHas('treatment_regra_ramos', [
            'rule_version_id' => $versao->id,
            'regra' => 51,
            'pergunta' => 2,
            'resposta' => false,
            'faixa' => 'ate_1250',
            'codigo_louos' => '07.12.13',
            'fluxo' => 'expresso',
        ]);

        // Regra 50 (item 18): SIM ≤ 1.250 m² → 07.05.03 expresso.
        $this->assertDatabaseHas('treatment_regra_ramos', [
            'rule_version_id' => $versao->id,
            'regra' => 50,
            'resposta' => true,
            'faixa' => 'ate_1250',
            'codigo_louos' => '07.05.03',
            'fluxo' => 'expresso',
        ]);
    }

    public function test_import_dos_ramos_e_idempotente(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-teste',
        ]);

        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
        $total = TratamentoRegraRamo::query()->where('rule_version_id', $versao->id)->count();

        $this->assertGreaterThan(0, $total);

        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));

        $this->assertSame($total, TratamentoRegraRamo::query()->where('rule_version_id', $versao->id)->count());
    }
}
