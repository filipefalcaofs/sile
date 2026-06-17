---
phase: 14-intelig-ncia-artificial
plan: 01
subsystem: infra
tags: [ia, multi-provider, configuracao, hu-014, anti-ssrf, lgpd, openai-compatible, http-client]

# Dependency graph
requires:
  - phase: 02-administracao-base
    provides: Settings/Parameter (HU-014), padrão de parâmetro sensível criptografado
  - phase: 11-comunicacao-multicanal
    provides: padrão CRUD administrável irmão (config-email — EmailServer, tester de conexão, masked credential)
provides:
  - Configuração de IA multi-provider administrável (AiConfiguration CRUD) sob manter-config-ia
  - Contrato AiProviderClient + cliente OpenAiCompatibleClient com teste de conexão HTTP REAL
  - Guarda anti-SSRF AiBaseUrlGuard (https + allowlist) reutilizável
  - 7 toggles features.ia_* (HU-014) e fallbacks; permissão manter-config-ia
affects:
  - Onda 1 (Documentos) — consumirá os provedores configurados + ponte runtime do SDK (Task 6 adiada)
  - Ondas 2-3 — toggles ia_resumo/ia_parecer/ia_explicacao/ia_assistente já registrados

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Config administrável irmã do config-email: CRUD server-driven + credencial criptografada nunca reexibida (DTO masked)"
    - "Anti-SSRF de base_url administrável: https obrigatório + allowlist de hosts (defesa em profundidade: FormRequest + client)"
    - "Resultado de integração SANITIZADO (ConnectionResult { ok, mensagem categorizada }) — nunca corpo/exceção/credencial"
    - "Throttle parametrizado nomeado lido via Settings (ai-connection-test)"

key-files:
  created:
    - app/Services/Ai/AiProviderClient.php
    - app/Services/Ai/OpenAiCompatibleClient.php
    - app/Services/Ai/ConnectionResult.php
    - app/Services/Ai/AiBaseUrlGuard.php
    - app/Http/Controllers/Gestao/AiConfigurationController.php
    - app/Http/Requests/Gestao/StoreAiConfigurationRequest.php
    - app/Http/Requests/Gestao/UpdateAiConfigurationRequest.php
    - resources/js/pages/gestao/config-ia/index.tsx
    - tests/Feature/Ai/AiProviderClientTest.php
    - tests/Feature/Ai/AiConfigurationControllerTest.php
  modified:
    - app/Models/AiConfiguration.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - database/seeders/ParameterSeeder.php
    - config/sile.php
    - app/Providers/AppServiceProvider.php
    - app/Providers/FortifyServiceProvider.php
    - routes/gestao.php
    - resources/js/layouts/gestao-layout.tsx
    - resources/js/components/app/command-search.tsx

key-decisions:
  - "Task 6 (composer require laravel/ai + ponte runtime + AiConfigDevSeeder) ADIADA para a Onda 1: v0.x instável + aws-sdk-php pesado/advisory + exige aprovação humana de dependência"
  - "Teste de conexão por HTTP direto OpenAI-compatible (GET {base_url}/models Bearer), sem SDK — já entrega valor real"
  - "Allowlist anti-SSRF deploy-controlada em config/sile.php (não no catálogo HU-014): postura segura e mantém baseline de 97 parâmetros"
  - "base_url obrigatória nos requests (a integração e o teste precisam de host); api_key nullable (espelha config-email, permite endpoint compatível sem auth)"

patterns-established:
  - "AiBaseUrlGuard::validate() compartilhado entre FormRequest e client (defesa em profundidade anti-SSRF)"
  - "Configuração de integração externa nasce com teste de conexão real + auditoria sem segredo"

# Metrics
duration: 30 min
completed: 2026-06-17
---

# Phase 14 Plan 01: Configuração de IA multi-provider (Onda 0) Summary

**Tela de configuração de provedores de IA administrável (OpenAI/Anthropic/Google e compatíveis) com credencial criptografada nunca reexibida, teste de conexão HTTP real anti-SSRF e toggles HU-014 — sem o SDK (ponte runtime adiada para a Onda 1).**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-06-17T02:43:00Z
- **Completed:** 2026-06-17T03:13:00Z
- **Tasks:** 4 (Tasks 2-5; Task 1 já estava pronta; Task 6 adiada)
- **Files modified/created:** 19

## Accomplishments

