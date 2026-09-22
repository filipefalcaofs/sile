# Fase 2 — CRUDs de dados oficiais sem interface — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Subagentes SEMPRE com model `kimi-k3-high` (decisão do usuário).

**Goal:** dar interface administrativa aos três dados oficiais que hoje só mudam por seeder/artisan: gatilhos semi-expresso (`risk_triggers`), Anexos A/B do Decreto 35.062/2021 (`virtual_office_activity_cnaes`) e termos legais LGPD (`legal_terms`).

**Architecture:** CRUDs server-driven espelhando `ViabilityServiceTypeController` (whitelist de sort/per_page, FormRequests, toggle sem exclusão, `HasAuditoria`). Os Anexos A/B reusam a infraestrutura versionada existente (`RuleVersionService::openDraft/publish` + `EscritorioVirtualCnaeImportService`) com importação no RASCUNHO — a vigente só muda na publicação, que passa a exigir quatro olhos (domínio vira sensível).

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12, Inertia v3 + React 19.

**Origem:** Fase 2 do backlog `docs/superpowers/plans/2026-09-17-parametrizacao-matrizes-auditoria.md` (auditoria 2026-09-17, itens B1/B2/B4).

## Global Constraints

- TDD estrito: teste falhando antes, RED confirmado pelo motivo certo.
- `vendor/bin/pint --format agent <arquivos tocados>` — NUNCA `--dirty` (a árvore pode ter WIP de outra sessão).
- `git add` SOMENTE dos arquivos nomeados; nunca `git add -A`/`.`. Antes de commitar, `git status --porcelain` nos staged.
- Mensagens de commit em pt-BR, conventional commits, sem ponto final.
- Permissões aditivas (`firstOrCreate`/`givePermissionTo`, nunca `sync`); contagens de permissões/parâmetros: ler o valor commitado em HEAD antes de editar (`git show HEAD:<arquivo>`), incremento sobre o commitado.
- Falhas pré-existentes NÃO são da feature: 30 testes PostGIS (banco espacial 127.0.0.1:5433 auth failure) + `Tests\Feature\Analise\AnaliseSmokeTest::test_degradacao_sem_motor_o_humano_decide_em_modo_manual`.
- Testes usam o banco de teste local via RefreshDatabase; NUNCA `php artisan migrate` (pgsql default aponta para dev remoto offline).

---

### Task 1: CRUD dos gatilhos semi-expresso (`risk_triggers`)

**Files:**
- Create: `app/Http/Controllers/Gestao/RiskTriggerController.php`
- Create: `app/Http/Requests/Gestao/UpdateRiskTriggerRequest.php`
- Modify: `app/Support/PermissionCatalog.php` (nova permissão, grupo 'CNAEs')
- Modify: `database/seeders/RolesAndPermissionsSeeder.php` (aditivo: array + admin/gestor)
- Modify: `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` (contagem +1, listas por papel)
- Modify: `routes/gestao.php` (após o bloco `tipos-imovel`)
- Create: `resources/js/pages/gestao/gatilhos-risco/index.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx` (grupo "Regras do licenciamento", após "Tipos de imóvel")
- Test: `tests/Feature/Risco/RiskTriggerCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\RiskTrigger` (fillable codigo/titulo/motivo/ativo/categoria; `codigo` cast `TipoGatilho`; `scopeAtivos()`; HasAuditoria); `App\Enums\TipoGatilho` (3 casos, `label()`).
- Produces: rotas `gestao.gatilhos-risco.{index,update,ativacao.update}` sob `permission:manter-gatilhos-risco`.

**Escopo deliberado (anti-fachada):** SEM criar/excluir gatilho. O `codigo` é enum-bound — cada caso tem comportamento no motor (`RiscoClassificationService::applyGatilhos`); uma linha criada pela UI sem caso no enum não faria nada (fachada). O CRUD gerencia `titulo`, `motivo` e `ativo` dos 3 códigos conhecidos. O docblock do enum já prevê "novos gatilhos entram como dado" — quando a SEDUR pedir um gatilho novo, ele nasce no enum + motor (deploy) e passa a ser administrável aqui.

