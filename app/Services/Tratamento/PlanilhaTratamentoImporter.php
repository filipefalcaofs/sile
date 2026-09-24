<?php

namespace App\Services\Tratamento;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoCnluItem;
use App\Models\TratamentoCondicionante;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoNota;
use App\Models\TratamentoPendencia;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Models\TratamentoRegraRamo;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class PlanilhaTratamentoImporter
{
    public function __construct(
        private PlanilhaTratamentoWorkbook $workbook,
        private RuleVersionService $versions,
        private AuditService $audit,
    ) {}

    /**
     * @return array{duplicada: bool, versao: RuleVersion, pendencias: int}
     */
    public function importar(string $path, int $autorId, string $nomeArquivo): array
    {
        $carga = $this->workbook->ler($path);
        $hash = hash('sha256', json_encode($carga, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $existente = RuleVersion::query()
            ->where('domain', RuleDomain::RiscoTratamento->value)
            ->where('content_hash', $hash)
            ->first();

        if ($existente !== null) {
            return [
                'duplicada' => true,
                'versao' => $existente,
                'pendencias' => TratamentoPendencia::query()->where('rule_version_id', $existente->getKey())->count(),
            ];
        }

        $rotulo = 'planilha-'.now()->format('Y-m-d').'-'.substr($hash, 0, 8);

        return DB::transaction(function () use ($carga, $hash, $rotulo, $autorId, $nomeArquivo): array {
            $draft = $this->versions->openDraft(
                RuleDomain::RiscoTratamento,
                $rotulo,
                $nomeArquivo,
                $autorId,
            );

            if ($draft->status !== RuleVersionStatus::Rascunho) {
                $draft = $this->versions->openDraft(
                    RuleDomain::RiscoTratamento,
                    $rotulo.'-'.substr($hash, 8, 4),
                    $nomeArquivo,
                    $autorId,
                );
            }

            $this->gravar($draft, $carga);
            $draft->update(['content_hash' => $hash]);

            $this->audit->log(
                logName: 'tratamento',
                event: 'importacao-planilha',
                description: "Rascunho {$draft->version} aberto a partir de {$nomeArquivo}",
                subject: $draft,
                properties: [
                    'arquivo' => $nomeArquivo,
                    'enquadramentos' => count($carga['enquadramentos']),
                    'regras' => count($carga['regras']),
                    'pendencias' => count($carga['pendencias']),
                ],
                result: 'sucesso',
                rulesVersion: $draft->version,
            );

            return [
                'duplicada' => false,
                'versao' => $draft->refresh(),
                'pendencias' => count($carga['pendencias']),
            ];
        });
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $carga
     */
    private function gravar(RuleVersion $draft, array $carga): void
    {
        $id = $draft->getKey();

        $this->upsert(TratamentoPergunta::class, array_map(fn (array $row): array => [
            'rule_version_id' => $id,
            'numero' => $row['numero'],
            'texto' => $row['texto'],
            'referencia' => $row['referencia'],
            'observacao' => $row['observacao'],
        ], $carga['perguntas']), ['rule_version_id', 'numero'], ['texto', 'referencia', 'observacao']);

        $this->upsert(TratamentoRegra::class, array_map(fn (array $row): array => [
            'rule_version_id' => $id,
            'numero' => $row['numero'],
            'tratamento' => $row['tratamento'],
            'referencia' => $row['referencia'],
        ], $carga['regras']), ['rule_version_id', 'numero'], ['tratamento', 'referencia']);

        $this->upsert(TratamentoEnquadramento::class, array_map(function (array $row) use ($id): array {
            $sub = (string) $row['subcategoria'];

            return [
                'rule_version_id' => $id,
                'cnae' => $row['cnae'],
                'denominacao' => $row['denominacao'],
                'risco' => $row['risco'] !== '' ? $row['risco'] : 'BAIXO RISCO',
                'regra' => $row['regra'],
                'codigo_louos' => $row['codigo_louos'],
                'denominacao_louos' => $row['denominacao_louos'],
                'subcategoria' => $sub,
                'grupo' => $this->grupoDe($sub),
                'ate_m2' => $row['ate_m2'],
                'enquadramento2' => $row['enquadramento2'],
                'ate_m2_2' => $row['ate_m2_2'],
                'enquadramento3' => $row['enquadramento3'],
                'acima_m2' => $row['acima_m2'],
                'codigo_tll' => $row['codigo_tll'],
                'especificacao_tll' => $row['especificacao_tll'],
                'classificacao' => $row['classificacao'],
                'vagas' => json_encode($row['vagas'], JSON_UNESCAPED_UNICODE),
            ];
        }, $carga['enquadramentos']), ['rule_version_id', 'cnae', 'codigo_louos', 'subcategoria'], [
            'denominacao', 'risco', 'regra', 'denominacao_louos', 'grupo', 'ate_m2', 'enquadramento2', 'ate_m2_2',
            'enquadramento3', 'acima_m2', 'codigo_tll', 'especificacao_tll', 'classificacao', 'vagas',
        ]);

        $this->upsert(TratamentoCnaeBinding::class, array_map(fn (array $row): array => [
            'rule_version_id' => $id,
            'cnae' => $row['cnae'],
            'regra' => $row['regra'],
            'codigo_louos' => $row['codigo_louos'],
            'perguntas' => json_encode($row['perguntas']),
            'condicionantes' => json_encode($row['condicionantes']),
        ], $carga['bindings']), ['rule_version_id', 'cnae', 'regra', 'codigo_louos'], ['perguntas', 'condicionantes']);

        $this->upsert(TratamentoCondicionante::class, array_map(fn (array $row): array => [
            'rule_version_id' => $id,
            'numero' => $row['numero'],
            'texto' => $row['texto'],
            'observacao' => $row['observacao'],
        ], $carga['condicionantes']), ['rule_version_id', 'numero'], ['texto', 'observacao']);

        $this->upsert(TratamentoCnluItem::class, array_map(fn (array $row): array => [
            'rule_version_id' => $id,
            'codigo' => $row['codigo'],
            'texto' => $row['texto'],
        ], $carga['cnlu']), ['rule_version_id', 'codigo'], ['texto']);

        $this->upsert(TratamentoNota::class, array_map(fn (array $row): array => [
            'rule_version_id' => $id,
            'chave' => $row['chave'],
            'texto' => $row['texto'],
        ], $carga['notas']), ['rule_version_id', 'chave'], ['texto']);

        $ramos = [];

        foreach ($carga['ramos'] as $row) {
            $chave = implode('|', [
                $row['regra'],
                $row['pergunta'] !== null ? (string) $row['pergunta'] : '-',
                $row['resposta'] !== null ? (string) (int) $row['resposta'] : '-',
                $row['faixa'] ?? '-',
                $row['tipo_dirige'] !== null ? '1' : '-',
                $row['codigo_louos'] ?? '-',
                $row['bloqueia'] ? '1' : '-',
            ]);

            $ramos[$chave] = [
                'rule_version_id' => $id,
                'regra' => $row['regra'],
                'pergunta' => $row['pergunta'],
                'resposta' => $row['resposta'],
                'faixa' => $row['faixa'],
                'tipo_dirige' => $row['tipo_dirige'],
                'codigo_louos' => $row['codigo_louos'],
                'fluxo' => $row['fluxo'],
                'bloqueia' => $row['bloqueia'],
                'chave' => $chave,
            ];
        }

        $this->upsert(TratamentoRegraRamo::class, array_values($ramos), ['rule_version_id', 'chave'], ['fluxo', 'bloqueia']);

        TratamentoPendencia::query()->where('rule_version_id', $id)->delete();

        foreach ($carga['pendencias'] as $row) {
            TratamentoPendencia::query()->create([
                'rule_version_id' => $id,
                'regra' => $row['regra'],
                'motivo' => $row['motivo'],
            ]);
        }
    }

    /**
     * @param  class-string  $model
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $unique
     * @param  list<string>  $update
     */
    private function upsert(string $model, array $rows, array $unique, array $update): void
    {
        foreach (array_chunk($rows, 400) as $chunk) {
            if ($chunk !== []) {
                $model::query()->upsert($chunk, $unique, $update);
            }
        }
    }

    private function grupoDe(string $subcategoria): string
    {
        $pos = strrpos($subcategoria, '-');

        return $pos === false ? $subcategoria : substr($subcategoria, 0, $pos);
    }
}
