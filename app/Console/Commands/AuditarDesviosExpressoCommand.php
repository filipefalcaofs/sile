<?php

namespace App\Console\Commands;

use App\Services\Expresso\AuditoriaDesviosExpressoService;
use Illuminate\Console\Command;

/**
 * Lista as decisões expressas já emitidas que o motor corrigido na 0.64.0 não
 * deferiria (Quadro 11-A com CNLU, observação (a)/(b) do Quadro 10 fora da
 * allow-list) e conta as citações legais antigas da fundamentação. Somente
 * leitura: o resultado serve para a SEDUR decidir o que vai à malha fina.
 */
class AuditarDesviosExpressoCommand extends Command
{
    protected $signature = 'expresso:auditar-desvios-064
        {--csv= : Caminho do arquivo CSV com as decisões apontadas}';

    protected $description = 'Lista as decisões expressas afetadas pelos desvios do motor corrigidos na 0.64.0 (somente leitura)';

    private const array COLUNAS = ['Protocolo', 'Decidido em', 'CNAE', 'Motivo', 'Detalhe', 'Situação'];

    public function handle(AuditoriaDesviosExpressoService $auditoria): int
    {
        $resultado = $auditoria->auditar();
        $linhas = array_map(array_values(...), $resultado['suspeitas']);

        if ($linhas === []) {
            $this->info('Nenhuma decisão expressa com desvio.');
        } else {
            $this->table(self::COLUNAS, $linhas);
            $this->info(count($linhas).' apontamento(s).');
        }

        $this->line("Deferimentos expressos avaliados: {$resultado['avaliadas']}");
        $this->line("Sem rastro do motor: {$resultado['sem_rastro']}");

        $this->newLine();
        $this->line('Decisões expressas com citação antiga na fundamentação:');

        foreach ($resultado['citacoes'] as $citacao => $total) {
            $this->line("  {$citacao}: {$total}");
        }

        $caminho = $this->option('csv');

        if (is_string($caminho) && $caminho !== '') {
            $this->gravarCsv($caminho, $linhas);
            $this->info("CSV gravado em {$caminho}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<list<?string>>  $linhas
     */
    private function gravarCsv(string $caminho, array $linhas): void
    {
        $arquivo = fopen($caminho, 'w');

        fputcsv($arquivo, self::COLUNAS, ';', escape: '');

        foreach ($linhas as $linha) {
            fputcsv($arquivo, $linha, ';', escape: '');
        }

        fclose($arquivo);
    }
}