- [ ] **Step 1: Escrever o teste que falha**

```bash
php artisan make:test Risco/RiskTriggerCrudTest --no-interaction
```

Espelhar o setup de `tests/Feature/Risco/PropertyTypeCrudTest.php` (roles via `administrador()`/`analista()` + `withAcceptedLgpdTerm()` — ler o arquivo e copiar o plumbing). Casos mínimos:

```php
    public function test_lista_exige_permissao(): void
    {
        // analista (tem acessar-gestao, NÃO tem manter-gatilhos-risco) → 403
    }

    public function test_edita_titulo_e_motivo_com_auditoria(): void
    {
        $this->seed(\Database\Seeders\RiskTriggerSeeder::class);
        // admin PUT /gestao/gatilhos-risco/{id} com titulo+motivo novos
        // assert: registro atualizado; activity_log tem updated para o model
    }

    public function test_toggle_desativa_gatilho_e_o_motor_para_de_aplicar(): void
    {
        $this->seed(\Database\Seeders\RiskTriggerSeeder::class);
        $gatilho = RiskTrigger::query()->where('codigo', TipoGatilho::ZeisEspecial->value)->firstOrFail();

        // admin PUT /gestao/gatilhos-risco/{id}/ativacao
        // assert: ativo === false; RiskTrigger::ativos() não contém mais zeis_especial
    }

    public function test_nao_existe_rota_de_criacao_ou_exclusao(): void
    {
        // POST /gestao/gatilhos-risco → 405; DELETE → 405 (anti-fachada: código sem motor)
    }
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Risco/RiskTriggerCrudTest.php`
Expected: FAIL — rotas inexistentes (404/405 conforme o caso; o de permissão pode dar 404 em vez de 403 — ambos provam o RED, anotar no relatório).

- [ ] **Step 3: Implementar**

Permissão em `PermissionCatalog.php` (após a entrada de risco/CNAE mais próxima, grupo 'CNAEs'):

```php
            [
                'name' => 'manter-gatilhos-risco',
                'label' => 'Manter gatilhos de risco',
                'description' => 'Edita e liga/desliga os gatilhos semi-expresso que enviam processos à análise técnica.',
                'group' => 'CNAEs',
            ],
```

`RolesAndPermissionsSeeder.php`: adicionar `'manter-gatilhos-risco'` ao `$permissions` e aos `givePermissionTo` dos papéis que hoje recebem `manter-cnaes`. Atualizar `RolesAndPermissionsSeederTest.php` (+1 e listas; ler o valor commitado em HEAD antes).

`routes/gestao.php` (após o bloco `tipos-imovel`):

```php
        // Gatilhos semi-expresso (HU-049/HU-051): dado administrável. Sem
        // create/destroy — o código é enum-bound e tem comportamento no motor;
        // desligar um gatilho muda o roteamento (ação sensível, auditada).
        Route::middleware('permission:manter-gatilhos-risco')->prefix('gatilhos-risco')->name('gatilhos-risco.')->group(function () {
            Route::get('/', [RiskTriggerController::class, 'index'])->name('index');
            Route::put('{riskTrigger}', [RiskTriggerController::class, 'update'])->name('update');
            Route::put('{riskTrigger}/ativacao', [RiskTriggerController::class, 'toggleActivation'])->name('ativacao.update');
        });
```

`RiskTriggerController`: `index` lista todos (sem paginação — 3 registros; ordenado por codigo) com `codigo` (value + label do enum), `titulo`, `motivo`, `ativo`; `update` via `UpdateRiskTriggerRequest` (`titulo` required|string|max:255, `motivo` required|string|max:1000; codigo/categoria NUNCA editáveis — ausentes do request); `toggleActivation` espelhando o de referência (nunca exclui). `Inertia::render('gestao/gatilhos-risco/index', ...)`.

`UpdateRiskTriggerRequest`: authorize true (o gate é o middleware da rota), attributes em pt-BR.

