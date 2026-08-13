---
phase: 10-analise-tecnica-sedur
plan: 11
subsystem: ciclo-de-pendencia
tags: [hu-083, hu-084, pendencia, em-pendencia, evento-de-dominio, after-commit, notificacao, email, emaillog, shouldqueue, portal, policy, anti-idor, anti-fachada, rn-002]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    plan: "02"
    provides: "analysis_pendencies (status/due_at/response/responded_at) + AnalysisPendency/Factory + enum AnalysisPendencyStatus + ViabilityRequest::pendencies()"
  - phase: 10-analise-tecnica-sedur
    plan: "03"
    provides: "transições em_analise→em_pendencia e em_pendencia→em_analise no mapa da ViabilityRequestStateMachine"
  - phase: 10-analise-tecnica-sedur
    plan: "01"
    provides: "parâmetro analise.pendencia.prazo_resposta_dias (default 15) com fallback em config/sile.php"
  - phase: 09-fluxo-expresso
    plan: "07"
    provides: "padrão de notificação ShouldQueue + EmailLog (emailLogId/failed) e degradação honesta 'sem-destinatario'"
  - phase: 08-solicitacao-de-viabilidade
    plan: "11"
    provides: "ViabilityRequestPolicy::view (dono + representação Fase 1) e rota portal.solicitacoes.show"
provides:
  - "App\\Services\\Analise\\PendenciaService: abrir(request, analista, descricao): AnalysisPendency (→em_pendencia) e responder(pendency, resposta): void (→em_analise)"
  - "App\\Events\\PendenciaSolicitada (Dispatchable + ShouldDispatchAfterCommit, carrega ViabilityRequest + AnalysisPendency) — gancho honesto p/ multicanal EP11"
  - "App\\Notifications\\PendenciaSolicitadaNotification (mail, ShouldQueue, EmailLog, SEM anexo) — e-mail simples real ao requerente"
  - "App\\Http\\Controllers\\Portal\\PendenciaRespostaController (show/responder) + ResponderPendenciaRequest + rotas portal.solicitacoes.pendencias[.responder] + página pendencias.tsx"
  - "App\\Services\\Analise\\PendenciaInvalidaException (CA-03: estado inválido para abrir/responder)"
affects: [10-15, 10-16, 10-17]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Serviço transacional + após o commit: efeitos colaterais (evento gancho + e-mail) só de uma operação efetivada (espelha FluxoExpressoService/ResultadoEmitido)"
    - "Evento de domínio como GANCHO honesto SEM listener nesta fase (multicanal EP11 pluga depois por auto-descoberta); e-mail simples enviado direto pelo serviço hoje"
    - "Escopo do dono no portal reusando ViabilityRequestPolicy::view (mecanismo 'em nome de' da Fase 1) + anti-IDOR manual {pendency}↔{solicitacao} (padrão dos documentos 08-11)"

key-files:
  created:
    - app/Services/Analise/PendenciaService.php
    - app/Services/Analise/PendenciaInvalidaException.php
    - app/Events/PendenciaSolicitada.php
    - app/Notifications/PendenciaSolicitadaNotification.php
    - app/Http/Controllers/Portal/PendenciaRespostaController.php
    - app/Http/Requests/Portal/ResponderPendenciaRequest.php
    - resources/js/pages/portal/solicitacoes/pendencias.tsx
    - tests/Feature/Analise/PendenciaServiceTest.php
    - tests/Feature/Analise/PendenciaRespostaPortalTest.php
  modified:
    - routes/portal.php

key-decisions:
  - "Escopo do portal reusa a policy view (owns + effectiveUser/representação) — sem nova ability na policy (fora do files_modified e evita colisão com 10-10/10-12); a precondição de estado é regra de domínio no serviço, não autorização"
  - "Sem novo parâmetro de toggle/assunto da notificação — nenhum existe para pendência e o catálogo HU-014 é dono único do 10-01; e-mail enviado com degradação honesta 'sem-destinatario'"
  - "Notificação leva ao portal.solicitacoes.show (rota real da Fase 8), desacoplando a Task 1 da rota nova da Task 2; 10-16/10-17 enriquecem o detalhe com a pendência"
  - "Param de rota {solicitacao} (consistência com todo o portal.php) em vez do literal {viabilityRequest} do plano — naming é discrição (CONTEXT)"

patterns-established:
  - "responder() resolve o ator da transição via auth()->user() (assinatura sem ator, igual ao plano) — coerente com a captura de causer do AuditService no fluxo do portal"

