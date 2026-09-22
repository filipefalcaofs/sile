<?php

namespace App\Services\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Gera (ou regenera) o rascunho do exercício seguinte da TLL: clona as
 * linhas ativas da origem, aplica o fator do decreto e liga à versão de
 * regra do domínio tll_valores. Destino já vigente não regenera.
 */
class TllPropagacaoExercicio
{
    public function __construct(
        private RuleVersionService $ruleVersions,
        private AuditService $audit,
    ) {}

    public function propagar(int $origem, int $destino, string $fator, string $decreto, int $autorId): RuleVersion
    {
        $fatorFloat = (float) $fator;

        if ($fatorFloat <= 0 || $fatorFloat > 2) {
            throw new DomainException('Fator de atualização inválido.');
        }

        $ativas = TllValor::query()->active()->where('exercicio', $origem)->get();

        if ($ativas->isEmpty()) {
            throw new DomainException("Nenhum valor ativo no exercício {$origem} para propagar.");
        }

        $versao = $this->ruleVersions->openDraft(
            RuleDomain::TllValores,
            (string) $destino,
            $decreto,
            $autorId,
        );

        if ($versao->status === RuleVersionStatus::Vigente) {
            throw new DomainException("O exercício {$destino} já está publicado e não pode ser regerado.");
        }

        if ($versao->source !== $decreto) {
            $versao->update(['source' => $decreto]);
        }

        $chaves = $ativas->map(
            fn (TllValor $linha): string => $linha->codigo_tll."\0".$linha->especificacao,
        )->all();

        DB::transaction(function () use ($ativas, $destino, $fatorFloat, $versao, $chaves): void {
            foreach ($ativas as $linha) {
                TllValor::query()->updateOrCreate(
                    [
                        'codigo_tll' => $linha->codigo_tll,
                        'exercicio' => $destino,
                        'especificacao' => $linha->especificacao,
                    ],
                    [
                        'valor' => number_format((float) $linha->valor * $fatorFloat, 2, '.', ''),
                        'taxa_servico' => number_format((float) $linha->taxa_servico * $fatorFloat, 2, '.', ''),
                        'codigo_tll_sefaz' => $linha->codigo_tll_sefaz,
                        'codigo_servico_sefaz' => $linha->codigo_servico_sefaz,
                        'servico_sefaz' => $linha->servico_sefaz,
                        'active' => true,
                        'rule_version_id' => $versao->id,
                    ],
                );
            }

            TllValor::query()
                ->where('exercicio', $destino)
                ->where('rule_version_id', $versao->id)
                ->get()
                ->each(function (TllValor $linha) use ($chaves): void {
                    $chave = $linha->codigo_tll."\0".$linha->especificacao;

                    if (! in_array($chave, $chaves, true)) {
                        $linha->update(['active' => false]);
                    }
                });
        });

        $this->audit->log(
            'regras',
            'tll-exercicio-gerado',
            "Exercício {$destino} da TLL gerado a partir de {$origem}",
            properties: [
                'origem' => $origem,
                'destino' => $destino,
                'fator' => $fator,
                'decreto' => $decreto,
                'linhas' => $ativas->count(),
            ],
            subject: $versao,
        );

        return $versao->fresh() ?? $versao;
    }
}
