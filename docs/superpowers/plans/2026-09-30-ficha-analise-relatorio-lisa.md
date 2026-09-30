# Ficha de análise — relatório Lisa 30/09 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A ficha e a tela do processo passam a mostrar só o fato operacional que a cliente pediu, a malha fina fica restrita ao fluxo expresso já decidido, e o indeferimento do Quadro 10 cita o subgrupo.

**Architecture:** O texto operacional do motivo nasce em `MotivoAnaliseComposer` e é reutilizado pelo resumo e pelo campo da tela do processo. A ficha só reorganiza cards e esconde a malha fina. O motor recusa indeferir nR1 pelo grupo quando os subgrupos divergem na zona.

**Tech Stack:** Laravel 13, PHPUnit 12, Inertia v3, React 19.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-30-ficha-analise-relatorio-lisa-design.md`
- TDD: teste falhando antes do código de produção
- PHP alterado: `vendor/bin/pint --dirty --format agent`
- UI, mensagens e commits em pt-BR; código em inglês
- Sem parâmetro novo: rótulo de tela fica no código
- Sem adaptador falso: zona e via ausentes continuam visíveis como ausência
- A frase do parecer não é reescrita; continua em `DecisionTextCatalog`
- HU-136 já está atualizada; não editar de novo

---

### Task 1: Motivo de Análise operacional

**Files:**
- Modify: `app/Services/Analise/MotivoAnaliseComposer.php`
- Test: `tests/Feature/Analise/MotivoAnaliseComposerTest.php`

**Interfaces:**
- Consumes: `ExpressoQueda` (`cnae`, `motivo`, `tipo_gatilho`), território e enquadramento já gravados na queda
- Produces: `MotivoAnaliseComposer::para(ViabilityRequest $request, ?ResolvedViability $resolved): list<string>` — só linhas operacionais

- [ ] **Step 1: Write the failing test**

Criar `tests/Feature/Analise/MotivoAnaliseComposerTest.php` com `RefreshDatabase`. Três casos:

```php
public function test_loteamento_itaigara_vira_so_a_frase_operacional(): void
{
    $request = ViabilityRequest::factory()->create();
    ExpressoQueda::factory()->create([
        'viability_request_id' => $request->id,
        'motivo' => 'Não foi possível verificar a restrição territorial "Loteamento Itaigara (TAC vigente)" (camada geográfica indisponível)',
        'tipo_gatilho' => 'regra_territorial',
        'dimensao' => 'territorio',
    ]);

    $linhas = app(MotivoAnaliseComposer::class)->para($request->fresh(), null);

    $this->assertSame(['Solicitação cadastrada no Loteamento Itaigara'], $linhas);
}

public function test_cnae_sem_enquadramento_cita_o_codigo_e_a_area(): void
{
    $request = ViabilityRequest::factory()->create(['used_area_m2' => 156636]);
    ExpressoQueda::factory()->create([
        'viability_request_id' => $request->id,
        'cnae' => '1353900',
        'motivo' => 'Classificação de risco não parametrizada para o CNAE',
        'tipo_gatilho' => 'sem_enquadramento',
    ]);

    $linhas = app(MotivoAnaliseComposer::class)->para($request->fresh(), null);

    $this->assertContains('CNAE 1353-9/00 — não enquadrado — para enquadramento do analista', $linhas);
    $this->assertContains('Área utilizada declarada: 156636 m².', $linhas);
    $this->assertFalse(collect($linhas)->contains(fn (string $l) => str_contains($l, 'gatilho') || str_contains($l, 'dimensão decisiva')));
}
```

Ajustar a factory de `ExpressoQueda` aos campos reais antes de rodar. Se `tipo_gatilho` for enum, usar o case existente de restrição territorial e o de ausência de enquadramento.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Analise/MotivoAnaliseComposerTest.php`

Expected: FAIL porque a linha ainda contém "camada geográfica indisponível" e "gatilho".

- [ ] **Step 3: Write minimal implementation**

Em `queda()`, mapear o motivo bruto para a frase da spec:

