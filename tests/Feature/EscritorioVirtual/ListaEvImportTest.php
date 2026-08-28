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
        $this->assertSame('ev-anexos-2026-08-28', $vigente->version);

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

    /**
     * Reproduz o cenário de produção do C1: banco onde o seeder ANTIGO já
     * rodou e publicou uma versão vigente com a lista única de 5 CNAEs (sem
     * discriminador de anexo), incluindo o 8211-3/00 — CNAE que constitui a
     * sede e não deveria estar em lista nenhuma. Aproximação da sequência
     * real (seeder antigo + migration de backfill), porque a migration já
     * foi consolidada no schema atual e não há como rodá-la isoladamente no
     * harness: semeia manualmente a linha (versão antiga, anexo 'B',
     * 8211-3/00) tal como o backfill da migration
     * 2026_08_28_100000_add_anexo_to_virtual_office_activity_cnaes deixou.
     * Rodar o seeder novo tem que publicar uma versão nova (RN-EV-05/07) e
     * fechar a antiga, de modo que permitido('8211-3/00') vire falso.
     */
    public function test_republicar_versao_corrige_cnae_de_sede_ja_semeado_na_lista_antiga(): void
    {
        $versaoAntiga = RuleVersion::factory()->create([
            'domain' => RuleDomain::AtividadesEscritorioVirtual,
            'version' => 'ev-snapshot-2026-07-16',
        ]);

        VirtualOfficeActivityCnae::query()->create([
            'rule_version_id' => $versaoAntiga->id,
            'anexo' => VirtualOfficeActivityCnae::ANEXO_B,
            'cnae_code' => '8211300',
            'cnae_description' => 'Serviços combinados de escritório e apoio administrativo',
        ]);

        $this->assertTrue(VirtualOfficeActivityCnae::permitido('8211-3/00'));

        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->assertFalse(VirtualOfficeActivityCnae::permitido('8211-3/00'));
        $this->assertSame(1, RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->count());
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
