# Parte 2 — Bloco B (Tipo e Validade na conclusão) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ao concluir como Deferido, o analista escolhe o Tipo (Deferido / Provisório / Pré-operacional). Nos dois últimos, escolhe também a Validade (6 meses, 1 ano, 2 anos). Tipo e validade ficam gravados na decisão, aparecem na TVL e no portal e valem para a renovação.

**Architecture:**
- O `TvlKind` ganha `PreOperacional`.
- Um objeto de valor, `ValidadeDeferimento`, leva tipo e prazo do request ao serviço.
- `ConclusaoAnaliseService` grava a validade em três caminhos: ficha, revisão de concluído e "já deferido".
- `AnaliseTecnicaDecisionService` passa a gravar tipo e validade também fora da renovação.

**Tech Stack:** Laravel 13, Inertia v3 (React 19), PHPUnit 12, Vitest + happy-dom.

**Spec:** `docs/superpowers/specs/2026-10-20-ficha-analise-layout-parte-2-design.md`, Bloco B.

## Global Constraints

- Prazos permitidos: **6, 12 e 24 meses**. Rótulos na tela: "6 meses", "1 ano", "2 anos".
- Rótulos do Tipo no formulário: "Deferido" (`definitiva`), "Provisório" (`provisoria`), "Pré-operacional" (`pre_operacional`).
- Rótulo do enum `PreOperacional`: "Pré-operacional".
- Deferido (`definitiva`) não tem validade (`tvl_valid_until` null) e não pode ser renovado.
- A validade conta da **data da conclusão** (`now()`), em meses de calendário.
- Processo de renovação: o formulário de conclusão **não** mostra nem exige Tipo/Validade. O diálogo "Decidir processo" não muda.
- Coluna `viability_decisions.tvl_kind` é `string`. **Não precisa de migration.**
- Arquivos com WIP alheio de renumeração de HU: `git add -p`.
- Sem coautoria nem marca de IA. Pint no PHP, `npm run typecheck` no TS.

## Review Focus

- Mudar de Deferido para Indeferido numa revisão: `tvl_kind` e `tvl_valid_until` voltam a null (Task 2).
- Resultado diferente de Deferido enviando `tipo` por engano: é ignorado, não gravado (Task 3).
- Tipo `definitiva` com `validade_meses` enviado: a validade é ignorada e fica null (Task 2, `ValidadeDeferimento::de`).
- Malha fina em processo já deferido pelo expresso: o tipo e a validade são atualizados sem gerar um TVL novo e sem duplicar a decisão (Task 2).
- Renovação deferida pelo formulário de conclusão não pode passar a exigir Tipo (Task 3: `requiredIf` exclui renovação).

---

### Task 1: Pré-operacional no enum e na renovação

**Files:**
- Modify: `app/Enums/TvlKind.php`
- Modify: `app/Services/Renovacao/RenovacaoElegibilidadeService.php:32`
- Test: `tests/Feature/Renovacao/RenovacaoElegibilidadeTest.php`

**Interfaces:**
- Produces: `TvlKind::PreOperacional` (`'pre_operacional'`) e `TvlKind::renovavel(): bool`, verdadeiro para `Provisoria` e `PreOperacional`.

- [ ] **Step 1: Teste**

Duas mudanças no arquivo:
- Novo `test_pre_operacional_pode_ser_renovado`. Mesmo setup do caso provisória já existente, trocando para `'tvl_kind' => TvlKind::PreOperacional`. Esperado: `assertTrue($resultado->elegivel)`. Conferir o nome da propriedade em `RenovacaoElegibilidade`.
- No caso de definitiva já existente, manter a asserção da mensagem 'Viabilidade definitiva não pode ser renovada.'.

- [ ] **Step 2: Ver falhar**

Run: `php artisan test --compact tests/Feature/Renovacao/RenovacaoElegibilidadeTest.php`
Expected: FAIL (o case `PreOperacional` não existe).

- [ ] **Step 3: Implementar**

- Criar o case e o `renovavel()`. Atualizar o PHPDoc do enum.
- Na linha 32 do serviço: `if ($decisao->tvl_kind?->renovavel() !== true)`.

- [ ] **Step 4: Ver passar**

