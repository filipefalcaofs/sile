<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\VirtualOfficeIntent;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Intencao de escritorio virtual derivada das DUAS respostas (RN-EV-01,
 * revisao 4): a pergunta geral ("deseja ser abrigado?") e a pergunta
 * vinculada ao CNAE 8211-3/00 ("ira prestar servico de escritorio virtual,
 * centro de negocios ou coworking?"). As respostas cruas ficam persistidas
 * para auditoria; a intencao e conclusao do sistema.
 */
class IntencaoEscritorioVirtualTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_resposta_sim_na_pergunta_geral_indica_abrigado(): void
    {
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => true,
            'wants_virtual_office_hq' => false,
        ]);

        $this->assertSame(VirtualOfficeIntent::Abrigado, $request->virtualOfficeIntent());
    }

    public function test_nao_na_geral_e_sim_na_vinculada_indica_sede(): void
    {
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => false,
            'wants_virtual_office_hq' => true,
        ]);

        $this->assertSame(VirtualOfficeIntent::Sede, $request->virtualOfficeIntent());
    }

    public function test_nao_nas_duas_nao_e_escritorio_virtual(): void
    {
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => false,
            'wants_virtual_office_hq' => false,
        ]);

        $this->assertSame(VirtualOfficeIntent::Nenhum, $request->virtualOfficeIntent());
    }

    /**
     * Sede legada: a coluna da pergunta geral so passou a existir na migration
     * de 2026-08-28, entao toda solicitacao anterior tem `tenant = null`. O que
     * caracteriza a sede e o CNAE 8211-3/00 mais o "Sim" na pergunta vinculada
     * (SEDUR 2026-08-31) — a pergunta geral nao entra nessa caracterizacao.
     * Sem isto, sede legada seria reclassificada como "nenhum" e divergiria do
     * SedeEscritorioVirtualGatilho, que le so a pergunta vinculada.
     */
    public function test_sede_legada_sem_resposta_na_pergunta_geral_continua_sede(): void
    {
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => null,
            'wants_virtual_office_hq' => true,
        ]);

        $this->assertSame(VirtualOfficeIntent::Sede, $request->virtualOfficeIntent());
    }

    /**
     * Sem nenhuma das duas respostas afirmativas, nao e escritorio virtual.
     */
    public function test_sem_resposta_e_sem_pergunta_vinculada_nao_e_escritorio_virtual(): void
    {
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => null,
            'wants_virtual_office_hq' => false,
        ]);

        $this->assertSame(VirtualOfficeIntent::Nenhum, $request->virtualOfficeIntent());
    }

    public function test_sim_na_geral_prevalece_sobre_a_vinculada(): void
    {
        // A pergunta vinculada so e exibida quando a geral e "Nao"; se as duas
        // vierem "Sim" por dado legado, a geral manda (fluxo de abrigado).
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => true,
            'wants_virtual_office_hq' => true,
        ]);

        $this->assertSame(VirtualOfficeIntent::Abrigado, $request->virtualOfficeIntent());
    }
}
