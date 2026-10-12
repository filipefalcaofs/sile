<?php

declare(strict_types=1);

/**
 * Reconstrói o simulation_snapshot dos processos REGIN que protocolaram sem as
 * respostas da planilha (a JUCEB entrega as respostas no xml do envelope e o
 * json.ROWSET chega sem GROUPPERGUNTAS) e redecide no motor.
 *
 * Ordem por processo: grava as respostas, redecide e só então acerta a ficha.
 * - Continua em análise: a revisão 1 intocada é refeita com o motivo atual.
 * - O motor decidiu: a revisão 1 intocada e as quedas da passada sem resposta
 *   saem, como num processo que o expresso decide de primeira.
 *
 * Idempotente: processo que um analista assumiu, ficha com trabalho humano e
 * decisão de analista não são tocados. Não altera o recebimento gravado.
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

    $protocolo = $recebimento->protocolo;
    $snapshotAtual = is_array($processo->simulation_snapshot) ? $processo->simulation_snapshot : [];
    $respostasAtuais = $snapshotAtual['respostas_tratamento_por_cnae'] ?? [];
    $jaTinhaResposta = is_array($respostasAtuais) && $respostasAtuais !== [];
    $snapshot = null;
    $nRespostas = 0;

    if (! $jaTinhaResposta) {
        $semResposta++;
        $snapshot = $protocolador->snapshotDoRecebimento($recebimento);
        $respostasNovas = is_array($snapshot) ? ($snapshot['respostas_tratamento_por_cnae'] ?? []) : [];

        if (! is_array($respostasNovas) || $respostasNovas === []) {
            echo "{$protocolo}: envelope sem resposta utilizável (xml ausente ou sem pergunta da planilha)\n";

            continue;
        }

        foreach ($respostasNovas as $mapa) {
            $nRespostas += is_array($mapa) ? count($mapa) : 0;
        }
    }

    $redecidir = ! $jaTinhaResposta || $expresso->podeReavaliar($processo);

    if ($simulacao) {
        $acoes = [];

        if (! $jaTinhaResposta) {
            $acoes[] = "reconstruiria {$nRespostas} resposta(s)";
        }

        if ($redecidir) {
            $acoes[] = 'redecidiria';
        }

        if (fichaIntocada($processo) !== null) {
            $acoes[] = 'refaria ou removeria a revisão 1 da ficha';
        }

        if (decididoPeloMotor($processo) && $processo->expressoQuedas()->exists()) {
            $acoes[] = 'removeria '.$processo->expressoQuedas()->count().' queda(s) da passada sem resposta';
        }

        if ($acoes !== []) {
            echo "{$protocolo}: ".implode(' · ', $acoes)."\n";
        }

        continue;
    }

    try {
        if ($snapshot !== null) {
            $processo->forceFill(['simulation_snapshot' => $snapshot])->save();
        }

        $motivo = null;

        if ($redecidir) {
            $resultado = $expresso->decide($processo->fresh() ?? $processo);
            $motivo = $resultado->reason ?? $resultado->status->value;
        }

        $ficha = acertarFicha($processo->fresh() ?? $processo, $preAnalise);

        $partes = array_filter([
            $nRespostas > 0 ? "{$nRespostas} resposta(s)" : null,
            $processo->fresh()->status->value,
            $motivo,
            $ficha,
        ]);

        if ($nRespostas > 0 || $ficha !== null) {
            echo "{$protocolo}: ".implode(' · ', $partes)."\n";
        }
    } catch (Throwable $erro) {
        echo "{$protocolo}: falhou · ".get_class($erro).': '.$erro->getMessage()."\n";
    }
}

echo "\nprocessos sem resposta gravada: {$semResposta}\n";

/**
 * Deixa a ficha e as quedas coerentes com o estado que o motor acabou de
 * decidir. Devolve a descrição do que mudou, ou null.
 */
function acertarFicha(ViabilityRequest $processo, PreAnaliseService $preAnalise): ?string
{
    $revisao = fichaIntocada($processo);

    if ($processo->status === ViabilityRequestStatus::EmAnalise && $processo->assigned_user_id === null) {
        $revisao?->delete();
        $processo->unsetRelation('analysisRecords');
        $processo->unsetRelation('currentAnalysisRecord');
        $preAnalise->preAnalisar($processo);

        return $revisao !== null ? 'revisão 1 da ficha refeita' : null;
    }

    if (! decididoPeloMotor($processo)) {
        return null;
    }

    $quedas = $processo->expressoQuedas()->count();
    $processo->expressoQuedas()->delete();
    $revisao?->delete();

    if ($revisao === null && $quedas === 0) {
        return null;
    }

    return "ficha e {$quedas} queda(s) da passada sem resposta removidas";
}

/**
 * Decisão emitida pelo motor sem analista: nenhum usuário decidiu nem assumiu.
 */
function decididoPeloMotor(ViabilityRequest $processo): bool
{
    $decisao = $processo->decision()->first();

    return $decisao !== null
        && $decisao->decided_by_user_id === null
        && $processo->assigned_user_id === null;
}

/**
 * Revisão 1 em rascunho que o analista nunca tocou: pode ser recriada pelo
 * motor. Revisão finalizada, assumida ou com parecer é trabalho humano —
 * intocável.
 */
function fichaIntocada(ViabilityRequest $processo): ?AnalysisRecord
{
    $revisoes = $processo->analysisRecords()->count();
    $revisao = $processo->analysisRecords()->where('revision', 1)->first();

    if ($revisao === null || $revisoes > 1) {
        return null;
    }

    $parecer = trim((string) ($revisao->parecer ?? ''));

    $intocada = $revisao->status === AnalysisRecordStatus::Rascunho
        && $revisao->analyst_user_id === null
        && $revisao->finalized_at === null
        && $parecer === '';

    return $intocada ? $revisao : null;
}
