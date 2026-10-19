<?php

declare(strict_types=1);

/**
 * Devolve à fila do setor um processo assumido por engano: zera o responsável e
 * volta o status de análise para "Para distribuir". O setor não muda.
 *
 * Por padrão só mostra o que seria feito; a gravação exige --aplicar.
 * Recusa processo cuja análise já começou (Em análise em diante).
 *
 * No terminal do pod:
 *
 *   php /tmp/devolver-processo-fila.php NUMERO_DO_PROTOCOLO
 *   php /tmp/devolver-processo-fila.php NUMERO_DO_PROTOCOLO --aplicar
 *
 * O último argumento que for um diretório é a raiz da aplicação
 * (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Enums\AnalysisStatus;
use App\Models\ViabilityRequest;
use App\Services\Analise\AnalysisStatusStateMachine;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (! isset($app)) {
    $argumentos = array_slice($argv ?? [], 1);
    $raiz = null;
    $protocolo = null;
    $aplicar = false;

    foreach ($argumentos as $argumento) {
        if ($argumento === '--aplicar') {
            $aplicar = true;
        } elseif (is_dir($argumento)) {
            $raiz = $argumento;
        } elseif ($protocolo === null) {
            $protocolo = $argumento;
        }
    }

    $raiz ??= is_dir('/opt/app-root/src') ? '/opt/app-root/src' : '/var/www/html';

    require $raiz.'/vendor/autoload.php';

    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
}

$protocolo = $protocolo ?? null;
$aplicar = $aplicar ?? false;

if ($protocolo === null) {
    fwrite(STDERR, "Informe o número do protocolo.\n");
    exit(1);
}

$processos = ViabilityRequest::query()->where('protocol_number', $protocolo)->get();

if ($processos->count() !== 1) {
    fwrite(STDERR, "Esperava 1 processo para o protocolo {$protocolo}, encontrei {$processos->count()}.\n");
    exit(1);
}

$processo = $processos->first();
$status = $processo->analysis_status;
$responsavel = $processo->assigned_user_id;

echo "Processo #{$processo->id} protocolo {$processo->protocol_number}\n";
echo '  setor: '.($processo->sector_id ?? '(nenhum)')."\n";
echo '  responsável: '.($responsavel ?? '(nenhum)')."\n";
echo '  status: '.($status?->value ?? '(nulo)')."\n";

if ($responsavel === null) {
    fwrite(STDERR, "O processo já está sem responsável; nada a fazer.\n");
    exit(1);
}

if (! in_array($status, [null, AnalysisStatus::ParaDistribuir, AnalysisStatus::Encaminhado, AnalysisStatus::Analisar], true)) {
    fwrite(STDERR, 'Análise já iniciada (status '.$status->value."); não devolvo por script.\n");
    exit(1);
}

if (! $aplicar) {
    echo "Simulação: zeraria responsável e assigned_at e voltaria o status para para_distribuir. Rode com --aplicar para gravar.\n";
    exit(0);
}

DB::transaction(function () use ($app, $processo, $status, $responsavel): void {
    $processo->forceFill([
        'assigned_user_id' => null,
        'assigned_at' => null,
    ])->save();

    if ($status !== null && $status !== AnalysisStatus::ParaDistribuir) {
        $app->make(AnalysisStatusStateMachine::class)->transition(
            $processo,
            AnalysisStatus::ParaDistribuir,
            null,
            'Devolvido à fila do setor (assunção indevida).',
            true,
        );
    }

    $app->make(AuditService::class)->log(
        'analise',
        'devolver-fila',
        "Processo devolvido à fila do setor (solicitação #{$processo->id}).",
        [
            'viability_request_id' => $processo->id,
            'protocol_number' => $processo->protocol_number,
            'responsavel_anterior_id' => $responsavel,
            'status_anterior' => $status?->value,
        ],
        $processo,
    );
});

$processo->refresh();

echo 'Devolvido. responsável: '.($processo->assigned_user_id ?? '(nenhum)').'; status: '.($processo->analysis_status?->value ?? '(nulo)')."\n";
