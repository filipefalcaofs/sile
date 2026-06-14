---
phase: 10-analise-tecnica-sedur
plan: "13"
subsystem: analise
tags: [hu-132, tvl, pdf, dompdf, dependencia-nova, storage-nao-publico, assinatura-parametrizavel, auditoria, anti-fachada, route-free]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur
    plan: "01"
    provides: "parâmetros analise.tvl.* (disk/assinatura.modo/assinatura.imagem_path/download.ttl_minutos) + permissão emitir-tvl + fallback config sile.analise.tvl.*"
  - phase: 10-analise-tecnica-sedur
    plan: "02"
    provides: "tabela/model tvl_documents (viability_decision_id, disk, path, verification_code único, generated_by/at) + TvlDocumentFactory"
  - phase: 10-analise-tecnica-sedur
    plan: "09"
    provides: "ficha finalizada (currentAnalysisRecord: conditions/parecer/per_cnae) — condicionantes/parecer do TVL"
  - phase: 10-analise-tecnica-sedur
    plan: "10"
    provides: "ViabilityDecision deferida (flow analise_tecnica, decided_by analista, per_cnae/fundamentacao da ficha, tvl_product_number) — fonte do PDF, a MESMA do expresso"
  - phase: 09-fluxo-expresso
    plan: "02"
    provides: "ViabilityDecision (flow expresso, isDeferida, per_cnae/rules_versions/fundamentacao) + DecisionOutcome — o TVL serve os 2 flows pela mesma decisão"
provides:
  - "barryvdh/laravel-dompdf ^3.1 (v3.1.2) — ÚNICA dependência nova da fase (pré-aprovada PROJECT.md, lib do SIGVISA), isolada neste plano"
  - "App\\Services\\Analise\\TvlPdfService::generate(ViabilityDecision, ?User): TvlDocument — TVL PDF real (dompdf) só de decisão deferida (FA-01), no disco parametrizado NÃO público, com tvl_documents auditado (analise/tvl-emitido)"
  - "App\\Services\\Analise\\TvlPdfService::montarDados(ViabilityDecision, string, ?DateTimeInterface): array — contrato de dados do documento (insumo de pré-visualização do 10-15)"
  - "resources/views/tvl/documento.blade.php — template do TVL (número de produto, CNAEs deferidas, condicionantes, parecer, fundamentação, assinatura parametrizável, código de verificação) em pt-BR"
  - "config sile.analise.tvl.paper/orientation — constantes técnicas do dompdf (fora do catálogo HU-014)"
affects: [10-15, 10-18]

# Tech tracking
tech-stack:
  added:
    - "barryvdh/laravel-dompdf ^3.1 (v3.1.2) — wrapper dompdf para geração de PDF (Barryvdh\\DomPDF\\Facade\\Pdf)"
  patterns:
    - "PDF real anti-fachada: Pdf::loadView(Blade) + assert que o conteúdo gravado começa com '%PDF' (Storage::fake), nunca HTML simulado"
    - "Storage parametrizado NÃO público com guarda explícita: disco 'public' é RECUSADO (RuntimeException) — o TVL é interno (CA-02/LGPD)"
    - "Cada emissão/reimpressão = nova linha tvl_documents (verification_code ULID único + path determinístico tvl/{decision}/{code}.pdf) auditada — append-only, sem sobrescrever"
    - "Assinatura parametrizável embutida em data URI base64 (sem acesso remoto do dompdf); gov.br/ICP como gancho documentado"
    - "Serviço route-free: a mesma ViabilityDecision serve TVL de expresso e de análise humana (sem ramificar por flow)"

key-files:
  created:
    - app/Services/Analise/TvlPdfService.php
    - resources/views/tvl/documento.blade.php
    - tests/Feature/Analise/TvlPdfServiceTest.php
  modified:
    - composer.json
    - composer.lock
    - config/sile.php

key-decisions:
  - "dompdf instalado via composer require DE VERDADE (v3.1.2, resolvido pelo composer p/ Laravel 13/PHP 8.5) — pré-aprovada no PROJECT.md, sem aprovação adicional; isolada nesta wave"
  - "Disco do TVL NUNCA público: guarda que recusa 'public' (RuntimeException) além do default 'local' — anti-vazamento ativo, não só convenção"
  - "verification_code = 'TVL-'+ULID (único, monotônico) e compõe o path → cada reimpressão tem documento próprio (CA-03)"
  - "Fonte do PDF = ViabilityDecision deferida (número/CNAEs/fundamentação) + ficha finalizada (condicionantes/parecer via currentAnalysisRecord) — decisão do expresso (sem ficha) gera TVL sem condicionantes de ficha (honesto)"
  - "Assinatura: só embute imagem quando modo='imagem' E o arquivo existe; 'nenhuma' não assina — nunca firma forjada (anti-fachada). gov.br/ICP é gancho (RN-005 → SEDUR)"
  - "paper/orientation como constantes técnicas em config (fora do catálogo HU-014 — precedente 02-02)"

