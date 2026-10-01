<?php

declare(strict_types=1);

/**
 * Apaga os processos da homologação a partir do terminal do pod.
 *
 * Sem --confirmar, só mostra a contagem. A confirmação é o nome do banco
 * impresso na simulação, para o comando não rodar no pod errado.
 *
 * Não mexe em usuários, empresas, CNAEs, regras, parâmetros, perfis nem setores.
 *
 *   php scripts/limpar-processos-hml.php
 *   php scripts/limpar-processos-hml.php --confirmar=NOME_DO_BANCO
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo <<<'TXT'
Uso no terminal do pod, na pasta da aplicação:

  php scripts/limpar-processos-hml.php
  php scripts/limpar-processos-hml.php --confirmar=NOME_DO_BANCO

Sem --confirmar, nada é apagado. O valor tem de ser o nome do banco que a
simulação imprimir. Só aceita URL ou banco com hml/homolog. Recusa
viabiliza.salvador.ba.gov.br e ambiente local/testing.

TXT;
    exit(0);
}

[$caminho, $confirmacao] = lerArgumentos($argv);
$raiz = raizDaAplicacao($caminho);

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$conexao = (string) config('database.default');
$banco = (string) config("database.connections.{$conexao}.database");
$url = (string) config('app.url');
$ambiente = (string) config('app.env');
$host = (string) config("database.connections.{$conexao}.host");

echo "APP_ENV: {$ambiente}\n";
echo "APP_URL: {$url}\n";
echo "Banco: {$banco} @ {$host}\n\n";

$recusa = motivoDeRecusa($url, $banco);

if ($recusa !== null) {
    fwrite(STDERR, $recusa."\n");
    exit(1);
}

if (! Schema::hasTable('viability_requests')) {
    fwrite(STDERR, "Recusado: este banco não tem a tabela de processos.\n");
    exit(1);
}

$plano = montarPlano();
$arquivos = coletarArquivos();

echo $confirmacao === null
    ? "Simulação — nada foi apagado.\n\nSeria apagado:\n"
    : "O que será apagado:\n";
foreach ($plano as $item) {
    echo sprintf("  %s: %d\n", $item['rotulo'], $item['total']);
}
echo '  arquivos de processo no storage: '.count($arquivos)."\n";

$sequencias = lerSequencias();
if ($sequencias !== []) {
    echo "  contadores que voltam a zero:\n";
    foreach ($sequencias as $sequencia) {
        echo sprintf("    %s %d = %d\n", $sequencia['tabela'], $sequencia['year'], $sequencia['last_number']);
    }
}

echo "\nPermanece:\n";
foreach (contagensPreservadas() as $rotulo => $total) {
    echo sprintf("  %s: %s\n", $rotulo, $total === null ? 'tabela ausente' : (string) $total);
}

if ($confirmacao === null) {
    echo "\nPara apagar de verdade, no terminal deste pod:\n";
    echo "  php scripts/limpar-processos-hml.php --confirmar={$banco}\n";
    exit(0);
}

if (app()->environment('local', 'testing')) {
    fwrite(STDERR, "\nRecusado: este PHP está em ambiente local ou de teste. Rode no terminal do pod de homologação.\n");
    exit(1);
}

if ($confirmacao !== $banco) {
    fwrite(STDERR, "\nRecusado: --confirmar={$confirmacao} não é o banco atual ({$banco}).\n");
    exit(1);
}

echo "\nApagando no banco {$banco}.\n";

DB::transaction(function (): void {
    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SET LOCAL statement_timeout = '10min'");
    }

    if (Schema::hasTable('viability_requests') && Schema::hasColumn('viability_requests', 'renews_viability_request_id')) {
        DB::table('viability_requests')
            ->whereNotNull('renews_viability_request_id')
            ->update(['renews_viability_request_id' => null]);
    }

    foreach (montarPlano() as $item) {
        $item['apagar']();
    }

    foreach (['protocol_sequences', 'tvl_sequences'] as $tabela) {
        if (Schema::hasTable($tabela)) {
            DB::table($tabela)->update(['last_number' => 0]);
        }
    }
});

$arquivosRemovidos = apagarArquivos($arquivos);
$restantes = Schema::hasTable('viability_requests')
    ? (int) DB::table('viability_requests')->count()
    : 0;

echo "\nProcessos apagados. Restantes: {$restantes}. Arquivos removidos: {$arquivosRemovidos}.\n";

if ($restantes !== 0) {
    fwrite(STDERR, "Ainda há processos na tabela. A limpeza não fechou.\n");
    exit(1);
}

/**
 * @return array{0: ?string, 1: ?string}
 */
