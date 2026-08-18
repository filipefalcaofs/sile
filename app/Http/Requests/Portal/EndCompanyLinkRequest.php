<?php

namespace App\Http\Requests\Portal;

use App\Enums\CompanyLinkRole;
use App\Models\Company;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EndCompanyLinkRequest extends FormRequest
{
    /**
     * Encerrar o próprio vínculo exige vínculo ATIVO do usuário efetivo
     * (policy endLink, HU-028). O 403 é auditado globalmente (CA-04).
     */
    public function authorize(): bool
    {
        return $this->user()->can('endLink', $this->route('company'));
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'ended_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Proteção do último responsável ativo (HU-028 CA-03, precedente
     * anti-lockout [02-05]): a empresa nunca fica sem NENHUM responsável
     * ativo. A regra conta APENAS o papel responsável — um procurador único
     * pode encerrar se houver responsável ativo.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Company $company */
                $company = $this->route('company');

                $effectiveUser = app(CurrentRepresentation::class)->grantor() ?? $this->user();

                $link = $company->activeLinks()
                    ->where('user_id', $effectiveUser->id)
                    ->first();

                if ($link?->role === CompanyLinkRole::Responsavel
                    && ! $company->activeLinks()
                        ->where('role', CompanyLinkRole::Responsavel)
                        ->whereKeyNot($link->id)
                        ->exists()
                ) {
                    $validator->errors()->add('vinculo', 'A empresa não pode ficar sem responsável ativo.');
                }
            },
        ];
    }
}
