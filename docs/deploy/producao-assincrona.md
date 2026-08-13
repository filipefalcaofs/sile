# Operação assíncrona em produção — scheduler, filas e retenção

Guia operacional do SILE para rodar em produção o **scheduler** (tarefas
agendadas), o **worker de fila** (jobs assíncronos) e a **retenção** de dados,
introduzidos na Fase 3.1. Em desenvolvimento, o `composer dev` já sobe tudo
junto; produção troca isso por um cron + um worker persistente.

## Visão geral

| Ambiente | Scheduler | Worker de fila |
|----------|-----------|----------------|
| Dev (`composer dev`) | `php artisan schedule:work` (processo do concurrently) | `php artisan queue:listen` (recarrega o código a cada job) |
| Produção | cron chamando `php artisan schedule:run` a cada minuto | `php artisan queue:work` persistente sob supervisor |

`queue:listen` (dev) recarrega o código a cada job — conveniente para
desenvolvimento, mais lento. `queue:work` (produção) mantém o processo vivo —
por isso exige reinício após deploy (`php artisan queue:restart`).

## 1. Scheduler em produção (cron)

Uma única entrada de cron dispara todo o agendamento do Laravel:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

O que está agendado hoje (`php artisan schedule:list`):

```
0 0 * * *  php artisan model:prune --model='App\Models\AccessLog'
```

- **Pruning diário de `access_logs`** — remove acessos além da janela de
  retenção (parâmetro `retencao.access_logs.dias`, ver seção 6).
- Idempotente: `->withoutOverlapping()` (não sobrepõe execuções longas) e
  `->onOneServer()` (em multi-instância, só um servidor executa).

Esse é o padrão herdado pelas rotinas futuras (prazo BAP — HU-134;
escalonamento por SLA — HU-147).

## 2. Worker de fila em produção

Fila padrão: **Redis** (`QUEUE_CONNECTION=redis`). Rode um worker persistente:

```bash
php artisan queue:work redis --tries=3 --timeout=120
```

Use um supervisor (supervisord/systemd) para reinício automático em caso de
queda. Exemplo de programa supervisord:

```ini
[program:sile-worker]
command=php /caminho/do/projeto/artisan queue:work redis --tries=3 --timeout=120 --sleep=3
autostart=true
autorestart=true
stopwaitsecs=130
numprocs=1
```

Após cada deploy, sinalize os workers para recarregarem o código novo:

```bash
php artisan queue:restart
```

## 3. ATENÇÃO: `retry_after` deve exceder o maior `timeout` de job

Em `config/queue.php`, cada conexão tem `retry_after` (segundos). Esse valor
**precisa ser maior** que o maior `timeout` de job, senão a fila reprocessa um
job que **ainda está rodando** — causando execução dupla.

- Jobs desta fase (`ImportRedesimJob`, `ImportCnaeJob`) usam `timeout = 60s`.
- O worker acima usa `--timeout=120`.
- Garanta `retry_after` (ex.: `REDIS_QUEUE_RETRY_AFTER`) **acima** de 120s
  (ex.: 150). Regra: `retry_after > --timeout >= maior timeout de job`.

## 4. `onOneServer`/`withoutOverlapping` exigem cache compartilhado

Os locks de `->onOneServer()` e `->withoutOverlapping()` vivem no cache. Em
ambiente com mais de uma instância, o cache **precisa ser compartilhado**:
`CACHE_STORE=redis` (ou `database`/`memcached`) — nunca `array`/`file` por
instância, senão cada servidor roda a rotina agendada (perde a garantia de
execução única).

## 5. Jobs falhos: visíveis e reprocessáveis

Falha nunca é silenciosa. Jobs que esgotam as tentativas vão para a tabela
`failed_jobs` (driver `database-uuids`):

```bash
php artisan queue:failed          # lista as falhas
php artisan queue:retry all       # reprocessa todas
php artisan queue:retry {uuid}    # reprocessa uma
php artisan queue:flush           # descarta as falhas antigas
```

Os jobs de importação registram auditoria na falha (`failed()`), além do
relatório do serviço — a operação fica rastreável.

## 6. Retenção de dados

A rotina de pruning aplica o parâmetro **`retencao.access_logs.dias`**
(administrável por interface — HU-014, default 365), com efeito **sem deploy**
(limitado ao cache de parâmetros). A trilha de auditoria de decisões
(`activity_log`) fica **FORA** do pruning — retenção longa por compliance; a
política completa de retenção/LGPD da auditoria é da Fase 12.

## 7. Variáveis de ambiente relevantes

```env
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids
CACHE_STORE=redis            # obrigatório compartilhado em multi-instância
REDIS_HOST=...
REDIS_PORT=6379
REDIS_PASSWORD=...
REDIS_QUEUE_RETRY_AFTER=150  # > maior --timeout de worker (120)
```

## 8. Escopo: o que NÃO está nesta fase

Decisão travada da Fase 3.1 — itens de **deploy futuro**, não construídos aqui:

- **Stack Docker de produção** (Dockerfile, supervisord, `docker-compose.portainer-full.yml`) — trabalho de deploy/infra; só o compose de dev existe no repo.
- **Laravel Horizon** (painel de visibilidade das filas Redis) — avaliar na Fase 13 (integrações), junto com fila dedicada por integração.

Esta fase entrega scheduler, jobs com retry, retenção e throttle **funcionando
e testados**; este documento é a referência operacional para quando o ambiente
de produção for montado.

---

## Evidência de verificação (Fase 3.1)

Verificação integral fresca em 2026-06-13 (todos os comandos lidos por inteiro):

- `php artisan test --compact` → **385 testes, 1949 asserções, 0 falhas**.
- `vendor/bin/pint --dirty --format agent` → **passed** (sem pendências).
- `npm run typecheck` → verde (tsc sem erros).
- `npm run build` → verde (vite; app ~320 kB).
- `php artisan migrate:fresh --seed --no-interaction` → todos os seeders DONE, sem erros; catálogo com **25 parâmetros** (`Parameter::count()` = 25).
- `php artisan schedule:list` → `0 0 * * * php artisan model:prune --model='App\Models\AccessLog'`.
- `php artisan list` → comandos `redesim:importar` e `cnae:importar` presentes.
