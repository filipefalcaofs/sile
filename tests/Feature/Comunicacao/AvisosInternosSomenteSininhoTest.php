<?php

namespace Tests\Feature\Comunicacao;

use App\Models\ExportFile;
use App\Models\User;
use App\Notifications\ExportacaoPronta;
use App\Notifications\TllExercicioFaltanteNotification;
use Tests\TestCase;

/**
 * Avisos aos usuários internos (gestão) chegam só pelo sininho, sem e-mail.
 */
class AvisosInternosSomenteSininhoTest extends TestCase
{
    public function test_tabela_tll_sem_publicacao_notifica_so_no_sistema(): void
    {
        $this->assertSame(['database'], (new TllExercicioFaltanteNotification([2026]))->via(new User));
    }

    public function test_exportacao_pronta_notifica_so_no_sistema(): void
    {
        $this->assertSame(['database'], (new ExportacaoPronta(new ExportFile))->via(new User));
    }
}