- contém "Loteamento Itaigara" → `Solicitação cadastrada no Loteamento Itaigara`
- contém "Loteamento Vela Branca" → `Solicitação cadastrada no Loteamento Vela Branca`
- ZEM, ZPAM, ABM trechos 5 e 6, ZUE → as frases já escritas em `docs/artefatos/Especificação de Regras de Negócio e Tratamentos do Sistema.pdf`
- sem enquadramento de CNAE → `CNAE {formatado} — não enquadrado — para enquadramento do analista`
- área declarada, quando a queda depender de área → `Área utilizada declarada: {valor} m².`

`contexto()` deixa de acrescentar tipo de imóvel, zona, Quadro 10 e tendência.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Analise/MotivoAnaliseComposerTest.php`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analise/MotivoAnaliseComposer.php tests/Feature/Analise/MotivoAnaliseComposerTest.php
git commit -m "fix: motivo de análise usa a frase operacional da queda"
```

---

### Task 2: Resumo mostra a restrição de endereço

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (`ResumoProcessoCard`)
- Test: `tests/Feature/Analise/FichaUiSmokeTest.php`

**Interfaces:**
- Consumes: `analysis_reasons` já gravados pela Task 1
- Produces: bloco visível no card Resumo com essas linhas quando a lista não está vazia

- [ ] **Step 1: Write the failing test**

No smoke da ficha, processo com `analysis_reasons` = `['Solicitação cadastrada no Loteamento Itaigara']`. Assert na prop Inertia `analysis_reasons` e, se o smoke renderiza HTML, que o texto aparece no card de resumo. Se o smoke só checa o componente, assertar a prop e cobrir a renderização num teste Vitest do card, se já existir suíte do componente. Senão, o feature test da prop basta e o card lê `analysisReasons` que a página já tem.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php --filter=resumo`

Expected: FAIL — o resumo não recebe as restrições.

- [ ] **Step 3: Write minimal implementation**

Dentro de `ResumoProcessoCard`, acima da síntese da IA, listar `analysisReasons`. Lista vazia não renderiza o bloco. A síntese da IA permanece.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/show.tsx tests/Feature/Analise/FichaUiSmokeTest.php
git commit -m "fix: resumo da ficha exibe a restrição de endereço"
```

---

### Task 3: Camadas — sem logradouros próximos, com zona e via

**Files:**
- Modify: `resources/js/components/geo/camadas-imovel.tsx`
- Modify: o payload de localização da ficha que monta `camadas.zona` (resource da ficha; hoje a zona do polígono e a da lista divergem)
- Test: `tests/Feature/Analise/FichaUiSmokeTest.php` e, se a zona vier do resource, o teste desse resource

**Interfaces:**
- Consumes: zona e via já usadas no card do polígono (`localizacao.zona`, `localizacao.via`)
- Produces: `CamadasImovelLista` sem a linha "Logradouros próximos"; linha Zona com o nome quando conhecido; linha Via

- [ ] **Step 1: Write the failing test**

Prop da ficha com zona `ZPR 3` e via `Avenida Luís Viana Filho`, e `camadas.zona.status` ausente. O resource deve preencher `camadas.zona.nome` com `ZPR 3` e expor `via`. O componente não inclui "Logradouros próximos".

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — zona "Não identificado" e linha de logradouros próximos presente.

- [ ] **Step 3: Write minimal implementation**

