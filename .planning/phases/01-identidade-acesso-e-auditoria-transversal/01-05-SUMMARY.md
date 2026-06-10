---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 05
subsystem: auth
tags: [lgpd, legal-terms, consent, middleware, versioning, inertia, react, pt-br]

# Dependency graph
requires:
  - phase: 01-02
    provides: HasAuditoria (activity de created no aceite), AuditService, auditoria transversal RN-002
  - phase: 01-03
    provides: Rotas portal × gestão com grupos de middleware, papéis seedados, UserFactory states por papel
  - phase: 01-04
    provides: Fluxo cadastro → verificação (verified antecede o gate LGPD), padrão de telas pt-BR com Form v3
provides:
  - HU-006 completa — consentimento LGPD exigido no primeiro acesso e a cada nova versão publicada, registrado com versão/IP/user_agent e auditado
  - LegalTerm versionado com vigência por published_at e LegalTerm::current() — base da administração de termos (HU-014)
  - Middleware lgpd.accepted nos dois ambientes; gestão avalia permissão ANTES do termo (403 prevalece)
  - State UserFactory::withAcceptedLgpdTerm() — helper para todos os testes dos planos 01-06 a 01-09
  - Página portal/termo-lgpd de leitura e aceite (layout próprio, sem navegação antes do consentimento)
affects: [01-06, 01-07, 01-08, 01-09, fase-2-administracao, fase-12-auditoria]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Termos legais como dados versionados: texto vive no seed/banco, nunca em código; nova versão publicada re-exige aceite automaticamente"
    - "Rotas do próprio termo dentro do grupo auth+verified mas FORA do subgrupo lgpd.accepted (evita loop de redirect)"
    - "Gate condicional: sem termo publicado, LegalTerm::current() retorna null e o middleware desarma (testes sem seed não quebram)"
    - "Ordem de middlewares na gestão: auth → verified → permission:acessar-gestao → lgpd.accepted"

key-files:
  created:
    - app/Models/LegalTerm.php
    - app/Models/LegalTermAcceptance.php
    - database/migrations/2026_06_10_022240_create_legal_terms_table.php
    - database/migrations/2026_06_10_022241_create_legal_term_acceptances_table.php
    - database/factories/LegalTermFactory.php
    - database/seeders/LegalTermSeeder.php
    - app/Http/Middleware/EnsureLgpdTermAccepted.php
    - app/Http/Controllers/Portal/LgpdTermController.php
    - resources/js/pages/portal/termo-lgpd.tsx
    - tests/Feature/Lgpd/TermAcceptanceTest.php
  modified:
    - app/Models/User.php
    - database/factories/UserFactory.php
    - database/seeders/DatabaseSeeder.php
    - bootstrap/app.php
    - routes/portal.php
    - routes/gestao.php
    - tests/Feature/Routing/EnvironmentAccessTest.php

key-decisions:
  - "Aceite gravado com firstOrCreate na chave (user_id, legal_term_id): idempotente sob a unique constraint — POST duplicado não estoura 500"
  - "Migration de acceptances renomeada para timestamp +1s: artisan gerou ambas no mesmo segundo e a ordem alfabética criaria acceptances antes de legal_terms (FK quebraria no Postgres)"
  - "Seed de roles+termo via helper privado nos testes HTTP (não no setUp da classe): os testes de domínio criam os próprios termos e colidiriam com o unique(type, version) do seed"

patterns-established:
  - "Helper de consentimento em testes: User::factory()->cidadao()->withAcceptedLgpdTerm()->create() para qualquer teste que acesse rotas protegidas pelo gate"
  - "Página de bloqueio (termo) com layout próprio mínimo: sem portal-layout para não induzir navegação antes do aceite"

# Metrics
duration: 8min
completed: 2026-06-10
---

# Fase 1 Plano 05: Termo LGPD Versionado com Gate de Aceite — Resumo

**HU-006 de ponta a ponta: termos legais versionados no banco (seed publica v1 com texto provisório administrável), middleware `lgpd.accepted` nos dois ambientes exigindo o aceite da versão vigente — re-exigido a cada nova publicação —, página de leitura/aceite pt-BR e consentimento registrado com versão, IP, user_agent e auditoria — 11 testes novos**

## Performance

- **Duração:** 8 min
- **Início:** 2026-06-10T02:21:54Z
- **Término:** 2026-06-10T02:29:36Z
- **Tasks:** 2 (ambas TDD Red-Green)
- **Arquivos modificados:** 17

