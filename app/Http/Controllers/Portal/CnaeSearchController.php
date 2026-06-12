<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Cnae;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Busca server-side da tabela oficial de CNAEs para os selects do portal
 * (HU-025/HU-026): retorna SOMENTE ativos — a seleção manual nunca oferece
 * CNAE inativo. Padrão de busca do CnaeController [02-04]: branch de
 * dígitos só quando o termo contém dígitos; whereLike sem case.
 */
class CnaeSearchController extends Controller
{
    /**
     * Limite técnico de itens do select com busca (não é parâmetro de
     * negócio — o refinamento vem da própria busca).
     */
    private const MAX_RESULTS = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $term = (string) $request->string('search')->trim();

        $cnaes = Cnae::query()
            ->where('active', true)
            ->when($term !== '', function ($query) use ($term) {
                $digits = preg_replace('/\D/', '', $term);

                $query->where(function ($inner) use ($term, $digits) {
                    if ($digits !== '') {
                        $inner->where('code', 'like', "{$digits}%");
                    }

                    $inner->orWhereLike('description', "%{$term}%", caseSensitive: false);
                });
            })
            ->orderBy('code')
            ->limit(self::MAX_RESULTS)
            ->get();

        return response()->json($cnaes->map(fn (Cnae $cnae) => [
            'id' => $cnae->id,
            'formatted_code' => $cnae->formatted_code,
            'description' => $cnae->description,
        ]));
    }
}
