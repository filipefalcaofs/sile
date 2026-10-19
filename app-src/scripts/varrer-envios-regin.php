<?php

declare(strict_types=1);

/**
 * Varredura somente leitura de todos os recebimentos REGIN gravados:
 *  - protocolos cujo envio não traz a pergunta que a planilha vigente exige para o CNAE;
 *  - protocolos com endereço do estabelecimento vazio ou área zerada.
 *
 * Gera /tmp/varredura-regin.json (linhas compactas) e imprime a contagem. O arquivo é
 * a entrada do relatório entregue à JUCEB. Nada é gravado no banco.
 *
 * No terminal do pod:
 *
 *   php /tmp/varrer-envios-regin.php
 *   php /tmp/varrer-envios-regin.php 2026-10-13        (a partir de uma data)
 *
 * O último argumento que for um diretório é a raiz da aplicação
 * (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Models\ReginRecebimento;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Tratamento\TratamentoRamoResolver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

if (! isset($app)) {
    $argumentos = array_slice($argv ?? [], 1);
    $raiz = null;

    foreach ($argumentos as $argumento) {
        if (is_dir($argumento)) {
            $raiz = $argumento;
        }
    }

    $raiz ??= is_dir('/opt/app-root/src') ? '/opt/app-root/src' : '/var/www/html';

    require $raiz.'/vendor/autoload.php';

    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $desde = null;

    foreach ($argumentos as $argumento) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $argumento) === 1) {
            $desde = $argumento;
        }
    }
}

$saida = $saida ?? '/tmp/varredura-regin.json';
$desde = $desde ?? null;

$protocolador = $app->make(ReginProcessoProtocolador::class);
$tratamento = $app->make(TratamentoRamoResolver::class);

$normalizar = static function (string $texto): string {
    if (str_contains($texto, 'Ã') || str_contains($texto, 'Â')) {
        $corrigido = @mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
        $texto = is_string($corrigido) && mb_check_encoding($corrigido, 'UTF-8') ? $corrigido : $texto;
    }

    $texto = Str::lower(Str::ascii($texto));
    $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

    return rtrim(trim($texto), '? ');
};

$lista = static fn (mixed $no): array => is_array($no) && $no !== [] ? (array_is_list($no) ? $no : [$no]) : [];

$query = ReginRecebimento::query()->orderBy('id');

if ($desde !== null) {
    $query->where('created_at', '>=', $desde.' 00:00:00');
}

$perguntas = [];
$envelopes = [];
$total = 0;
$semRowset = 0;
$periodo = ['de' => null, 'ate' => null];

$query->chunkById(100, function ($lote) use (
    &$perguntas, &$envelopes, &$total, &$semRowset, &$periodo,
    $protocolador, $tratamento, $normalizar, $lista,
) {
    foreach ($lote as $r) {
        $total++;
        $periodo['de'] ??= (string) $r->created_at;
        $periodo['ate'] = (string) $r->created_at;

        $corpo = is_array($r->corpo) ? $r->corpo : [];
        $rowset = is_array($corpo['rowset'] ?? null) ? $corpo['rowset'] : (is_array($corpo['ROWSET'] ?? null) ? $corpo['ROWSET'] : []);

        if ($rowset === []) {
            $semRowset++;

            continue;
        }

        $xml = is_string($r->envelope['xml'] ?? null) ? $r->envelope['xml'] : '';
        $evento = null;

        foreach ($lista($rowset['GROUPPSC_PROT_EVENTO_RFB']['PSC_PROT_EVENTO_RFB'] ?? []) as $ev) {
            $nome = trim((string) ($ev['PEV_NOME_EVENTO'] ?? ''));

            if ($nome !== '') {
                $evento = ($evento === null ? '' : $evento.' | ').$nome;
            }
        }

        $cnaes = [];

        foreach ($lista($rowset['GROUPRUC_ACTV_ECON']['RUC_ACTV_ECON'] ?? []) as $l) {
            $codigo = substr(preg_replace('/\D/', '', (string) ($l['RAE_TAE_COD_ACTVD'] ?? '')) ?? '', 0, 7);

            if (strlen($codigo) === 7) {
                $cnaes[] = $codigo;
            }
        }

        $cnaes = array_values(array_unique($cnaes));

        $base = [
            'protocolo' => $r->protocolo,
            'gerado_pela_juceb' => $r->data_geracao?->format('Y-m-d H:i:s'),
            'recebido_em' => $r->created_at?->format('Y-m-d H:i:s'),
            'xml_sha256' => $xml === '' ? null : hash('sha256', $xml),
            'evento' => $evento,
            'cnaes' => $cnaes,
        ];

        // Envelope incompleto: endereço e área do estabelecimento.
        $estab = is_array($rowset['RUC_ESTAB'] ?? null) ? $rowset['RUC_ESTAB'] : [];
        $logradouroVazio = trim((string) ($estab['RES_DIRECCION'] ?? '')) === '';
        $bairroVazio = trim((string) ($estab['RES_URBANIZACION'] ?? '')) === '';
        $areaTexto = trim((string) ($estab['RES_AREA'] ?? ''));
        $areaZerada = $areaTexto !== '' && (float) str_replace(',', '.', $areaTexto) <= 0;
        $inscricao = null;

        foreach ($lista($rowset['GROUPRUC_GEN_PROTOCOLO']['RUC_GEN_PROTOCOLO'] ?? []) as $l) {
            if ((string) ($l['RGP_TGE_COD_TIP_TAB'] ?? '') === '5' && trim((string) ($l['RGP_VALOR'] ?? '')) !== '') {
                $inscricao = trim((string) $l['RGP_VALOR']);
                break;
            }
        }

        if ($logradouroVazio && $bairroVazio) {
            $envelopes[] = $base + ['situacao' => 'Endereço vazio', 'inscricao' => $inscricao, 'veio_no_envio' => 'Logradouro e bairro vazios; CEP '.(trim((string) ($estab['RES_ZONA_POSTAL'] ?? '')) !== '' ? 'preenchido' : 'vazio')];
        }

        if ($areaZerada) {
            $envelopes[] = $base + ['situacao' => 'Área zerada', 'inscricao' => $inscricao, 'veio_no_envio' => 'RES_AREA = '.$areaTexto];
        }

        // Perguntas exigidas pela planilha vigente que o envio não traz.
        if ($cnaes === []) {
            continue;
        }

        $snapshot = $protocolador->snapshotDoRecebimento($r);
        $respostas = is_array($snapshot) ? ($snapshot['respostas_tratamento_por_cnae'] ?? []) : [];
        $recebidas = is_array($snapshot) ? ($snapshot['perguntas_regin'] ?? []) : [];

        $textoEnvio = $normalizar($xml.' '.json_encode($rowset, JSON_UNESCAPED_UNICODE));
        $faltantes = [];

        foreach ($cnaes as $cnae) {
            $exibidas = $tratamento->perguntasExibidas($cnae);
            $porNumero = [];

            foreach ($exibidas as $e) {
                $porNumero[(int) $e['numero']] = $e;
            }

            foreach ($exibidas as $e) {
                $numero = (int) $e['numero'];

                if (isset($respostas[$cnae][$numero])) {
                    continue;
                }

                if (is_array($e['depende'])) {
                    $mae = $porNumero[(int) $e['depende']['pergunta']] ?? null;
                    $respostaMae = $mae === null ? null : ($respostas[$cnae][(int) $mae['numero']] ?? null);

                    if ($respostaMae === null || (bool) $respostaMae !== (bool) $e['depende']['sim']) {
                        continue;
                    }
                }

                $titulo = trim((string) (preg_split('/\\\\n|\R/', (string) $e['texto'])[0] ?? ''));
                $busca = $normalizar($titulo);
                $ocorrencias = $busca === '' ? 0 : substr_count($textoEnvio, $busca);

                $faltantes[] = [
                    'cnae' => $cnae,
                    'numero' => $numero,
                    'enunciado' => $titulo,
                    'condicional' => is_array($e['depende']),
                    'ocorrencias' => $ocorrencias,
                ];
            }
        }

        if ($faltantes === []) {
            continue;
        }

        $perguntas[] = $base + [
            'situacao' => $recebidas === [] ? 'Envio sem nenhuma pergunta' : 'Pergunta exigida não enviada',
            'perguntas_recebidas' => count($recebidas),
            'faltantes' => $faltantes,
        ];
    }
});

file_put_contents($saida, json_encode([
    'gerado_em' => date('Y-m-d H:i:s'),
    'filtro_desde' => $desde,
    'periodo_recebimentos' => $periodo,
    'recebimentos_total' => $total,
    'sem_rowset' => $semRowset,
    'perguntas' => $perguntas,
    'envelopes' => $envelopes,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

echo "recebimentos: {$total} (de {$periodo['de']} até {$periodo['ate']}); sem rowset: {$semRowset}\n";
echo 'perguntas exigidas ausentes: '.count($perguntas).' protocolos ('.count(array_filter($perguntas, fn ($p) => $p['situacao'] === 'Envio sem nenhuma pergunta')).' sem nenhuma pergunta)'."\n";
echo 'ocorrência do enunciado achada no envio (revisar): '.count(array_filter($perguntas, fn ($p) => count(array_filter($p['faltantes'], fn ($f) => $f['ocorrencias'] > 0)) > 0))."\n";
echo 'envelopes incompletos: '.count($envelopes)."\n";
echo "arquivo: {$saida}\n";
