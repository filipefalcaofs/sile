<?php

namespace App\Services;

use App\Models\Cnae;
use App\Models\Company;
use App\Support\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * ÚNICO ponto de escrita do pivot company_cnae (HU-025/HU-026): garante a
 * invariante "exatamente um principal" na aplicação (precedente [01-07]) e
 * registra auditoria explícita com antes/depois — relações não entram no
 * diff do HasAuditoria, e a trilha alimenta o motor de regras (Fases 5/6).
 */
class CompanyCnaeService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Define o CNAE principal em transação: demove o antigo (que permanece
     * vinculado como secundário) e promove o novo — ordem demote→promote
     * para nunca existirem dois principais (Pitfall 7).
     */
    public function setPrimary(Company $company, Cnae $cnae): void
    {
        DB::transaction(function () use ($company, $cnae) {
            $previous = $company->primaryCnae()->first();

            $company->cnaes()->wherePivot('is_primary', true)->newPivotQuery()->update(['is_primary' => false]);
            $company->cnaes()->syncWithoutDetaching([$cnae->id => ['is_primary' => true]]);

            $this->audit->log('empresas', 'cnae-principal', 'CNAE principal definido', [
                'empresa_id' => $company->id,
                'cnae_anterior' => $previous?->code,
                'cnae_novo' => $cnae->code,
            ], $company);
        });
    }

    /**
     * Sincroniza o conjunto EXATO de secundários (intenção do usuário =
     * conjunto marcado, padrão syncPermissions [02-06]), preservando a
     * linha do principal no payload do sync.
     *
     * @param  array<int, int>  $cnaeIds
     */
    public function syncSecondaries(Company $company, array $cnaeIds): void
    {
        DB::transaction(function () use ($company, $cnaeIds) {
            $before = $company->cnaes()->wherePivot('is_primary', false)->pluck('code')->all();

            $payload = [];

            if ($primary = $company->primaryCnae()->first()) {
                $payload[$primary->id] = ['is_primary' => true];
            }

            foreach ($cnaeIds as $id) {
                $payload[$id] = ['is_primary' => false];
            }

            $company->cnaes()->sync($payload);

            $after = Cnae::query()->whereIn('id', $cnaeIds)->pluck('code')->all();

            $this->audit->log('empresas', 'cnaes-secundarios', 'CNAEs secundários atualizados', [
                'empresa_id' => $company->id,
                'antes' => $before,
                'depois' => $after,
            ], $company);
        });
    }
}
