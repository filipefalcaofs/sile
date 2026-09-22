<?php

namespace App\Http\Requests\Gestao;

use App\Support\Louos\LouosLinhaRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Publicação versionada de um Quadro da LOUOS (HU-015..018/HU-046). A
 * autorização é o middleware permission:manter-louos da rota; a regra dos
 * quatro olhos (autor distinto do publicador) é verificada no controller para
 * comunicar o bloqueio de forma controlada (flash.error), não como erro de
 * validação. A versão é única dentro do domínio do Quadro escolhido (não
 * reescreve a vigente). A estrutura de `alteracoes` é validada por Quadro, com
 * a chave natural correspondente exigida.
 */
class PublishLouosVersionRequest extends FormRequest
{
    /**
     * Param `quadro` → domínio de regra versionada (rule_versions.domain).
     *
     * @var array<string, string>
     */
    public const QUADRO_DOMAINS = [
        'quadro10' => 'louos_quadro10',
        'quadro11a' => 'louos_quadro11a',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $quadro = (string) $this->input('quadro');
        $domain = self::QUADRO_DOMAINS[$quadro] ?? '';

        return [
            'quadro' => ['required', 'string', 'in:quadro10,quadro11a'],
            'version' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rule_versions', 'version')->where('domain', $domain),
            ],
            'author_id' => ['required', 'integer', 'exists:users,id'],
            'alteracoes' => ['sometimes', 'array'],
            ...$this->alteracaoRules($quadro),
        ];
    }

    /**
     * Regras por Quadro para cada item de `alteracoes` — delega para
     * `LouosLinhaRules::forQuadro` com prefixo 'alteracoes.*.'.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function alteracaoRules(string $quadro): array
    {
        return LouosLinhaRules::forQuadro($quadro, 'alteracoes.*.');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quadro' => 'Quadro',
            'version' => 'versão',
            'author_id' => 'autor',
            'alteracoes' => 'alterações',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.unique' => 'Já existe uma versão deste Quadro da LOUOS com este identificador.',
        ];
    }
}
