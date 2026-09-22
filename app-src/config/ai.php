<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provedores padrão por capacidade
    |--------------------------------------------------------------------------
    |
    | Defaults do SDK laravel/ai. Em runtime, a ponte de configuração
    | (App\Services\Ai\AiConfigResolver + App\Providers\AiConfigServiceProvider)
    | SOBRESCREVE estes valores com a configuração ADMINISTRÁVEL do banco (model
    | AiConfiguration) — sem deploy (HU-014). Sem nenhuma configuração ativa,
    | permanecem estes defaults e qualquer função de IA fica atrás de um toggle
    | features.ia_* OFF (degradação controlada, nunca falha silenciosa).
    |
    */

    'default' => env('AI_DEFAULT', 'openai'),
    'default_for_embeddings' => env('AI_DEFAULT_FOR_EMBEDDINGS', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Armazenamento de conversas (assistentes — Onda 2)
    |--------------------------------------------------------------------------
    |
    | O pacote referencia `ai.conversations.*` (DatabaseConversationStore) mas NÃO
    | publica este bloco — declaramos com defaults seguros para o store ficar
    | determinístico quando os assistentes (RemembersConversations) entrarem.
    |
    | connection nulo = conexão padrão do banco (DB::connection(null)). NÃO usamos
    | config('database.default') aqui: config/ai.php é carregado ANTES de
    | config/database.php (ordem alfabética), então a chamada devolveria null.
    |
    | Os demais blocos (`providers`, `caching`) vêm do próprio pacote via
    | mergeConfigFrom (Laravel\Ai\AiServiceProvider) — não os duplicamos aqui para
    | não divergir do SDK; a ponte apenas mescla os overrides do banco sobre eles.
    |
    */

    'conversations' => [
        'connection' => env('AI_CONVERSATIONS_CONNECTION'),
        'tables' => [
            'conversations' => 'agent_conversations',
            'messages' => 'agent_conversation_messages',
        ],

        // Os assistentes (HU-120/121) NÃO expõem uma lista de conversas titulada —
        // a conversa é escopada por cidadão/processo na própria tela. Gerar título
        // por IA custaria uma chamada extra ao provedor a cada nova conversa, sem
        // valor para o usuário; o store cai no resumo da 1ª mensagem (Str::limit),
        // determinístico e sem custo. Ligar só se uma tela de histórico exigir.
        'generate_title' => env('AI_CONVERSATIONS_GENERATE_TITLE', false),
    ],

];
