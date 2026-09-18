<?php

namespace Tests\Feature\Risco;

use App\Enums\RiscoMunicipal;
use App\Enums\RuleDomain;
use App\Enums\TipoGatilho;
use App\Enums\TipoImovelReconhecimento;
use App\Models\PropertyType;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O catálogo vigente vem do banco (cadastro administrável). O seed oficial
 * resolve EXATAMENTE como o sedur200826() embutido — prova de não-regressão
 * da migração código → banco.
 */
class TipoImovelCatalogVigenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogo_semeado_resolve_como_o_embutido(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $catalogo = TipoImovelCatalog::vigente();

        foreach (['GALPÃO', 'Container', 'Edificação Residencial'] as $raw) {
            $this->assertSame(
                TipoImovelReconhecimento::DirigeRegra,
                TipoImovel::fromRegin($raw, $catalogo)->reconhecimento,
                $raw,
            );
        }

        $comum = TipoImovel::fromRegin('Edificação Comercial', $catalogo);
        $this->assertSame(TipoImovelReconhecimento::RamoComum, $comum->reconhecimento);
        $this->assertSame('edificacao_comercial', $comum->normalized);

        $desconhecido = TipoImovel::fromRegin('Loja de shopping (grafia nova)', $catalogo);
        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $desconhecido->reconhecimento);
    }

    public function test_escrita_no_cadastro_invalida_o_cache(): void
    {
        $this->seed(PropertyTypeSeeder::class);

        $antes = TipoImovel::fromRegin('Galpão logístico', TipoImovelCatalog::vigente());
        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $antes->reconhecimento);

        PropertyType::query()->where('code', 'galpao')->firstOrFail()
            ->aliases()->create(['alias' => 'Galpão logístico']);

        $depois = TipoImovel::fromRegin('Galpão logístico', TipoImovelCatalog::vigente());
        $this->assertSame(TipoImovelReconhecimento::DirigeRegra, $depois->reconhecimento);
    }

    public function test_tipo_inativo_sai_do_reconhecimento_e_degrada_para_analise(): void
    {
        $this->seed([PropertyTypeSeeder::class, RiskTriggerSeeder::class]);

        PropertyType::query()->where('code', 'galpao')->firstOrFail()->update(['active' => false]);

        $tipo = TipoImovel::fromRegin('GALPÃO', TipoImovelCatalog::vigente());
        $this->assertSame(TipoImovelReconhecimento::Desconhecido, $tipo->reconhecimento);
        $this->assertFalse($tipo->permiteDecisaoAutomatica());

        // Efeito de ROTEAMENTO: o mesmo CNAE que iria ao expresso cai para
        // análise, porque o valor do REGIN deixou de ser reconhecido.
        $municipal = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoMunicipal,
            'version' => 'decreto-32636-2020',
            'rules_version' => 'decreto-32636-2020',
        ]);
        RiskClassification::factory()->create([
            'rule_version_id' => $municipal->id,
            'cnae_code' => '1212121',
            'risco_municipal' => RiscoMunicipal::BaixoA,
        ]);

        $service = app(RiscoClassificationService::class);

        $semTipo = $service->classify(RiscoInput::paraCnae('1212121'));
        $this->assertSame('expresso', $semTipo->encaminhamento['fluxo']);

        $comTipoInativo = $service->classify(new RiscoInput(cnaeCode: '1212121', tipoImovel: $tipo));
        $this->assertSame('analise', $comTipoInativo->encaminhamento['fluxo']);
        $this->assertContains(
            TipoGatilho::DadosDoProcesso->value,
            array_column($comTipoInativo->encaminhamento['gatilhos_acionados'], 'codigo'),
        );
    }
}