## Realizações

- Domínio de termos versionados: `legal_terms` com `unique(type, version)` e vigência por `published_at` (null = rascunho); `LegalTerm::current('lgpd')` resolve a maior versão publicada; `legal_term_acceptances` com `unique(user_id, legal_term_id)`, IP e user_agent — 4 testes de domínio
- Seed da versão 1 do termo (texto provisório pt-BR cobrindo finalidade, dados coletados, direitos do titular conforme Lei nº 13.709/2018 e canal do encarregado), marcado para substituição pelo texto oficial da SEDUR via nova versão publicada (HU-014) — texto é dado, nunca código
- Gate de consentimento real (CA-01): cidadão verificado sem aceite é redirecionado ao termo em `/portal`; admin sem aceite é redirecionado em `/gestao`; cidadão sem permissão recebe 403 na gestão ANTES do gate (ordem provada por teste)
- Aceite registrado e auditado (CA-02): versão, IP, user_agent, accepted_at + activity `created` via HasAuditoria; sem concordância marcada, validação bloqueia com mensagem pt-BR e nada é gravado (CA-03); visitante não acessa o termo (CA-04)
- Re-aceite por versão: publicar v2 via factory volta a bloquear quem só aceitou v1 — comparação sempre contra a versão vigente
- `withAcceptedLgpdTerm()` na UserFactory e `EnvironmentAccessTest` blindado (atualização defensiva declarada no plano) — testes existentes imunes a setups futuros que seedem o termo
- Suíte completa verde: 62 testes / 199 asserções; `npm run typecheck` e `npm run build` exit 0; pint limpo

## Estrutura final dos grupos de rota

```php
// routes/portal.php — auth + verified no grupo; termo FORA do subgrupo lgpd.accepted
Route::middleware(['auth', 'verified'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('termo-lgpd', [LgpdTermController::class, 'show'])->name('termo-lgpd.show');
    Route::post('termo-lgpd', [LgpdTermController::class, 'accept'])->name('termo-lgpd.accept');

    Route::middleware('lgpd.accepted')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
});

// routes/gestao.php — permissão antes do termo (403 prevalece sobre redirect)
Route::middleware(['auth', 'verified', 'permission:acessar-gestao', 'lgpd.accepted'])
    ->prefix('gestao')->name('gestao.')->group(...);
```

Rotas novas: `portal.termo-lgpd.show` (GET `/portal/termo-lgpd`) e `portal.termo-lgpd.accept` (POST `/portal/termo-lgpd`). Logout permanece rota do Fortify fora dos grupos — não afetado pelo gate.

## Testes atualizados

- `tests/Feature/Lgpd/TermAcceptanceTest.php` (novo, 11 testes): 4 de domínio (current/aceite/seed) + 7 HTTP (redirect sem aceite, página vigente, aceite com versão/IP/auditoria, bloqueio sem concordância, re-aceite por nova versão, visitante bloqueado, gestão exige termo com permissão antes)
- `tests/Feature/Routing/EnvironmentAccessTest.php` (defensivo): os 4 testes que esperam 200 em `/portal` e `/gestao` agora usam `withAcceptedLgpdTerm()`; o teste de 403 na gestão permanece SEM aceite, provando que a permissão é avaliada antes do termo

## Commits por Task

Cada fase TDD foi commitada atomicamente:

1. **Task 1 RED: teste falhando de termos versionados** - `e901d5f` (test)
2. **Task 1 GREEN: models + migrations + factory + seed v1** - `eac4948` (feat)
3. **Task 2 RED: teste falhando do fluxo de aceite** - `a515d63` (test)
4. **Task 2 GREEN: middleware + rotas + controller + página + factory state** - `c0df6fe` (feat)

_REFACTOR não gerou commits próprios: pint só reordenou imports do bootstrap/app.php dentro do próprio ciclo GREEN da Task 2._

## Arquivos Criados/Modificados