patterns-established:
  - "Geração de PDF no SILE: dompdf + Blade + Storage::fake nos testes (assert %PDF + linha auditada no disco não público)"

# Metrics
duration: ~18min
completed: 2026-06-14
---

# Phase 10 Plan 13: TVL em PDF no backoffice — TvlPdfService + dompdf (HU-132)

**A emissão do TVL como relatório administrativo INTERNO (não vai mais ao cidadão — canal oficial é Regin/SEFAZ). Instalei a ÚNICA dependência nova da fase — `barryvdh/laravel-dompdf` ^3.1 (v3.1.2), pré-aprovada no PROJECT.md (lib do SIGVISA), descoberta sem erro — e o `TvlPdfService::generate(ViabilityDecision, ?User): TvlDocument`: renderiza o TVL DE VERDADE com o dompdf sobre o Blade `tvl.documento` (o conteúdo gravado começa com `%PDF`), SÓ para decisões deferidas (FA-01: indeferida → `DomainException`), no disco parametrizado `analise.tvl.disk` e NUNCA público (guarda que recusa `public`), criando uma linha auditada em `tvl_documents` (verification_code único, generated_by/at) a cada emissão/reimpressão (RN-002/CA-03, evento `analise`/`tvl-emitido`). A fonte é a MESMA `ViabilityDecision` deferida do expresso e da análise humana (10-10) — número de produto, CNAEs deferidas e fundamentação — somada às condicionantes/parecer da ficha finalizada (10-09). Assinatura parametrizável (`analise.tvl.assinatura.modo` imagem/nenhuma) embutida em base64; gov.br/ICP é gancho (RN-005 → SEDUR). Serviço route-free: o endpoint emitir/download por URL assinada e o gate `emitir-tvl` (RN-001) são do 10-15. TDD estrito (RED→GREEN com evidência fresca): `TvlPdfServiceTest` 8/8 (34 asserções). Suíte completa SQLite 1081/1083 — as 2 falhas são do `ProcessoConsultaTest` (wave paralela 10-14, páginas Inertia `gestao/processos/*` ainda inexistentes), NÃO regressão deste plano. Paralelo file-disjunto com 10-14 (não toquei routes nem os arquivos do 10-14).**

## Performance
- **Duration:** ~18 min
- **Tasks:** 2 (dependência + config; serviço + template + testes)
- **Files:** 3 criados + 3 modificados (composer.json/lock pela dependência; config/sile.php pelas constantes técnicas)

## Dependência nova (insumo de 10-15)
- **`barryvdh/laravel-dompdf` ^3.1 → v3.1.2** instalada via `composer require` (composer resolveu a versão; NÃO inventada). Presente em `composer.json` (require) e `composer.lock`; provider/facade `Barryvdh\DomPDF` descobertos por `php artisan package:discover` sem erro.
- Facade usada: `Barryvdh\DomPDF\Facade\Pdf` → `Pdf::loadView('tvl.documento', $dados)->setPaper($paper, $orientation)->output()` (string binária do PDF).
- ÚNICA dependência nova da fase, isolada neste plano (CONTEXT/agentes-sile: pré-aprovada, sem escalonamento).

## Contrato (assinaturas — insumo de 10-15/10-18)

### `App\Services\Analise\TvlPdfService`
```php
public function __construct(private readonly AuditService $audit) {}

// Gera o TVL PDF de uma decisão DEFERIDA: renderiza o Blade (dompdf), grava no
// disco parametrizado (não público) e registra a emissão auditada em tvl_documents.
// Não deferida → DomainException (FA-01). Cada chamada = nova linha/arquivo.
public function generate(ViabilityDecision $decision, ?User $ator = null): TvlDocument;

// Contrato de dados do documento (público — serve pré-visualização do 10-15).
public function montarDados(ViabilityDecision $decision, string $verificationCode, ?DateTimeInterface $emitidoEm = null): array;
```

Fluxo de `generate`:
1. `! $decision->isDeferida()` → **DomainException** (FA-01).
2. `resolverDisco()` = `Settings::get('analise.tvl.disk', config('sile.analise.tvl.disk','local'))`; disco `public` → **RuntimeException** (guarda anti-vazamento, CA-02/LGPD).
3. `verification_code` = `'TVL-'.Str::ulid()` (único, monotônico); `generated_at` = `now()`.
4. `Pdf::loadView('tvl.documento', montarDados(...))->setPaper(paper, orientation)->output()` (paper/orientation de `config('sile.analise.tvl.*')`).
5. `Storage::disk($disk)->put("tvl/{decision_id}/{code}.pdf", $pdf)`.
6. `TvlDocument::create({viability_decision_id, disk, path, verification_code, generated_by_user_id=$ator?->id, generated_at})`.
7. `audit->log('analise','tvl-emitido', subject=$decision, result='sucesso', rulesVersion=representativa, properties={viability_request_id, viability_decision_id, tvl_document_id, tvl_product_number, verification_code, generated_by})`.

