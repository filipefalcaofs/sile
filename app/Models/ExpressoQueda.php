<?php

namespace App\Models;

use Database\Factories\ExpressoQuedaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro ESTRUTURADO da queda ao analista por CNAE (HU-145), gravado pelo
 * {@see App\Services\Expresso\FluxoExpressoService::encaminharAnalise} no momento
 * do encaminhamento à análise técnica. É o dado REAL que fecha o ciclo de
 * melhoria contínua do expresso (medir → parametrizar HU-143 → medir): sem ele,
 * o relatório de quedas seria fachada.
 *
 * tipo_gatilho/dimensao espelham o gatilho semi-expresso e a dimensão decisiva
 * do RiscoClassificationService; ficam NULL quando o motor degradou (toggle off
 * ou veredito pendente sem zona) — nesse caso uma única linha de nível-processo
 * (cnae NULL) carrega o motivo textual, NUNCA um gatilho inventado (RN-001).
 */
#[Fillable([
    'viability_request_id',
    'cnae',
    'tipo_gatilho',
    'dimensao',
    'motivo',
])]
class ExpressoQueda extends Model
{
    /** @use HasFactory<ExpressoQuedaFactory> */
    use HasFactory;

    protected $table = 'expresso_quedas';

    /**
     * Solicitação cujo encaminhamento à análise originou a queda.
     *
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }
}
