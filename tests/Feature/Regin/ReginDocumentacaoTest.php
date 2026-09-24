<?php

namespace Tests\Feature\Regin;

use Tests\TestCase;

class ReginDocumentacaoTest extends TestCase
{
    public function test_pagina_abre_o_swagger_ui(): void
    {
        $response = $this->get('/api_integracao/docs');

        $response->assertOk();
        $response->assertSee('swagger-ui', false);
        $response->assertSee('/api_integracao/docs/openapi.yaml', false);
        $response->assertSee('/swagger-ui/swagger-ui-bundle.js', false);
        $response->assertSee('/swagger-ui/swagger-ui.css', false);
    }

    public function test_especificacao_lista_homologacao_e_producao(): void
    {
        $response = $this->get('/api_integracao/docs/openapi.yaml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/yaml; charset=UTF-8');
        $response->assertSee('https://viabiliza-hml.salvador.ba.gov.br', false);
        $response->assertSee('https://viabiliza.salvador.ba.gov.br', false);
        $response->assertSee('/api_integracao/recebe', false);
    }
}
