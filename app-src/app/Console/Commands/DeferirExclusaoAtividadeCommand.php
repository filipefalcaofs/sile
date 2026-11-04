<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Expresso\DeferirExclusaoAtividadeService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Defere no expresso, sem taxa, os processos em análise que só excluem
 * atividades e não mudam de endereço, inclusive os já assumidos por analista.
 */
class DeferirExclusaoAtividadeCommand extends Command
{
    protected $signature = 'processos:deferir-exclusao-atividade
        {--id=* : Só estes processos (id)}
        {--dry-run : Mostra o que seria feito, sem gravar}';

    protected $description = 'Defere no expresso, sem taxa, os processos em análise que só excluem atividades';

    public function handle(DeferirExclusaoAtividadeService $servico): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_filter((array) $this->option('id'));

        $query = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::EmAnalise)
            ->whereDoesntHave('decision')
            ->with('eventos')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id');

        $contagem = ['deferidos' => 0, 'outros' => 0, 'erros' => 0];

        foreach ($query->cursor() as $processo) {
            if (! $servico->elegivel($processo)) {
                continue;
            }

            $rotulo = ($processo->protocol_number ?? '#'.$processo->id)." (id {$processo->id})";
            $excluidas = implode(', ', $processo->comparacao_cnaes['excluidas'] ?? $processo->cnaesParaExcluir()->pluck('code')->all());
            $assumido = $processo->assigned_user_id !== null ? ", assumido pelo analista {$processo->assigned_user_id}" : '';

            if ($dryRun) {
                $this->line("{$rotulo}: exclui {$excluidas}{$assumido}; seria deferido sem taxa");
                $contagem['deferidos']++;

                continue;
            }

            try {
                $resultado = $servico->deferir($processo);
            } catch (Throwable $e) {
                $this->error("{$rotulo}: {$e->getMessage()}");
                $contagem['erros']++;

                continue;
            }

            if ($resultado->status === ViabilityRequestStatus::Deferida) {
                $contagem['deferidos']++;
                $this->line("{$rotulo}: exclui {$excluidas}{$assumido}; deferido, viabilidade {$resultado->decision?->tvl_product_number}");

                continue;
            }

            $contagem['outros']++;
            $this->warn("{$rotulo}: {$resultado->status->label()}".($resultado->reason ? " ({$resultado->reason})" : ''));
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."deferidos {$contagem['deferidos']}, outro resultado {$contagem['outros']}, erros {$contagem['erros']}");

        return $contagem['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
