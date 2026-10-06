<?php

declare(strict_types=1);

/**
 * Confere, no banco do pod, se a JUCEB consumiu o POST /api_integracao/recebe
 * e se isso apareceria em Auditoria → Log de integrações (REGIN / JUCEB).
 *
 * Só lê. Não grava, não apaga e não chama a API.
 *
 * No terminal do pod, a partir da pasta da aplicação:
 *
 *   php scripts/verificar-recebimento-regin.php
 *   php scripts/verificar-recebimento-regin.php BAP2601471812 BAP2601471650
 *
 * Se o arquivo ainda não estiver na imagem, grave em /tmp e rode de qualquer pasta:
 *
 *   php /tmp/verificar-recebimento-regin.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo <<<'TXT'
Uso no terminal do pod:

  php scripts/verificar-recebimento-regin.php
  php scripts/verificar-recebimento-regin.php BAP2601471812 BAP2601471650
  php /tmp/verificar-recebimento-regin.php /opt/app-root/src

Sem protocolos, confere BAP2601471812 e BAP2601471650.
Só leitura.

TXT;
    exit(0);
}

[$raiz, $protocolos] = argumentos($argv);

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$conexao = (string) config('database.default');
$banco = (string) config("database.connections.{$conexao}.database");
$host = (string) config("database.connections.{$conexao}.host");
$url = (string) config('app.url');
$versao = (string) config('app.version');

echo "Versão: {$versao}\n";
echo 'APP_ENV: '.config('app.env')."\n";
echo "APP_URL: {$url}\n";
echo "Banco: {$banco} @ {$host}\n";

if (! str_contains(strtolower($url), 'hml') && ! str_contains(strtolower($banco), 'hml') && ! str_contains(strtolower($banco), 'homolog')) {
    echo "Aviso: esta URL não parece homologação. O script continua porque só lê.\n";
}

$temRecebimento = Schema::hasTable('regin_recebimentos');
$temProcesso = Schema::hasTable('viability_requests');
$temTrilha = Schema::hasTable('activity_log');
$temLog = Schema::hasTable('integration_calls');

echo "\nTabelas:\n";
echo '  regin_recebimentos: '.($temRecebimento ? 'presente' : 'ausente')."\n";
echo '  viability_requests: '.($temProcesso ? 'presente' : 'ausente')."\n";
echo '  activity_log: '.($temTrilha ? 'presente' : 'ausente')."\n";
echo '  integration_calls: '.($temLog ? 'presente' : 'ausente')."\n";

if (! $temLog) {
    echo "\nA tela Auditoria → Log de integrações grava em integration_calls.\n";
    echo "Essa tabela não existe neste banco. A versão em execução é anterior à 0.40.0,\n";
    echo "então um POST bem-sucedido não aparece nessa tela, mesmo que o recebimento tenha sido gravado.\n";
}

foreach ($protocolos as $protocolo) {
    echo "\n".str_repeat('─', 64)."\n";
    echo "Protocolo {$protocolo}\n";

    $recebimento = $temRecebimento ? recebimento($protocolo) : null;
    $processo = $temProcesso ? processo($protocolo, $recebimento) : null;
    $trilhas = $temTrilha ? trilhas($protocolo, $recebimento) : [];
    $chamadas = $temLog ? chamadas($protocolo) : [];

    if ($recebimento === null) {
        echo "  Recebimento: nenhum registro em regin_recebimentos.\n";
    } else {
        echo sprintf(
            "  Recebimento: id %s em %s%s\n",
            $recebimento->id,
            $recebimento->created_at,
            isset($recebimento->viability_request_id) && $recebimento->viability_request_id !== null
                ? ", processo {$recebimento->viability_request_id}"
                : '',
        );
    }

    if ($processo === null) {
        echo "  Processo: nenhum com este protocolo em external_reference.\n";
    } else {
        echo sprintf(
            "  Processo: id %s, situação %s, origem %s, em %s\n",
            $processo->id,
            $processo->status ?? '—',
            $processo->origin ?? '—',
            $processo->created_at,
        );
    }

    if (! $temTrilha) {
        echo "  Trilha (activity_log): tabela ausente.\n";
    } elseif ($trilhas === []) {
        echo "  Trilha (activity_log): nenhum evento regin/recebe para este protocolo.\n";
    } else {
        foreach ($trilhas as $trilha) {
            $dados = json_decode((string) $trilha->properties, true);
            $codigo = is_array($dados) ? ($dados['codigo'] ?? '—') : '—';
            echo sprintf(
                "  Trilha: id %s em %s, evento %s, código %s, resultado %s\n",
                $trilha->id,
                $trilha->created_at,
                $trilha->event ?? '—',
                $codigo,
                $trilha->result ?? '—',
            );
        }
    }

    if (! $temLog) {
        echo "  Log de integrações: não há tabela. A tela não registrou este POST.\n";
    } elseif ($chamadas === []) {
        echo "  Log de integrações: nenhum POST /api_integracao/recebe com este protocolo no corpo.\n";
    } else {
        foreach ($chamadas as $chamada) {
            $resposta = preg_replace('/\s+/', ' ', trim((string) $chamada->response_body)) ?? '';
            echo sprintf(
                "  Log de integrações: id %s em %s, %s %s, HTTP %s, %s, resposta [%s]\n",
                $chamada->id,
                $chamada->created_at,
                $chamada->method,
                $chamada->url,
                $chamada->http_status ?? '—',
                $chamada->outcome,
                mb_substr($resposta, 0, 40),
            );
        }
    }

    echo '  Conclusão: '.conclusao($recebimento !== null, $processo !== null, $trilhas !== [], $temLog, $chamadas !== []).".\n";
}

echo "\n".str_repeat('─', 64)."\n";
echo "Últimos recebimentos gravados:\n";
if (! $temRecebimento) {
    echo "  tabela ausente\n";
} else {
    $recentes = DB::table('regin_recebimentos')->orderByDesc('id')->limit(8)->get(['id', 'protocolo', 'created_at']);
    if ($recentes->isEmpty()) {
        echo "  nenhum\n";
    }
    foreach ($recentes as $recente) {
        echo "  {$recente->created_at}  {$recente->protocolo}  (id {$recente->id})\n";
    }
}

if ($temLog) {
    echo "\nÚltimos POST /api_integracao/recebe no log de integrações:\n";
    $posts = DB::table('integration_calls')
        ->where('method', 'POST')
        ->where(function ($query): void {
            $query->where('integration', 'regin')
                ->orWhere('url', 'like', '%api_integracao/recebe%');
        })
        ->orderByDesc('id')
        ->limit(8)
        ->get(['id', 'created_at', 'http_status', 'outcome', 'url']);

    if ($posts->isEmpty()) {
        echo "  nenhum\n";
    }
    foreach ($posts as $post) {
        echo "  {$post->created_at}  HTTP {$post->http_status}  {$post->outcome}  id {$post->id}\n";
        echo "    {$post->url}\n";
    }
}

echo "\n";

/**
 * @param  list<string>  $argv
 * @return array{0: string, 1: list<string>}
 */