# Metrics
duration: ~35min
completed: 2026-06-14
---

# Phase 10 Plan 11: Ciclo de Pendência Interno (HU-083/084 PARCIAL) — Summary

**Ciclo de pendência interno REAL entregue: o analista abre uma pendência (`PendenciaService::abrir` → cria `analysis_pendencies` aberta com prazo parametrizado e transiciona `em_analise→em_pendencia`) e, APÓS o commit, dispara o evento gancho `PendenciaSolicitada` (a base honesta para o multicanal do EP11, sem listener hoje) e envia um e-mail SIMPLES e REAL ao requerente (`PendenciaSolicitadaNotification`, mail/ShouldQueue/EmailLog, SEM anexo). O requerente responde pelo PORTAL ("Minhas solicitações"): `PendenciaService::responder` grava `response`/`responded_at` (respondida) e transiciona `em_pendencia→em_analise`, reabrindo a análise. Escopo do dono/representado reusando `ViabilityRequestPolicy::view` (mecanismo "em nome de" da Fase 1) + anti-IDOR no `{pendency}`; terceiro recebe 403 auditado no ponto único. Tudo auditado (RN-002). Anti-fachada honrado: o convite via Simplifica/Regin e os canais plenos (WhatsApp/in-app/templates) seguem BLOQUEADOS → EP11 — registrados como gancho, nunca simulados. TDD estrito (RED→GREEN com evidência fresca): `PendenciaServiceTest` 9/9 + `PendenciaRespostaPortalTest` 7/7 (filtro combinado 16/16, 78 asserções); `npx tsc --noEmit` verde; suíte completa SQLite 1064/1064 (5359 asserções) — zero regressão. ZERO dependência nova. Único editor de `routes/portal.php` na Wave 5 (disjunto de 10-10/10-12). A abertura pelo endpoint gestão é wiring do 10-15.**

## Performance

- **Duration:** ~35 min
- **Tasks:** 2 (serviço + evento + e-mail; resposta pelo portal)
- **Commits:** 4 atômicos (test/feat por task) + este SUMMARY
- **Files:** 10 (9 criados, 1 modificado) — ZERO dependência nova

## Contrato para os planos seguintes (assinaturas exatas)

### `App\Services\Analise\PendenciaService`

```php
public function abrir(ViabilityRequest $request, User $analista, string $descricao): AnalysisPendency
// exige em_analise (senão PendenciaInvalidaException); transação: cria analysis_pendencies
// (aberta, due_at = now()+analise.pendencia.prazo_resposta_dias) → transition em_analise→em_pendencia
// → audita 'analise'/'pendencia-aberta'. APÓS commit: PendenciaSolicitada::dispatch + e-mail simples.

public function responder(AnalysisPendency $pendency, string $resposta): void
// exige pendency Aberta + request EmPendencia (senão PendenciaInvalidaException); transação:
// grava response/responded_at (Respondida) → transition em_pendencia→em_analise → audita
// 'analise'/'pendencia-respondida'. (Expiração por prazo é gancho do scheduler HU-147/EP11.)
```

### `App\Events\PendenciaSolicitada`

```php
class PendenciaSolicitada implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    public function __construct(public ViabilityRequest $request, public AnalysisPendency $pendency) {}
}
```

- **Gancho EP11:** plugar canais plenos como listeners AUTO-DESCOBERTOS (type-hint no `handle`, NUNCA `Event::listen`). Hoje SEM listener — o e-mail simples já sai pelo serviço.

### `App\Notifications\PendenciaSolicitadaNotification` (mail, ShouldQueue)

```php
public ?int $emailLogId = null;
public function __construct(public string $protocolNumber, public string $descricao, public int $viabilityRequestId) {}
public function via(object $notifiable): array;        // ['mail']
public function toMail(object $notifiable): MailMessage; // assunto + corpo + action p/ portal.solicitacoes.show; SEM ->attach()
public function failed(\Throwable $exception): void;     // EmailLog::markAsFailed
```

### Rotas (portal, auth:web + verified + lgpd.accepted + ResolveRepresentation)

```
GET  portal/solicitacoes/{solicitacao}/pendencias                       → portal.solicitacoes.pendencias            (show)
POST portal/solicitacoes/{solicitacao}/pendencias/{pendency}/responder  → portal.solicitacoes.pendencias.responder  (responder)
```

