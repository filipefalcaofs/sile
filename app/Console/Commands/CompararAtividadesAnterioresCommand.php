<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Sefaz\ComparacaoCnaesViabilidade;
use App\Services\Sefaz\ConsultarComparacaoCnaesService;
use App\Services\Sefaz\LegadoViabilidadeFonte;
use Illuminate\Console\Command;
use Throwable;

/**
 * Preenche, nos processos de alteração da JUCEB que já estão na análise, a
 * comparação das atividades com a viabilidade anterior da empresa (Simplifica,
 * Viabiliza ou SEFAZ): incluídas, existentes e excluídas aparecem na ficha.
 * Quando a comparação mostra que nada foi incluído e o endereço não muda (só
 * exclusão, ou pedido igual à viabilidade), o processo que ninguém assumiu
 * volta ao motor e é deferido no expresso, sem taxa. Quando a consulta ao Simplifica
 * falha (o limite da API é de 60 por minuto), espera e tenta de novo; se não
 * responder, não grava "sem base" e conta como erro.
 */
class CompararAtividadesAnterioresCommand extends Command
{
    protected $signature = 'processos:comparar-atividades-anteriores
        {--id=* : Só estes processos (id)}
        {--limite= : Máximo de processos nesta passada}
        {--refazer : Compara de novo quem já tem comparação}
        {--refazer-sem-base : Compara de novo só quem ficou sem viabilidade anterior}
        {--espera=60 : Segundos de espera quando o Simplifica não responde}
        {--tentativas=3 : Tentativas por processo quando o Simplifica não responde}
        {--dry-run : Mostra o resultado, sem gravar}';

    protected $description = 'Compara as atividades dos processos de alteração em análise com a viabilidade anterior da empresa';

    public function handle(ConsultarComparacaoCnaesService $comparacao, LegadoViabilidadeFonte $legado, FluxoExpressoService $motor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $espera = max(0, (int) $this->option('espera'));
        $tentativas = max(1, (int) $this->option('tentativas'));

        $query = ViabilityRequest::query()
            ->where('status', ViabilityRequestStatus::EmAnalise)
            ->whereDoesntHave('decision')
            ->whereHas('eventos')
            ->whereHas('company', fn ($q) => $q->whereNotNull('cnpj'))
            ->when(
                $this->option('refazer-sem-base'),
                fn ($q) => $q->where('comparacao_cnaes->sem_viabilidade', true),
                fn ($q) => $q->when(! $this->option('refazer'), fn ($q) => $q->whereNull('comparacao_cnaes')),
            )
            ->with(['company', 'eventos'])
            ->orderBy('id');

        $ids = array_filter((array) $this->option('id'));

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $contagem = ['comparados' => 0, 'sem_base' => 0, 'ignorados' => 0, 'erros' => 0, 'deferidos' => 0];

        foreach ($query->cursor() as $processo) {
            $rotulo = $processo->protocol_number ?? "#{$processo->id}";

            if (! $comparacao->aplica($processo)) {
                $contagem['ignorados']++;

                continue;
            }

            try {
                $feita = $this->comparar($comparacao, $legado, $processo, $tentativas, $espera);
            } catch (Throwable $e) {
                $this->error("{$rotulo}: {$e->getMessage()}");
                $contagem['erros']++;

                continue;
            }

            if ($feita === null) {
                $this->error("{$rotulo}: o Simplifica não respondeu; nada gravado");
                $contagem['erros']++;

                continue;
            }

            if (! $dryRun) {
                $comparacao->registrar($processo, $feita['resultado'], $feita['fonte']);
            }

            if ($feita['resultado']->semViabilidade) {
                $contagem['sem_base']++;
                $this->line("{$rotulo}: sem viabilidade anterior encontrada");

                continue;
            }

            $contagem['comparados']++;
            $this->line("{$rotulo}: ".$this->resumo($feita['resultado']));

            if ($feita['resultado']->incluidas !== [] || $processo->alteraEndereco() || ! $motor->podeReavaliar($processo)) {
                continue;
            }

            if ($dryRun) {
                $this->line("{$rotulo}: sem atividade incluída, seria deferido sem taxa");

                continue;
            }

            try {
                $decidido = $motor->decide($processo);
            } catch (Throwable $e) {
                $this->error("{$rotulo}: motor falhou: {$e->getMessage()}");
                $contagem['erros']++;

                continue;
            }

            $this->line("{$rotulo}: motor refeito, ".$decidido->status->label());

            if ($decidido->status === ViabilityRequestStatus::Deferida) {
                $contagem['deferidos']++;
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."comparados {$contagem['comparados']}, sem base {$contagem['sem_base']}, fora da regra {$contagem['ignorados']}, erros {$contagem['erros']}, deferidos {$contagem['deferidos']}");

        return $contagem['erros'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Compara sem gravar. Null quando o Simplifica falhou em todas as tentativas.
     *
     * @return array{resultado: ComparacaoCnaesViabilidade, fonte: ?string}|null
     */
    private function comparar(ConsultarComparacaoCnaesService $comparacao, LegadoViabilidadeFonte $legado, ViabilityRequest $processo, int $tentativas, int $espera): ?array
    {
        for ($tentativa = 1; $tentativa <= $tentativas; $tentativa++) {
            $feita = $comparacao->compararSemGravar($processo);

            if (! $legado->falhou()) {
                return $feita;
            }

            if ($tentativa < $tentativas && $espera > 0) {
                $this->warn("Simplifica sem resposta; aguardando {$espera}s");
                sleep($espera);
            }
        }

        return null;
    }

    private function resumo(ComparacaoCnaesViabilidade $resultado): string
    {
        $lista = fn (array $codigos): string => $codigos === [] ? 'nenhuma' : implode(', ', $codigos);

        return "viabilidade {$resultado->numeroViabilidade}; incluídas {$lista($resultado->incluidas)}; existentes {$lista($resultado->mantidas)}; excluídas {$lista($resultado->excluidas)}";
    }
}
