---
phase: 10-analise-tecnica-sedur
plan: 15
subsystem: analise
tags: [hu-083, hu-086, hu-087, hu-088, hu-089, hu-132, hu-136, endpoints, thin-controller, gestao-route-owner, signed-url, temporarySignedRoute, streaming, disco-nao-publico, gate-permissao, 403-auditado, anti-fachada, rn-002]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    plan: "09"
    provides: "currentAnalysisRecord (ficha vigente/finalizada) consumida pelo endpoint de decisão"
  - phase: 10-analise-tecnica-sedur
    plan: "10"
    provides: "AnaliseTecnicaDecisionService::decide(record, analista) — RN-004, transição/encerramento, ResultadoEmitido, DomainException (rascunho/não-em-análise)"
  - phase: 10-analise-tecnica-sedur
    plan: "11"
    provides: "PendenciaService::abrir(request, analista, descricao) + PendenciaInvalidaException (estado inválido)"
  - phase: 10-analise-tecnica-sedur
    plan: "12"
    provides: "MalhaFinaService::encaminhar/encaminharLote + MalhaFinaException (motivo obrigatório) — ortogonal ao status (RN-001)"
  - phase: 10-analise-tecnica-sedur
    plan: "13"
    provides: "TvlPdfService::generate(decision, ator) — TVL PDF de decisão deferida (FA-01), disco não público; parâmetro analise.tvl.download.ttl_minutos"
  - phase: 10-analise-tecnica-sedur
    plan: "01"
    provides: "permissões analisar-processos, encaminhar-malha-fina, emitir-tvl"
  - phase: 08-solicitacao-de-viabilidade
    plan: "11"
    provides: "padrão de URL pública assinada (URL::temporarySignedRoute + middleware signed) a espelhar no download do TVL"
provides:
  - "5 endpoints gestao (Wave 7 route-owner) das ações do analista, controllers FINOS orquestrando os serviços das Waves 5/6: decidir, abrir pendência, encaminhar malha fina (single+lote), emitir TVL e baixar TVL"
  - "App\\Http\\Controllers\\Gestao\\ProcessoDecisaoController (invokable) — decidir/encerrar a partir da ficha finalizada (gate analisar-processos); DomainException → 422"
  - "App\\Http\\Controllers\\Gestao\\ProcessoPendenciaController@store — abrir pendência (gate analisar-processos); PendenciaInvalidaException → 422; AbrirPendenciaRequest valida descricao"
  - "App\\Http\\Controllers\\Gestao\\MalhaFinaController@store — encaminhar single+lote (gate encaminhar-malha-fina); EncaminharMalhaFinaRequest valida request_ids+motivo (single=lote de um)"
  - "App\\Http\\Controllers\\Gestao\\TvlDocumentController@store/@download — emitir (gate emitir-tvl, FA-01 → 422) + download por temporarySignedRoute (signed) servindo o disco NÃO público por streaming (HU-132 CA-02)"
affects: [10-16, 10-17, 10-18]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Controller FINO sobre serviço já testado: o endpoint só resolve binding/permissão, delega ao serviço e traduz a exceção de domínio (DomainException/PendenciaInvalidaException/MalhaFinaException) em 422 — ZERO regra reimplementada"
    - "Download de artefato sensível por URL temporária assinada (URL::temporarySignedRoute + middleware signed) servindo Storage::disk(...)->download de disco NÃO público — espelha a consulta pública 08-11, agora autenticado + gated (defesa em profundidade)"
    - "Ações Inertia (decidir/pendência/malha fina) respondem back()->with(status); endpoint de dado (emitir TVL) responde JSON com o link de download — coerente com os padrões 10-09 (XHR/JSON) e CaixaSetorController (redirect)"
    - "request_id único normalizado para request_ids no prepareForValidation (single = lote de um) — espelha DistribuirProcessoRequest"

