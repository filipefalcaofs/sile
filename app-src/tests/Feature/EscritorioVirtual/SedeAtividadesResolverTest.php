<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Services\EscritorioVirtual\SedeAtividadesResolver;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Atividades que uma SEDE de escritorio virtual pode exercer (RN-EV-05c):
 * {8211-3/00} uniao Anexo A. O 8211-3/00 caracteriza a sede e por isso nao
 * figura no Anexo A — precisa ser excluido da conferencia, senao toda sede
 * seria indeferida pelo proprio CNAE que a define (SEDUR 2026-08-31).
 */
class SedeAtividadesResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SedeAtividadesResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->resolver = app(SedeAtividadesResolver::class);
    }

    public function test_o_cnae_que_caracteriza_a_sede_e_permitido(): void
    {
        $this->assertTrue($this->resolver->permitida('8211-3/00'));
        $this->assertSame([], $this->resolver->naoPermitidos(['8211-3/00']));
    }

    public function test_atividade_do_anexo_a_e_permitida(): void
    {
        $this->assertTrue($this->resolver->permitida('6920-6/01'));
    }

    public function test_atividade_so_do_anexo_b_nao_e_permitida_a_sede(): void
    {
        // 8630-5/99 consta do Anexo B (abrigado) e nao do Anexo A (sede).
        $this->assertFalse($this->resolver->permitida('8630-5/99'));
    }

    public function test_devolve_todos_os_cnaes_reprovados_na_ordem_de_entrada(): void
    {
        $this->assertSame(
            ['8630-5/99', '9999-9/99'],
            $this->resolver->naoPermitidos(['8211-3/00', '8630-5/99', '6920-6/01', '9999-9/99']),
        );
    }

    public function test_conjunto_valido_completo_nao_reprova_nada(): void
    {
        $this->assertSame([], $this->resolver->naoPermitidos(['8211-3/00', '6920-6/01', '8219-9/99']));
    }
}