Run: `php artisan test --compact tests/Feature/Renovacao`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/TvlKind.php app/Services/Renovacao/RenovacaoElegibilidadeService.php tests/Feature/Renovacao/RenovacaoElegibilidadeTest.php
git commit -m "feat: viabilidade pré-operacional e renovável como a provisória"
```

### Task 2: Gravar tipo e validade ao concluir

**Files:**
- Create: `app/Services/Analise/ValidadeDeferimento.php`
- Modify: `app/Services/Analise/ConclusaoAnaliseService.php` (`concluir` L50, `concluirPelaFicha` L197-211, `revisarConclusao` L132-195; método privado novo)
- Modify: `app/Services/Analise/AnaliseTecnicaDecisionService.php:226-230` (`registrarDecisao`)
- Test: `tests/Feature/Analise/ConclusaoValidadeTest.php` (novo)

**Interfaces:**
- Produces: `final readonly class ValidadeDeferimento`:
  - `__construct(public TvlKind $tipo, public ?int $meses)`;
  - `static de(?string $tipo, int|string|null $meses): ?self`. Devolve null se `$tipo` vazio. Para `definitiva`, força `meses = null`.
  - `validoAte(CarbonInterface $base): ?CarbonImmutable`. Null se `meses` for null; senão, `$base->toImmutable()->addMonthsNoOverflow($meses)->startOfDay()`.
- Produces: o parâmetro novo, no final, `?ValidadeDeferimento $validade = null` em `ConclusaoAnaliseService::concluir(...)`. Os chamadores atuais continuam compilando.
- Consumes: `AnaliseTecnicaDecisionService::decide(..., ?TvlKind $tvlKind, ?string $tvlValidUntil, ...)`, que já existe.
- Regra em `registrarDecisao`:
  - `tvl_kind` = `$outcome === Deferida ? $tvlKind : null`;
  - `tvl_valid_until` = `$outcome === Deferida ? $tvlValidUntil : null`.
  - Sai a condição `isRenewal()`. A validação de renovação nas linhas 114-118 continua.

- [ ] **Step 1: Escrever `ConclusaoValidadeTest`**

`use LazilyRefreshDatabase`, seed de `RolesAndPermissionsSeeder`, `Event::fake([ResultadoEmitido::class])`, `Carbon::setTestNow('2026-10-20 10:00')`. Helpers copiados de `ConclusaoAnaliseTest` (`analista()` e `processoComFicha()`). Chamando o serviço `app(ConclusaoAnaliseService::class)->concluir(...)`:
- `test_deferido_provisorio_grava_tipo_e_validade_de_seis_meses`: `ValidadeDeferimento::de('provisoria', 6)` resulta em `decision->tvl_kind === TvlKind::Provisoria` e `tvl_valid_until->toDateString() === '2027-04-20'`.
- `test_deferido_pre_operacional_dois_anos`: `'2028-10-20'`.
- `test_deferido_definitivo_nao_tem_validade`: `de('definitiva', 12)` resulta em `Definitiva` e `tvl_valid_until` null.
- `test_revisao_de_indeferido_para_deferido_grava_validade`: processo já `Indeferida` com decisão; concluir Deferido com `de('provisoria', 12)` resulta em `'2027-10-20'`.
- `test_revisao_de_deferido_para_indeferido_limpa_tipo_e_validade`: decisão com `Provisoria`/`'2027-01-01'`; concluir Indeferido resulta nos dois null.
- `test_processo_ja_deferido_atualiza_tipo_e_validade_sem_novo_tvl`: processo `Deferida` com decisão `tvl_product_number` `'TVL-1'`; concluir Deferido com `de('pre_operacional', 24)` resulta em:
  - tipo `PreOperacional` e validade `'2028-10-20'`;
  - `tvl_product_number` ainda `'TVL-1'`;
  - `ViabilityDecision::count()` = 1;
  - existe `Activity` com `log_name` `'analise'` e `event` `'validade-deferimento'`.

- [ ] **Step 2: Ver falhar**

Run: `php artisan test --compact tests/Feature/Analise/ConclusaoValidadeTest.php`
Expected: FAIL (a classe `ValidadeDeferimento` não existe).

- [ ] **Step 3: Implementar**

1. Criar `ValidadeDeferimento` com as assinaturas acima.
2. `concluirPelaFicha`: repassar a `decide()` `tvlKind: $validade?->tipo` e `tvlValidUntil: $validade?->validoAte(now())?->toDateString()`, só quando `$resultado === ConclusionOutcome::Deferido && ! $request->isRenewal()`.
3. `revisarConclusao`: incluir em `$dados`:
   - com Deferido e não renovação: `tvl_kind` e `tvl_valid_until` da validade (ou null se não vier);
   - com qualquer outro resultado: os dois null.
4. `concluir`: no ramo em que `$request->status === $resultado->status()`, se `$resultado === Deferido`, `$validade !== null` e não for renovação, chamar o novo `private atualizarValidade(ViabilityRequest $request, User $ator, ValidadeDeferimento $validade): void`. Ele:
   - atualiza a decisão existente;
   - registra `AuditService::log(logName: 'analise', event: 'validade-deferimento', description: "Tipo e validade da viabilidade atualizados no processo #{id}", properties: [viability_request_id, tipo, valido_ate, anterior_tipo, anterior_valido_ate], subject: $request)`.
5. `registrarDecisao`: aplicar a regra de Interfaces.
6. Antes de fechar, conferir se a linha do tempo do processo (`ProcessoController::timeline`, L322) lê o log de auditoria. Se ler, o evento novo já aparece. Se não ler, deixar só a auditoria e anotar na entrega.

- [ ] **Step 4: Ver passar**

Run: `php artisan test --compact tests/Feature/Analise/ConclusaoValidadeTest.php tests/Feature/Analise/ConclusaoAnaliseTest.php tests/Feature/Renovacao`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/ValidadeDeferimento.php tests/Feature/Analise/ConclusaoValidadeTest.php
git add -p app/Services/Analise/ConclusaoAnaliseService.php app/Services/Analise/AnaliseTecnicaDecisionService.php
git commit -m "feat: conclusão grava o tipo e a validade da viabilidade deferida"
```

