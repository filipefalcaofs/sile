# CRUD dos Quadros da LOUOS sobre rascunho versionado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** CRUD completo (inserir/alterar/excluir linhas + importação CSV em lote) dos Quadros 7, 10 e 11A da LOUOS operando sobre rascunho versionado, publicado por quatro olhos com revisão por resumo (diff).

**Architecture:** Rascunho materializado — abrir o rascunho copia as linhas da vigente para uma `rule_versions` com status `rascunho`; o CRUD opera via Eloquent nas tabelas tipadas do rascunho; a publicação reusa `RuleVersionService::publish` (quatro olhos já enforced). Importação CSV reusando os import services existentes. Frontend: página dedicada `gestao/louos/rascunho` (a consulta `index` permanece, menos o Quadro 11).

**Tech Stack:** Laravel 13, Inertia v3, React 19, PHPUnit 12, spatie/activitylog (AuditService).

**Spec:** `docs/superpowers/specs/2026-08-18-louos-quadros-crud-rascunho-design.md` (rev. 4)

## Global Constraints

- TDD estrito: nenhum código de produção sem teste falhando antes (Red-Green-Refactor).
- Escopo de Quadros: **apenas `quadro7`, `quadro10`, `quadro11a`**. O domínio `LouosQuadro11` NÃO é removido do código nem do motor — só sai da tela de consulta e do escopo do CRUD.
- Vigente NUNCA é alterada: toda mutação exige `rule_versions.status = rascunho`.
- Quatro olhos: publicador ≠ autor (`created_by`) — enforced por `RuleVersionService::publish` em domínio sensível.
- Auditoria (`AuditService`, log `louos`) em abrir/inserir/alterar/excluir/descartar/importar; publicação já é auditada pelo `RuleVersionService`.
- UI, mensagens, commits em pt-BR; código (classes, métodos, variáveis) em inglês. Conventional commits em português.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- Testes: `php artisan test --compact tests/Feature/Louos/<arquivo>` por task; suite Louos completa ao final.
- Não criar novas dependências. Não criar arquivos de documentação além dos já existentes.

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `app/Services/Louos/LouosQuadroCopier.php` (novo) | Cópia das linhas tipadas vigente → rascunho (extraído de `LouosMaintenanceService`) |
| `app/Services/Louos/LouosMaintenanceService.php` (modifica) | Delega a cópia ao `LouosQuadroCopier` |
| `app/Services/Louos/LouosDraftService.php` (novo) | Ciclo de vida do rascunho + CRUD de linhas + diff + importação |
| `app/Support/Louos/LouosLinhaRules.php` (novo) | Regras de validação por Quadro (compartilhadas) |
| `app/Http/Requests/Gestao/PublishLouosVersionRequest.php` (modifica) | Reusa `LouosLinhaRules` |
| `app/Http/Requests/Gestao/OpenLouosDraftRequest.php` (novo) | Valida abertura do rascunho |
| `app/Http/Requests/Gestao/StoreLouosLinhaRequest.php` (novo) | Valida linha (store/update) |
| `app/Http/Requests/Gestao/ImportLouosCsvRequest.php` (novo) | Valida upload CSV |
| `app/Http/Controllers/Gestao/LouosDraftController.php` (novo) | Endpoints do rascunho |
| `app/Http/Controllers/Gestao/LouosController.php` (modifica) | Remove `quadro11` do mapa de consulta |
| `routes/gestao.php` (modifica) | Rotas do rascunho no grupo `permission:manter-louos` |
| `app/Services/Louos/LouosQuadro11ImportService.php` (modifica) | Cabeçalho sem `quadro`; `condicoes` como lista `;` |
| `database/data/louos/quadro11a-condicoes-via.csv` (novo) | Carga 11A no formato novo |
| `database/data/louos/quadro11-condicoes-via.csv` (remove) | Substituído pelo arquivo 11A |
| `database/seeders/LouosQuadro11Seeder.php` (modifica) | Semeia só o 11A |
| `resources/js/pages/gestao/louos/quadro-fields.ts` (novo) | Metadados de campos por Quadro (extraídos da index) |
| `resources/js/pages/gestao/louos/index.tsx` (modifica) | Remove Quadro 11; botão "Editar Quadro" |
| `resources/js/pages/gestao/louos/rascunho.tsx` (novo) | Página de edição do rascunho (CRUD + importar + publicar + descartar) |
| `tests/Feature/Louos/LouosDraftServiceTest.php` (novo) | Ciclo de vida + CRUD + diff + quatro olhos |
| `tests/Feature/Louos/LouosDraftEndpointsTest.php` (novo) | Rotas, permissões, validações, importação, modelo CSV |
| `tests/Feature/Louos/LouosQuadro11ImportServiceTest.php` (modifica) | Novo formato 11A |
| `tests/Feature/Louos/LouosSeedDistributionTest.php` (modifica) | 11 sem versão; 11A vigente |

