<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de localização por sobreposição (HU-037 RN-004): recebe o polígono
 * informado como GeoJSON Polygon. PADRÃO CROSS-GUARD do território (04-03) — o
 * gate é o middleware permission:consultar-territorio; authorize() retorna true
 * (ver IdentifyTerritoryRequest). A estrutura mínima do GeoJSON (type Polygon +
 * coordinates) é validada aqui; a topologia é responsabilidade do PostGIS.
 */
class ValidateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'polygon' => ['required', 'array'],
            'polygon.type' => ['required', 'string', 'in:Polygon'],
            'polygon.coordinates' => ['required', 'array'],
        ];
    }
}
