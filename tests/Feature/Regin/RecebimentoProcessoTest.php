<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Http\Resources\ProcessoResource;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecebimentoProcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_declaracao_sem_solicitante_nao_vincula_o_processo_a_usuario_existente(): void
    {
        $primeiro = User::factory()->create();
        $declaracao = $this->declaracao($this->cnpjValido());
        unset($declaracao['cpfSolicitante'], $declaracao['nomeSolicitante'], $declaracao['emailSolicitante']);

        $this->postJson('/api_integracao/recebe', $declaracao)->assertOk();

        $recebimento = ReginRecebimento::query()->where('protocolo', 'BAP-2026-0001')->firstOrFail();
        $processo = ViabilityRequest::query()->findOrFail($recebimento->viability_request_id);

        $this->assertNotSame($primeiro->id, $processo->requester_user_id);
        $this->assertNotSame($primeiro->id, $processo->created_by_user_id);
        $this->assertSame(User::EMAIL_INTEGRACAO_REGIN, $processo->createdBy->email);
        $this->assertFalse($processo->requester->temEmailEntregavel());
    }

    public function test_post_da_declaracao_abre_processo_com_os_dados_recebidos(): void
    {
        $cnpj = $this->cnpjValido();

        $response = $this->postJson('/api_integracao/recebe', $this->declaracao($cnpj));

        $response->assertOk();
        $this->assertSame('3', $response->getContent());

        $recebimento = ReginRecebimento::query()->where('protocolo', 'BAP-2026-0001')->first();
        $this->assertNotNull($recebimento?->viability_request_id);

        $processo = ViabilityRequest::query()->findOrFail($recebimento->viability_request_id);
        $processo->load(['company', 'requester', 'cnaes', 'serviceType']);

        $this->assertSame(ViabilityRequestOrigin::Regin, $processo->origin);
        $this->assertSame('BAP-2026-0001', $processo->external_reference);
        $this->assertSame('Padaria do Porto Ltda', $processo->company?->legal_name);
        $this->assertNull($processo->company?->cnpj);
        $this->assertSame('Microempresa', $processo->company?->size);
        $this->assertSame('Salvador', $processo->company?->city);
        $this->assertSame('BA', $processo->company?->state);
        $this->assertSame(User::EMAIL_INTEGRACAO_REGIN, $processo->createdBy?->email);
        $this->assertSame('52998224725', $processo->requester?->cpf);
        $this->assertSame('Maria Souza', $processo->requester?->name);
        $this->assertSame('maria.souza@exemplo.com', $processo->requester?->email);
        $this->assertSame('12345678', $processo->property_registration);
        $this->assertSame('212', $processo->cod_log);
        $this->assertSame('das Flores', $processo->address_street);
        $this->assertSame('100', $processo->address_number);
        $this->assertSame('Sala 2', $processo->address_complement);
        $this->assertSame('Comércio', $processo->address_neighborhood);
        $this->assertSame('40020000', $processo->address_zip);
        $this->assertSame('Em frente à praça', $processo->address_reference);
        $this->assertSame(120.5, (float) $processo->used_area_m2);
        $this->assertSame('Loja', $processo->tipo_imovel);
        $this->assertTrue($processo->is_public_area);
        $this->assertTrue($processo->is_virtual_office);
        $this->assertTrue($processo->wants_virtual_office_tenant);
        $this->assertSame('4712100', $processo->primaryCnae()->first()?->code);
        $this->assertEqualsCanonicalizing(
            ['5611201', '5620104'],
            $processo->cnaes()->wherePivot('is_primary', false)->pluck('code')->all(),
        );
        $this->assertSame('Consulta de viabilidade', $processo->serviceType?->name);
        $this->assertSame('2026-10-03', $processo->protocoled_at?->toDateString());
        $this->assertNotNull($processo->protocol_number);

        $declaracao = $processo->origem_declarada;
        $this->assertIsArray($declaracao);
        $this->assertSame('Rua', $declaracao['tipo_logradouro']);
        $this->assertSame('Comercial', $declaracao['categoria_imovel']);
        $this->assertSame($cnpj, $declaracao['cnpj_escritorio_virtual']);
        $this->assertSame('12', $declaracao['perguntas'][0]['pergunta']);
        $this->assertSame('Não', $declaracao['perguntas'][0]['resposta']);

        $respostas = $processo->respostasDaPlanilha('4712100');
        $this->assertArrayHasKey(12, $respostas);
        $this->assertFalse($respostas[12]);

        $this->assertSame(1, ViabilityServiceType::query()->where('name', 'Consulta de viabilidade')->count());
        $this->assertSame(1, User::query()->where('cpf', '52998224725')->count());

        $tela = (new ProcessoResource($processo->fresh()))->resolve(request());
        $this->assertSame('Maria Souza', $tela['recepcao']['nome_solicitante']);
        $this->assertSame('Sim', $tela['recepcao']['atividade_em_area_publica']);
        $this->assertSame($cnpj, $tela['recepcao']['cnpj_escritorio_virtual']);
        $this->assertStringContainsString('Rua das Flores', $tela['endereco_completo']);
        $this->assertStringContainsString('Salvador/BA', $tela['endereco_completo']);
    }

    public function test_resposta_em_opcoes_do_regin_chega_ao_motor(): void
    {
        $declaracao = $this->declaracao($this->cnpjValido());
        $declaracao['perguntas'] = [
            ['cnae' => '4712100', 'pergunta' => '14', 'resposta' => '1,2'],
            ['cnae' => '4712100', 'pergunta' => '22', 'resposta' => '2'],
            ['cnae' => '4712100', 'pergunta' => '27', 'resposta' => 'B'],
            ['cnae' => '4712100', 'pergunta' => '11', 'resposta' => 'Não'],
        ];

        $this->postJson('/api_integracao/recebe', $declaracao)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();
        $respostas = $processo->respostasDaPlanilha('4712100');

        $this->assertSame([1, 2], $respostas[14]);
        $this->assertSame(2, $respostas[22]);
        $this->assertSame(2, $respostas[27]);
        $this->assertFalse($respostas[11]);
    }

    public function test_natureza_juridica_da_declaracao_vai_para_a_empresa(): void
    {
        $declaracao = $this->declaracao($this->cnpjValido());
        $declaracao['naturezaJuridica'] = '1015 - Órgão Público do Poder Executivo Federal';

        $this->postJson('/api_integracao/recebe', $declaracao)->assertOk();

        $empresa = ViabilityRequest::query()->firstOrFail()->company;

        $this->assertSame('1015', $empresa->legal_nature_code);
        $this->assertSame('Órgão Público do Poder Executivo Federal', $empresa->legal_nature);
    }

    public function test_natureza_juridica_so_com_texto_fica_na_descricao(): void
    {
        $declaracao = $this->declaracao($this->cnpjValido());
        $declaracao['naturezaJuridica'] = 'Sociedade Empresária Limitada';

        $this->postJson('/api_integracao/recebe', $declaracao)->assertOk();

        $empresa = ViabilityRequest::query()->firstOrFail()->company;

        $this->assertNull($empresa->legal_nature_code);
        $this->assertSame('Sociedade Empresária Limitada', $empresa->legal_nature);
    }

    public function test_area_utilizada_da_atividade_manda_sobre_as_areas_do_cadastro(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        unset($payload['area']);
        $payload['areaUtilizada'] = '45,75';
        $payload['areaEstabelecimento'] = 216;
        $payload['areaTerreno'] = 300;

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();

        $this->assertSame(45.75, (float) $processo->used_area_m2);
        $this->assertSame(45.75, $processo->origem_declarada['area_utilizada']);
        // Inteiro no JSON volta como int: o que importa é o valor, não o tipo.
        $this->assertSame(216.0, (float) $processo->origem_declarada['area_construida']);
        $this->assertSame(300.0, (float) $processo->origem_declarada['area_terreno']);
    }

    public function test_poligono_declarado_entra_como_geometria_do_imovel(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        $payload['poligonoDeclarado'] = [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.516331, -12.977852],
                [-38.516291, -12.977746],
                [-38.516453, -12.977688],
                [-38.516492, -12.977795],
                [-38.516331, -12.977852],
            ]],
        ];

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();

        $this->assertSame('Polygon', $processo->property_polygon_geojson['type']);
        $this->assertCount(5, $processo->property_polygon_geojson['coordinates'][0]);

        // Geometria DECLARADA: fica no processo, mas não é a oficial — o
        // endereçamento e o cadastro municipal passam na frente ao zonear.
        $this->assertNull($processo->address_polygon_geojson);
    }

    public function test_geometria_sem_area_nao_vira_poligono_do_imovel(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        $payload['poligonoDeclarado'] = [
            'type' => 'LineString',
            'coordinates' => [[-38.5163, -12.9778], [-38.5162, -12.9777]],
        ];

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $this->assertNull(ViabilityRequest::query()->firstOrFail()->property_polygon_geojson);
    }

    public function test_acessos_independentes_entram_no_imovel(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        $payload['acessosIndependentes'] = 'Sim';

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $this->assertTrue(ViabilityRequest::query()->firstOrFail()->has_independent_access);
    }

    public function test_declaracoes_complementares_do_regin_ficam_registradas(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        $payload = array_merge($payload, [
            'evento' => 'Inscrição de primeiro estabelecimento (Matriz)',
            'objetoSocial' => 'Comércio varejista de produtos de padaria',
            'naturezaJuridica' => 'Sociedade Empresária Limitada',
            'imovelEmCondominio' => 'Sim',
            'areaEmbarqueDesembarque' => 'Não',
            'possuiConcessaoDeUso' => 'Sim',
            'termoDispensaViabilidadeLocacional' => 'Não',
            'bapAnteriorIndeferido' => 'BAP-2025-0777',
            'meiDesenquadrado' => 'Não',
            'coordenadaGeografica' => ['latitude' => -12.977852, 'longitude' => -38.516331],
            'contador' => ['nome' => 'Contabilidade Souza', 'documento' => '52998224725', 'crc' => 'BA-012345/O'],
            'atividadesExercidasNoLocal' => [
                ['cnae' => '4712-1/00', 'exercida' => 'Sim'],
                ['cnae' => '5611201', 'exercida' => 'Não'],
            ],
        ]);

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $declarada = ViabilityRequest::query()->firstOrFail()->origem_declarada;

        $this->assertSame('Inscrição de primeiro estabelecimento (Matriz)', $declarada['evento']);
        $this->assertSame('Comércio varejista de produtos de padaria', $declarada['objeto_social']);
        $this->assertSame('Sociedade Empresária Limitada', $declarada['natureza_juridica']);
        $this->assertSame('Sim', $declarada['imovel_em_condominio']);
        $this->assertSame('Não', $declarada['area_embarque_desembarque']);
        $this->assertSame('Sim', $declarada['possui_concessao_de_uso']);
        $this->assertSame('Não', $declarada['termo_dispensa_viabilidade_locacional']);
        $this->assertSame('BAP-2025-0777', $declarada['bap_anterior_indeferido']);
        $this->assertSame('Não', $declarada['mei_desenquadrado']);
        $this->assertSame(-12.977852, $declarada['coordenada_geografica']['latitude']);
        $this->assertSame('BA-012345/O', $declarada['contador']['crc']);
        $this->assertSame(
            [['cnae' => '4712100', 'exercida' => 'Sim'], ['cnae' => '5611201', 'exercida' => 'Não']],
            $declarada['atividades_exercidas_no_local'],
        );
    }

    public function test_trata_se_de_escritorio_virtual_e_sinonimo_da_pergunta_anterior(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        unset($payload['atividadeAbrigadaEmEscritorioVirtual'], $payload['cnpjEscritorioVirtual']);
        $payload['trataSeDeEscritorioVirtual'] = 'Sim';
        $payload['cnpjEscritorioVirtual'] = $this->cnpjValido();

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();
        $this->assertTrue($processo->wants_virtual_office_tenant);
        $this->assertSame($payload['cnpjEscritorioVirtual'], $processo->origem_declarada['cnpj_escritorio_virtual']);
    }

    public function test_resposta_de_sede_de_escritorio_virtual_vira_gatilho_no_processo(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        $payload['prestaServicoDeEscritorioVirtual'] = 'Sim';

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();

        $this->assertTrue($processo->wants_virtual_office_hq);
        $this->assertSame('Sim', $processo->origem_declarada['presta_servico_de_escritorio_virtual']);
    }

    public function test_declaracao_sem_a_pergunta_de_sede_nao_marca_o_gatilho(): void
    {
        $this->postJson('/api_integracao/recebe', $this->declaracao($this->cnpjValido()))->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();

        $this->assertFalse($processo->wants_virtual_office_hq);
        $this->assertNull($processo->origem_declarada['presta_servico_de_escritorio_virtual']);
    }

    public function test_cnpj_de_escritorio_virtual_so_entra_quando_a_atividade_esta_abrigada(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        $payload['atividadeAbrigadaEmEscritorioVirtual'] = 'Não';

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();

        $this->assertFalse($processo->wants_virtual_office_tenant);
        $this->assertNull($processo->origem_declarada['cnpj_escritorio_virtual'] ?? null);
    }

    public function test_declaracao_guarda_os_campos_que_vieram_do_envio(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        $payload['cnpjDestino'] = '13.927.801/0001-49';
        $payload['cnpjEmpresa'] = '13927801000149';
        $payload['cnpjOrigem'] = '13927801000149';
        $payload['codFuncao'] = 103;
        $payload['dataGeracao'] = '2026-09-16T16:00:00.000Z';
        $payload['nire'] = '00000000000';
        $payload['RAE_CALIF_ACTV'] = '1';
        $payload['RGP_TGE_COD_TIP_TAB'] = '5';
        $payload['RGP_VALOR'] = '12345678';

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();
        $declaracao = $processo->origem_declarada;

        $this->assertSame('13.927.801/0001-49', $declaracao['cnpj_destino']);
        $this->assertSame('13927801000149', $declaracao['cnpj_empresa']);
        $this->assertSame('13927801000149', $declaracao['cnpj_origem']);
        $this->assertSame(103, $declaracao['cod_funcao']);
        $this->assertSame('2026-09-16T16:00:00.000Z', $declaracao['data_geracao']);
        $this->assertSame('00000000000', $declaracao['nire']);
        $this->assertSame('1', $declaracao['RAE_CALIF_ACTV']);
        $this->assertSame('5', $declaracao['RGP_TGE_COD_TIP_TAB']);
        $this->assertSame('12345678', $declaracao['RGP_VALOR']);
        $this->assertSame('Padaria do Porto Ltda', $declaracao['razao_social']);
        $this->assertSame('2026-10-03', $declaracao['data_abertura']);

        $tela = (new ProcessoResource($processo->fresh()))->resolve(request());
        $this->assertSame(103, $tela['recepcao']['cod_funcao']);
        $this->assertSame('1', $tela['recepcao']['RAE_CALIF_ACTV']);
        $this->assertSame('5', $tela['recepcao']['RGP_TGE_COD_TIP_TAB']);
        $this->assertSame('12345678', $tela['recepcao']['RGP_VALOR']);
    }

    public function test_declaracao_com_os_nomes_do_envio_dto_abre_o_processo(): void
    {
        $payload = [
            'protocolo' => 'BAP-2026-0001',
            'RGE_NOMB' => 'Padaria do Porto Ltda',
            'cpfSolicitante' => '529.982.247-25',
            'nomeSolicitante' => 'Maria Souza',
            'emailSolicitante' => 'maria.souza@exemplo.com',
            'RES_TTL_TIP_LOGRADORO' => 'Rua',
            'RES_DIRECCION' => 'das Flores',
            'RES_NUME' => '100',
            'RES_IDENT_COMP' => 'Sala 2',
            'RES_URBANIZACION' => 'Comércio',
            'RES_ZONA_POSTAL' => '40020-000',
            'RES_REFER' => 'Em frente à praça',
            'RES_TMU_DESC_MUN' => 'Salvador',
            'RES_TES_COD_ESTADO' => 'BA',
            'RES_AREA' => '120,5',
            'RES_DS_EVENTO' => 'Inscrição de primeiro estabelecimento (Matriz)',
            'RES_CNPJ_SEDE' => '13927801000149',
            'RGE_TGE_VTAMANHO' => '1',
            'RUC_ACTV_ECON' => [
                ['RAE_TAE_COD_ACTVD' => '4712-1/00', 'RAE_CALIF_ACTV' => '1'],
                ['RAE_TAE_COD_ACTVD' => '5611201', 'RAE_CALIF_ACTV' => '2'],
            ],
            'RGP_TGE_COD_TIP_TAB' => '5',
            'RGP_VALOR' => '12345678',
        ];

        $this->postJson('/api_integracao/recebe', $payload)->assertOk();

        $processo = ViabilityRequest::query()->firstOrFail();

        $this->assertSame('Padaria do Porto Ltda', $processo->company?->legal_name);
        $this->assertSame('das Flores', $processo->address_street);
        $this->assertSame('Comércio', $processo->address_neighborhood);
        $this->assertSame('40020000', $processo->address_zip);
        $this->assertSame(120.5, (float) $processo->used_area_m2);
        $this->assertSame('4712100', $processo->primaryCnae()->first()?->code);
        $this->assertSame(['5611201'], $processo->cnaes()->wherePivot('is_primary', false)->pluck('code')->all());
        $this->assertSame('12345678', $processo->property_registration);
        $this->assertSame('1', $processo->company?->size);
        $this->assertSame('13927801000149', $processo->origem_declarada['cnpj_empresa']);
        $this->assertSame('Inscrição de primeiro estabelecimento (Matriz)', $processo->origem_declarada['evento']);
    }

    public function test_declaracao_sem_cnae_principal_e_recusada_sem_gravar(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        unset($payload['cnaePrincipal']);

        $response = $this->postJson('/api_integracao/recebe', $payload);

        $response->assertStatus(400);
        $this->assertSame('0', $response->getContent());
        $this->assertSame(0, ReginRecebimento::query()->count());
        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_declaracao_sem_endereco_e_recusada_sem_gravar(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        unset($payload['logradouro'], $payload['bairro'], $payload['cep']);

        $response = $this->postJson('/api_integracao/recebe', $payload);

        $response->assertStatus(400);
        $this->assertSame('0', $response->getContent());
        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    public function test_declaracao_sem_area_e_recusada_sem_gravar(): void
    {
        $payload = $this->declaracao($this->cnpjValido());
        unset($payload['area']);

        $response = $this->postJson('/api_integracao/recebe', $payload);

        $response->assertStatus(400);
        $this->assertSame('0', $response->getContent());
        $this->assertSame(0, ReginRecebimento::query()->count());
    }

    public function test_mesmo_protocolo_nao_abre_segundo_processo(): void
    {
        $payload = $this->declaracao($this->cnpjValido());

        $this->postJson('/api_integracao/recebe', $payload);
        $segunda = $this->postJson('/api_integracao/recebe', $payload);

        $segunda->assertOk();
        $this->assertSame('5', $segunda->getContent());
        $this->assertSame(1, ViabilityRequest::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function declaracao(string $cnpjEscritorio): array
    {
        return [
            'protocolo' => 'BAP-2026-0001',
            'razaoSocial' => 'Padaria do Porto Ltda',
            'cpfSolicitante' => '529.982.247-25',
            'nomeSolicitante' => 'Maria Souza',
            'emailSolicitante' => 'maria.souza@exemplo.com',
            'inscricaoImobiliaria' => '12345678',
            'codlog' => '212',
            'tipoLogradouro' => 'Rua',
            'logradouro' => 'das Flores',
            'numero' => '100',
            'bairro' => 'Comércio',
            'cep' => '40020-000',
            'pontoReferencia' => 'Em frente à praça',
            'municipio' => 'Salvador',
            'uf' => 'BA',
            'area' => '120,5',
            'tipoComplemento' => 'Sala',
            'descricaoComplemento' => '2',
            'categoriaImovel' => 'Comercial',
            'cnaePrincipal' => '4712-1/00',
            'cnaesSecundarios' => ['5611-2/01', '5620104'],
            'perguntas' => [
                [
                    'cnae' => '4712100',
                    'pergunta' => '12',
                    'resposta' => 'Não',
                ],
                [
                    'cnae' => '5611201',
                    'pergunta' => 'Há música ao vivo?',
                    'resposta' => 'Sim',
                ],
            ],
            'tipoImovel' => 'Loja',
            'porteEmpresa' => 'Microempresa',
            'atividadeEmAreaPublica' => 'Sim',
            'escritorioVirtual' => 'Sim',
            'atividadeAbrigadaEmEscritorioVirtual' => 'Sim',
            'cnpjEscritorioVirtual' => $cnpjEscritorio,
            'servico' => 'Consulta de viabilidade',
            'dataAbertura' => '2026-10-03',
        ];
    }

    private function cnpjValido(): string
    {
        $base = '112223330001';
        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $cnpj = $base;

        foreach ([12, 13] as $position) {
            $slice = array_slice($weights, 13 - $position);
            $sum = 0;

            foreach ($slice as $index => $weight) {
                $sum += (ord($cnpj[$index]) - 48) * $weight;
            }

            $rest = $sum % 11;
            $cnpj .= (string) ($rest < 2 ? 0 : 11 - $rest);
        }

        return $cnpj;
    }
}
