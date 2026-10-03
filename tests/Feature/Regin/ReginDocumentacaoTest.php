<?php

namespace Tests\Feature\Regin;

use App\Services\Regin\RecebimentoComplemento;
use App\Services\Regin\RecebimentoProcesso;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
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

    /**
     * A página que a JUCEB abre tem que descrever o contrato que o endpoint
     * realmente aceita — spec desatualizada é integração errada.
     */
    public function test_especificacao_publica_o_contrato_da_declaracao(): void
    {
        $spec = Yaml::parse((string) $this->get('/api_integracao/docs/openapi.yaml')->getContent());

        $propriedades = $spec['components']['schemas']['ProcessoDeclaracao']['properties'] ?? [];

        $this->assertSame('2.0.0', $spec['info']['version']);

        $texto = (string) $this->get('/api_integracao/docs/openapi.yaml')->getContent();
        foreach ([
            'análise técnica',
            'decisão automática',
            'GeoServer',
            'art. 18',
            'Decreto',
            'maior porção',
            'enquadramento',
            'Cadastro Imobiliário',
        ] as $interno) {
            $this->assertStringNotContainsString($interno, $texto);
        }

        foreach (array_keys((new RecebimentoProcesso(
            protocolo: '1',
            razaoSocial: null,
            cpfSolicitante: null,
            nomeSolicitante: null,
            emailSolicitante: null,
            inscricaoImobiliaria: null,
            codlog: null,
            tipoLogradouro: null,
            logradouro: null,
            numero: null,
            bairro: null,
            cep: null,
            pontoReferencia: null,
            municipio: null,
            uf: null,
            area: null,
            tipoComplemento: null,
            descricaoComplemento: null,
            categoriaImovel: null,
            cnaePrincipal: null,
            cnaesSecundarios: [],
            perguntas: [],
            tipoImovel: null,
            porteEmpresa: null,
            atividadeEmAreaPublica: null,
            escritorioVirtual: null,
            atividadeAbrigadaEmEscritorioVirtual: null,
            prestaServicoDeEscritorioVirtual: null,
            cnpjEscritorioVirtual: null,
            servico: null,
            dataAbertura: null,
            acessosIndependentes: null,
            poligonoDeclarado: null,
            complemento: RecebimentoComplemento::fromEnvelope([]),
        ))->toArray()) as $campoGravado) {
            $this->assertArrayHasKey(
                Str::camel($campoGravado),
                $propriedades,
                "O campo gravado '{$campoGravado}' não está documentado na OpenAPI.",
            );
        }
    }
}
