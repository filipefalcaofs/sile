# Minha Área com processos do Viabiliza e do Simplifica — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Subir a Minha Área (SAD) no ambiente de teste do Portainer, com a lista do Viabiliza que já existe e uma lista nova dos processos do Simplifica pelo CPF do cidadão.

**Architecture:** O SAD ganha um serviço e um widget Filament para o Simplifica no molde do bloco do Viabiliza. A imagem de teste roda em `pg_local` sobre o Postgres do portal de teste. O portal de teste passa a mandar o "Minha área" para o login do SAD.

**Tech Stack:** Laravel 11 + Filament 4 (SAD), PHPUnit, Docker, Portainer API, Nginx Proxy Manager (`http.conf` custom).

**Spec:** `docs/superpowers/specs/2026-10-24-minha-area-processos-viabiliza-simplifica-design.md`

## Global Constraints

- Código do SAD só na branch local `viabiliza-minha-area` (`~/Downloads/fabrica/sedur-mais/sad`). Nada é enviado ao GitLab da SEDUR.
- Testes puros do SAD: `BASE=<repo> phpunit --no-configuration --bootstrap sedur-mais/ferramentas/bootstrap-unit.php` (vendor do Viabiliza), como nos testes do bloco do Viabiliza.
- Token do cliente do legado, credencial do Basic Auth e token do GitLab: só em `segredos.json`/env da stack. Nunca no repositório, em log ou no chat. Não copiar a URL com credencial do `composer.json` do SAD.
- Env novas: `SIMPLIFICA_API_URL`, `SIMPLIFICA_API_TOKEN`, `SIMPLIFICA_TIMEOUT` (padrão 5).
- Commits sem coautoria de IA.

## Review Focus

1. CPF do contato com máscara ou com menos de 11 dígitos: limpa a máscara; inválido não chama a API.
2. API do legado lenta além do timeout: a lista mostra "indisponível" e a página abre.
3. Resposta do legado sem `status` ou sem `numeros_tvl`: a linha aparece com "—", sem erro.
4. Sessão do Portal expirada: a lista do Viabiliza mostra o aviso de sessão expirada, e a do Simplifica continua.
5. Acesso a `minha-area-teste...` sem a credencial do proxy: 401.

---

### Task 1: Serviço e widget do Simplifica no SAD

**Files:**
- Create: `app/Services/SimplificaProcessosService.php`, `app/Filament/MinhaArea/Resources/DocumentoResource/Widgets/ProcessosSimplificaWidget.php`, `resources/views/minha_area/processos-simplifica.blade.php`, `config/simplifica.php`, `tests/Unit/SimplificaProcessosServiceTest.php`
- Modify: `app/Filament/MinhaArea/Resources/DocumentoResource/Pages/ListDocumento.php` (`getHeaderWidgets()` com os dois widgets), `config/viabiliza.php` (URL também com `VIABILIZA_DEMO=true`)

**Interfaces:**
- Produces: `SimplificaProcessosService::disponivel(): bool` (URL e token preenchidos); `::listar(?string $cpf): array{processos: list<array{numero: string, criado_em: string, situacao: string, finalizado: bool, viabilidades: string}>, indisponivel: bool}`.

- [ ] **Step 1: Failing tests:** `test_lista_os_processos_do_cpf` (requisição a `{url}/v1/processos?documento=12345678901` com Bearer; data em d/m/Y; `viabilidades` "2104040, 2104041"); `test_cpf_com_mascara_e_limpo`; `test_cpf_invalido_nao_chama_a_api` (lista vazia, nenhuma requisição); `test_falha_ou_401_vira_indisponivel`; `test_linha_sem_status_nem_tvls_usa_traco`; `test_sem_url_ou_token_fica_indisponivel_para_exibir` (`disponivel()` falso).
- [ ] **Step 2: Run** a suíte unitária do SAD — FAIL (classe inexistente).
- [ ] **Step 3: Implement** serviço (closure de HTTP injetável, como no `ViabilizaProcessosService`), widget (`canView()` = `disponivel()`; CPF do `Contato` logado em `minha_area`) e a view (mesma tabela e textos no estilo do bloco do Viabiliza; título "Processos do sistema anterior (Simplifica)"; colunas Processo, Data, Situação, Viabilidades).
- [ ] **Step 4: Run** — PASS, e os 7 testes do bloco do Viabiliza continuam passando.
- [ ] **Step 5: Commit** `feat: Meus Processos mostra os processos do sistema anterior (Simplifica) pelo CPF`

### Task 2: Imagem de teste do SAD

**Files (scratchpad da sessão, fora dos repositórios):** `sad-teste/Dockerfile`, `sad-teste/entrypoint.sh`, `sad-teste/monta-vendor.sh`

- [ ] **Step 1:** Montar o vendor: `composer install --no-dev` com a `sedur-mais-shared` apontada (repositório `path`) para a cópia do portal de teste; sem acesso ao GitLab, usar o vendor do portal de teste. Conferir com `php artisan route:list --path=minha-area` sem erro.
- [ ] **Step 2:** `docker build` (base `php:8.4-apache-bookworm` com `pdo_pgsql`, `intl`, `gd`, `zip`; `npm run build` dos assets do painel). Esperado: imagem `sad-minha-area-teste:<sha>`.
- [ ] **Step 3:** Rodar local contra um Postgres com o esquema do portal e conferir `GET /login/govbr?token=<base64 demo>` → 302 para `/minha-area`, que responde 200 com os três blocos.

### Task 3: Stack, proxy e portal de teste

- [ ] **Step 1:** Criar o cliente `sad-minha-area-teste` na API do legado com escopo `processos:ler` (o token vai só para `segredos.json`).
- [ ] **Step 2:** Enviar a imagem ao Portainer (`images/load`) e incluir o serviço `psd-sad` na stack `portal-servicos-demo` (redes `psd_net`, `ssl_default`, `legado-simplifica_default`; `APP_ENV=pg_local`, `VIABILIZA_DEMO=true`, `VIABILIZA_URL`, `SIMPLIFICA_*`, `SEDUR_ENDPOINT_PORTAL_NOVO_CARTA`). No `psd-app`, `SEDUR_ENDPOINT_MINHA_AREA_LOGIN_GOVBR={SAD}/login/govbr` e `SEDUR_ENDPOINT_MINHA_AREA={SAD}/minha-area`.
- [ ] **Step 3:** Publicar `minha-area-teste.144.22.212.3.nip.io` → `psd-sad:80` no `http.conf` custom do proxy, com Basic Auth; `nginx -t` e reload.
- [ ] **Step 4:** Conferir no navegador: Portal de teste → Minha área → credencial do proxy → "Meus Processos" com a tabela do SAD, a lista do Viabiliza (Detalhar abre a consulta do Viabiliza) e a lista do Simplifica.

### Task 4: Listagem única (revisão de 24/10)

**Files (SAD):** `app/Services/ProcessosUnificados.php` (puro: `linhasViabiliza`, `linhasSimplifica`, `daAba`, `buscar`, `paginar`), `app/Services/ViabilizaProcessosService.php` (expõe `situacao_codigo` e `urlConsulta(string $numero, string $token): ?string`), `ListDocumento.php` (`records()`, abas e contadores pela lista junta, coluna Origem, Detalhes por linha), remover os widgets e views dos blocos; `tests/Unit/ProcessosUnificadosTest.php`.

- [ ] Testes: abas de cada origem, busca nas três fontes, ordenação por data, paginação, URL da consulta do Viabiliza com o token para Viabiliza e Simplifica. Ver falhar, implementar, ver passar, commit; nova imagem `sad-minha-area-teste` e conferência da página.
