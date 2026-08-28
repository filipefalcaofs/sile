<?php

namespace App\Models;

use App\Enums\SefazNotificationEvent;
use App\Enums\SefazNotificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comunicação devida à SEFAZ sobre uma condição cadastral de escritório
 * virtual (`Alteração de Endereço` §4.3.2, RN-EV-09/EV-10). Registro de
 * primeira classe: substitui o texto solto que a auditoria da desvinculação
 * gravava. `retorno` guarda a resposta bruta da integração, quando disponível.
 * Ela própria É o payload que o gateway envia — o reprocessamento reusa este
 * registro, sem remontar dados.
 */
#[Fillable([
    'viability_request_id', 'event', 'cnpj',
    'property_registration_anterior', 'property_registration_nova',
    'endereco_anterior', 'endereco_novo',
    'status', 'tentativas', 'erro', 'retorno', 'enviada_em',
])]
class SefazNotification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => SefazNotificationEvent::class,
            'status' => SefazNotificationStatus::class,
            'retorno' => 'array',
            'enviada_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class, 'viability_request_id');
    }

    /**
     * Comunicações reprocessáveis: Pendente (nunca tentada) ou Falha (tentada e
     * não confirmada). Enviada é terminal — fora do recorte.
     *
     * @param  Builder<SefazNotification>  $query
     */
    public function scopePendentes(Builder $query): void
    {
        $query->whereIn('status', [SefazNotificationStatus::Pendente, SefazNotificationStatus::Falha]);
    }
}