function lerArgumentos(array $argv): array
{
    $caminho = null;
    $confirmacao = null;

    foreach (array_slice($argv, 1) as $argumento) {
        if (str_starts_with($argumento, '--confirmar=')) {
            $confirmacao = substr($argumento, strlen('--confirmar='));

            continue;
        }

        if ($argumento === '--confirmar') {
            fwrite(STDERR, "Use --confirmar=NOME_DO_BANCO com o nome impresso na simulação.\n");
            exit(1);
        }

        $caminho = $argumento;
    }

    if ($confirmacao === '') {
        fwrite(STDERR, "A confirmação está vazia. Use --confirmar=NOME_DO_BANCO.\n");
        exit(1);
    }

    return [$caminho, $confirmacao];
}

function raizDaAplicacao(?string $informada): string
{
    $candidatas = array_filter([
        $informada,
        getcwd(),
        '/var/www/html',
        '/opt/app-root/src',
    ]);

    foreach ($candidatas as $candidata) {
        if (is_file($candidata.'/artisan') && is_file($candidata.'/vendor/autoload.php')) {
            return $candidata;
        }
    }

    fwrite(STDERR, "Não achei a aplicação Laravel. Rode na pasta do pod ou passe o caminho: php limpar-processos-hml.php /var/www/html\n");
    exit(1);
}

function motivoDeRecusa(string $url, string $banco): ?string
{
    $destino = strtolower((string) parse_url($url, PHP_URL_HOST));
    $texto = strtolower($url.' '.$banco);
    $homologacao = str_contains($texto, 'homolog') || str_contains($texto, 'hml');

    if ($destino === 'viabiliza.salvador.ba.gov.br') {
        return 'Recusado: esta URL é a de produção (viabiliza.salvador.ba.gov.br).';
    }

    if (! $homologacao && preg_match('/(^|[^a-z])prod(uction|ucao)?([^a-z]|$)/', $texto) === 1) {
        return 'Recusado: a URL ou o banco parecem produção.';
    }

    if (! $homologacao) {
        return 'Recusado: a URL e o banco não se identificam como homologação. É preciso haver hml ou homolog em um dos dois.';
    }

    return null;
}

/**
 * @return list<array{rotulo: string, total: int, apagar: callable(): void}>
 */
function montarPlano(): array
{
    $plano = [];

    $vinculadas = [
        'communications' => 'comunicações de processo',
        'abuse_alerts' => 'alertas de abuso ligados a processo',
        'ai_suggestions' => 'sugestões de IA ligadas a processo',
    ];

    foreach ($vinculadas as $tabela => $rotulo) {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'viability_request_id')) {
            continue;
        }

        $plano[] = item(
            $rotulo,
            (int) DB::table($tabela)->whereNotNull('viability_request_id')->count(),
            function () use ($tabela): void {
                DB::table($tabela)->whereNotNull('viability_request_id')->delete();
            },
        );
    }

    foreach ([
        'regin_recebimentos' => 'recebimentos REGIN',
        'regin_simulacao_execucoes' => 'simulações REGIN',
    ] as $tabela => $rotulo) {
        if (! Schema::hasTable($tabela)) {
            continue;
        }

        $plano[] = item(
            $rotulo,
            (int) DB::table($tabela)->count(),
            function () use ($tabela): void {
                DB::table($tabela)->delete();
            },
        );
    }

    if (Schema::hasTable('activity_log')) {
        $plano[] = item(
            'trilha de auditoria dos processos',
            (int) consultaAtividade()->count(),
            function (): void {
                consultaAtividade()->delete();
            },
        );
    }

    if (Schema::hasTable('notifications')) {
        $plano[] = item(
            'notificações de processo',
            (int) consultaTexto('notifications', 'type', classesDeNotificacaoDeProcesso())->count(),
            function (): void {
                consultaTexto('notifications', 'type', classesDeNotificacaoDeProcesso())->delete();
            },
        );
    }

    if (Schema::hasTable('email_logs') && Schema::hasColumn('email_logs', 'notification_class')) {
        $plano[] = item(
            'e-mails de processo',
            (int) consultaTexto('email_logs', 'notification_class', classesDeNotificacaoDeProcesso())->count(),
            function (): void {
                consultaTexto('email_logs', 'notification_class', classesDeNotificacaoDeProcesso())->delete();
            },
        );
    }

    foreach (['jobs' => 'fila de jobs de processo', 'failed_jobs' => 'jobs de processo que falharam'] as $tabela => $rotulo) {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'payload')) {
            continue;
        }

        $plano[] = item(
            $rotulo,
            (int) consultaTexto($tabela, 'payload', trechosDeFilaDeProcesso())->count(),
            function () use ($tabela): void {
                consultaTexto($tabela, 'payload', trechosDeFilaDeProcesso())->delete();
            },
        );
    }

    foreach (tabelasFilhas() as $tabela => $rotulo) {
        if (! Schema::hasTable($tabela)) {
            continue;
        }

        $plano[] = item($rotulo, (int) DB::table($tabela)->count(), function (): void {});
    }

    if (Schema::hasTable('viability_requests')) {
        $plano[] = item(
            'processos',
            (int) DB::table('viability_requests')->count(),
            function (): void {
                DB::table('viability_requests')->delete();
            },
        );
    }

    return $plano;
}

