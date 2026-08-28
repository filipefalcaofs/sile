<?php

namespace App\Enums;

/**
 * Intencao do requerente quanto a escritorio virtual (RN-EV-01), derivada das
 * duas respostas cruas da solicitacao. Nao e persistida: e conclusao do
 * sistema sobre os fatos declarados, e persistir as duas coisas permitiria
 * que divergissem.
 */
enum VirtualOfficeIntent: string
{
    case Abrigado = 'abrigado';
    case Sede = 'sede';
    case Nenhum = 'nenhum';
}
