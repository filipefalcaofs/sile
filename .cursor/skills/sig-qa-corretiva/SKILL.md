---
name: sig-qa-corretiva
description: >
  QA no SIG: gera prévia documental de card de corretiva, cadastra via MCP após
  autorização explícita, vincula RF de origem e move cards no reteste. Usar quando
  a QA documentar erros, pedir corretiva, reteste ou "abrir bug no SIG".
---

# Skill: QA — Criação de Cards de Corretiva no SIG

## Quando usar esta skill

Usar automaticamente sempre que a QA (ou o agente de IA atuando como QA) precisar:

- Documentar erros encontrados durante ciclo de testes e gerar a **prévia** do card de corretiva;
- **Após autorização explícita** da QA responsável: criar o card de corretiva real no SIG via MCP;
- Vincular o card corretivo ao card pai (HU de origem) via campos nativos MCP;
- Registrar subtarefas por Task distinto;
- Registrar evolução `TESTE CONCLUIDO` na fase de execução;
- Registrar impedimento `Card: #N` no card pai somente na fase autorizada;
- Mover cards conforme fluxo de reteste.

Durante qualquer execução em Teste, ativar também a skill `sig-telemetry` para
observabilidade contínua. A telemetria não autoriza cadastro de corretiva nem move colunas.

> Esta skill é específica de QA/corretiva. Criação genérica de cards: skill `sig-criar-cards`.

---

## Fluxo obrigatório (5 fases)

```
FASE 1 — Consolidação dos testes
  ↓
FASE 2 — Geração da prévia documental (este template)
  ↓
FASE 3 — Revisão e AUTORIZAÇÃO EXPLÍCITA da QA responsável
  ↓
FASE 4 — Cadastro no SIG via MCP (somente após autorização)
  ↓
FASE 5 — Movimentação e impedimento conforme regras de reteste
```

**NUNCA** avançar da Fase 2 para a Fase 4 sem a autorização da Fase 3.

**Gate humano (modo autônomo do kit SIG):** mesmo com o agent Dev 100% autônomo até Teste,
cadastro de corretiva e Teste→Concluído **continuam exigindo autorização humana explícita**.

---

## Fase 1 — Consolidação dos testes (pré-requisito)

Antes de iniciar o preenchimento do template:

1. Confirmar que todos os CTs da tela foram executados.
2. Atualizar o Documento de Erros com os erros identificados.
3. Atualizar o Documento de Evidências de Teste com prints/vídeos/logs.
4. Cruzar o comportamento observado com HU, regra de negócio, critério de aceite e docs oficiais.
5. Confirmar que cada erro é funcional e reproduzível antes de incluir no card.

**Não criar corretiva para:** dúvida, premissa, requisito ausente, indisponibilidade de ambiente sem defeito, credencial/massa incorreta, cenário `NÃO APLICÁVEL` justificado, observação sem erro funcional.

---

## Fase 2 — Geração da prévia documental

Preencher o template em `templates/card-corretiva.md` com as regras abaixo.

### Regras de estrutura

- **Uma Task = um erro distinto.** Não misturar erros diferentes na mesma Task.
- **Máximo 6 Tasks por card.** Se houver mais de 6: parar e aguardar decisão humana.
- Numeração sequencial: `Task 1`, `Task 2`, …
- Cada Task: descrição objetiva + passos para reprodução + critério de aceite em Gherkin.

### Regras de conteúdo

- Preencher todos os campos entre `[ ]`. Quando a informação não existir: `[NÃO INFORMADO]`.
- Nunca inventar IDs, números de card, links ou dados do SIG.
- Nunca incluir senha, token, cookie, dado de sessão ou CPF/nome real não mascarado.
- Marcar `Status da prévia` como `Aguardando revisão da QA responsável`.
- Marcar `Autorização para cadastrar` como `[NÃO INFORMADO]` até receber autorização.

### Título do card (RESUMO)

```
[HU ou Nome do Projeto] Caminho: [Caminho exato até a tela] ([resumo dos erros em poucas palavras])
```

---

## Fase 3 — Autorização

- Apresentar a prévia à QA responsável.
- Aguardar confirmação explícita: "pode cadastrar", "autorizado", "ok para criar" ou equivalente.
- Registrar quem autorizou e quando no template.
- Conferir duplicidade no SIG.
- Somente após esses passos avançar para a Fase 4.

---

## Fase 4 — Cadastro no SIG via MCP

### CAS_CAT e campos nativos (API real)

| Campo | Valor | Parâmetro MCP |
|---|---|---|
| Categoria | Corretiva | `categoria: 1` |
| Origem | Equipe de Teste / Cliente | `origemCorretiva: "T"` ou `"C"` |
| RF Origem da Corretiva | COD_CASO do card pai | `codCasoOrigemErro: <ID_PAI>` |
| Passos | Texto dos passos | `passosReproducao` |
| Épico (opcional) | COD_EPICO | `codEpico` — via `list_epics` |
| Prioridade | ver tabela abaixo | `prioridade` |
| Tempo / SP | 0 se não informado | `storyPoints: 0` |

> **Não usar workarounds de descrição** para origem/RF — a tool `create_task` já envia `CAS_ORIGEM_CORRETIVA` e `COD_CASO_ORIGEM_ERRO`. Comentário `"Corretiva do card: #[ID_PAI]"` continua útil para rastreabilidade humana.

### Mapeamento de tools MCP

