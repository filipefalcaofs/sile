<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Sefaz\ComparacaoCnaesViabilidade;
use App\Services\Sefaz\ConsultarComparacaoCnaesService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Preenche, nos processos de alteração da JUCEB que já estão na análise, a
 * comparação das atividades com a viabilidade anterior da empresa (Viabiliza,
 * Simplifica ou SEFAZ): incluídas, existentes e excluídas aparecem na ficha.
 * Não decide nem muda o status do processo.
 */
class CompararAtividadesAnterioresCommand extends Command
{
    protected $signature = 'processos:comparar-atividades-anteriores
        {--id=* : Só estes processos (id)}
        {--limite= : Máximo de processos nesta passada}
        {--refazer : Compara de novo quem já tem comparação}
        {--dry-run : Mostra o resultado, sem gravar}';

    protected $description = 'Compara as atividades dos processos de alteração em análise com a viabilidade anterior da empresa';

    public function handle(ConsultarComparacaoCnaesService $comparacao): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::EmAnalise)
            ->whereDoesntHave('decision')
            ->whereHas('eventos')
            ->whereHas('company', fn ($q) => $q->whereNotNull('cnpj'))
            ->when(! $this->option('refazer'), fn ($q) => $q->whereNull('comparacao_cnaes'))
            ->with(['company', 'eventos'])
            ->orderBy('id');

        $ids = array_filter((array) $this->option('id'));

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $contagem = ['comparados' => 0, 'sem_base' => 0, 'ignorados' => 0, 'erros' => 0];

        foreach ($query->cursor() as $processo) {
            $rotulo = $processo->protocol_number ?? "#{$processo->id}";

            if (! $comparacao->aplica($processo)) {
                $contagem['ignorados']++;

                continue;
            }

            try {
                $resultado = $dryRun
                    ? $comparacao->compararSemGravar($processo)['resultado']
                    : $comparacao->executar($processo, refazer: true);
            } catch (Throwable $e) {
                $this->error("{$rotulo}: {$e->getMessage()}");
                $contagem['erros']++;

                continue;
            }

            if ($resultado === null || $resultado->semViabilidade) {
                $contagem['sem_base']++;
                $this->line("{$rotulo}: sem viabilidade anterior encontrada");

                continue;
            }

            $contagem['comparados']++;
            $this->line("{$rotulo}: ".$this->resumo($resultado));
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."comparados {$contagem['comparados']}, sem base {$contagem['sem_base']}, fora da regra {$contagem['ignorados']}, erros {$contagem['erros']}");

        return $contagem['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resumo(ComparacaoCnaesViabilidade $resultado): string
    {
        $lista = fn (array $codigos): string => $codigos === [] ? 'nenhuma' : implode(', ', $codigos);

        return "viabilidade {$resultado->numeroViabilidade}; incluídas {$lista($resultado->incluidas)}; existentes {$lista($resultado->mantidas)}; excluídas {$lista($resultado->excluidas)}";
    }
}
