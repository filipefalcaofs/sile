<?php

namespace App\Support\Representation;

use App\Models\Procuration;
use App\Models\User;

/**
 * Estado da representação "em nome de" na request corrente (HU-008/009).
 * Registrado como scoped: resolvido pelo ResolveRepresentation a cada
 * request e consumido por controllers e pelo share do Inertia.
 */
class CurrentRepresentation
{
    private ?Procuration $procuration = null;

    public function set(Procuration $procuration): void
    {
        $this->procuration = $procuration;
    }

    public function clear(): void
    {
        $this->procuration = null;
    }

    public function procuration(): ?Procuration
    {
        return $this->procuration;
    }

    public function grantor(): ?User
    {
        return $this->procuration?->grantor;
    }
}
