# Parte 2 — Bloco D (justificativa e erro do convite) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** a justificativa enviada à JUCEB deixa de levar "Fundamentação legal" e "Condicionantes", e colocar em convite deixa de cair na página "Oops 422".

**Architecture:** Mudança em `HttpReginParecerNotifier::justificativaPara`. O controller de pendência troca `abort(422)` por `ValidationException`. Um `respond()` em `bootstrap/app.php` converte qualquer 422 não-validação de visita Inertia em volta com flash de erro. A ficha passa a esconder "Colocar em convite" fora de `em_analise`.

**Tech Stack:** Laravel 13, Inertia v3 (React 19), PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-10-20-ficha-analise-layout-parte-2-design.md`, Bloco D.

## Global Constraints

- Nome do sistema nas mensagens: **Viabiliza**.
- Sem coautoria nem marca de IA em código ou commit.
- Justificativa gravada pelo analista (`regin_justificativa`) continua indo como está.
- Não alterar `fundamentacao` gravada nas decisões. Só o texto montado muda.
- Não mexer em `app/Http/Controllers/Gestao/VistoriaConsultaController.php` (WIP alheio).
- O working tree tem WIP alheio de renumeração de HU em comentários. Nos commits, usar `git add <arquivo>` só dos arquivos da tarefa. Quando um arquivo da tarefa também tiver WIP alheio, usar `git add -p` e levar só os hunks da tarefa.
- Depois de mexer em PHP: `vendor/bin/pint --dirty --format agent`.

## Review Focus

- Decisão com `reason` vazio e só fundamentação: o texto não pode ficar vazio. Cai em `"Viabilidade {outcome} pelo motor de regras."` (Task 1).
- Requisição JSON (`Accept: application/json`) em `gestao/processos/*` que recebe 422 deve continuar recebendo JSON 422, não redirect (Task 2).
- `ValidationException` em visita Inertia deve continuar voltando com `errors`, não com flash `error` (Task 2).
- Processo `em_pendencia`: "Colocar em convite" some e "Cancelar convite" continua (Task 3).
- 403 e 404 em visita Inertia não mudam de comportamento (Task 2: o handler só trata 422).

---

### Task 1: Justificativa à JUCEB sem fundamentação legal nem condicionantes (item 6)

**Files:**
- Modify: `app/Services/Regin/HttpReginParecerNotifier.php:85-180` (`justificativaPara` e auxiliares)
- Test: `tests/Feature/Regin/HttpReginParecerNotifierTest.php:93-168`
- Test: `tests/Feature/Regin/JustificativaReginTest.php:114-134`

**Interfaces:**
- Produces: `HttpReginParecerNotifier::justificativaPara(ViabilityRequest $request, ViabilityDecision $decision): string`, com a mesma assinatura. Também é usado em `ProcessoController.php:264` (card da tela), que passa a mostrar o mesmo texto novo.

- [ ] **Step 1: Reescrever os testes que fixam o texto antigo**

Em `HttpReginParecerNotifierTest`:
- `test_justificativa_nao_leva_notas_internas_do_motor` → renomear para `test_justificativa_sem_motivo_nao_leva_fundamentacao_nem_condicionantes`. Mesmo setup. Asserções:
  ```php
  $this->assertSame('Viabilidade deferida pelo motor de regras.', $justificativa);
  ```
- `test_indeferimento_envia_a_justificativa_seguida_da_fundamentacao` → renomear para `test_indeferimento_envia_so_o_motivo_da_decisao`. Asserção:
  ```php
  $this->assertSame('A solicitação está localizada no logradouro 212 – Avenida Lafayette Coutinho.', $justificativa);
  ```
- Novo `test_motivo_com_condicionantes_na_fundamentacao_vai_sem_elas`: `reason` = `'Deferida conforme análise técnica.'`, `fundamentacao` = `['Lei nº 9.148/2016 (LOUOS) — nR2-12', 'Desde que seja escritório da empresa']`. Esperado: `assertSame('Deferida conforme análise técnica.', $justificativa)`.

Em `JustificativaReginTest`:
- `test_texto_padrao_diz_o_fundamento_legal_e_separa_as_condicionantes` → renomear para `test_texto_padrao_sem_motivo_nao_leva_fundamento_nem_condicionantes`, com `$esperado = 'Viabilidade deferida pelo motor de regras.';`. Conferir no helper `processoDeferido` se `reason` é nulo. Se não for, o esperado é o `reason` do helper.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Regin/HttpReginParecerNotifierTest.php tests/Feature/Regin/JustificativaReginTest.php`
Expected: FAIL nos três testes reescritos e no novo (o texto ainda traz "Fundamentação legal"/"Condicionantes").

- [ ] **Step 3: Implementar**

`justificativaPara` mantém:
1. o texto gravado (`regin_justificativa`) quando houver;
2. senão, `trim($decision->reason)` quando não vazio;
3. senão, `"Viabilidade {$decision->outcome->value} pelo motor de regras."`.

Sempre com `mb_substr(..., 0, 10000)`. Remover o que ficar sem uso: `fundamentacao()`, `ehNotaInterna()`, `ehCondicionante()`, `abertura()` e a constante `MARCAS_NOTA_INTERNA`. Confirmar com `grep -n` no arquivo antes de apagar. Atualizar o PHPDoc do método para descrever a nova regra.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Regin`
Expected: PASS.

- [ ] **Step 5: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Regin/HttpReginParecerNotifier.php tests/Feature/Regin/HttpReginParecerNotifierTest.php tests/Feature/Regin/JustificativaReginTest.php
git commit -m "fix: justificativa enviada à JUCEB leva só o motivo da decisão, sem fundamentação legal e condicionantes"
```

### Task 2: Convite recusado volta com a mensagem, sem página "Oops" (item 8, backend)

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoPendenciaController.php:25-34`
- Modify: `bootstrap/app.php` (dentro do `respond()` existente, ~linha 180)
- Test: `tests/Feature/Analise/ProcessoPendenciaEndpointTest.php:100-111`
- Create: `tests/Feature/InertiaErroNegocioTest.php`

**Interfaces:**
- Produces: em `store`, a recusa vira `ValidationException::withMessages(['descricao' => $e->getMessage()])`. O `onError` de `abrirPendencia` (`show.tsx:1158`) já lê o primeiro erro e mostra no modal.
- Produces: a regra global em `respond()`: status 422 + cabeçalho `X-Inertia` + `! $request->expectsJson()` → `back()->with('error', $mensagem)`. `$mensagem` = `$e->getMessage()` quando `$e` for `HttpExceptionInterface` com mensagem não vazia; senão, `'Não foi possível concluir a operação.'`.

- [ ] **Step 1: Reescrever e escrever os testes**

`ProcessoPendenciaEndpointTest::test_abrir_fora_de_em_analise_e_recusado_com_422` → renomear para `test_abrir_fora_de_em_analise_volta_com_erro_no_campo_descricao`:
```php
$this->actingAs($this->analista(), 'gestao')
    ->from("/gestao/processos/{$processo->id}/ficha")
    ->post("/gestao/processos/{$processo->id}/pendencias", ['descricao' => 'Complementar a documentação.'])
    ->assertRedirect("/gestao/processos/{$processo->id}/ficha")
    ->assertSessionHasErrors(['descricao']);
$this->assertDatabaseCount('analysis_pendencies', 0);
$this->assertSame(ViabilityRequestStatus::Deferida, $processo->fresh()->status);
```
Atualizar o PHPDoc da classe ("recusado com 422" → "volta com erro no campo descricao").

`tests/Feature/InertiaErroNegocioTest.php` (`use LazilyRefreshDatabase`). No `setUp`, registrar rotas só de teste com middleware `web`:
- `Route::post('/_teste/abort-422', fn () => abort(422, 'Regra de negócio recusou.'))`
- `Route::post('/_teste/abort-403', fn () => abort(403))`
- `Route::post('/_teste/validacao', fn (Request $r) => $r->validate(['campo' => 'required']))`

Testes:
- `test_422_em_visita_inertia_volta_com_flash_de_erro`: `->from('/origem')->withHeaders(['X-Inertia' => 'true'])->post('/_teste/abort-422')`, `assertRedirect('/origem')` e `assertSessionHas('error', 'Regra de negócio recusou.')`.
- `test_422_em_requisicao_json_continua_422`: `->postJson('/_teste/abort-422')->assertStatus(422)`.
- `test_validacao_em_visita_inertia_continua_com_errors`: X-Inertia + `post('/_teste/validacao')`, `assertSessionHasErrors('campo')` e `assertSessionMissing('error')`.
- `test_403_em_visita_inertia_nao_muda`: X-Inertia + `post('/_teste/abort-403')->assertForbidden()`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoPendenciaEndpointTest.php tests/Feature/InertiaErroNegocioTest.php`
Expected: FAIL em `..._volta_com_erro_no_campo_descricao` (recebe 422) e em `test_422_em_visita_inertia_volta_com_flash_de_erro`. Os demais passam.

- [ ] **Step 3: Implementar**

- No controller, trocar `abort(422, $e->getMessage())` por `throw ValidationException::withMessages(['descricao' => $e->getMessage()]);`. Ajustar o PHPDoc da classe: "aviso controlado no campo descrição".
- Em `bootstrap/app.php`, no `respond()` existente, antes do `return $response;`, acrescentar o ramo 422 + `X-Inertia` descrito em Interfaces. Atualizar o comentário do bloco: 419 e 422 de regra de negócio em visita Inertia voltam à página anterior com aviso.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoPendenciaEndpointTest.php tests/Feature/InertiaErroNegocioTest.php tests/Feature/Analise --filter=Pendencia`
Expected: PASS.

- [ ] **Step 5: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/ProcessoPendenciaController.php tests/Feature/Analise/ProcessoPendenciaEndpointTest.php tests/Feature/InertiaErroNegocioTest.php
git add -p bootstrap/app.php
git commit -m "fix: convite recusado e outros 422 de regra de negócio voltam à tela com a mensagem em vez da página de erro"
```

### Task 3: "Colocar em convite" só com o processo em análise (item 8, tela)

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx:2564-2566`

**Interfaces:**
- Consumes: `processo.status`, já disponível (usado em `show.tsx:2568`).

- [ ] **Step 1: Implementar**

Envolver o botão "Colocar em convite" em `{processo.status === 'em_analise' && ( ... )}`, no mesmo padrão do "Cancelar convite" logo abaixo.

- [ ] **Step 2: Tipos e build**

Run: `npx tsc --noEmit -p . && npm run build`
Expected: sem erros.

- [ ] **Step 3: Conferir na tela**

Abrir a ficha de um processo `em_analise` (o botão aparece) e de um `em_pendencia` (o botão some, "Cancelar convite" aparece), pelo navegador do app com o servidor de desenvolvimento.

- [ ] **Step 4: Commit**

```bash
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "fix: ficha só oferece colocar em convite com o processo em análise"
```

### Task 4: Fechamento do bloco

- [ ] **Step 1: Suíte das áreas tocadas**

Run: `php artisan test --compact tests/Feature/Regin tests/Feature/Analise tests/Feature/InertiaErroNegocioTest.php`
Expected: PASS. Se aparecer falha, conferir se ela já existia antes do bloco (rodando o mesmo teste no commit `6e232c1b`). Falha anterior é anotada na entrega, não corrigida aqui.
