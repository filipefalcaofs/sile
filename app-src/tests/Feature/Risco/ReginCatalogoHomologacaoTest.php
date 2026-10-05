<?php

namespace Tests\Feature\Risco;

use Tests\TestCase;

class ReginCatalogoHomologacaoTest extends TestCase
{
    /**
     * O catálogo publicado é o lote de homologação do motor. Zona e via vêm
     * do GeoServer da SEDUR e as perguntas ficam em branco para o usuário
     * responder na simulação — nenhum valor inventado entra aqui.
     */
    public function test_catalogo_publicado_traz_os_protocolos_com_zona_e_via(): void
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
            'Processo 8 — inscrição 7726732',
            'Processo 9 — inscrição 9561994',
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
