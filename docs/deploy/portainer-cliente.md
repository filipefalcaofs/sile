# Deploy Portainer — ambiente de validação SEDUR

Guia para subir o SILE no Portainer e entregar à cliente para validação fase a fase.

## Arquitetura

Duas stacks no mesmo servidor Docker:

| Stack | Compose | Função |
|---|---|---|
| `sile-infra` | `docker-compose.portainer-infra.yml` | PostgreSQL 16 + PostGIS + pgvector, Redis, rede `sile_net` |
| `sile-app` | `docker-compose.portainer-full.yml` | App Laravel, fila, scheduler, migrate + seed |

Ordem: **infra primeiro**, depois **app**.

## 0. Publicar a imagem Docker (obrigatório antes do primeiro deploy)

O Portainer precisa puxar a imagem da aplicação. Na máquina de desenvolvimento:

```bash
docker login
./scripts/portainer-build-push.sh
```

Isso publica `filipefalcaofs97/sile:latest` no Docker Hub (AMD64).

Alternativa: tornar o pacote `ghcr.io/filipefalcaofs/sile` público no GitHub
ou cadastrar credenciais GHCR em Portainer → Registries, e ajustar `x-sile-image`
no compose.

## 1. Build da imagem PostGIS (uma vez no servidor)

A imagem `sile/pgsql:16-postgis-pgvector` não está no Docker Hub — build local:

```bash
git clone https://github.com/filipefalcaofs/sile.git
cd sile
docker build -t sile/pgsql:16-postgis-pgvector -f docker/postgres/Dockerfile docker/postgres
```

## 2. Stack sile-infra

Portainer → **Stacks** → **Add stack** → **Repository**:

| Campo | Valor |
|---|---|
| Name | `sile-infra` |
| Repository URL | `https://github.com/filipefalcaofs/sile.git` |
| Compose path | `docker-compose.portainer-infra.yml` |
| Branch | `main` |

Variáveis de ambiente:

```env
DB_PASSWORD=<senha-forte>
DB_PORT_HOST=5434
REDIS_PORT_HOST=6380
```

## 3. Stack sile-app

Portainer → **Stacks** → **Add stack** → **Repository**:

| Campo | Valor |
|---|---|
| Name | `sile-app` |
| Repository URL | `https://github.com/filipefalcaofs/sile.git` |
| Compose path | `docker-compose.portainer-full.yml` |
| Branch | `main` |

Variáveis de ambiente **obrigatórias**:

```env
APP_KEY=base64:...gerar com php artisan key:generate --show...
APP_URL=http://<IP-OU-DOMINIO>:8082
DB_PASSWORD=<mesma-senha-da-infra>
SILE_DEMO_DATA=true
APP_PORT=8082
```

Opcionais:

```env
APP_NAME=SILE
MAIL_MAILER=log
LOG_LEVEL=info
```

### Gerar APP_KEY

```bash
php artisan key:generate --show
```

## 4. Atualizar após deploy de código

1. Push na branch `main` (dispara build da imagem no GitHub Actions → `ghcr.io/filipefalcaofs/sile:latest`)
2. Portainer → stack `sile-app` → **Pull and redeploy** com **Re-pull image**

O container `seed` roda a cada redeploy quando `SILE_DEMO_DATA=true` (idempotente).

## 5. Credenciais para a cliente

Com `SILE_DEMO_DATA=true`, o seed cria:

### Retaguarda (validação SEDUR)

| Campo | Valor |
|---|---|
| URL | `{APP_URL}/gestao/login` |
| E-mail | `validacao@sedur.salvador.ba.gov.br` |
| Senha | `SileDemo2026!` |
| Perfil inicial | `validacao-fase-completa` |

### Portal do cidadão

| Campo | Valor |
|---|---|
| URL | `{APP_URL}/portal/login` |
| E-mail | `requerente@sedur.salvador.ba.gov.br` |
| Senha | `SileDemo2026!` |

### Admin técnico (sua equipe)

| Campo | Valor |
|---|---|
| E-mail | `admin@sile.dev` |
| Senha | `SileDemo2026!` |

### Analista / gestor / cidadão (demo)

No ambiente demo o seed rotaciona a senha de TODOS os usuários `@sile.dev`
para a senha demo (o ambiente é público — senha fraca `password` só em dev local).

| Papel | E-mail | Senha |
|---|---|---|
| Analista | `analista@sile.dev` | `SileDemo2026!` |
| Gestor | `gestor@sile.dev` | `SileDemo2026!` |
| Cidadão (seed) | `cidadao@sile.dev` | `SileDemo2026!` |

### Massa de demonstração (diversas situações)

Com `SILE_DEMO_DATA=true` o seed também cria a massa navegável (dados
fictícios, lógica real): solicitações em rascunho, protocolada, cancelada e
contingência; fluxo expresso com deferimento e TVL sobre a zona fictícia;
análise técnica (em análise, deferida com malha fina, em pendência);
comunicação multicanal; trilha de auditoria/explicabilidade/LGPD/abuso; e
massa de indicadores para o dashboard e os relatórios.

## 6. Liberar telas por fase

Perfis pré-criados (Gestão → Perfis):

| Perfil | Fases liberadas |
|---|---|
| `validacao-fase-02` | Painel + CNAEs |
| `validacao-fase-04` | + Território |
| `validacao-fase-07` | + LOUOS + Risco |
| `validacao-fase-10` | + Processos + Análise |
| `validacao-fase-completa` | + Auditoria + Relatórios |

Para restringir a cliente a uma fase: **Gestão > Usuários** → editar `validacao@sedur...` → trocar perfil.

## 7. Verificação pós-deploy

```bash
curl -fsS http://<host>:8082/up
```

Containers esperados:

- `sile-pgsql`, `sile-redis` (infra)
- `sile-app`, `sile-queue`, `sile-scheduler` (app)
- `sile-migrate` e `sile-seed` (one-shot, status Exited 0)

Logs do seed:

```bash
docker logs sile-seed
```

## 8. Bloqueios honestos (comunicar à cliente)

- Zona urbanística oficial (GIS SEDUR) — fluxo expresso degrada para análise humana
- Integrações REDESIM/SEFAZ/gov.br — Fase 13
- WhatsApp — toggle desligado
- Funções de IA — toggles desligados por padrão

## 9. Produção real vs. demo

| | Demo (cliente) | Produção |
|---|---|---|
| `SILE_DEMO_DATA` | `true` | `false` ou omitido |
| Zona fictícia | Sim (Centro) | Não |
| Usuários `@sile.dev` | Sim | Não |
| Senha demo | `SileDemo2026!` | Credenciais reais |

Nunca usar `SILE_DEMO_DATA=true` em produção definitiva.
