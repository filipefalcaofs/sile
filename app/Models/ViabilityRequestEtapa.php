<?php

namespace App\Models;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use Database\Factories\ViabilityRequestEtapaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado de uma etapa do motor que depende de serviço de terceiro, uma linha
 * por processo e etapa. A trilha de cada tentativa fica em activity_log.
 */
#[Fillable([
    'viability_request_id',
    'etapa',
    'servico',
    'situacao',
    'motivo',
    'tentativas',
    'primeira_falha_em',
    'ultima_tentativa_em',
    'proxima_tentativa_em',
    'esgotada_em',
])]
class ViabilityRequestEtapa extends Model
{
    /** @use HasFactory<ViabilityRequestEtapaFactory> */
    use HasFactory;

    protected $table = 'viability_request_etapas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etapa' => EtapaMotor::class,
            'situacao' => SituacaoEtapa::class,
            'tentativas' => 'integer',
            'primeira_falha_em' => 'datetime',
            'ultima_tentativa_em' => 'datetime',
            'proxima_tentativa_em' => 'datetime',
            'esgotada_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }
}
