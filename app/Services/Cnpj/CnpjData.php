<?php

namespace App\Services\Cnpj;

/**
 * DTO imutável dos dados cadastrais de uma empresa retornados pelo provider
 * de CNPJ. O named constructor fromBrasilApi() mapeia o payload da BrasilAPI
 * (idêntico ao do minhareceita.org); toArray() expõe o contrato snake_case
 * consumido pelo endpoint JSON e pelo formulário React de cadastro.
 */
final readonly class CnpjData
{
    /**
     * @param  array<int, string>  $secondaryCnaeCodes
     */
    public function __construct(
        public string $cnpj,
        public string $legalName,
        public ?string $tradeName,
        public ?string $legalNatureCode,
        public ?string $legalNature,
        public ?string $sizeCode,
        public ?string $size,
        public ?string $primaryCnaeCode,
        public array $secondaryCnaeCodes,
        public ?string $street,
        public ?string $number,
        public ?string $complement,
        public ?string $neighborhood,
        public ?string $city,
        public ?string $state,
        public ?string $zipCode,
        public ?string $phone,
        public ?string $email,
        public ?string $registrationStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromBrasilApi(array $payload): self
    {
        $digits = static fn (mixed $value): ?string => $value === null || $value === ''
            ? null
            : (preg_replace('/\D/', '', (string) $value) ?: null);

        $nullable = static fn (mixed $value): ?string => $value === null || $value === ''
            ? null
            : (string) $value;

        $secondary = collect($payload['cnaes_secundarios'] ?? [])
            ->map(static fn (array $item): string => (string) ($item['codigo'] ?? ''))
            ->filter(static fn (string $code): bool => $code !== '' && (int) $code !== 0)
            ->values()
            ->all();

        return new self(
            cnpj: (string) ($payload['cnpj'] ?? ''),
            legalName: (string) ($payload['razao_social'] ?? ''),
            tradeName: $nullable($payload['nome_fantasia'] ?? null),
            legalNatureCode: isset($payload['codigo_natureza_juridica'])
                ? (string) $payload['codigo_natureza_juridica']
                : null,
            legalNature: $nullable($payload['natureza_juridica'] ?? null),
            sizeCode: isset($payload['codigo_porte'])
                ? str_pad((string) $payload['codigo_porte'], 2, '0', STR_PAD_LEFT)
                : null,
            size: $nullable($payload['porte'] ?? null),
            primaryCnaeCode: isset($payload['cnae_fiscal'])
                ? (string) $payload['cnae_fiscal']
                : null,
            secondaryCnaeCodes: $secondary,
            street: $nullable($payload['logradouro'] ?? null),
            number: $nullable($payload['numero'] ?? null),
            complement: $nullable($payload['complemento'] ?? null),
            neighborhood: $nullable($payload['bairro'] ?? null),
            city: $nullable($payload['municipio'] ?? null),
            state: $nullable($payload['uf'] ?? null),
            zipCode: $digits($payload['cep'] ?? null),
            phone: $digits($payload['ddd_telefone_1'] ?? null),
            email: $nullable($payload['email'] ?? null),
            registrationStatus: $nullable($payload['descricao_situacao_cadastral'] ?? null),
        );
    }

    /**
     * Contrato snake_case do JSON do endpoint e do formulário React.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cnpj' => $this->cnpj,
            'legal_name' => $this->legalName,
            'trade_name' => $this->tradeName,
            'legal_nature_code' => $this->legalNatureCode,
            'legal_nature' => $this->legalNature,
            'size_code' => $this->sizeCode,
            'size' => $this->size,
            'primary_cnae_code' => $this->primaryCnaeCode,
            'secondary_cnae_codes' => $this->secondaryCnaeCodes,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'phone' => $this->phone,
            'email' => $this->email,
            'registration_status' => $this->registrationStatus,
        ];
    }
}
