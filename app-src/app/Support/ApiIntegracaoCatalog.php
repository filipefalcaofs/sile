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
            self::cnpj(),
            self::govbr(),
            self::geocoding(),
            self::geoserver(),
            self::whatsapp(),
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
            'descricao' => 'Consulta cadastral anônima da SEDUR (ws-contato). Troque prd por hml na URL para homologação.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::url('base_url', 'integrations.sedur_contato.base_url', 'URL base'),
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
            'descricao' => 'API institucional de login AD. A credencial é a do próprio servidor, não fica gravada aqui.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('habilitada', 'features.ad_login', 'Login AD habilitado'),
                self::url('base_url', 'integrations.sedur_auth.base_url', 'URL base'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function cnpj(): array
    {
        return [
            'id' => 'cnpj',
            'nome' => 'CNPJ (Receita)',
            'descricao' => 'Consulta de CNPJ no cadastro de empresa, pelos dados abertos da Receita Federal.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('habilitada', 'features.cnpj_lookup', 'Consulta habilitada'),
                self::url('base_url', 'integrations.cnpj_lookup.base_url', 'URL base'),
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
            'descricao' => 'Login Único. Staging usa sso.staging.acesso.gov.br; produção usa sso.acesso.gov.br.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('habilitada', 'features.govbr_login', 'Login GOV.BR habilitado'),
                self::url('base_url', 'integrations.govbr.base_url', 'URL base'),
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
            'descricao' => 'Nominatim da Prefeitura, usado quando o cadastro não traz coordenada.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('habilitada', 'features.geocoding', 'Geocodificação habilitada'),
                self::url('base_url', 'integrations.geocoding.base_url', 'URL base'),
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
            'descricao' => 'WFS/WMS de zoneamento da SEDUR.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('zona', 'features.geoserver_zona', 'Consulta de zona habilitada'),
                self::interruptor('territorio', 'features.geoserver_territorio', 'Regras territoriais habilitadas'),
                self::url('base_url', 'integrations.geoserver.base_url', 'URL base'),
            ],
        ];
    }

    /**
     * @return Aba
     */
    private static function whatsapp(): array
    {
        return [
            'id' => 'whatsapp',
            'nome' => 'WhatsApp',
            'descricao' => 'API comercial de notificação. Sem URL e token, o canal permanece desligado.',
            'teste' => null,
            'espelhar_url_ativa' => null,
            'campos' => [
                self::interruptor('habilitada', 'features.notificacao_whatsapp', 'Canal habilitado'),
                self::url('base_url', 'integrations.whatsapp.base_url', 'URL base'),
                self::senha('token', 'integrations.whatsapp.token', 'Token'),
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
