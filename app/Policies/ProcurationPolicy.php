<?php

namespace App\Policies;

use App\Models\Cidadao;
use App\Models\Procuration;

class ProcurationPolicy
{
    /**
     * Somente o outorgante revoga a própria procuração (HU-009 CA-04).
     */
    public function delete(Cidadao $cidadao, Procuration $procuration): bool
    {
        return $cidadao->id === $procuration->grantor_cidadao_id;
    }
}
