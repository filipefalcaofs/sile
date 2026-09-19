<?php

return [
    // Massa de demonstração para validação SEDUR (Portainer/staging).
    // Nunca ligar em produção real — só no ambiente de apresentação ao cliente.
    'demo_data' => env('SILE_DEMO_DATA', false),

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
        'email_logs' => ['per_page' => 20],
        'auditoria' => ['per_page' => 20],
        // Quantidade de solicitações recentes no painel do cidadão. Constante
        // técnica/de UI (precedente [02-02]): NÃO entra no catálogo HU-014.
        'painel' => ['solicitacoes_recentes' => 5],
    ],
    'features' => [
        'procuracoes' => true,
        'cnpj_lookup' => true,
        'govbr_login' => false,
        'geocoding' => true,
        'geoserver_zona' => true,
        'cadastro_imobiliario' => env('SILE_CADASTRO_IMOBILIARIO', true),
        'consulta_viabilidade' => true,
        'solicitacao_viabilidade' => true,
        'simulacao_solicitacao' => true,
        'fluxo_expresso' => true,
        // Bypass de HOMOLOGAÇÃO do motor de risco (simulação REGIN): ligado,
        // processo nascido do simulador com veredito pendente (zona oficial
        // pendente SEDUR) emite TVL como permitido. DESLIGADO por default —
        // NUNCA ligar em produção; remoção definitiva antes do go-live.
        'simulacao_protocolo' => false,
        'notificacao_resultado_expresso' => true,
        'analise_tecnica' => true,
        // Toggles de canal da comunicação multicanal (EP11). E-mail e in-app
        // ligados; WhatsApp DESLIGADO (provedor real só na Fase 13 — bloqueio
        // honesto, degrada de forma comunicada).
        'notificacao_email' => true,
        'notificacao_in_app' => true,
        'notificacao_whatsapp' => false,
        // Toggles das funções de IA (Fase 14 — HU-014 aplicada à IA). TODOS
        // nascem DESLIGADOS: a fundação (config multi-provider) não liga função
        // nenhuma; cada onda (1-3) liga a sua quando entregar. Desligado degrada
        // de forma controlada (a função some/avisa), nunca falha silenciosa.
        'ia_ocr' => false,
        'ia_classificacao' => false,
        'ia_inconsistencias' => false,
        'ia_resumo' => false,
        'ia_parecer' => false,
        'ia_explicacao' => false,
        'ia_assistente' => false,
        // Auditoria Preditiva de Processos Expressos (Módulo 3): nasce DESLIGADA
        // (governança DPO/LGPD art. 20 — nunca pune, só alerta + malha fina).
        'ia_auditoria_preditiva' => false,
    ],
    // Chaves pt-BR (geo.*, retencao.*, seguranca.*) espelham os parâmetros
    // HU-014 de mesmo nome — Settings::get lê config("sile.{chave}") no
    // fallback. São distintas do bloco `security` (inglês): decisão travada da
    // Fase 3.1.
    'geo' => [
        'validacao' => ['sobreposicao_minima' => 50],
        // Atributos candidatos ao nome da zona na feição — fallback do
        // parâmetro geo.zona.atributos_nome (HU-014).
        'zona' => ['atributos_nome' => ['ZONA', 'zona', 'SIGLA_ZONA', 'SUBZONA']],
        // Constante técnica (raio em metros da "via mais próxima"): fica SÓ
        // aqui, NÃO entra no catálogo do ParameterSeeder — precedente [02-02].
        'via_max_metros' => 50,
        // Constante TÉCNICA de upload (fora do catálogo HU-014 — precedente
        // [02-02]): teto em MB do GeoJSON importado pela gestão de camadas.
        // É limite de infraestrutura (tamanho de request), não valor de
        // negócio; ajustável sem deploy se uma base oficial maior precisar subir.
        'upload_max_mb' => 20,
    ],
    'retencao' => [
        'access_logs' => ['dias' => 365],
    ],
    // Constante TÉCNICA da exportação da trilha (HU-101): teto de linhas por
    // arquivo CSV para a guarda de volume do streaming — NÃO materializa
    // exportações gigantes. Fora do catálogo HU-014 (precedente [02-02]): é
    // resiliência/volume, não valor de negócio; ajustável sem deploy se preciso.
    'auditoria' => [
        'export' => ['max_linhas' => 50000],
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
        // HU-014: fallback da janela de reincidência por CNPJ. O valor vigente
        // vive em solicitacao.duplicidade.janela_dias. A definição oficial
        // continua pendente SEDUR — este é o default honesto. O detector só
        // ALERTA (link ao processo anterior), nunca bloqueia.
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
        'pendencia' => ['prazo_resposta_dias' => 15, 'prazo_resposta_horas_uteis' => 48],
        'escritorio_virtual' => [
            'cnae_gatilho_sede' => '8211-3/00',
            'condicionante_sede' => 'A viabilidade é DEFERIDA na condição de prestação de serviços de escritório virtual, nos termos da legislação vigente.',
            'mensagem_bloqueio_abrigado' => 'A atividade informada não está na lista de atividades permitidas para escritório virtual nesta inscrição.',
            'mensagem_recusa_abrigo' => 'Inscrição imobiliária vinculada a uma sede de escritório virtual. Para exercer atividades nesse local, deverá ser abrigado da sede vinculada.',
            'mensagem_cnae_sede_em_abrigado' => 'O CNAE :cnae não é permitido para exercício em escritório virtual e coworking, conforme as disposições do Anexo B do Decreto Municipal nº 35.062/2021.',
            'mensagem_sede_duplicada' => 'Já existe uma sede de escritório virtual vinculada a esta inscrição imobiliária.',
            'mensagem_cnae_fora_anexo_a' => 'O CNAE :cnae não é permitido para exercício em sede de escritório virtual, conforme as disposições do Anexo A do Decreto Municipal nº 35.062/2021.',
            'flag_analise_sede' => 'Verificar se atende ao §2º do artigo 6º do Decreto Municipal nº 35.062, de 29 de dezembro de 2021.',
            'mensagem_confirma_perda_sede' => 'A exclusão do CNAE :cnae fará com que a empresa deixe de ser caracterizada como Sede de Escritório Virtual. Deseja prosseguir com a exclusão?',
            'pergunta_geral' => 'Deseja ser abrigado de escritório virtual?',
            'pergunta_vinculada' => 'Irá prestar serviço de escritório virtual, centro de negócios ou coworking?',
        ],
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
            // Constantes TÉCNICAS do PDF (fora do catálogo HU-014 — precedente
            // [02-02]): tamanho do papel e orientação do dompdf. São detalhe de
            // renderização, não decisão de negócio.
            'paper' => 'a4',
            'orientation' => 'portrait',
        ],
        // Constante TÉCNICA (fora do catálogo HU-014 — precedente [02-02]):
        // debounce (ms) do autosave da ficha de análise. É detalhe de UX/UI,
        // não decisão de negócio.
        'autosave' => ['debounce_ms' => 1500],
    ],
    // Espelha os parâmetros HU-014 notificacoes.* da comunicação multicanal
    // (EP11). Settings::get lê config("sile.notificacoes.*") no fallback (banco
    // indisponível). mapa_canais e escalonamento.tratamento são os ARRAYS já
    // decodificados — typedValue() do parâmetro json também devolve array, de
    // modo que o dispatcher (11-04) sempre recebe array, nunca string. Os
    // toggles de canal ficam no bloco `features`; as constantes técnicas do
    // WhatsApp, em `integrations.whatsapp`.
    'notificacoes' => [
        'mapa_canais' => [
            'pendencia_aberta' => ['email', 'in_app'],
            'pendencia_respondida' => ['in_app'],
            'pendencia_expirada' => ['email', 'in_app'],
            'prazo_vencendo' => ['email', 'in_app'],
            'escalonamento_sla' => ['email', 'in_app'],
            'resultado' => ['email', 'in_app'],
        ],
        'vencimento' => ['antecedencia_dias' => 3],
        'escalonamento' => [
            'tratamento' => ['amarelo' => 'notificar_analista', 'vencido' => 'notificar_gestor'],
            'gestor_role' => 'gestor',
        ],
        'pendencia' => [
            'assunto' => 'Convite na sua solicitação de viabilidade {protocolo}',
            'corpo' => 'Olá! Identificamos um convite na sua solicitação de viabilidade {protocolo}. Convite: {pendencia}. Acesse o portal do Viabiliza para responder dentro do prazo informado.',
        ],
    ],
    'seguranca' => [
        'throttle' => [
            'cnpj_lookup' => ['por_minuto' => 30],
            'geocoding' => ['por_minuto' => 60],
            'consulta_viabilidade' => ['por_minuto' => 20],
            'consulta_protocolo' => ['por_minuto' => 30],
            // Teste de conexão de IA (Fase 14): ação interna de admin
            // (manter-config-ia). Teto técnico/de segurança fora do catálogo
            // HU-014 (precedente [02-02]) — ajustável sem deploy via Settings.
            'ai_test' => ['por_minuto' => 10],
            'regin_recebe' => ['por_minuto' => 60],
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
            'user_agent' => 'Viabiliza-SEDUR-Salvador/1.0 (contato@sedur.salvador.ba.gov.br)',
            'timeout' => 8,
            'retries' => 2,
            'backoff_ms' => 1000,
            'cache_ttl' => 86400,
        ],
        'inscricao_imobiliaria' => [
            'em_producao' => true,
            'url_homologacao' => 'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            'url_producao' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            'base_url' => 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            'inscricao_teste' => '0000000000',
            'timeout' => 12,
            'retries' => 3,
            'backoff_ms' => 500,
        ],
        'geoserver' => [
            'base_url' => 'https://geoserver.sedur.salvador.ba.gov.br/geoserver',
            'user_agent' => 'Viabiliza-SEDUR-Salvador/1.0 (contato@sedur.salvador.ba.gov.br)',
            'timeout' => 8,
            'retries' => 1,
            'backoff_ms' => 500,
            'type_names' => [
                'louos_zpr1:VM_L_Z_USO_ZPR_1',
                'louos_zpr2:VM_L_Z_USO_ZPR_2',
                'louos_zpr3:VM_L_Z_USO_ZPR_3',
                'louos_zpam:VM_L_Z_USO_ZPAM',
                'louos_zde1:VM_L_Z_USO_ZDE_1',
                'louos_zde2:VM_L_Z_USO_ZDE_2',
                'louos_zue:VM_L_Z_USO_ZUE',
                'louos_zusi:VM_L_Z_USO_ZUSI',
                'louos_zem:VM_L_Z_USO_ZEM',
                'louos_zit:VM_L_Z_USO_ZIT',
                'louos_zeis:VM_L_Z_USO_ZEIS',
                'louos_zona_uso_zclme:VM_L_Z_USO_ZCLME',
                'louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU',
                'louos_zcme_aguas_claras:VM_L_Z_USO_ZCME_AGUAS_CLARAS',
                'louos_zcme_camaragibe:VM_L_Z_USO_ZCME_CAMARAGIBE',
                'louos_zcme_centro_antigo:VM_L_Z_USO_ZCME_CA',
                'louos_zcme_luis_viana_29_marco:VM_L_Z_USO_ZCME_L_VIANA_29_MAR',
                'louos_zcme_retiro_acesso_norte:VM_L_Z_USO_ZCME_RET_ACESS_NOR',
                'louos_zcmu_municipal_1:VM_L_Z_USO_ZCMU_1',
                'louos_zcmu_municipal_2:VM_L_Z_USO_ZCMU_2',
            ],
        ],
        // WhatsApp (HU-095): base_url/token são parametrizáveis (catálogo
        // HU-014; token sensível/criptografado). As constantes TÉCNICAS
        // (timeout/tries/backoff) ficam SÓ aqui — precedente [02-02] —, pois são
        // cadência/resiliência do adaptador real (Fase 13), não valor de
        // negócio. Provedor real bloqueado: toggle features.notificacao_whatsapp
        // nasce off (degradação honesta).
        'whatsapp' => [
            'base_url' => '',
            'token' => '',
            'timeout' => 8,
            'tries' => 3,
            'backoff_ms' => 1000,
        ],
        'regin' => [
            'em_producao' => false,
            'url_homologacao' => 'http://10.57.247.9:8080/api_integracao',
            'url_producao' => 'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao',
            'usuario' => 'sedur_integracao',
            'senha' => '',
            'cnpj_prefeitura' => '13927801000149',
            'timeout' => 8,
        ],
    ],
    // Espelha os parâmetros HU-014 relatorios.* (EP15). Settings::get lê
    // config("sile.relatorios.*") no fallback (banco indisponível). As
    // constantes TÉCNICAS (max_linhas, chunk, disk, pdf, cache_ttl_segundos,
    // job) ficam SÓ aqui, fora do catálogo (precedente [02-02]).
    // formatos_habilitados é o ARRAY já decodificado — typedValue() do parâmetro
    // json também devolve array. meta_taxa é OMITIDO de propósito: a meta nasce
    // "não definida" (pendência SEDUR); espelhá-la como null faria config()
    // devolver null em vez do default do call site — Settings::get(chave,
    // "indefinida") só cai em "indefinida" quando a CHAVE está ausente (Arr::get).
    'relatorios' => [
        'export' => [
            'assincrono_limiar_linhas' => 5000,
            'formatos_habilitados' => ['csv', 'xlsx', 'pdf'],
            'retencao_dias' => 7,
            'max_linhas' => 100000,
            'chunk' => 200,
            'disk' => 'local',
            'pdf' => ['paper' => 'a4', 'orientation' => 'portrait'],
        ],
        'expresso' => ['janela_dias' => 30],
        // Janela (dias) dos KPIs do painel executivo (HU-122). Constante TÉCNICA
        // fora do catálogo HU-014 (precedente [02-02]): recorte de leitura do
        // dashboard, ajustável sem deploy; não é valor de negócio do licenciamento.
        'dashboard' => ['janela_dias' => 30],
        'cache_ttl_segundos' => 300,
        'job' => ['tries' => 3, 'timeout' => 300, 'backoff' => [30, 60, 120], 'fila' => 'default'],
        'tempo' => ['etapas' => []],
        // Observatório de Saturação Locacional (Módulo 2): espelho de fallback dos
        // parâmetros HU-014 relatorios.saturacao.*. capacidades é o ARRAY já
        // decodificado (mapa código CNAE → limite); vazio = sem capacidade
        // definida (degrada honesto). Os limiares classificam saturando/saturado.
        'saturacao' => [
            'capacidades' => [],
            'alerta_percentual' => 80,
            'bloqueio_percentual' => 100,
        ],
    ],
    // Configuração de IA (Fase 14, Onda 0). Os provedores administráveis vivem
    // no banco (model AiConfiguration). Aqui ficam só os controles de SEGURANÇA
    // do teste de conexão — constantes técnicas/de defesa, fora do catálogo
    // HU-014 (precedente [02-02]).
    'ai' => [
        // Allowlist anti-SSRF de hosts permitidos para a integração de IA. A
        // base_url é administrável → tratada como SSRF: SÓ estes hosts (sempre
        // https) podem ser alvo do teste de conexão (e, na Onda 1, do runtime).
        // Controle PRIMÁRIO de egress. Lida via Settings::get('ai.allowed_hosts')
        // (uma futura linha de catálogo sobrescreve sem deploy); mantê-la
        // deploy-controlada é a postura segura — evita SSRF por má configuração.
        'allowed_hosts' => [
            'api.openai.com',
            'api.anthropic.com',
            'generativelanguage.googleapis.com',
        ],
        // Tetos do teste de conexão: connectTimeout baixo + teto do timeout total
        // (um timeout_ms enorme não pode prender o servidor — anti-SSRF/slowloris).
        'test' => [
            'connect_timeout' => 3,
            'timeout_max_ms' => 15000,
        ],
        // TTL (segundos) do cache da ponte de runtime (AiConfigResolver): evita
        // ler o banco a cada boot. Constante TÉCNICA fora do catálogo HU-014
        // (precedente [02-02]) — ajustável sem deploy via Settings. A gravação/
        // exclusão de uma AiConfiguration invalida o cache na hora (evento do
        // model), então o TTL é só a rede de segurança.
        'config_cache_ttl' => 60,
        // Infra de RAG (Onda 3): dimensão do vetor de embeddings. FONTE ÚNICA da
        // coluna `vector(N)` da migration e do `->dimensions()` pedido ao SDK —
        // coluna e vetor têm de casar. 1536 = padrão do text-embedding-3-small
        // (OpenAI). Constante TÉCNICA atada ao modelo escolhido, fora do catálogo
        // HU-014 (precedente [02-02]); trocar de modelo/dimensão exige reindexar.
        'embeddings' => [
            'dimensions' => 1536,
        ],
        // Resiliência do job de execução de IA (Onda 1+), espelhando
        // sile.expresso.job: fila + tries/timeout/backoff. Constante TÉCNICA fora
        // do catálogo HU-014 (precedente [02-02]) — não é valor de negócio.
        'job' => ['tries' => 3, 'timeout' => 120, 'backoff' => [30, 60, 120], 'fila' => 'default'],
        // Limiar de confiança do guardrail: sugestão com confiança ABAIXO deste
        // nível é escalada para análise humana (AI-SPEC §6). Fallback técnico em
        // config; lido via Settings::get('ai.limiar_confianca') — uma futura linha
        // de catálogo HU-014 o torna administrável sem deploy, sem mudar call site.
        'limiar_confianca' => 'media',
        // Preço por 1.000 tokens (entrada + saída) por modelo, para estimar o
        // custo da chamada (o SDK só entrega tokens). Vazio por padrão ⇒ custo
        // null (NUNCA inventado — anti-fachada). Administrável via Settings.
        'preco_por_modelo' => [],
    ],
    // Espelho de fallback dos parâmetros HU-014 ia.* (Auditoria Preditiva —
    // Módulo 3). O bloco 'ia' (pt) é distinto do bloco 'ai' (SDK, en); o toggle
    // fica em features.ia_auditoria_preditiva. Valores de negócio (janela/limiar)
    // administráveis via catálogo; aqui é só o espelho de fallback.
    'ia' => [
        'auditoria_preditiva' => [
            'janela_dias' => 30,
            'limiar_score' => 70,
            'pesos' => ['volume' => 40, 'inscricao' => 30, 'prosseguiu' => 40],
            'cortes_severidade' => ['alta' => 80, 'media' => 60],
        ],
    ],
    'parameters' => [
        'cache_ttl' => 300,
    ],
];
