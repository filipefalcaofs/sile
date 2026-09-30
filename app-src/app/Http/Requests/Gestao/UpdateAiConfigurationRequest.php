<?php

namespace App\Http\Requests\Gestao;

use App\Models\AiConfiguration;
use App\Services\Ai\AiBaseUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAiConfigurationRequest extends FormRequest
{
    /**
     * Autorização é o middleware permission:manter-config-ia da rota.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    /**
     * A chave de API é opcional na edição: em branco = manter a atual (RN-009, o
     * controller não sobrescreve). Demais campos são atualização completa.
     *
     * @return array<string, ValidationRule|array<mixed>|string|Closure>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'in:openai,anthropic,gemini,azure,compativel'],
            'capability' => ['required', 'string', 'in:text,vision,embeddings'],
            'base_url' => ['required', 'string', 'max:255', $this->baseUrlRule()],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required', 'string', 'max:255'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:1000000'],
            'timeout_ms' => ['required', 'integer', 'min:1000', 'max:120000'],
            'active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('active')) {
                return;
            }

            $configuracao = $this->route('aiConfiguration');
            $temChaveNova = filled($this->input('api_key'));
            $temChaveAtual = $configuracao instanceof AiConfiguration && filled($configuracao->api_key);

            if (! $temChaveNova && ! $temChaveAtual) {
                $validator->errors()->add('api_key', 'Informe a chave de API para ativar a configuração.');
            }
        });
    }

    protected function baseUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $error = AiBaseUrlGuard::validate(is_string($value) ? $value : null);

            if ($error !== null) {
                $fail($error);
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'provider' => 'provedor',
            'capability' => 'capacidade',
            'base_url' => 'URL base',
            'api_key' => 'chave de API',
            'model' => 'modelo',
            'temperature' => 'temperatura',
            'max_tokens' => 'máximo de tokens',
            'timeout_ms' => 'tempo limite (ms)',
            'active' => 'situação',
            'is_default' => 'configuração padrão',
        ];
    }
}
