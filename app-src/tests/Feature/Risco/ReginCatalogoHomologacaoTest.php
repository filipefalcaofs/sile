<?php

namespace Tests\Feature\Risco;

use Tests\TestCase;

class ReginCatalogoHomologacaoTest extends TestCase
{
    public function test_catalogo_publicado_traz_so_os_sete_protocolos_com_zona_e_via(): void
    {
        $dados = json_decode(
            (string) file_get_contents(database_path('data/regras-20-08-26/protocolos-sedur.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame([
            'Processo 1 — inscrição 2396017',
            'Processo 2 — inscrição 2359391',
            'Processo 3 — inscrição 2396017',
            'Processo 4 — inscrição 625553',
            'Processo 5 — inscrição 4082176',
            'Processo 6 — inscrição 2507625',
            'Processo 7 — inscrição 2706946',
        ], array_column($dados['protocolos'], 'rotulo'));

        foreach ($dados['protocolos'] as $protocolo) {
            $this->assertNotSame('', trim((string) $protocolo['zona']));
            $this->assertNotSame('', trim((string) $protocolo['via']));

            foreach ($protocolo['atividades'] as $atividade) {
                $this->assertSame([], $atividade['perguntas']);
            }
        }
    }
}
