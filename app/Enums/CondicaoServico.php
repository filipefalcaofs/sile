<?php

namespace App\Enums;

/**
 * Condição de uma linha do De x Para dos serviços: o mesmo evento da JUCEB
 * recebe nomes diferentes conforme o que mudou em relação ao último processo
 * da empresa (tabela "Alteração de nomenclatura - Serviços" da SEDUR).
 */
enum CondicaoServico: string
{
    case CnaeIncluido = 'cnae_incluido';
    case CnaeExcluido = 'cnae_excluido';
    case CnaeSemAlteracao = 'cnae_sem_alteracao';
    case EnderecoLocalizacao = 'endereco_localizacao';
    case EnderecoEspaco = 'endereco_espaco';

    public function label(): string
    {
        return match ($this) {
            self::CnaeIncluido => 'Caso sistema identifique que está sendo incluído algum CNAE',
            self::CnaeExcluido => 'Caso sistema identifique que está sendo incluído e também excluindo algum CNAE',
            self::CnaeSemAlteracao => 'Caso o sistema identifique que o pedido não altera nenhum CNAE',
            self::EnderecoLocalizacao => 'Caso o sistema identifique que está sendo alterado o: codlog / nº de porta ou métrico / Bairro',
            self::EnderecoEspaco => 'Caso o sistema identifique que está sendo alterado o Complemento / Sub-unidade / nº Sub-unidade / CEP',
        };
    }

    public function comparaCnaes(): bool
    {
        return in_array($this, [self::CnaeIncluido, self::CnaeExcluido, self::CnaeSemAlteracao], true);
    }
}
