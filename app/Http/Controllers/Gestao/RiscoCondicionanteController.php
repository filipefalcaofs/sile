<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RiscoSanitario;
use App\Enums\RuleDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreRiscoCondicionanteRequest;
use App\Http\Requests\Gestao\UpdateRiscoCondicionanteRequest;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD das condicionantes-pergunta de risco (HU-019), escopado à versão
 * sanitária VIGENTE (rule_version_id). Espelha o CnaeController (server-driven,
 * FormRequest, flash status pt-BR). A auditoria do CRUD é automática via
 * HasAuditoria do RiskCondicionante (CA-02); o gate é o middleware
 * permission:manter-risco / consultar-risco das rotas.
 */
class RiscoCondicionanteController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public function index(Request $request): Response
    {
        $versao = RuleVersion::vigente(RuleDomain::RiscoSanitario)->first();

        $perPage = (int) $request->input('per_page');
        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : (int) Settings::get('ui.cnaes.per_page', 15);

        $condicionantes = RiskCondicionante::query()
            ->where('rule_version_id', $versao?->id ?? 0)
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $term = (string) $request->string('search')->trim();
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function ($inner) use ($term, $digits) {
                    if ($digits !== '') {
                        $inner->where('cnae_code', 'like', "{$digits}%");
                    }

                    $inner->orWhereLike('pergunta', "%{$term}%", caseSensitive: false);
                });
            })
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (RiskCondicionante $condicionante) => [
                'id' => $condicionante->id,
                'cnae_code' => $condicionante->cnae_code,
                'formatted_code' => $this->formatCnae($condicionante->cnae_code),
                'pergunta' => $condicionante->pergunta,
                'tipo_resposta' => $condicionante->tipo_resposta->value,
                'regra_reclassificacao' => $condicionante->regra_reclassificacao,
                'texto_parecer' => $condicionante->texto_parecer,
            ]);

        return Inertia::render('gestao/risco/condicionantes', [
            'condicionantes' => $condicionantes,
            'versaoSanitaria' => $versao === null ? null : [
                'version' => $versao->version,
                'valid_from' => $versao->valid_from?->toDateString(),
            ],
            'filtros' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'niveisReclassificacao' => $this->niveisReclassificacao(),
        ]);
    }

    public function store(StoreRiscoCondicionanteRequest $request): RedirectResponse
    {
        $versao = RuleVersion::vigente(RuleDomain::RiscoSanitario)->first();

        if ($versao === null) {
            return back()->with(
                'error',
                'Não há versão vigente de risco sanitário para vincular a condicionante.',
            );
        }

        RiskCondicionante::create([
            'rule_version_id' => $versao->id,
            ...$request->validated(),
        ]);

        return back()->with('status', 'Condicionante cadastrada com sucesso.');
    }

    public function update(UpdateRiscoCondicionanteRequest $request, RiskCondicionante $condicionante): RedirectResponse
    {
        $condicionante->update($request->validated());

        return back()->with('status', 'Condicionante atualizada com sucesso.');
    }

    public function destroy(RiskCondicionante $condicionante): RedirectResponse
    {
        $condicionante->delete();

        return back()->with('status', 'Condicionante removida.');
    }

    /**
     * Opções de nível para a regra de reclassificação (contrato do formulário
     * da UI 06-08) — dimensão sanitária (baixo/medio/alto).
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function niveisReclassificacao(): array
    {
        return array_map(
            fn (RiscoSanitario $nivel) => ['value' => $nivel->value, 'label' => $nivel->label()],
            RiscoSanitario::cases(),
        );
    }

    /**
     * Código no formato oficial DDDD-D/SS (a condicionante pode ser geral,
     * sem CNAE — nesse caso não há código a formatar).
     */
    private function formatCnae(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $code);
    }
}
