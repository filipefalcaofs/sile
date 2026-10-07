<?php

namespace Tests\Feature\Expresso;

use App\Enums\ResultadoViabilidade;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\SolicitacaoViabilityResolver;
use App\Services\Tratamento\TratamentoRamosSedur;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Regra 38 (Administração Pública): a categoria da empresa vem da natureza
 * jurídica do cadastro e chega ao motor da solicitação.
 */
class SolicitacaoCategoriaEmpresaTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    private function solicitacao(?string $naturezaCodigo, ?string $naturezaDescricao = null): ViabilityRequest
    {
        $versao = $this->seedTratamentoPlanilha();
        (new TratamentoRamosSedur)->carregar($versao, database_path('data/regras-20-08-26/regras-fluxo-sedur.json'));

        $empresa = Company::factory()->create([
            'legal_nature_code' => $naturezaCodigo,
            'legal_nature' => $naturezaDescricao,
        ]);
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'used_area_m2' => 120.0,
            'company_id' => $empresa->id,
        ]);
        $solicitacao->cnaes()->attach(Cnae::factory()->create(['code' => '8411600'])->id, ['is_primary' => true]);
        $solicitacao->respostasTratamento = [7 => true];

        return $solicitacao;
    }

    public function test_empresa_que_nao_e_orgao_publico_e_indeferida_pela_regra_38(): void
    {
        $resolvida = app(SolicitacaoViabilityResolver::class)->resolve($this->solicitacao('2062'));

        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $resolvida->consolidado);
        $this->assertSame(
            'Esta atividade só é permitida para órgãos da Administração Pública',
            $resolvida->por_cnae[0]['consulta']->enquadramento->consolidado['motivo'],
        );
    }

    public function test_orgao_publico_enquadra_orgaos_dos_poderes(): void
    {
        $resolvida = app(SolicitacaoViabilityResolver::class)->resolve($this->solicitacao('1015'));

        $enquadramento = $resolvida->por_cnae[0]['consulta']->enquadramento->enquadramento;

        $this->assertSame('07.11.03', $enquadramento['codigo_louos']);
        $this->assertFalse($enquadramento['bloqueia']);
    }

    public function test_sem_natureza_juridica_o_motor_nao_decide(): void
    {
        $resolvida = app(SolicitacaoViabilityResolver::class)->resolve($this->solicitacao(null));

        $this->assertSame(ResultadoViabilidade::Pendente->value, $resolvida->consolidado);
        $this->assertSame(
            'Categoria da empresa ausente para a regra que depende dela',
            $resolvida->por_cnae[0]['consulta']->enquadramento->enquadramento['motivo'],
        );
    }
}
