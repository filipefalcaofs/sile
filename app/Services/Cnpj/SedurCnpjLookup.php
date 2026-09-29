<?php

namespace App\Services\Cnpj;

use App\Services\Sedur\SedurContatoClient;
use App\Services\Sedur\SedurContatoIndisponivelException;
use App\Services\Sedur\SedurContatoNaoEncontradoException;

/**
 * Consulta de CNPJ na base cadastral da SEDUR (ws-contato). Preenche só o que
 * a SEDUR devolve: razão social, nome fantasia, telefone e endereço. Não
 * inventa CNAE, porte nem natureza jurídica.
 */
class SedurCnpjLookup implements CnpjLookup
{
    public function __construct(private SedurContatoClient $contato) {}

    public function lookup(string $cnpj): CnpjData
    {
        try {
            $empresa = $this->contato->consultarCnpj($cnpj);
        } catch (SedurContatoNaoEncontradoException) {
            throw new CnpjNotFoundException($cnpj);
        } catch (SedurContatoIndisponivelException) {
            throw new CnpjLookupException($cnpj);
        }

        $telefone = preg_replace('/\D/', '', (string) $empresa->telefone) ?: null;

        return new CnpjData(
            cnpj: $empresa->cnpj,
            legalName: $empresa->razaoSocial,
            tradeName: $empresa->nomeFantasia,
            legalNatureCode: null,
            legalNature: null,
            sizeCode: null,
            size: null,
            primaryCnaeCode: null,
            secondaryCnaeCodes: [],
            street: $empresa->endereco,
            number: null,
            complement: null,
            neighborhood: null,
            city: null,
            state: null,
            zipCode: null,
            phone: $telefone,
            email: null,
            registrationStatus: null,
        );
    }
}
