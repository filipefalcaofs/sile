<?php

namespace App\Enums;

/**
 * Situação de uma proposta de alteração de parâmetro decisório. A pendente
 * coexiste com o valor vigente; só a aprovada o substitui.
 */
enum ParameterProposalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
