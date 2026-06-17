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
  - Onda 1 (Documentos) — consumirá os provedores configurados via a ponte runtime do SDK (Task 6 ENTREGUE): basta criar Agents que leem config('ai.*')
  - Ondas 2-3 — toggles ia_resumo/ia_parecer/ia_explicacao/ia_assistente já registrados; conversations (RemembersConversations) já declarado em config/ai.php

# Tech tracking
tech-stack:
  added:
    - "laravel/ai (SDK multi-provider de IA; v0.8.1) — integrado + ponte de runtime (Task 6)"
  patterns:
    - "Ponte de runtime config-banco → config('ai.*') no boot (irmã do MailConfigServiceProvider), com cache curto e invalidação por evento do model; api_key cacheada como ciphertext e decriptada só em memória"
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
    - app/Services/Ai/AiConfigResolver.php
    - app/Providers/AiConfigServiceProvider.php
    - app/Http/Controllers/Gestao/AiConfigurationController.php
    - app/Http/Requests/Gestao/StoreAiConfigurationRequest.php
    - app/Http/Requests/Gestao/UpdateAiConfigurationRequest.php
    - config/ai.php
    - database/seeders/AiConfigDevSeeder.php
    - resources/js/pages/gestao/config-ia/index.tsx
    - tests/Feature/Ai/AiProviderClientTest.php
    - tests/Feature/Ai/AiConfigurationControllerTest.php
    - tests/Feature/Ai/AiConfigBridgeTest.php
    - tests/Feature/Ai/AiConfigDevSeederTest.php
    - tests/Fixtures/Ai/BridgeProbeAgent.php
  modified:
    - app/Models/AiConfiguration.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - database/seeders/ParameterSeeder.php
    - database/seeders/DatabaseSeeder.php
    - bootstrap/providers.php
    - config/sile.php
    - app/Providers/AppServiceProvider.php
    - app/Providers/FortifyServiceProvider.php
    - routes/gestao.php
    - resources/js/layouts/gestao-layout.tsx
    - resources/js/components/app/command-search.tsx

key-decisions:
  - "Task 6 ENTREGUE (fecha a Onda 0): laravel/ai v0.8.1 integrado + ponte runtime (AiConfigResolver/AiConfigServiceProvider) + AiConfigDevSeeder. A ponte usa as chaves REAIS do SDK (driver/key/url/models.text.default), NÃO as do AI-SPEC (api_key/base_url)"
  - "Chaves do SDK ≠ chaves do model: provider→driver (compativel→openai), base_url→url, api_key→key, model→models.text.default (ou models.embeddings.default); defaults por capacidade em ai.default (texto) e ai.default_for_embeddings"
  - "Cache da ponte guarda o CIPHERTEXT da api_key (RN-009/LGPD), nunca o claro; decripta só em apply() para a config em memória. TTL técnico ai.config_cache_ttl em config/sile.php (fora do catálogo HU-014, baseline 97 intacto); invalidação por evento saved/deleted do model"
  - "config/ai.php enxuto: declara só o que falta (default* + bloco conversations que o pacote referencia mas não publica); a lista de providers vem do pacote via mergeConfigFrom — sem duplicar/divergir do SDK"
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

**Tela de configuração de provedores de IA administrável (OpenAI/Anthropic/Google e compatíveis) com credencial criptografada nunca reexibida, teste de conexão HTTP real anti-SSRF e toggles HU-014 — e a ponte de runtime (Task 6) que faz essa config do banco alimentar o SDK laravel/ai em `config('ai.*')` sem deploy. Onda 0/14-01 COMPLETA.**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-06-17T02:43:00Z
- **Completed:** 2026-06-17T03:13:00Z
- **Tasks:** 5 de 6 entregues na execução inicial (Tasks 2-5; Task 1 já pronta) + **Task 6 (ponte runtime)** entregue em sessão seguinte — Onda 0 completa.
- **Files modified/created:** 19 (Onda 0) + 9 (Task 6)

## Accomplishments