---

### Task 1: Importação 11A no formato oficial (sem coluna `quadro`, condicoes como lista `;`)

**Files:**
- Modify: `app/Services/Louos/LouosQuadro11ImportService.php`
- Create: `database/data/louos/quadro11a-condicoes-via.csv`
- Delete: `database/data/louos/quadro11-condicoes-via.csv`
- Modify: `database/seeders/LouosQuadro11Seeder.php`
- Test: `tests/Feature/Louos/LouosQuadro11ImportServiceTest.php`, `tests/Feature/Louos/LouosSeedDistributionTest.php`

**Interfaces:**
- Produces: `LouosQuadro11ImportService::import(RuleVersion $version, string $csvPath): array{lidos: int, importados: int, atualizados: int, rejeitados: array<int, string>, total: int}` — sem o param `$quadro`; cabeçalho `classe_via,grupo_uso,condicoes,base_legal`; `condicoes` = texto com itens separados por `;` → gravado como JSON array de strings (ou null quando vazio).

- [ ] **Step 1: Reescrever o teste do import para o formato novo (RED)**

Substituir o conteúdo de `tests/Feature/Louos/LouosQuadro11ImportServiceTest.php` por testes que usam um CSV temporário no formato novo (sem `quadro`, `condicoes` com `;`):

```php
$csv = "classe_via,grupo_uso,condicoes,base_legal\n"
    ."Arterial I,nR1-01,\"Estacionamento nos fundos; acesso único\",Art. 92\n"
    ."Local,nR1-01,,\n";
```

Assertivas: `lidos=2`, `importados=2`; a linha com condições grava `['Estacionamento nos fundos', 'acesso único']` (array de strings); a linha sem condições grava `null`; re-import faz upsert (`atualizados=2`, sem duplicar); cabeçalho antigo (com `quadro`) lança `RuntimeException`.

- [ ] **Step 2: Rodar e confirmar falha**

Run: `php artisan test --compact tests/Feature/Louos/LouosQuadro11ImportServiceTest.php`
Expected: FAIL (assinatura/cabeçalho antigos).

- [ ] **Step 3: Reescrever o import service**

Em `LouosQuadro11ImportService.php`: `EXPECTED_HEADER = ['classe_via', 'grupo_uso', 'condicoes', 'base_legal']`; assinatura `import(RuleVersion $version, string $csvPath)`; remover o filtro por `quadro`; `condicoes` vira:

```php
$condicoes = null;

if ($data['condicoes'] !== '') {
    $itens = array_values(array_filter(array_map(
        fn (string $item) => trim($item),
        explode(';', $data['condicoes']),
    ), fn (string $item) => $item !== ''));

    $condicoes = $itens === [] ? null : json_encode($itens, JSON_UNESCAPED_UNICODE);
}
```

