<?php

namespace App\Http\Requests\Gestao;

use App\Enums\CondicaoServico;
use App\Models\ServicoNomenclatura;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServicoNomenclaturaRequest extends FormRequest
{
    /**
     * A autorização é o middleware permission:manter-nomenclatura-servicos da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'de' => trim((string) $this->input('de')),
            'para' => trim((string) $this->input('para')),
            'exibir' => $this->has('exibir') ? $this->boolean('exibir') : true,
            'condicao' => trim((string) $this->input('condicao')),
        ]);
    }

    /**
     * O De é único por condição, pela chave normalizada (sem caixa, acento ou
     * espaço extra).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'de' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                $existe = ServicoNomenclatura::query()
                    ->where('chave', ServicoNomenclatura::chaveDe((string) $value))
                    ->where('condicao', (string) $this->input('condicao'))
                    ->exists();

                if ($existe) {
                    $fail('Já existe uma linha para este serviço.');
                }
            }],
            'condicao' => ['present', 'string', Rule::in(['', ...array_column(CondicaoServico::cases(), 'value')])],
            'para' => ['required', 'string', 'max:255'],
            'exibir' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'de' => 'nome recebido (De)',
            'condicao' => 'condição',
            'para' => 'nome exibido (Para)',
            'exibir' => 'exibir',
        ];
    }
}
