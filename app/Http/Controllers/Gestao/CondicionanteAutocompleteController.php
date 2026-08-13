<?php

namespace App\Http\Controllers\Gestao;

use App\Enums\RuleDomain;
use App\Http\Controllers\Controller;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autocomplete de condicionantes para a ficha de análise (T02 EV) — serve os
 * itens do cadastro VERSIONADO VIGENTE (a "tela de Condicionantes" da gestão,
 * escopada à RuleVersion sanitária vigente, RN-005). O rótulo é o texto de
 * parecer da condicionante (fallback: a própria pergunta). Degrada honesto:
 * sem versão vigente, lista vazia — jamais itens inventados. Gated por
 * analisar-processos (a rota já aplica a permissão).
 */
class CondicionanteAutocompleteController extends Controller
{
    private const LIMITE = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $termo = (string) $request->string('q')->trim();

        $versao = RuleVersion::vigente(RuleDomain::RiscoSanitario)->first();

        $itens = RiskCondicionante::query()
            ->where('rule_version_id', $versao?->id ?? 0)
            ->when($termo !== '', function ($query) use ($termo): void {
                $digitos = preg_replace('/\D/', '', $termo);

                $query->where(function ($inner) use ($termo, $digitos): void {
                    $inner->whereLike('pergunta', "%{$termo}%", caseSensitive: false)
                        ->orWhereLike('texto_parecer', "%{$termo}%", caseSensitive: false);

                    if ($digitos !== '') {
                        $inner->orWhere('cnae_code', 'like', "{$digitos}%");
                    }
                });
            })
            ->orderBy('id')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (RiskCondicionante $condicionante): array => [
                'id' => $condicionante->id,
                'label' => $condicionante->texto_parecer ?: $condicionante->pergunta,
                'cnae_code' => $condicionante->cnae_code,
            ])
            ->all();

        return response()->json([
            'data' => $itens,
            'versao' => $versao?->version,
        ]);
    }
}
