# Parte 2 — Bloco C (caixas e encaminhamento) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** a Caixa de entrada passa a mostrar contadores certos, a aba Analisados por histórico e nenhum filtro de Analista. A ficha ganha "Encaminhar para Chefia". O vistoriador só vê e abre o que é dele.

**Architecture:** As consultas da caixa ficam em `ProcessoQueryService`. O encaminhamento reaproveita `POST /status-analise`. O acesso à vistoria passa por uma classe nova, `VistoriaAcesso`, usada na lista e nas rotas diretas.

**Tech Stack:** Laravel 13, Inertia v3 (React 19), PHPUnit 12, Spatie Permission.

**Spec:** `docs/superpowers/specs/2026-10-20-ficha-analise-layout-parte-2-design.md`, Bloco C.

## Global Constraints

- Nome do sistema nas mensagens: **Viabiliza**.
- Sem coautoria nem marca de IA.
- Não incluir nos commits a mudança alheia em `VistoriaConsultaController.php:269` (`->role(['Vistoriador','administrador'])`). Nesse arquivo, usar sempre `git add -p` e recusar esse hunk.
- Arquivos com WIP alheio de renumeração de HU (comentários): `git add -p` e levar só os hunks da tarefa.
- PHP alterado: `vendor/bin/pint --dirty --format agent`. TS alterado: `npm run typecheck`.

## Review Focus

- Processo `para_conclusao` destinado ao usuário (`conclusao_encaminhada_para_user_id`) está na caixa dele e precisa entrar em "Aguardando análise" (Task 1).
- Processo analisado por ela e que voltou para a caixa dela não pode aparecer duas vezes em Analisados (Task 3: `distinct` por id).
- Transição com `from_status` nulo (processo antigo) para `encaminhado` conta como saída da analista, porque não veio de `para_distribuir` (Task 3).
- Vistoriador sem setor e sem nenhuma atribuição: a caixa fica vazia com os cards zerados, sem erro (Task 5).
- Analista do processo abrindo `processos/{id}/vistoria/visualizar` depois de mandar para a vistoria: 200, não 403 (Task 5).

---

### Task 1: Cards da Caixa de entrada (item 19)

**Files:**
- Modify: `app/Services/Analise/ProcessoQueryService.php:209-224` (`contadores`)
- Test: `tests/Feature/Analise/ProcessoFilaTest.php:173-195`

**Interfaces:**
- Produces: `contadores(User $user): array{aguardando_analise:int, em_analise:int, em_pendencia:int, vencendo_hoje:int}`, com a mesma assinatura.

- [ ] **Step 1: Reescrever o teste**

`test_contadores_da_caixa_de_entrada_sao_do_usuario`, com o cenário (usar os helpers `processo()` e `analistaDoSetor()` do arquivo):
- p1: setor, `assigned_user_id` null. Não é da caixa.
- p2: atribuído ao analista + `AnalysisRecord::factory()->create(['viability_request_id' => p2, 'analyst_user_id' => $analista->id])`.
- p3: atribuído + `AnalysisRecord` com `'analyst_user_id' => null` (pré-análise do motor).
- p4: atribuído, `status` EmPendencia.
- p5: atribuído, `analysis_due_at` = `now()->endOfDay()`.

Asserções:
```php
$this->assertSame(4, $contadores['aguardando_analise']);
$this->assertSame(1, $contadores['em_analise']);
$this->assertSame(1, $contadores['em_pendencia']);
$this->assertSame(1, $contadores['vencendo_hoje']);
```
Conferir se a factory de `AnalysisRecord` exige `revision`/`status` e passar os valores mínimos que ela pedir.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=test_contadores_da_caixa_de_entrada_sao_do_usuario`
Expected: FAIL (`aguardando_analise` = 0).

- [ ] **Step 3: Implementar**

- `aguardando_analise` = `escopo($user)->whereIn('status', self::STATUS_FILA)->count()`, a mesma base de `fila()`.
- `em_analise` = mesma base + `whereHas('analysisRecords', fn ($q) => $q->whereNotNull('analyst_user_id'))`.
- `em_pendencia` e `vencendo_hoje` não mudam.
- Atualizar o PHPDoc do método: "Aguardando análise = tudo na caixa; Em análise = os que já têm ficha aberta por alguém".

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoFilaTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -p app/Services/Analise/ProcessoQueryService.php tests/Feature/Analise/ProcessoFilaTest.php
git commit -m "fix: Aguardando análise conta tudo da caixa e Em análise só os que já têm ficha aberta"
```

