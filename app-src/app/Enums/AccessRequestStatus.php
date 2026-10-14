<?php

namespace App\Enums;

enum AccessRequestStatus: string
{
    case Pendente = 'pendente';
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';
}
