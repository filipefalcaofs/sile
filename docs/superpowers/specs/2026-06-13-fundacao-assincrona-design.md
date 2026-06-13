# Fase 3.1 — Fundação assíncrona: scheduler, jobs e retenção

**Data:** 2026-06-13
**Status:** Aprovado
**Fase:** 3.1 (INSERTED, após a Fase 3)
**Origem:** Levantamento "Laravel 13 — recursos prontos não usados" (2026-06-12)

## Objetivo

Ativar a infraestrutura assíncrona que o Laravel 13 entrega pronta e que ainda não usamos — scheduler, jobs em fila com retry e retenção de dados — fechando lacunas que travariam prazos automáticos (HU-134), SLA (HU-144/147) e integrações (HU-146) nas fases seguintes. Sem features de fachada: cada peça executa lógica real de ponta a ponta.

## Contexto atual (verificado no código)

- `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis` (predis); `docker-compose.yml` de dev tem serviço `redis`.
- `composer dev` roda `serve` + `queue:listen` + `pail` + `vite` — **sem scheduler**.
- Sem `withSchedule()` em `bootstrap/app.php`; `routes/console.php` só tem `inspire`.
- As migrations `jobs`, `failed_jobs` e `job_batches` JÁ existem (`database/migrations/0001_01_01_000002_create_jobs_table.php`, padrão do Laravel 11+) — não há migration a criar; os jobs apenas as USAM.
- `AccessLog` (`app/Models/AccessLog.php`): sem pruning; `const UPDATED_AT = null`, tem `created_at`. NÃO usa `HasAuditoria` (já É auditoria de acesso).
- `BrasilApiCnpjLookup` já faz `->retry(retries, 200, throw: false)` + `timeout`/`connectTimeout`, mas os valores vêm de **constantes** em `config/sile.php` (`sile.integrations.cnpj_lookup.*`), não de parâmetros.
- `RedesimImportService->import(path)` e `CnaeImportService` existem e são síncronos; comando `redesim:importar` despacha o serviço direto. Não há comando de CNAE (carga via `CnaeSeeder`).
- Produção: **só** o compose de dev existe no repo; não há Dockerfile/supervisord/compose Portainer. Decisão do usuário (2026-06-13): produção apenas **documentada** nesta fase.

## Decisões travadas

1. **Driver de fila:** manter **Redis** (já configurado; zero dependência nova). `failed_jobs` sempre grava no banco (driver `database-uuids`) — as migrations já existem (não criar).
2. **Primeira rotina real do scheduler = pruning de retenção de `access_logs`** — mata dois critérios (scheduler real + retenção LGPD) sem rotina artificial.
3. **Trilha de auditoria de decisões (`activity_log`/RN-002) NÃO é podada** nesta fase — retenção longa por compliance; política completa fica na Fase 12.
4. **Produção documentada**, não construída: `docs/deploy/producao-assincrona.md` com cron `schedule:run` + `queue:work`/Horizon. O stack Portainer entra quando o deploy for montado.
5. **Sem Horizon / `Queue::route()` / `Http::pool` / `Concurrency` nesta fase** (YAGNI) — anotados para a Fase 13.

## Unidades de trabalho

### U1 — Scheduler ativo + primeira rotina real (pruning)
- `composer dev`: somar `php artisan schedule:work` (4 → 5 processos).
- `routes/console.php`: `Schedule::command('model:prune')` diário, `->withoutOverlapping()` + `->onOneServer()` (preparado para multi-instância; padrão herdado por HU-134/HU-147).
- `AccessLog` recebe `MassPrunable` + `prunable()` = `where('created_at', '<', now()->subDays(retencao.access_logs.dias))`.
- `activity_log` permanece fora do pruning (sem trait Prunable).
- **Teste:** rotina registrada no schedule; pruning remove logs além da retenção e preserva activity_log; alterar o parâmetro muda a janela.

### U2 — Jobs em fila com retry/timeout/relatório
- Tabelas de fila `failed_jobs` e `job_batches`: JÁ existem (migration padrão `0001_01_01_000002_create_jobs_table.php`) — os jobs apenas as usam; nenhuma migration nova.
- `ImportRedesimJob` envelopa `RedesimImportService` com `$tries`, `$timeout`, `backoff()`; o comando `redesim:importar` ganha flag `--queue` para despachar o job (modo síncrono preservado para homologação local).
- `ImportCnaeJob` envelopa `CnaeImportService`; novo comando `cnae:importar {arquivo} {--queue}` dá ao admin um caminho assíncrono de reimportação oficial (hoje só via re-seed).
- Jobs falhos visíveis em `failed_jobs` e reprocessáveis (`queue:retry`); auditoria do resultado preservada (o serviço já audita).
- **Teste:** job processa via `Bus::fake()`/fila real de teste; retry/backoff configurados; falha vai para `failed_jobs` (não silenciosa).

### U3 — Throttle parametrizado nas rotas públicas
- `RateLimiter` nomeado (ex.: `cnpj-lookup`) com limite lido de parâmetro; aplicado em `portal/empresas/consultar-cnpj`.
- 429 ao exceder; padrão estabelecido para HU-069 (protocolo) e EP07.
- **Teste:** N requisições passam, a N+1 recebe 429; alterar o parâmetro muda o limite (efeito sem deploy).

### U4 — HTTP client com retry/backoff parametrizado
- `BrasilApiCnpjLookup`: `retries`, `timeout` e novo `backoff_ms` lidos de **parâmetros** (fallback em `config/sile.php`).
- **Teste:** `Http::fake()` com falhas iniciais → sucesso após retry; o número de tentativas segue o parâmetro.

### U5 — Catálogo de parâmetros
Novos parâmetros administráveis (tipo, default, validação, grupo):
- `retencao.access_logs.dias` — integer, default 365, `min:30 max:3650` (grupo `retencao`).
- `seguranca.throttle.cnpj_lookup.por_minuto` — integer, default 30, `min:1 max:300` (grupo `seguranca`).
- `integrations.cnpj_lookup.retries` — integer, default 2, `min:0 max:5` (grupo `integracoes`).
- `integrations.cnpj_lookup.timeout` — integer (segundos), default 8, `min:1 max:30`.
- `integrations.cnpj_lookup.backoff_ms` — integer, default 200, `min:0 max:5000`.
- **Teste:** seeder cria os parâmetros; idempotência; contagem atualizada.

### U6 — Documentação de produção
- `docs/deploy/producao-assincrona.md`: cron do `schedule:run`, `queue:work` (e nota sobre Horizon/Portainer), variáveis de fila, e o aviso de que o stack Docker de produção é trabalho de deploy futuro.

## Critério de pronto (espelha o ROADMAP)

1. Scheduler ativo em dev (`composer dev`) com rotina real idempotente e auditada; produção documentada.
2. REDESIM e CNAE executam como jobs com retry/timeout/relatório; falhos visíveis em `failed_jobs`.
3. Rotas públicas com throttle parametrizado (consulta de CNPJ).
4. `access_logs` com retenção parametrizada por pruning; `activity_log` fora do pruning.
5. HTTP client com retry/backoff parametrizado.
6. Todos os CAs cobertos por feature tests PHPUnit; `pint`/`typecheck`/`build` verdes; sem features de fachada.

## Fora de escopo (YAGNI)

Horizon, fila dedicada por integração (`Queue::route()`), `Http::pool`/`Concurrency`, stack Docker de produção — Fase 13/deploy.