### Task 2: Caixa de entrada sem filtro de Analista (item 15)

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php:136-176` (`fila`)
- Modify: `resources/js/components/analise/processo-filtros.tsx:39-50,105,175-178`
- Modify: `resources/js/pages/gestao/processos/fila.tsx:69,105,300-308`
- Test: `tests/Feature/Analise/ProcessoFilaTest.php`

**Interfaces:**
- Produces: a prop nova `mostrarAnalista?: boolean` (padrão `true`) em `ProcessoFiltros`. As caixas do setor e de vistoria não mudam.

- [ ] **Step 1: Escrever o teste**

`test_caixa_de_entrada_nao_oferece_nem_aplica_filtro_de_analista`. Analista A com 2 processos atribuídos; analista B do mesmo setor.
```php
$props = $this->filaProps($a, 'meus', 'analista='.$b->id);
$this->assertArrayNotHasKey('analistaOptions', $props);
$this->assertCount(2, $props['processos']['data']);
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=test_caixa_de_entrada_nao_oferece_nem_aplica_filtro_de_analista`
Expected: FAIL.

- [ ] **Step 3: Implementar**

- Em `fila()`: depois do loop dos filtros, `$filtros['analista'] = '';`. Remover `'analistaOptions'` do render.
- Em `ProcessoFiltros`: quando `mostrarAnalista` for `false`, não renderizar o bloco do Analista.
- Em `fila.tsx`: tirar a prop `analistaOptions` do tipo e da desestruturação, e passar `mostrarAnalista={false}`.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoFilaTest.php && npm run typecheck`
Expected: PASS, sem erros de tipo.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -p app/Http/Controllers/Gestao/ProcessoController.php resources/js/components/analise/processo-filtros.tsx resources/js/pages/gestao/processos/fila.tsx tests/Feature/Analise/ProcessoFilaTest.php
git commit -m "fix: Caixa de entrada deixa de ter o filtro Analista"
```

### Task 3: Aba Analisados pelo histórico de tramitação (item 3)

**Files:**
- Modify: `app/Services/Analise/ProcessoQueryService.php:188-199` (`analisadosPor`)
- Test: `tests/Feature/Analise/ProcessoFilaAnalisadosTest.php`

**Interfaces:**
- Consumes: `ViabilityRequest::analysisStatusTransitions()` e `AnalysisStatusTransition` (`from_status`, `to_status`, `actor_user_id`, `created_at`).
- Produces: `analisados(User)` e `contadorAnalisados(User, int $dias)` com a mesma assinatura. Os dois passam a incluir as saídas por tramitação.

- [ ] **Step 1: Escrever os testes**

Helper novo `transicao(ViabilityRequest $p, User $ator, ?AnalysisStatus $de, AnalysisStatus $para, int $diasAtras = 0)`. Ele cria o `AnalysisStatusTransition` e ajusta `created_at`. Os processos ficam em `status` EmAnalise, atribuídos a outra pessoa e **sem ficha finalizada pela analista**.
- `test_enviado_para_conclusao_pela_analista_aparece_em_analisados`: `EmAnalise → ParaConclusao` pela analista. Contém o processo.
- `test_encaminhado_pela_analista_aparece_em_analisados`: `EmAnalise → Encaminhado`. Contém.
- `test_analise_concluida_pela_analista_aparece_em_analisados`: `EmAnalise → AnaliseConcluida`. Contém.
- `test_distribuicao_feita_pelo_apoio_nao_conta_como_analisado`: `ParaDistribuir → Encaminhado` com ator = apoio. A aba do apoio não contém o processo.
- `test_processo_com_ficha_e_transicao_aparece_uma_vez`: ficha finalizada por ela + transição. `assertCount(1)`.
- `test_janela_de_dias_usa_a_data_da_transicao`: transição há 40 dias. `?janela=30` não conta; `?janela=90` conta (props `contadores.analisados`).

Usar `/gestao/processos/fila?visao=analisados` e `$props['processos']['data']`, como os testes existentes.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoFilaAnalisadosTest.php`
Expected: FAIL nos novos testes de "aparece" e de janela. Os de "não conta" e "uma vez" podem passar.

- [ ] **Step 3: Implementar**