Remover o item "Logradouros próximos" do array `linhas` em `camadas-imovel.tsx`. Acrescentar Via. No resource, se `camadas.zona` não estiver identificado e `localizacao.zona` estiver, copiar o nome. O mesmo para a via. Não apagar `logradourosProximos()` de `CamadasIncidentesService`.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/geo/camadas-imovel.tsx tests/Feature/Analise/FichaUiSmokeTest.php
git commit -m "fix: camadas da ficha mostram zona e via e ocultam logradouros próximos"
```

Incluir no `git add` o resource PHP se ele tiver sido alterado.

---

### Task 4: Confirmações dentro do polígono

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (cards por volta das linhas 1608 e 1703)

- [ ] **Step 1: Write the failing test**

Smoke: a prop `address_confirmed` e `is_public_area` continuam na ficha. Não há segundo card com título "Confirmações do imóvel" isolado; os controles ficam no card do polígono. Se o smoke não renderiza HTML, extrair o bloco para um componente e cobrir com o teste de página existente que inspeciona o source via Inertia só no contrato, e um teste do componente se a suíte Vitest da ficha existir. Mínimo aceitável: o markup do card do polígono contém "Endereço correto?" e o card separado deixa de existir — verificável lendo o JSX no teste de string só se já houver esse padrão. Preferir o smoke Inertia das props, que não quebra, mais a mudança de layout no mesmo passo.

- [ ] **Step 2: Mover o bloco**

Recortar o conteúdo de "Confirmações do imóvel" para dentro do `Card` do polígono, depois do `dl` de Zona e Via. Apagar o card antigo.

- [ ] **Step 3: Run the ficha smoke**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php`

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "fix: confirmações do imóvel ficam no card do polígono"
```

---

### Task 5: Malha fina só no expresso já decidido

**Files:**
- Modify: `app/Services/Analise/MalhaFinaService.php`
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (botão por volta da linha 2555)
- Modify: `resources/js/pages/gestao/processos/index.tsx` (lote)
- Test: `tests/Feature/Analise/MalhaFinaServiceTest.php`

**Interfaces:**
- Consumes: decisão do fluxo expresso (`decision.flow` expresso, ou o campo que o teste de deferido expresso já usa) e status de análise
- Produces: `encaminhar()` lança exceção de domínio com mensagem visível quando o processo está em análise humana ou não tem decisão expressa

- [ ] **Step 1: Write the failing test**

```php
public function test_recusa_malha_fina_de_processo_em_analise_humana(): void
{
    $request = ViabilityRequest::factory()->emAnalise()->create();

    $this->expectException(DomainException::class);

    app(MalhaFinaService::class)->encaminhar($request, User::factory()->create(), 'Rever');
}

public function test_aceita_malha_fina_de_deferido_pelo_expresso(): void
{
    $request = ViabilityRequest::factory()->deferidoExpresso()->create();

    $referral = app(MalhaFinaService::class)->encaminhar(
        $request,
        User::factory()->create(),
        'Amostra do expresso',
    );

    $this->assertTrue($request->fresh()->in_fine_mesh);
    $this->assertNotNull($referral->id);
}
```

Usar os states reais da factory. Se `deferidoExpresso` não existir, criar state que grava `ViabilityDecision` com flow do expresso. Se `emAnalise` não existir, `forceFill` do `analysis_status` para o case Em análise.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Analise/MalhaFinaServiceTest.php --filter=malha_fina_de_processo`

Expected: FAIL — `encaminhar` hoje aceita o processo em análise.

- [ ] **Step 3: Write minimal implementation**

No início de `encaminhar` e de cada item de `encaminharLote`: se não houver decisão do fluxo expresso, lançar `DomainException` com a frase "Malha fina só se aplica a processo decidido pelo fluxo expresso." Não alterar `in_fine_mesh`.

Na ficha, não renderizar o botão quando o processo está em análise (a ficha inteira é esse caso: `podeMalhaFina` passa a exigir também decisão expressa, prop nova `podeEncaminharMalhaFina` vinda do controller).

Na consulta, o checkbox do lote só habilita linhas com essa elegibilidade.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS, inclusive os testes antigos de deferido. Ajustar o teste que encaminhava processo em análise esperando sucesso: ele passa a esperar a exceção.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analise/MalhaFinaService.php resources/js/pages/gestao/ficha-analise/show.tsx resources/js/pages/gestao/processos/index.tsx tests/Feature/Analise/MalhaFinaServiceTest.php
git commit -m "fix: malha fina só no processo decidido pelo fluxo expresso"
```

---

### Task 6: Precedentes em análise no imóvel

**Files:**
- Modify: o repositório de precedentes (`app/Services/Analise/PrecedentRepository.php` ou o Postgis irmão)
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (`Precedentes`, linha ~3626)
- Test: o teste de precedentes existente

**Interfaces:**
- Produces: `precedentes.em_analise` inteiro — quantidade de processos do mesmo imóvel com status Em análise, excluindo o processo aberto

- [ ] **Step 1: Write the failing test**

Dois processos com a mesma inscrição, um Em análise além do atual. A resposta de precedentes traz `em_analise` = 1.

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — chave ausente.

- [ ] **Step 3: Write minimal implementation**

Contar pela inscrição imobiliária e `analysis_status` Em análise. No painel, linha "Em análise neste imóvel: {n}". Zero aparece como 0, não some.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "feat: precedentes contam processos em análise do imóvel"
```

