---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 01
subsystem: auth
tags: [fortify, spatie-permission, spatie-activitylog, laravel-lang, pt-br, security-headers, settings]

# Dependency graph
requires: []
provides:
  - Fortify 1.37.2 instalado com registration, resetPasswords, emailVerification e updatePasswords (2FA/passkeys desligados)
  - spatie/laravel-permission 8.0.0 e spatie/laravel-activitylog 5.0.0 publicados (configs + migrations)
  - Locale pt_BR ativo com lang/pt_BR commitado (laravel-lang/common 6.8.0 dev)
  - config/sile.php + App\Support\Settings::get() — parâmetros de segurança sem hardcode
  - Password::defaults() parametrizado lendo config('sile.security.password')
  - Middleware SecurityHeaders em todas as respostas web
  - tests/TestCase.php com withoutVite() — base de testes imune ao Vite
affects: [01-02, 01-03, 01-04, 01-05, 01-06, 01-07, 01-08, 01-09, fase-2-hu-014]

# Tech tracking
tech-stack:
  added: [laravel/fortify ^1.37 (1.37.2), spatie/laravel-permission ^8.0 (8.0.0), spatie/laravel-activitylog ^5.0 (5.0.0), laravel-lang/common ^6.8 (6.8.0, dev)]
  patterns: [Settings wrapper config-backed (HU-014 sem tocar call sites), Password::defaults() derivado de parâmetros, SecurityHeaders appended ao grupo web]