Em `analisadosPor`, acrescentar ao `where` agrupado:
```php
->orWhereHas('analysisStatusTransitions', fn (Builder $t) => $t
    ->where('actor_user_id', $user->id)
    ->whereIn('to_status', [AnalysisStatus::ParaConclusao->value, AnalysisStatus::Encaminhado->value, AnalysisStatus::AnaliseConcluida->value])
    ->where(fn (Builder $origem) => $origem->whereNull('from_status')->orWhere('from_status', '!=', AnalysisStatus::ParaDistribuir->value))
    ->when($desde, fn (Builder $q) => $q->where('created_at', '>=', $desde)))
```
Garantir que os três `orWhereHas` ficam dentro do mesmo grupo de parênteses. Como `whereHas` não duplica linhas, o "uma vez" vem de graça; confirmar pelo teste. Atualizar o PHPDoc.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoFilaAnalisadosTest.php tests/Feature/Analise/ProcessoFilaTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -p app/Services/Analise/ProcessoQueryService.php tests/Feature/Analise/ProcessoFilaAnalisadosTest.php
git commit -m "fix: aba Analisados inclui o que a analista enviou para conclusão, encaminhou ou concluiu"
```

### Task 4: "Encaminhar para Chefia" na ficha (item 14b)

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php:392-441` (`atualizarStatusAnalise`)
- Modify: `app/Http/Controllers/Gestao/AnalysisRecordController.php:117-160` (props do `show`)
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (props, card Ações ~2521-2599 e o diálogo)
- Test: `tests/Feature/Analise/ParaConclusaoTest.php`
- Test: `tests/Feature/Analise/FichaUiSmokeTest.php`

**Interfaces:**
- Produces: a prop de topo da ficha `podeEncaminharChefia: bool` = `in_array(AnalysisStatus::ParaConclusao, $vr->analysis_status?->proximasPara($user->can('concluir-analise')) ?? [], true) && $user->can('analisar-processos')`.
- Produces: o campo opcional `voltar_para_caixa` (boolean) em `POST /gestao/processos/{id}/status-analise`. Quando verdadeiro e a transição dá certo, a resposta é `redirect()->route('gestao.processos.fila')->with('status', <mesma mensagem de confirmacaoDaTramitacao>)`. Em erro, o `back()` atual não muda.

- [ ] **Step 1: Escrever os testes**

Em `ParaConclusaoTest`:
- `test_encaminhar_para_chefia_pela_ficha_volta_para_a_caixa_de_entrada`, com o setup de `test_analista_servidor_envia_para_conclusao_sem_destinatario` e o payload `['status' => 'para_conclusao', 'voltar_para_caixa' => '1']`:
  ```php
  ->assertRedirect(route('gestao.processos.fila'))
  ->assertSessionHas('status', fn (string $m) => str_contains($m, 'aba Para conclusão'));
  ```
  Conferir também `analysis_status` = ParaConclusao e `conclusao_encaminhada_para_user_id` null.

