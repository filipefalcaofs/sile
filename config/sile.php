<?php

return [
    'security' => [
        'password' => [
            'min_length' => 8,
            'require_mixed_case' => true,
            'require_numbers' => true,
            'require_symbols' => false,
        ],
        'login' => ['max_attempts' => 5],
        'password_reset_expire' => 60,
        'govbr' => ['minimum_level' => 'bronze'],
    ],
    'ui' => [
        'access_history' => ['per_page' => 15],
        'cnaes' => ['per_page' => 15],
        'users' => ['per_page' => 15],
        'companies' => ['per_page' => 15],
    ],
    'features' => [
        'procuracoes' => true,
        'cnpj_lookup' => true,
        'govbr_login' => false,
        'geocoding' => true,
    ],
    // Chaves pt-BR (geo.*, retencao.*, seguranca.*) espelham os parâmetros
    // HU-014 de mesmo nome — Settings::get lê config("sile.{chave}") no
    // fallback. São distintas do bloco `security` (inglês): decisão travada da
    // Fase 3.1.
    'geo' => [
        'validacao' => ['sobreposicao_minima' => 50],
        // Constante técnica (raio em metros da "via mais próxima"): fica SÓ
        // aqui, NÃO entra no catálogo do ParameterSeeder — precedente [02-02].
        'via_max_metros' => 50,
    ],
    'retencao' => [
        'access_logs' => ['dias' => 365],
    ],
    'seguranca' => [
        'throttle' => [
            'cnpj_lookup' => ['por_minuto' => 30],
            'geocoding' => ['por_minuto' => 60],
        ],
    ],
    'integrations' => [
        // Constantes técnicas (timeout/retries/cache_ttl) ficam SÓ aqui,
        // nunca no registry — precedente [02-02]. base_url é parametrizável.
        'cnpj_lookup' => [
            'base_url' => 'https://brasilapi.com.br/api/cnpj/v1',
            'timeout' => 8,
            'retries' => 2,
            'backoff_ms' => 200,
            'cache_ttl' => 86400,
        ],
        'govbr' => [
            'base_url' => 'https://sso.staging.acesso.gov.br',
            'timeout' => 8,
            'jwk_cache_ttl' => 3600,
            'jwt_leeway' => 60,
        ],
        'geocoding' => [
            'base_url' => 'https://nominatim.openstreetmap.org',
            'user_agent' => 'SILE-SEDUR-Salvador/1.0 (contato@sedur.salvador.ba.gov.br)',
            'timeout' => 8,
            'retries' => 2,
            'backoff_ms' => 1000,
            'cache_ttl' => 86400,
        ],
    ],
    'parameters' => [
        'cache_ttl' => 300,
    ],
];
