---
name: sig-criar-cards
description: >
  Orquestra a criação e o preenchimento correto de cards (tasks e bugs) no SIG via MCP.
  Usar automaticamente quando o usuário pedir para criar um card, task, bug, requisito
  ou "abrir um card" no SIG, ou quando disser "criar card para a HU XX", "lançar bug",
  "abrir task de corretiva", "criar card de melhoria". Também acionar ao mencionar
  "Nº RF", "story points", "descrição do card", "critérios de aceite" no contexto SIG.
---

# Skill — Criação e Gestão de Cards no SIG

Segue o **Manual de Criação de Cards (v5, 24/07/2026)** da fábrica.
Toda interação com o SIG é feita via MCP `user-sig-dashboard`.

---

## 0. Contexto obrigatório antes de criar qualquer card

Antes de chamar `create_task`, verificar se o contexto está definido. Se não estiver:

```
sig_set_context(projectId, sprintId)
```

Obter os IDs com:
- `sig_projects` — lista projetos ativos
- `sig_sprints` — lista sprints do projeto

Se a sprint não for informada, o card vai para o **backlog** (sem vinculação a sprint).

---

## 1. Tipos de card (CAS_CAT — API real)

| Tipo | Quando criar | `categoria` em `create_task` |
|------|-------------|------------------------------|
| **Corretiva** | Erros em testes ou produção (bug). | `1` |
| **Melhoria / Inclusão** | TASK de HU/RF, novas funcionalidades. Limite de **420 minutos** por card. | `2` |
| **Elicitação** | Levantamento / refinamento de requisitos. | `3` |
| **Outras** | Sugestões, ajustes técnicos, usabilidade. | `4` (padrão) |

> Card TASK gerado pelo chatbot: acessar https://chatgpt.com/g/g-67db4af8bc78819196ba7da5b1d85e67-criador-de-cards-sig-oficial, enviar a HU em PDF e copiar o HTML gerado como `descricao`.

---

## 2. Fluxo completo de criação — passo a passo

### Passo 1 — Coletar informações do usuário

Perguntar (uma pergunta por vez se não fornecidas):
1. **Tipo de card**: TASK, BUG ou Outra atividade?
2. **Resumo** (título): curto, imperativo, técnico. Ex.: "Implementar validação de CPF na tela de cadastro".
3. **Nº RF / HU**: número da história de usuário (ex.: HU42). *Incluir no título ou descrição — sem campo direto no MCP.*
4. **Projeto**: nome ou ID (`sig_projects` para listar).
5. **Sprint**: nome ou ID (`sig_sprints` para listar). Omitir → backlog.
6. **Descrição** (HTML): texto completo com critérios de aceite, contexto e regras. Aceita HTML rico.
7. **Complexidade**: BAIXA, MEDIA ou ALTA.
8. **Story points**: número inteiro definido no planejamento.
9. **Tempo previsto** (minutos): preencher apenas se o usuário for responsável técnico; caso contrário, `0`.
10. **Prioridade**: 1=Baixa, 2=Média (padrão), 3=Alta, 4=Urgente.

Para card **Corretiva** (`categoria: 1`), coletar adicionalmente (campos MCP obrigatórios):
- `origemCorretiva`: `"T"` (Equipe de Teste) ou `"C"` (Cliente).
- `codCasoOrigemErro`: COD_CASO do RF onde o erro foi encontrado.
- `passosReproducao`: passos para reproduzir (também na descrição).
- Comportamento esperado vs. comportamento atual.

Antes de criar, listar épicos se o card for vinculado a um:
```
list_epics(projectId?)
```

### Passo 2 — Buscar regras de negócio na wiki (quando aplicável)

Antes de preencher a descrição, verificar se existe página de wiki com as regras da HU:

```
search_wiki(query: "HU42")
```

Se encontrar, citar o trecho relevante na descrição do card.

### Passo 3 — Criar o card

```
create_task(
  resumo:              "[HU42] Título curto e imperativo",
  descricao:           "<html descritivo com critérios de aceite>",
  codProjeto:          <ID do projeto>,
  codSprint:           <ID da sprint — omitir para backlog>,
  categoria:           <1|2|3|4 — ver tabela acima>,
  prioridade:          <1-4>,
  storyPoints:         <número>,
  complexidade:        "BAIXA" | "MEDIA" | "ALTA",
  codEpico:            <COD_EPICO opcional — list_epics>,
  # se categoria = 1 (Corretiva):
  origemCorretiva:     "T" | "C",
  codCasoOrigemErro:   <COD_CASO do RF de origem>,
  passosReproducao:    "<passos>"
)
```