### Shape de `montarDados` (contrato da view `tvl.documento`)
```jsonc
{
  "tvl_product_number": "TVL-2026-000001|null",
  "verification_code": "TVL-01J...",
  "emitido_em": "<Carbon>", "decidido_em": "<Carbon>|null",
  "protocolo": "VIA-2026-000001|null",
  "empresa":  { "razao_social": "...|null", "nome_fantasia": "...|null", "cnpj": "00.000.000/0000-00|null" },
  "imovel":   { "endereco": "Rua, nº, compl", "bairro": "...|null", "cep": "...|null", "area_m2": "120.50|null" },
  "atividades": [ { "codigo": "4712100", "codigo_formatado": "4712-1/00", "descricao": "...|null", "condicionantes": ["..."] } ],
  "condicionantes": ["..."],          // gerais da ficha finalizada (conditions)
  "parecer": "...|null",               // da ficha finalizada
  "fundamentacao": ["Lei nº 9.148/2016 (LOUOS) — Quadro 7", "..."],  // da decisão
  "assinatura": { "modo": "imagem", "imagem": "data:image/png;base64,...|null" } | null
}
```
- **CNAEs deferidas:** todas as entradas de `decision.per_cnae` (numa decisão deferida todas as CNAEs estão deferidas — consolidação RN-009); descrição enriquecida pelo cadastro `Cnae` (lookup único), `cnae_formatado` do snapshot ou derivado.
- **Condicionantes/parecer:** lidos da ficha finalizada via `request->currentAnalysisRecord` (decisão do expresso sem ficha → sem condicionantes de ficha, honesto).
- **`listaDeTexto`** normaliza jsonb (lista de strings OU de objetos `{descricao}`), descartando vazios.

## Parametrização (HU-014 — lido via Settings::get com fallback config)
| Chave | Uso no TVL |
|---|---|
| `analise.tvl.disk` (default `local`) | disco do Storage — **recusa `public`** |
| `analise.tvl.assinatura.modo` (`imagem`/`nenhuma`) | embute firma do diretor ou não assina |
| `analise.tvl.assinatura.imagem_path` (default `''`) | caminho da imagem (base64 data URI; vazio/inexistente → sem firma) |
| `analise.tvl.download.ttl_minutos` (default 5) | **consumido em 10-15** (URL assinada) — não usado aqui |
| `sile.analise.tvl.paper`/`orientation` (config) | constantes técnicas do dompdf (`a4`/`portrait`) |

## Mapa CA → teste (provado)
| HU / RN | Teste | Evidência |
|---|---|---|
| HU-132 CA-01 — PDF com número/atividades/condicionantes/fundamentação | `test_template_contem_numero_atividades_condicionantes_e_fundamentacao` | render do Blade real: nº produto + `4712-1/00` + descrição CNAE + condicionante da ficha + fundamentação + nota "interno" (CA-02) |
| HU-132 — PDF real (anti-fachada) | `test_gera_pdf_real_no_disco_e_registra_tvl_document_auditado` | arquivo gravado começa com `%PDF` (Storage::fake) |
| HU-132 FA-01 — só deferida | `test_bloqueia_emissao_de_decisao_indeferida` | indeferida → DomainException; 0 linhas; 0 auditoria |
| HU-132 CA-03 / RN-002 — emissão/reimpressão auditada | `test_gera...` + `test_reimpressao_cria_nova_linha_e_nova_auditoria` | `analise`/`tvl-emitido`; reimpressão = 2 linhas/códigos/arquivos + 2 auditorias |
| HU-132 — disco não público | `test_usa_o_disco_parametrizado_e_nunca_o_publico` + `test_recusa_disco_publico_configurado` | disco = configurado, ≠ `public`; `public` → RuntimeException |
| HU-132 RN-005 — assinatura parametrizável | `test_assinatura...modo_imagem_embute_a_imagem` + `..._modo_nenhuma_nao_assina` | modo imagem com PNG real → `data:image`; modo nenhuma → sem `data:image` |

## Task Commits
TDD estrito (RED→GREEN verificado com evidência fresca antes de cada commit):
1. **Task 1: instala barryvdh/laravel-dompdf (pré-aprovada) + config do TVL** — `f57606d` (chore) — `composer require` real (v3.1.2), `package:discover` ok, constantes `analise.tvl.paper/orientation` em config.
2. **Task 2: TvlPdfService + template Blade + tvl_documents auditado** — `5a1ee2e` (feat) — RED: 8 erros (`Target class [TvlPdfService] does not exist`) → GREEN: `TvlPdfServiceTest` 8/8 (34 asserções).

