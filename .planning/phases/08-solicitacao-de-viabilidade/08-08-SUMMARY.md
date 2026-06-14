---
phase: 08-solicitacao-de-viabilidade
plan: 08
subsystem: portal
tags: [solicitacao-viabilidade, hu-066, hu-067, anexos, storage, disk-parametrizado, sha256, streaming, lgpd, document-requirement-resolver, rn-002, policy, anti-fachada]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: 01
    provides: "tabela viability_request_documents (disk/path/sha256/original_name/uploaded_by) + ViabilityRequest::documents() (hasMany) + DocumentRequirement (cnaes() belongsToMany) + factories"
  - phase: 08-solicitacao-de-viabilidade
    plan: 02
    provides: "parâmetros storage.documentos.disk (default local, nunca público), solicitacao.anexos.max_mb (10) e solicitacao.anexos.mime_permitidos (json) com fallback em config/sile.php"
  - phase: 08-solicitacao-de-viabilidade
    plan: 04
    provides: "CRUD de DocumentRequirement + contrato do resolver (whereHas('cnaes') ∪ condicionais base; tabela por-CNAE nasce vazia)"
  - phase: 08-solicitacao-de-viabilidade
    plan: 06
    provides: "indicador is_public_area gravado no imóvel (condicional do termo de concessão)"
  - phase: 08-solicitacao-de-viabilidade
    plan: 05
    provides: "ViabilityRequestPolicy::update (dono + rascunho) / ::view (dono) + rotas portal.solicitacoes.* (literais)"
  - phase: 01-identidade
    plan: "02"
    provides: "AuditService::log (RN-002) + 403 auditado globalmente (AccessDeniedHttpException → seguranca/acesso-negado/bloqueado, CA-04)"
provides:
  - "App\\Services\\Solicitacao\\DocumentRequirementResolver::required(ViabilityRequest): Collection — união dos requisitos required dos CNAEs + condicionais base (fachada sempre; concessão se área pública), sem duplicar"
  - "App\\Services\\Solicitacao\\DocumentRequirementResolver::missing(ViabilityRequest): Collection — obrigatórios ainda não atendidos por anexo (base do bloqueio do protocolo no 08-10)"
  - "App\\Http\\Controllers\\Portal\\SolicitacaoDocumentoController (store/download/destroy) — upload via Storage (disk parametrizado, nunca público) com sha256 real; download por streaming só do dono autenticado; remoção/substituição só em rascunho"
  - "App\\Http\\Requests\\Portal\\StoreSolicitacaoDocumentoRequest — file required + mimetypes/max dinâmicos (solicitacao.anexos.*) + requirement_id opcional"
  - "rotas portal.solicitacoes.documentos.{store,download,destroy}"
