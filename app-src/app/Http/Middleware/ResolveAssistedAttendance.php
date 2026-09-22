<?php

namespace App\Http\Middleware;

use App\Models\AssistedAttendance;
use App\Support\Representation\CurrentRepresentation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espelha o ResolveRepresentation (HU-008/009) para o atendimento presencial
 * assistido (HU-150): revalida o atendimento da sessão em TODA request do
 * console de atendimento (pertence ao atendente, ativo, não expirado) e popula
 * o MESMO Context/CurrentRepresentation que a procuração — alimentando a
 * auditoria "em nome de" (RecordActivityAction) e a ViabilityRequestPolicy sem
 * recriar nada. Vínculo expirado/ausente limpa o estado: a próxima ação exige
 * reabertura explícita (CA-03).
 */
class ResolveAssistedAttendance
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $attendanceId = $request->session()->get('attending_attendance_id');

        if ($attendanceId !== null) {
            $attendance = AssistedAttendance::query()->with('citizen')->find($attendanceId);

            if ($attendance === null
                || $attendance->attendant_user_id !== $request->user()->id
                || ! $attendance->isActive()) {
                $request->session()->forget('attending_attendance_id');
                $request->session()->flash('status', 'O atendimento presencial foi encerrado porque o vínculo expirou. Reabra para continuar.');
                $this->endAttendance();
            } else {
                Context::add('acting_for_user_id', $attendance->citizen_user_id);
                app(CurrentRepresentation::class)->setAttendance($attendance);
            }
        } else {
            $this->endAttendance();
        }

        return $next($request);
    }

    /**
     * Zera o estado compartilhado do atendimento. Necessário porque Context e o
     * serviço scoped sobrevivem entre requests em runtimes sem flush por request
     * (testes na mesma instância, queue workers) — idem ResolveRepresentation.
     */
    private function endAttendance(): void
    {
        Context::forget('acting_for_user_id');
        app(CurrentRepresentation::class)->clearAttendance();
    }
}