- [ ] **Step 4: Tela + menu**

`resources/js/pages/gestao/gatilhos-risco/index.tsx` espelhando `gestao/tipos-imovel/index.tsx` (mesma estrutura de Card/DataTable/Modal), mais simples: sem busca/paginação, sem criação. Colunas: código (Badge com `label()` do enum), título, motivo (truncado com title), situação. Modal de edição: titulo + motivo (textarea). Toggle com ConfirmDialog: ao DESATIVAR, texto "Processos que hoje caem neste gatilho passam a concluir automaticamente no fluxo expresso. Confirmar?"; ao ativar, texto informativo simples. Gate `auth.permissions.includes('manter-gatilhos-risco')`.

Menu em `gestao-layout.tsx`, grupo "Regras do licenciamento", após "Tipos de imóvel": `{ name: 'Gatilhos de risco', href: '/gestao/gatilhos-risco', icon: <TagIcon />, visible: auth.permissions.includes('manter-gatilhos-risco') }`.

- [ ] **Step 5: GREEN + build + suíte**

Run: `php artisan test --compact tests/Feature/Risco/RiskTriggerCrudTest.php tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` → PASS.
Run: `npm run build` → exit 0. NÃO commitar `public/build`.
Run: `php artisan test --compact` → PASS exceto as 31 pré-existentes.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Gestao/RiskTriggerController.php app/Http/Requests/Gestao/UpdateRiskTriggerRequest.php app/Support/PermissionCatalog.php database/seeders/RolesAndPermissionsSeeder.php tests/Feature/Authorization/RolesAndPermissionsSeederTest.php routes/gestao.php resources/js/pages/gestao/gatilhos-risco/index.tsx resources/js/layouts/gestao-layout.tsx tests/Feature/Risco/RiskTriggerCrudTest.php
git commit -m "feat: adiciona manutenção dos gatilhos semi-expresso na gestão"
```

---

### Task 2: Publicação versionada dos Anexos A/B (Decreto 35.062/2021)

**Files:**
- Modify: `app/Enums/RuleDomain.php` (`AtividadesEscritorioVirtual` → sensível)
- Create: `app/Services/EscritorioVirtual/EscritorioVirtualAnexosService.php`
- Create: `app/Http/Controllers/Gestao/EscritorioVirtualAnexosController.php`
- Create: `app/Http/Requests/Gestao/PublicarAnexosEscritorioVirtualRequest.php`
- Modify: `routes/gestao.php`
- Create: `resources/js/pages/gestao/escritorio-virtual/anexos.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx`
- Test: `tests/Feature/EscritorioVirtual/EscritorioVirtualAnexosTest.php`
- Modify: `tests/Unit/Rules/RuleVersionServiceTest.php` ou equivalente que trave `isSensitive()` (verificar onde isSensitive é testado)

**Interfaces:**
- Consumes: `RuleVersionService::openDraft(RuleDomain, string $version, string $source, ?int $createdBy): RuleVersion` e `::publish(RuleVersion, ?int $publishedBy): RuleVersion` (lança `FourEyesViolationException` em domínio sensível com publicador = autor); `EscritorioVirtualCnaeImportService::import(RuleVersion, string $csvPath, string $anexo): array{importados: int}`; `VirtualOfficeActivityCnae::ANEXO_A/ANEXO_B`; `RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)`.
- Produces: rotas `gestao.escritorio-virtual.anexos.{index,store,publicar}` sob `permission:manter-cnaes` (reuso deliberado — os anexos são listas de CNAE, mesmo mantenedor do risco; NÃO criar permissão nova).

**Decisões:**
- **Quatro olhos:** `AtividadesEscritorioVirtual` passa a sensível (auditoria, ponto G7 — publicar anexo bloqueia/libera constituição de sede). Ajustar o teste que trava o mapa de sensibilidade.
- **Importação no RASCUNHO:** a vigente fica intacta até a publicação — a guarda de "lista disponível" (sem versão vigente → recurso indisponível) nunca é violada no meio do caminho.
- **Versão nomeada por data** com sufixo livre: input `versao` com default `ev-anexos-{Y-m-d}`; se já existir rascunho com esse nome, o openDraft o reusa (idempotente) e a importação substitui o conteúdo do rascunho (upsert por rule_version_id+anexo+cnae_code já garante; linhas do rascunho ausentes do CSV novo são removidas — o rascunho é descartável).

- [ ] **Step 1: Escrever o teste que falha**

```bash
php artisan make:test EscritorioVirtual/EscritorioVirtualAnexosTest --no-interaction
```

Casos mínimos (usar `UploadedFile::fake()->createWithContent` ou CSVs temporários com o cabeçalho `cnae_code,cnae_description`):

```php
    public function test_upload_cria_rascunho_sem_tocar_a_vigente(): void
    {
        // seed da vigente oficial (EscritorioVirtualCnaeSeeder)
        // admin POST /gestao/escritorio-virtual/anexos com 2 CSVs pequenos
        // assert: nova RuleVersion rascunho do domínio com as linhas importadas;
        // a versão vigente e seus counts NÃO mudaram; permitidoNoAnexo() responde pela vigente
    }

    public function test_preview_mostra_diff_entre_rascunho_e_vigente(): void
    {
        // após upload: GET index expõe adicionados/removidos por anexo (cnae_code)
    }

    public function test_publicacao_exige_quatro_olhos(): void
    {
        // autor do rascunho tenta publicar → 422 com a mensagem de quatro olhos
        // outro admin publica → rascunho vira vigente, anterior vira substituída,
        // permitidoNoAnexo() passa a responder pela nova lista
    }

    public function test_dominio_escritorio_virtual_agora_e_sensivel(): void
    {
        $this->assertTrue(RuleDomain::AtividadesEscritorioVirtual->isSensitive());
    }
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/EscritorioVirtual/EscritorioVirtualAnexosTest.php`
Expected: FAIL — rotas inexistentes; `isSensitive()` false.

- [ ] **Step 3: Implementar**

`RuleDomain.php`: mover `AtividadesEscritorioVirtual` para o ramo `true` do `isSensitive()` e ajustar o docblock (anexos definem quem constitui sede — decisório).

`EscritorioVirtualAnexosService`:

```php
    /**
     * Abre (ou reusa) o rascunho nomeado e importa os dois CSVs PARA O
     * RASCUNHO — a vigente só muda na publicação. Linhas do rascunho ausentes
     * dos CSVs são removidas (o rascunho é descartável; a vigente, jamais).
     *
     * @return array{versao: RuleVersion, anexo_a: int, anexo_b: int}
     */
    public function importarParaRascunho(string $versao, string $fonte, string $csvAnexoA, string $csvAnexoB, int $autorId): array;

    /**
     * Diff rascunho × vigente por anexo.
     *
     * @return array{A: array{adicionados: list<string>, removidos: list<string>}, B: array{adicionados: list<string>, removidos: list<string>}}
     */
    public function diffRascunho(RuleVersion $rascunho): array;

    /** Publica via RuleVersionService (quatro olhos) e audita com os contadores. */
    public function publicar(RuleVersion $rascunho, int $publicadorId): RuleVersion;