Manter: validação de `classe_via` obrigatória, contagem importados/atualizados por `(classe_via, grupo_uso)`, upsert por `['rule_version_id', 'classe_via', 'grupo_uso']` atualizando `['condicoes', 'base_legal']`. Atualizar o docblock: importa o Quadro 11A (o "Quadro 11" não existe na publicação oficial da SEDUR — só 11A e 11B).

- [ ] **Step 4: Novo CSV de carga e seeder só do 11A**

Criar `database/data/louos/quadro11a-condicoes-via.csv` (condições convertidas do JSON legado para texto):

```csv
classe_via,grupo_uso,condicoes,base_legal
via_local,nR1,Recuo frontal de 3 m,Quadro 11A da Lei nº 9.148/2016
via_coletora,nR2,Recuo frontal de 4 m; 1 vaga de carga e descarga,Quadro 11A da Lei nº 9.148/2016
via_arterial,nR2,Recuo frontal de 4 m; faixa de acumulação,Quadro 11A da Lei nº 9.148/2016
via_de_transito_rapido,nR3,Recuo frontal de 6 m; exige estudo de tráfego,Quadro 11A da Lei nº 9.148/2016
```

Apagar `database/data/louos/quadro11-condicoes-via.csv`. Em `LouosQuadro11Seeder`: remover a chamada do domínio `LouosQuadro11`; a do 11A passa a `->import($version, database_path('data/louos/quadro11a-condicoes-via.csv'))`. Atualizar docblock (só 11A; Quadro 11 inexistente na lei).

- [ ] **Step 5: Ajustar testes de distribuição de seed**

Em `LouosSeedDistributionTest::test_quadro10_e_quadro11_e_11a_tem_versao_vigente`: renomear para `test_quadro10_e_quadro11a_tem_versao_vigente`, removendo a assertiva do domínio `LouosQuadro11` e adicionando `assertNull(RuleVersion::vigente(RuleDomain::LouosQuadro11)->first())`. Se `DatabaseSeederTest` referenciar o CSV antigo ou o quadro 11 semeado, ajustar da mesma forma.

- [ ] **Step 6: Verificar verde + pint + commit**

Run: `php artisan test --compact tests/Feature/Louos/LouosQuadro11ImportServiceTest.php tests/Feature/Louos/LouosSeedDistributionTest.php tests/Feature/Seeders/DatabaseSeederTest.php`
Expected: PASS. Depois `vendor/bin/pint --dirty --format agent`.

```bash
git add -A && git commit -m "refactor: padroniza importacao do Quadro 11A no formato CSV oficial"
```

---

### Task 2: Extrair `LouosQuadroCopier` (cópia vigente → rascunho reutilizável)

**Files:**
- Create: `app/Services/Louos/LouosQuadroCopier.php`
- Modify: `app/Services/Louos/LouosMaintenanceService.php`
- Test: `tests/Feature/Louos/LouosMaintenanceTest.php` (existente — regressão)

**Interfaces:**
- Produces: `LouosQuadroCopier::copy(RuleDomain $domain, ?RuleVersion $from, RuleVersion $to, array $alteracoes = []): void` — mesma semântica dos métodos `copyQuadro7/10/11` atuais (chave natural, insert em lote, `condicoes` como JSON).

- [ ] **Step 1: Teste de regressão já existe** — `LouosMaintenanceTest` cobre a cópia. Nenhum teste novo: refatoração pura (comportamento preservado).

- [ ] **Step 2: Criar `LouosQuadroCopier`** movendo `copyRows`/`copyQuadro7`/`copyQuadro10`/`copyQuadro11` de `LouosMaintenanceService` para a nova classe, expondo `copy(RuleDomain $domain, ?RuleVersion $from, RuleVersion $to, array $alteracoes = [])`. No `LouosMaintenanceService`, injetar o copier no construtor e trocar `$this->copyRows(...)` por `$this->copier->copy($domain, $current, $draft, $alteracoes)`.

- [ ] **Step 3: Verificar verde + pint + commit**

Run: `php artisan test --compact tests/Feature/Louos/LouosMaintenanceTest.php`
Expected: PASS (inalterado).

