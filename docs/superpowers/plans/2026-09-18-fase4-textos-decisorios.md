# Fase 4 — Textos decisórios: catálogo `decision_texts` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Subagentes SEMPRE com model `kimi-k3-high` (decisão do usuário).

**Goal:** tirar das constantes PHP os textos que vão ao TVL, ao parecer e à ficha do cidadão (~30 textos em 5 services) e movê-los para um catálogo administrável por chave estável com templates de placeholders — sem mudar UMA vírgula do que é emitido hoje.

**Architecture:** catálogo `decision_texts` (key única, template, description, sem delete/toggle — a chave é ligada ao motor) lido por `DecisionTextCatalog` com cache invalidado na escrita (padrão `PropertyType::CACHE_KEY`) e fallback aos textos de fábrica SÓ com banco inalcançável (QueryException, padrão `Settings`/`TipoImovelCatalog`). Os defaults de fábrica ficam no próprio catálogo (`defaults()`) e são a fonte do seeder — fonte única, sem duplicação. Templates com placeholders `:nome` interpolados por `strtr`; a formatação de valores (área, CNAE) permanece em código (é apresentação, não texto de negócio).

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12, Inertia v3 + React 19.

**Origem:** Fase 4 do backlog `docs/superpowers/plans/2026-09-17-parametrizacao-matrizes-auditoria.md` (auditoria 2026-09-17, itens 4.1/4.2/4.3).

## Global Constraints

- TDD estrito: teste falhando antes, RED confirmado pelo motivo certo.
- **INVARIANTE CENTRAL: byte-identidade.** Para os mesmos inputs, o texto emitido DEPOIS da migração é idêntico ao de antes. Cada task de migração prova isso com testes de string exata. Os testes existentes que assertam trechos de motivo NÃO podem ser afrouxados — se um falhar, a migração está errada.
- `vendor/bin/pint --format agent <arquivos tocados>` — NUNCA `--dirty`.
- `git add` SOMENTE dos arquivos nomeados; nunca `git add -A`/`.`; `git status --porcelain` antes de commitar.
- Commits em pt-BR, conventional commits, sem ponto final.
- Descriptions < 255 chars (varchar(255) no Postgres real — lição da Fase 0).
- Falhas pré-existentes NÃO são da feature: `AnaliseSmokeTest::test_degradacao_sem_motor_o_humano_decide_em_modo_manual` e `ExpressoSeedPostgisTest::test_seed_carrega_zona_ficticia_e_defere_exemplo_navegavel` (WIP LOUOS do usuário).
- Testes usam RefreshDatabase; NUNCA `php artisan migrate`.
- `public/build` NÃO é commitado; `npm run build` é só verificação.
- O usuário trabalha em paralelo (LOUOS/indeferimento). Ler o estado ATUAL de qualquer arquivo antes de editar; nunca reverter mudanças dele.

## Inventário canônico (mapeado na auditoria + verificado no código em 2026-09-18)

