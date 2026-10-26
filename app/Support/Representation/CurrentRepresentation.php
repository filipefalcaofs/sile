<?php

namespace App\Support\Representation;

use App\Models\AssistedAttendance;
use App\Models\CompanyUser;
use App\Models\Procuration;
use App\Models\User;

/**
 * Estado da representação "em nome de" na request corrente (HU-008/009).
 * Registrado como scoped: resolvido pelo ResolveRepresentation (procuração,
 * portal) ou pelo ResolveAssistedAttendance (atendimento presencial, console —
 * HU-139) a cada request e consumido por controllers, policies e pelo share do
 * Inertia. Os dois mecanismos populam o MESMO estado: grantor() devolve o
 * usuário efetivo (representado/atendido) independentemente da origem.
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
     * Define o atendimento presencial ativo (HU-139). O cidadão atendido passa
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
     * Usuário efetivo "em nome de": o cidadão atendido (atendimento presencial)
     * tem precedência; senão o outorgante da procuração; senão ninguém.
     */
    public function grantor(): ?User
    {
        return $this->attendance?->citizen ?? $this->procuration?->grantor;
    }

    /**
     * Usuário em nome de quem a request age: o representado, se houver, ou o próprio autenticado.
     */
    public function effectiveUser(User $authenticated): User
    {
        return $this->grantor() ?? $authenticated;
    }

    /**
     * Ids das empresas com vínculo ativo do usuário efetivo.
     *
     * @return list<int>
     */
    public function companyIds(User $authenticated): array
    {
        return CompanyUser::query()
            ->where('user_id', $this->effectiveUser($authenticated)->id)
            ->whereNull('ended_at')
            ->pluck('company_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function actsForCompany(User $authenticated, int $companyId): bool
    {
        return in_array($companyId, $this->companyIds($authenticated), true);
    }
}