Guardar o `caseId` retornado — usado em todos os passos seguintes.

### Passo 4 — Definir tempo previsto (se informado)

Se o responsável técnico informar o tempo previsto em minutos:

```
update_task(
  caseId:  <caseId>,
  tempoMax: <minutos>
)
```

> Se o usuário **não** for responsável técnico, deixar `0` (conforme manual).

### Passo 5 — Adicionar critérios de aceite como subtarefas

Para cada critério de aceite ou sub-entrega técnica definida:

```
add_subtask(caseId: <caseId>, descricao: "Critério: <texto>")
```

Cada subtarefa deve ser verificável e objetiva.

### Passo 6 — Registrar a HU / regra de negócio na wiki (obrigatório para RFs relevantes)

A wiki do SIG é o brain do projeto. Toda RF/HU relevante deve ter uma página de spec na wiki
vinculada ao card — esse vínculo garante rastreabilidade e alimenta o contexto do dev ao
pegar a task. Verificar primeiro se já existe; se existir, atualizar; se não existir, criar.

```
search_wiki(query: "HU42")   ← sempre verificar antes de criar
```

Se não existir página:

```
create_wiki_page(
  titulo:   "HU42 — <nome da funcionalidade>",
  conteudo: "<HTML com regras de negócio, critérios de aceite e contexto da HU>",
  escopo:   "caso",
  codCaso:  <caseId>,
  tipo:     "spec",
  icone:    "file-text"
)
```

Se já existir: chamar `update_wiki_page(pageId: <id>, conteudo: "<conteúdo atualizado>")`.

> O dev que pegar essa task via `sig_pick_task` vai ler a wiki como primeiro passo. Uma spec
> bem escrita aqui economiza horas de análise durante o desenvolvimento.

### Passo 7 — Vincular ao fluxo de trabalho

Após criar o card, o status inicial é **A Fazer**. Para que o dev possa pegar a task:

```
sig_workflow_start()   ← se não foi chamado ainda na sessão
sig_pick_task(caseId: <caseId>)   ← move para "Fazendo" e inicia timer
```

Se o card for criado mas ainda não puder ser iniciado, deixar em **A Fazer** — o workflow será acionado quando o dev pegar a task.

---

## 3. Mapeamento completo: campos do manual → MCP

| Campo do manual | Tool MCP | Parâmetro | Observação |
|----------------|----------|-----------|------------|
| Resumo/Título | `create_task` | `resumo` | Obrigatório |
| Descrição | `create_task` | `descricao` | HTML aceito; incluir Nº RF no início |
| Projeto | `create_task` | `codProjeto` | Usar `sig_projects` para obter ID |
| Versão / Sprint | `create_task` | `codSprint` | Usar `sig_sprints` para obter ID |
| Categoria | `create_task` | `categoria` | 1=Corretiva, 2=Melhoria/Inclusão, 3=Elicitação, 4=Outras |
| Épico | `create_task` / `update_task` | `codEpico` | `list_epics` / `create_epic` |
| Origem da Corretiva | `create_task` | `origemCorretiva` | `"T"` ou `"C"` (obrigatório se cat=1) |
| RF Origem da Corretiva | `create_task` | `codCasoOrigemErro` | COD_CASO do card pai (obrigatório se cat=1) |
| Passos reprodução | `create_task` | `passosReproducao` | CAS_PASSOS_REPR |
| Complexidade | `create_task` | `complexidade` | "BAIXA", "MEDIA" ou "ALTA" |
| Story Point | `create_task` | `storyPoints` | Número inteiro |
| Prioridade | `create_task` | `prioridade` | 1=Baixa, 2=Média, 3=Alta, 4=Urgente |
| T. Previsto (min) | `update_task` | `tempoMax` | 0 se não for responsável técnico |
| % Feito | `update_task` | `percentual` | 0-100, atualizar durante execução |
| Status / Coluna | `move_task` | `toColumn` | afazer, fazendo, review, teste, concluido |
| Solução Dada | `set_solution` | `solucao` | Preencher ao mover para review |
| Critérios de aceite | `add_subtask` | `descricao` | Um por subtarefa |
| Comentários | `add_comment` | `comentario` | Observações, comunicações |
| Evolução/Progresso | `add_evolution` | `descricao` + `percentual` | Registros de andamento |
| Regras da HU (wiki) | `create_wiki_page` | `conteudo` | Escopo "caso" vinculado ao caseId |
| Busca de regras | `search_wiki` | `query` | Antes de criar, verificar wiki existente |
| Impedimento | `report_impediment` | `motivo` | Quando houver bloqueio |