| Chave | Origem hoje | Placeholders |
|---|---|---|
| `base_legal.louos` | `LouosEnquadramentoService::FUNDAMENTO_LOUOS` = 'Lei nº 9.148/2016 (LOUOS)' | — |
| `base_legal.risco_municipal` | `RiscoClassificationService` (buildFundamentacao, ~296) = 'Decreto Municipal nº 32.636/2020' | — |
| `louos.motivo.sem_enquadramento_consolidado` | const linha 40 | — |
| `louos.motivo.proibido` | const linha 42 (também embutido via rtrim em motivoNaoPermitido) | — |
| `louos.motivo.permitido` | const linha 44 (idem motivoPermitido) | — |
| `louos.motivo.permitido_com_condicoes` | const linha 46 | — |
| `louos.motivo.zona_pendente` | const linha 48 | — |
| `louos.motivo.sem_enquadramento` | const linha 50 | — |
| `louos.motivo.quadro10_sem_versao` | const linha 52 | — |
| `louos.motivo.zona_sem_regra` | const linha 54 | — |
| `louos.motivo.via_pendente` | const linha 56 | — |
| `louos.motivo.via_sem_atributo` | const linha 58 | — |
| `louos.motivo.via_sem_versao` | const linha 60 | — |
| `louos.motivo.via_sem_regra` | const linha 62 | — |
| `louos.template.quadro7` | `motivoQuadro7()` linha 617 | `:cnae` `:area` `:grupo` `:subgrupo` `:faixa` |
| `louos.template.quadro10` | `motivoQuadro10()` linha 639 | `:grupo` `:permissao` `:zona` |
| `louos.template.permitido` | `motivoPermitido()` linha 657 | `:cnae` `:area` `:grupo` `:zona` `:condicao` |
| `louos.template.nao_permitido` | `motivoNaoPermitido()` linha 679 | idem |
| `louos.template.quadro_via` | `motivoQuadroVia()` linha 700 | `:grupo` `:classe_via` |
| `consulta.aviso.zona_pendente` | `ConsultaViabilidadeService` linha 41 | — |
| `consulta.aviso.cnae_sem_local` | linha 43 | — |
| `consulta.aviso.inscricao_indisponivel` | linha 45 | — |
| `analise.pre_analise.intro` | `PreAnaliseService::parecerRascunho` linha 287 | `:resultado` |
| `justificativa.conclusao.permitido` | `JustificativaFundamentadaComposer::conclusao` linha 340 | `:zona` |
| `justificativa.conclusao.permitido_com_condicoes` | linha 341 | `:zona` |
| `justificativa.conclusao.nao_permitido` | linha 342 | `:zona` |
| `justificativa.conclusao.padrao` | linha 343 | — |
| `explicacao.titulo.*` (7) | `DecisionExplanationService` linhas 26-38 | — |
| `explicacao.motivo.*` (7) | linhas 40-52 | — |

**Notas de composição (críticas para a byte-identidade):**
- `motivoPermitido`/`motivoNaoPermitido` embutem a constante de condição via `rtrim($condicao, '.')` e o template pai adiciona o ponto final. No catálogo, as entradas `louos.motivo.permitido*`/`proibido` guardam o texto COM ponto final (forma canônica standalone); o motor aplica `rtrim(..., '.')` ao interpolar `:condicao` — exatamente como hoje. MAPEAR todos os usos de cada constante antes de migrar (grep) — se alguma é usada standalone, a forma com ponto é a dela.
- Os placeholders `:subgrupo` e `:faixa` do template Quadro 7 carregam a formatação (`" (nR1-01)"` ou `""`; `" (faixa X a Y m²)"` ou `""`) — a lógica de formatação (`rotuloFaixaQuadro7`, `formatarArea`, `formatarCnae`) PERMANECE em código.
- `JustificativaFundamentadaComposer::conclusao` usa `match` com `default` — o `default` vira `justificativa.conclusao.padrao`.

---

### Task 1: Fundação — tabela, model, catálogo com cache e seeder

**Files:**
- Create: `database/migrations/2026_09_18_163000_create_decision_texts_table.php`
- Create: `app/Models/DecisionText.php`
- Create: `app/Services/Decisao/DecisionTextCatalog.php`
- Create: `database/seeders/DecisionTextSeeder.php` + registro no `DatabaseSeeder.php` E no `SEEDERS_HOMOLOGACAO` do `DemonstracaoClienteSeeder.php` (o deploy roda essa lista — lição do C1 da Fase 3)
- Test: `tests/Feature/Decisao/DecisionTextCatalogTest.php` + `tests/Feature/Seeders/DecisionTextSeederTest.php`

**Interfaces:**
- Produces:
  - `decision_texts` (id, key string unique, template text, description string, timestamps) — SEM active/delete: a chave é ligada ao motor.
  - `DecisionTextCatalog::get(string $key): string` — banco → cache → defaults; chave desconhecida lança `InvalidArgumentException` (chave ausente é bug, não estado de runtime).
  - `DecisionTextCatalog::render(string $key, array<string, string> $placeholders = []): string` — `strtr($template, $placeholders)`; placeholder não fornecido permanece literal (visível, honesto).
  - `DecisionTextCatalog::defaults(): array<string, string>` — os textos de fábrica (conteúdo ATUAL do código, verbatim).
  - `DecisionText::CACHE_KEY = 'sile.decision_texts.catalogo'`; escrita invalida (booted saved/deleted, padrão PropertyType).
- Consumes: nada além do padrão.

