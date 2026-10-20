<?php

namespace App\Http\Requests\Portal;

use App\Models\CompanyUser;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Consulta de uma viabilidade anterior (sistema legado) pelo número da TVL ou
 * do processo. A empresa precisa estar vinculada ao usuário efetivo.
 */
class ConsultarLegadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $empresa = $this->input('empresa_id');

        if (! is_numeric($empresa)) {
            return true;
        }

        $usuario = app(CurrentRepresentation::class)->grantor() ?? $this->user();

        return CompanyUser::query()
            ->where('user_id', $usuario->id)
            ->whereNull('ended_at')
            ->where('company_id', (int) $empresa)
            ->exists();
    }

    protected function prepareForValidation(): void
    {
        $numero = $this->input('numero_viabilidade');

        if (is_string($numero)) {
            $this->merge(['numero_viabilidade' => preg_replace('/\s+/', '', trim($numero))]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'empresa_id' => ['required', 'integer'],
            'numero_viabilidade' => ['required', 'string', 'max:40', 'regex:/^(\d{7}|\d{10}-\d{8}\/\d{4})$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_viabilidade.required' => 'Informe o número da viabilidade ou do processo.',
            'numero_viabilidade.regex' => 'Informe o número da viabilidade com 7 dígitos ou o número do processo no formato 0000000000-00000000/0000.',
        ];
    }
}
