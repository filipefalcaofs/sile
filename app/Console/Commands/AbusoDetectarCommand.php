<?php

namespace App\Console\Commands;

use App\Services\Abuso\AbuseDetectionService;
use Illuminate\Console\Command;

/**
 * HU-149: motor de detecção de abuso. Apenas ORQUESTRA o AbuseDetectionService —
 * NUNCA decide/pune (RN-001/RN-003). NO-OP honesto enquanto features.deteccao_abuso
 * está OFF (default): imprime "detecção desligada" e sai 0, sem gravar nada. Quando
 * a SEDUR ligar o toggle, gera alertas idempotentes e encaminha à malha fina acima
 * do limiar. Agendado diário e seguro em multi-instância (withoutOverlapping/
 * onOneServer) — espelha o padrão idempotente das Fases 9/11.
 */
class AbusoDetectarCommand extends Command
{
    protected $signature = 'abuso:detectar';

    protected $description = 'HU-149: detecta padrões de abuso (alerta + malha fina), idempotente; no-op honesto enquanto desligado';

    public function handle(AbuseDetectionService $service): int
    {
        $resumo = $service->detectar();

        if (! $resumo['executado']) {
            $this->info('Detecção de abuso desligada (features.deteccao_abuso=0).');

            return self::SUCCESS;
        }

        $this->info("Detecção de abuso: {$resumo['criados']} alerta(s) criado(s), {$resumo['reaproveitados']} reaproveitado(s), {$resumo['encaminhados']} encaminhado(s) à malha fina.");

        return self::SUCCESS;
    }
}
