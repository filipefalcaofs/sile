<?php

namespace App\Support;

final class Roles
{
    /** Papéis referenciados pelo código — não excluíveis nem renomeáveis (HU-013). */
    public const array STRUCTURAL = ['cidadao', 'analista', 'gestor', 'administrador'];

    /**
     * Perfis do legado SEDUR que o código não referencia.
     * Nascem sem permissão; o administrador configura cada um na tela.
     *
     * @var list<string>
     */
    public const array LEGACY = [
        'Analista Servidor',
        'Analista Terceirizado',
        'Analista Vistoriador',
        'Atendimento Sedur',
        'Chefe de setor',
        'Coordenador',
        'Diretor',
        'Fiscal',
        'Gerente',
        'GR',
        'Padrão',
        'Secretário',
        'SEFAZ',
        'Servidor',
        'Subcoordenador',
        'Vistoriador',
    ];

    /**
     * Catálogo fechado: os vinte perfis do legado e o cidadão do portal.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        return [
            'cidadao',
            'analista',
            'apoio',
            'gestor',
            'administrador',
            ...self::LEGACY,
        ];
    }
}
