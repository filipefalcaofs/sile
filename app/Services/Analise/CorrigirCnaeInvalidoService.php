<?php

namespace App\Services\Analise;

use App\Models\Cnae;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Sefaz\ConsultarComparacaoCnaesService;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * O analista troca, na ficha, o código CNAE que a comparação com a SEFAZ marcou
 * como inválido por um código da lista oficial. A comparação é refeita e a ficha
 * recalculada; o produto de viabilidade sai conforme a ficha.
 */
class CorrigirCnaeInvalidoService
{
    public function __construct(
        private ConsultarComparacaoCnaesService $comparacao,
        private PreAnaliseService $preAnalise,
        private AnalysisRecordService $fichas,
        private AuditService $audit,
    ) {}

    public function corrigir(ViabilityRequest $processo, string $codigoAtual, string $codigoNovo, User $ator): void
    {
        DB::transaction(function () use ($processo, $codigoAtual, $codigoNovo, $ator): void {
            $atual = Cnae::query()->where('code', $codigoAtual)->firstOrFail();
            $novo = Cnae::query()->where('code', $codigoNovo)->firstOrFail();
            $principal = (bool) $processo->cnaes()->where('cnaes.id', $atual->id)->first()?->pivot->is_primary;

            $processo->cnaes()->detach($atual->id);
            $processo->cnaes()->syncWithoutDetaching([$novo->id => ['is_primary' => $principal]]);

            $this->comparacao->recalcular($processo);

            $this->audit->log(
                logName: 'analise',
                event: 'cnae-invalido-corrigido',
                description: "Código CNAE inválido corrigido na ficha do processo #{$processo->id}",
                properties: [
                    'viability_request_id' => $processo->id,
                    'protocol_number' => $processo->protocol_number,
                    'codigo_atual' => $codigoAtual,
                    'codigo_novo' => $codigoNovo,
                    'ator_id' => $ator->id,
                    'ator_nome' => $ator->name,
                    'tela' => 'ficha-analise',
                ],
                subject: $processo,
            );
        });

        $this->preAnalise->recalcularFicha($processo, $this->fichas->current($processo));
    }
}