---

## 4. Campos SEM suporte direto no MCP (gaps restantes)

Os campos abaixo ainda **não** têm parâmetro dedicado nas tools. Usar o workaround:

| Campo do manual | Gap | Workaround |
|----------------|-----|------------|
| **Nº RF** (número da HU) | Sem campo dedicado | Incluir no início do `resumo`: `[HU42] Título...` |
| **RF Dependente** | Sem campo dedicado | Mencionar na `descricao`: `"Depende do card #123"` |
| **Início Previsto** | Sem campo de data no MCP | Mencionar na `descricao` ou em comentário |
| **Data Conclusão** | Sem campo de data no MCP | Mencionar na `descricao` ou em comentário |
| **PF Estimado** | Sem campo no card | Contagem APF nativa: `import_apf_json` / `create_apf_contagem`; ou `add_comment` |
| **Responsável do Requisito** | Sem campo de atribuição no MCP | Mencionar na `descricao` ou comentário |
| **Upload de imagens/vídeos** | `upload_attachment` aceita apenas texto plain | Orientar o usuário a anexar manualmente na interface web |

> **Já cobertos pelo MCP (não usar workaround):** Épico (`codEpico` / `list_epics`), Origem da Corretiva (`origemCorretiva`), RF Origem (`codCasoOrigemErro`), Passos (`passosReproducao`).

---

## 5. Descrição de qualidade — padrão obrigatório

Toda descrição deve conter as seguintes seções (em HTML ou markdown convertido):

```html
<h3>Contexto</h3>
<p>[Breve descrição do que precisa ser feito e por quê]</p>

<h3>Critérios de aceite</h3>
<ul>
  <li>[ ] Critério 1 — verificável e objetivo</li>
  <li>[ ] Critério 2</li>
</ul>

<h3>Regras de negócio</h3>
<p>[Regras da HU aplicáveis a este card]</p>

<h3>Observações técnicas</h3>
<p>[Referências, componentes, endpoints, dependências]</p>
```

Para cards de **BUG / Corretiva**, adicionar:

```html
<h3>Passos para reprodução</h3>
<ol>
  <li>Acessar [tela/funcionalidade]</li>
  <li>[Ação realizada]</li>
  <li>[Resultado observado]</li>
</ol>

<h3>Comportamento esperado</h3>
<p>[O que deveria acontecer]</p>

<h3>Ambiente</h3>
<p>Origem: Equipe de Teste | Cliente — RF de origem: #[nº do card]</p>
```

---

## 6. Regras de ouro e anti-padrões

### Regras obrigatórias

- **Limite de 420 minutos** por card TASK — se a estimativa for maior, quebrar em múltiplos cards.
- **Nº RF obrigatório** no resumo — toda task deve referenciar a HU de origem: `[HU42] Título`.
- **Buscar a wiki antes de preencher** — sempre chamar `search_wiki` para verificar regras de negócio existentes.
- **Subtarefas = critérios de aceite** — usar `add_subtask` para cada critério verificável.
- **Descrição em HTML** — o campo aceita HTML; usar para estruturar o conteúdo com `<h3>`, `<ul>`, `<li>`.
- **QA deve testar todos os cenários** antes de devolver o card — não devolver por erro parcial.
- **Verificar ambiente antes de criar bug crítico** — confirmar se o comportamento é do sistema ou do ambiente de testes.

### Anti-padrões proibidos

| Anti-padrão | Por que é errado | Ação correta |
|-------------|-----------------|--------------|
| Criar card sem Nº RF | Impossível rastrear a HU de origem | Sempre incluir `[HU42]` no resumo |
| Descrição vaga ("Corrigir bug da tela X") | Desenvolvimento não consegue reproduzir | Passos + comportamento esperado + atual |
| Criar card com mais de 420min de esforço | Quebre em múltiplos cards menores | Dividir por funcionalidade ou camada |
| Mover card para "review" sem preencher Solução Dada | Perde rastreabilidade da solução | Chamar `set_solution` antes de `move_task` |
| Bug criado sem verificar se é bug de ambiente | Cria impedimento desnecessário para o dev | Reproduzir em ambiente correto antes |
| Sugestão de QA sem alinhamento com analista | Gera retrabalho e conflito de escopo | Alinhar antes de criar o card |
| Card sem critérios de aceite | Ninguém sabe quando está pronto | Usar `add_subtask` para cada critério |