/**
 * @param  callable(): void  $apagar
 * @return array{rotulo: string, total: int, apagar: callable(): void}
 */
function item(string $rotulo, int $total, callable $apagar): array
{
    return [
        'rotulo' => $rotulo,
        'total' => $total,
        'apagar' => $apagar,
    ];
}

/**
 * Filhas apagadas pelo banco ao remover viability_requests. Entram no
 * relatório para a contagem aparecer antes da confirmação.
 *
 * @return array<string, string>
 */
function tabelasFilhas(): array
{
    return [
        'viability_request_cnaes' => 'CNAEs dos processos',
        'viability_request_documents' => 'anexos dos processos',
        'viability_request_transitions' => 'transições dos processos',
        'viability_decisions' => 'decisões',
        'tvl_documents' => 'PDFs de TVL',
        'indeferimento_documents' => 'PDFs de indeferimento',
        'analysis_records' => 'fichas de análise',
        'analysis_divergences' => 'divergências de análise',
        'analysis_pendencies' => 'pendências',
        'analysis_status_transitions' => 'transições de análise',
        'fine_mesh_referrals' => 'encaminhamentos de malha fina',
        'inspections' => 'vistorias',
        'inspection_attachments' => 'anexos de vistoria',
        'inspection_referrals' => 'encaminhamentos de vistoria',
        'expresso_quedas' => 'quedas do fluxo expresso',
        'sefaz_notifications' => 'notificações SEFAZ',
        'service_declaration_acceptances' => 'aceites de declaração',
        'virtual_office_inscription_locks' => 'travas de escritório virtual',
        'predictive_anomalies' => 'anomalias preditivas de processo',
        'ai_precedent_embeddings' => 'precedentes de IA dos processos',
    ];
}

/**
 * @return list<string>
 */
function assuntosDeProcesso(): array
{
    return [
        'App\\Models\\ViabilityRequest',
        'App\\Models\\ViabilityDecision',
        'App\\Models\\Inspection',
        'App\\Models\\PredictiveAnomaly',
        'App\\Models\\AnalysisRecord',
        'App\\Models\\AnalysisPendency',
    ];
}

/**
 * @return list<string>
 */
function classesDeNotificacaoDeProcesso(): array
{
    return [
        'App\\Notifications\\PendenciaSolicitadaNotification',
        'App\\Notifications\\ResultadoExpressoNotification',
        'App\\Notifications\\RespostaPendenciaNotification',
        'App\\Notifications\\ProcessoEscalonadoNotification',
        'App\\Notifications\\PrazoVencendoNotification',
        'App\\Notifications\\PendenciaExpiradaNotification',
        'App\\Notifications\\AbrigadoDesvinculadoNotification',
    ];
}

/**
 * @return list<string>
 */