```bash
git add -A && git commit -m "refactor: extrai copia de linhas dos Quadros LOUOS para LouosQuadroCopier"
```

---

### Task 3: `LouosDraftService` — ciclo de vida do rascunho e CRUD de linhas

**Files:**
- Create: `app/Services/Louos/LouosDraftService.php`
- Test: `tests/Feature/Louos/LouosDraftServiceTest.php`

**Interfaces:**
- Consumes: `RuleVersionService::openDraft/publish`, `LouosQuadroCopier::copy`, `AuditService::log`, import services (`LouosQuadro7ImportService::import(RuleVersion, string)`, `LouosQuadro10ImportService::import(RuleVersion, string)`, `LouosQuadro11ImportService::import(RuleVersion, string)`).
- Produces (assinaturas exatas):

```php
final class LouosDraftService
{
    public const QUADRO_DOMAINS = [
        'quadro7' => RuleDomain::LouosQuadro7,
        'quadro10' => RuleDomain::LouosQuadro10,
        'quadro11a' => RuleDomain::LouosQuadro11a,
    ];

    public function rascunhoAberto(RuleDomain $domain): ?RuleVersion;
    public function abrirOuRetomar(RuleDomain $domain, ?string $version, int $userId): RuleVersion;
    public function inserirLinha(RuleVersion $draft, array $dados): Model;
    public function alterarLinha(RuleVersion $draft, int $linhaId, array $dados): Model;
    public function excluirLinha(RuleVersion $draft, int $linhaId): void;
    public function descartar(RuleVersion $draft): void;
    /** @return array{novas: int, alteradas: int, excluidas: int} */
    public function diff(RuleVersion $draft): array;
    /** @return array<string, mixed> relatório do import service */
    public function importarCsv(RuleVersion $draft, string $csvPath): array;
    public function publicar(RuleVersion $draft, int $publisherId): RuleVersion;
}
```

Regras internas:
- `rascunhoAberto`: `RuleVersion` do domínio com `status = rascunho`, mais recente.
- `abrirOuRetomar`: se há rascunho aberto, retorna ele (ignora `$version`); senão `openDraft` + `LouosQuadroCopier::copy($domain, $vigente, $draft)` + auditoria `rascunho-aberto`. `$version` obrigatória na abertura (lançar `InvalidArgumentException` se null e sem rascunho).
- Guarda comum (`assertDraft`): `$draft->status === RuleVersionStatus::Rascunho` e domínio ∈ QUADRO_DOMAINS, senão `DomainException`.
- Chave natural por domínio: quadro7 = `cnae_code` (normalizado para dígitos) + `area_min`; quadro10 = `zona` + `grupo_uso` + `subgrupo` (null → ''); quadro11a = `classe_via` + `grupo_uso` (null → '').
- `inserirLinha`: 422-equivalente via `ValidationException` (`ValidationException::withMessages(['linha' => 'Já existe uma linha com esta chave no rascunho.'])`) se a chave natural já existir no rascunho; cria via model (HasAuditoria registra) + auditoria `louos` evento `rascunho-linha-inserida`.
- `alterarLinha`: resolve o model pelo `linhaId` na tabela do domínio **com `rule_version_id` do rascunho** (senão `ModelNotFoundException`); rejeita colisão de chave com outra linha; auditoria `rascunho-linha-alterada`.
- `excluirLinha`: mesma resolução; `delete()` + auditoria `rascunho-linha-excluida`.
- `descartar`: apaga as linhas tipadas do rascunho e o `RuleVersion`; auditoria `rascunho-descartado`.
- `diff`: compara rascunho × vigente por chave natural: `novas` (só no rascunho), `excluidas` (só na vigente), `alteradas` (mesma chave, payload diferente — comparar todos os campos exceto id/timestamps/rule_version_id; quadro7 comparar floats com cast; quadro11a comparar `condicoes` como array). Sem vigente: tudo é `novas`.
- `importarCsv`: grava o upload em temp path e delega ao import service do domínio; auditoria `rascunho-importacao` com o relatório + nome original do arquivo; retorna o relatório.
- `publicar`: delega a `RuleVersionService::publish($draft, $publisherId)` (quatro olhos enforced) e audita o diff em `rascunho-publicado` (properties: diff + versão).

