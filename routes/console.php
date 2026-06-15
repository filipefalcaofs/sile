<?php

use App\Models\AccessLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Primeira rotina real do scheduler: retenção de access_logs (LGPD).
// Idempotente (withoutOverlapping) e segura em multi-instância (onOneServer,
// exige cache compartilhado — Redis em produção). Padrão herdado por
// HU-134 (prazo BAP) e HU-147 (escalonamento por SLA).
Schedule::command('model:prune', ['--model' => [AccessLog::class]])
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

// Rede de SEGURANÇA do fluxo expresso (EP09): redecide as solicitações
// protocoladas que ficaram SEM decisão (órfãs — gatilho perdido/worker caído).
// NÃO é o gatilho principal (esse é o listener AvaliarFluxoExpresso no
// protocolo); é reprocesso idempotente. Cadência TÉCNICA (precedente [02-02]),
// segura em multi-instância (withoutOverlapping/onOneServer).
Schedule::command('expresso:reavaliar')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// HU-134 DORMENTE (EP09): indefere "sem atuação" as solicitações paradas em
// aguardando_bap além do prazo (expresso.bap.prazo_horas). Hoje é NO-OP em
// produção — nada entra em aguardando_bap até o Regin alimentar bap_due_at
// (Fase 13); a varredura encontra zero. Reprocessa a cada hora (RN-004) e é
// segura em multi-instância (withoutOverlapping/onOneServer).
Schedule::command('expresso:indeferir-sem-bap')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// HU-093 (EP11): alerta ANTECIPADO de vencimentos. Varre, na antecedência
// parametrizável (notificacoes.vencimento.antecedencia_dias), os processos
// em_analise (analysis_due_at — fonte única da Fase 10) e as pendências abertas
// (due_at) próximos de vencer e notifica analista/requerente pelo dispatcher
// multicanal. SÓ notifica (sem transição/timeline → sem dupla contagem HU-129).
// Idempotente pela checagem no ledger communications (RN-004), sem schema novo.
Schedule::command('notificacoes:alertar-vencimentos')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();
