<?php

namespace App\Http\Requests\Gestao;

use App\Models\Company;
use App\Models\User;
use App\Rules\ValidCpf;
use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro de solicitação em CONTINGÊNCIA na retaguarda (HU-148). A permissão é
 * o middleware permission:registrar-contingencia (CA-04); aqui validamos o MESMO
 * conjunto de dados do formulário oficial. O beneficiário (cidadão) é informado
 * por CPF e a empresa por CNPJ — ambos precisam existir no sistema (o protocolo
 * exige empresa, e o requerente é um usuário). Motivo obrigatório (RN-001). Os
 * tipos aceitos de anexo e o limite de complementares são parametrizados (HU-014)
 * e lidos dinamicamente do catálogo (banco→cache→config), efeito sem deploy.
 */
class RegistrarContingenciaRequest extends FormRequest
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
        $maxComplementares = (int) Settings::get(
            'solicitacao.cnaes_complementares.max',
            config('sile.solicitacao.cnaes_complementares.max', 99),
        );

        /** @var array<int, string> $mimes */
        $mimes = (array) Settings::get(
            'solicitacao.anexos.mime_permitidos',
            config('sile.solicitacao.anexos.mime_permitidos', ['application/pdf', 'image/jpeg', 'image/png']),
        );

        $maxKb = (int) Settings::get(
            'solicitacao.anexos.max_mb',
            config('sile.solicitacao.anexos.max_mb', 10),
        ) * 1024;

        return [
            // Beneficiário (cidadão) e empresa: a existência é confirmada no after().
            'beneficiary_cpf' => ['required', 'string', new ValidCpf],
            'company_cnpj' => ['required', 'string', 'max:18'],

            'service_type_id' => ['nullable', 'integer', Rule::exists('viability_service_types', 'id')->where('active', true)],

            // Motivo da contingência é obrigatório (RN-001).
            'contingency_reason' => ['required', 'string', 'max:2000'],
            // Referência externa (BAP/Regin informado pelo requerente) — opcional.
            'external_reference' => ['nullable', 'string', 'max:255'],

            // Imóvel/polígono (GeoJSON Polygon com anel de pelo menos 4 pontos).
            'property_polygon_geojson' => ['required', 'array'],
            'property_polygon_geojson.type' => ['required', 'in:Polygon'],
            'property_polygon_geojson.coordinates' => ['required', 'array', 'size:1'],
            'property_polygon_geojson.coordinates.0' => ['required', 'array', 'min:4'],
            'property_polygon_geojson.coordinates.0.*' => ['array', 'size:2'],
            'property_polygon_geojson.coordinates.0.*.*' => ['numeric'],

            'used_area_m2' => ['required', 'numeric', 'gt:0'],

            'address_street' => ['nullable', 'string', 'max:255'],
            'address_number' => ['nullable', 'string', 'max:50'],
            'address_complement' => ['nullable', 'string', 'max:255'],
            'address_neighborhood' => ['nullable', 'string', 'max:255'],
            'address_zip' => ['nullable', 'string', 'max:20'],
            'address_reference' => ['nullable', 'string', 'max:255'],

            'is_virtual_office' => ['sometimes', 'boolean'],
            'is_public_area' => ['sometimes', 'boolean'],
            'has_independent_access' => ['sometimes', 'boolean'],

            // Atividades: principal obrigatória + complementares ativas e únicas.
            'principal_cnae_id' => ['required', 'integer', Rule::exists('cnaes', 'id')->where('active', true)],
            'complementares' => ['nullable', 'array', "max:{$maxComplementares}"],
            'complementares.*' => ['integer', 'distinct', Rule::exists('cnaes', 'id')->where('active', true)],

            // Anexos opcionais, mapeados por requirement_id (documents[{id}] = arquivo).
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimetypes:'.implode(',', $mimes), "max:{$maxKb}"],
        ];
    }

    /**
     * Beneficiário e empresa precisam existir; o CNAE principal não pode estar
     * entre os complementares (intenções distintas, espelha o [08-07]).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('beneficiary_cpf') && $this->resolveBeneficiary() === null) {
                    $validator->errors()->add('beneficiary_cpf', 'Nenhum cidadão cadastrado com este CPF.');
                }

                if (! $validator->errors()->has('company_cnpj') && $this->resolveCompany() === null) {
                    $validator->errors()->add('company_cnpj', 'Nenhuma empresa cadastrada com este CNPJ.');
                }

                $principal = (int) $this->input('principal_cnae_id');
                $complementares = array_map('intval', (array) $this->input('complementares', []));

                if ($principal !== 0 && in_array($principal, $complementares, true)) {
                    $validator->errors()->add('complementares', 'A atividade principal não pode estar entre os CNAEs complementares.');
                }
            },
        ];
    }

    /**
     * Beneficiário (cidadão) resolvido por CPF — não nulo após validação.
     */
    public function beneficiaryUser(): User
    {
        $user = $this->resolveBeneficiary();

        abort_if($user === null, 422);

        return $user;
    }

    /**
     * Empresa beneficiária resolvida por CNPJ — não nula após validação.
     */
    public function beneficiaryCompany(): Company
    {
        $company = $this->resolveCompany();

        abort_if($company === null, 422);

        return $company;
    }

    private function resolveBeneficiary(): ?User
    {
        $cpf = preg_replace('/\D/', '', (string) $this->input('beneficiary_cpf'));

        if ($cpf === '') {
            return null;
        }

        return User::query()->where('cpf', $cpf)->first();
    }

    private function resolveCompany(): ?Company
    {
        $cnpj = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('company_cnpj')));

        if ($cnpj === '') {
            return null;
        }

        return Company::query()->where('cnpj', $cnpj)->first();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'beneficiary_cpf.required' => 'Informe o CPF do beneficiário.',
            'company_cnpj.required' => 'Informe o CNPJ da empresa beneficiária.',
            'contingency_reason.required' => 'Informe o motivo da contingência.',
            'property_polygon_geojson.required' => 'Defina o imóvel (polígono) no mapa.',
            'property_polygon_geojson.coordinates.0.min' => 'O polígono do imóvel precisa de pelo menos 4 pontos.',
            'used_area_m2.required' => 'Informe a área utilizada.',
            'used_area_m2.gt' => 'A área utilizada deve ser maior que zero.',
            'principal_cnae_id.required' => 'Selecione a atividade principal.',
            'principal_cnae_id.exists' => 'A atividade principal informada não está ativa na tabela oficial.',
            'complementares.max' => 'O número de CNAEs complementares excede o limite permitido.',
            'complementares.*.exists' => 'Há CNAE complementar inativo ou inexistente na seleção.',
            'complementares.*.distinct' => 'Há CNAE complementar duplicado na seleção.',
            'documents.*.file' => 'Um dos anexos enviados é inválido.',
            'documents.*.mimetypes' => 'O tipo de um dos anexos não é permitido.',
            'documents.*.max' => 'Um dos anexos excede o tamanho máximo permitido.',
        ];
    }
}
