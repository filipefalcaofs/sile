<?php

namespace App\Policies;

use App\Models\Procuration;
use App\Models\User;

class ProcurationPolicy
{
    /**
     * Somente o outorgante revoga a própria procuração (HU-009 CA-04).
     */
    public function delete(User $user, Procuration $procuration): bool
    {
        return $user->id === $procuration->grantor_user_id;
    }
}