affects: [08-10-protocolo, 08-13-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Upload com disk PARAMETRIZADO (Settings::get('storage.documentos.disk', config(...,'local'))) — nunca disk público; o arquivo é gravado de verdade ($file->store(dir, disk)) e o sha256 REAL (hash_file) é persistido para integridade/auditoria"
    - "Validação dinâmica do anexo no rules() do FormRequest: mimetypes:{mime_permitidos} + max:{max_mb*1024} lidos via Settings::get (banco→cache→config) — efeito sem deploy (espelha [02-07]/[08-07])"
    - "Download por STREAMING autenticado: response()->streamDownload lendo Storage::disk($disk)->readStream($path) — nunca URL pública (LGPD); funciona para qualquer disk parametrizado (local/s3)"
    - "Resolver de obrigatoriedade documental = união (por id) dos required dos CNAEs (whereHas) com condicionais base referenciados por CÓDIGO ESTÁVEL (degrada honesto se ausentes) — valida o conhecido mesmo com a tabela por-CNAE vazia"
    - "Anti-IDOR em recurso aninhado: binding simples de {documento} + abort_unless($documento->viability_request_id === $solicitacao->id, 404)"

key-files:
  created:
    - app/Services/Solicitacao/DocumentRequirementResolver.php
    - app/Http/Controllers/Portal/SolicitacaoDocumentoController.php
    - app/Http/Requests/Portal/StoreSolicitacaoDocumentoRequest.php
    - tests/Feature/Solicitacao/DocumentRequirementResolverTest.php
    - tests/Feature/Solicitacao/AnexarDocumentoTest.php
  modified:
    - routes/portal.php

key-decisions:
  - "Disk SEMPRE do parâmetro storage.documentos.disk (Settings::get, default inline 'local' via config/sile.php) — NUNCA público. O acesso ao documento é sempre por streaming autenticado (response()->streamDownload + Storage::disk($disk)->readStream), nunca por URL pública/temporária (a consulta pública sem login HU-069 do 08-11 NÃO expõe anexos — LGPD)."
  - "sha256 REAL gravado: hash_file('sha256', $file->getRealPath()) calculado ANTES do store() (a movimentação do temporário invalida getRealPath/getSize) — integridade do anexo e insumo de auditoria. Metadados (original_name/mime_type/size) também capturados antes do store()."
  - "MIME por mimetypes (não mimes): o parâmetro solicitacao.anexos.mime_permitidos guarda TIPOS MIME completos (application/pdf, image/jpeg, image/png), então a regra é mimetypes:{lista} (checa o MIME real do conteúdo, não a extensão). max em KB = max_mb*1024. Ambos lidos dinamicamente no rules() (HU-014)."
  - "Substituição antes do protocolo: ao anexar com requirement_id, o anexo anterior do MESMO requisito é removido (disk + linha) na mesma transação — mantém um documento por requisito. Anexo avulso (requirement_id null) é permitido e não dispara substituição."
  - "Autorização: store/destroy usam Gate::authorize('update') (dono + rascunho — anexo só antes do protocolo); download usa Gate::authorize('view') (dono, qualquer status). 403 é auditado globalmente (seguranca/acesso-negado/bloqueado, CA-04). Anti-IDOR: {documento} validado contra a solicitação (abort_unless 404)."
  - "DocumentRequirementResolver: required() = (document_requirements required+active vinculados aos CNAEs via whereHas('cnaes')) ∪ condicionais base por CÓDIGO ESTÁVEL — foto-fachada SEMPRE; termo-concessao SE is_public_area; união por id (sem duplicar). missing() = required() menos os requisitos já cobertos por anexo (requirement_id). Degrada honesto: sem requisito por-CNAE (tabela vazia) ou sem o requisito-base seedado, só retorna o que existe e está ativo — nunca inventa."
  - "Códigos estáveis dos condicionais base no resolver: DocumentRequirementResolver::CODE_FACHADA = 'foto-fachada' e ::CODE_CONCESSAO = 'termo-concessao' — a serem seedados no 08-16 (o resolver os referencia por code e ignora se ausentes/inativos)."

patterns-established:
  - "Anexo de arquivo no portal: Gate::authorize('update') → FormRequest com mimetypes/max parametrizados → captura de metadados+sha256 antes do store() → transação (substituição do mesmo requisito + store no disk parametrizado + create da linha + auditoria) → back() com flash. Download por streamDownload autenticado. Molde para futuros anexos."

# Metrics
duration: ~14 min
completed: 2026-06-14
---

# Phase 8 Plan 08: Anexar Documentos (HU-066) e Validar Obrigatoriedade Documental (HU-067) Summary

**A solicitação agora recebe anexos de verdade e sabe o que ainda falta. O `SolicitacaoDocumentoController` faz upload via `Storage` com o disk PARAMETRIZADO (`storage.documentos.disk`, default `local`, NUNCA público), grava o `sha256` REAL do conteúdo (calculado antes do `store()`) com `disk`/`path`/`original_name`/`mime_type`/`size`, e audita cada anexo (RN-002). O `StoreSolicitacaoDocumentoRequest` lê os tipos aceitos (`mimetypes`) e o tamanho máximo (`max_mb`) DINAMICAMENTE do catálogo (HU-014, efeito sem deploy). O download é por STREAMING e só do dono autenticado (`response()->streamDownload` + `Storage::disk($disk)->readStream` — nunca URL pública, LGPD), com auditoria de acesso; substituição e remoção só enquanto a solicitação é rascunho, e `{documento}` é validado contra a solicitação (anti-IDOR). O `DocumentRequirementResolver` calcula os obrigatórios como a UNIÃO dos `document_requirements` (required, ativos) vinculados aos CNAEs da solicitação com os condicionais base — foto da fachada SEMPRE; termo de concessão SE a área é pública —, e `missing()` é a base do bloqueio do protocolo (08-10). ANTI-FACHADA: a tabela por-CNAE nasce VAZIA (carga oficial pendente SEDUR), mas o resolver degrada honesto e JÁ valida os obrigatórios-base conhecidos. ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): 5 + 6 = 11 testes novos; suíte completa 761/761 (excluindo apenas o teste em andamento do 08-09 paralelo), incl. 16 `@group postgis` com o container `sile-pgsql` healthy.**

