<?php

declare(strict_types=1);

/**
 * Abre o processo dos recebimentos REGIN que ficaram gravados sem processo
 * porque a JUCEB enviou o corpo em json.ROWSET (maiúsculo) e a versão 0.30.0
 * só lê json.rowset.
 *
 * Não altera o recebimento gravado: o corpo normalizado existe só em memória.
 * Pula o protocolo que já tem processo. Mostra a exceção, se houver.
 *
 * No terminal do pod:
 *
 *   php /tmp/reprocessar-recebimento-regin.php --dry-run
 *   php /tmp/reprocessar-recebimento-regin.php
 *   php /tmp/reprocessar-recebimento-regin.php BAP2601471812 BAP2601471650
 *
 * O último argumento que for um diretório é a raiz da aplicação
 * (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
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
$protocolos = $protocolos === [] ? ['BAP2601471812', 'BAP2601471650'] : $protocolos;

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo 'Versão '.config('app.version').' · '.config('app.url').($simulacao ? ' · SIMULAÇÃO' : '')."\n";

foreach ($protocolos as $protocolo) {
    $recebimento = ReginRecebimento::query()->where('protocolo', $protocolo)->first();

    if ($recebimento === null) {
        echo "{$protocolo}: sem recebimento gravado\n";

        continue;
    }

    $existente = ViabilityRequest::query()->where('external_reference', $protocolo)->first();

    if ($existente !== null) {
        echo "{$protocolo}: já tem processo {$existente->id}\n";

        continue;
    }

    $corpo = is_array($recebimento->corpo) ? $recebimento->corpo : [];

    if (! is_array($corpo['rowset'] ?? null) && is_array($corpo['ROWSET'] ?? null)) {
        $corpo['rowset'] = $corpo['ROWSET'];
    }

    if (! is_array($corpo['rowset'] ?? null)) {
        echo "{$protocolo}: corpo sem rowset nem ROWSET\n";

        continue;
    }

    if ($simulacao) {
        echo "{$protocolo}: corpo legível; abriria o processo\n";

        continue;
    }

    $recebimento->corpo = $corpo;

    try {
        $processo = $app->make(ReginProcessoProtocolador::class)->protocolar($recebimento);
    } catch (Throwable $erro) {
        echo "{$protocolo}: falhou · ".get_class($erro).': '.$erro->getMessage()."\n";

        continue;
    }

    if ($processo === null) {
        echo "{$protocolo}: dados insuficientes (CNAE, área ou endereço)\n";

        continue;
    }

    $recebimento->setRawAttributes(array_merge(
        $recebimento->getOriginal(),
        ['viability_request_id' => $processo->id],
    ));
    ReginRecebimento::query()->whereKey($recebimento->id)->update(['viability_request_id' => $processo->id]);

    echo "{$protocolo}: processo {$processo->id} · {$processo->protocol_number} · {$processo->status->value}\n";
}