### Task 3: Requests e controllers da conclusão e da malha fina

**Files:**
- Modify: `app/Http/Requests/Gestao/ConcluirAnaliseRequest.php`
- Modify: `app/Http/Requests/Gestao/ConcluirMalhaFinaRequest.php`
- Modify: `app/Http/Controllers/Gestao/ConclusaoAnaliseController.php`
- Modify: `app/Http/Controllers/Gestao/MalhaFinaController.php:66`
- Test: `tests/Feature/Analise/ConclusaoValidadeTest.php` (endpoints)
- Test: `tests/Feature/Analise/MalhaFinaConcluirTest.php`

**Interfaces:**
- Produces: campos de request `tipo` (string) e `validade_meses` (int).
- Regras, iguais nos dois requests:
  - `'tipo' => [Rule::requiredIf(fn () => $this->input('resultado') === 'deferido' && ! $this->route('viabilityRequest')->isRenewal()), 'nullable', Rule::enum(TvlKind::class)]`
  - `'validade_meses' => ['nullable', 'required_if:tipo,provisoria,pre_operacional', 'integer', Rule::in([6, 12, 24])]`
- Mensagens:
  - `tipo.required` → 'Escolha o tipo do deferimento.'
  - `validade_meses.required_if` → 'Escolha a data da validade.'
- Controllers: passam `validade: $request->validated('resultado') === 'deferido' ? ValidadeDeferimento::de($request->validated('tipo'), $request->validated('validade_meses')) : null` ao serviço.

- [ ] **Step 1: Testes de endpoint**

Em `ConclusaoValidadeTest`:
- `test_deferir_sem_tipo_e_recusado`: POST `/gestao/processos/{id}/conclusao-analise` com `['resultado' => 'deferido']`, `assertSessionHasErrors('tipo')`.
- `test_provisorio_sem_validade_e_recusado`: `assertSessionHasErrors('validade_meses')`.
- `test_validade_fora_das_opcoes_e_recusada`: `validade_meses` 18, erro em `validade_meses`.
- `test_endpoint_grava_pre_operacional_um_ano`: sem erros, validade `'2027-10-20'`.
- `test_indeferir_ignora_tipo_enviado`: `['resultado' => 'indeferido', 'tipo' => 'provisoria', 'validade_meses' => 6]`, sem erros, e `tvl_kind` null.
- `test_renovacao_nao_exige_tipo_na_conclusao`: renovação (helper de `RenovacaoDecisaoTest`) com `['resultado' => 'indeferido']`, `assertSessionDoesntHaveErrors('tipo')`.

Em `MalhaFinaConcluirTest`:
- `test_malha_fina_deferido_exige_tipo_e_grava_validade`: primeiro sem `tipo`, erro; depois com `provisoria`/6, validade gravada. Usar o setup de encaminhamento aberto já existente no arquivo.