key-files:
  created:
    - app/Http/Controllers/Gestao/ProcessoDecisaoController.php
    - app/Http/Controllers/Gestao/ProcessoPendenciaController.php
    - app/Http/Controllers/Gestao/MalhaFinaController.php
    - app/Http/Controllers/Gestao/TvlDocumentController.php
    - app/Http/Requests/Gestao/AbrirPendenciaRequest.php
    - app/Http/Requests/Gestao/EncaminharMalhaFinaRequest.php
    - tests/Feature/Analise/ProcessoDecisaoEndpointTest.php
    - tests/Feature/Analise/ProcessoPendenciaEndpointTest.php
    - tests/Feature/Analise/MalhaFinaEndpointTest.php
    - tests/Feature/Analise/TvlDocumentEndpointTest.php
  modified:
    - routes/gestao.php

key-decisions:
  - "Decidir resolve a ficha via currentAnalysisRecord e delega ao serviço; null → 422 (ficha ainda não criada), DomainException (rascunho/fora de em_analise) → 422 — o controller nunca decide por conta própria"
  - "Emitir TVL responde JSON 200 com o link de download (temporarySignedRoute); o download é GET assinado servindo o disco não público por streaming (Storage::disk->download). A assinatura + gate emitir-tvl + auth:gestao + disco não público garantem que o TVL não vaza por URL pública (CA-02)"
  - "Malha fina sempre via encaminharLote (single=lote de um); SEM restrição de status no request (RN-001 — atinge inclusive deferida). Motivo obrigatório no request (vazio → erro de validação); só-espaços é barrado pelo serviço (MalhaFinaException → 422)"
  - "Abrir pendência e malha fina respondem back()->with(status) (ações Inertia que recarregam a página); validação de formulário volta com erros de sessão (padrão Inertia), regra de estado volta 422"
  - "ÚNICO editor de routes/gestao.php na Wave 7: 5 rotas novas em grupo dedicado sob processos/, cada uma no seu gate; 10-16/10-17 só consomem os nomes de rota"

patterns-established:
  - "Casca HTTP gated/auditada sobre serviço de domínio route-free: o gate (permission:) audita o 403 no ponto único (bootstrap/app.php) e a exceção de domínio do serviço vira 422 — gabarito para os demais endpoints de ação da retaguarda"

# Metrics
duration: ~8min
completed: 2026-06-15
---

# Phase 10 Plan 15: Endpoints das ações do analista (Wave 7, gestao route-owner) — HU-083/086/087/088/089/132/136

**A casca HTTP que liga as telas (10-16/10-17) aos serviços JÁ TESTADOS das Waves 5/6 — controllers FINOS, ZERO regra reimplementada. Cinco endpoints gated por permissão (403 auditado no ponto único, CA-04) e auditados (RN-002): (1) `POST processos/{id}/decidir` (`ProcessoDecisaoController`, gate `analisar-processos`) resolve a ficha vigente (`currentAnalysisRecord`) e delega ao `AnaliseTecnicaDecisionService::decide` (10-10) — RN-004, encerramento HU-089 e `ResultadoEmitido` vêm do serviço; ficha em rascunho/processo fora de em_analise → 422; (2) `POST processos/{id}/pendencias` (`ProcessoPendenciaController@store`, `analisar-processos`) valida a descrição (`AbrirPendenciaRequest`) e chama `PendenciaService::abrir` (10-11) — em_analise→em_pendencia + e-mail ao requerente; fora de em_analise → 422; (3) `POST processos/malha-fina` (`MalhaFinaController@store`, gate `encaminhar-malha-fina`) encaminha single+lote via `MalhaFinaService::encaminharLote` (10-12) — ORTOGONAL ao status (RN-001, atinge até deferido), motivo obrigatório; (4) `POST processos/{id}/tvl` (`TvlDocumentController@store`, gate `emitir-tvl`) emite o TVL da decisão deferida via `TvlPdfService::generate` (10-13) — não deferida/sem decisão → 422 (FA-01) — e devolve o link de download; (5) `GET processos/tvl/{tvlDocument}/download` (`@download`, gate `emitir-tvl` + `signed`) faz o streaming do PDF do disco NÃO público por URL TEMPORÁRIA ASSINADA (`URL::temporarySignedRoute`, TTL `analise.tvl.download.ttl_minutos`) — nunca URL pública, nunca ao cidadão (CA-02). A decisão dispara `ResultadoEmitido` (Regin/SEFAZ BLOQUEADOS → Fase 13). TDD estrito (RED→GREEN com evidência fresca): `ProcessoDecisaoEndpointTest` 4/4 + `ProcessoPendenciaEndpointTest` 4/4 + `MalhaFinaEndpointTest` 4/4 + `TvlDocumentEndpointTest` 5/5 (filtro do plano 17/17, 72 asserções). Suíte completa SQLite 1112/1112 (5606) — zero regressão. ZERO dependência nova; ÚNICO editor de routes/gestao.php na Wave 7.**