function argumentos(array $argv): array
{
    $raiz = null;
    $protocolos = [];

    foreach (array_slice($argv, 1) as $argumento) {
        if (str_starts_with($argumento, '-')) {
            continue;
        }

        if ($raiz === null && (is_dir($argumento) || str_contains($argumento, '/'))) {
            $raiz = $argumento;

            continue;
        }

        $protocolos[] = strtoupper(trim($argumento));
    }

    if ($protocolos === []) {
        $protocolos = ['BAP2601471812', 'BAP2601471650'];
    }

    return [raizDaAplicacao($raiz), $protocolos];
}

function raizDaAplicacao(?string $informada): string
{
    $candidatas = array_filter([
        $informada,
        getcwd() ?: null,
        '/var/www/html',
        '/opt/app-root/src',
    ]);

    foreach ($candidatas as $candidata) {
        if (is_file($candidata.'/artisan') && is_file($candidata.'/vendor/autoload.php')) {
            return $candidata;
        }
    }

    fwrite(STDERR, "Não achei a aplicação Laravel. Rode na pasta do pod ou passe o caminho: php verificar-recebimento-regin.php /opt/app-root/src\n");
    exit(1);
}

function recebimento(string $protocolo): ?object
{
    $colunas = ['id', 'protocolo', 'created_at'];

    if (Schema::hasColumn('regin_recebimentos', 'viability_request_id')) {
        $colunas[] = 'viability_request_id';
    }

    return DB::table('regin_recebimentos')->where('protocolo', $protocolo)->first($colunas);
}

function processo(string $protocolo, ?object $recebimento): ?object
{
    $colunas = array_values(array_filter(
        ['id', 'status', 'origin', 'external_reference', 'created_at'],
        fn (string $coluna): bool => Schema::hasColumn('viability_requests', $coluna),
    ));

    $porProtocolo = DB::table('viability_requests')->where('external_reference', $protocolo)->first($colunas);

    if ($porProtocolo !== null) {
        return $porProtocolo;
    }

    $processoId = $recebimento->viability_request_id ?? null;

    if ($processoId === null) {
        return null;
    }

    return DB::table('viability_requests')->where('id', $processoId)->first($colunas);
}

/**
 * @return list<object>
 */
function trilhas(string $protocolo, ?object $recebimento): array
{
    $consulta = DB::table('activity_log')->where('log_name', 'regin')->orderByDesc('id');

    $consulta->where(function ($filtro) use ($protocolo, $recebimento): void {
        $filtro->whereRaw(textoContem('properties'), ['%'.$protocolo.'%']);

        if ($recebimento !== null && Schema::hasColumn('activity_log', 'subject_id')) {
            $filtro->orWhere(function ($peloRecebimento) use ($recebimento): void {
                $peloRecebimento
                    ->where('subject_id', $recebimento->id)
                    ->where('subject_type', 'like', '%ReginRecebimento');
            });
        }
    });

    return $consulta->limit(5)->get()->all();
}

/**
 * @return list<object>
 */
function chamadas(string $protocolo): array
{
    return DB::table('integration_calls')
        ->where('method', 'POST')
        ->where('url', 'like', '%api_integracao/recebe%')
        ->whereRaw(textoContem('request_body'), ['%'.$protocolo.'%'])
        ->orderByDesc('id')
        ->limit(5)
        ->get()
        ->all();
}

function textoContem(string $coluna): string
{
    return match (DB::connection()->getDriverName()) {
        'pgsql' => "{$coluna}::text ilike ?",
        default => "{$coluna} like ?",
    };
}

function conclusao(bool $recebeu, bool $processo, bool $trilha, bool $temLog, bool $chamada): string
{
    if (! $recebeu && ! $processo && ! $trilha && ! $chamada) {
        return 'este protocolo não está no banco. O POST não foi gravado neste ambiente';
    }

    $partes = [];
    $partes[] = $recebeu
        ? 'a API gravou o recebimento'
        : 'não há recebimento gravado';
    $partes[] = $processo
        ? 'há processo com este protocolo'
        : 'não há processo aberto com este protocolo';

    if (! $temLog) {
        $partes[] = 'o log de integrações da tela não existe nesta versão';
    } else {
        $partes[] = $chamada
            ? 'o POST está no log de integrações'
            : 'o POST não está no log de integrações';
    }

    if ($trilha) {
        $partes[] = 'a trilha regin/recebe existe';
    }

    return implode('; ', $partes);
}
