<?php

namespace App\Services\Geo;

use App\Models\ViabilityRequest;
use Illuminate\Support\Str;

/**
 * Compara o logradouro declarado no processo com o logradouro tributário da
 * inscrição imobiliária (SEFAZ). Só aponta divergência quando os dois existem
 * e os nomes, sem tipo, caixa, acento e preposição, não coincidem. Um nome que
 * está contido no outro não conta como divergência.
 */
class DivergenciaLogradouroInscricao
{
    private const TIPOS_DE_LOGRADOURO = [
        'RUA', 'R', 'AVENIDA', 'AV', 'AVN', 'TRAVESSA', 'TV', 'TRAV', 'ALAMEDA', 'AL', 'ESTRADA', 'EST',
        'PRACA', 'PCA', 'PC', 'LADEIRA', 'LAD', 'BECO', 'LARGO', 'LGO', 'RODOVIA', 'ROD', 'VIA', 'VIELA',
        'PASSAGEM', 'PSG', 'CAMINHO', 'CAM',
    ];

    private const PREPOSICOES = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'E'];

    /**
     * @return array{declarado: string, sefaz: string}|null
     */
    public function avaliar(ViabilityRequest $request): ?array
    {
        $declarado = trim((string) $request->address_street);
        $tributario = data_get($request->camadas_incidentes, 'logradouro_tributario.nome');
        $tributario = is_string($tributario) ? trim($tributario) : '';

        if (! $this->diverge($declarado, $tributario)) {
            return null;
        }

        return ['declarado' => $declarado, 'sefaz' => $tributario];
    }

    public function diverge(?string $declarado, ?string $tributario): bool
    {
        $nomeDeclarado = $this->palavras($declarado);
        $nomeTributario = $this->palavras($tributario);

        if ($nomeDeclarado === [] || $nomeTributario === []) {
            return false;
        }

        return array_diff($nomeDeclarado, $nomeTributario) !== []
            && array_diff($nomeTributario, $nomeDeclarado) !== [];
    }

    /**
     * @return list<string>
     */
    private function palavras(?string $logradouro): array
    {
        $texto = Str::upper(Str::ascii((string) $logradouro));
        $palavras = preg_split('/[^A-Z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($palavras !== [] && in_array($palavras[0], self::TIPOS_DE_LOGRADOURO, true)) {
            array_shift($palavras);
        }

        return array_values(array_unique(array_diff($palavras, self::PREPOSICOES)));
    }
}
