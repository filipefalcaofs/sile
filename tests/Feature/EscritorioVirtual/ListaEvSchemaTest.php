<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use App\Services\Rules\RuleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaEvSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_permitido_consulta_a_versao_vigente_normalizando_digitos(): void
    {
        $svc = app(RuleVersionService::class);
        $draft = $svc->openDraft(RuleDomain::AtividadesEscritorioVirtual, 'ev-2026-07', 'teste');
        $svc->publish($draft);

        VirtualOfficeActivityCnae::create([
            'rule_version_id' => RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first()->id,
            'cnae_code' => '6204000',
            'cnae_description' => 'Consultoria em TI',
        ]);

        $this->assertTrue(VirtualOfficeActivityCnae::permitido('6204-0/00'));
        $this->assertFalse(VirtualOfficeActivityCnae::permitido('4712-1/00'));
    }
}
