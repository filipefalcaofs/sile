<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdatePrimaryCnaeRequest;
use App\Http\Requests\Portal\UpdateSecondaryCnaesRequest;
use App\Models\Cnae;
use App\Models\Company;
use App\Services\CompanyCnaeService;
use Illuminate\Http\RedirectResponse;

/**
 * Vínculo de CNAEs da empresa (HU-025/HU-026). TODA escrita do pivot é
 * delegada ao CompanyCnaeService (transação + auditoria antes/depois) —
 * sync direto aqui é anti-pattern ([03-RESEARCH]).
 */
class CompanyCnaeController extends Controller
{
    public function __construct(private CompanyCnaeService $service) {}

    /**
     * Define/troca o CNAE principal (HU-025).
     */
    public function updatePrimary(UpdatePrimaryCnaeRequest $request, Company $company): RedirectResponse
    {
        $this->service->setPrimary($company, Cnae::findOrFail($request->validated('cnae_id')));

        return back()->with('status', 'CNAE principal definido com sucesso.');
    }

    /**
     * Sincroniza o conjunto exato de CNAEs secundários (HU-026).
     */
    public function updateSecondaries(UpdateSecondaryCnaesRequest $request, Company $company): RedirectResponse
    {
        $this->service->syncSecondaries($company, $request->validated('cnaes'));

        return back()->with('status', 'CNAEs secundários atualizados com sucesso.');
    }
}