```

`EscritorioVirtualAnexosController`:
- `index`: vigente (versão, publicada em/por, counts por anexo) + rascunho aberto (se houver, com diff) + histórico de versões do domínio (versão, status, valid_from/to).
- `store` (upload): `PublicarAnexosEscritorioVirtualRequest` — `versao` required|string|max:60|regex:/^[a-z0-9\-]+$/ , `fonte` required|string|max:255, `anexo_a`/`anexo_b` required|file|mimetypes:text/csv,text/plain. Salva os uploads em storage temporário, chama `importarParaRascunho`, devolve back() com o resumo.
- `publicar` (POST `{versao}/publicar`): chama `publicar` com o usuário; `FourEyesViolationException` → back com `flash.error` e a mensagem da exceção (nunca 500).

Rotas:

```php
        // Anexos A/B do Decreto 35.062/2021 (escritório virtual): publicação
        // versionada com quatro olhos — a lista vigente só muda no publish.
        Route::middleware('permission:manter-cnaes')->prefix('escritorio-virtual/anexos')->name('escritorio-virtual.anexos.')->group(function () {
            Route::get('/', [EscritorioVirtualAnexosController::class, 'index'])->name('index');
            Route::post('/', [EscritorioVirtualAnexosController::class, 'store'])->name('store');
            Route::post('{versao}/publicar', [EscritorioVirtualAnexosController::class, 'publicar'])->name('publicar');
        });
