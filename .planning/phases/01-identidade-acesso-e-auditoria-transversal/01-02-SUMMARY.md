---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 02
subsystem: audit
tags: [spatie-activitylog, activitylog-v5, access-logs, listeners, fortify, rn-002, hu-010]

# Dependency graph
requires:
  - phase: 01-01
    provides: activitylog 5.0.0 publicado (config + migration), Fortify 1.37.2 com rotas de auth, base de testes com withoutVite
provides:
  - Migration activity_log estendida com colunas SILE (ip_address, user_agent, channel, acting_for_user_id, result, rules_version)
  - Model App\Models\Activity com relacionamento actingFor e casts mesclados
  - RecordActivityAction (action v5) — enriquecimento central de TODA activity (origem, em-nome-de via Context, result default)
  - Trait App\Concerns\HasAuditoria (logFillable + logOnlyDirty + dontLogEmptyChanges + logExcept password/remember_token)
  - User com HasAuditoria + CausesActivity
  - AuditService (log explícito com ação/resultado/versão de regras) e logBlocked
  - 403 de autorização auditado num ponto único (render callbacks em bootstrap/app.php) mantendo o status padrão
  - Tabela access_logs + model AccessLog + AccessLogFactory (HU-010)
  - 7 listeners auto-descobertos dos eventos nativos de auth (Login, Logout, Failed, Lockout, Registered, Verified, PasswordReset)
affects: [01-03, 01-04, 01-05, 01-06, 01-07, 01-08, 01-09, fase-5-motor, fase-6-risco, fase-12-auditoria]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Enriquecimento central de auditoria via action class v5 (config activitylog.actions.log_activity)"
    - "access_logs como tabela dedicada imutável (sem updated_at), não evento filtrado em activity_log"
    - "Listeners auto-descobertos em app/Listeners com causer explícito quando não há sessão autenticada"
    - "403 auditado via render callbacks tipados na exceção PREPARADA (AccessDeniedHttpException) + UnauthorizedException do spatie"

key-files:
  created:
    - app/Models/Activity.php
    - app/Models/AccessLog.php
    - app/Support/Audit/RecordActivityAction.php
    - app/Support/Audit/AuditService.php
    - app/Concerns/HasAuditoria.php
    - app/Listeners/RecordSuccessfulLogin.php
    - app/Listeners/RecordLogout.php
    - app/Listeners/RecordFailedLogin.php
    - app/Listeners/RecordLockout.php
    - app/Listeners/RecordRegistrationActivity.php
    - app/Listeners/RecordEmailVerifiedActivity.php
    - app/Listeners/RecordPasswordResetActivity.php
    - database/migrations/2026_06_10_014429_create_access_logs_table.php
    - database/factories/AccessLogFactory.php
    - tests/Feature/Audit/AuditInfrastructureTest.php
    - tests/Feature/Audit/AccessLogRecordingTest.php
  modified:
    - database/migrations/2026_06_09_234627_create_activity_log_table.php
    - config/activitylog.php
    - config/fortify.php
    - app/Models/User.php
    - bootstrap/app.php

key-decisions:
  - "Activity model sem $fillable próprio: o pai (spatie v5) usa $guarded = [] — redefinir $fillable restringiria o mass assignment do pai; merge feito só em casts()"
  - "Render callback de 403 tipado em AccessDeniedHttpException: o Handler converte AuthorizationException ANTES dos callbacks (prepareException) — callback no tipo original nunca dispararia"
  - "fortify.limiters.login = null para o pipeline usar EnsureLoginIsNotThrottled e disparar Lockout (auditável); limiter nomeado de rota geraria 429 sem evento"
  - "Listeners de Registered/Verified/PasswordReset usam activity() com causedBy explícito (sem AuditService): no Registered não há usuário autenticado"

patterns-established:
  - "RN-002: toda activity carrega origem/result/em-nome-de automaticamente — nenhum controller audita manualmente"
  - "AuditService::log(logName, event, description, properties, subject, result, rulesVersion) para registro explícito em serviços"
  - "CA-04 transversal: AuditService::logBlocked grava event=acesso-negado result=bloqueado em todo 403"

# Metrics
duration: 15min
completed: 2026-06-10
---

# Fase 1 Plano 02: Auditoria Transversal (RN-002) e Histórico de Acessos — Resumo

**Trilha de auditoria transversal sobre activitylog v5 com enriquecimento central (ip/user_agent/channel/acting_for/result/rules_version), AuditService para registro explícito, 403 auditado no exception handler e access_logs alimentada por 7 listeners dos eventos nativos de auth — 15 testes novos provando tudo com requests HTTP reais**