| Ação | Tool MCP | Parâmetros obrigatórios |
|---|---|---|
| Listar épicos (se vínculo) | `list_epics` | `projectId?` |
| Criar card corretivo | `create_task` | `resumo`, `descricao`, `categoria: 1`, `origemCorretiva`, `codCasoOrigemErro`, `prioridade`, `codProjeto`, `codSprint?` |
| Adicionar cada Task | `add_subtask` | `caseId`, `descricao` |
| Comentário de rastreabilidade | `add_comment` | `caseId`, `comentario: "Corretiva do card: #[ID_PAI]"` |
| Anexar evidência em texto | `upload_attachment` | `caseId`, `nomeArquivo`, `conteudo` |

### Mapeamento de prioridade

| Texto da prévia | Código MCP |
|---|---|
| Urgente | `prioridade: 4` |
| Alta | `prioridade: 3` |
| Média | `prioridade: 2` |
| Baixa | `prioridade: 1` |

### Sequência de cadastro

```
1. create_task(
     resumo, descricao, categoria: 1,
     origemCorretiva: "T"|"C",
     codCasoOrigemErro: <ID_PAI>,
     passosReproducao, prioridade, codProjeto, codSprint?, codEpico?
   ) → caseId real
2. add_comment → "Corretiva do card: #[ID_PAI]"
3. add_subtask × N (máx. 6)
4. upload_attachment (se aplicável)
5. add_comment → CT(s), build, ambiente
```

### Regras de cadastro

- Nunca deduzir o `caseId`: usar **exclusivamente** o valor retornado por `create_task`.
- Nunca cadastrar antes da autorização da Fase 3.
- Se `create_task` falhar (ex.: falta origem): reportar o erro, não tentar burlar com categoria ≠ 1.

---

## Fase 5 — Movimentação, evolução e impedimento (regras de reteste)

Carregar também `.agents/skills/sig-telemetry/SKILL.md` durante a execução em Teste
(sessão, activity, test_result, error, blocked). Telemetria **não** move colunas.

### Play na coluna Teste

Com o card pai em **Teste**, a QA (perfil QA) dá **PLAY** no card/timer da coluna com:

```text
start_work({ caseId: [ID_PAI] })
```

Ao pausar o teste, usar `pause_work({ caseId: [ID_PAI], ... })`. O agente de QA não usa o
workflow composto de dev (`sig_pick_task` / `sig_finish_coding`) para “concluir” o ciclo — o
fluxo de QA começa em Teste.

### Caminho feliz (teste OK)

**somente perfil QA** pode executar:

```text
move_task(caseId: [ID_PAI], toColumn: "concluido")
```

Também registrar evolução de fechamento, por exemplo:

```text
add_evolution(caseId: [ID_PAI], descricao: "TESTE CONCLUIDO — aprovado", percentual: 100, usouIA: false)
```

**Proibido** para agente/dev sem perfil QA mover Teste → Concluído.

### Caminho com erro (ordem obrigatória — opção A)

1. Detectar erro funcional reproduzível (Fases 1–2).
2. Gerar prévia e obter **autorização explícita** (Fases 2–3).
3. **Criar card de corretiva** (Fase 4) — obter `NUMERO_REAL_CORRETIVO`.
4. **só então:**
   - `move_task(caseId: [ID_PAI], toColumn: "review")`
   - `report_impediment(caseId: [ID_PAI], motivo: "Card: #[NUMERO_REAL_CORRETIVO]")`
5. Card corretivo permanece em **A Fazer** (ou coluna inicial padrão do create).

Não registrar impedimento no pai **antes** da corretiva existir.
Não mover o pai para Review antes do passo 3 concluir com sucesso.

### Durante a execução (antes do veredito)

Emitir telemetria (`activity`, `test_result`, etc.). Evoluções genéricas de andamento de teste
são permitidas; **não** usar ainda o impedimento `Card: #N`.

### Reteste após correção

| Card | Ação |
|---|---|
| Corretivo | Reteste OK → `move_task → concluido` (QA); reprova → manter/voltar conforme padrão |
| Pai | Todas corretivas OK → resolver impedimentos → `move_task → teste` (ou fluxo da sprint) → novo ciclo de play |

### Regra de `pause_work`

```
pause_work(caseId, evolucao, percentual?, usouIA?: false, iaUtilizada?)
```

Com `usouIA: true`, `iaUtilizada` (IUC_COD) é obrigatório.

---

## Checklist de validação (antes de apresentar a prévia)

- [ ] Todos os CTs da tela foram executados
- [ ] Documento de Erros e Evidências atualizados
- [ ] Cada Task = um erro distinto; máx. 6
- [ ] Nenhum campo `[ ]` vazio — usar `[NÃO INFORMADO]` se necessário
- [ ] Dados pessoais/senhas mascarados
- [ ] IDs não inventados
- [ ] Título no formato `[Projeto] Caminho: ... (resumo)`
- [ ] Status da prévia = `Aguardando revisão da QA responsável`

## Checklist pós-cadastro

- [ ] Número do card corretivo no Documento de Erros
- [ ] `categoria: 1` + `origemCorretiva` + `codCasoOrigemErro` enviados
- [ ] Comentário `Corretiva do card: #[ID_PAI]` registrado
- [ ] Subtarefas coincidem com as Tasks do template
- [ ] Impedimento `Card: #[número real]` no pai (se a fase exigir)

---

## Referência ao template

```
.agents/skills/sig-qa-corretiva/templates/card-corretiva.md
```

Usar como base da prévia. Não modificar o template original; criar cópia preenchida por card.

---

## Idioma e gramática

Todo conteúdo em **português brasileiro (pt-BR)** com acentuação correta — título, descrição, subtarefas, comentários e evoluções.
