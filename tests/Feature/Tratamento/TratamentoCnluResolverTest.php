<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoCnluItem;
use App\Services\Tratamento\TratamentoRamoInput;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Tratamento\TratamentoRamoResult;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Enquadramento sinalizado como CNLU nunca é decidido pelo motor: o ramo fica
 * não resolvido e segue para análise (resposta da SEDUR de 07/10/2026).
 */
class TratamentoCnluResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private RuleVersion $versao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);

        (new TratamentoRegrasImportService)->import($this->versao, database_path('data/regras-20-08-26'));
    }

    private function cnlu(string ...$codigos): void
    {
        foreach ($codigos as $codigo) {
            TratamentoCnluItem::query()->create([
                'rule_version_id' => $this->versao->getKey(),
                'codigo' => $codigo,
                'texto' => 'Aba Análise CNLU',
            ]);
        }
    }

    /**
     * Responde Sim a toda pergunta ligada ao CNAE: o ramo "no local" é o que
     * carrega o enquadramento sinalizado.
     *
     * @return array<int, bool>
     */
    private function respostasSim(string $cnae): array
    {
        $perguntas = TratamentoCnaeBinding::query()
            ->where('rule_version_id', $this->versao->getKey())
            ->where('cnae', $cnae)
            ->get()
            ->pluck('perguntas')
            ->flatten()
            ->filter()
            ->unique();

        return $perguntas->mapWithKeys(fn (mixed $numero): array => [(int) $numero => true])->all();
    }

    private function resolver(string $cnae): TratamentoRamoResult
    {
        return app(TratamentoRamoResolver::class)->resolver(new TratamentoRamoInput(
            cnae: $cnae,
            respostas: $this->respostasSim($cnae),
            areaUtilizada: 100.0,
        ));
    }

    public function test_subgrupo_com_espaco_depois_do_hifen_vai_para_analise(): void
    {
        $this->cnlu('ID3-07');

        $ramo = $this->resolver('0500-3/01');

        $this->assertSame('nao_resolvido', $ramo->status);
        $this->assertSame('analise', $ramo->fluxo);
        $this->assertSame('A SER DEFINIDO PELA CNLU', $ramo->motivo);
    }

    public function test_subgrupo_com_sufixo_vai_para_analise(): void
    {
        $this->cnlu('ID3-19');

        $ramo = $this->resolver('0210-1/08');

        $this->assertSame('A SER DEFINIDO PELA CNLU', $ramo->motivo);
    }

    public function test_subgrupo_fora_da_lista_nao_vai_para_a_cnlu(): void
    {
        $this->cnlu('nR3-08');

        $ramo = $this->resolver('0500-3/01');

        $this->assertNotSame('A SER DEFINIDO PELA CNLU', $ramo->motivo);
    }
}
