<?php

namespace App\Http\Requests\Portal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do imóvel (HU-062) e da área utilizada (HU-063) do rascunho. A
 * autorização fina (dono + status rascunho) é feita no controller via
 * Gate::authorize('update', $solicitacao) — aqui só a forma dos dados.
 *
 * O polígono é GeoJSON Polygon com anel exterior de pelo menos 4 pontos (4
 * vértices fechando no primeiro). O complemento é TEXTO LIVRE (HU-139 adiado);
 * o ponto de referência é obrigatório (RN do HU-062). A área pública (indicador)
 * exige concessão de uso — a regra documental fica no 08-08; aqui só o flag.
 */
class UpdateSolicitacaoImovelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'property_polygon_geojson' => ['required', 'array'],
            'property_polygon_geojson.type' => ['required', 'string', 'in:Polygon'],
            'property_polygon_geojson.coordinates' => ['required', 'array', 'min:1'],
            'property_polygon_geojson.coordinates.0' => ['required', 'array', 'min:4'],
            'property_polygon_geojson.coordinates.0.*' => ['array', 'size:2'],
            'property_polygon_geojson.coordinates.0.*.*' => ['numeric'],

            'used_area_m2' => ['required', 'numeric', 'gt:0'],
            'address_reference' => ['required', 'string', 'max:255'],

            'address_street' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:50'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'address_neighborhood' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:20'],
            'property_registration' => ['nullable', 'string', 'max:255'],

            'is_virtual_office' => ['sometimes', 'boolean'],
            'wants_virtual_office_hq' => ['sometimes', 'boolean'],
            'is_public_area' => ['sometimes', 'boolean'],
            'has_independent_access' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'property_polygon_geojson.required' => 'Demarque o polígono do imóvel no mapa.',
            'property_polygon_geojson.array' => 'O polígono informado é inválido.',
            'property_polygon_geojson.type.required' => 'O polígono do imóvel é inválido.',
            'property_polygon_geojson.type.in' => 'O polígono do imóvel deve ser do tipo Polygon.',
            'property_polygon_geojson.coordinates.required' => 'O polígono do imóvel não tem coordenadas.',
            'property_polygon_geojson.coordinates.0.required' => 'Demarque o polígono do imóvel no mapa.',
            'property_polygon_geojson.coordinates.0.min' => 'O polígono deve ter pelo menos 4 pontos.',
            'property_polygon_geojson.coordinates.0.*.size' => 'Cada ponto do polígono deve ter longitude e latitude.',
            'property_polygon_geojson.coordinates.0.*.*.numeric' => 'As coordenadas do polígono devem ser numéricas.',
            'used_area_m2.required' => 'Informe a área utilizada.',
            'used_area_m2.numeric' => 'A área utilizada deve ser um número.',
            'used_area_m2.gt' => 'A área utilizada deve ser maior que zero.',
            'address_reference.required' => 'Informe um ponto de referência.',
        ];
    }
}