## Performance

- **Duração:** 15 min
- **Início:** 2026-06-10T01:33:44Z
- **Término:** 2026-06-10T01:48:38Z
- **Tasks:** 3 (todas TDD Red-Green)
- **Arquivos modificados:** 21

## Realizações

- Migration `activity_log` estendida com as 6 colunas SILE da RN-002; `App\Models\Activity` própria com `actingFor()`; `RecordActivityAction` (action class v5) enriquece TODA activity — de model event ou chamada manual — num ponto único
- `HasAuditoria` (trait padrão SILE) loga apenas atributos fillable efetivamente alterados e nunca `password`/`remember_token`; `User` ganhou `HasAuditoria` + `CausesActivity`
- `AuditService` com registro explícito (ação, resultado, versão de regras) e `logBlocked`; qualquer 403 de autorização (Gate/Policy ou middleware do spatie/permission) gera registro `result=bloqueado` sem nenhum código por controller, mantendo o status 403 padrão (provado por teste)
- Tabela dedicada `access_logs` (imutável, indexada para a consulta da HU-010) alimentada por listeners de `Login`, `Logout`, `Failed` e `Lockout`; `Registered`, `Verified` e `PasswordReset` geram activities com causer explícito — base do CA-02 das HU-001/003/005 e da HU-010
- Suíte completa verde: 21 testes / 44 asserções (15 novos no namespace Audit), sem `Event::fake()` — efeitos assertados no banco via requests reais

## API final do AuditService (consumida pelos planos 01-06/01-07/01-08)

```php
public function log(
    string $logName,
    string $event,
    string $description,
    array $properties = [],
    ?Model $subject = null,
    string $result = 'sucesso',
    ?string $rulesVersion = null,
): Activity;

public function logBlocked(string $logName, string $description, array $properties = []): Activity;
// delega para log() com event='acesso-negado', result='bloqueado'
```

O causer é o usuário autenticado quando houver; o enriquecimento de origem vem da `RecordActivityAction`; `result`/`rules_version` são aplicados via `forceFill` pós-log (independe da API interna do builder v5).

**Render de 403:** os dois callbacks em `bootstrap/app.php` retornam `null` após auditar — o render padrão do framework segue valendo e o status 403 é preservado (asserção `assertForbidden()` no teste).

## Commits por Task

Cada fase TDD foi commitada atomicamente:

1. **Task 1 RED: teste da infraestrutura (colunas SILE + enriquecimento)** - `b1c0d30` (test)
2. **Task 1 GREEN: migration estendida + Activity + RecordActivityAction + config** - `2a604de` (feat)
3. **Task 2 RED: teste de trait, AuditService e 403 auditado** - `da9c5ef` (test)
4. **Task 2 GREEN: HasAuditoria + User + AuditService + render callbacks** - `82f2d73` (feat)
5. **Task 3 RED: teste de access_logs e listeners** - `20bc188` (test)
6. **Task 3 GREEN: migration access_logs + model + factory + 7 listeners + fortify** - `e3eb6f5` (feat)

_REFACTOR não gerou commits próprios: pint só reordenou imports do bootstrap/app.php antes do commit GREEN da Task 2._

## Arquivos Criados/Modificados

- `database/migrations/2026_06_09_234627_create_activity_log_table.php` - Colunas SILE + down()
- `app/Models/Activity.php` - Model estendido (casts mesclados, actingFor)
- `app/Support/Audit/RecordActivityAction.php` - Enriquecimento central (action v5)
- `config/activitylog.php` - activity_model e actions.log_activity apontando para as classes SILE
- `app/Concerns/HasAuditoria.php` - Trait padrão de auditoria de models de domínio
- `app/Models/User.php` - HasAuditoria + CausesActivity
- `app/Support/Audit/AuditService.php` - Registro explícito + logBlocked
- `bootstrap/app.php` - Render callbacks de AccessDeniedHttpException e UnauthorizedException
- `database/migrations/2026_06_10_014429_create_access_logs_table.php` - Tabela dedicada HU-010
- `app/Models/AccessLog.php` + `database/factories/AccessLogFactory.php` - Model imutável + factory
- `app/Listeners/Record*.php` (7) - Listeners auto-descobertos dos eventos de auth
- `config/fortify.php` - limiters.login = null (Lockout auditável)
- `tests/Feature/Audit/AuditInfrastructureTest.php` (7 testes) e `tests/Feature/Audit/AccessLogRecordingTest.php` (8 testes)

