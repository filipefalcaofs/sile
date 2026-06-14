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
        'consulta_viabilidade' => true,
        'solicitacao_viabilidade' => true,
        'simulacao_solicitacao' => true,
        'fluxo_expresso' => true,
        'notificacao_resultado_expresso' => true,
        'analise_tecnica' => true,
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
    // Espelha os parâmetros HU-014 risco.* (encaminhamento). Settings::get lê
    // config("sile.risco.*") no fallback (banco indisponível). O mapa é o ARRAY
    // já decodificado — typedValue() do parâmetro json também devolve array, de
    // modo que o consumidor (motor 06-05) sempre recebe array, nunca string.
    // Nível ausente no mapa degrada para 'analise' (decisão do consumidor) —
    // o decreto não tem nível "médio".
    'risco' => [
        'mapa_encaminhamento' => ['baixo_a' => 'expresso', 'baixo_b' => 'expresso', 'alto' => 'analise'],
        'dimensao_tvl' => 'municipal',
    ],
    // Espelha os parâmetros HU-014 louos.* (motor de regras da LOUOS).
    // Settings::get lê config("sile.louos.*") no fallback (banco indisponível).
    // exigencia_por_grupo é o ARRAY já decodificado — typedValue() do parâmetro
    // json também devolve array, de modo que o motor sempre recebe array. Vazio
    // = vagas não parametrizadas (a SEDUR ainda não entregou): o motor registra
    // "não parametrizado", nunca bloqueia silenciosamente.
    'louos' => [
        'vagas' => ['exigencia_por_grupo' => []],
        'sandbox' => ['amostra_padrao' => 50],
    ],
    // Espelha os parâmetros HU-014 solicitacao.* e storage.documentos.* (Fase 8).
    // Settings::get lê config("sile.solicitacao.*") / config("sile.storage.*") no
    // fallback (banco indisponível). mime_permitidos é o ARRAY já decodificado —
    // typedValue() do parâmetro json também devolve array. O disk dos documentos
    // NUNCA é público (precedente de LGPD/anexos).
    'solicitacao' => [
        'cnaes_complementares' => ['max' => 99],
        'protocolo' => ['prefixo' => 'VIA', 'padding' => 6],
        'consulta_publica' => ['assinatura_ttl_dias' => 30],
        'anexos' => ['max_mb' => 10, 'mime_permitidos' => ['application/pdf', 'image/jpeg', 'image/png']],
        'area_poligono' => ['tolerancia_percentual' => 10],
        'prazo_estimado_dias' => 30,
        'atendimento' => ['expiracao_minutos' => 30],
        // Estados em que o requerente pode cancelar a solicitação (HU-070).
        // Default honesto enquanto não decidido (rascunho + protocolada); a
        // definição fina é pendência SEDUR. typedValue() do parâmetro json
        // também devolve array, então o serviço sempre recebe array.
        'cancelamento' => ['estados_cancelaveis' => ['rascunho', 'protocolada']],
        // Janela (em dias) da detecção de reincidência por CNPJ (HU-061 RN-007).
        // É uma CONSTANTE técnica/de negócio aqui, NÃO um parâmetro do catálogo
        // HU-014: a definição oficial de "duplicidade/reincidência" é pendência
        // SEDUR — este é um default honesto e ajustável sem deploy. O detector
        // só ALERTA (link ao processo anterior), nunca bloqueia.
        'duplicidade' => ['janela_dias' => 180],
    ],
    'storage' => [
        'documentos' => ['disk' => 'local'],
    ],
    // Espelha os parâmetros HU-014 expresso.* / features.* do fluxo expresso
    // (EP09). Settings::get lê config("sile.expresso.*") no fallback (banco
    // indisponível). Os valores de negócio (prazo BAP, assuntos de e-mail,
    // formato do número TVL) nascem administráveis no ParameterSeeder; aqui é
    // só o espelho de fallback.
    'expresso' => [
        'bap' => ['prazo_horas' => 48],
        'notificacao' => [
            'assunto_deferida' => 'Resultado da sua solicitação de viabilidade: deferida',
            'assunto_indeferida' => 'Resultado da sua solicitação de viabilidade: indeferida',
        ],
        'tvl' => ['prefixo' => 'TVL', 'padding' => 6],
        // Constantes TÉCNICAS (fora do catálogo HU-014 — precedente [02-02]):
        // idempotência/concorrência da emissão da decisão (TTL do Cache::lock)
        // e resiliência do job de decisão (fila + tries/timeout/backoff). Não
        // são valores de negócio; parametrizá-los no painel seria ruído.
        'lock' => ['ttl_segundos' => 10],
        'fila' => 'default',
        'job' => ['tries' => 3, 'timeout' => 120, 'backoff' => [30, 60, 120]],
    ],
    // Espelha os parâmetros HU-014 analise.* / features.analise_tecnica da
    // análise técnica (EP10). Settings::get lê config("sile.analise.*") no
    // fallback (banco indisponível). Os valores de NEGÓCIO (SLA por etapa,
    // limiar do semáforo, prazo de pendência, janela/itens de precedentes e o
    // TVL PDF) nascem administráveis no ParameterSeeder; aqui é só o espelho.
    'analise' => [
        'sla' => [
            'distribuicao_dias' => 2,
            'analise_dias' => 10,
            'semaforo' => ['amarelo_percentual' => 80],
        ],
        'pendencia' => ['prazo_resposta_dias' => 15],
        'precedentes' => [
            'janela_meses' => 12,
            'max_itens' => 10,
            // Constante TÉCNICA (fora do catálogo HU-014 — precedente [02-02]):
            // TTL (segundos) do cache das estatísticas de precedentes. Não é
            // valor de negócio; parametrizá-la no painel seria ruído.
            'cache_ttl_segundos' => 300,
        ],
        'tvl' => [
            'disk' => 'local',
            'assinatura' => ['modo' => 'imagem', 'imagem_path' => ''],
            'download' => ['ttl_minutos' => 5],
        ],
        // Constante TÉCNICA (fora do catálogo HU-014 — precedente [02-02]):
        // debounce (ms) do autosave da ficha de análise. É detalhe de UX/UI,
        // não decisão de negócio.
        'autosave' => ['debounce_ms' => 1500],
    ],
    'seguranca' => [
        'throttle' => [
            'cnpj_lookup' => ['por_minuto' => 30],
            'geocoding' => ['por_minuto' => 60],
            'consulta_viabilidade' => ['por_minuto' => 20],
            'consulta_protocolo' => ['por_minuto' => 30],
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