- **CRUD real de `AiConfiguration`** navegável no console sob `manter-config-ia` (index/store/update/destroy/test/toggleActivation), irmão do config-email.
- **Credencial nunca em claro:** listagem via DTO campo a campo com `masked_api_key`; `$hidden=['api_key']` no model (defesa em profundidade); no editar, chave em branco mantém a atual (RN-009).
- **Teste de conexão HTTP REAL** (`GET {base_url}/models` com `Authorization: Bearer`) — sem chave válida falha honestamente; sem fachada.
- **Anti-SSRF (requisito ALTO):** `https` obrigatório + allowlist de hosts (controle PRIMÁRIO), sem seguir redirects, `connectTimeout` baixo + teto do `timeout`. Validado no FormRequest E re-checado no client.
- **Resultado SANITIZADO:** `ConnectionResult { ok, mensagem categorizada }` — nunca corpo/headers da resposta, exceção crua nem a credencial; a `api_key` nunca é logada.
- **HU-014:** permissão `manter-config-ia` (só administrador) e 7 toggles `features.ia_*` (grupo `ia`, default OFF) administráveis, com fallbacks em `config/sile.php`.
- **Auditoria RN-002** em todas as operações, sem segredo (`auditProps` nunca inclui `api_key` nem corpo do provedor; `personal_data=false`).

## Ponte de runtime (Task 6 — ENTREGUE, fecha a Onda 0)

A configuração administrável do banco agora **alimenta o SDK `laravel/ai` em runtime**, sem deploy — a tela deixou de ser fachada e passou a controlar de verdade qual provedor/modelo a IA usa.

- **`AiConfigResolver`** (route-free) lê as `AiConfiguration` **ativas** e monta os overrides do SDK; **`AiConfigServiceProvider`** aplica no `boot()` (registrado em `bootstrap/providers.php`), com defensividade `Schema::hasTable` + `try/catch` (não quebra `migrate`/console/CI), espelhando o `MailConfigServiceProvider`.
- **Chaves REAIS do SDK v0.8.1 (≠ AI-SPEC):** o mapeamento usa `ai.default` (texto), `ai.default_for_embeddings`, e `ai.providers.{nome}` com **`driver` / `key` / `url` / `models.text.default`** — **NÃO** `api_key`/`base_url`. Tradução: `provider→driver` (com **`compativel→openai`**), `base_url→url`, `api_key→key`, `model→models.text.default` (ou `models.embeddings.default` para embeddings). `vision` é texto multimodal no SDK (sem default próprio): registra o provider e é escolhido por chamada.
- **Degradação honesta (anti-fachada):** sem nenhuma config ativa, `apply()` **não sobrescreve** — o SDK fica com os defaults do pacote e a IA permanece atrás dos toggles `features.ia_*` OFF. Config inativa não é aplicada.
- **Segurança (RN-009/LGPD):** o cache guarda a `api_key` como **ciphertext** (idêntico ao banco), **nunca em claro**; a decriptação acontece **só em `apply()`**, para a config em memória do processo — nunca persistida em claro nem logada.
- **Cache + invalidação (HU-014, efeito sem deploy):** `resolve()` cacheia por um TTL técnico curto (`ai.config_cache_ttl`, default 60s, em `config/sile.php` — fora do catálogo, baseline 97 intacto); gravar/excluir uma `AiConfiguration` invalida o cache na hora (evento `saved`/`deleted` do model, que cobre o CRUD da Onda 0 e o `setAsDefault()`).
- **`config/ai.php` enxuto:** declara só o que falta — `default*` e o bloco **`conversations`** (que o pacote referencia em `DatabaseConversationStore` mas **não publica**: `connection` = conexão padrão, tabelas `agent_conversations`/`agent_conversation_messages`). A lista de `providers` vem do próprio pacote via `mergeConfigFrom` — sem duplicar/divergir do SDK.
- **Prova de ponta a ponta SEM rede/credencial:** `BridgeProbeAgent` (interface `Agent` + trait `Promptable`) com `::fake(['ok'])`; após a ponte aplicar a config, `BridgeProbeAgent::make()->prompt('oi')->text === 'ok'` — o provedor `openai` só é resolvível porque a ponte populou `config('ai.providers.openai')`.
- **Seed de dev:** `AiConfigDevSeeder` (gate `local`, no-op em testing/produção) cria 1 provedor openai/texto padrão+ativo com **credencial placeholder explícita** (`sk-DEV-…`, nunca real), idempotente e que não sobrescreve uma chave real cadastrada pela tela.

### Peculiaridades da API 0.x do SDK encontradas

