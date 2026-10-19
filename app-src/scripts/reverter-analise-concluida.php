<?php

declare(strict_types=1);

/**
 * Reverte o status de tramitação de "Análise concluída" para "Para conclusão",
 * para corrigir uma conclusão feita por equívoco. O processo volta à fila de
 * conclusão sem mudar de setor nem de responsável.
 *
 * Só age quando a conclusão não gerou decisão: processo com decisão gravada
 * (deferido, indeferido, cassado, revogado ou desativado) é recusado, porque a
 * decisão e o aviso à JUCEB não se desfazem por script.
 *
 * Por padrão só mostra o que seria feito; a gravação exige --aplicar.
 *
 * No terminal do pod:
 *
 *   php /tmp/reverter-analise-concluida.php BAP2601857401
 *   php /tmp/reverter-analise-concluida.php BAP2601857401 --aplicar
 *
 * Aceita o número do BAP ou o do protocolo. O último argumento que for um
 * diretório é a raiz da aplicação (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$autonomo = ! isset($app);

if ($autonomo) {
    $argumentos = array_slice($argv ?? [], 1);
    $raiz = null;
    $numero = null;
    $aplicar = false;

    foreach ($argumentos as $argumento) {
        if ($argumento === '--aplicar') {
            $aplicar = true;
        } elseif (is_dir($argumento)) {
            $raiz = $argumento;
        } elseif ($numero === null) {
            $numero = $argumento;
        }
    }

    $raiz ??= is_dir('/opt/app-root/src') ? '/opt/app-root/src' : '/var/www/html';

    require $raiz.'/vendor/autoload.php';

    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
}

$numero = $numero ?? null;
$aplicar = $aplicar ?? false;

$codigo = (function () use ($app, $numero, $aplicar): int {
    if ($numero === null) {
        fwrite(STDERR, "Informe o número do BAP ou do protocolo.\n");

        return 1;
    }

    $processos = ViabilityRequest::query()
        ->where(fn ($q) => $q->where('external_reference', $numero)->orWhere('protocol_number', $numero))
        ->get();

    if ($processos->count() !== 1) {
        fwrite(STDERR, "Esperava 1 processo para {$numero}, encontrei {$processos->count()}.\n");

        return 1;
    }

    $processo = $processos->first();
    $status = $processo->analysis_status;
    $decisao = $processo->decision()->first();

    echo "Processo #{$processo->id} protocolo {$processo->protocol_number} BAP ".($processo->external_reference ?? '(sem BAP)')."\n";
    echo '  status do processo: '.$processo->status->value."\n";
    echo '  status de tramitação: '.($status?->value ?? '(nulo)')."\n";
    echo '  responsável: '.($processo->assigned_user_id ?? '(nenhum)')."\n";
    echo '  decisão gravada: '.($decisao === null ? 'não' : 'sim ('.$decisao->outcome->value.')')."\n";

    if ($status !== AnalysisStatus::AnaliseConcluida) {
        fwrite(STDERR, 'O status de tramitação não é "Análise concluída"; nada a reverter.'."\n");

        return 1;
    }

    if ($decisao !== null || $processo->status !== ViabilityRequestStatus::EmAnalise) {
        fwrite(STDERR, "O processo já tem decisão ou saiu de \"Em análise\"; a reversão não é feita por script. Trate o caso pela tela (reabertura) ou com a SEDUR.\n");

        return 1;
    }

    if (! $aplicar) {
        echo "Simulação: voltaria o status de tramitação para para_conclusao. Rode com --aplicar para gravar.\n";

        return 0;
    }

    DB::transaction(function () use ($app, $processo, $status): void {
        $app->make(AnalysisStatusStateMachine::class)->transition(
            $processo,
            AnalysisStatus::ParaConclusao,
            null,
            'Reversão de conclusão feita por equívoco operacional, a pedido da SEDUR.',
            true,
        );

        $app->make(AuditService::class)->log(
            'analise',
            'reverter-analise-concluida',
            "Análise concluída revertida para Para conclusão (solicitação #{$processo->id}).",
            [
                'viability_request_id' => $processo->id,
                'protocol_number' => $processo->protocol_number,
                'status_anterior' => $status->value,
            ],
            $processo,
        );
    });

    echo "Gravado: status de tramitação em para_conclusao.\n";

    return 0;
})();

if ($autonomo) {
    exit($codigo);
}

return $codigo;
