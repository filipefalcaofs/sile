<?php

declare(strict_types=1);

/**
 * Corrige o responsável dos processos em análise que ficaram com a pessoa
 * errada antes da v0.116.0: quem abriu o convite ou devolveu o "Para conclusão"
 * sem ter recebido o processo não virava responsável, e o processo seguia na
 * caixa (e com os avisos) de quem analisou antes.
 *
 * Varre os processos em Em análise, Em convite, Convite respondido ou Convite
 * cancelado. Pega a última dessas ações; se ela é mais recente que a
 * atribuição atual e foi feita por outra pessoa, essa pessoa passa a ser a
 * responsável, com o evento "Processo assumido" na tramitação. Redistribuição
 * feita depois da ação prevalece. Setor e prazo não mudam.
 *
 * Por padrão só lista o que seria feito; a gravação exige --aplicar.
 *
 * No terminal do pod:
 *
 *   php /tmp/assumir-quem-agiu.php
 *   php /tmp/assumir-quem-agiu.php --aplicar
 *
 * O último argumento que for um diretório é a raiz da aplicação
 * (padrão: /opt/app-root/src, depois /var/www/html).
 */

use App\Enums\AnalysisStatus;
use App\Models\AnalysisPendency;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$autonomo = ! isset($app);

if ($autonomo) {
    $argumentos = array_slice($argv ?? [], 1);
    $raiz = null;
    $aplicar = false;

    foreach ($argumentos as $argumento) {
        if ($argumento === '--aplicar') {
            $aplicar = true;
        } elseif (is_dir($argumento)) {
            $raiz = $argumento;
        }
    }

    $raiz ??= is_dir('/opt/app-root/src') ? '/opt/app-root/src' : '/var/www/html';

    require $raiz.'/vendor/autoload.php';

    $app = require $raiz.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
}

$aplicar = $aplicar ?? false;

$codigo = (function () use ($app, $aplicar): int {
    $emAnalise = [
        AnalysisStatus::EmAnalise->value,
        AnalysisStatus::EmConvite->value,
        AnalysisStatus::ConviteRespondido->value,
        AnalysisStatus::ConviteCancelado->value,
    ];

    $nomes = [];
    $nome = function (?int $id) use (&$nomes): string {
        if ($id === null) {
            return '(ninguém)';
        }

        return $nomes[$id] ??= User::query()->whereKey($id)->value('name') ?? "#{$id}";
    };

    $encontrados = 0;

    ViabilityRequest::query()
        ->whereIn('analysis_status', $emAnalise)
        ->orderBy('id')
        ->each(function (ViabilityRequest $processo) use ($app, $aplicar, $nome, &$encontrados): void {
            $convite = AnalysisPendency::query()
                ->where('viability_request_id', $processo->id)
                ->latest('id')
                ->first();

            $devolucao = $processo->analysisStatusTransitions()
                ->where('from_status', AnalysisStatus::ParaConclusao->value)
                ->whereNotIn('to_status', [AnalysisStatus::Encaminhado->value, AnalysisStatus::AnaliseConcluida->value])
                ->whereNotNull('actor_user_id')
                ->latest('id')
                ->first();

            $acoes = array_filter([
                $convite === null ? null : ['ator' => $convite->requested_by_user_id, 'em' => $convite->created_at, 'acao' => 'abrir o convite'],
                $devolucao === null ? null : ['ator' => $devolucao->actor_user_id, 'em' => $devolucao->created_at, 'acao' => 'devolver o Para conclusão'],
            ]);

            if ($acoes === []) {
                return;
            }

            usort($acoes, fn (array $a, array $b): int => $b['em'] <=> $a['em']);
            $ultima = $acoes[0];

            if ((int) $ultima['ator'] === (int) $processo->assigned_user_id) {
                return;
            }

            if ($processo->assigned_at !== null && $processo->assigned_at->greaterThan($ultima['em'])) {
                return;
            }

            $encontrados++;
            $anterior = $processo->assigned_user_id;

            echo "Processo #{$processo->id} BAP ".($processo->external_reference ?? '(sem BAP)')." protocolo {$processo->protocol_number}\n";
            echo '  situação: '.$processo->analysis_status->value."\n";
            echo '  responsável hoje: '.$nome($anterior)."\n";
            echo '  passa para: '.$nome((int) $ultima['ator'])." (ao {$ultima['acao']} em ".$ultima['em']->format('d/m/Y H:i').")\n";

            if (! $aplicar) {
                return;
            }

            DB::transaction(function () use ($app, $processo, $ultima, $anterior, $nome): void {
                $processo->forceFill([
                    'assigned_user_id' => (int) $ultima['ator'],
                    'assigned_at' => now(),
                ])->save();

                $app->make(AuditService::class)->log(
                    'analise',
                    'assumir',
                    $nome((int) $ultima['ator'])." assumiu o processo ao {$ultima['acao']} (correção por script, solicitação #{$processo->id}).",
                    [
                        'viability_request_id' => $processo->id,
                        'protocol_number' => $processo->protocol_number,
                        'sector_id' => $processo->sector_id,
                        'assigned_user_id' => (int) $ultima['ator'],
                        'analista_anterior_id' => $anterior,
                        'ator_id' => (int) $ultima['ator'],
                        'correcao_script' => 'assumir-quem-agiu',
                    ],
                    $processo,
                );
            });

            echo "  Gravado.\n";
        });

    echo $encontrados === 0
        ? "Nenhum processo com o responsável a corrigir.\n"
        : "{$encontrados} processo(s)".($aplicar ? ' corrigido(s).' : ' a corrigir. Rode com --aplicar para gravar.')."\n";

    return 0;
})();

if ($autonomo) {
    exit($codigo);
}
