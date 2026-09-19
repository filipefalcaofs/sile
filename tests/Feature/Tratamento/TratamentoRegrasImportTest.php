<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TratamentoRegrasImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_import_e_idempotente_e_afirma_contagens(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);

        $service = new TratamentoRegrasImportService;
        $dir = database_path('data/regras-20-08-26');

        $primeiro = $service->import($versao, $dir);
        $segundo = $service->import($versao, $dir);

        $this->assertSame([], $primeiro['rejeitados']);
        $this->assertSame(32, TratamentoPergunta::query()->where('rule_version_id', $versao->id)->count());
        $this->assertSame(59, TratamentoRegra::query()->where('rule_version_id', $versao->id)->count());
        $this->assertSame(2850, TratamentoEnquadramento::query()->where('rule_version_id', $versao->id)->count());
        $this->assertSame(1332, TratamentoEnquadramento::query()->where('rule_version_id', $versao->id)->distinct()->count('cnae'));
        $this->assertSame(0, TratamentoRegra::query()->where('rule_version_id', $versao->id)->where('numero', 45)->count());
        $this->assertTrue(
            TratamentoEnquadramento::query()
                ->where('rule_version_id', $versao->id)
                ->where('cnae', '9900-8/00')
                ->exists(),
        );

        $this->assertSame($primeiro['importados'], $segundo['atualizados']);
        $this->assertSame(0, $segundo['importados']);
        $this->assertSame(
            TratamentoCnaeBinding::query()->where('rule_version_id', $versao->id)->count(),
            2850,
        );
    }
}