## Performance
- **Duration:** ~8 min (início 2026-06-15T00:17:31Z)
- **Tasks:** 2 (decisão+pendência; malha fina+TVL) — 1 commit atômico por task
- **Files:** 11 (10 criados, 1 modificado: routes/gestao.php) — ZERO dependência nova

## Rotas (routes/gestao.php — ÚNICO route-owner da Wave 7)

Grupo dedicado sob prefixo `processos/`, name `gestao.processos.*`, dentro do grupo `auth:gestao + acessar-gestao + lgpd.accepted`. Insumo direto de 10-16/10-17/10-18:

| Método | Caminho | Controller | Name | Gate | Resposta |
|---|---|---|---|---|---|
| POST | `gestao/processos/{viabilityRequest}/decidir` | `ProcessoDecisaoController` (invokable) | `gestao.processos.decidir` | `analisar-processos` | redirect `back()` + flash `status` (422 em rascunho/fora de em_analise) |
| POST | `gestao/processos/{viabilityRequest}/pendencias` | `ProcessoPendenciaController@store` | `gestao.processos.pendencias.store` | `analisar-processos` | redirect `back()` + flash `status` (422 fora de em_analise; erros de sessão se descrição vazia) |
| POST | `gestao/processos/malha-fina` | `MalhaFinaController@store` | `gestao.processos.malha-fina.store` | `encaminhar-malha-fina` | redirect `back()` + flash `status`/`warning` (erros de sessão se motivo vazio) |
| POST | `gestao/processos/{viabilityRequest}/tvl` | `TvlDocumentController@store` | `gestao.processos.tvl.store` | `emitir-tvl` | JSON 200 `{document:{id,verification_code,generated_at}, download_url}` (422 se não deferida — FA-01) |
| GET | `gestao/processos/tvl/{tvlDocument}/download` | `TvlDocumentController@download` | `gestao.processos.tvl.download` | `emitir-tvl` + `signed` | streaming do PDF (disco não público); link sem assinatura/expirado → 403 |

### Payloads de entrada
- **decidir:** sem corpo (a decisão nasce da ficha finalizada — RN-004).
- **pendencias:** `{ descricao: string (obrigatória, max 2000) }`.
- **malha-fina:** `{ request_id: int }` (single) OU `{ request_ids: int[] }` (lote) + `{ motivo: string (obrigatória, max 2000) }`. `request_id` é normalizado para `request_ids` (single = lote de um).
- **tvl (emitir):** sem corpo (a fonte é a `ViabilityDecision` deferida do processo).

### Esquema do download assinado do TVL (HU-132 CA-02)
1. `store` emite o documento (`TvlPdfService::generate`) e devolve `download_url = URL::temporarySignedRoute('gestao.processos.tvl.download', now()->addMinutes(ttl), ['tvlDocument' => $id])`, TTL = `Settings::get('analise.tvl.download.ttl_minutos', config('sile.analise.tvl.download.ttl_minutos', 5))`.
2. `download` (middleware `signed`) valida assinatura/TTL e faz `Storage::disk($doc->disk)->download($doc->path, "tvl-{verification_code}.pdf")` — o disco é o parametrizado em `analise.tvl.disk` (NUNCA `public`, guarda do 10-13). O acesso é auditado (`analise`/`tvl-download`).
3. Defesa em profundidade: assinatura válida **+** gate `emitir-tvl` **+** `auth:gestao` **+** disco não público — o TVL não vaza por URL pública e não vai ao cidadão.

