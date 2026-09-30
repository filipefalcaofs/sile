<?php

namespace Tests\Feature\Louos;

use App\Services\Decisao\DecisionTextCatalog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class Quadro11aClasseViaTextosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_motivos_da_classificacao_viaria_existem_no_catalogo(): void
    {
        $catalogo = app(DecisionTextCatalog::class);

        foreach ([
            'louos.motivo.via_sem_codlog',
            'louos.motivo.via_classe_desligada',
            'louos.motivo.via_classe_indisponivel',
            'louos.motivo.via_classe_desconhecida',
        ] as $chave) {
            $this->assertNotSame('', trim($catalogo->get($chave)), "Texto ausente: {$chave}");
        }
    }
}
