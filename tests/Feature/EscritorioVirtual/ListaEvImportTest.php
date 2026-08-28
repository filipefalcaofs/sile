<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Carga das listas de atividade por anexo do Decreto 35.062/2021 (Anexo A =
 * SEDE, Anexo B = ABRIGADO, RN-EV-05/07): o seeder publica uma versão
 * vigente do domínio atividades_escritorio_virtual e importa os snapshots
 * CSV (anexo-a-sede.csv e anexo-b-abrigado.csv), tornando
 * VirtualOfficeActivityCnae::permitido() navegável sobre dado real. Espelha
 * RiscoSanitarioSeederTest.
 */
class ListaEvImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_publica_versao_ev_e_carrega_a_lista(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->count());

        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->sole();
        $this->assertSame('ev-snapshot-2026-07-16', $vigente->version);

        $this->assertGreaterThan(0, VirtualOfficeActivityCnae::query()->count());

        $this->assertTrue(VirtualOfficeActivityCnae::permitido('8219-9/99'));
        $this->assertFalse(VirtualOfficeActivityCnae::permitido('9999-9/99'));
    }

    /**
     * O CNAE 8211-3/00 constitui a SEDE de escritorio virtual e nao consta do
     * Anexo B: pedir esse CNAE na condicao de abrigado e indeferimento
     * automatico (Constituicao §4.1.1). A lista unica anterior o continha —
     * a separacao por anexo corrige isso.
     */
    public function test_cnae_de_sede_nao_e_permitido_para_abrigado(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->assertFalse(VirtualOfficeActivityCnae::permitido('8211-3/00'));
    }

    public function test_seeder_e_idempotente(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);
        $total = VirtualOfficeActivityCnae::query()->count();

        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->count());
        $this->assertSame($total, VirtualOfficeActivityCnae::query()->count());
    }
}