## Performance

- **Duration:** ~14 min
- **Completed:** 2026-06-14
- **Tasks:** 2 (Task 1 resolver + testes; Task 2 controller/request/rotas + testes)
- **Files:** 5 criados + 1 modificado (`routes/portal.php`, append-only) — ZERO dependência nova

## Accomplishments

- **`DocumentRequirementResolver`** (HU-067): `required()` une os requisitos `required`+ativos dos CNAEs da solicitação (via `whereHas('cnaes')`) com os condicionais base (fachada sempre; concessão se `is_public_area`), sem duplicar; `missing()` desconta os já cobertos por anexo — base do bloqueio do protocolo (08-10) e do aviso.
- **Anti-fachada provado**: com a tabela `cnae_document_requirement` vazia, `required()` ainda traz o obrigatório-base conhecido (fachada) — `test_tabela_por_cnae_vazia_ainda_valida_base`.
- **`SolicitacaoDocumentoController@store`** (HU-066): upload via `Storage` no disk parametrizado (nunca público), `sha256` real, metadados; substituição do mesmo requisito e auditoria, tudo em transação; só dono em rascunho.
- **`download`**: streaming autenticado do disk gravado (nunca URL pública — LGPD), com auditoria de acesso a dado; terceiro → 403 auditado (CA-04); anti-IDOR.
- **`destroy`**: remove o anexo (disk + linha) só em rascunho, auditado.
- **`StoreSolicitacaoDocumentoRequest`**: `file` required + `mimetypes:{mime_permitidos}` + `max:{max_mb*1024}` lidos dinamicamente (HU-014); `requirement_id` opcional (`Rule::exists`).

## Rotas (nomes exatos — insumo de 08-10/08-13)

| Método | URI | Nome |
|---|---|---|
| POST | `portal/solicitacoes/{solicitacao}/documentos` | `portal.solicitacoes.documentos.store` |
| GET | `portal/solicitacoes/{solicitacao}/documentos/{documento}/download` | `portal.solicitacoes.documentos.download` |
| DELETE | `portal/solicitacoes/{solicitacao}/documentos/{documento}` | `portal.solicitacoes.documentos.destroy` |

Grupo `auth:web` + `verified` + `lgpd.accepted` + `ResolveRepresentation`; rotas com `{solicitacao}` DEPOIS das literais. Anexadas após a rota do imóvel (coordenação com 08-09, zero clobber).

## Contratos (insumo dos planos seguintes)

- **`DocumentRequirementResolver::required(ViabilityRequest): Collection<DocumentRequirement>`** — união dos required dos CNAEs ∪ condicionais base (fachada/concessão), sem duplicar (por id).
- **`DocumentRequirementResolver::missing(ViabilityRequest): Collection<DocumentRequirement>`** — required() menos os já cobertos por anexo (por requirement_id). **08-10 bloqueia o protocolo quando `missing()` não está vazio** (aviso ao requerente; o bloqueio em si é do 08-10).
- **Códigos estáveis dos condicionais base** (a seedar no 08-16): `DocumentRequirementResolver::CODE_FACHADA = 'foto-fachada'` (sempre), `::CODE_CONCESSAO = 'termo-concessao'` (se `is_public_area`).
- **Disk/streaming**: o disk vem de `storage.documentos.disk` (nunca público); o download é sempre por `response()->streamDownload` autenticado — a consulta pública HU-069 (08-11) NÃO expõe anexos.

## Task Commits

TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):

1. **feat(08-08)** — `19c9b87` — `DocumentRequirementResolver` + `DocumentRequirementResolverTest`. RED: 5 erros (classe inexistente) → GREEN: 5/5 (11 asserções).
2. **feat(08-08)** — `df61479` — `SolicitacaoDocumentoController` + `StoreSolicitacaoDocumentoRequest` + rotas + `AnexarDocumentoTest`. RED: 6 erros (rotas inexistentes) → GREEN: 6/6 (33 asserções).

**Plan metadata:** `docs(08-08)` (este SUMMARY + STATE).

## Decisions Made