- [ ] **Step 1: Escrever os testes (RED)** — `tests/Feature/Louos/LouosDraftServiceTest.php` com `LazilyRefreshDatabase`, seedando `LouosQuadro7Seeder`/`LouosQuadro10Seeder` conforme o caso:

1. `test_abrir_rascunho_materializa_linhas_da_vigente` — abre rascunho do quadro7; asserta status rascunho, mesma contagem de faixas da vigente, vigente intacta.
2. `test_retomar_rascunho_aberto_e_idempotente` — segunda chamada retorna o mesmo rascunho, sem duplicar linhas.
3. `test_inserir_alterar_e_excluir_linha_so_no_rascunho` — CRUD de uma faixa; a vigente não muda; chave duplicada lança `ValidationException`.
4. `test_crud_quadro10_e_quadro11a` — mesma cobertura nas outras duas tabelas (permissão e condição de via).
5. `test_operacao_fora_de_rascunho_e_rejeitada` — `DomainException` ao operar numa versão vigente.
6. `test_diff_conta_novas_alteradas_e_excluidas` — após inserir 1, alterar 1 e excluir 1: `['novas' => 1, 'alteradas' => 1, 'excluidas' => 1]`.
7. `test_publicar_exige_quatro_olhos_e_promove_rascunho` — `FourEyesViolationException` com publisher = autor; com publisher distinto, rascunho vira vigente e a anterior é fechada; linha excluída não existe na nova vigente.
8. `test_descartar_remove_rascunho_e_preserva_vigente`.
9. `test_importar_csv_no_rascunho_retorna_relatorio` — CSV temporário de quadro7 com 2 linhas válidas + 1 inválida: relatório com `importados=2` e 1 rejeitado; linhas caem no rascunho, não na vigente.
10. `test_auditoria_registra_mutacoes` — `assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-linha-inserida'])` (e demais eventos).

- [ ] **Step 2: Rodar e confirmar falha**

Run: `php artisan test --compact tests/Feature/Louos/LouosDraftServiceTest.php`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Implementar `LouosDraftService`** conforme as regras internas acima, com mapa privado `modelClass(RuleDomain): string` (`LouosQuadro7Faixa`/`LouosQuadro10Permissao`/`LouosQuadro11CondicaoVia`) e `naturalKey(RuleDomain, array $dados): string`.

- [ ] **Step 4: Verificar verde + pint + commit**

Run: `php artisan test --compact tests/Feature/Louos/LouosDraftServiceTest.php`
Expected: PASS. Depois `vendor/bin/pint --dirty --format agent`.

```bash
git add -A && git commit -m "feat: adiciona LouosDraftService com CRUD de rascunho dos Quadros LOUOS"
```

---

### Task 4: Validação compartilhada + Form Requests

**Files:**
- Create: `app/Support/Louos/LouosLinhaRules.php`
- Modify: `app/Http/Requests/Gestao/PublishLouosVersionRequest.php`
- Create: `app/Http/Requests/Gestao/OpenLouosDraftRequest.php`
- Create: `app/Http/Requests/Gestao/StoreLouosLinhaRequest.php`
- Create: `app/Http/Requests/Gestao/ImportLouosCsvRequest.php`
- Test: coberto pelos testes de endpoint da Task 5 (escrever os casos de validação lá — RED primeiro na Task 5).

**Interfaces:**
- Produces: `LouosLinhaRules::forQuadro(string $quadro, string $prefix = ''): array<string, mixed>` — mesmas regras de `PublishLouosVersionRequest::alteracaoRules`, parametrizadas por prefixo (`'alteracoes.*.'` no publish, `''` no CRUD). `quadro11` NÃO é aceito (só 7/10/11a).

