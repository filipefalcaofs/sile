<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Duas listas de atividade versionadas (RN-EV-05/07, revisão 4): Anexo A vale
 * para a SEDE (6 CNAEs) e Anexo B para o ABRIGADO (319 CNAEs). O mesmo CNAE
 * pode constar dos dois; `permitido()` mantém a semântica antiga (Anexo B)
 * para não quebrar os call sites existentes.
 */
class AnexoListaEvTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EscritorioVirtualCnaeSeeder::class);
    }

    public function test_anexo_a_tem_os_seis_cnaes_da_sede(): void
    {
        $this->assertSame(6, VirtualOfficeActivityCnae::query()
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->count());
    }

    public function test_anexo_b_tem_os_cnaes_do_abrigado(): void
    {
        $this->assertSame(319, VirtualOfficeActivityCnae::query()
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_B)
            ->count());
    }

    /**
     * O Anexo A e um subconjunto restritivo do Anexo B: toda atividade
     * permitida a uma SEDE tambem e permitida a um ABRIGADO. O inverso nao
     * vale. Este teste trava a propriedade — uma importacao futura que
     * introduza CNAE de sede fora do Anexo B falha aqui.
     */
    public function test_anexo_a_e_subconjunto_do_anexo_b(): void
    {
        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first();

        $anexoA = VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->pluck('cnae_code');

        foreach ($anexoA as $code) {
            $this->assertTrue(
                VirtualOfficeActivityCnae::permitidoNoAnexo($code, VirtualOfficeActivityCnae::ANEXO_B),
                "CNAE {$code} consta do Anexo A mas nao do Anexo B.",
            );
        }
    }

    /**
     * A discriminacao entre os anexos: ha atividade permitida ao ABRIGADO que
     * nao e permitida a SEDE. 8630-5/99 (atencao ambulatorial) consta do
     * Anexo B e nao do Anexo A.
     */
    public function test_cnae_de_abrigado_nao_vale_como_anexo_a(): void
    {
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('8630-5/99', VirtualOfficeActivityCnae::ANEXO_B));
        $this->assertFalse(VirtualOfficeActivityCnae::permitidoNoAnexo('8630-5/99', VirtualOfficeActivityCnae::ANEXO_A));
    }

    /**
     * 8219-9/99 consta dos dois anexos — a sobreposicao e esperada.
     */
    public function test_cnae_presente_nos_dois_anexos_vale_nos_dois(): void
    {
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('8219-9/99', VirtualOfficeActivityCnae::ANEXO_A));
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('8219-9/99', VirtualOfficeActivityCnae::ANEXO_B));
    }

    /**
     * `permitido()` preserva a semantica historica — a pergunta do ABRIGADO
     * (Anexo B). 8211-3/00 constitui a SEDE e nao consta de nenhum dos dois
     * anexos, entao e falso aqui.
     */
    public function test_permitido_mantem_semantica_de_abrigado(): void
    {
        $this->assertTrue(VirtualOfficeActivityCnae::permitido('8630-5/99'));
        $this->assertFalse(VirtualOfficeActivityCnae::permitido('8211-3/00'));
        $this->assertFalse(VirtualOfficeActivityCnae::permitido('9999-9/99'));
    }
}
