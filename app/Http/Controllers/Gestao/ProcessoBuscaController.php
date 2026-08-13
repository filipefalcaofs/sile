<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Busca global da retaguarda (HU-082 RN-009 — atalho Cmd/Ctrl+K). Endpoint LEVE:
 * casa o termo contra os identificadores e dados-chave do processo (número do
 * processo, BAP, produto TVL, CNPJ e nome da empresa/requerente) com whereLike
 * caseSensitive:false (case-insensitive em PostgreSQL e SQLite) e devolve poucos
 * resultados em JSON para levar direto ao processo, sem passar pela tela de
 * filtros (HU-082 index). Gated por consultar-solicitacoes (reuso). Consulta
 * leve: NÃO audita por padrão (preferência por não poluir a trilha — o acesso ao
 * processo é auditado no show); o 403 é auditado no ponto único.
 */
class ProcessoBuscaController extends Controller
{
    /** Teto de resultados (constante técnica — atalho rápido, não listagem). */
    private const LIMITE = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $termo = trim($request->string('q')->toString());

        if ($termo === '') {
            return response()->json(['resultados' => []]);
        }

        $resultados = ViabilityRequest::query()
            ->with('company:id,legal_name,trade_name')
            ->where(function ($query) use ($termo) {
                $query->whereLike('protocol_number', "%{$termo}%", caseSensitive: false)
                    ->orWhereLike('external_reference', "%{$termo}%", caseSensitive: false)
                    ->orWhereHas('decision', fn ($d) => $d->whereLike('tvl_product_number', "%{$termo}%", caseSensitive: false))
                    ->orWhereHas('company', fn ($c) => $c
                        ->whereLike('cnpj', "%{$termo}%", caseSensitive: false)
                        ->orWhereLike('legal_name', "%{$termo}%", caseSensitive: false)
                        ->orWhereLike('trade_name', "%{$termo}%", caseSensitive: false))
                    ->orWhereHas('requester', fn ($r) => $r->whereLike('name', "%{$termo}%", caseSensitive: false));
            })
            ->orderByDesc('id')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (ViabilityRequest $processo): array => [
                'id' => $processo->id,
                'protocolo' => $processo->protocol_number,
                'empresa' => $processo->company?->trade_name ?: $processo->company?->legal_name,
                'link' => route('gestao.processos.show', $processo),
            ])
            ->all();

        return response()->json(['resultados' => $resultados]);
    }
}
