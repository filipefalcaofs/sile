<?php

namespace Tests\Feature\Louos;

use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\RuleVersion;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use App\Services\Risco\TipoImovel;
use App\Services\Risco\TipoImovelCatalog;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LouosEnquadramentoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);

        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
    }

    public function test_enquadramento_vem_do_ramo_e_nao_tem_quadro7(): void
    {
        $result = $this->service()->enquadrar(new EnquadramentoInput(
            area: 800.0,
            cnaePrincipal: '0111-3/01',
            respostas: [11 => false],
        ));

        $this->assertArrayNotHasKey('quadro7', $result->toArray());
        $this->assertArrayHasKey('enquadramento', $result->toArray());
        $this->assertFalse(Schema::hasTable('louos_quadro7_faixas'));
        $this->assertSame(EnquadramentoResult::STATUS_IDENTIFICADO, $result->enquadramento['status']);
        $this->assertSame('nR1', $result->enquadramento['grupo']);
        $this->assertSame('nR1-12', $result->enquadramento['subgrupo']);
        $this->assertSame('07.12.13', $result->enquadramento['codigo_louos']);
        $this->assertSame('planilha-20-08-26', $result->versoes()['risco_tratamento']);
        $this->assertArrayNotHasKey('quadro7', $result->versoes());
        $this->assertStringNotContainsStringIgnoringCase('quadro 7', (string) $result->enquadramento['motivo']);
    }

    public function test_mesmo_cnae_escolhe_o_ramo_pela_pergunta(): void
    {
        $escritorio = $this->service()->enquadrar(new EnquadramentoInput(
            area: 400.0,
            cnaePrincipal: '0210-1/07',
            respostas: [11 => false],
        ));

        $noLocal = $this->service()->enquadrar(new EnquadramentoInput(
            area: 400.0,
            cnaePrincipal: '0210-1/07',
            respostas: [11 => true],
            tipoImovel: TipoImovel::fromRegin('GALPÃO', TipoImovelCatalog::sedur200826()),
        ));

        $this->assertSame('07.12.13', $escritorio->enquadramento['codigo_louos']);
        $this->assertSame('nR1-12', $escritorio->enquadramento['subgrupo']);
        $this->assertSame('ID2-07', $noLocal->enquadramento['subgrupo']);
        $this->assertNotSame(
            $escritorio->enquadramento['subgrupo'],
            $noLocal->enquadramento['subgrupo'],
        );
    }

    public function test_ramo_nao_resolvido_consolida_pendente(): void
    {
        $result = $this->service()->enquadrar(new EnquadramentoInput(
            area: 800.0,
            cnaePrincipal: '0111-3/01',
            respostas: [],
        ));

        $this->assertSame(EnquadramentoResult::STATUS_NAO_ENCONTRADO, $result->enquadramento['status']);
        $this->assertSame(ResultadoViabilidade::Pendente->value, $result->resultado());
        $this->assertTrue($result->pendente());
        $this->assertContains(
            $result->resultado(),
            [ResultadoViabilidade::Pendente->value],
        );
        $this->assertNotSame(ResultadoViabilidade::Permitido->value, $result->resultado());
        $this->assertNotSame(ResultadoViabilidade::NaoPermitido->value, $result->resultado());
    }

    public function test_enquadramento_e_auditado_com_versao_da_planilha(): void
    {
        $this->service()->enquadrar(new EnquadramentoInput(
            area: 800.0,
            cnaePrincipal: '0111-3/01',
            respostas: [11 => false],
        ));

        $activity = Activity::query()
            ->where('log_name', 'louos')
            ->where('event', 'enquadramento')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame('planilha-20-08-26', $activity->rules_version);
        $this->assertSame('identificado', $activity->properties['enquadramento_status']);
        $this->assertArrayNotHasKey('quadro7_status', $activity->properties);
    }

    private function service(): LouosEnquadramentoService
    {
        return app(LouosEnquadramentoService::class);
    }
}