---

### Task 7: Enquadramento, sem justificativa por CNAE

**Files:**
- Modify: `app/Http/Controllers/Gestao/AnalysisRecordController.php` (`enquadramentoOpcoes`, linha 520)
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (card linha 1926 e o textarea Justificativa por volta da 2128)
- Test: teste do controller ou `FichaUiSmokeTest`

**Interfaces:**
- Produces: `enquadramentoOpcoes[cnae]` com todas as linhas vigentes da planilha para o CNAE de 7 dígitos, mesmo quando `codigo_louos` do item está vazio

- [ ] **Step 1: Write the failing test**

CNAE `1353900` com duas linhas em `TratamentoEnquadramento` e `per_cnae` sem `codigo_louos`. A prop `enquadramentoOpcoes` traz as duas. O payload da ficha não inclui campo de justificativa editável por CNAE na estrutura que a página usa para renderizar o textarea — assertar que a página não recebe obrigação de justificativa. No JSX, remover o textarea e a descrição do `CardHeader`.

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL se a busca por CNAE mascarado não achar as linhas no formato gravado. Corrigir o casamento (`1353-9/00` versus `1353900`) no teste para o formato real da tabela; o defeito do relatório é a lista vazia com atividade não enquadrada.

- [ ] **Step 3: Write minimal implementation**

Garantir que CNAE sem código LOUOS ainda consulta a planilha. Remover o bloco Justificativa de cada `<li>` de CNAE e a prop `description` do card "Enquadramento por atividade (CNAE)".

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "fix: enquadramento manual lista a planilha e sai a justificativa por CNAE"
```

---

### Task 8: Tramitação sai da ficha; classificação de risco muda de lugar

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`

- [ ] **Step 1: Write the failing test**

Smoke da ficha não depende do bloco de tramitação da ficha. Busca no TSX, via teste ou revisão do render: não existe card com título "Tramitação" nesta página. O card de risco tem título "Classificação de Risco e Fundamentação Legal" e aparece no JSX antes do card "Condicionantes" (linha 2187).

- [ ] **Step 2: Implementar**

Apagar o card Tramitação da ficha (tabela e "Imprimir Extrato da Tramitação"). Renomear o card da linha 1809. Mover esse card para imediatamente antes de Condicionantes.

- [ ] **Step 3: Run the ficha smoke**

Run: `php artisan test --compact tests/Feature/Analise/FichaUiSmokeTest.php`

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git commit -m "fix: ficha tira a tramitação e antecipa a classificação de risco"
```

---

### Task 9: Parecer mostra o enquadramento do CNAE

**Files:**
- Modify: o componente do parecer na ficha e em `resources/js/components/decisao/parecer-card.tsx`, onde a frase do catálogo é montada
- Modify: o builder que prefixa `CNAE {código}:` na frase (`JustificativaIndeferimentoBuilder` ou o equivalente da ficha)
- Test: `tests/Feature/Decisao/ViabilityDecisionJustificativaTest.php` ou o teste do parecer da ficha

**Interfaces:**
- Consumes: `DecisionTextCatalog::render('justificativa.conclusao.nao_permitido', [':zona' => ...])`
- Produces: texto por CNAE com uma linha de enquadramento antes da frase do catálogo

- [ ] **Step 1: Write the failing test**

```php
$this->assertStringContainsString('nR1-04', $parecer);
$this->assertStringContainsString(
    'Diante do enquadramento acima, manifesta-se pelo indeferimento desta atividade',
    $parecer,
);
```

O enquadramento (subgrupo e código LOUOS) vem antes da frase. A frase continua saindo do catálogo, não de string nova no teste além do trecho estável.

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — hoje só existe a frase, sem o subgrupo.

- [ ] **Step 3: Write minimal implementation**

Prefixar cada parágrafo com o enquadramento gravado no item (`grupo`, `subgrupo`, `codigo_louos`, quadro). Sem subgrupo gravado, a linha diz "subgrupo não identificado" em vez de omitir o bloco.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "fix: parecer exibe o enquadramento do CNAE antes da frase padrão"
```