**Regras:**
- O seeder popula TODAS as chaves do inventário (inclusive as dos services migrados nas Tasks 2-4) a partir de `DecisionTextCatalog::defaults()` — fonte única: o seeder lê `defaults()`, não duplica strings.
- `defaults()` contém os textos EXATOS de hoje (extrair do código, verbatim — incluindo pontuação e espaços).
- Fallback: `get()` lê o banco via cache; `QueryException|\Exception` → defaults (só build/CI/sem-banco).

- [ ] **Step 1: Testes que falham** — (a) `get()` de chave seedada devolve o texto; (b) `render()` interpola placeholders e deixa não-fornecidos literais; (c) chave desconhecida lança; (d) escrita no model invalida o cache; (e) **paridade seeder↔código**: para uma amostra representativa (uma por service: `base_legal.louos`, `louos.motivo.proibido`, `consulta.aviso.zona_pendente`, `justificativa.conclusao.permitido`, `explicacao.titulo.risco`), o valor seedado é byte-idêntico à constante atual do código (ler a constante via reflexão ou referência direta — o teste NASCE para provar a paridade e será REMOVIDO/convertido ao fim da migração de cada service... não: manter como golden permanente dos defaults).
- [ ] **Step 2: RED** → FAIL (classe/tabela inexistentes).
- [ ] **Step 3: Implementar** — migration, model, catálogo, seeder, registros.
- [ ] **Step 4: GREEN + suíte** — focados PASS; suíte completa → só as 2 pré-existentes.
- [ ] **Step 5: Commit** — `feat: cria o catálogo de textos decisórios` (staging só dos arquivos nomeados).

---

### Task 2: Migração do `LouosEnquadramentoService`

**Files:**
- Modify: `app/Services/Louos/LouosEnquadramentoService.php` (12 constantes + 5 métodos de template + FUNDAMENTO_LOUOS — mapear TODOS os usos com grep antes)
- Test: `tests/Feature/Louos/LouosTextosByteIdenticosTest.php` (novo) + a suíte LOUOS existente como rede

**Interfaces:**
- Consumes: `DecisionTextCatalog` da Task 1 (injetado no construtor do service — verificar como o service é resolvido; se for `new` manual em algum lugar, ajustar para o container).

**Regras:**
- Byte-identidade: escrever PRIMEIRO o teste golden com as strings EXATAS de hoje para uma matriz de cenários (permitido, permitido_com_condicoes, nao_permitido, sem enquadramento, zona pendente, zona sem regra, via pendente/sem atributo/sem versão/sem regra, quadro10 sem versão) — extrair as strings do código atual, rodar contra o código NÃO migrado para confirmar que o golden está correto (verde ANTES da migração), SÓ ENTÃO migrar e manter verde.
- As constantes viram chamadas `$this->textos->get('louos.motivo.proibido')` etc.; os templates viram `render('louos.template.quadro7', [...])` com os valores pré-formatados pelos helpers existentes (que permanecem).
- `FUNDAMENTO_LOUOS` → `get('base_legal.louos')`.

- [ ] **Step 1: Golden test verde no código atual** — escrever `LouosTextosByteIdenticosTest` com as strings exatas; rodar: PASS sem nenhuma mudança de produção (prova que o golden está correto).
- [ ] **Step 2: Migrar** o service para o catálogo.
- [ ] **Step 3: GREEN** — o golden segue PASS + `php artisan test --compact tests/Feature/Louos/ tests/Unit/Louos/` inteiro PASS.
- [ ] **Step 4: Suíte completa** → só as 2 pré-existentes.
- [ ] **Step 5: Commit** — `refactor: lê os textos do enquadramento LOUOS do catálogo` (staging só dos arquivos nomeados).

---

### Task 3: Migração dos services de consulta, risco, pré-análise e justificativa

**Files:**
- Modify: `app/Services/Viabilidade/ConsultaViabilidadeService.php` (3 avisos)
- Modify: `app/Services/Risco/RiscoClassificationService.php` (citação do Decreto 32.636/2020 em buildFundamentacao — mapear o uso exato)
- Modify: `app/Services/Analise/PreAnaliseService.php` (intro do parecerRascunho, linha 287 — template com `:resultado`)
- Modify: `app/Services/Analise/JustificativaFundamentadaComposer.php` (4 conclusões + fallback da fundamentação → `base_legal.louos`)
- Test: `tests/Feature/Decisao/TextosServicosByteIdenticosTest.php` (novo)

