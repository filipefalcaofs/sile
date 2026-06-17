<?php

namespace App\Services\Ai;

use App\Models\AiConfiguration;

/**
 * Contrato do cliente de provedor de IA (Fase 14, Onda 0).
 *
 * Por ora expõe só o teste de conexão REAL; a ponte de runtime do SDK
 * (`laravel/ai`) entra na Onda 1 atrás deste mesmo namespace. O binding default
 * é o `OpenAiCompatibleClient` (HTTP direto OpenAI-compatible).
 */
interface AiProviderClient
{
    public function testConnection(AiConfiguration $config): ConnectionResult;
}
