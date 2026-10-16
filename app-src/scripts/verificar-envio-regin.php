<?php

declare(strict_types=1);

/**
 * Confere, pelo log de integração, quais processos REGIN já tiveram o parecer
 * enviado à JUCEB (fluxo expresso e análise). Só lê: não grava, não envia e
 * não reprocessa nada.
 *
 * Duas fontes, cruzadas por protocolo:
 *  1. Auditoria `integracoes` / evento `regin-parecer` (listener
 *     ComunicarResultadoRegin): result `sucesso` = a JUCEB respondeu
 *     RECEBIDO_SUCESSO; `bloqueado` = a transmissão não ocorreu.
 *  2. Chamadas de saída gravadas em `integration_calls` (integração `regin`,
 *     rota /recebe, codFuncao 110): outcome e status HTTP da tentativa.
 *
 * ENVIADO = qualquer uma das fontes registra sucesso. Processo com decisão
 * mas sem sucesso aparece como NÃO ENVIADO; sem decisão, SEM DECISÃO.
 *
 * No terminal do pod:
 *
 *   php /tmp/verificar-envio-regin.php
 *   php /tmp/verificar-envio-regin.php BAP2601852181 BAP2601852238
 *   php /tmp/verificar-envio-regin.php --so-enviados
 *
 * O último argumento que for um diretório é a raiz da aplicação
 * (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Enums\ViabilityRequestOrigin;
use App\Models\IntegrationCall;
use App\Models\ViabilityRequest;
use Illuminate\Contracts\Console\Kernel;
use Spatie\Activitylog\Models\Activity;

$argumentos = array_slice($argv, 1);
$soEnviados = in_array('--so-enviados', $argumentos, true);
$argumentos = array_values(array_filter($argumentos, static fn (string $a): bool => $a !== '--so-enviados'));

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

echo 'Versão '.config('app.version').' · '.config('app.url')."\n\n";

$processos = ViabilityRequest::query()
    ->where('origin', ViabilityRequestOrigin::Regin)
    ->whereNotNull('external_reference')
    ->when($protocolos !== [], fn ($q) => $q->whereIn('external_reference', $protocolos))
    ->with('decision')
    ->orderBy('id')
    ->get();

$porId = $processos->keyBy('id');

// 1. Auditoria do listener: o registro mais recente por processo decide.
$auditoria = [];

Activity::query()
    ->where('log_name', 'integracoes')
    ->where('event', 'regin-parecer')
    ->where('subject_type', (new ViabilityRequest)->getMorphClass())
    ->whereIn('subject_id', $porId->keys()->all())
    ->orderBy('id')
    ->get()
    ->each(function (Activity $registro) use (&$auditoria): void {
        $auditoria[$registro->subject_id][] = $registro;
    });

// 2. Chamadas de saída ao /recebe com o parecer (codFuncao 110).
$chamadas = [];

IntegrationCall::query()
    ->where('direction', 'saida')
    ->where('integration', 'regin')
    ->where('url', 'like', '%/recebe%')
    ->orderBy('id')
    ->chunkById(500, function ($lote) use (&$chamadas): void {
        foreach ($lote as $chamada) {
            $corpo = (string) $chamada->request_body;

            if (! preg_match('/codFuncao"?\s*[:=]\s*"?110\b/i', $corpo)) {
                continue;
            }

            if (preg_match('/"?protocolo"?\s*[:=]\s*"?([A-Z]{3}\d+)/i', $corpo, $m) === 1) {
                $chamadas[strtoupper($m[1])][] = $chamada;
            }
        }
    });

$enviados = [];
$naoEnviados = [];
$semDecisao = [];

foreach ($processos as $processo) {
    $protocolo = (string) $processo->external_reference;
    $registros = $auditoria[$processo->id] ?? [];
    $tentativas = $chamadas[strtoupper($protocolo)] ?? [];

    $sucessoAuditoria = collect($registros)->first(fn (Activity $r): bool => $r->result === 'sucesso');
    $sucessoChamada = collect($tentativas)->first(fn (IntegrationCall $c): bool => $c->outcome === 'sucesso');
    $ultimaAuditoria = end($registros) ?: null;
    $ultimaChamada = end($tentativas) ?: null;

    $linha = [
        'protocolo' => $protocolo,
        'status' => $processo->status->value,
        'decisao' => $processo->decision?->outcome?->value ?? '—',
        'auditoria' => $ultimaAuditoria ? $ultimaAuditoria->result.' '.$ultimaAuditoria->created_at : '—',
        'chamada' => $ultimaChamada ? $ultimaChamada->outcome.' HTTP '.($ultimaChamada->http_status ?? '?').' '.$ultimaChamada->created_at : '—',
        'tentativas' => count($tentativas),
    ];

    if ($sucessoAuditoria !== null || $sucessoChamada !== null) {
        $linha['enviado_em'] = (string) ($sucessoChamada?->created_at ?? $sucessoAuditoria?->created_at);
        $enviados[] = $linha;
    } elseif ($processo->decision === null) {
        $semDecisao[] = $linha;
    } else {
        $naoEnviados[] = $linha;
    }
}

$imprimir = static function (string $titulo, array $linhas): void {
    echo "== {$titulo} (".count($linhas).") ==\n";

    foreach ($linhas as $l) {
        echo sprintf(
            "%s  status=%s  decisao=%s  auditoria=[%s]  chamada=[%s]  tentativas=%d%s\n",
            $l['protocolo'],
            $l['status'],
            $l['decisao'],
            $l['auditoria'],
            $l['chamada'],
            $l['tentativas'],
            isset($l['enviado_em']) ? '  enviado_em='.$l['enviado_em'] : '',
        );
    }

    echo "\n";
};

$imprimir('ENVIADOS À JUCEB', $enviados);

if (! $soEnviados) {
    $imprimir('COM DECISÃO, MAS NÃO ENVIADOS (sem sucesso no log)', $naoEnviados);
    $imprimir('SEM DECISÃO (nada a enviar ainda)', $semDecisao);
}

echo 'Total de processos REGIN: '.$processos->count()
    .' · enviados: '.count($enviados)
    .' · com decisão e não enviados: '.count($naoEnviados)
    .' · sem decisão: '.count($semDecisao)."\n";