## Mapa CA → teste (provado)
| HU / RN | Teste | Evidência |
|---|---|---|
| HU-086 RN-004 — decidir defere (todas deferidas) | `ProcessoDecisaoEndpointTest::test_decide_defere_quando_a_ficha_finalizada_tem_todas_deferidas` | flow analise_tecnica, decided_by analista, TVL, status deferida, ResultadoEmitido |
| HU-087 RN-004 — decidir indefere | `...::test_decide_indefere_quando_a_ficha_tem_cnae_indeferida` | outcome indeferida, sem TVL, status indeferida |
| HU-086 CA-03 — ficha rascunho não decide | `...::test_recusa_decidir_quando_a_ficha_esta_em_rascunho_com_422` | 422; 0 decisões; status em_analise; evento não disparado |
| HU-083 — abrir pendência (em_analise) | `ProcessoPendenciaEndpointTest::test_abre_pendencia_em_processo_em_analise` | em_pendencia + analysis_pendencies (aberta) |
| HU-083 CA-03 — abrir fora de em_analise | `...::test_abrir_fora_de_em_analise_e_recusado_com_422` | 422; nada gravado |
| HU-083 — descrição obrigatória | `...::test_descricao_e_obrigatoria` | erro de validação; nada gravado |
| HU-136 RN-001 — malha fina single (em_analise) | `MalhaFinaEndpointTest::test_encaminha_single_em_analise` | fine_mesh_referrals + in_fine_mesh |
| HU-136 RN-001/004 — lote incl. deferido | `...::test_encaminha_em_lote_incluindo_processo_deferido` | 3 referrals; deferido continua deferido + in_fine_mesh |
| HU-136 RN-002 — motivo obrigatório | `...::test_motivo_e_obrigatorio` | erro de validação; nada gravado |
| HU-132 CA-01/RN-002 — emitir só deferida + auditoria | `TvlDocumentEndpointTest::test_emite_tvl_de_decisao_deferida` | 200 + tvl_documents + %PDF + analise/tvl-emitido + download_url |
| HU-132 FA-01 — só deferida | `...::test_bloqueia_emissao_de_decisao_indeferida_com_422` | indeferida → 422; 0 documentos |
| HU-132 — download por URL assinada (não público) | `...::test_download_com_url_assinada_valida_faz_streaming` | 200 streaming (%PDF) por link assinado |
| HU-132 — link sem assinatura/expirado | `...::test_download_sem_assinatura_ou_expirado_e_403` | unsigned → 403; expirado → 403 |
| CA-04 — segurança de acesso (gates) | todos os `test_sem_permissao_*_recebe_403_auditado` | 403 + seguranca/acesso-negado/bloqueado |

## Task Commits
TDD estrito (RED confirmado pelo motivo certo antes de cada GREEN):
1. **Task 1: decidir + abrir pendência** — `d10d47e` (feat) — RED: 8×404 → GREEN: `ProcessoDecisaoEndpointTest` 4/4 + `ProcessoPendenciaEndpointTest` 4/4 (33 asserções).
2. **Task 2: malha fina + emitir/baixar TVL** — `6a93a90` (feat) — RED: 405/404 + rota não definida → GREEN: `MalhaFinaEndpointTest` 4/4 + `TvlDocumentEndpointTest` 5/5 (39 asserções).

## Decisions Made
- **Decidir delega 100% ao serviço:** resolve `currentAnalysisRecord`; null → 422 (ficha não criada), `DomainException` (rascunho/fora de em_analise) → 422. A RN-004, a transição/encerramento e o `ResultadoEmitido` são do `AnaliseTecnicaDecisionService` — o controller não decide nada.
- **Emitir = JSON com link; download = GET assinado/streaming:** `store` devolve `{document, download_url}` (200) para a tela disparar o download; `download` valida `signed` e faz streaming do disco não público. Camadas: assinatura + `emitir-tvl` + `auth:gestao` + disco não público (CA-02/LGPD).
- **Malha fina sempre via `encaminharLote`** (single = lote de um), SEM restrição de status no request (RN-001). Motivo `required` no form (vazio → erros de sessão); só-espaços é barrado pelo serviço (`MalhaFinaException` → 422).
- **Respostas Inertia vs JSON:** decidir/pendência/malha fina são ações que recarregam a página (`back()->with('status')`); emitir TVL é dado consumido por XHR (JSON), coerente com 10-09. Erros de validação voltam como erros de sessão (padrão Inertia); regra de estado volta 422.
- **Um único grupo de rotas da Wave 7**, com um subgrupo por gate. As rotas estáticas (`malha-fina`, `tvl/{tvlDocument}/download`) convivem com o wildcard `{viabilityRequest}` por método/estrutura distintos (confirmado por `route:list`).

