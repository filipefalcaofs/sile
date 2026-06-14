---
phase: 09-fluxo-expresso
plan: 03
subsystem: integracoes
tags: [hu-104, hu-110, hu-134, hu-076, fluxo-expresso, regin, sefaz, bap, contrato-bloqueado, anti-fachada, evento-dominio, after-commit, expresso]

# Dependency graph
requires:
  - phase: 09-fluxo-expresso
    plan: "02"
    provides: "App\\Models\\ViabilityDecision (model imutável 1:1, FK viability_request_id) + ViabilityDecisionFactory (deferida default) — payload dos contratos e do evento"
  - phase: 08-solicitacao-de-viabilidade
    plan: "(protocolo)"
    provides: "App\\Models\\ViabilityRequest + App\\Events\\SolicitacaoProtocolada (padrão ShouldDispatchAfterCommit espelhado) + App\\Services\\Realty\\PropertyRegistryLookup (padrão de contrato bloqueado espelhado)"
provides:
  - "3 contratos de integração de saída do expresso atrás de interface: ReginParecerNotifier (HU-104), SefazViabilidadeGateway (HU-110), BapRegistry (HU-134)"
  - "3 providers Unavailable (degradação honesta): Regin/SEFAZ LANÇAM exceção; BAP::findLinkage retorna null"
  - "2 exceções RuntimeException: ReginUnavailableException, SefazUnavailableException"
  - "DTO readonly BapLinkage (bapNumber + linkedAt) — retorno do BapRegistry"
  - "3 bindings no AppServiceProvider apontando para os Unavailable (Fase 13 troca SÓ o binding)"
  - "Segundo evento de domínio ResultadoEmitido (ShouldDispatchAfterCommit) carregando ViabilityRequest + ViabilityDecision"
affects: [09-05, 09-07, 09-08, 09-09, 09-10, 13]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Contrato bloqueado (espelha PropertyRegistryLookup): interface + provider Unavailable + binding único trocável na Fase 13, sem tocar call sites — Regin/SEFAZ degradam LANÇANDO exceção própria (anti-fachada: a transmissão não ocorreu); BAP degrada retornando null (ausência de vínculo é estado de negócio honesto, não erro)"
    - "Evento de domínio after-commit (espelha SolicitacaoProtocolada): ShouldDispatchAfterCommit + Dispatchable, payload imutável (request + decision), efeitos via listeners AUTO-DESCOBERTOS (NÃO Event::listen) — a auditoria autoritativa é síncrona e não depende do evento"
    - "DTO readonly (espelha PropertyRegistryResult): final readonly class para o vínculo BAP"

key-files:
  created:
    - app/Services/Regin/ReginParecerNotifier.php
    - app/Services/Regin/UnavailableReginParecerNotifier.php
    - app/Services/Regin/ReginUnavailableException.php
    - app/Services/Regin/BapRegistry.php
    - app/Services/Regin/UnavailableBapRegistry.php
    - app/Services/Regin/BapLinkage.php
    - app/Services/Sefaz/SefazViabilidadeGateway.php
    - app/Services/Sefaz/UnavailableSefazViabilidadeGateway.php
    - app/Services/Sefaz/SefazUnavailableException.php
    - app/Events/ResultadoEmitido.php
    - tests/Feature/Expresso/BlockedIntegrationContractsTest.php
    - tests/Feature/Expresso/ResultadoEmitidoEventTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "Regin/SEFAZ Unavailable LANÇAM exceção própria (RuntimeException), BAP retorna null — distinção honesta: transmissão pendente é erro; ausência de vínculo é estado de negócio (nada entra em aguardando_bap)."
  - "BapRegistry::findLinkage retorna ?BapLinkage (DTO readonly), não ?string/array — discrição concedida pelo plano; tipado e documentado para 09-10."
  - "BapLinkage carrega bapNumber + linkedAt (não dueAt): o prazo é DERIVADO a jusante (09-10) via BusinessDeadlineCalculator + expresso.bap.prazo_horas — separa a política de prazo da resolução do vínculo."
  - "As exceções carregam ?protocolNumber + ?motivo (espelha PropertyRegistryUnavailableException com $inscricao) — contexto para o audit de pendência dos listeners 09-08/09."
  - "ResultadoEmitido com propriedades públicas promovidas request/decision (espelha SolicitacaoProtocolada) — sem getters; o evento é só transporte imutável."

patterns-established:
  - "Integração de saída bloqueada nasce com interface + Unavailable + binding: Fase 13 liga a base oficial trocando UMA linha no AppServiceProvider, os listeners consumidores não mudam."

# Metrics
duration: ~13 min
completed: 2026-06-14
---

# Phase 9 Plan 03: Fundação de Integração Desacoplada (Contratos Bloqueados + ResultadoEmitido) Summary

