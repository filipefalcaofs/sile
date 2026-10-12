<?php

declare(strict_types=1);

/**
 * Reconstrói o simulation_snapshot dos processos REGIN que protocolaram sem as
 * respostas da planilha (a JUCEB entrega as respostas no xml do envelope e o
 * json.ROWSET chega sem GROUPPERGUNTAS) e redecide no motor.
 *
 * Idempotente: pula processo que já tem resposta gravada, que já tem decisão,
 * ou que um analista assumiu. Reconstroi a pré-análise (a revisão 1 vazia da
 * ficha é refeita pelo PreAnaliseService). Não altera o recebimento gravado.
 *
 * No terminal do pod:
 *
 *   php /tmp/reprocessar-respostas-regin.php --dry-run
 *   php /tmp/reprocessar-respostas-regin.php
 *   php /tmp/reprocessar-respostas-regin.php BAP2601473733 BAP2601473725
 *
 * O último argumento que for um diretório é a raiz da aplicação
 * (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Enums\AnalysisRecordStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Analise\PreAnaliseService;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Regin\ReginProcessoProtocolador;
use Illuminate\Contracts\Console\Kernel;

$argumentos = array_slice($argv, 1);
$simulacao = in_array('--dry-run', $argumentos, true);
$argumentos = array_values(array_filter($argumentos, static fn (string $a): bool => $a !== '--dry-run'));

$raiz = null;
$protocolos = [];

foreach ($argumentos as $argumento) {
    if (is_dir($argumento)) {
        $raiz = $argumento;
    } else {
        $protocolos[] = $argumento;
    }
}

$raiz ??= is_dir('/opt/app-root/src') ? '/opt/app-root/src' : '/var/www/html';

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$protocolador = $app->make(ReginProcessoProtocolador::class);
$expresso = $app->make(FluxoExpressoService::class);
$preAnalise = $app->make(PreAnaliseService::class);

$recebimentos = ReginRecebimento::query()
    ->when($protocolos !== [], fn ($q) => $q->whereIn('protocolo', $protocolos))
    ->whereNotNull('viability_request_id')
    ->orderBy('id')
    ->get();

echo 'Versão '.config('app.version').' · '.config('app.url').($simulacao ? ' · SIMULAÇÃO' : '')."\n";
echo "recebimentos com processo: {$recebimentos->count()}\n\n";

$semResposta = 0;

foreach ($recebimentos as $recebimento) {
    $processo = ViabilityRequest::query()->find($recebimento->viability_request_id);

    if ($processo === null) {
        continue;
    }

    $snapshotAtual = is_array($processo->simulation_snapshot) ? $processo->simulation_snapshot : [];
    $respostasAtuais = $snapshotAtual['respostas_tratamento_por_cnae'] ?? [];

    if (is_array($respostasAtuais) && $respostasAtuais !== []) {
        // Reprocesso já rodou: repara a ficha se a revisão 1 ficou da passada
        // sem respostas (idempotência do PreAnaliseService devolveu a antiga).
        if ($simulacao) {
            if (fichaIntocada($processo) !== null) {
                echo "{$recebimento->protocolo}: recriaria a revisão 1 da ficha\n";
            }

            continue;
        }

        try {
            $revisao = fichaIntocada($processo);

            if ($revisao !== null) {
                $revisao->delete();
                $processo->unsetRelation('analysisRecords');
                $processo->unsetRelation('currentAnalysisRecord');
                $preAnalise->preAnalisar($processo->fresh() ?? $processo);

                echo "{$recebimento->protocolo}: revisão 1 da ficha recriada · {$processo->fresh()->status->value}\n";
            }
        } catch (Throwable $erro) {
            echo "{$recebimento->protocolo}: falhou ao reparar a ficha · ".get_class($erro).': '.$erro->getMessage()."\n";
        }

        continue;
    }

    $semResposta++;
    $protocolo = $recebimento->protocolo;

    $snapshot = $protocolador->snapshotDoRecebimento($recebimento);
    $respostasNovas = is_array($snapshot) ? ($snapshot['respostas_tratamento_por_cnae'] ?? []) : [];

    if (! is_array($respostasNovas) || $respostasNovas === []) {
        echo "{$protocolo}: envelope sem resposta utilizável (xml ausente ou sem pergunta da planilha)\n";

        continue;
    }

    $nRespostas = 0;

    foreach ($respostasNovas as $mapa) {
        $nRespostas += is_array($mapa) ? count($mapa) : 0;
    }

    if ($simulacao) {
        echo "{$protocolo}: reconstruiria {$nRespostas} resposta(s) e redecidiria\n";

        continue;
    }

    try {
        $processo->forceFill(['simulation_snapshot' => $snapshot])->save();

        if ($processo->status === ViabilityRequestStatus::EmAnalise) {
            $preAnalise->preAnalisar($processo->fresh() ?? $processo);
        }

        $resultado = $expresso->decide($processo->fresh() ?? $processo);
        $motivo = $resultado->reason ?? $resultado->status->value;

        echo "{$protocolo}: {$nRespostas} resposta(s) · {$processo->fresh()->status->value} · {$motivo}\n";
    } catch (Throwable $erro) {
        echo "{$protocolo}: falhou · ".get_class($erro).': '.$erro->getMessage()."\n";
    }
}

echo "\nprocessos sem resposta gravada: {$semResposta}\n";

/**
 * Revisão 1 em rascunho que o analista nunca tocou: pode ser recriada pelo
 * motor. Revisão finalizada, assumida ou com parecer é trabalho humano —
 * intocável.
 */
function fichaIntocada(ViabilityRequest $processo): ?AnalysisRecord
{
    $revisao = $processo->analysisRecords()->where('revision', 1)->first();

    if ($revisao === null) {
        return null;
    }

    $parecer = trim((string) ($revisao->parecer ?? ''));

    $intocada = $revisao->status === AnalysisRecordStatus::Rascunho
        && $revisao->analyst_user_id === null
        && $revisao->finalized_at === null
        && $parecer === '';

    return $intocada ? $revisao : null;
}