```

- [ ] **Step 4: Tela + menu**

`resources/js/pages/gestao/escritorio-virtual/anexos.tsx`: card da vigente (versão, data, autor, contagem por anexo); formulário de upload (2 inputs de arquivo + versão com default `ev-anexos-{data de hoje}` + fonte); card do rascunho com o diff (adicionados/removidos por anexo, badges) e botão "Publicar" — quando o usuário logado é o autor do rascunho, o backend rejeita (quatro olhos): exibir o flash.error; tabela de histórico. Espelhar padrões de upload/form das telas LOUOS (`gestao/louos` — ler `resources/js/pages/gestao/louos/index.tsx` para o padrão de importação CSV).

Menu em `gestao-layout.tsx`, grupo "Regras do licenciamento": `{ name: 'Anexos escritório virtual', href: '/gestao/escritorio-virtual/anexos', icon: <FileIcon />, visible: auth.permissions.includes('manter-cnaes') }`.

- [ ] **Step 5: GREEN + build + suíte**

Run: `php artisan test --compact tests/Feature/EscritorioVirtual/ tests/Unit/Rules/` → PASS.
Run: `npm run build` → exit 0. NÃO commitar `public/build`.
Run: `php artisan test --compact` → PASS exceto as 31 pré-existentes.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/RuleDomain.php app/Services/EscritorioVirtual/EscritorioVirtualAnexosService.php app/Http/Controllers/Gestao/EscritorioVirtualAnexosController.php app/Http/Requests/Gestao/PublicarAnexosEscritorioVirtualRequest.php routes/gestao.php resources/js/pages/gestao/escritorio-virtual/anexos.tsx resources/js/layouts/gestao-layout.tsx tests/
git commit -m "feat: publicação versionada dos anexos de escritório virtual na gestão"
```

---

### Task 3: CRUD de termos legais (`legal_terms`)

**Files:**
- Create: `app/Http/Controllers/Gestao/LegalTermController.php`
- Create: `app/Http/Requests/Gestao/StoreLegalTermRequest.php`
- Create: `app/Http/Requests/Gestao/UpdateLegalTermRequest.php`
- Modify: `routes/gestao.php`
- Create: `resources/js/pages/gestao/termos-legais/index.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx` (grupo "Administração", junto dos parâmetros)
- Test: `tests/Feature/Gestao/LegalTermCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\LegalTerm` (fillable type/version/title/content/published_at; `current(string $type)` = última publicada; HasAuditoria); `LegalTermAcceptance` (NÃO tocado — aceites ficam vinculados à versão aceita).
- Produces: rotas `gestao.termos-legais.{index,store,update,publicar.update,destroy}` sob `permission:manter-parametros` (reuso — termos são textos administráveis do mesmo mantenedor; NÃO criar permissão nova).

**Regras de negócio (LGPD):**
- Versão nova: `version = max(version)+1` por `type` (nunca informada pelo usuário).
- Termo publicado é IMUTÁVEL: sem update, sem destroy (o aceite do usuário referencia aquela versão — editar seria fraude de registro).
- Rascunho (published_at null): editável e excluível.
- Publicar: `published_at = now()` — `current()` passa a devolvê-lo; aceites anteriores permanecem na versão antiga (histórico preservado).

- [ ] **Step 1: Escrever o teste que falha**