---

## 7. Checklist — card está pronto para "A Fazer"

Antes de considerar o card criado corretamente, verificar:

- [ ] Resumo contém `[HU42]` (Nº RF) e título imperativo e claro
- [ ] Descrição possui: Contexto, Critérios de aceite, Regras de negócio, Observações técnicas
- [ ] Para bug: Passos de reprodução, Comportamento esperado, Comportamento atual e Ambiente preenchidos
- [ ] Projeto e sprint (ou backlog) corretamente definidos
- [ ] Categoria correta (1 Corretiva / 2 Melhoria / 3 Elicitação / 4 Outras)
- [ ] Se corretiva: `origemCorretiva` + `codCasoOrigemErro` preenchidos
- [ ] Épico vinculado via `codEpico` quando aplicável
- [ ] Complexidade informada (BAIXA / MEDIA / ALTA)
- [ ] Story points definidos (se houver planejamento)
- [ ] Tempo previsto em minutos configurado (0 se não for responsável técnico)
- [ ] Pelo menos um critério de aceite cadastrado como subtarefa via `add_subtask`
- [ ] Wiki consultada (`search_wiki`) para verificar regras de negócio e spec existentes
- [ ] Página de spec criada ou atualizada na wiki para RFs relevantes (`escopo: "caso"`, `tipo: "spec"`)
- [ ] `caseId` registrado para uso subsequente no workflow

---

## 8. Integração com o workflow pós-criação

Após criar o card, seguir o fluxo de trabalho do SIG:

```
Card criado (A Fazer)
        ↓
sig_pick_task(caseId)      ← dev inicia; move para "Fazendo", play timer
        ↓
[dev trabalha; sig_sync_progress a cada marco]
        ↓
sig_finish_coding(solucao) ← dev finaliza; move para "Review", troca timer
        ↓
sig_pass_review(resumo)    ← review aprovado; move para "Teste", encerra sessão
        ↓
[QA assume — dev não atua mais]
```

Para cards criados mas não iniciados imediatamente, o card permanece em **A Fazer** e será pego via `sig_pick_task` quando o dev estiver disponível.

---

## 9. Exemplos de chamadas MCP

### Criar task de melhoria/inclusão

```
list_epics(projectId: 42)

create_task(
  resumo:       "[HU15] Implementar filtro de data no relatório de vendas",
  descricao:    "<h3>Contexto</h3><p>...</p><h3>Critérios de aceite</h3><ul><li>[ ] Filtro por período</li></ul>",
  codProjeto:   42,
  codSprint:    17,
  categoria:    2,
  prioridade:   2,
  storyPoints:  5,
  complexidade: "MEDIA",
  codEpico:     10
)
→ caseId: 891

update_task(caseId: 891, tempoMax: 240)

add_subtask(caseId: 891, descricao: "Filtro por data inicial e data final funciona corretamente")
add_subtask(caseId: 891, descricao: "Relatório respeita o período selecionado")
```

### Criar card de corretiva

```
create_task(
  resumo:              "[HU08] BUG — Botão Salvar não dispara validação na tela de cadastro",
  descricao:           "<h3>Passos para reprodução</h3><ol>...</ol><h3>Comportamento esperado</h3>...",
  codProjeto:          42,
  codSprint:           17,
  categoria:           1,
  prioridade:          3,
  complexidade:        "BAIXA",
  origemCorretiva:     "T",
  codCasoOrigemErro:   456,
  passosReproducao:    "1. Acessar Cadastro\n2. Deixar CPF em branco\n3. Clicar em Salvar"
)
→ caseId: 892

add_subtask(caseId: 892, descricao: "Validação de CPF é executada ao clicar em Salvar")
```

---

## 10. Referências

- Manual de Criação de Cards SIG v5 (24/07/2026) — fonte de verdade deste playbook
- Workflow do SIG: `.cursor/rules/sig-mcp-workflow.mdc`
- Skill de desenvolvimento: `.agents/skills/sig-dev/SKILL.md`
- Chatbot de geração automática: https://chatgpt.com/g/g-67db4af8bc78819196ba7da5b1d85e67-criador-de-cards-sig-oficial
- Wiki do projeto (regras de negócio): acessar via `search_wiki` antes de criar cards
