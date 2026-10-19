<?php

declare(strict_types=1);

/**
 * Devolve processos à situação "Para distribuir" na Caixa de vistoria: tira o
 * vistoriador do processo e, quando a ficha de vistoria foi aberta por ele e
 * está em preenchimento, remove essa ficha para que o próximo vistoriador abra
 * a dele. O status de tramitação continua "Vistoriar" e o setor não muda.
 *
 * Recusa ficha concluída. Ficha em preenchimento só é removida com
 * --remover-ficha, e nunca quando já tem anexos.
 *
 * Por padrão só mostra o que seria feito; a gravação exige --aplicar.
 *
 * No terminal do pod:
 *
 *   php /tmp/devolver-vistoria-para-distribuir.php BAP2601872682 BAP2603854348
 *   php /tmp/devolver-vistoria-para-distribuir.php BAP2601872682 BAP2603854348 --remover-ficha --aplicar
 *
 * Aceita números de BAP ou de protocolo. O último argumento que for um
 * diretório é a raiz da aplicação (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Enums\AnalysisStatus;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$autonomo = ! isset($app);

if ($autonomo) {
    $argumentos = array_slice($argv ?? [], 1);
    $raiz = null;
    $numeros = [];
    $aplicar = false;
    $removerFicha = false;

    foreach ($argumentos as $argumento) {
        if ($argumento === '--aplicar') {
            $aplicar = true;
        } elseif ($argumento === '--remover-ficha') {
            $removerFicha = true;
        } elseif (is_dir($argumento)) {
            $raiz = $argumento;
        } else {
            $numeros[] = $argumento;
        }
    }

    $raiz ??= is_dir('/opt/app-root/src') ? '/opt/app-root/src' : '/var/www/html';

    require $raiz.'/vendor/autoload.php';

    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
}

$numeros = $numeros ?? [];
$aplicar = $aplicar ?? false;
$removerFicha = $removerFicha ?? false;

$codigo = (function () use ($app, $numeros, $aplicar, $removerFicha): int {
    if ($numeros === []) {
        fwrite(STDERR, "Informe ao menos um número de BAP ou de protocolo.\n");

        return 1;
    }

    $falhou = false;

    foreach ($numeros as $numero) {
        $processos = ViabilityRequest::query()
            ->where(fn ($q) => $q->where('external_reference', $numero)->orWhere('protocol_number', $numero))
            ->get();

        if ($processos->count() !== 1) {
            fwrite(STDERR, "{$numero}: esperava 1 processo, encontrei {$processos->count()}.\n");
            $falhou = true;

            continue;
        }

        $processo = $processos->first();
        $ficha = Inspection::query()->where('viability_request_id', $processo->id)->latest('id')->first();

        echo "Processo #{$processo->id} protocolo {$processo->protocol_number} BAP ".($processo->external_reference ?? '(sem BAP)')."\n";
        echo '  status de tramitação: '.($processo->analysis_status?->value ?? '(nulo)')."\n";
        echo '  responsável: '.($processo->assigned_user_id ?? '(nenhum)')."\n";
        echo '  ficha de vistoria: '.($ficha === null ? 'não há' : $ficha->status->value." (vistoriador {$ficha->vistoriador_user_id})")."\n";

        if ($processo->analysis_status !== AnalysisStatus::Vistoriar) {
            fwrite(STDERR, "{$numero}: o processo não está em \"Vistoriar\"; não mexo por script.\n");
            $falhou = true;

            continue;
        }

        if ($ficha !== null && $ficha->status === InspectionStatus::Concluida) {
            fwrite(STDERR, "{$numero}: a ficha de vistoria já foi concluída; não desfaço por script.\n");
            $falhou = true;

            continue;
        }

        if ($ficha !== null && ! $removerFicha) {
            fwrite(STDERR, "{$numero}: há ficha em preenchimento; rode com --remover-ficha para removê-la.\n");
            $falhou = true;

            continue;
        }

        if ($ficha !== null && $ficha->attachments()->exists()) {
            fwrite(STDERR, "{$numero}: a ficha já tem anexos; não removo por script.\n");
            $falhou = true;

            continue;
        }

        if (! $aplicar) {
            echo '  Simulação: tiraria o responsável'.($ficha === null ? '' : ' e removeria a ficha em preenchimento').". Rode com --aplicar para gravar.\n";

            continue;
        }

        DB::transaction(function () use ($app, $processo, $ficha): void {
            $anterior = $processo->assigned_user_id;

            if ($ficha !== null) {
                $ficha->delete();
            }

            $processo->forceFill(['assigned_user_id' => null, 'assigned_at' => null])->save();

            $app->make(AuditService::class)->log(
                'vistoria',
                'devolver-para-distribuir',
                "Processo devolvido para distribuir na Caixa de vistoria (solicitação #{$processo->id}).",
                [
                    'viability_request_id' => $processo->id,
                    'protocol_number' => $processo->protocol_number,
                    'responsavel_anterior_id' => $anterior,
                    'ficha_removida' => $ficha !== null,
                ],
                $processo,
            );
        });

        echo "  Gravado: processo sem responsável, em Para distribuir na Caixa de vistoria.\n";
    }

    if ($falhou) {
        return 1;
    }

    return 0;
})();

if ($autonomo) {
    exit($codigo);
}

return $codigo;
