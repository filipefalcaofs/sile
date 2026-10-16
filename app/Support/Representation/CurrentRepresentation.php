<?php

namespace App\Support\Representation;

use App\Models\AssistedAttendance;
use App\Models\Cidadao;
use App\Models\Procuration;

/**
 * Estado da representação "em nome de" na request corrente (HU-008/009).
 * Registrado como scoped: resolvido pelo ResolveRepresentation (procuração,
 * portal) ou pelo ResolveAssistedAttendance (atendimento presencial, console —
 * HU-150) a cada request e consumido por controllers, policies e pelo share do
 * Inertia. Os dois mecanismos populam o MESMO estado: grantor() devolve o
 * cidadão efetivo (representado/atendido) independentemente da origem.
 */
class CurrentRepresentation
{
    private ?Procuration $procuration = null;

    private ?AssistedAttendance $attendance = null;

    public function set(Procuration $procuration): void
    {
        $this->procuration = $procuration;
    }

    public function clear(): void
    {
        $this->procuration = null;
        $this->attendance = null;
    }

    /**
     * Define o atendimento presencial ativo (HU-150). O cidadão atendido passa
     * a ser o usuário efetivo, espelhando o outorgante da procuração.
     */
    public function setAttendance(AssistedAttendance $attendance): void
    {
        $this->attendance = $attendance;
    }

    public function clearAttendance(): void
    {
        $this->attendance = null;
    }

    public function procuration(): ?Procuration
    {
        return $this->procuration;
    }

    public function attendance(): ?AssistedAttendance
    {
        return $this->attendance;
    }

    /**
     * Cidadão efetivo "em nome de": o cidadão atendido (atendimento presencial)
     * tem precedência; senão o outorgante da procuração; senão ninguém.
     */
    public function grantor(): ?Cidadao
    {
        return $this->attendance?->citizen ?? $this->procuration?->grantor;
    }
}
