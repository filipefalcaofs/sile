<?php

namespace App\Services\Realty;

/** Campos tipados da certidão / Cadastro Imobiliário (IPTU) — só leitura na ficha. */
final readonly class PropertyCadastroCampos
{
    public function __construct(
        public ?string $inscricao,
        public ?string $endereco,
        public ?string $numero_metrico,
        public ?string $loteamento,
        public ?string $quadra,
        public ?string $lote,
        public ?string $conjunto_edificio,
        public ?string $bloco,
        public ?string $sub_unidade,
        public ?string $numero_sub_unidade,
        public ?string $bairro,
        public ?string $cep,
        public ?string $area_construida_m2,
        public ?string $tipo_imovel,
        public ?string $data_lancamento,
        public ?string $situacao_cadastral,
        public ?string $contribuinte,
        public ?string $cpf_cnpj,
        public ?string $numero_porta,
        public ?string $area_terreno_m2,
        public ?string $valor_venal_iptu,
        public ?string $logradouro_tributario,
        public ?string $situacao_fiscal,
        public ?string $data_emissao_certidao,
    ) {}

    public static function vazios(): self
    {
        return new self(
            inscricao: null,
            endereco: null,
            numero_metrico: null,
            loteamento: null,
            quadra: null,
            lote: null,
            conjunto_edificio: null,
            bloco: null,
            sub_unidade: null,
            numero_sub_unidade: null,
            bairro: null,
            cep: null,
            area_construida_m2: null,
            tipo_imovel: null,
            data_lancamento: null,
            situacao_cadastral: null,
            contribuinte: null,
            cpf_cnpj: null,
            numero_porta: null,
            area_terreno_m2: null,
            valor_venal_iptu: null,
            logradouro_tributario: null,
            situacao_fiscal: null,
            data_emissao_certidao: null,
        );
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'inscricao' => $this->inscricao,
            'endereco' => $this->endereco,
            'numero_metrico' => $this->numero_metrico,
            'loteamento' => $this->loteamento,
            'quadra' => $this->quadra,
            'lote' => $this->lote,
            'conjunto_edificio' => $this->conjunto_edificio,
            'bloco' => $this->bloco,
            'sub_unidade' => $this->sub_unidade,
            'numero_sub_unidade' => $this->numero_sub_unidade,
            'bairro' => $this->bairro,
            'cep' => $this->cep,
            'area_construida_m2' => $this->area_construida_m2,
            'tipo_imovel' => $this->tipo_imovel,
            'data_lancamento' => $this->data_lancamento,
            'situacao_cadastral' => $this->situacao_cadastral,
            'contribuinte' => $this->contribuinte,
            'cpf_cnpj' => $this->cpf_cnpj,
            'numero_porta' => $this->numero_porta,
            'area_terreno_m2' => $this->area_terreno_m2,
            'valor_venal_iptu' => $this->valor_venal_iptu,
            'logradouro_tributario' => $this->logradouro_tributario,
            'situacao_fiscal' => $this->situacao_fiscal,
            'data_emissao_certidao' => $this->data_emissao_certidao,
        ];
    }
}