`OpenLouosDraftRequest`: `quadro` (required, in:quadro7,quadro10,quadro11a), `version` (nullable, string, max:255, unique em `rule_versions.version` para o domínio — exigida só quando não há rascunho aberto; o controller decide e o service valida).
`StoreLouosLinhaRequest`: `quadro` (required, in) + `LouosLinhaRules::forQuadro($quadro)`. Usado no store e no update.
`ImportLouosCsvRequest`: `quadro` (required, in) + `arquivo` (required, file, mimes:csv,txt, max:5120).

- [ ] **Step 1: Criar `LouosLinhaRules`** movendo o match de `alteracaoRules` para `forQuadro($quadro, $prefix)`; `PublishLouosVersionRequest::alteracaoRules` passa a `return LouosLinhaRules::forQuadro($quadro, 'alteracoes.*.');`.
- [ ] **Step 2: Criar os 3 Form Requests** (authorize: true — o gate é o middleware `permission:manter-louos` da rota).
- [ ] **Step 3: Regressão do publish**

Run: `php artisan test --compact tests/Feature/Louos/LouosMaintenanceTest.php tests/Feature/Louos/LouosUiTest.php`
Expected: PASS. Depois `vendor/bin/pint --dirty --format agent`.

```bash
git add -A && git commit -m "refactor: compartilha regras de validacao de linha dos Quadros LOUOS"
```

---

### Task 5: `LouosDraftController` + rotas + modelo CSV

**Files:**
- Create: `app/Http/Controllers/Gestao/LouosDraftController.php`
- Modify: `routes/gestao.php` (grupo `permission:manter-louos` existente, prefixo `louos`)
- Modify: `app/Http/Controllers/Gestao/LouosController.php` (remover `'quadro11'` de `QUADRO_DOMAINS`)
- Test: `tests/Feature/Louos/LouosDraftEndpointsTest.php`

**Interfaces:**
- Consumes: `LouosDraftService` (Task 3), Form Requests (Task 4).
- Produces (rotas nomeadas):

```php
Route::get('rascunho', [LouosDraftController::class, 'show'])->name('rascunho.show');          // ?quadro=
Route::post('rascunho', [LouosDraftController::class, 'open'])->name('rascunho.abrir');
Route::post('rascunho/linhas', [LouosDraftController::class, 'storeLinha'])->name('rascunho.linhas.store');
Route::put('rascunho/linhas/{linha}', [LouosDraftController::class, 'updateLinha'])->name('rascunho.linhas.update');
Route::delete('rascunho/linhas/{linha}', [LouosDraftController::class, 'destroyLinha'])->name('rascunho.linhas.destroy');
Route::post('rascunho/importar', [LouosDraftController::class, 'importar'])->name('rascunho.importar');
Route::put('rascunho/publicar', [LouosDraftController::class, 'publish'])->name('rascunho.publicar');
Route::delete('rascunho', [LouosDraftController::class, 'discard'])->name('rascunho.descartar');
Route::get('modelo-csv', [LouosDraftController::class, 'modeloCsv'])->name('modelo-csv');      // ?quadro=
```

Comportamento do controller:
- `show`: sem rascunho aberto para o quadro → `Inertia::render('gestao/louos/rascunho', [... 'draft' => null ...])` (a página oferece abrir). Com rascunho: props `quadro`, `quadroLabel`, `draft` (id, version, autor {id, name}), `itens` (paginado como a consulta, mas lendo o `rule_version_id` do rascunho), `diff`, `canPublish` (auth id ≠ created_by).
- `open`: cria/retoma e redireciona para `rascunho.show` do quadro.
- `storeLinha`/`updateLinha`/`destroyLinha`: resolvem o rascunho do quadro (404/422 se não houver), delegam ao service, `back()->with('status', ...)`. `ValidationException` do service vira erro de formulário automaticamente.
- `importar`: salva o upload em `storage_path('app/temp')`, chama `importarCsv`, devolve `back()->with('importacao', $relatorio)` (flash data para o relatório na tela).
- `publish`: `FourEyesViolationException` → `back()->with('error', ...)`. Sucesso → redirect para `louos.index` com status.
- `discard`: descarta e redirect para `louos.index`.
- `modeloCsv`: `StreamedResponse` `text/csv` com o cabeçalho do quadro + 1 linha de exemplo (conteúdo dos exemplos da spec §4.1.1).
- `LouosController::QUADRO_DOMAINS` perde a chave `'quadro11'` (consulta passa a exibir 3 Quadros).