- **Disk parametrizado, nunca público; acesso só por streaming autenticado** (LGPD). O `sha256` real é calculado antes do `store()` (metadados também) e persistido.
- **MIME por `mimetypes`** (o parâmetro guarda tipos MIME completos), `max` em KB = `max_mb*1024`; ambos dinâmicos no `rules()` (efeito sem deploy).
- **Substituição por requisito** (um documento por `requirement_id`); anexo avulso permitido.
- **Resolver une required dos CNAEs ∪ condicionais base por código estável**, degrada honesto com a tabela por-CNAE vazia; `missing()` é a base do bloqueio do protocolo.

## Deviations from Plan

None — plano executado como escrito (2 tasks, TDD). Decisões dentro do escopo/discrição do executor: (1) download via `response()->streamDownload` lendo `Storage::disk($disk)->readStream` (em vez de `Storage::download`) — satisfaz o critério de aceite (`streamDownload`), funciona para qualquer disk parametrizado e é genuinamente por streaming; (2) anti-IDOR com `abort_unless` no `{documento}` (binding simples); (3) eventos de auditoria nomeados `documento-anexado`/`documento-download`/`documento-removido`. Nomes de teste pt-BR normalizados pelo Pint (`php_unit_method_casing`).

## Issues Encountered

- **08-09 (simulação HU-141) em paralelo na MESMA working dir (mesmo `routes/portal.php`):** o 08-09 já havia commitado seu Task 1 (`9dab9dc`, exposição da consulta por ponto+CNAE) e tem o `SimulacaoSolicitacaoTest` commitado em RED (rota `portal.solicitacoes.simular` ainda não implementada). **Boundary respeitado**: reli `routes/portal.php` imediatamente antes de editar e ANEXEI só o meu import + 3 rotas após a rota do imóvel (`git diff` confirmou exatamente o meu trecho, zero clobber); staging individual (nunca `git add -A`). O `vendor/bin/pint --dirty` tocou o `SimulacaoSolicitacaoTest.php` do 08-09 (no_unused_imports) — **revertido com `git checkout --`** para não interferir no trabalho deles; NÃO commitei nenhum arquivo de simulação.
- **Artefato conhecido de filtro misto SQLite+`@group postgis`**: a suíte completa em ordem natural (container `sile-pgsql` healthy) roda os `@group postgis` inline normalmente; os filtros pequenos do plano foram rodados como SQLite puro.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=DocumentRequirementResolverTest` → 5 erros (classe inexistente). **GREEN:** 5/5 (11 asserções).
- **RED Task 2:** `--filter=AnexarDocumentoTest` → 6 erros (`Route [portal.solicitacoes.documentos.store] not defined`). **GREEN:** 6/6 (33 asserções).
- **Filtros do plano juntos:** `--filter="DocumentRequirementResolverTest|AnexarDocumentoTest"` → **11/11** (44 asserções).
- **Critérios de aceite (greps):** `class DocumentRequirementResolver` + `required()`/`missing()`; `fachada|is_public_area|concess` no resolver; `storage.documentos.disk`, `sha256` e `streamDownload` no controller; 3 rotas `documentos` em `route:list`.
- **`vendor/bin/pint --dirty --format agent`** → passed (arquivos do 08-08).
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **770 testes, 762 passaram, 8 erros** — os 8 erros são EXCLUSIVAMENTE do `SimulacaoSolicitacaoTest` (08-09 em andamento, rota `portal.solicitacoes.simular` ainda não criada). Excluindo apenas essa classe em andamento: **761/761** (3850 asserções; = 750 baseline + 11 do 08-08; inclui 16 `@group postgis` com o container `sile-pgsql` healthy). Nenhuma regressão introduzida pelo 08-08.

## Next Phase Readiness

- **08-10 (protocolo):** `DocumentRequirementResolver::missing()` é a base do bloqueio — protocolar com `missing()` não vazio deve ser bloqueado com aviso (o bloqueio em si é do 08-10). Os anexos passam a ser imutáveis após o protocolo (a policy `update` já barra edição fora de rascunho).
- **08-13 (UI):** consome `portal.solicitacoes.documentos.{store,download,destroy}` (upload multipart, download autenticado, remoção) e o resolver (`required`/`missing`) para listar os documentos exigidos e os faltantes.
- **08-16 (fechamento):** seedar os requisitos-base com os códigos estáveis `foto-fachada` e `termo-concessao` (o resolver já os referencia e degrada honesto até lá).
- **Bloqueio herdado (degrada honesto, registrado):** carga oficial dos requisitos por CNAE (planilha SEDUR vazia hoje) — o CRUD (08-04) está pronto para a SEDUR popular; quando a base chegar, muda a carga, não a lógica.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