**A Fase 9 ganhou a fundação de integração desacoplada (Track C da Wave 1): as transmissões de saída do fluxo expresso — comunicar o parecer ao Regin/Junta (HU-104), enviar a viabilidade à SEFAZ municipal (HU-110) e resolver o vínculo BAP (HU-134) — vivem atrás de interface, com provider `Unavailable` que degrada HONESTO (espelhando `PropertyRegistryLookup`): `ReginParecerNotifier` e `SefazViabilidadeGateway` LANÇAM exceção própria (a transmissão não ocorreu; nunca adaptador falso), e `BapRegistry::findLinkage` retorna `null` (ausência de vínculo é estado de negócio honesto — nada entra em `aguardando_bap` hoje). Os 3 bindings no `AppServiceProvider::register()` apontam para os `Unavailable` com o comentário de que a Fase 13 troca SÓ o binding, sem tocar nenhum call site. Entrou também o SEGUNDO evento de domínio `ResultadoEmitido` (`ShouldDispatchAfterCommit`, espelhando `SolicitacaoProtocolada`), carregando a `ViabilityRequest` + a `ViabilityDecision` imutável — base desacoplada dos efeitos da Wave 4 (notificação HU-077, Regin HU-104, SEFAZ HU-110 via listeners auto-descobertos); a auditoria autoritativa da decisão (HU-078) é síncrona e NÃO depende do evento. TDD estrito (RED→GREEN com evidência fresca): `BlockedIntegrationContractsTest` 6/6 e `ResultadoEmitidoEventTest` 3/3 (filtro combinado 9/9, 10 asserções); suíte completa SQLite 867/867 (4458 asserções). ZERO dependência nova.**

## Performance

- **Duration:** ~13 min
- **Started:** 2026-06-14T13:31Z (carregamento de estado/plano + leitura dos padrões a espelhar)
- **Completed:** 2026-06-14T13:44:14-03:00 (último commit de código `86eecd2`)
- **Tasks:** 2 (contratos+providers+exceções+DTO+bindings; evento ResultadoEmitido)
- **Files modified:** 13 (12 criados + AppServiceProvider) — ZERO dependência nova

## Contrato dos consumidores (assinaturas exatas — insumo de 09-05/07/08/09/10)

### Contratos Regin/SEFAZ/BAP e providers Unavailable

```php
// app/Services/Regin/ReginParecerNotifier.php  (HU-104)
interface ReginParecerNotifier
{
    /** @throws ReginUnavailableException */
    public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void;
}
// app/Services/Regin/UnavailableReginParecerNotifier.php → throw new ReginUnavailableException($request->protocol_number)

// app/Services/Sefaz/SefazViabilidadeGateway.php  (HU-110 — SÓ deferimento; quem chama decide)
interface SefazViabilidadeGateway
{
    /** @throws SefazUnavailableException */
    public function sendViabilidade(ViabilityRequest $request, ViabilityDecision $decision): void;
}
// app/Services/Sefaz/UnavailableSefazViabilidadeGateway.php → throw new SefazUnavailableException($request->protocol_number)

// app/Services/Regin/BapRegistry.php  (HU-134)
interface BapRegistry
{
    public function findLinkage(ViabilityRequest $request): ?BapLinkage;
}
// app/Services/Regin/UnavailableBapRegistry.php → return null (sem vínculo, jamais inventado)
```

### Exceções (RuntimeException — mensagem "pendente Fase 13")

```php
// app/Services/Regin/ReginUnavailableException.php
// app/Services/Sefaz/SefazUnavailableException.php
new ReginUnavailableException(?string $protocolNumber = null, ?string $motivo = null)
new SefazUnavailableException(?string $protocolNumber = null, ?string $motivo = null)
```

Os listeners 09-08 (Regin) e 09-09 (SEFAZ) devem CAPTURAR essas exceções e AUDITAR a pendência de integração (result 'bloqueado') — nunca registrar sucesso.

### DTO readonly do vínculo BAP

```php
// app/Services/Regin/BapLinkage.php
final readonly class BapLinkage
{
    public function __construct(
        public string $bapNumber,
        public DateTimeImmutable $linkedAt, // o bap_due_at é derivado em 09-10 (BusinessDeadlineCalculator + expresso.bap.prazo_horas)
    ) {}
}
```

### Bindings (AppServiceProvider::register — Fase 13 troca SÓ estes)

```php
$this->app->bind(ReginParecerNotifier::class, UnavailableReginParecerNotifier::class);
$this->app->bind(SefazViabilidadeGateway::class, UnavailableSefazViabilidadeGateway::class);
$this->app->bind(BapRegistry::class, UnavailableBapRegistry::class);
```

### Evento de domínio ResultadoEmitido

```php
// app/Events/ResultadoEmitido.php
class ResultadoEmitido implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public ViabilityRequest $request,
        public ViabilityDecision $decision,
    ) {}
}
// Disparo (09-05, após o commit): ResultadoEmitido::dispatch($request, $decision);
// Efeitos (Wave 4): listeners AUTO-DESCOBERTOS com type-hint no handle — NÃO Event::listen.
```

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **Task 1: contratos Regin/Sefaz/Bap + providers Unavailable + exceções + DTO + bindings** — `615f9b7` (feat) — RED: 6 erros (`Target class [...] does not exist` para ReginParecerNotifier/ReginUnavailableException/SefazViabilidadeGateway/SefazUnavailableException/BapRegistry) → GREEN: 6/6 (6 asserções), pint passed.
2. **Task 2: evento de domínio ResultadoEmitido (after-commit)** — `86eecd2` (feat) — RED: 3 erros (`Class "App\Events\ResultadoEmitido" not found`) → GREEN: 3/3 (4 asserções), pint passed.