_Commit único por task (teste + implementação coesos no domínio), espelhando 10-09/10-10._

## Decisions Made
- **dompdf instalado de verdade** (não simulado): `composer require barryvdh/laravel-dompdf` → v3.1.2; composer.json/lock atualizados e versionados no commit da dependência.
- **Disco NUNCA público com guarda ativa:** além do default `local`, o serviço recusa `public` (RuntimeException) — o TVL é interno (CA-02), proteção contra misconfiguração.
- **verification_code ULID + path determinístico** (`tvl/{decision}/{code}.pdf`): cada reimpressão é um documento próprio, append-only, auditado (CA-03).
- **Assinatura honesta:** só embute imagem quando configurada e existente; `nenhuma` não assina — sem firma forjada. gov.br/ICP fica como gancho (RN-005 → SEDUR; pendência registrada no 10-01).
- **Serviço route-free, 2 flows pela mesma decisão:** TVL do expresso e da análise humana saem do mesmo `TvlPdfService` (sem ramificar por `flow`); endpoint/gate/download em 10-15.

## Deviations from Plan
Nenhum desvio de escopo. Plano executado como escrito (2 tasks). Extras dentro do escopo, justificados:
- **Guarda de disco `public` (RuntimeException) + teste dedicado** — materializa "NUNCA público" como invariante imposta, não só default (anti-fachada/LGPD).
- **Testes de conteúdo (CA-01) e de assinatura (RN-005) renderizando o Blade real** — provam o conteúdo do documento (que o binário `%PDF` não permite assertar) e o gancho da assinatura ponta a ponta (PNG real → data URI).

## Authentication Gates
Nenhum — `composer require` rodou sem credencial; serviço route-free sem CLI/credencial externa.

## Issues Encountered (cross-plan)
- **`vendor/bin/pint --dirty`** formatou também `app/Http/Controllers/Gestao/ProcessoController.php` (arquivo UNTRACKED da wave paralela 10-14): foi apenas `no_unused_imports` (seguro) e **NÃO foi incluído no meu commit** (staging individual dos meus 3 arquivos; nunca `git add -A`).
- **Suíte completa 1081/1083:** as 2 falhas são do `ProcessoConsultaTest` (10-14) por páginas Inertia `gestao/processos/index|show` ainda inexistentes (frontend em andamento do 10-14) — **não é regressão deste plano** (meus 8 testes verdes; nada fora do meu escopo foi tocado).
- `STATE.md` NÃO alterado (consolidação a cargo do orquestrador — instrução do user query; evita clobber entre executores concorrentes).

## Verification (evidência fresca)
- **Task 1:** `composer show barryvdh/laravel-dompdf` → v3.1.2; `php artisan package:discover` sem erro; grep `barryvdh/laravel-dompdf` em composer.json (require) + composer.lock.
- **RED Task 2:** `--filter=TvlPdfServiceTest` → 8 erros (`Target class [App\Services\Analise\TvlPdfService] does not exist`).
- **GREEN Task 2:** `php artisan test --compact --filter=TvlPdfServiceTest` → **8/8 (34 asserções)**.
- **`vendor/bin/pint --dirty --format agent`** → passed (meus arquivos limpos).
- **Suíte completa SQLite:** `--exclude-group=postgis` → **1081/1083** (as 2 falhas são do `ProcessoConsultaTest`/10-14, acima — zero regressão deste plano).
- **Greps de aceite:** `loadView('tvl.documento'` + `TvlDocument::create` + `isDeferida` + `tvl-emitido` em `TvlPdfService.php`; `tvl_product_number` em `documento.blade.php`.

## Next Phase Readiness
- **10-15** (endpoints emitir/download): injeta `TvlPdfService::generate($decision, $ator)` num controller gated por `emitir-tvl` (RN-001); o download usa `URL::temporarySignedRoute` com TTL `analise.tvl.download.ttl_minutos` servindo `Storage::disk($doc->disk)->download($doc->path)` (NUNCA arquivo público; NUNCA ao cidadão — CA-02). `montarDados` disponível para pré-visualização. Traduz `DomainException` (não deferida) em 422.
- **10-18** (smoke do TVL): exercita emitir após deferimento (expresso e análise humana) ponta a ponta, assert `%PDF` + `tvl_documents` + auditoria.
- **Gancho registrado:** assinatura digital gov.br/ICP-Brasil (RN-005) — pendência SEDUR; default atual é imagem do diretor (vazia até a SEDUR entregar a firma) ou nenhuma.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
