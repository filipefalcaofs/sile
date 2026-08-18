<?php

namespace App\Services\Relatorios\Export;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * DTO do que exportar (HU-131): carrega o MESMO Builder filtrado da tela (RN-005,
 * zero filtro duplicado) mais os metadados de apresentação e auditoria. O builder
 * e o mapRow são Closures — NUNCA são serializados; no caminho assíncrono o
 * GerarExportacaoJob reconstrói a definition a partir do `sourceClass` + bag de
 * filtros (ver {@see ReportSource}).
 */
final readonly class ReportDefinition
{
    /**
     * @param  list<array{key: string, label: string}>  $colunas  Ordem e rótulos das colunas.
     * @param  Closure(): Builder<*>  $builder  Fábrica do Builder filtrado (RN-005).
     * @param  Closure(mixed): array<int, scalar|null>  $mapRow  Mapeia um model para a linha exportada.
     * @param  array<string, scalar>  $filtrosAplicados  Filtros preenchidos (rodapé do PDF + auditoria).
     * @param  int|null  $maxRows  Teto técnico de linhas emitidas (guarda de volume); null = sem teto. Honrado por TODO driver (CSV/XLSX/PDF), no síncrono e no assíncrono — a contagem real (builder()->count()) é preservada para a decisão de limiar.
     */
    public function __construct(
        public string $titulo,
        public array $colunas,
        public Closure $builder,
        public Closure $mapRow,
        public array $filtrosAplicados = [],
        public string $logName = 'relatorios',
        public string $event = 'exporta',
        public bool $personalData = false,
        public string $arquivoBase = 'relatorio',
        public ?int $maxRows = null,
    ) {}

    /**
     * Rótulos das colunas na ordem definida (cabeçalho do CSV/XLSX/PDF).
     *
     * @return list<string>
     */
    public function columnLabels(): array
    {
        return array_map(static fn (array $coluna): string => $coluna['label'], $this->colunas);
    }

    /**
     * Builder filtrado da tela (RN-005). Cada chamada devolve um Builder fresco
     * (clonável para count/stream/get sem efeito colateral).
     *
     * @return Builder<*>
     */
    public function builder(): Builder
    {
        return ($this->builder)();
    }

    /**
     * Linha exportada de um model (ordem alinhada às colunas).
     *
     * @return array<int, scalar|null>
     */
    public function mapRow(mixed $model): array
    {
        return ($this->mapRow)($model);
    }

    /**
     * Nome do arquivo gerado: base em slug + carimbo de data/hora + extensão.
     */
    public function fileName(string $ext): string
    {
        return Str::slug($this->arquivoBase).'-'.now()->format('Ymd-His').'.'.$ext;
    }
}