- **CRUD real de `AiConfiguration`** navegável no console sob `manter-config-ia` (index/store/update/destroy/test/toggleActivation), irmão do config-email.
- **Credencial nunca em claro:** listagem via DTO campo a campo com `masked_api_key`; `$hidden=['api_key']` no model (defesa em profundidade); no editar, chave em branco mantém a atual (RN-009).
- **Teste de conexão HTTP REAL** (`GET {base_url}/models` com `Authorization: Bearer`) — sem chave válida falha honestamente; sem fachada.
- **Anti-SSRF (requisito ALTO):** `https` obrigatório + allowlist de hosts (controle PRIMÁRIO), sem seguir redirects, `connectTimeout` baixo + teto do `timeout`. Validado no FormRequest E re-checado no client.
- **Resultado SANITIZADO:** `ConnectionResult { ok, mensagem categorizada }` — nunca corpo/headers da resposta, exceção crua nem a credencial; a `api_key` nunca é logada.
- **HU-014:** permissão `manter-config-ia` (só administrador) e 7 toggles `features.ia_*` (grupo `ia`, default OFF) administráveis, com fallbacks em `config/sile.php`.
- **Auditoria RN-002** em todas as operações, sem segredo (`auditProps` nunca inclui `api_key` nem corpo do provedor; `personal_data=false`).

## Task Commits

TDD estrito (RED → GREEN) por task:

1. **Task 2 — Permissão + toggles** — `87a234a` (test) → `8a25451` (feat)
2. **Task 3 — Contrato + teste de conexão anti-SSRF** — `aeeccb8` (test) → `2f8631b` (feat)
3. **Task 4 — CRUD backend + autorização** — `cb8ab76` (test) → `316b031` (feat)
4. **Task 5 — UI React + navegação** — `9e66e5c` (feat; verificado por Inertia render + tsc/build)

## Files Created/Modified

- `app/Services/Ai/AiProviderClient.php` — contrato `testConnection(AiConfiguration): ConnectionResult`
- `app/Services/Ai/OpenAiCompatibleClient.php` — cliente HTTP OpenAI-compatible (anti-SSRF, sanitização)
- `app/Services/Ai/ConnectionResult.php` — DTO sanitizado do resultado
- `app/Services/Ai/AiBaseUrlGuard.php` — guarda anti-SSRF (https + allowlist), compartilhada
- `app/Http/Controllers/Gestao/AiConfigurationController.php` — CRUD + test + toggle
- `app/Http/Requests/Gestao/{Store,Update}AiConfigurationRequest.php` — validação + base_url anti-SSRF
- `app/Models/AiConfiguration.php` — `$hidden=['api_key']`
- `database/seeders/RolesAndPermissionsSeeder.php` — `manter-config-ia` (só admin)
- `database/seeders/ParameterSeeder.php` — 7 toggles `features.ia_*` (grupo `ia`)
- `config/sile.php` — fallbacks dos toggles, bloco `ai` (allowlist + tetos do teste), throttle `ai_test`
- `app/Providers/AppServiceProvider.php` — binding `AiProviderClient → OpenAiCompatibleClient`
- `app/Providers/FortifyServiceProvider.php` — limiter `ai-connection-test` parametrizado
- `routes/gestao.php` — grupo `config-ia` sob `permission:manter-config-ia`, throttle no `testar`
- `resources/js/pages/gestao/config-ia/index.tsx` — tela (cards, modais, testar, excluir)
- `resources/js/layouts/gestao-layout.tsx` + `components/app/command-search.tsx` — navegação gated
- `tests/Feature/Ai/AiProviderClientTest.php` + `AiConfigurationControllerTest.php` — cobertura TDD

## Novos baselines

- **Permissões: 31** (30 → +1 `manter-config-ia`). Baselines ajustados em `DatabaseSeederTest`, `ManageRolesTest`, `RolesAndPermissionsSeederTest`.
- **Parâmetros: 97** (90 → +7 toggles `features.ia_*`, grupo novo `ia`). Baselines ajustados em `DatabaseSeederTest` e `ParameterSeederTest`.

## Controles de segurança implementados (requisitos ALTOS)

