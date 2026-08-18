<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Carga da Lista EV (CNAEs permitidos para ABRIGADO de escritório virtual,
 * RN-EV-05/07): o seeder publica uma versão vigente do domínio
 * atividades_escritorio_virtual e importa o snapshot CSV
 * (database/data/escritorio-virtual/atividades-permitidas.csv), tornando
 * VirtualOfficeActivityCnae::permitido() navegável sobre dado real. Espelha
 * RiscoSanitarioSeederTest.
 */
class ListaEvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_publica_versao_ev_e_carrega_a_lista(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->assertSame(1, RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->count());

        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->sole();
        $this->assertSame('ev-snapshot-2026-07-16', $vigente->version);

        $this->assertGreaterThan(0, VirtualOfficeActivityCnae::query()->count());

        $this->assertTrue(VirtualOfficeActivityCnae::permitido('8211-3/00'));
        $this->assertFalse(VirtualOfficeActivityCnae::permitido('9999-9/99'));
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
