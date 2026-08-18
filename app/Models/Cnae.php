<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\CnaeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Subclasse CNAE (HU-011). O banco guarda o código em dígitos ('0111301');
 * a exibição usa o accessor formatted_code ('0111-3/01'). A carga oficial
 * vem do CnaeImportService (sem model events); o CRUD manual é auditado
 * automaticamente via HasAuditoria (CA-02).
 */
#[Fillable(['code', 'description', 'section_code', 'section_description', 'division_code', 'division_description', 'group_code', 'group_description', 'class_code', 'class_description', 'active', 'exige_rt', 'exige_rt_se_alto', 'exige_fator_multiplicador', 'exige_detalhamento_multiplicador'])]
class Cnae extends Model
{
    use HasAuditoria;

    /** @use HasFactory<CnaeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'exige_rt' => 'boolean',
            'exige_rt_se_alto' => 'boolean',
            'exige_fator_multiplicador' => 'boolean',
            'exige_detalhamento_multiplicador' => 'boolean',
        ];
    }

    /**
     * Código no formato oficial DDDD-D/SS.
     *
     * @return Attribute<string, never>
     */
    protected function formattedCode(): Attribute
    {
        return Attribute::make(
            get: fn () => preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $this->code),
        );
    }

    /**
     * Empresas que vinculam este CNAE (principal ou secundário).
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_cnae')->withPivot('is_primary')->withTimestamps();
    }
}