key-files:
  created:
    - config/sile.php
    - app/Support/Settings.php
    - app/Http/Middleware/SecurityHeaders.php
    - app/Providers/FortifyServiceProvider.php
    - app/Actions/Fortify/*
    - config/fortify.php
    - config/permission.php
    - config/activitylog.php
    - lang/pt_BR/*
    - tests/Unit/Support/SettingsTest.php
    - tests/Feature/Security/SecurityHeadersTest.php
  modified:
    - composer.json
    - bootstrap/app.php
    - bootstrap/providers.php
    - app/Providers/AppServiceProvider.php
    - tests/TestCase.php
    - .env.example

key-decisions:
  - "Features do Fortify restritas às 4 das HUs; 2FA e passkeys fora (migrations publicadas mantidas, inofensivas com features off)"
  - "home do Fortify em /portal (rota nasce no plano 01-03)"
  - "geolocation=(self) na Permissions-Policy para o mapa interativo da fase 4"
  - "Teste de política de senha ganhou caso de senha longa sem maiúscula/número para garantir RED significativo"

patterns-established:
  - "Parametrização: Settings::get('chave.aninhada', default) delega para config('sile.*') na fase 1; vira banco na HU-014 sem alterar call sites"
  - "Política de senha única: Password::defaults() no AppServiceProvider — Fortify aplica em cadastro, reset e alteração"
  - "Cabeçalhos de segurança transversais via middleware no grupo web"

# Metrics
duration: 8min
completed: 2026-06-09
---

# Fase 1 Plano 01: Fundação — Dependências, pt-BR, Parametrização e SecurityHeaders — Resumo

**Fortify 1.37.2 + spatie/permission 8 + activitylog 5 instalados, locale pt-BR ativo, parâmetros de segurança centralizados em config/sile.php com wrapper Settings testado e cabeçalhos de segurança em toda resposta web**

## Performance

- **Duração:** 8 min
- **Início:** 2026-06-09T23:45:20Z
- **Término:** 2026-06-09T23:53:05Z
- **Tasks:** 3
- **Arquivos modificados:** 31

## Realizações

- Dependências da fase instaladas nas versões exatas previstas pela pesquisa (dry-run confirmado na prática): fortify 1.37.2, permission 8.0.0, activitylog 5.0.0, laravel-lang/common 6.8.0
- Fortify publicado com exatamente as 4 features das HUs (registration, resetPasswords, emailVerification, updatePasswords) e `home => /portal`; `FortifyServiceProvider` registrado em `bootstrap/providers.php`
- Locale pt_BR ativo (`.env` + `.env.example`) com traduções do framework commitadas em `lang/pt_BR`
- `config/sile.php` + `App\Support\Settings` operacionais e testados — política de senha, tentativas de login e expiração de reset sem nenhum valor hardcoded em código de produção
- `Password::defaults()` derivado dos parâmetros (min 8, maiúscula+minúscula, número; símbolos off) e `auth.passwords.users.expire` espelhado de `sile.security.password_reset_expire`
- Middleware `SecurityHeaders` aplicado ao grupo web, provado por teste (X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy strict-origin-when-cross-origin, Permissions-Policy com geolocation=(self))
- Base de testes imune ao Vite (`withoutVite()` no TestCase) — suíte final verde com 6 testes / 16 asserções

## Commits por Task

Cada task foi commitada atomicamente:

1. **Task 1: Instalação, publicação e locale pt-BR** - `c8cb025` (chore)
2. **Task 2 RED: teste falhando para Settings e política de senha** - `290513b` (test)
3. **Task 2 GREEN: config/sile.php + Settings + Password::defaults()** - `c87a38b` (feat)
4. **Task 3 RED: teste falhando para cabeçalhos de segurança** - `823da0a` (test)
5. **Task 3 GREEN: middleware SecurityHeaders no grupo web** - `951615d` (feat)

_REFACTOR das tasks 2 e 3 não gerou mudanças (código já limpo; pint sem pendências) — sem commits de refactor._

## Rotas reais do Fortify (confirmadas via route:list)

A pesquisa tinha confiança BAIXA nos nomes; confirmados na versão instalada (1.37.2):

| URI | Método | Nome da rota |
|---|---|---|
| `/login` | GET | `login` |
| `/login` | POST | `login.store` |
| `/logout` | POST | `logout` |
| `/register` | GET | `register` |
| `/register` | POST | `register.store` |
| `/forgot-password` | GET | `password.request` |
| `/forgot-password` | POST | `password.email` |
| `/reset-password/{token}` | GET | `password.reset` |
| `/reset-password` | POST | `password.update` |
| `/email/verify` | GET | `verification.notice` |
| `/email/verify/{id}/{hash}` | GET | `verification.verify` |
| `/email/verification-notification` | POST | `verification.send` |
| `/user/confirm-password` | GET/POST | `password.confirm` / `password.confirm.store` |
| `/user/confirmed-password-status` | GET | `password.confirmation` |
| `/user/password` | PUT | `user-password.update` |

Os nomes batem com os usados nos testes do starter kit oficial (`login.store`, `register.store`) — os planos 01-04/01-06 podem usar tanto URIs literais quanto rotas nomeadas.

## Arquivos Criados/Modificados

- `config/sile.php` - Parâmetros de segurança da fase com defaults (preparação HU-014)
- `app/Support/Settings.php` - Wrapper de leitura de parâmetros (config-backed na fase 1)
- `app/Providers/AppServiceProvider.php` - Password::defaults() parametrizado + espelhamento do expire
- `app/Http/Middleware/SecurityHeaders.php` - Cabeçalhos de segurança em toda resposta web
- `bootstrap/app.php` - SecurityHeaders appended ao grupo web
- `config/fortify.php` - 4 features das HUs, home em /portal
- `app/Providers/FortifyServiceProvider.php` + `app/Actions/Fortify/*` - Publicados pelo fortify:install (customização nos planos seguintes)
- `config/permission.php`, `config/activitylog.php` - Configs spatie publicadas
- `database/migrations/*` - Migrations publicadas (permission, activity_log, 2FA/passkeys inofensivas) — NENHUMA executada em dev; extensão do activity_log fica no plano 01-02
- `lang/pt_BR/*` - Traduções do framework
- `tests/TestCase.php` - withoutVite() na base
- `tests/Unit/Support/SettingsTest.php` - 3 testes da parametrização
- `tests/Feature/Security/SecurityHeadersTest.php` - 1 teste dos cabeçalhos

## Decisões Tomadas

- Migrations de 2FA/passkeys publicadas pelo `fortify:install` foram mantidas (plano: não brigar com o pacote; inofensivas com as features desligadas)
- `geolocation=(self)` em vez de `geolocation=()` na Permissions-Policy — o mapa da fase 4 pode pedir localização no próprio domínio
- Sem commits de REFACTOR nas tasks TDD: implementação mínima já saiu limpa e o pint não acusou pendências

## Desvios do Plano

### Ajustes automáticos

**1. [Rule 2 - Missing Critical] Caso de teste adicional na política de senha**

- **Encontrado em:** Task 2 (fase RED)
- **Problema:** Os dois casos planejados ('fraca' falha, 'SenhaForte1' passa) já passavam com o default nativo do Laravel (`Password::min(8)`) — o teste ficava verde sem nenhuma implementação, violando o princípio de RED significativo
- **Correção:** Acrescentado caso 'somenteminusculas' (17 chars, sem maiúscula/número) que falha apenas quando a política parametrizada (mixedCase + numbers) está ativa
- **Arquivos:** tests/Unit/Support/SettingsTest.php
- **Verificação:** RED confirmado com a falha exatamente nesse caso; GREEN após implementar
- **Commit:** 290513b (RED) / c87a38b (GREEN)

---

**Total de desvios:** 1 ajuste automático (fortalecimento de teste)
**Impacto no plano:** Nenhum scope creep — apenas garantia de que o ciclo TDD prova o comportamento parametrizado de verdade.

## Problemas Encontrados

Nenhum.

## Portões de Autenticação

Nenhum — execução 100% local (composer, artisan, git).

## Configuração Manual Necessária

Nenhuma — sem serviços externos neste plano.

## Prontidão para o Próximo Plano

- Plano 01-02 (auditoria transversal RN-002) destravado: activitylog 5.0.0 publicado com config e migration prontas para extensão (colunas SILE: ip_address, user_agent, channel, acting_for_user_id, result, rules_version)
- A migration publicada do activity_log NÃO foi executada nem editada — exatamente o estado que o 01-02 espera
- `Settings`/`config/sile.php` disponíveis para parametrizar rate limit de login (plano 01-06) e demais valores
- Atenção para o 01-02: usar API v5 do activitylog (`beforeActivityLogged`, `attribute_changes`, action classes) — exemplos antigos da web usam API v4

---
*Fase: 01-identidade-acesso-e-auditoria-transversal*
*Concluído em: 2026-06-09*
