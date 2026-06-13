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
