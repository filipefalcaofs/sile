<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Models\Company;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Regin\ReginProtocoloSimulacaoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReginProcessoProtocoladorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ruc(array $overrides = []): array
    {
        $rowset = [
            'RUC_GENERAL' => [
                'RGE_CGC_CPF' => '13927801000149',
                'RGE_NOMB' => 'Empresa REGIN Teste',
            ],
            'RUC_ESTAB' => [
                'RES_AREA' => '834',
                'RES_DIRECCION' => 'Rua das Flores',
                'RES_NUME' => '100',
                'RES_IDENT_COMP' => 'Sala 2',
                'RES_URBANIZACION' => 'Centro',
                'RES_ZONA_POSTAL' => '40000000',
            ],
            'GROUPRUC_ACTV_ECON' => [
                'RUC_ACTV_ECON' => [
                    ['RAE_TAE_COD_ACTVD' => '4712100', 'RAE_CALIF_ACTV' => '1'],
                    ['RAE_TAE_COD_ACTVD' => '5611201', 'RAE_CALIF_ACTV' => '2'],
                ],
            ],
            'GROUPRUC_GEN_PROTOCOLO' => [
                'RUC_GEN_PROTOCOLO' => [
                    ['RGP_TGE_COD_TIP_TAB' => '5', 'RGP_VALOR' => '12345678'],
                ],
            ],
        ];

        return ['rowset' => array_replace_recursive($rowset, $overrides)];
    }

    private function recebimento(array $corpo): ReginRecebimento
    {
        return ReginRecebimento::factory()->create([
            'protocolo' => '43747',
            'corpo' => $corpo,
        ]);
    }

    public function test_ruc_completo_protocola_com_cnaes_endereco_area_e_inscricao(): void
    {
        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($this->ruc()));

        $this->assertNotNull($processo);
        $this->assertSame(ViabilityRequestOrigin::Regin, $processo->origin);
        $this->assertSame(ReginProtocoloSimulacaoService::CONTINGENCIA_RECEBE, $processo->contingency_reason);
        $this->assertSame('43747', $processo->external_reference);
        $this->assertSame(834.0, (float) $processo->used_area_m2);
        $this->assertSame('Rua das Flores', $processo->address_street);
        $this->assertSame('100', $processo->address_number);
        $this->assertSame('Sala 2', $processo->address_complement);
        $this->assertSame('Centro', $processo->address_neighborhood);
        $this->assertSame('40000000', $processo->address_zip);
        $this->assertSame('12345678', $processo->property_registration);
        $this->assertSame(2, $processo->cnaes()->count());
        $this->assertSame('4712100', $processo->primaryCnae()->first()?->code);
        $this->assertNotNull($processo->protocol_number);
    }

    public function test_ruc_sem_cnae_principal_nao_protocola(): void
    {
        $corpo = $this->ruc();
        unset($corpo['rowset']['GROUPRUC_ACTV_ECON']);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNull($processo);
        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_ruc_sem_area_nao_protocola(): void
    {
        $corpo = $this->ruc(['RUC_ESTAB' => ['RES_AREA' => '']]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($corpo));

        $this->assertNull($processo);
        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_empresa_e_reusada_pelo_cnpj(): void
    {
        $empresa = Company::factory()->create([
            'cnpj' => '13927801000149',
            'legal_name' => 'Empresa já cadastrada',
        ]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($this->recebimento($this->ruc()));

        $this->assertNotNull($processo);
        $this->assertSame($empresa->id, $processo->company_id);
        $this->assertSame(1, Company::query()->where('cnpj', '13927801000149')->count());
    }
}
