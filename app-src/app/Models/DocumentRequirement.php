<?php

namespace App\Models;

use App\Concerns\HasAuditoria;
use Database\Factories\DocumentRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Requisito documental (modelo "Requisito" do SIGVISA — HU-067). required marca
 * o obrigatório-base; a obrigatoriedade por atividade vem do pivot com CNAE.
 * validation_instructions é gancho para a validação por IA (EP14), inerte aqui.
 * Auditoria automática via HasAuditoria (RN-002); o vínculo com CNAEs é auditado
 * explicitamente no controller (relações não entram no diff do HasAuditoria).
 */
#[Fillable(['code', 'name', 'description', 'required', 'active', 'validation_instructions'])]
class DocumentRequirement extends Model
{
    use HasAuditoria;

    /** @use HasFactory<DocumentRequirementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * CNAEs que exigem este requisito (HU-067).
     *
     * @return BelongsToMany<Cnae, $this>
     */
    public function cnaes(): BelongsToMany
    {
        return $this->belongsToMany(Cnae::class, 'cnae_document_requirement')->withTimestamps();
    }
}
