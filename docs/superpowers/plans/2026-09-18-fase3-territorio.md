# Fase 3 — Território: parâmetro de zona, catálogo GeoServer, gestão de camadas e cadastro de zonas — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Subagentes SEMPRE com model `kimi-k3-high` (decisão do usuário).

**Goal:** fechar as quatro lacunas de território da auditoria: atributo de nome da zona parametrizável (3.4), catálogo de camadas GeoServer em banco (3.2), gestão de camadas geo pela interface (3.1) e cadastro de zonas com validação na publicação do Quadro 10 (3.3).

**Architecture:** tudo segue os padrões consolidados — parâmetros HU-014 (`ParameterSeeder` + `Settings::get` com fallback em `config/sile.php`), CRUD server-driven espelhando os controllers recentes (`PropertyTypeController`), versionamento de camadas pelo `GeoLayerService`/`GeoJsonLayerImporter` já existentes (a UI é um wrapper do que o `geo:importar` faz), e validação de zona na borda de publicação do rascunho LOUOS.

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12, Inertia v3 + React 19, PostGIS.

**Origem:** Fase 3 do backlog `docs/superpowers/plans/2026-09-17-parametrizacao-matrizes-auditoria.md` (auditoria 2026-09-17, itens 3.1-3.4).

## Global Constraints

- TDD estrito: teste falhando antes, RED confirmado pelo motivo certo.
- `vendor/bin/pint --format agent <arquivos tocados>` — NUNCA `--dirty`.
- `git add` SOMENTE dos arquivos nomeados; nunca `git add -A`/`.`; `git status --porcelain` antes de commitar.
- Commits em pt-BR, conventional commits, sem ponto final.
- Permissões/parâmetros aditivos; contagens: ler o valor commitado em HEAD (`git show HEAD:<arquivo>`) e incrementar sobre ele.
- **Ambiente PostGIS REPARADO (2026-09-18):** o grupo `@group postgis` roda verde nesta máquina (container `sile-pgsql` na porta 5444, `DB_TEST_PORT=5444` no .env). Testes espaciais reais são obrigatórios onde a feature toca geometria.
- Falhas pré-existentes NÃO são da feature: `Tests\Feature\Analise\AnaliseSmokeTest::test_degradacao_sem_motor_o_humano_decide_em_modo_manual` e `Tests\Feature\Seeders\ExpressoSeedPostgisTest::test_seed_carrega_zona_ficticia_e_defere_exemplo_navegavel` (causada pelo WIP de LOUOS do usuário — Quadro 10 oficial sem a zona fictícia ZCN-1; NÃO corrigir, é do dono do WIP).
- Testes usam RefreshDatabase; NUNCA `php artisan migrate` (pgsql default aponta para dev remoto offline).
- `public/build` NÃO é commitado (outra sessão gerencia); `npm run build` é só verificação.

---

### Task 1: Parâmetro `geo.zona.atributos_nome` (item 3.4)