- [ ] **Step 1: Escrever os testes de endpoint (RED)** — `LouosDraftEndpointsTest.php`:

1. `test_consultar_louos_nao_acessa_rotas_de_rascunho` (403 para usuário só com `consultar-louos`; seguir o padrão de criação de usuário/permissão de `LouosConsultaTest`).
2. `test_manter_louos_abre_rascunho_e_ve_pagina` (200 + componente `gestao/louos/rascunho` + prop `draft.version`).
3. `test_store_update_destroy_linha_via_http` (faixa criada/alterada/excluída só no rascunho; 422 em chave duplicada).
4. `test_publicar_com_mesmo_autor_retorna_erro` (flash `error`; com outro usuário publica e redireciona).
5. `test_importar_csv_retorna_relatorio_em_flash` (`UploadedFile::fake()->createWithContent` com CSV de quadro7; asserta flash `importacao.importados`).
6. `test_modelo_csv_baixa_cabecalho_do_quadro` (200, `content-type` csv, primeira linha = cabeçalho esperado).
7. `test_consulta_nao_lista_mais_quadro11` (a prop `quadros` da `louos.index` tem 3 itens).

- [ ] **Step 2: Rodar e confirmar falha** — Expected: FAIL (rotas/controller inexistentes).

- [ ] **Step 3: Implementar controller + rotas + ajuste no `LouosController`**.

- [ ] **Step 4: Verificar verde + pint + commit**

Run: `php artisan test --compact tests/Feature/Louos/LouosDraftEndpointsTest.php tests/Feature/Louos/LouosConsultaTest.php tests/Feature/Louos/LouosUiTest.php`
Expected: PASS.

```bash
git add -A && git commit -m "feat: adiciona endpoints do rascunho dos Quadros LOUOS com importacao CSV"
```

---

### Task 6: Frontend — metadados compartilhados + página do rascunho + ajustes na consulta

**Files:**
- Create: `resources/js/pages/gestao/louos/quadro-fields.ts`
- Modify: `resources/js/pages/gestao/louos/index.tsx`
- Create: `resources/js/pages/gestao/louos/rascunho.tsx`
- Test: `tests/Feature/Louos/LouosUiTest.php` (ajustar contagens de quadros se necessário)

**Interfaces:**
- Consumes: rotas nomeadas da Task 5 (URLs literais: `/gestao/louos/rascunho` etc. — o projeto usa URLs literais nas páginas, ver `index.tsx`).
- Produces: `quadro-fields.ts` exporta `AlteracaoField`, `PERMISSAO_OPTIONS`, `ALTERACAO_FIELDS` (sem a chave `quadro11`), `fieldsFor(quadro)`, `buildAlteracaoPayload(fields, row)` — movidos de `index.tsx` sem mudança de comportamento.

`index.tsx`:
- Remover `quadro11` de `QUADROS_META` (e o alias `ALTERACAO_FIELDS.quadro11a = ...` passa ao arquivo compartilhado).
- No `CardHeader` da tabela, ao lado de "Publicar nova versão", botão **"Editar Quadro"** (`Link`/`router.get` para `/gestao/louos/rascunho?quadro=${quadroSelecionado}`), visível com `manter-louos`.