- Escopo: `Gate::authorize('view', $solicitacao)` (dono/representado) + `abort_if($pendency->viability_request_id !== $solicitacao->id, 404)`.
- `responder` delega ao serviço; `PendenciaInvalidaException` → `back()->with('error', ...)` (aviso comunicado, CA-03); sucesso → redirect `portal.solicitacoes.show` com flash status.
- Página `portal/solicitacoes/pendencias` props: `solicitacao` (id, protocol_number, status) + `pendencias[]` (id, description, status, status_label, due_at, created_at — abertas).

## Mapa CA → teste (provado)

| HU / RN | Teste | Evidência |
|---|---|---|
| HU-083 — abrir (→em_pendencia) + prazo | `PendenciaServiceTest::test_abrir_cria_pendencia_e_transiciona_para_em_pendencia` | status aberta, due_at +15d, transição + auditoria |
| HU-083 — e-mail simples real | `...::test_abrir_envia_email_simples_real_ao_requerente` | EmailLog 'na_fila' + assertSentTo |
| Anti-fachada — evento gancho EP11 | `...::test_abrir_dispara_o_evento_gancho_pendencia_solicitada` | Event::assertDispatched(request+pendency) |
| CA-03 — abrir fora de em_analise | `...::test_abrir_fora_de_em_analise_e_bloqueado` | exceção; nada gravado/transicionado |
| Degradação honesta sem destinatário | `...::test_abrir_sem_destinatario_com_email_audita_e_nao_envia` | assertNothingSent + 'sem-destinatario' |
| HU-084 — responder reabre (→em_analise) | `...::test_responder_grava_resposta_e_reabre_a_analise` + `PendenciaRespostaPortalTest::test_dono_responde_e_reabre_a_analise` | respondida + em_analise + auditoria |
| CA-03 — já respondida | `...::test_responder_pendencia_ja_respondida_e_bloqueado` + `...::test_pendencia_ja_respondida_vira_aviso` | exceção/flash.error; nada muda |
| RN-004 — e-mail sem anexo + link portal | `...::test_notification_e_email_simples_sem_anexo_com_link_do_portal` | attachments []; protocolo + pendência no corpo |
| CA-04 — só o dono | `PendenciaRespostaPortalTest::test_terceiro_nao_responde_e_e_403_auditado` + `...::test_terceiro_nao_ve_a_pagina_de_pendencias` | 403 + 'seguranca'/'bloqueado' |
| Anti-IDOR | `...::test_pendencia_de_outra_solicitacao_da_404` | 404; pendência intacta |
| Validação | `...::test_resposta_e_obrigatoria` | erro 'response'; nada muda |
| Inertia (página) | `...::test_show_lista_a_pendencia_aberta` | component + props da pendência aberta |

## Task Commits

1. **Task 1 (test):** `9b2eafe` — `test(10-11)` PendenciaServiceTest (RED: 9 erros, classes inexistentes).
2. **Task 1 (feat):** `4022aac` — `feat(10-11)` PendenciaService + PendenciaSolicitada + PendenciaSolicitadaNotification + PendenciaInvalidaException (GREEN: 9/9, 38 asserções).
3. **Task 2 (test):** `0e6b7cf` — `test(10-11)` PendenciaRespostaPortalTest (RED: 7 erros, rotas inexistentes).
4. **Task 2 (feat):** `092beb4` — `feat(10-11)` controller + request + rotas portal + página pendencias.tsx (GREEN: 7/7, 40 asserções; tsc verde).

## Decisions Made

- **Escopo via `view`, não nova ability:** a policy não está no `files_modified` e é território potencial de planos paralelos; `view` (owns + representação Fase 1) já é exatamente a propriedade exigida. A precondição de estado (aberta/em_pendencia) é regra de domínio do serviço (CA-03), não autorização.
- **Sem novo toggle/assunto parametrizável:** o plano dizia "respeitar toggle de notificação SE existir"; não há para pendência e o catálogo HU-014 é dono único do 10-01 (não tocar `ParameterSeeder`). E-mail enviado com degradação honesta ('sem-destinatario'); assunto fixo pt-BR. Gancho de parametrização/multicanal fica para o EP11.
- **Evento gancho SEM listener + e-mail direto:** `PendenciaSolicitada` é a base honesta para os canais plenos do EP11 (auto-descoberta depois); o e-mail simples REAL já sai do próprio serviço após o commit (não via listener) — entrega de verdade hoje, sem fachada.
- **Notificação → `portal.solicitacoes.show`:** rota real da Fase 8, desacopla a Task 1 da rota nova da Task 2; 10-16/10-17 enriquecem o detalhe com a pendência/aviso e o deep-link para responder.

## Deviations from Plan