Em `FichaUiSmokeTest`:
- `test_ficha_oferece_encaminhar_para_chefia_so_em_analise`: processo com `analysis_status` EmAnalise dá `podeEncaminharChefia` true; com `Analisar` dá false. Usar o helper de render já existente no arquivo.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Analise/ParaConclusaoTest.php tests/Feature/Analise/FichaUiSmokeTest.php`
Expected: FAIL nos dois testes novos.

- [ ] **Step 3: Implementar o backend**

Colocar o ramo de `voltar_para_caixa` no `return` final de `atualizarStatusAnalise` e adicionar `podeEncaminharChefia` às props do `show` da ficha.

- [ ] **Step 4: Implementar a tela**

- Botão `"Encaminhar para Chefia"` (`variant="outline" size="sm"`) no card Ações, logo depois de "Conclusão de Análise", visível quando `podeEncaminharChefia`.
- Abre `ConfirmDialog`:
  - título "Encaminhar para Chefia?";
  - texto "O processo vai para a aba Para conclusão da Caixa do setor e sai da sua Caixa de entrada.";
  - confirmar "Encaminhar".
- Ao confirmar: `router.post(\`/gestao/processos/${processo.id}/status-analise\`, { status: 'para_conclusao', voltar_para_caixa: true })`, com `loading` durante o envio.

- [ ] **Step 5: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Analise/ParaConclusaoTest.php tests/Feature/Analise/FichaUiSmokeTest.php && npm run typecheck`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -p app/Http/Controllers/Gestao/ProcessoController.php app/Http/Controllers/Gestao/AnalysisRecordController.php resources/js/pages/gestao/ficha-analise/show.tsx tests/Feature/Analise/ParaConclusaoTest.php tests/Feature/Analise/FichaUiSmokeTest.php
git commit -m "feat: ficha de análise encaminha para a chefia, mandando o processo para Para conclusão"
```

### Task 5: Vistoriador só vê e abre os processos dele (item 4)

**Files:**
- Create: `app/Services/Vistoria/VistoriaAcesso.php`
- Modify: `app/Http/Controllers/Gestao/VistoriaConsultaController.php` (`__invoke` universo e abas; `imprimir`)
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php` (`consultaVistoria`, ~193-201)
- Modify: `app/Http/Controllers/Gestao/InspectionController.php` (`show` ~80, `visualizar` ~128, `downloadAnexo` ~375)
- Test: `tests/Feature/Vistoria/VistoriaConsultaTest.php`
- Create: `tests/Feature/Vistoria/VistoriaAcessoTest.php`

**Interfaces:**
- Produces `App\Services\Vistoria\VistoriaAcesso`:
  - `escopar(Builder $query, User $user): Builder`. Com `distribuir-processos`, devolve a query como está. Sem ela, filtra `assigned_user_id = user` **ou** `whereHas('inspection', vistoriador_user_id = user)`.
  - `podeAtuar(ViabilityRequest $processo, User $user): bool`. Verdadeiro com `distribuir-processos`, ou quando `assigned_user_id = user`, ou quando a ficha de vistoria é dele.
  - `podeConsultar(ViabilityRequest $processo, User $user): bool`. Verdadeiro em `podeAtuar`, ou quando o usuário foi analista do processo: tem `analysisRecords.analyst_user_id = user` ou `analysisStatusTransitions.actor_user_id = user`.
- Onde cada um vale:
  - `podeAtuar`: `InspectionController::show`.
  - `podeConsultar`: `consultaVistoria`, `imprimir`, `visualizar`, `downloadAnexo`.
  - Negado → `abort(403)`. Isso cai no 403 auditado global.

- [ ] **Step 1: Ajustar `VistoriaConsultaTest`**

O `$this->vistoriador` (analista) passa a ver só os processos dele:
- `test_consulta_renderiza_universo_kpis_e_situacao`: `has('vistorias.data', 2)`, `kpis.encaminhadas` 2, `kpis.a_designar` 0, e a aba `designar` ausente (`abas` sem `id` `designar`).
- Novo `test_quem_distribui_ve_todo_o_universo`: `User::factory()->gestor()` (confirmar no seeder que tem `distribuir-processos`) vê 3, com `a_designar` 1 e a aba `designar` presente.
- `test_consulta_filtra_por_aba`: o caso `situacao=designar` passa a usar o gestor.

- [ ] **Step 2: Escrever `VistoriaAcessoTest`**

Setup: processo em Vistoriar atribuído ao vistoriador V1, com `Inspection` dele e um `InspectionAttachment` (usar a factory se existir; senão, criar o registro com `Storage::fake`). V2 é outro analista sem relação com o processo. A é a analista que encaminhou (transição `EmAnalise → Vistoriar` com ator A).

Testes:
- `test_outro_vistoriador_recebe_403_nas_rotas_diretas`: V2 em `GET /gestao/vistorias/{id}/consulta`, `.../consulta/imprimir`, `GET /gestao/processos/{id}/vistoria`, `.../vistoria/visualizar` e `.../vistoria/anexos/{anexo}`. Todos com `assertForbidden()`.
- `test_vistoriador_responsavel_abre_a_ficha_e_a_capa`: V1 recebe 200 em `/vistoria` e em `/consulta`.
- `test_analista_que_encaminhou_visualiza_a_ficha_de_vistoria`: A recebe 200 em `/vistoria/visualizar` e 403 em `/vistoria` (edição).

- [ ] **Step 3: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Vistoria/VistoriaConsultaTest.php tests/Feature/Vistoria/VistoriaAcessoTest.php`
Expected: FAIL (hoje V2 recebe 200 e a lista traz 3).

- [ ] **Step 4: Implementar**

- Criar `VistoriaAcesso` com as três assinaturas acima, injetando nos controllers pelo construtor ou pelo método, no padrão de cada controller.
- `__invoke`: aplicar `escopar($universo, $user)` **antes** de `kpis()`, para os cards também respeitarem o escopo. Tirar a aba `designar` de `abas` quando `! $podeDistribuir`.
- `imprimir` e `consultaVistoria`: `abort_unless($acesso->podeConsultar(...), 403)` depois da guarda de 404 que já existe.
- `InspectionController::show`: `abort_unless(podeAtuar, 403)` antes de `abrirOuRetomar`.
- `visualizar` e `downloadAnexo`: `podeConsultar`.

- [ ] **Step 5: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Vistoria`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Vistoria/VistoriaAcesso.php tests/Feature/Vistoria/VistoriaAcessoTest.php tests/Feature/Vistoria/VistoriaConsultaTest.php
git add -p app/Http/Controllers/Gestao/VistoriaConsultaController.php app/Http/Controllers/Gestao/ProcessoController.php app/Http/Controllers/Gestao/InspectionController.php
git commit -m "fix: vistoriador vê e abre só os processos de vistoria atribuídos a ele"
```
No `git add -p` do `VistoriaConsultaController.php`, recusar o hunk de `->role(['Vistoriador', 'administrador'])`.

### Task 6: Fechamento do bloco

- [ ] **Step 1: Suíte das áreas tocadas**

Run: `php artisan test --compact tests/Feature/Analise tests/Feature/Vistoria && npm run typecheck && npm test`
Expected: PASS. Se algo falhar, conferir se a falha já existia no commit `6e232c1b`, e anotar na entrega se for o caso.
