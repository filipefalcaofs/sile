<?php

namespace App\Http\Requests\Gestao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Polígono redesenhado na vistoria: GeoJSON Polygon com um anel de ao menos
 * 4 posições [lng, lat] FECHADO (primeira == última). A estrutura é validada
 * numa única passada e o erro vai para a chave `polygon` — a tela mostra uma
 * mensagem só, sob o mapa.
 */
class InspectionPolygonRequest extends FormRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('polygon')) {
                return;
            }

            if (! $this->poligonoValido($this->input('polygon'))) {
                $validator->errors()->add(
                    'polygon',
                    'O polígono informado é inválido: desenhe um anel fechado com ao menos 3 vértices.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'polygon.required' => 'Desenhe o polígono no mapa antes de validar.',
        ];
    }

    private function poligonoValido(mixed $polygon): bool
    {
        if (! is_array($polygon) || ($polygon['type'] ?? null) !== 'Polygon') {
            return false;
        }

        $anel = $polygon['coordinates'][0] ?? null;

        if (! is_array($anel) || count($anel) < 4) {
            return false;
        }

        foreach ($anel as $posicao) {
            if (! is_array($posicao) || count($posicao) !== 2) {
                return false;
            }

            [$lng, $lat] = array_values($posicao);

            if (! is_numeric($lng) || ! is_numeric($lat)) {
                return false;
            }

            if ((float) $lng < -180 || (float) $lng > 180 || (float) $lat < -90 || (float) $lat > 90) {
                return false;
            }
        }

        $primeira = array_values($anel[0]);
        $ultima = array_values($anel[count($anel) - 1]);

        return (float) $primeira[0] === (float) $ultima[0] && (float) $primeira[1] === (float) $ultima[1];
    }
}