**Plan metadata:** `docs(09-03)` (este SUMMARY + STATE).

## Decisions Made

- **Degradação por tipo de integração**: Regin/SEFAZ LANÇAM exceção (transmissão pendente = erro a auditar); BAP retorna null (sem vínculo = estado de negócio honesto). Espelha a separação de `PropertyNotFoundException` vs `PropertyRegistryUnavailableException` da Realty.
- **`?BapLinkage` em vez de `?string`/array**: o plano deu discrição; um DTO readonly tipado é mais claro e auditável para 09-10. `BapLinkage` carrega `bapNumber` + `linkedAt`; o prazo (`bap_due_at`) é derivado a jusante via `BusinessDeadlineCalculator` + `expresso.bap.prazo_horas` (09-01), separando política de prazo da resolução do vínculo.
- **Exceções com `?protocolNumber`/`?motivo`**: espelha `PropertyRegistryUnavailableException($inscricao, $motivo)` — dá contexto ao audit de pendência dos listeners sem acoplar a um payload fino (esse é Fase 13).
- **`ResultadoEmitido` com propriedades públicas promovidas**: idêntico ao `SolicitacaoProtocolada` (transporte imutável, sem getters).

## Deviations from Plan

None — plano executado exatamente como escrito. O `BapLinkage.php` (DTO) não está listado nominalmente em `files_modified`, mas o plano concede explicitamente a discrição do tipo de retorno do `findLinkage` ("`?BapLinkage` (ou `?string`/array simples)") e os pontos críticos pedem "DTO readonly"; é arquivo do meu namespace exclusivo (`app/Services/Regin`), sem invadir escopo de outro plano.

## Issues Encountered

- **Wave 1 em paralelo na MESMA working dir (dependência de tipo cross-plan):** os contratos (assinaturas `notifyParecer`/`sendViabilidade`) e o evento dependem de `App\Models\ViabilityDecision`, criado pelo plano **09-02** (Track B), que rodava concorrente. No início da execução o model ainda não existia (só a migration); aguardei o 09-02 publicar `ViabilityDecision` + `ViabilityDecisionFactory` (commit `30d79c8`) antes de rodar o ciclo TDD, garantindo RED pelo motivo CERTO (minhas classes ausentes, não a dependência). **Boundary respeitado**: toquei só os meus arquivos + o `AppServiceProvider` (dono único nesta wave), staging sempre individual (nunca `git add -A`); commits `615f9b7`/`86eecd2` íntegros, intercalados com 09-01/02/04 sem conflito.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=BlockedIntegrationContractsTest` → 6 erros (`Target class [...] does not exist`). **GREEN:** 6/6 (6 asserções).
- **RED Task 2:** `--filter=ResultadoEmitidoEventTest` → 3 erros (`Class "App\Events\ResultadoEmitido" not found`). **GREEN:** 3/3 (4 asserções).
- **Filtro combinado do plano:** `--filter="BlockedIntegrationContractsTest|ResultadoEmitidoEventTest"` → **9/9 (10 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed (após cada task).
- **Suíte completa SQLite:** `php artisan test --compact --exclude-group postgis` → **867 testes, 867 passaram, 0 falhas** (4458 asserções; inclui o trabalho commitado dos planos paralelos 09-01/02/04). Nenhum call site existente alterado.

## Next Phase Readiness

- **Insumo direto:** 09-05 (FluxoExpressoService dispara `ResultadoEmitido::dispatch($request, $decision)` após o commit da decisão), 09-07 (listener NotificarResultadoExpresso, HU-077, type-hint em ResultadoEmitido), 09-08 (ComunicarResultadoRegin consome `ReginParecerNotifier`, captura `ReginUnavailableException` e audita), 09-09 (EnviarViabilidadeSefaz consome `SefazViabilidadeGateway`, captura `SefazUnavailableException`, SÓ no deferimento), 09-10 (consome `BapRegistry`/`BapLinkage` e emite `ResultadoEmitido` → Regin no indeferimento sem atuação).
- **Bloqueios honestos prontos para a Fase 13:** as 3 integrações de saída têm abstração + binding único trocável; ligar Regin/SEFAZ/BAP conveniados é trocar 3 linhas no `AppServiceProvider`, sem tocar listeners. Nenhuma fachada: os `Unavailable` nunca simulam sucesso.
- **Lição reforçada da Fase 8 mantida:** os listeners da Wave 4 são auto-descobertos (type-hint no handle); NÃO registrar via `Event::listen` (evita auditoria duplicada).

---
*Phase: 09-fluxo-expresso*
*Completed: 2026-06-14*
