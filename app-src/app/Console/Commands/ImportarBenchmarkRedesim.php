<?php

namespace App\Console\Commands;

use App\Models\BenchmarkIngestion;
use App\Services\Benchmark\BenchmarkIngestionService;
use App\Support\Capitais;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Importa o benchmark federal de tempo de viabilidade.
 *
 * O backfill cobre 27 UFs por quase cem competências desde 2019, com São
 * Paulo a cerca de 16 MB cada — por isso o comando é retomável (pula o que já
 * veio com sucesso), explícito sobre o que falhou e executável em lotes.
 */
class ImportarBenchmarkRedesim extends Command
{
    protected $signature = 'benchmark:importar-redesim
                            {--ano= : Ano da competência (padrão: a última competência fechada)}
                            {--mes= : Mês da competência (padrão: a última competência fechada)}
                            {--uf= : Importa só esta UF (padrão: as 27)}
                            {--desde= : Importa de AAAA-MM até a última competência fechada}
                            {--forcar : Reimporta competências já importadas com sucesso}';

    protected $description = 'Importa os microdados de tempo de viabilidade da REDESIM por UF e competência';

    public function handle(BenchmarkIngestionService $ingestao): int
    {
        $ufs = $this->ufs();
        $competencias = $this->competencias();

        if ($competencias === []) {
            $this->warn('Nenhuma competência fechada no intervalo informado.');

            return self::SUCCESS;
        }

        $forcar = (bool) $this->option('forcar');
        $falhas = [];
        $linhas = [];

        foreach ($competencias as [$ano, $mes]) {
            foreach ($ufs as $uf) {
                if (! $forcar && $this->jaImportada($uf, $ano, $mes)) {
                    continue;
                }

                $resultado = $ingestao->ingerir($uf, $ano, $mes);

                $linhas[] = [
                    sprintf('%s %02d/%d', $uf, $mes, $ano),
                    $resultado->status,
                    (string) $resultado->linhas_lidas,
                    (string) $resultado->linhas_descartadas,
                    (string) ($resultado->motivo ?? ''),
                ];

                if ($resultado->status === BenchmarkIngestion::STATUS_FALHA) {
                    $falhas[] = sprintf('%s %02d/%d', $uf, $mes, $ano);
                }
            }
        }

        if ($linhas !== []) {
            $this->table(['Competência', 'Status', 'Lidas', 'Descartadas', 'Motivo'], $linhas);
        }

        if ($falhas !== []) {
            $this->error('Competências que falharam: '.implode(', ', $falhas));

            return self::FAILURE;
        }

        $this->info('Importação concluída sem falhas.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function ufs(): array
    {
        $uf = $this->option('uf');

        return is_string($uf) && $uf !== ''
            ? [strtoupper(trim($uf))]
            : Capitais::ufs();
    }

    /**
     * Competências a percorrer. Sem opção, a última fechada; com --desde,
     * todas do intervalo até a última fechada. O mês corrente nunca entra:
     * a competência ainda está sendo consolidada na origem.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function competencias(): array
    {
        $ultima = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $desde = $this->option('desde');

        if (is_string($desde) && $desde !== '') {
            $cursor = Carbon::createFromFormat('Y-m', $desde)->startOfMonth();

            $competencias = [];

            while ($cursor->lessThanOrEqualTo($ultima)) {
                $competencias[] = [$cursor->year, $cursor->month];
                $cursor = $cursor->addMonthNoOverflow();
            }

            return $competencias;
        }

        $ano = $this->option('ano');
        $mes = $this->option('mes');

        if (is_numeric($ano) && is_numeric($mes)) {
            return [[(int) $ano, (int) $mes]];
        }

        return [[$ultima->year, $ultima->month]];
    }

    private function jaImportada(string $uf, int $ano, int $mes): bool
    {
        return BenchmarkIngestion::query()
            ->sucesso()
            ->where(['uf' => $uf, 'ano' => $ano, 'mes' => $mes])
            ->exists();
    }
}