**Regras:** mesmas da Task 2 — golden de string exata verde ANTES de migrar, migrar, manter verde. O `match` da conclusão vira leitura de catálogo por chave derivada do resultado (`justificativa.conclusao.{permitido|permitido_com_condicoes|nao_permitido}` + `padrao` no default). A intro da pré-análise embute `ResultadoViabilidade::...->label()` — o label continua vindo do enum (rótulo é Fase 6, fora do escopo); o template leva `:resultado`.

- [ ] **Steps 1-5** — mesmo ciclo da Task 2 (golden verde antes → migrar → verde → suíte → commit `refactor: lê os textos de consulta, risco e justificativa do catálogo`).

---

### Task 4: Migração do `DecisionExplanationService`

**Files:**
- Modify: `app/Services/Auditoria/DecisionExplanationService.php` (13 constantes: 7 títulos + 6 motivos)
- Test: `tests/Feature/Auditoria/DecisionExplanationTest.php` (existente — verificar se já asserta os textos; se não, adicionar golden exato antes de migrar)

**Regras:** mesmo ciclo. Os títulos/motivos viram `explicacao.titulo.*` / `explicacao.motivo.*`.

- [ ] **Steps 1-5** — golden verde antes → migrar → verde → suíte → commit `refactor: lê os textos da explicação da decisão do catálogo`.

---

### Task 5: Tela de manutenção dos textos

**Files:**
- Create: `app/Http/Controllers/Gestao/DecisionTextController.php` + `UpdateDecisionTextRequest`
- Modify: `routes/gestao.php` (prefixo `textos-decisao`, permissão `manter-parametros` — reuso, mesmo mantenedor dos textos-padrão)
- Create: `resources/js/pages/gestao/textos-decisao/index.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx` (grupo "Administração", após "Textos-padrão" se existir)
- Test: `tests/Feature/Decisao/DecisionTextCrudTest.php`

**Regras:**
- **SEM create/delete/toggle** — as chaves são ligadas ao motor (criar chave sem call site é fachada; apagar quebra a emissão). Só listar + editar `template`/`description`. Mesma disciplina dos gatilhos (Fase 2).
- A listagem agrupa por prefixo da chave (louos.*, consulta.*, justificativa.*, explicacao.*, base_legal.*) e mostra a `description` com os placeholders disponíveis — o admin precisa saber o contrato antes de editar.
- O modal de edição avisa: "Este texto é emitido em documentos oficiais (TVL/parecer). Placeholders `:nome` são substituídos pelo motor; removê-los remove a informação do documento."
- Auditoria via HasAuditoria no model (RN-002) — toda edição registrada.

- [ ] **Step 1: Testes que falham** — 403 sem permissão; update do template persiste e o motor passa a emitir o novo texto (prova de ponta a ponta: editar `louos.motivo.proibido` → o enquadramento emite o texto novo); update de chave inexistente 404; ausência de rotas create/delete (405).
- [ ] **Steps 2-6** — RED → implementar → GREEN → build → suíte → commit `feat: adiciona a manutenção dos textos decisórios na gestão`.

---

## Self-Review

- **Cobertura da Fase 4 do backlog:** 4.1 (constantes dos motores → catálogo tipado) → Tasks 1-4; 4.2 (match da justificativa) → Task 3; 4.3 (bases legais) → entradas `base_legal.*` no mesmo catálogo (DESVIO da auditoria, que propunha cadastro separado: mesma forma (chave→texto), mesma UI, menos superfície — registrado).
- **Anti-fachada:** sem create/delete (chaves motor-bound); fallback de fábrica só sem banco; placeholder ausente fica literal (visível), nunca inventado.
- **Risco jurídico:** a byte-identidade é provada por golden tests escritos ANTES da migração e verdes DEPOIS — nenhum documento oficial muda de texto sem edição administrativa explícita e auditada.
- **Fora de escopo (registrado):** a normalização de níveis de risco (`baixo_b`→'Médio', `baixo_c` fantasma) é vocabulário — Fase 6, pendente de resposta SEDUR; os `label()` dos enums permanecem.