- [ ] **Step 2: Ver falhar**

Run: `php artisan test --compact tests/Feature/Analise/ConclusaoValidadeTest.php tests/Feature/Analise/MalhaFinaConcluirTest.php`
Expected: FAIL nos testes novos.

- [ ] **Step 3: Implementar**

Aplicar as regras e repassar a validade nos dois controllers. Os testes existentes que deferem sem `tipo` pelo endpoint (`ConclusaoAnaliseTest`, `MalhaFinaConcluirTest`, `ConclusaoExigeFichaSalvaTest`, `ParecerImediatoReginTest`) passam a receber erro: acrescentar `'tipo' => 'definitiva'` nos payloads de deferimento deles.

- [ ] **Step 4: Ver passar**

Run: `php artisan test --compact tests/Feature/Analise tests/Feature/Regin/ParecerImediatoReginTest.php tests/Feature/Renovacao`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -p app/Http/Requests/Gestao/ConcluirAnaliseRequest.php app/Http/Requests/Gestao/ConcluirMalhaFinaRequest.php app/Http/Controllers/Gestao/ConclusaoAnaliseController.php app/Http/Controllers/Gestao/MalhaFinaController.php tests/Feature
git commit -m "feat: conclusão de análise e malha fina pedem o tipo e a validade ao deferir"
```

### Task 4: Campos Tipo e Validade no formulário

**Files:**
- Modify: `resources/js/components/analise/conclusao-analise-form.tsx`
- Test: `resources/js/components/analise/conclusao-analise-form.test.tsx`
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (`concluirAnalise` L1094-1117, modal L2711-2738)
- Modify: `resources/js/pages/gestao/processos/show.tsx` (`concluirAnalise` L206-223, form L458-470) e a prop de renovação vinda de `ProcessoController::show`
- Modify: `resources/js/pages/gestao/malha-fina/index.tsx` (`useForm` L115, form L356) e `MalhaFinaItem` com `renovacao`
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php` (`show`: expor `renovacao` = `isRenewal()`) e o builder dos itens da malha fina (`renovacao` por item)

**Interfaces:**
- Produces: props opcionais novas em `ConclusaoAnaliseForm`:
  - `tipo?: string`, `onTipo?: (v: string) => void`;
  - `validadeMeses?: string`, `onValidadeMeses?: (v: string) => void`;
  - `erroTipo?: string | null`, `erroValidade?: string | null`.
- Comportamento:
  - Os campos aparecem só com `onTipo` definido **e** `resultado === 'deferido'`.
  - A validade aparece só com `tipo` em `provisoria` ou `pre_operacional`.
  - O botão de confirmar fica desabilitado enquanto faltar tipo ou validade obrigatórios.
- Opções exportadas: `TIPO_DEFERIMENTO_OPCOES` e `VALIDADE_OPCOES` (`[{value:'6',label:'6 meses'},{value:'12',label:'1 ano'},{value:'24',label:'2 anos'}]`).
- Páginas: passam `onTipo` só quando o processo não é renovação. Enviam `tipo` e `validade_meses` no POST. A malha fina usa `useForm({ resultado, observacao, tipo: '', validade_meses: '' })`.

- [ ] **Step 1: Testes Vitest** (padrão `createRoot`/`act` do arquivo)

- `'mostra Tipo só quando o resultado é deferido'`: `resultado='indeferido'` não tem `#tipo-deferimento`; com `deferido` tem.
- `'mostra Data da Validade só para provisório e pré-operacional'`: `tipo='definitiva'` não tem `#validade-deferimento`; `provisoria` tem.
- `'desabilita concluir sem a validade obrigatória'`: `deferido` + `provisoria` + `validadeMeses=''` deixam o botão de confirmar `disabled`.
- `'sem onTipo não mostra os campos (renovação)'`.

Os `id`s dos selects são `tipo-deferimento` e `validade-deferimento`.

- [ ] **Step 2: Ver falhar**

Run: `npx vitest run resources/js/components/analise/conclusao-analise-form.test.tsx`
Expected: FAIL.

- [ ] **Step 3: Implementar o form e as três páginas**

Ao trocar o resultado para algo diferente de deferido, limpar `tipo` e `validade_meses`. Ao trocar o tipo para `definitiva`, limpar a validade. Mostrar `errors.tipo` e `errors.validade_meses` vindos do servidor.

