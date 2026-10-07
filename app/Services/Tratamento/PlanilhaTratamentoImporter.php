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
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class PlanilhaTratamentoImporter
{
    /**
     * Regras 41 e 42 (CNAE 8211-3/00) são decididas pelo fluxo de escritório
     * virtual, não por ramo da planilha.
     *
     * @var list<int>
     */
    private const REGRAS_DO_ESCRITORIO_VIRTUAL = [41, 42];

    /**
     * Regras 40 e 43 citam as opções da pergunta 1, que a planilha não traz.
     *
     * @var list<int>
     */
    private const REGRAS_COM_OPCOES_DA_PERGUNTA_1 = [40, 43];

    public function __construct(
        private PlanilhaTratamentoWorkbook $workbook,
        private RuleVersionService $versions,
        private AuditService $audit,
        private DecisionTextCatalog $textos = new DecisionTextCatalog,
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
     * Completa a versão carregada dos CSVs com o que só a planilha traz:
     * metadados e vagas por enquadramento, lista CNLU, notas gerais e as regras
     * sem fluxo curado como pendência. Não toca nos ramos curados.
     *
     * @return array{atualizados: int, sem_correspondencia: int, cnlu: int, pendencias: int}
     */
    public function complementar(RuleVersion $versao, string $path): array
    {
        $carga = $this->workbook->ler($path);
        $id = $versao->getKey();

        return DB::transaction(function () use ($carga, $id, $versao, $path): array {
            $atualizados = 0;
            $semCorrespondencia = 0;

            foreach ($carga['enquadramentos'] as $row) {
                $afetadas = TratamentoEnquadramento::query()
                    ->where('rule_version_id', $id)
                    ->where('cnae', $row['cnae'])
                    ->where('codigo_louos', $row['codigo_louos'])
                    ->where('subcategoria', (string) $row['subcategoria'])
                    ->update([
                        'vagas' => json_encode($row['vagas'], JSON_UNESCAPED_UNICODE),
                        'metadados' => json_encode($row['metadados'], JSON_UNESCAPED_UNICODE),
                    ]);

                $afetadas > 0 ? $atualizados++ : $semCorrespondencia++;
            }

            TratamentoCnluItem::query()->where('rule_version_id', $id)->delete();
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

            $ramosSedur = dirname($path).'/regras-fluxo-sedur.json';

            if (is_file($ramosSedur)) {
                (new TratamentoRamosSedur)->carregar($versao, $ramosSedur);
            }

            $pendencias = $this->pendenciasSemFluxoCurado($id, $carga['regras']);

            return [
                'atualizados' => $atualizados,
                'sem_correspondencia' => $semCorrespondencia,
                'cnlu' => count($carga['cnlu']),
                'pendencias' => $pendencias,
            ];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $regras
     */
    private function pendenciasSemFluxoCurado(int $versaoId, array $regras): int
    {
        $comRamo = TratamentoRegraRamo::query()
            ->where('rule_version_id', $versaoId)
            ->pluck('regra')
            ->map(fn (mixed $regra): int => (int) $regra)
            ->all();

        TratamentoPendencia::query()->where('rule_version_id', $versaoId)->delete();

        $total = 0;

        foreach ($regras as $regra) {
            $numero = (int) $regra['numero'];

            if (in_array($numero, $comRamo, true) || in_array($numero, self::REGRAS_DO_ESCRITORIO_VIRTUAL, true)) {
                continue;
            }

            TratamentoPendencia::query()->create([
                'rule_version_id' => $versaoId,
                'regra' => $numero,
                'motivo' => $this->textos->get(
                    in_array($numero, self::REGRAS_COM_OPCOES_DA_PERGUNTA_1, true)
                        ? 'tratamento.pendencia.opcoes_pergunta_1'
                        : 'tratamento.pendencia.sem_fluxo_curado',
                ),
            ]);
            $total++;
        }

        return $total;
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
                'metadados' => json_encode($row['metadados'], JSON_UNESCAPED_UNICODE),
            ];
        }, $carga['enquadramentos']), ['rule_version_id', 'cnae', 'codigo_louos', 'subcategoria'], [
            'denominacao', 'risco', 'regra', 'denominacao_louos', 'grupo', 'ate_m2', 'enquadramento2', 'ate_m2_2',
            'enquadramento3', 'acima_m2', 'codigo_tll', 'especificacao_tll', 'classificacao', 'vagas', 'metadados',
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