- **Fake é POR AGENT** (`MeuAgent::fake([...])` / `Ai::fakeAgent(...)`), **não há `Ai::fake()` global** — confirmado em `Concerns/InteractsWithFakeAgents`.
- O provedor é resolvido de `config('ai.default')` quando o Agent não declara `provider()`/`#[Provider]`; o modelo, de `config('ai.providers.{p}.models.text.default')` via `OpenAiProvider::defaultTextModel()` (fallback hardcoded do pacote). Por isso a ponte popula `models.text.default`.
- O pacote faz `mergeConfigFrom` do seu `config/ai.php` (não precisa publicar para funcionar); o bloco `conversations` é a única lacuna referenciada e não publicada.

## Task Commits

TDD estrito (RED → GREEN) por task:

1. **Task 2 — Permissão + toggles** — `87a234a` (test) → `8a25451` (feat)
2. **Task 3 — Contrato + teste de conexão anti-SSRF** — `aeeccb8` (test) → `2f8631b` (feat)
3. **Task 4 — CRUD backend + autorização** — `cb8ab76` (test) → `316b031` (feat)
4. **Task 5 — UI React + navegação** — `9e66e5c` (feat; verificado por Inertia render + tsc/build)
5. **Task 6 — Ponte runtime do SDK** — `5e9c323` (test) → `7d03636` (feat: resolver/provider/config/ai.php/evento) → `1b25ee1` (feat: AiConfigDevSeeder + teste do seed)

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

Os 22 testes alvo (AiConfigurationTest 5 + AiProviderClientTest 6 + AiConfigurationControllerTest 11) passam (filtro preciso `--filter='/(AiConfigurationTest|AiProviderClientTest|AiConfigurationControllerTest)/'` → 22/22, 71 asserções).

### Verificação da Task 6 (ponte runtime — evidência fresca)

```
php artisan test --compact --filter='/(AiConfigBridgeTest|AiConfigurationTest|AiProviderClientTest|AiConfigurationControllerTest)/'
{"tool":"phpunit","result":"passed","tests":28,"passed":28,"assertions":90}

php artisan test --compact --filter='/(DatabaseSeederTest|ParameterSeederTest)/'
{"tool":"phpunit","result":"passed","tests":28,"passed":28,"assertions":601}   # baselines 31 permissões / 97 parâmetros intactas

php artisan test --compact --filter=AiConfigDevSeederTest
{"tool":"phpunit","result":"passed","tests":3,"passed":3,"assertions":12}

php artisan config:show ai   # sanity: boot não quebra; providers do pacote + bloco conversations presentes
vendor/bin/pint --dirty --format agent -> passed
```

(Não foi rodado o `composer test` global — fica para o guardião/orquestrador.)

## Deviations from Plan

### Escopo: Task 6 (inicialmente adiada) ENTREGUE em sessão seguinte

- A execução inicial da Onda 0 adiou a Task 6 (SDK + ponte). Em sessão seguinte, com o `laravel/ai` v0.8.1 já presente no `composer.json`/`composer.lock` e a API real mapeada, a **Task 6 foi entregue**, fechando a Onda 0. A ponte aplica config REAL (não fachada); credencial indisponível continua bloqueando a função atrás do toggle, nunca simulando.
- **Ajuste de mapeamento (não desvio):** as chaves do SDK confirmadas no código (`driver/key/url/models.text.default`) diferem das citadas no AI-SPEC (`api_key/base_url`). A implementação seguiu as **chaves reais** (fonte de verdade = código do pacote).

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

**Pronto (Onda 0 COMPLETA):**
- Fundação multi-provider administrável navegável; contrato `AiProviderClient` + teste de conexão real.
- Toggles `features.ia_*` e permissão no lugar; teste de conexão real validável contra provedor (sem chave válida, falha honesta).
- **Ponte de runtime do SDK ENTREGUE:** a Onda 1 só precisa criar Agents (interface `Agent` + `Promptable`) que leem `config('ai.*')` — o provedor/modelo/credencial já vêm do banco via a ponte, atrás dos toggles `ia_*`. O bloco `conversations` (assistentes — Onda 2) já está declarado.

**Pendências externas (fora do escopo da ponte, NÃO fachada):**
- Credenciais reais de provedor (OpenAI/Anthropic/Google) são cadastradas pelo administrador na tela (criptografadas) — não há credencial no `.env` versionado.
- A validação contra homologação real de um provedor (chamada de IA de verdade) ocorre quando a Onda 1 acoplar a primeira função (OCR/classificação) com credencial válida — a ponte já está pronta para isso.

---
*Phase: 14-intelig-ncia-artificial*
*Completed: 2026-06-17*
