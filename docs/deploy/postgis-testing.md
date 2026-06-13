# Testes espaciais (grupo `postgis`)

A maior parte da suíte roda em **SQLite `:memory:`** (rápido, sem dependências).
O SQL espacial (PostGIS) da Fase 4 — `ST_Contains`, `ST_DWithin`/`ST_Distance`,
`ST_Intersects`, `ST_GeomFromGeoJSON`, `ST_MakeValid` — **não existe em SQLite** e
é provado num grupo separado, `@group postgis`, contra um Postgres real com a
extensão PostGIS.

## Por que um grupo separado

- A suíte padrão (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` no `phpunit.xml`)
  permanece rápida e sem serviços externos.
- Os testes do grupo `postgis` estendem `Tests\PostgisTestCase`, que aponta a
  conexão default para `pgsql_testing` **antes** de migrar (via `RefreshDatabase`)
  — sempre o banco `sile_testing`, **nunca** o banco de dev.

## Conexão `pgsql_testing`

Definida em `config/database.php`, lendo variáveis `DB_TEST_*` (com fallback para
as `DB_*`). Padrões:

| Ambiente | Host | Porta | Banco | Usuário |
|---|---|---|---|---|
| **Local (dev)** | `127.0.0.1` | **5433** (container de dev) | `sile_testing` | `sile` |
| **CI** | `127.0.0.1` | **5432** (service container) | `sile_testing` | `sile` |

No CI, o `env:` do job define `DB_TEST_PORT=5432` e vence o `.env` — o Dotenv do
Laravel é **imutável** e não sobrescreve variáveis já presentes no ambiente.

## Rodando localmente

Pré-requisito: o container de dev com PostGIS de pé na porta 5433
(`docker ps` deve listar `sile-pgsql` como `healthy`).

### 1. Preflight (uma vez, idempotente)

O container de dev cria só o banco `sile`. O grupo `postgis` precisa do
`sile_testing` com a extensão PostGIS. Crie-o com:

```bash
php artisan geo:preparar-banco-de-testes
```

O comando cria o banco `sile_testing` (se faltar) e garante
`CREATE EXTENSION IF NOT EXISTS postgis`. **Não toca o banco de dev `sile`.**

### 2. Rodar os grupos

```bash
# Suíte SQLite inteira (sem o grupo espacial) — rápida:
php artisan test --compact --exclude-group postgis

# Só o SQL espacial (Postgres + PostGIS):
php artisan test --compact --group postgis
```

## Enforcement: skip vira FALHA (sem fachada de teste)

`PostgisTestCase` tem um **guard de honestidade** para nunca "passar" sem rodar
SQL espacial de verdade:

1. **Servidor Postgres inacessível** → a distinção é por ambiente:
   - Sem `POSTGIS_TESTS_REQUIRED`: `markTestSkipped` (máquina de dev sem o
     container — skip legítimo, com a orientação do preflight).
   - Com `POSTGIS_TESTS_REQUIRED=true`: **FALHA** — o grupo não pode ser pulado.
2. **Servidor de pé, mas banco/extensão ausentes** → **FALHA** (com a orientação
   para rodar o preflight), nunca skip silencioso.

Ou seja: com o servidor de pé, qualquer problema é FALHA. E no CI, mesmo a
ausência do servidor é FALHA.

## CI (`.github/workflows/tests.yml`)

O workflow sobe um **service container `postgis/postgis:16-3.5`** (a extensão
PostGIS já é criada no `POSTGRES_DB=sile_testing` pelo init da imagem — o
preflight é só para o ambiente local) e roda os dois passos:

```yaml
env:
  DB_TEST_HOST: 127.0.0.1
  DB_TEST_PORT: 5432
  DB_TEST_DATABASE: sile_testing
  DB_TEST_USERNAME: sile
  DB_TEST_PASSWORD: secret
  POSTGIS_TESTS_REQUIRED: 'true'   # skip do grupo postgis vira FALHA
# ...
- run: php artisan test --compact --exclude-group postgis
- run: php artisan test --compact --group postgis
```

Com `POSTGIS_TESTS_REQUIRED=true`, se o PostGIS não estiver acessível o grupo
**falha** em vez de pular — o SQL espacial é sempre exercido no CI.
