<?php

namespace App\Support;

/**
 * Abas do formulário único de configuração das APIs em uso.
 * Cada campo aponta para uma chave já existente no catálogo de parâmetros.
 *
 * @phpstan-type Campo array{id: string, key: string, rotulo: string, tipo: string, opcoes: list<array{value: string, label: string}>}
 * @phpstan-type Aba array{id: string, nome: string, descricao: string, teste: array{url: string, erro: string}|null, campos: list<Campo>, espelhar_url_ativa: array{destino: string, interruptor: string, producao: string, homologacao: string}|null}
 */
class ApiIntegracaoCatalog
{
    /**
     * @return list<Aba>
     */
    public static function abas(): array
    {
        return [
            self::regin(),
            self::inscricao(),
            self::sedurContato(),
            self::sedurAuth(),
            self::govbr(),
            self::portalServicos(),
            self::geocoding(),
            self::geoserver(),
            self::arcgisSemap(),
            self::edificacoes(),
        ];
    }

    /**
     * @return Aba|null
     */
    public static function find(string $id): ?array
    {
        foreach (self::abas() as $aba) {
            if ($aba['id'] === $id) {
                return $aba;
            }
        }

        return null;
    }

    /**
     * @return Aba
     */
    private static function regin(): array
    {
        return [
            'id' => 'regin',
            'nome' => 'REGIN',
            'descricao' => 'API de integração com a JUCEB. O interruptor escolhe a URL ativa.',
            'teste' => ['url' => '/gestao/config-regin/testar', 'erro' => 'regin_connection'],
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('em_producao', 'integrations.regin.em_producao', 'Usar produção'),
                self::url('url_homologacao', 'integrations.regin.url_homologacao', 'URL de homologação'),
                self::url('url_producao', 'integrations.regin.url_producao', 'URL de produção'),
                self::texto('usuario', 'integrations.regin.usuario', 'Usuário'),
                self::senha('senha', 'integrations.regin.senha', 'Senha'),
                self::texto('cnpj_prefeitura', 'integrations.regin.cnpj_prefeitura', 'CNPJ da Prefeitura'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function inscricao(): array
    {
        return [
            'id' => 'inscricao',
            'nome' => 'Inscrição imobiliária',
            'descricao' => 'Cadastro Imobiliário da SEFAZ, via BFF da SEDUR.',
            'teste' => ['url' => '/gestao/config-inscricao-imobiliaria/testar', 'erro' => 'inscricao_connection'],
            'espelhar_url_ativa' => [
                'destino' => 'integrations.inscricao_imobiliaria.base_url',
                'interruptor' => 'em_producao',
                'producao' => 'url_producao',
                'homologacao' => 'url_homologacao',
            ],
            'campos' => [
                self::interruptor('habilitada', 'features.cadastro_imobiliario', 'Consulta habilitada'),
                self::interruptor('em_producao', 'integrations.inscricao_imobiliaria.em_producao', 'Usar produção'),
                self::url('url_homologacao', 'integrations.inscricao_imobiliaria.url_homologacao', 'URL de homologação'),
                self::url('url_producao', 'integrations.inscricao_imobiliaria.url_producao', 'URL de produção'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function sedurContato(): array
    {
        return [
            'id' => 'sedur_contato',
            'nome' => 'CPF e CNPJ',
            'descricao' => 'Consulta cadastral da SEDUR (ws-contato) para CPF e CNPJ. O interruptor escolhe a URL ativa.',
            'teste' => null,
            'espelhar_url_ativa' => [
                'destino' => 'integrations.sedur_contato.base_url',
                'interruptor' => 'em_producao',
                'producao' => 'url_producao',
                'homologacao' => 'url_homologacao',
            ],
            'campos' => [
                self::interruptor('habilitada', 'features.cnpj_lookup', 'Consulta de CNPJ no cadastro de empresa'),
                self::interruptor('em_producao', 'integrations.sedur_contato.em_producao', 'Usar produção'),
                self::url('url_homologacao', 'integrations.sedur_contato.url_homologacao', 'URL de homologação'),
                self::url('url_producao', 'integrations.sedur_contato.url_producao', 'URL de produção'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function sedurAuth(): array
    {
        return [
            'id' => 'sedur_auth',
            'nome' => 'Autenticação SEDUR',
            'descricao' => 'API institucional de login AD. O interruptor escolhe a URL ativa. A credencial é a do próprio servidor.',
            'teste' => null,
            'espelhar_url_ativa' => [
                'destino' => 'integrations.sedur_auth.base_url',
                'interruptor' => 'em_producao',
                'producao' => 'url_producao',
                'homologacao' => 'url_homologacao',
            ],
            'campos' => [
                self::interruptor('habilitada', 'features.ad_login', 'Login AD habilitado'),
                self::interruptor('em_producao', 'integrations.sedur_auth.em_producao', 'Usar produção'),
                self::url('url_homologacao', 'integrations.sedur_auth.url_homologacao', 'URL de homologação'),
                self::url('url_producao', 'integrations.sedur_auth.url_producao', 'URL de produção'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function govbr(): array
    {
        return [
            'id' => 'govbr',
            'nome' => 'GOV.BR',
            'descricao' => 'Login Único. O interruptor escolhe a URL ativa.',
            'teste' => null,
            'espelhar_url_ativa' => [
                'destino' => 'integrations.govbr.base_url',
                'interruptor' => 'em_producao',
                'producao' => 'url_producao',
                'homologacao' => 'url_homologacao',
            ],
            'campos' => [
                self::interruptor('habilitada', 'features.govbr_login', 'Login GOV.BR habilitado'),
                self::interruptor('em_producao', 'integrations.govbr.em_producao', 'Usar produção'),
                self::url('url_homologacao', 'integrations.govbr.url_homologacao', 'URL de homologação'),
                self::url('url_producao', 'integrations.govbr.url_producao', 'URL de produção'),
                self::senha('client_id', 'integrations.govbr.client_id', 'Client ID'),
                self::senha('client_secret', 'integrations.govbr.client_secret', 'Client Secret'),
                self::escolha('nivel', 'security.govbr.minimum_level', 'Nível mínimo', [
                    ['value' => 'bronze', 'label' => 'Bronze'],
                    ['value' => 'prata', 'label' => 'Prata'],
                    ['value' => 'ouro', 'label' => 'Ouro'],
                ]),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function geocoding(): array
    {
        return [
            'id' => 'geocoding',
            'nome' => 'Geocodificação',
            'descricao' => 'Nominatim da Prefeitura. O interruptor escolhe a URL ativa. Sem host próprio de um ambiente, o valor inicial repete a URL já conhecida.',
            'teste' => null,
            'espelhar_url_ativa' => [
                'destino' => 'integrations.geocoding.base_url',
                'interruptor' => 'em_producao',
                'producao' => 'url_producao',
                'homologacao' => 'url_homologacao',
            ],
            'campos' => [
                self::interruptor('habilitada', 'features.geocoding', 'Geocodificação habilitada'),
                self::interruptor('em_producao', 'integrations.geocoding.em_producao', 'Usar produção'),
                self::url('url_homologacao', 'integrations.geocoding.url_homologacao', 'URL de homologação'),
                self::url('url_producao', 'integrations.geocoding.url_producao', 'URL de produção'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function geoserver(): array
    {
        return [
            'id' => 'geoserver',
            'nome' => 'GeoServer',
            'descricao' => 'WFS/WMS de zoneamento. O interruptor escolhe a URL ativa. Sem host próprio de um ambiente, o valor inicial repete a URL já conhecida.',
            'teste' => null,
            'espelhar_url_ativa' => [
                'destino' => 'integrations.geoserver.base_url',
                'interruptor' => 'em_producao',
                'producao' => 'url_producao',
                'homologacao' => 'url_homologacao',
            ],
            'campos' => [
                self::interruptor('zona', 'features.geoserver_zona', 'Consulta de zona habilitada'),
                self::interruptor('territorio', 'features.geoserver_territorio', 'Regras territoriais habilitadas'),
                self::interruptor('via_classe', 'features.geoserver_via_classe', 'Classificação viária habilitada'),
                self::interruptor('enderecamento', 'features.geoserver_enderecamento', 'Polígono do endereçamento habilitado'),
                self::interruptor('em_producao', 'integrations.geoserver.em_producao', 'Usar produção'),
                self::url('url_homologacao', 'integrations.geoserver.url_homologacao', 'URL de homologação'),
                self::url('url_producao', 'integrations.geoserver.url_producao', 'URL de produção'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function portalServicos(): array
    {
        return [
            'id' => 'portal_servicos',
            'nome' => 'Portal de Serviços SEDUR',
            'descricao' => 'Entrada do cidadão pelo Portal de Serviços da SEDUR (HU-142). Ligada, o portal do Viabiliza deixa de ter login, cadastro e senha próprios; a sessão do cidadão é conferida no BFF do portal.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('habilitada', 'features.login_portal_servicos', 'Entrada pelo Portal de Serviços habilitada'),
                self::url('bff_base_url', 'integrations.portal_servicos.bff_base_url', 'URL do BFF do Portal de Serviços'),
                self::url('login_url', 'integrations.portal_servicos.login_url', 'URL de entrada do Portal de Serviços'),
                self::url('carta_url', 'integrations.portal_servicos.carta_url', 'URL da Carta de Serviços (volta ao sair)'),
                self::texto('intervalo_checagem_sessao', 'integrations.portal_servicos.intervalo_checagem_sessao', 'Intervalo de conferência da sessão (segundos)'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function arcgisSemap(): array
    {
        return [
            'id' => 'arcgis_semap',
            'nome' => 'Cadastro municipal (SEMAP)',
            'descricao' => 'Lote por inscrição imobiliária no ArcGIS da Prefeitura, quando o endereçamento não tem o polígono. A camada exige token do Portal ArcGIS.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('habilitada', 'features.arcgis_semap', 'Consulta do lote por inscrição habilitada'),
                self::url('query_url', 'integrations.arcgis_semap.query_url', 'URL de consulta da camada'),
                self::senha('token', 'integrations.arcgis_semap.token', 'Token'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function edificacoes(): array
    {
        return [
            'id' => 'edificacoes',
            'nome' => 'Edificações complementares',
            'descricao' => 'Polígono do lote pela edificação que contém o ponto do imóvel quando a SEDUR e o SEMAP não acham o lote: Overture, Microsoft e, por último, IA sobre a ortofoto da Prefeitura.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('overture', 'features.edificacoes_overture', 'Edificações da Overture'),
                self::interruptor('microsoft', 'features.edificacoes_microsoft', 'Edificações da Microsoft'),
                self::interruptor('ia', 'features.edificacoes_ia', 'Contorno por IA na ortofoto'),
                self::interruptor('importacao_agendada', 'features.edificacoes_importacao_agendada', 'Atualização agendada das bases'),
                self::url('ia_url', 'integrations.edificacoes.ia_url', 'URL do serviço de inferência'),
                self::url('ortofoto_2024_url', 'integrations.edificacoes.ortofoto_2024_url', 'Ortofoto 2024 (ImageServer)'),
                self::url('ortofoto_2016_url', 'integrations.edificacoes.ortofoto_2016_url', 'Ortofoto 2016/2017 (WMS)'),
                self::senha('ortofoto_token', 'integrations.edificacoes.ortofoto_token', 'Token da ortofoto'),
            ],
        ];
    }

    /**
     * @return Campo
     */
    private static function url(string $id, string $key, string $rotulo): array
    {
        return self::campo($id, $key, $rotulo, 'url');
    }

    /**
     * @return Campo
     */
    private static function texto(string $id, string $key, string $rotulo): array
    {
        return self::campo($id, $key, $rotulo, 'texto');
    }

    /**
     * @return Campo
     */
    private static function senha(string $id, string $key, string $rotulo): array
    {
        return self::campo($id, $key, $rotulo, 'senha');
    }

    /**
     * @return Campo
     */
    private static function interruptor(string $id, string $key, string $rotulo): array
    {
        return self::campo($id, $key, $rotulo, 'interruptor');
    }

    /**
     * @param  list<array{value: string, label: string}>  $opcoes
     * @return Campo
     */
    private static function escolha(string $id, string $key, string $rotulo, array $opcoes): array
    {
        return self::campo($id, $key, $rotulo, 'escolha', $opcoes);
    }

    /**
     * @param  list<array{value: string, label: string}>  $opcoes
     * @return Campo
     */
    private static function campo(string $id, string $key, string $rotulo, string $tipo, array $opcoes = []): array
    {
        return [
            'id' => $id,
            'key' => $key,
            'rotulo' => $rotulo,
            'tipo' => $tipo,
            'opcoes' => $opcoes,
        ];
    }
}
