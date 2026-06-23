<?php

use App\Models\AccessLog;
use App\Models\ExportFile;
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

// HU-131 (EP15): retenção dos arquivos de exportação. Poda diária dos ExportFiles
// além de relatorios.export.retencao_dias (Prunable: pruning() remove o arquivo do
// Storage, sem órfão) — o disco não cresce sem limite. Entrada SEPARADA da de
// access_logs (mutex distinto pelo --model, sem colisão) e idempotente/segura em
// multi-instância (withoutOverlapping/onOneServer), no mesmo padrão da Fase 3.1.
Schedule::command('model:prune', ['--model' => [ExportFile::class]])
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

// HU-147 (EP11): escalonamento por SLA. A cada hora lê o semáforo da FONTE ÚNICA
// (analysis_due_at via AnalysisSlaService — mesma origem da fila/badge da Fase
// 10): amarelo alerta o analista, vencido escala ao(s) gestor(es) (role
// parametrizável). Tratamento por notificacoes.escalonamento.tratamento (default
// só notificar). SÓ notifica (sem decisão automática, RN-003; sem dupla contagem
// HU-129). Idempotente pelo ledger communications (RN-004); cadência TÉCNICA,
// segura em multi-instância (withoutOverlapping/onOneServer).
Schedule::command('notificacoes:escalonar-sla')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// HU-091 RN-005 (EP11): expira as pendências abertas vencidas sem resposta
// (Expirada) e notifica o analista responsável. SEM decisão automática — NÃO
// indefere por não-resposta (rito SEDUR não inventado); o estado do processo é
// mantido. Idempotente pela própria transição Aberta→Expirada (a 2ª passada não
// acha mais Aberta vencida). Segura em multi-instância (withoutOverlapping/
// onOneServer).
Schedule::command('pendencias:expirar')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// HU-149 (EP12): detecção de abuso/fraude. Varre as solicitações na janela
// (abuso.janela_dias) com detectores determinísticos sobre dado REAL (SEM IA) e
// gera alertas idempotentes; acima de abuso.severidade_malha_fina encaminha à
// malha fina (ator=sistema) — NUNCA pune nem transiciona status (RN-001). NO-OP
// honesto enquanto features.deteccao_abuso=0 (default; a SEDUR liga após validar
// os limiares). Idempotente (índice único parcial da 12-03) e seguro em
// multi-instância (withoutOverlapping/onOneServer), como as Fases 9/11.
Schedule::command('abuso:detectar')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer();

// Auditoria Preditiva de Processos Expressos (Módulo 3): varredura SEMANAL dos
// deferimentos automáticos do fluxo expresso — pontua sinais determinísticos e
// gera anomalia + malha fina (NUNCA pune; LGPD art. 20). No-op honesto enquanto
// features.ia_auditoria_preditiva=0 (default; a SEDUR/DPO liga após validar os
// limiares). Idempotente por fingerprint e segura em multi-instância
// (withoutOverlapping/onOneServer).
Schedule::command('ia:auditoria-preditiva')
    ->weekly()
    ->withoutOverlapping()
    ->onOneServer();