## Deviations from Plan
Nenhum desvio de escopo. Plano executado como escrito (2 tasks; 4 controllers + 2 requests + rotas + 4 feature tests com os cenários especificados). ZERO dependência nova; ZERO regra reimplementada (anti-fachada — toda a lógica vive nos serviços 10-10/10-11/10-12/10-13, já testada).

Notas de execução (dentro do escopo, discrição do CONTEXT em nomes de rota):
- **Nomes de rota:** `pendencias.store`, `malha-fina.store`, `tvl.store`, `tvl.download`, `decidir`. Insumo direto de 10-16/10-17.
- **Guarda `currentAnalysisRecord === null` → 422** no decidir: comportamento honesto (não decide sem ficha), além dos cenários nominais do plano.
- **Auditoria do download do TVL** (`analise`/`tvl-download`): acesso a artefato sensível auditado (RN-002/CA-02), além do que os testes exigem nominalmente.

## Authentication Gates
Nenhum — sem CLI/credencial externa neste plano (endpoints HTTP sobre serviços já prontos).

## Issues Encountered (cross-plan)
- Waves paralelas 10-16/10-17 (UI) commitando na mesma working dir (`resources/js/pages/gestao/processos/`, `resources/js/components/analise/` untracked; commits de 10-16 intercalados no log). NÃO toquei React/layout; staging individual dos meus 11 arquivos (nunca `git add -A`). Arquivos `.cursor/` não versionados ignorados.
- `STATE.md` NÃO alterado (consolidação a cargo do orquestrador — instrução do user query; evita clobber entre executores concorrentes).

## Verification (evidência fresca)
- **RED Task 1:** `--filter="ProcessoDecisaoEndpointTest|ProcessoPendenciaEndpointTest"` → 8 falhas por 404 (rotas inexistentes). **GREEN:** 8/8 (33 asserções).
- **RED Task 2:** `--filter="MalhaFinaEndpointTest|TvlDocumentEndpointTest"` → 405/404 + `Route [gestao.processos.tvl.download] not defined`. **GREEN:** 9/9 (39 asserções).
- **Filtro do plano:** `--filter="ProcessoDecisaoEndpointTest|ProcessoPendenciaEndpointTest|MalhaFinaEndpointTest|TvlDocumentEndpointTest"` → **17/17 (72 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed (em cada task).
- **`php artisan route:list --path=gestao`** → as 5 rotas registradas (decidir, pendencias.store, malha-fina.store, tvl.store, tvl.download) nos gates corretos.
- **Greps de aceite:** `malha-fina`/`tvl` em routes/gestao.php (gates encaminhar-malha-fina/emitir-tvl); `temporarySignedRoute` em TvlDocumentController; `AnaliseTecnicaDecisionService` em ProcessoDecisaoController.
- **Suíte completa SQLite:** `--exclude-group=postgis` → **1112/1112 (5606 asserções)** — zero regressão.

## Next Phase Readiness
- **10-16** (consulta/detalhe): botão "encaminhar à malha fina" (single + lote da seleção) → `gestao.processos.malha-fina.store`; Cmd+K e detalhe consomem os demais nomes de rota.
- **10-17** (ficha + admin + layout): a ficha aciona `gestao.processos.decidir` (deferir/indeferir/encerrar), `gestao.processos.pendencias.store` (abrir pendência) e `gestao.processos.tvl.store` (emitir TVL → usa `download_url` para baixar via `gestao.processos.tvl.download`).
- **10-18** (golden/smoke): exercita os endpoints ponta a ponta (decidir após finalizar a ficha; emitir + baixar o TVL assinado).
- **Bloqueio honesto mantido:** a decisão dispara `ResultadoEmitido`, mas Regin (HU-104)/SEFAZ (HU-110) seguem BLOQUEADOS (auditam pendência, nunca "enviado") → Fase 13. A decisão/TVL/auditoria/download são reais.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-15*