function trechosDeFilaDeProcesso(): array
{
    return [
        ...classesDeNotificacaoDeProcesso(),
        'App\\Jobs\\ProtocolarRecebimentoReginJob',
        'App\\Jobs\\DecidirFluxoExpressoJob',
        'App\\Listeners\\EnviarViabilidadeSefaz',
        'App\\Listeners\\ComunicarResultadoRegin',
        'App\\Jobs\\Ai\\ResumoProcessoJob',
    ];
}

function consultaAtividade(): Builder
{
    $assuntos = assuntosDeProcesso();

    return DB::table('activity_log')->where(function ($query) use ($assuntos): void {
        $query->whereIn('subject_type', $assuntos);
        $query->orWhere(function ($texto): void {
            ondeContemTexto($texto, 'properties', ['viability_request_id'], true);
        });
    });
}

/**
 * @param  list<string>  $trechos
 */
function consultaTexto(string $tabela, string $coluna, array $trechos): Builder
{
    return DB::table($tabela)->where(function ($query) use ($coluna, $trechos): void {
        ondeContemTexto($query, $coluna, $trechos, false);
    });
}

/**
 * @param  list<string>  $trechos
 */
function ondeContemTexto(object $query, string $coluna, array $trechos, bool $json): void
{
    if (! in_array($coluna, ['properties', 'type', 'notification_class', 'payload'], true)) {
        throw new InvalidArgumentException("Coluna fora do recorte de limpeza: {$coluna}");
    }

    $driver = DB::getDriverName();
    $primeiro = true;

    foreach ($trechos as $trecho) {
        $expressao = $driver === 'pgsql'
            ? ($json
                ? "position(? in coalesce({$coluna}::text, '')) > 0"
                : "position(? in coalesce({$coluna}, '')) > 0")
            : "instr(coalesce({$coluna}, ''), ?) > 0";

        if ($primeiro) {
            $query->whereRaw($expressao, [$trecho]);
            $primeiro = false;

            continue;
        }

        $query->orWhereRaw($expressao, [$trecho]);
    }
}

/**
 * @return list<object{disk: string, path: string}>
 */
function coletarArquivos(): array
{
    $arquivos = [];

    foreach (['viability_request_documents', 'tvl_documents', 'indeferimento_documents', 'inspection_attachments'] as $tabela) {
        if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'disk') || ! Schema::hasColumn($tabela, 'path')) {
            continue;
        }

        foreach (DB::table($tabela)->select(['disk', 'path'])->orderBy('id')->get() as $arquivo) {
            if ((string) $arquivo->path === '') {
                continue;
            }

            $arquivos[] = $arquivo;
        }
    }

    return $arquivos;
}

/**
 * @param  list<object{disk: string, path: string}>  $arquivos
 */
function apagarArquivos(array $arquivos): int
{
    $removidos = 0;

    foreach ($arquivos as $arquivo) {
        $disco = (string) $arquivo->disk;
        $caminho = (string) $arquivo->path;

        try {
            $storage = Storage::disk($disco);

            if ($storage->exists($caminho) && $storage->delete($caminho)) {
                $removidos++;
            }
        } catch (Throwable $falha) {
            fwrite(STDERR, "Arquivo não removido ({$disco}:{$caminho}): {$falha->getMessage()}\n");
        }
    }

    return $removidos;
}

/**
 * @return list<array{tabela: string, year: int, last_number: int}>
 */
function lerSequencias(): array
{
    $linhas = [];

    foreach (['protocol_sequences', 'tvl_sequences'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            continue;
        }

        foreach (DB::table($tabela)->orderBy('year')->get(['year', 'last_number']) as $sequencia) {
            $linhas[] = [
                'tabela' => $tabela,
                'year' => (int) $sequencia->year,
                'last_number' => (int) $sequencia->last_number,
            ];
        }
    }

    return $linhas;
}

/**
 * @return array<string, int|null>
 */
function contagensPreservadas(): array
{
    $tabelas = [
        'usuários' => 'users',
        'empresas' => 'companies',
        'CNAEs' => 'cnaes',
        'parâmetros' => 'parameters',
        'consultas prévias' => 'viability_queries',
    ];

    $totais = [];

    foreach ($tabelas as $rotulo => $tabela) {
        $totais[$rotulo] = Schema::hasTable($tabela)
            ? (int) DB::table($tabela)->count()
            : null;
    }

    return $totais;
}