- `app/Models/LegalTerm.php` - Termo versionado com `current()` e HasAuditoria
- `app/Models/LegalTermAcceptance.php` - Aceite com relações user/term e HasAuditoria
- `app/Models/User.php` - `termAcceptances()` e `hasAcceptedTerm()`
- `database/migrations/*_create_legal_terms_table.php` - type/version/title/content/published_at + unique(type, version)
- `database/migrations/*_create_legal_term_acceptances_table.php` - FKs em cascata, ip/user_agent, unique(user_id, legal_term_id)
- `database/factories/LegalTermFactory.php` - Default rascunho + state `published()`
- `database/seeders/LegalTermSeeder.php` - Publica v1 com texto provisório (PHPDoc de substituição via HU-014)
- `database/seeders/DatabaseSeeder.php` - Inclui LegalTermSeeder
- `app/Http/Middleware/EnsureLgpdTermAccepted.php` - Gate da versão vigente (desarma sem termo publicado)
- `app/Http/Controllers/Portal/LgpdTermController.php` - show (leitura) e accept (validação `accepted` + firstOrCreate + flash)
- `bootstrap/app.php` - Alias `lgpd.accepted`
- `routes/portal.php` / `routes/gestao.php` - Estrutura final dos grupos (acima)
- `resources/js/pages/portal/termo-lgpd.tsx` - Página pt-BR com conteúdo rolável, checkbox obrigatório, erro de validação e Sair
- `database/factories/UserFactory.php` - State `withAcceptedLgpdTerm()`
- `tests/Feature/Lgpd/TermAcceptanceTest.php` / `tests/Feature/Routing/EnvironmentAccessTest.php`

## Decisões Tomadas

- `firstOrCreate` no aceite (chave user_id + legal_term_id): POST duplicado (clique duplo) seria 500 por violação da unique constraint com `create` puro; idempotência preserva o registro original do consentimento
- Migration de acceptances renomeada para timestamp +1s: artisan gerou as duas no mesmo segundo e a ordem alfabética executaria acceptances antes de legal_terms — a FK quebraria no Postgres de dev
- Seed de roles+termo via helper privado chamado nos testes HTTP, não no `setUp` da classe: os testes de domínio criam termos v1/v2/v3 próprios e colidiriam com o `unique(type, version)` do seed

## Desvios do Plano

### Correções automáticas

**1. [Rule 3 - Bloqueio] Ordem das migrations geradas no mesmo segundo**

- **Encontrado em:** Task 1 (GREEN)
- **Problema:** `make:model -m` gerou `create_legal_terms` e `create_legal_term_acceptances` com timestamp idêntico (`2026_06_10_022240`); em ordem alfabética, `legal_term_acceptances` < `legal_terms`, então a tabela com FK seria criada antes da referenciada — falha no Postgres
- **Correção:** Migration de acceptances renomeada para `2026_06_10_022241_*`
- **Arquivos:** database/migrations/2026_06_10_022241_create_legal_term_acceptances_table.php
- **Verificação:** Suíte completa verde com RefreshDatabase (migrations executam na ordem correta)
- **Commit:** eac4948

---

**Total de desvios:** 1 correção automática (bloqueio)
**Impacto no plano:** Nenhum scope creep. `firstOrCreate` em vez de `create` no aceite foi decisão de implementação dentro do escopo (registrada em Decisões) — o plano pedia "criar acceptance" e a criação ocorre, com idempotência sob a constraint definida pelo próprio plano.

## Problemas Encontrados

Nenhum — plano executado sem retrabalho; RED falhou pelos motivos certos nas duas tasks (classes/rotas inexistentes) e GREEN passou na primeira execução completa.

## Portões de Autenticação

Nenhum — execução 100% local (artisan, phpunit, npm, git).

## Configuração Manual Necessária

Nenhuma — sem serviços externos neste plano.

## Prontidão para o Próximo Plano

- 01-06 (login/senhas): fluxo completo cadastro → verificação → termo pronto; testes de login que acessem `/portal` devem usar `withAcceptedLgpdTerm()` (ou não seedar o termo)
- 01-07/01-08: helper `withAcceptedLgpdTerm()` disponível; rotas novas do portal entram DENTRO do subgrupo `lgpd.accepted`
- Texto oficial da SEDUR pendente (Open Question 3 do RESEARCH): substituição via publicação de nova versão pela HU-014 (Fase 2) — sem mudança de código
- Atenção transversal: testes que seedem `DatabaseSeeder` completo passam a ter termo publicado — usuários de factory sem `withAcceptedLgpdTerm()` serão redirecionados ao termo nas rotas protegidas

---
*Fase: 01-identidade-acesso-e-auditoria-transversal*
*Concluído em: 2026-06-10*