**Files:**
- Modify: `database/seeders/ParameterSeeder.php` (grupo 'geo', após a entrada existente ~linha 235)
- Modify: `config/sile.php` (seção `geo` — criar se não existir)
- Modify: `app/Services/Louos/LouosEnquadramentoService.php` (`zonaNome()`, linhas 588-604)
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php` e `tests/Feature/Seeders/DatabaseSeederTest.php` (contagem +1)
- Test: `tests/Unit/Louos/ZonaNomeAtributosTest.php` (ou o arquivo de teste mais próximo do enquadramento — verificar onde zonaNome já é exercitado)

**Interfaces:**
- Consumes: `Settings::get('geo.zona.atributos_nome', <default>)` — json no banco, fallback em config.
- Produces: parâmetro `geo.zona.atributos_nome` (type json, default `["ZONA","zona","SIGLA_ZONA","SUBZONA"]`).

- [ ] **Step 1: Teste que falha** — configurar uma lista customizada (ex.: `config(['sile.geo.zona.atributos_nome' => ['NM_ZONA']])`) e provar que `zonaNome` passa a ler `NM_ZONA` das propriedades da feição (hoje lê só as 4 fixas). O método é private — exercitar via o caminho público que o usa (verificar nos testes existentes de enquadramento como a zona entra; se já houver teste cobrindo `zonaNome` indiretamente, seguir o mesmo caminho).
- [ ] **Step 2: RED** — `php artisan test --compact --filter=ZonaNomeAtributos` → FAIL (atributo customizado ignorado).
- [ ] **Step 3: Implementar** — em `zonaNome()`, trocar o array fixo por:

```php
        /** @var list<string> $atributos */
        $atributos = Settings::get('geo.zona.atributos_nome', ['ZONA', 'zona', 'SIGLA_ZONA', 'SUBZONA']);

        foreach ($atributos as $chave) {
```

ParameterSeeder (espelhar a entrada json de `risco.mapa_encaminhamento`):

```php
            'geo.zona.atributos_nome' => [
                'group' => 'geo',
                'type' => 'json',
                'default_value' => '["ZONA","zona","SIGLA_ZONA","SUBZONA"]',
                'validation_rules' => ['required', 'json', 'json_string_list'],
                'description' => 'Atributos da feição da camada de zona candidatos a nome/código da zona, em ordem de precedência (a confirmar com a base oficial SEDUR)',
            ],
```

`config/sile.php` — seção `geo` (ou onde fizer mais sentido junto às chaves geo existentes):

```php
    'geo' => [
        // Atributos candidatos ao nome da zona na feição — fallback do
        // parâmetro geo.zona.atributos_nome (HU-014).
        'zona' => ['atributos_nome' => ['ZONA', 'zona', 'SIGLA_ZONA', 'SUBZONA']],
    ],
```

- [ ] **Step 4: GREEN + contagens + suíte** — teste novo PASS; contagens de parâmetros +1 (ler o commitado antes); `php artisan test --compact` → só as 2 pré-existentes falhando.
- [ ] **Step 5: Commit**

```bash
git add database/seeders/ParameterSeeder.php config/sile.php app/Services/Louos/LouosEnquadramentoService.php tests/
git commit -m "feat: parametriza os atributos de nome da zona na feição"
```

---

### Task 2: Catálogo `geoserver_layers` (item 3.2)

**Files:**
- Create: `database/migrations/2026_09_18_100000_create_geoserver_layers_table.php`
- Create: `app/Models/GeoServerLayer.php`
- Create: `database/factories/GeoServerLayerFactory.php`
- Create: `database/seeders/GeoServerLayerSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (registrar o seeder)
- Modify: `app/Services/Geo/GeoServerWfsZonaClient.php` (`typeNames()`, linhas 95-106)
- Modify: `app/Support/PermissionCatalog.php` + `database/seeders/RolesAndPermissionsSeeder.php` (permissão nova `manter-territorio`, grupo 'Território e LOUOS' se existir, senão o grupo de `consultar-territorio`)
- Create: `app/Http/Controllers/Gestao/GeoServerLayerController.php` + FormRequests
- Modify: `routes/gestao.php`
- Create: `resources/js/pages/gestao/territorio/geoserver.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx` (após "Consulta territorial")
- Test: `tests/Feature/Geo/GeoServerLayerCrudTest.php` + ajuste em `tests/Unit/Geo/GeoServerWfsZonaClientTest.php`
- Modify: `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` + `tests/Feature/Roles/ManageRolesTest.php` + `tests/Feature/Seeders/DatabaseSeederTest.php` (contagem de permissões +1)

**Interfaces:**
- Produces: tabela `geoserver_layers` (id, workspace, type_name, label nullable, ativo bool, ordem int, timestamps; unique(workspace, type_name)); model com `scopeAtivos()` (ordenado por ordem) + HasAuditoria; rotas `gestao.territorio.geoserver.{index,store,update,ativacao.update}` sob `manter-territorio`.

**Decisões:**
- **Leitura do cliente:** banco quando alcançável (mesmo vazio — vazio = nenhuma camada, honesto); fallback para `config('sile.integrations.geoserver.type_names')` SÓ com banco inalcançável (QueryException, padrão `Settings`). Formato emitido: `"workspace:type_name"`.
- **Seed:** as 20 entradas atuais de `config/sile.php:284-296` (workspace = prefixo antes de `:`, type_name = depois), ordem sequencial. Idempotente (`updateOrCreate` por workspace+type_name).
- **CRUD completo** (create/edit/toggle, sem destroy — padrão do projeto): diferente dos gatilhos, aqui a linha nova NÃO é fachada — o cliente consome qualquer linha ativa de verdade na próxima consulta WFS.
- O teste unitário existente `GeoServerWfsZonaClientTest` seta o config — com a leitura DB-first, ele passa a semear `GeoServerLayer` (factory) em vez de setar config. Atualizá-lo.

- [ ] **Step 1: Teste que falha** — `GeoServerLayerCrudTest` (403 sem permissão; store cria; toggle desativa; unique workspace+type_name) + ajuste do `GeoServerWfsZonaClientTest` (camadas ativas do banco dirigem as URLs WFS; inativa não entra).
- [ ] **Step 2: RED** — rodar os dois arquivos → FAIL.
- [ ] **Step 3: Implementar** — migration, model, factory, seeder, registro no DatabaseSeeder, leitura no cliente, permissão, controller (espelho de `PropertyTypeController`), requests, rotas:

```php
        // Catálogo de camadas do GeoServer SEDUR (WFS): zona nova entra por
        // cadastro, sem deploy. Leitura DB-first com fallback de config.
        Route::middleware('permission:manter-territorio')->prefix('territorio/geoserver')->name('territorio.geoserver.')->group(function () {
            Route::get('/', [GeoServerLayerController::class, 'index'])->name('index');
            Route::post('/', [GeoServerLayerController::class, 'store'])->name('store');
            Route::put('{geoServerLayer}', [GeoServerLayerController::class, 'update'])->name('update');
            Route::put('{geoServerLayer}/ativacao', [GeoServerLayerController::class, 'toggleActivation'])->name('ativacao.update');
        });
```

- [ ] **Step 4: Tela + menu** — `geoserver.tsx` (tabela workspace/type_name/label/ordem/situação + modal criar/editar + toggle), menu após "Consulta territorial" visível com `manter-territorio`.
- [ ] **Step 5: GREEN + build + suíte** — focados PASS; `npm run build` exit 0; suíte completa → só as 2 pré-existentes.
- [ ] **Step 6: Commit** — `feat: adiciona catálogo administrável de camadas do GeoServer` (staging só dos arquivos nomeados).

---

### Task 3: Gestão de camadas geográficas pela interface (item 3.1)

**Files:**
- Create: `app/Http/Controllers/Gestao\GeoLayerController.php`
- Create: `app/Http/Requests/Gestao/ImportGeoLayerRequest.php`
- Modify: `config/sile.php` (`geo.upload_max_mb` default 20 — constante técnica de upload, comentário explicando)
- Modify: `routes/gestao.php` (prefixo `territorio/camadas`, mesma permissão `manter-territorio` da Task 2)
- Create: `resources/js/pages/gestao/territorio/camadas.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx`
- Test: `tests/Feature/Geo/GeoLayerManagementTest.php` (SQLite: 403/validação/guarda de driver) + `tests/Feature/Geo/GeoLayerManagementPostgisTest.php` (import real)

**Interfaces:**
- Consumes: `GeoJsonLayerImporter::import(GeoLayerType, string $version, string $source, array $featureCollection): array{lidas, inseridas, invalidas, version, feature_count}`; `GeoLayer::vigente()`; `GeoLayerType` (5 casos com `label()`).
- Produces: rotas `gestao.territorio.camadas.{index,store}`.

**Regras:**
- **Guarda de driver honesta (anti-fachada):** o importador é PostGIS-only (`ST_*`). Em conexão não-pgsql, o store responde flash.error "A importação de camadas exige o banco PostGIS (produção/homologação)" — NUNCA fingir importação.
- Upload: `arquivo` required|file|max:{geo.upload_max_mb*1024}; JSON decodificado no controller (JsonException → flash.error amigável, sem path); `InvalidArgumentException` do importador → flash.error com a mensagem.
- Sucesso: flash com lidas/inseridas/inválidas (contagem + até 5 motivos de inválidas).
- Index: camadas agrupadas por tipo (label do enum), vigente destacada, histórico com feature_count/source/valid_from/valid_to.

- [ ] **Step 1: Testes que falham** — SQLite: 403 sem permissão; validação (sem arquivo, tipo inválido, versão vazia); guarda de driver (em SQLite, upload válido → flash.error PostGIS, NENHUMA camada criada). Postgis (`PostgisTestCase`, `#[Group('postgis')]`): upload de um FeatureCollection pequeno real → nova versão vigente criada, anterior substituída, feature_count correto, auditoria `carga-camada`; re-import da mesma versão idempotente.
- [ ] **Step 2: RED** — ambos os arquivos FAIL.
- [ ] **Step 3: Implementar** controller + request + config + rotas.
- [ ] **Step 4: Tela + menu** — `camadas.tsx` (cards por tipo com a vigente + tabela de histórico + modal de upload com tipo/versão/origem/arquivo), menu "Camadas geográficas" após "Consulta territorial" com `manter-territorio`.
- [ ] **Step 5: GREEN + build + suíte** — focados PASS (incluindo `--group=postgis` dos arquivos novos); `npm run build` exit 0; suíte completa → só as 2 pré-existentes.
- [ ] **Step 6: Commit** — `feat: adiciona importação de camadas geográficas pela gestão` (staging só dos arquivos nomeados).

---

### Task 4: Cadastro `zonas` + validação na publicação do Quadro 10 (item 3.3)

**Files:**
- Create: `database/migrations/2026_09_18_100001_create_zonas_table.php`
- Create: `app/Models/Zona.php` + `database/factories/ZonaFactory.php`
- Create: `database/seeders/ZonaSeeder.php` + registro no `DatabaseSeeder.php` (APÓS os seeders LOUOS)
- Create: `app/Http/Controllers/Gestao/ZonaController.php` + FormRequests
- Modify: `app/Services/Louos/LouosDraftService.php` (`publicar()`, linha 315 — validação de zonas antes de publicar Quadro 10)
- Modify: `routes/gestao.php` (prefixo `louos/zonas`, permissão `manter-louos` — reuso: zona é artefato da LOUOS, mesmo mantenedor dos quadros)
- Create: `resources/js/pages/gestao/louos/zonas.tsx`
- Modify: `resources/js/layouts/gestao-layout.tsx` (após "Quadros LOUOS")
- Test: `tests/Feature/Louos/ZonaCrudTest.php` + `tests/Feature/Louos/LouosDraftZonaValidationTest.php`

**Interfaces:**
- Produces: tabela `zonas` (id, codigo unique, nome, macrozona nullable, ativo bool, timestamps); model com `scopeAtivas()` + HasAuditoria; rotas `gestao.louos.zonas.{index,store,update,ativacao.update}`.

**Decisões:**
- **Fonte da zona é a LOUOS** (a lei define as zonas; a camada geo é a representação espacial). `ZonaSeeder`: popula a partir dos `zona` distintos da versão VIGENTE do Quadro 10 (sem depender de arquivo); idempotente; vazio honesto se não houver quadro vigente. Em dev, o conteúdo de dev do Quadro 10 (incl. ZCN-1) entra igual.
- **Validação na borda de publicação (não no seeder):** `LouosDraftService::publicar()` — ao publicar rascunho do domínio `LouosQuadro10`, rejeita com `DomainException` listando as zonas do rascunho ausentes de `zonas` (ativas). Seeders publicam por `RuleVersionService` direto (fora desse caminho) — sem problema de bootstrap. Erro vira flash.error no controller do rascunho (verificar como `LouosDraftController` trata exceções de publicação hoje e seguir o padrão).
- Zona desativada NÃO é removida de quadros vigentes (histórico); só bloqueia publicação nova.

- [ ] **Step 1: Testes que falham** — CRUD (403, store, update, toggle, codigo único/imutável); validação: rascunho Quadro 10 com zona inexistente → publicar rejeitado com a zona listada na mensagem; com zona cadastrada → publica.
- [ ] **Step 2: RED** → FAIL.
- [ ] **Step 3: Implementar** — migration, model, factory, seeder, validação no `publicar`, controller/requests/rotas.
- [ ] **Step 4: Tela + menu** — `zonas.tsx` (padrão dos CRUDs anteriores: codigo/nome/macrozona/situação), menu "Zonas" após "Quadros LOUOS" com `manter-louos`.
- [ ] **Step 5: GREEN + build + suíte** — focados PASS; suíte completa → só as 2 pré-existentes. ATENÇÃO: a suíte LOUOS é grande (`php artisan test --compact tests/Feature/Louos/`) — rodar inteira.
- [ ] **Step 6: Commit** — `feat: adiciona cadastro de zonas com validação na publicação do Quadro 10` (staging só dos arquivos nomeados).

---

## Self-Review

- **Cobertura da Fase 3 do backlog:** 3.4 → Task 1; 3.2 → Task 2; 3.1 → Task 3; 3.3 → Task 4. Dependência 3.3←3.1 do backlog foi reavaliada: a fonte da zona é a LOUOS (não a camada geo), então Task 4 não depende da Task 3 — fica só ordenada depois por conveniência de menu.
- **Permissões:** +1 total (`manter-territorio`, Tasks 2-3); Task 4 reuso de `manter-louos` (decisão registrada).
- **Anti-fachada:** guarda de driver na Task 3 (nunca simular importação em não-pgsql); Task 2 com CRUD completo porque a linha nova é consumida de verdade (diferente dos gatilhos enum-bound).
- **Consistência:** nomes de rota `gestao.territorio.geoserver.*`, `gestao.territorio.camadas.*`, `gestao.louos.zonas.*` iguais em controllers, testes e TSX.
