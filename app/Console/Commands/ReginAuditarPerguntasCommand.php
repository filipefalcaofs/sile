<?php

namespace App\Console\Commands;

use App\Enums\ViabilityRequestOrigin;
use App\Models\ReginRecebimento;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginProcessoProtocolador;
use App\Services\Tratamento\TratamentoRamoResolver;
use Illuminate\Console\Command;

/**
 * Lista, com a data de entrada, os processos da JUCEB cujo envelope veio sem perguntas, ou sem
 * resposta para algum CNAE que a planilha vigente exibe perguntas. Só lê:
 * recalcula o casamento pelo envelope recebido e não grava nada.
 */
class ReginAuditarPerguntasCommand extends Command
{
    protected $signature = 'regin:auditar-perguntas
        {--protocolo=* : Só estes protocolos (BAP…)}
        {--limite= : Máximo de processos}
        {--csv= : Grava o resultado neste arquivo CSV}';

    protected $description = 'Lista processos REGIN sem perguntas ou sem respostas para os CNAEs';

    public function handle(ReginProcessoProtocolador $protocolador, TratamentoRamoResolver $tratamento): int
    {
        $query = ViabilityRequest::query()
            ->where('origin', ViabilityRequestOrigin::Regin)
            ->whereNotNull('external_reference')
            ->orderBy('id');

        $protocolos = array_filter((array) $this->option('protocolo'));

        if ($protocolos !== []) {
            $query->whereIn('external_reference', $protocolos);
        }

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $total = 0;
        $achados = [];

        foreach ($query->cursor() as $processo) {
            $total++;
            $protocolo = (string) $processo->external_reference;
            $recebimento = ReginRecebimento::query()->where('protocolo', $protocolo)->latest('id')->first();
            $entrada = ($recebimento?->created_at ?? $processo->created_at)?->format('d/m/Y H:i') ?? '';

            if ($recebimento === null) {
                $achados[] = [$protocolo, $entrada, 'sem envelope', '', 'nenhum recebimento gravado'];

                continue;
            }

            $snapshot = $protocolador->snapshotDoRecebimento($recebimento);

            if ($snapshot === null) {
                $achados[] = [$protocolo, $entrada, 'sem perguntas', '', 'o envelope não trouxe pergunta respondida'];

                continue;
            }

            $respostas = $snapshot['respostas_tratamento_por_cnae'];

            foreach ($processo->cnaes()->orderBy('code')->get() as $cnae) {
                $exibidas = array_values(array_filter(
                    $tratamento->perguntasExibidas($cnae->code),
                    fn (array $pergunta): bool => $pergunta['depende'] === null,
                ));

                if ($exibidas === []) {
                    continue;
                }

                $dadas = $respostas[$cnae->code] ?? [];

                if ($dadas === []) {
                    $achados[] = [$protocolo, $entrada, 'sem respostas', $cnae->code, count($exibidas).' pergunta(s) na planilha, nenhuma respondida'];

                    continue;
                }

                $faltam = array_values(array_filter(
                    $exibidas,
                    fn (array $pergunta): bool => ! array_key_exists($pergunta['numero'], $dadas),
                ));

                if ($faltam !== []) {
                    $numeros = implode(', ', array_column($faltam, 'numero'));
                    $achados[] = [$protocolo, $entrada, 'respostas incompletas', $cnae->code, 'sem resposta nas perguntas '.$numeros];
                }
            }
        }

        foreach ($achados as [$protocolo, $entrada, $problema, $cnae, $detalhe]) {
            $this->line(trim("{$protocolo}  {$entrada}  {$problema}  {$cnae}  {$detalhe}"));
        }

        $this->gravarCsv($achados);

        $comProblema = count(array_unique(array_column($achados, 0)));
        $this->info("{$total} processo(s) REGIN verificados; {$comProblema} com problema.");

        return $achados === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<array{string, string, string, string, string}>  $achados
     */
    private function gravarCsv(array $achados): void
    {
        $caminho = $this->option('csv');

        if (! is_string($caminho) || $caminho === '') {
            return;
        }

        $arquivo = fopen($caminho, 'w');
        fputcsv($arquivo, ['protocolo', 'entrada', 'problema', 'cnae', 'detalhe'], ',', '"', '');

        foreach ($achados as $linha) {
            fputcsv($arquivo, $linha, ',', '"', '');
        }

        fclose($arquivo);
        $this->line("CSV gravado em {$caminho}");
    }
}