```bash
php artisan make:test Gestao/LegalTermCrudTest --no-interaction
```

Casos mínimos:

```php
    public function test_lista_exige_permissao(): void; // sem manter-parametros → 403
    public function test_cria_rascunho_com_proxima_versao_do_tipo(): void; // type lgpd com v1 publicado → store cria v2 com published_at null
    public function test_publicar_torna_o_termo_vigente(): void; // LegalTerm::current('lgpd') passa a ser a nova versão
    public function test_termo_publicado_nao_pode_ser_editado_nem_excluido(): void; // PUT/DELETE em publicado → 422/403 com mensagem clara
    public function test_rascunho_pode_ser_editado_e_excluido(): void;
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Gestao/LegalTermCrudTest.php`
Expected: FAIL — rotas inexistentes.

- [ ] **Step 3: Implementar controller, requests e rotas**

Controller server-driven no padrão das tasks anteriores. `store`: cria com `version` computada e `published_at` null. `update`/`destroy`: rejeitam publicado com `back()->with('error', 'Termos publicados são imutáveis — crie uma nova versão.')` (o destroy de publicado NUNCA existe). `publicar`: `published_at = now()`, flash de sucesso. Requests com regras: `type` required|string|max:50|regex:/^[a-z0-9_]+$/ (store; imutável no update), `title` required|string|max:255, `content` required|string.

- [ ] **Step 4: Tela + menu**

`resources/js/pages/gestao/termos-legais/index.tsx`: tabela (tipo, versão, título, status [Vigente — atual / Aguardando publicação / Substituído], publicado em) + ação primária "Novo termo" (modal: tipo [select dos tipos existentes + opção de digitar novo], título, conteúdo em textarea grande) + editar/excluir só para rascunhos + "Publicar" com ConfirmDialog ("O novo texto passa a valer imediatamente para novos aceites. Confirmar publicação?"). Gate `manter-parametros`.

Menu em `gestao-layout.tsx`, grupo "Administração", junto de "Textos-padrão"/parâmetros: `{ name: 'Termos legais', href: '/gestao/termos-legais', icon: <FileIcon />, visible: auth.permissions.includes('manter-parametros') }`.

- [ ] **Step 5: GREEN + build + suíte**

Run: `php artisan test --compact tests/Feature/Gestao/LegalTermCrudTest.php` → PASS.
Run: `npm run build` → exit 0. NÃO commitar `public/build`.
Run: `php artisan test --compact` → PASS exceto as 31 pré-existentes.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Gestao/LegalTermController.php app/Http/Requests/Gestao/StoreLegalTermRequest.php app/Http/Requests/Gestao/UpdateLegalTermRequest.php routes/gestao.php resources/js/pages/gestao/termos-legais/index.tsx resources/js/layouts/gestao-layout.tsx tests/Feature/Gestao/LegalTermCrudTest.php
git commit -m "feat: adiciona gestão de termos legais versionados"
```

---

## Self-Review

- **Cobertura da Fase 2 do backlog:** 2.1 risk_triggers → Task 1 (com a decisão anti-fachada de não criar código sem motor); 2.2 Anexos A/B → Task 2 (reuso do RuleVersionService + import no rascunho + quatro olhos, guarda da vigente preservada por construção); 2.3 legal_terms → Task 3 (imutabilidade do publicado = integridade do aceite LGPD).
- **Permissões:** apenas +1 (`manter-gatilhos-risco`); Tasks 2 e 3 reusam `manter-cnaes`/`manter-parametros` — decisões registradas nas Interfaces de cada task.
- **Consistência:** nomes de rota `gestao.gatilhos-risco.*`, `gestao.escritorio-virtual.anexos.*`, `gestao.termos-legais.*` iguais em controller, testes e TSX.
- **Placeholders:** o setup de autenticação referencia os testes irmãos (fonte única, evita divergência); os asserts de motor da Task 1 ficam no nível do scope `ativos()` porque o efeito no encaminhamento já é coberto pela suíte de risco existente.
