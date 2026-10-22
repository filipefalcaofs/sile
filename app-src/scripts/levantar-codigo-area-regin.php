<?php

declare(strict_types=1);

/**
 * Lista os pareceres que a JUCEB aceitou com CODIGO_AREA diferente da área
 * configurada (70, viabilidade de Salvador). Esses protocolos ficaram
 * pendentes no REGIN e precisam ser reenviados. Só lê: não grava, não envia.
 *
 * Fonte principal: chamadas de saída ao /recebe com codFuncao 110 gravadas em
 * `integration_calls`. O envio mais recente com sucesso decide a área de cada
 * protocolo. Processo com sucesso na auditoria `regin-parecer` mas sem a
 * chamada gravada entra como "área desconhecida".
 *
 * No terminal do pod:
 *
 *   php /tmp/levantar-codigo-area-regin.php
 *   php /tmp/levantar-codigo-area-regin.php BAB2601852710
 *
 * O último argumento que for um diretório é a raiz da aplicação
 * (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Enums\ViabilityRequestOrigin;
use App\Models\IntegrationCall;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginIntegrationSettings;
use Illuminate\Contracts\Console\Kernel;
use Spatie\Activitylog\Models\Activity;

$argumentos ??= array_slice($argv ?? [], 1);
$raiz = null;
$filtro = [];

foreach ($argumentos as $argumento) {
    if (is_dir($argumento)) {
        $raiz = $argumento;
    } else {
        $filtro[] = strtoupper($argumento);
    }
}

if (! isset($app)) {
    $raiz ??= is_dir('/opt/app-root/src') ? '/opt/app-root/src' : '/var/www/html';

    require $raiz.'/vendor/autoload.php';

    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
}

$areaCorreta = $app->make(ReginIntegrationSettings::class)->codigoArea();

echo 'Versão '.config('app.version').' · '.config('app.url')." · área configurada: {$areaCorreta}\n\n";

// Envio mais recente com sucesso por protocolo, com a área que foi no corpo.
$envios = [];

IntegrationCall::query()
    ->where('direction', 'saida')
    ->where('integration', 'regin')
    ->where('url', 'like', '%/recebe%')
    ->where('outcome', 'sucesso')
    ->orderBy('id')
    ->chunkById(500, function ($lote) use (&$envios, $filtro): void {
        foreach ($lote as $chamada) {
            $corpo = json_decode((string) $chamada->request_body, true);

            if (! is_array($corpo) || (int) ($corpo['codFuncao'] ?? 0) !== 110) {
                continue;
            }

            $protocolo = strtoupper(trim((string) ($corpo['protocolo'] ?? '')));

            if ($protocolo === '' || ($filtro !== [] && ! in_array($protocolo, $filtro, true))) {
                continue;
            }

            $area = $corpo['dadosProcesso']['ANALISES']['AREA'][0] ?? [];

            $envios[$protocolo] = [
                'area' => array_key_exists('CODIGO_AREA', $area) ? (string) $area['CODIGO_AREA'] : 'ausente',
                'status_analise' => (string) ($area['STATUS_ANALISE'] ?? '?'),
                'enviado_em' => (string) $chamada->created_at,
            ];
        }
    });

// Sucesso na auditoria sem chamada gravada: área não verificável.
$processos = ViabilityRequest::query()
    ->where('origin', ViabilityRequestOrigin::Regin)
    ->whereNotNull('external_reference')
    ->when($filtro !== [], fn ($q) => $q->whereIn('external_reference', $filtro))
    ->get(['id', 'external_reference']);

$semCorpo = [];

Activity::query()
    ->where('log_name', 'integracoes')
    ->where('event', 'regin-parecer')
    ->where('subject_type', (new ViabilityRequest)->getMorphClass())
    ->where('result', 'sucesso')
    ->whereIn('subject_id', $processos->pluck('id')->all())
    ->orderBy('id')
    ->get()
    ->each(function (Activity $registro) use (&$semCorpo, $processos, $envios): void {
        $protocolo = strtoupper((string) $processos->firstWhere('id', $registro->subject_id)?->external_reference);

        if ($protocolo !== '' && ! isset($envios[$protocolo])) {
            $semCorpo[$protocolo] = (string) $registro->created_at;
        }
    });

$reenviar = array_filter($envios, fn (array $e): bool => $e['area'] !== (string) $areaCorreta);
$corretos = array_diff_key($envios, $reenviar);

echo '== A REENVIAR: aceitos com área diferente de '.$areaCorreta.' ('.count($reenviar).") ==\n";

foreach ($reenviar as $protocolo => $e) {
    echo sprintf("%s  CODIGO_AREA=%s  STATUS_ANALISE=%s  enviado_em=%s\n", $protocolo, $e['area'], $e['status_analise'], $e['enviado_em']);
}

echo "\n== SUCESSO NA AUDITORIA SEM CORPO GRAVADO: área desconhecida (".count($semCorpo).") ==\n";

foreach ($semCorpo as $protocolo => $quando) {
    echo "{$protocolo}  auditoria_sucesso={$quando}\n";
}

echo "\n== JÁ ENVIADOS COM A ÁREA {$areaCorreta} (".count($corretos).") ==\n";

foreach ($corretos as $protocolo => $e) {
    echo "{$protocolo}  enviado_em={$e['enviado_em']}\n";
}

echo "\nA reenviar: ".count($reenviar).' · área desconhecida: '.count($semCorpo).' · corretos: '.count($corretos)."\n";