- **Anti-SSRF:** `AiBaseUrlGuard` exige `https` e host na allowlist (`config/sile.php` `ai.allowed_hosts`: api.openai.com, api.anthropic.com, generativelanguage.googleapis.com); `withoutRedirecting()`; `connectTimeout(3)` + teto `timeout_max_ms`. Validado no Request E no client (defesa em profundidade).
- **`$hidden` + DTO:** o model nunca é serializado para o Inertia (o cast decripta no `toArray()`); a listagem monta DTO com `masked_api_key`; `$hidden=['api_key']` como rede de segurança.
- **Sanitização:** `ConnectionResult` só `{ ok, mensagem categorizada }` (ex.: "Credencial inválida...", "Tempo de conexão esgotado...", "Host não autorizado..."); nunca corpo/exceção/credencial; nada logado com a chave.
- **Update vazio = manter:** chave em branco no editar não sobrescreve a credencial atual.
- **Auditoria sem segredo:** `auditProps` audita name/provider/capability/model/base_url/active/is_default + resultado do teste; nunca a `api_key`; `personal_data=false`.
- **403 auditado** no ponto único (`bootstrap/app.php`) — não reimplementado.

## Verificação (evidência fresca)

```
php artisan test --compact --filter=Ai
{"tool":"phpunit","result":"passed","tests":222,"passed":222,"assertions":1083}

php artisan test --compact --filter='/(DatabaseSeederTest|ManageRolesTest|RolesAndPermissionsSeederTest|ParameterSeederTest)/'
{"tool":"phpunit","result":"passed","tests":53,"passed":53,"assertions":799}

npx tsc --noEmit  -> exit 0
npm run build     -> exit 0 (chunk config-ia-*.js gerado)
vendor/bin/pint --dirty --format agent -> passed
```

Os 23 testes alvo (AiConfigurationTest 6 + AiProviderClientTest 6 + AiConfigurationControllerTest 11) estão entre os 222 (o filtro `Ai` é case-insensitive e varre amplo; tudo verde).

## Deviations from Plan

### Escopo reescopado (decisão do arquiteto-técnico, registrada na tarefa)

- **Task 6 (SDK `laravel/ai` + ponte runtime) ADIADA para a Onda 1** — não é desvio de execução, é a fronteira de escopo desta onda. Detalhe na seção "Next Phase Readiness".

### Ajustes automáticos (deviation rules)

**1. [Rule 2 - Missing Critical] Throttle parametrizado do teste de conexão**
- **Encontrado em:** Task 4 (rota `test`)
- **Decisão:** limiter nomeado `ai-connection-test` em `FortifyServiceProvider`, lido via `Settings::get('seguranca.throttle.ai_test.por_minuto', 10)` com fallback em `config/sile.php` — fora do catálogo HU-014 (teto técnico/segurança, precedente [02-02]) para preservar o baseline de 97 parâmetros.
- **Commit:** `316b031`

**2. [Correção de teste próprio] Asserção do sucesso**
- **Encontrado em:** Task 3 (GREEN)
- **Issue:** a asserção buscava o substring "sucesso" na mensagem "Conexão bem-sucedida." (não casa); a implementação estava correta.
- **Fix:** asserção ajustada para "bem-sucedida".
- **Commit:** `2f8631b`

**Total deviations:** 1 adição crítica (throttle) + 1 correção de asserção de teste. Sem scope creep.

## Issues Encountered

- O teste do controller que renderiza a página (`gestao/config-ia/index`) só fecha com o `.tsx` presente (acoplamento Inertia render ↔ Task 5). Resolvido ao entregar a Task 5; suíte 100% verde no fim.

## User Setup Required

None — nenhuma configuração de serviço externo é necessária para a Onda 0. As credenciais reais de provedor são cadastradas pelo administrador na própria tela (criptografadas).

## Next Phase Readiness

**Pronto:**
- Fundação multi-provider administrável navegável; contrato `AiProviderClient` e binding prontos para a Onda 1 trocar SÓ o binding pela ponte do SDK.
- Toggles `features.ia_*` e permissão no lugar; teste de conexão real validável contra provedor (sem chave válida, falha honesta).

**Pendência registrada (NÃO é fachada — bloqueio explícito):**
- **Task 6 — `composer require laravel/ai` + ponte runtime (`AiConfigServiceProvider`/`AiConfigResolver` aplicando a config do banco em `config('ai.*')`) + `AiConfigDevSeeder`** fica para a **Onda 1**. Motivo: o SDK está em API 0.x (instável), puxa `aws-sdk-php` pesado e tem 1 advisory de segurança associado; instalar numa fundação ainda sem consumidor seria infra inerte. A instalação exige **aprovação humana de dependência** (Rule 4 / política de escalonamento) e deve ser feita junto da primeira função que a consome (OCR/classificação — Onda 1). Até lá, a tela + o teste de conexão (HTTP direto) já entregam valor real.

---
*Phase: 14-intelig-ncia-artificial*
*Completed: 2026-06-17*
