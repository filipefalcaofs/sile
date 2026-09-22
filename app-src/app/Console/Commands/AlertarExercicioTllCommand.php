<?php

namespace App\Console\Commands;

use App\Enums\RuleDomain;
use App\Models\Activity;
use App\Models\RuleVersion;
use App\Models\User;
use App\Notifications\TllExercicioFaltanteNotification;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Alerta gestores em dezembro/janeiro quando o exercício corrente ou o
 * seguinte não tem tabela TLL vigente. Só notifica — nunca grava valor nem
 * publica. Idempotente pela auditoria (RN-002).
 */
class AlertarExercicioTllCommand extends Command
{
    protected $signature = 'tll:alertar-exercicio';

    protected $description = 'Alerta gestores quando falta tabela TLL vigente do exercício (dez/jan)';

    public function handle(AuditService $audit): int
    {
        $mes = (int) now()->month;

        if (! in_array($mes, [1, 12], true)) {
            $this->info('Fora da janela (dez/jan). Nada a alertar.');

            return self::SUCCESS;
        }

        $anos = [(int) now()->year, (int) now()->year + 1];
        $faltantes = array_values(array_filter(
            $anos,
            fn (int $ano): bool => ! $this->temVigente($ano),
        ));

        if ($faltantes === []) {
            $this->info('Exercícios da janela já têm tabela TLL vigente.');

            return self::SUCCESS;
        }

        $gestorRole = (string) Settings::get(
            'notificacoes.escalonamento.gestor_role',
            config('sile.notificacoes.escalonamento.gestor_role', 'gestor'),
        );

        $gestores = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', $gestorRole))
            ->orderBy('id')
            ->get();

        $alertados = 0;

        foreach ($gestores as $gestor) {
            $pendentes = array_values(array_filter(
                $faltantes,
                fn (int $ano): bool => ! $this->jaAlertado($gestor->id, $ano),
            ));

            if ($pendentes === []) {
                continue;
            }

            Notification::send($gestor, new TllExercicioFaltanteNotification($pendentes));

            foreach ($pendentes as $ano) {
                $audit->log(
                    'regras',
                    'tll-exercicio-faltante',
                    "Tabela TLL do exercício {$ano} ainda não publicada",
                    properties: [
                        'exercicio' => $ano,
                        'recipient_user_id' => $gestor->id,
                    ],
                    result: 'aviso',
                );
            }

            $alertados++;
        }

        if ($alertados === 0) {
            $this->info('Nenhum gestor a alertar (já avisados ou sem destinatário).');

            return self::SUCCESS;
        }

        $this->info("Alertado(s) {$alertados} gestor(es) sobre exercício(s) ".implode(', ', $faltantes).'.');

        return self::SUCCESS;
    }

    private function temVigente(int $ano): bool
    {
        return RuleVersion::query()
            ->vigente(RuleDomain::TllValores)
            ->where('version', (string) $ano)
            ->exists();
    }

    private function jaAlertado(int $userId, int $exercicio): bool
    {
        return Activity::query()
            ->where('log_name', 'regras')
            ->where('event', 'tll-exercicio-faltante')
            ->get()
            ->contains(fn (Activity $activity): bool => (int) ($activity->properties['recipient_user_id'] ?? 0) === $userId
                && (int) ($activity->properties['exercicio'] ?? 0) === $exercicio);
    }
}
