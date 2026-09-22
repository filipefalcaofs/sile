<?php

namespace Tests\Fixtures\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Agent mínimo de teste para provar de ponta a ponta que a ponte de runtime
 * (AiConfigResolver/AiConfigServiceProvider) deixa o SDK laravel/ai operacional
 * com a configuração do banco — SEM rede e SEM credencial real. O provedor é
 * resolvido a partir de config('ai.default'); o gateway é substituído pelo fake
 * por agent (BridgeProbeAgent::fake([...])).
 */
class BridgeProbeAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Você é um agente de teste da ponte de configuração de IA.';
    }
}
