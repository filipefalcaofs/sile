<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\ServiceDeclarationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Declaração de responsabilidade de um tipo de serviço. Textos vigentes
 * entram no wizard; a versão aceita fica no aceite, não no texto atual.
 */
#[Fillable(['service_type_id', 'body', 'version', 'required', 'valid_from', 'valid_to'])]
class ServiceDeclaration extends Model
{
    use HasAuditoria;

    /** @use HasFactory<ServiceDeclarationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'required' => 'boolean',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    /**
     * Declarações em vigor agora para o tipo de serviço.
     *
     * @return Collection<int, self>
     */
    public static function vigentesPara(int $serviceTypeId): Collection
    {
        return static::query()
            ->where('service_type_id', $serviceTypeId)
            ->where(function ($query): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->orderBy('version')
            ->get();
    }

    /**
     * @return BelongsTo<ViabilityServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ViabilityServiceType::class, 'service_type_id');
    }
}