- [ ] **Step 4: Ver passar**

Run: `npx vitest run resources/js/components/analise && npm run typecheck && php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php`
Expected: PASS.

- [ ] **Step 5: Conferir na tela**

Pelo navegador do app com o servidor de desenvolvimento, conferir:
- ficha: Deferido → Provisório → 1 ano → concluir;
- malha fina: os mesmos campos;
- ficha de renovação: os campos não aparecem.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/analise/conclusao-analise-form.tsx resources/js/components/analise/conclusao-analise-form.test.tsx resources/js/pages/gestao/malha-fina/index.tsx resources/js/pages/gestao/processos/show.tsx
git add -p resources/js/pages/gestao/ficha-analise/show.tsx app/Http/Controllers/Gestao/ProcessoController.php app/Http/Controllers/Gestao/MalhaFinaController.php
git commit -m "feat: formulário de conclusão pergunta o tipo do deferimento e a validade"
```

### Task 5: Tipo e validade na TVL e no portal

**Files:**
- Modify: `app/Services/Analise/TvlPdfService.php:102-120` (`montarDados`)
- Modify: `resources/views/tvl/documento.blade.php` (perto de "Data da decisão", ~L44-47)
- Modify: `app/Support/ParecerDecisao.php:16-33` (`de`)
- Modify: `resources/js/components/decisao/parecer-card.tsx` (tipo e render)
- Test: `tests/Feature/Analise/TvlPdfServiceTest.php`
- Test: `tests/Unit/Support/ParecerDecisaoTest.php` (criar se não existir; senão, o teste existente de `ParecerDecisao`)

**Interfaces:**
- Produces: chaves novas em `montarDados`:
  - `tvl_tipo` (`?string`) = `$decision->tvl_kind?->label()`;
  - `tvl_valido_ate` (`?CarbonInterface`) = `$decision->tvl_valid_until`.
- Produces: chaves novas em `ParecerDecisao::de()`:
  - `tipo` (`?string`) = label;
  - `valido_ate` (`?string`) = ISO date `Y-m-d`.
- Blade:
  - com `tvl_tipo`: `<div class="campo"><strong>Tipo:</strong> {{ $tvl_tipo }}</div>`;
  - com `tvl_valido_ate`: `<div class="campo"><strong>Válido até:</strong> {{ $tvl_valido_ate->format('d/m/Y') }}</div>`.
- Portal (`ParecerCard`): com `tipo`, mostrar "Tipo: X"; com `valido_ate`, mostrar "Válido até dd/mm/aaaa".

- [ ] **Step 1: Testes**

- `TvlPdfServiceTest::test_template_mostra_tipo_e_validade`: decisão deferida com `PreOperacional` e `'2027-10-20'`. O HTML de `view('tvl.documento', $dados)` contém `'Pré-operacional'` e `'Válido até:</strong> 20/10/2027'`.
- `TvlPdfServiceTest::test_template_sem_validade_nao_mostra_valido_ate`: decisão `Definitiva`. Contém `'Definitiva'` e não contém `'Válido até'`.
- `ParecerDecisaoTest::test_inclui_tipo_e_validade`: `tipo` = 'Provisória' e `valido_ate` = '2027-04-20'.

- [ ] **Step 2: Ver falhar**

Run: `php artisan test --compact --filter='TvlPdfServiceTest|ParecerDecisaoTest'`
Expected: FAIL.

- [ ] **Step 3: Implementar backend, Blade e o card do portal**

- [ ] **Step 4: Ver passar**

Run: `php artisan test --compact --filter='TvlPdfServiceTest|ParecerDecisaoTest' && npm run typecheck`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/tvl/documento.blade.php app/Support/ParecerDecisao.php resources/js/components/decisao/parecer-card.tsx tests/Feature/Analise/TvlPdfServiceTest.php tests/Unit/Support/ParecerDecisaoTest.php
git add -p app/Services/Analise/TvlPdfService.php
git commit -m "feat: TVL e portal mostram o tipo e a validade da viabilidade"
```

### Task 6: Fechamento do bloco

- [ ] **Step 1: Suíte das áreas tocadas**

Run: `php artisan test --compact tests/Feature/Analise tests/Feature/Renovacao tests/Feature/Regin tests/Unit && npm run typecheck && npm test`
Expected: PASS. Se algo falhar, conferir se a falha já existia no commit `6e232c1b`, e anotar na entrega se for o caso.