`rascunho.tsx` (página nova, `GestaoLayout`, padrões visuais da `index.tsx`):
- Props: `quadro`, `quadroLabel`, `draft: { id, version, autor: { id, name } } | null`, `itens` (paginador), `diff: { novas, alteradas, excluidas } | null`, `canPublish: boolean`, `perPageOptions`.
- `draft === null`: card "Nenhum rascunho aberto" + form (Input identificador da versão) → `router.post('/gestao/louos/rascunho', { quadro, version })`.
- Com draft: banner azul "Rascunho `{version}` em edição — autor: {name}. A versão vigente não é alterada até a publicação."; `TableToolbar` com busca; `DataTable` com as mesmas colunas da consulta (extrair `getColumns` para `quadro-fields.ts` ou componente compartilhado) + coluna de ações (editar/excluir); botões: **Nova linha**, **Importar CSV**, **Baixar modelo CSV** (`<a href="/gestao/louos/modelo-csv?quadro=...">`), **Publicar**, **Descartar**.
- Modal de linha (criar/editar): mesmo padrão do `PublishQuadroVersionModal`, usando `fieldsFor(quadro)` e `buildAlteracaoPayload`; POST em `rascunho/linhas` ou PUT em `rascunho/linhas/{id}`; edição pré-preenche o form a partir da linha.
- Excluir: confirmação (`window.confirm` ou Modal pequeno) → `router.delete`.
- Importar: modal com `<input type="file" accept=".csv">` via `useForm` (`forceFormData: true`) → POST `rascunho/importar`; relatório vem em `flash.importacao` e é exibido em card (lidos/importados/atualizados + lista de rejeitados).
- Publicar: modal de confirmação exibindo o **diff** ("1 nova, 2 alteradas, 1 excluída") e, se `!canPublish`, aviso de quatro olhos + botão desabilitado.
- Descartar: confirmação → `router.delete('/gestao/louos/rascunho', { data: { quadro } })`.

- [ ] **Step 1: Extrair `quadro-fields.ts`** e ajustar `index.tsx` (sem mudança de comportamento; remover Quadro 11).
- [ ] **Step 2: Criar `rascunho.tsx`** completo conforme acima.
- [ ] **Step 3: Build do frontend**

Run: `npm run build`
Expected: sucesso sem erros de tipo. (O projeto commita `public/build` — incluir no commit.)

- [ ] **Step 4: Ajustar `LouosUiTest`** (3 quadros na prop; botão Editar presente para `manter-louos` e ausente para `consultar-louos`) e rodar:

Run: `php artisan test --compact tests/Feature/Louos/LouosUiTest.php`
Expected: PASS.

```bash
git add -A && git commit -m "feat: adiciona tela de edicao do rascunho dos Quadros LOUOS"
```

---

### Task 7: Verificação final

- [ ] **Step 1: Suite Louos completa**

Run: `php artisan test --compact tests/Feature/Louos`
Expected: PASS total.

- [ ] **Step 2: Pint geral dos arquivos tocados**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 3: Commit final (se houver resíduo)**

```bash
git add -A && git commit -m "style: formata arquivos do CRUD dos Quadros LOUOS"
```

---

## Self-Review

- **Cobertura da spec:** rascunho único compartilhado (Task 3), CRUD 4 operações (3/5/6), importação CSV + relatório (1/3/5/6), modelo CSV (5/6), diff na publicação (3/5/6), quatro olhos por resumo (3/5/6), remoção do Quadro 11 da tela (5/6), descarte (3/5/6), auditoria (3), permissões (5). Sem lacunas.
- **Placeholders:** nenhum — assinaturas, rotas, regras e dados de teste explícitos.
- **Consistência de tipos:** `LouosDraftService::QUADRO_DOMAINS` (3 chaves) usado em controller e Form Requests; `LouosQuadroCopier::copy(domain, from, to, alteracoes)` consumido por maintenance e draft; import 11A sem `$quadro` consistente entre Task 1 e Task 3.