## Decisões Tomadas

- `Activity` sem `$fillable` próprio: o model pai da v5 usa `$guarded = []` (tudo mass-assignable); redefinir `$fillable` no filho restringiria o comportamento do pai — conferido no vendor antes de decidir
- Listeners de activity (`Registered`/`Verified`/`PasswordReset`) chamam `activity()->causedBy($event->user)` direto em vez de `AuditService` — no `Registered` não há sessão autenticada e o causer precisa ser explícito
- `AccessLog` sem `HasAuditoria` — é dado de auditoria; trait causaria ruído/recursão

## Desvios do Plano

### Correções automáticas

**1. [Rule 1 - Bug no plano] Render callback de `AuthorizationException` nunca dispararia**

- **Encontrado em:** Task 2 (teste do 403 falhou no GREEN)
- **Problema:** O plano mandava interceptar `Illuminate\Auth\Access\AuthorizationException` em `$exceptions->render()`. Evidência no framework instalado (`Handler::render()`): `prepareException()` converte `AuthorizationException` em `Symfony\...\AccessDeniedHttpException` ANTES de `renderViaCallbacks()` — o callback tipado na exceção original jamais é invocado
- **Correção:** Callback tipado em `AccessDeniedHttpException` (a exceção preparada, com a original em `getPrevious()`); o callback de `UnauthorizedException` do spatie permaneceu como planejado (estende `HttpException`, não é convertida)
- **Arquivos:** bootstrap/app.php
- **Verificação:** `test_excecao_de_autorizacao_gera_auditoria_de_bloqueio` verde com `assertForbidden()` + registro `bloqueado`
- **Commit:** 82f2d73

**2. [Rule 1 - Bug no plano] Evento `Lockout` nunca dispararia com `limiters.login` configurado**

- **Encontrado em:** Task 3 (teste de bloqueio falhou no GREEN — 6ª tentativa não gerava registro)
- **Problema:** Com `fortify.limiters.login = 'login'` (default publicado), a rota POST /login ganha middleware `throttle:login`, que lança 429 SEM evento, e o pipeline do Fortify EXCLUI `EnsureLoginIsNotThrottled` (única fonte do evento `Lockout`). O listener `RecordLockout` seria código morto — violação da regra de entrega funcional
- **Correção:** `'login' => null` em `config/fortify.php` (com comentário explicando) — o pipeline volta a usar `EnsureLoginIsNotThrottled`, que dispara `Lockout` na 6ª tentativa e responde com a mensagem de throttle padrão
- **Arquivos:** config/fortify.php (fora do files_modified previsto no plano)
- **Verificação:** `test_bloqueio_temporario_registra_evento` verde (5 falhas + 6ª gera `event=bloqueio`)
- **Commit:** e3eb6f5
- **Atenção para o 01-06:** o limite agora vem do `LoginRateLimiter` do Fortify (5 fixo em `tooManyAttempts`); a parametrização do rate limit (HU-014/Settings) deve sobrescrever esse binding (ou reintroduzir limiter nomeado preservando o disparo de Lockout). O `RateLimiter::for('login')` do FortifyServiceProvider ficou sem rota consumidora até lá

---

**Total de desvios:** 2 correções automáticas (bugs do plano vs. comportamento real do framework/pacote)
**Impacto no plano:** Nenhum scope creep — ambas necessárias para o CA-04 transversal e o registro de bloqueio funcionarem de verdade. Intenção do plano preservada integralmente.

## Problemas Encontrados

Nenhum além dos desvios documentados acima.

## Portões de Autenticação

Nenhum — execução 100% local (artisan, phpunit, git).

## Configuração Manual Necessária

Nenhuma — sem serviços externos neste plano.

## Prontidão para o Próximo Plano

- Infraestrutura RN-002 completa e testada: planos 01-03 em diante só consomem (trait, AuditService, listeners, access_logs)
- `AccessLogFactory` pronta para os testes da HU-010 (plano 01-08)
- Campos RN-002 todos mapeados: causer, created_at, ip/user_agent/channel, description/event/log_name, properties, result, properties.error, rules_version, acting_for_user_id
- Canal `gestao` será detectado automaticamente quando as rotas `gestao.*` nascerem (01-03) — nada a mudar na auditoria
- Migrations NÃO executadas em dev (apenas SQLite de teste) — `php artisan migrate` em Postgres fica para quando o ambiente subir

---
*Fase: 01-identidade-acesso-e-auditoria-transversal*
*Concluído em: 2026-06-10*
