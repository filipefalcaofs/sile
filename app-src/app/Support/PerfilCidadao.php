<?php

namespace App\Support;

final class PerfilCidadao
{
    /** Perfil atribuído no autocadastro e no login gov.br (parâmetro portal.perfil_cidadao). */
    public static function nome(): string
    {
        return (string) Settings::get('portal.perfil_cidadao', 'cidadao');
    }
}