---

### Task 10: Tela do processo — motivo, encaminhamento e quem distribuiu

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php` (timeline, linha 200)
- Modify: `resources/js/pages/gestao/processos/show.tsx` (linhas 318 e 352, aba Tramitação linha 400)
- Modify: o endpoint `status-analise` que recebe o POST
- Test: feature do detalhe do processo

**Interfaces:**
- Produces: timeline item `{ ator: string|null, atribuido: string|null }`
- Produces: POST `status-analise` com `status=encaminhado` exige `sector_id` e `user_id`

- [ ] **Step 1: Write the failing test**

Processo em análise com motivos `['Loteamento Vela Branca']` e categoria semi-expresso. A prop do detalhe expõe `motivo_encaminhamento` = `Loteamento Vela Branca / Fluxo semi-expresso`, não `simulacao_protocolo`.

Transição de distribuição com `actor_user_id` e `assigned_to`. A timeline traz `ator` e `atribuido`.

POST encaminhado sem setor devolve 422. Com setor e usuário, grava a atribuição e a transição.

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

Trocar o rótulo lido pelo analista: em análise, o campo mostra `motivo_encaminhamento`. O select "Encaminhado para" revela setor e usuário e só então envia o POST. `ProcessoController::show` carrega `transitions.actor` e `assignedTo` e projeta os nomes.

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "fix: processo mostra o motivo do encaminhamento e quem distribuiu"
```

---

### Task 11: Quadro 10 não indefere nR1 pelo grupo

**Files:**
- Modify: `app/Services/Louos/LouosEnquadramentoService.php` (busca da permissão por subgrupo, por volta da linha 935, e `motivoNaoPermitido`)
- Test: `tests/Feature/Louos/LouosConsolidacaoTest.php` ou teste irmão do Quadro 10

**Interfaces:**
- Consumes: permissões do Quadro 10 com `subgrupo` preenchido e linha de grupo com subgrupo vazio
- Produces: resultado `nao_permitido` só quando o subgrupo específico é proibido; sem subgrupo e com mistura S/N na zona, resultado de análise, motivo citando que o subgrupo não foi identificado

- [ ] **Step 1: Write the failing test**

Zona ZPR 1. Subgrupo `nR1-01` permitido e `nR1-04` proibido. CNAE enquadrado só como grupo `nR1`, sem subgrupo.

```php
$this->assertNotSame('nao_permitido', $resultado->consolidado['resultado']);
$this->assertStringContainsString('subgrupo', $resultado->consolidado['motivo']);
```

Segundo caso: CNAE enquadrado em `nR1-04`. O motivo contém `nR1-04` e o resultado é `nao_permitido`.

- [ ] **Step 2: Run test to verify it fails**

Expected: FAIL — o motor indefere pelo grupo.

- [ ] **Step 3: Write minimal implementation**

Em `primeiraPermissaoQuadro10`, não cair na regra do grupo quando existirem subgrupos com permissões diferentes naquela zona. Sem subgrupo nesse caso, devolver pendente de análise com motivo explícito. Com subgrupo, a frase `louos.template.nao_permitido` recebe o subgrupo em `:grupo` (ou placeholder já existente `:subgrupo` se o template passar a incluí-lo via catálogo, sem trocar a frase padrão além de exibir o código).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Louos/LouosConsolidacaoTest.php`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git commit -m "fix: indeferimento do quadro 10 cita o subgrupo nR1"
```

---

## Self-review

- Spec coberta: Tasks 1–2 (motivo e resumo), 3 (camadas), 4 (confirmações), 5 (malha fina), 6 (precedentes), 7 (enquadramento e justificativa), 8 (tramitação e classificação), 9 (parecer), 10 (tela do processo), 11 (Quadro 10).
- Texto do catálogo e a HU-136 ficam de fora, como a spec manda.
- O cálculo de logradouros próximos não é apagado.
