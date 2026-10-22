<?php

namespace App\Http\Requests\Gestao;

use App\Enums\AnalysisStatus;
use App\Models\User;
use App\Support\PerfisDeChefia;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação da mudança manual de status de análise. A autorização é o
 * middleware permission:analisar-processos da rota. `motivo` é opcional na
 * Fase 1 — a obrigatoriedade de parecer para convite_cancelado/override
 * chega na Fase 2 (convite).
 */
class AnalysisStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AnalysisStatus::class)],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'force' => ['sometimes', 'boolean'],
            'sector_id' => ['required_if:status,encaminhado', 'nullable', 'integer', 'exists:sectors,id'],
            'user_id' => ['required_if:status,encaminhado', 'nullable', 'integer', 'exists:users,id'],
            // Para conclusão não tem destinatário: o Apoio distribui pela aba.
            'encaminhado_para_user_id' => ['prohibited'],
            // Indicação opcional de quem concluir: só sugestão para o Apoio.
            'indicado_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail): void {
                $indicavel = User::query()->whereKey($value)->role(PerfisDeChefia::NOMES)->exists();

                if (! $indicavel) {
                    $fail('A indicação só aceita usuários com perfil de Chefe de setor, Subcoordenador ou Coordenador.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'encaminhado_para_user_id.prohibited' => 'O processo vai para a aba Para conclusão e é distribuído pelo Apoio: não escolha destinatário.',
        ];
    }
}