### 1. [Naming — discrição] Param de rota `{solicitacao}` em vez do literal `{viabilityRequest}`
O plano escreveu `portal/solicitacoes/{viabilityRequest}/pendencias`; usei `{solicitacao}` para alinhar com TODAS as rotas de solicitação do `portal.php` (convenção pervasiva do arquivo). O binding é por type-hint (`ViabilityRequest $solicitacao`), funcionalmente idêntico; o CONTEXT marca "rotas/estrutura da UI" como discrição. Aceite (`grep "pendencias"`/`"responder"`) satisfeito.

### 2. [Boundary] Autorização reusa `ViabilityRequestPolicy::view` (sem nova ability)
Em vez de adicionar `respondPendency` à policy (arquivo fora do `files_modified` e fora do meu boundary de Wave 5), reusei `view` — mesmo escopo de propriedade + mecanismo de representação da Fase 1. Sem risco de colisão com 10-10/10-12.

### 3. [Implementação] `PendenciaInvalidaException` criada
Exceção de domínio (CA-03) no meu domínio `Services/Analise` (espelha `DistribuicaoException`/`CancelamentoNaoPermitidoException`). Não listada no `files_modified` por ser detalhe de implementação do serviço.

### 4. [Anti-fachada — além dos testes listados] Degradação honesta 'sem-destinatario'
Adicionei a guarda + teste de "sem e-mail válido → audita e não envia" (espelha o listener da Fase 9). É comportamento do meu serviço, dentro do escopo, sem inventar envio.

### 5. [Teste] Contorno da armadilha do `protocoled()` (10-02)
No teste do portal, ao criar DUAS solicitações no mesmo caso (anti-IDOR), gerei `protocol_number` único por solicitação (via id) em vez de usar `protocoled()` (hardcoded `VIA-2026-000001`, que estouraria o `unique`). Resolvido no próprio ciclo RED.

## Authentication Gates

Nenhum — sem CLI/credencial externa neste plano.

## Verification (evidência fresca)

- **Task 1 RED:** `--filter=PendenciaServiceTest` → 9 erros (classes inexistentes).
- **Task 1 GREEN:** `--filter=PendenciaServiceTest` → **9/9 (38 asserções)**; `pint --dirty` ok.
- **Task 2 RED:** `--filter=PendenciaRespostaPortalTest` → 7 erros (rotas inexistentes; após corrigir a armadilha do protocoled, RED limpo só por rota).
- **Task 2 GREEN:** `--filter=PendenciaRespostaPortalTest` → **7/7 (40 asserções)**; `pint --dirty` ok; `npx tsc --noEmit` ok.
- **Filtro combinado do plano:** `--filter="PendenciaServiceTest|PendenciaRespostaPortalTest"` → **16/16 (78 asserções)**.
- **Suíte completa SQLite** (`--exclude-group=postgis`): **1064/1064 (5359 asserções)** — ZERO regressão.
- **Aceite (grep):** `EmPendencia`×3 e `PendenciaSolicitada::dispatch`×1 em PendenciaService; `class PendenciaSolicitada`×1; `pendencias`×2 em routes/portal.php; `responder`×2 no controller. `route:list --path=pendencias` → 2 rotas registradas.

## Bloqueios honestos (registrados, NÃO simulados → EP11/Fase 13)

- **Convite via Simplifica/Regin** (HU-083 RN-004) e **comunicação multicanal** (WhatsApp/in-app/templates) → EP11. O evento `PendenciaSolicitada` é o gancho honesto; hoje só o ciclo interno (portal SILE + e-mail simples) é REAL.
- **Expiração por prazo da pendência** (HU-147) → gancho do scheduler do EP11; o `due_at` já fica gravado.
- A **abertura pelo analista** (endpoint gestão chamando `PendenciaService::abrir`) é wiring do **10-15** — aqui entregamos o serviço + evento + e-mail + a resposta pelo portal.

## Next Phase Readiness

- **10-15** (endpoint gestão): chama `PendenciaService::abrir($request, $analista, $descricao)` sob `permission:analisar-processos`; a transição/auditoria/e-mail já estão prontos.
- **10-16/10-17** (detalhe/ficha + aviso ao requerente): consomem `ViabilityRequest::pendencies()` (badge/aviso) e a rota `portal.solicitacoes.pendencias` (deep-link para responder); a notificação pode passar a apontar direto para a página de resposta.
- **EP11** (multicanal): pendura listeners AUTO-DESCOBERTOS em `PendenciaSolicitada` (type-hint no `handle`), sem tocar o serviço; o e-mail simples permanece como canal base.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
