---
name: sig-dev
description: >
  Executor autônomo de desenvolvimento no SIG via MCP. Auto-escolhe/retoma task, implementa,
  telemetria contínua, avança com sig_finish_coding e sig_pass_review até Teste sem pedir
  "terminei" ou "review ok". Usar automaticamente em qualquer trabalho de sprint/card SIG.
  Carregar também sig-telemetry.
---

# Skill — Executor Autônomo Dev no SIG

O agente é **autônomo por padrão**: escolhe ou retoma a task, trabalha, registra telemetria e
avança o kanban até **Teste** sem pedidos conversacionais. O usuário não precisa dizer
“registre”, “mova”, “terminei” ou “review ok”.

Toda interação com o SIG é feita via MCP `user-sig-dashboard`.

**Gates humanos (fora desta skill):** autorização de corretiva e Teste→Concluído (QA).

---

## 1. Papel e responsabilidade

O agente nesta skill é responsável por:

- Inicializar o workflow e **auto-selecionar ou retomar** a task (sem perguntar se houver elegível)
- Implementar e avançar sozinho até Teste quando o checklist estiver verde
- Registrar **marcos de entrega** via `sig_sync_progress` (commit, feature parcial)
- Manter a wiki do projeto atualizada com especificações e decisões técnicas
- Documentar a solução, os arquivos tocados e os commits no encerramento
- Garantir que a **coluna kanban** reflita o estado real do trabalho
- **Carregar e obedecer `sig-telemetry`** em todo turno com task ativa

**Observabilidade contínua** fica na skill `.agents/skills/sig-telemetry/SKILL.md`.
Esta skill **não** substitui a telemetria fina; evita duplicar a mesma frase:
- `sig-dev` → evoluções de entrega (`progress` / `sig_sync_progress` descritivo)
- `sig-telemetry` → eventos com prefixos `[activity]`, `[file]`, `[cmd]`, `[test]`, `[error]`
  via `add_evolution` (ou `log_work` quando mapeado), nunca `sig_sync_progress`

**Fonte de verdade para regras de negócio:** a wiki do projeto no SIG. Antes de qualquer
implementação, consultar com `search_wiki` e `list_wiki_pages`. O que não estiver na wiki deve
ser documentado durante o desenvolvimento.

---

## 2. Fronteiras — o que esta skill NÃO faz

| Escopo | Skill responsável |
|--------|-------------------|
| Observabilidade contínua (sessão, eventos, heartbeat) | `.agents/skills/sig-telemetry/SKILL.md` |
| Criar cards (tasks, bugs, HUs) | `.agents/skills/sig-criar-cards/SKILL.md` |
| Testar e abrir corretivas | `.agents/skills/sig-qa-corretiva/SKILL.md` |
| Mover card de "teste" para "concluído" | **Proibido nesta skill** — só perfil QA (`sig-qa-corretiva`) |
| Qualquer ação pós-"teste" | Fora do escopo — o dev não atua após "teste" |

Esta skill cobre exclusivamente o ciclo **A Fazer → Fazendo → Review → Teste**.

---

## 3. Kanban do Dev — estados e transições

```
A Fazer
   ↓  sig_pick_task()          ← play timer "fazendo" + abre sessão IA
Fazendo
   ↓  sig_finish_coding()      ← pause "fazendo", play "review", define solução
Review
   ↓  sig_pass_review()        ← pause "review", move para "teste", encerra sessão IA
Teste                          ← QA assume; dev não atua mais
```

**Regra absoluta:** NUNCA chamar `move_task` diretamente enquanto o workflow estiver ativo.
Usar sempre as tools compostas (`sig_pick_task`, `sig_finish_coding`, `sig_pass_review`).
O MCP gerencia play/pause automaticamente — o dev não precisa pensar nisso.

---

## 4. Fluxo MCP composto — autonomia e sinais

### 4.0 Loop autônomo (padrão — executar sem pedir)

```text
sig_workflow_start
→ sig_workflow_status
→ se task ativa: retomar
→ senão: auto-pick entre "A Fazer" do usuário (maior prioridade; empate → mais antiga)
→ se zero elegíveis: informar e PARAR (não inventar card)
→ sig_pick_task
→ trabalhar + telemetria + sig_sync_progress em marcos
→ checklist §9 verde → sig_finish_coding
→ checklist §10 verde → sig_pass_review → Teste
```

**Proibido:** perguntar “qual task?” quando existir elegível; esperar “terminei” / “review ok”
para avançar se o checklist já estiver verde.

### 4.1 Sinais opcionais (humano pode interferir; não são obrigatórios para avançar)

| Sinal | Ferramenta MCP | Observações |
|---|---|---|
| Início de conversa / sessão nova | `sig_workflow_start` + auto-pick | Não perguntar task se houver elegível |
| Dúvida sobre estado | `sig_workflow_status` | — |
| Usuário força ID/descrição | `sig_pick_task` | Sobrescreve auto-pick |
| Commit / marco | `sig_sync_progress` | Também disparar sem o usuário pedir |
| Checklist §9 verde | `sig_finish_coding` | **Automático** — não esperar “terminei” |
| Checklist §10 verde | `sig_pass_review` | **Automático** — não esperar “review ok” |
| “vou parar” / pausa explícita | `sig_pause_current` | Só se o usuário pedir |
| “voltei” / retomar | `sig_resume_current` | — |
| Trocar de task (pedido explícito) | `sig_pause_current` + `sig_pick_task` | — |

### 4.2 Inicialização de sessão

```
sig_workflow_start()
```

Em seguida `sig_workflow_status`. Se não houver task ativa, executar auto-pick (§4.3).
Informar ao usuário **qual** task foi escolhida (transparência), sem pedir confirmação
quando a regra de auto-pick for aplicável.

### 4.3 Seleção de task (auto-pick)

Ordem obrigatória:

1. Task já ativa no workflow → retomar (`caseId` atual).  
2. Senão, candidatas em **A Fazer** atribuídas ao usuário logado.  
3. Ordenar por **maior prioridade**; empate → **mais antiga**.  
4. `sig_pick_task(caseId: <escolhida>)`.  
5. Se zero candidatas → informar e parar (não criar card sozinho).

Override: se o usuário informar ID/descrição explicitamente, usar esse alvo.

```
sig_pick_task(caseId: <número>)
sig_pick_task(descricao: "<texto>")   ← só override humano ou busca
```

Parâmetro opcional `ferramenta`: `"cursor"` | `"claude_code"` | `"codex"` | etc.

### 4.4 Registro de progresso

```
sig_sync_progress(
  evolucao:     "<descrição do que foi feito>",
  percentual:   <0-100>,
  usouIA:       true | false,          ← padrão false se omitido
  iaUtilizada:  <IUC_COD numérico>     ← obrigatório se usouIA=true
)
```

Chamar a cada marco real: commit, funcionalidade parcial concluída, bug resolvido, ou a cada
30–60 minutos. Não chamar a cada linha de código.

Além dos marcos acima, a skill `sig-telemetry` registra atividades/arquivos/comandos/testes
continuamente com `add_evolution` (ou `log_work` quando mapeado). Não usar
`sig_sync_progress` para micro-eventos de telemetria.

**IA e evoluções (API real):**
- `usouIA: true` **exige** `iaUtilizada` = `IUC_COD` numérico (cadastro `SUP_IA_UTILIZADA_CASO`).
- Sem IA: omitir ou `usouIA: false` (padrão seguro — não quebra a API).
- Opcional: `SIG_DEFAULT_IA_COD` no env do MCP, ou `iaUtilizada` em `sig_pick_task` para a sessão.
- Mesmos parâmetros em `sig_finish_coding`, `sig_pass_review`, `sig_pause_current`,
  `pause_work`, `move_task` e `add_evolution`.

### 4.5 Pausas e retomadas

```
sig_pause_current(motivo: "<motivo opcional>")   ← pausa timer; registra evolução se motivo
sig_resume_current()                              ← retoma timer na coluna atual
```

Ao pausar, registrar progresso parcial descritivo no `motivo` — nunca pausar sem contexto.

### 4.6 Finalizar desenvolvimento

```
sig_finish_coding(
  solucao: "<descrição técnica completa do que foi implementado>",
  resumo:  "<resumo opcional do trabalho>"
)
```

O MCP automaticamente:
1. Pausa o timer de "fazendo"
2. Define a solução técnica no card (`/solution`)
3. Registra evolução com percentual 80
4. Move para "review"
5. Inicia o timer de "review"

### 4.7 Review aprovado (último passo do dev)

```
sig_pass_review(
  resumo:          "<resumo final do trabalho>",
  arquivosTocados: ["<arquivo1>", "<arquivo2>", ...],
  commits:         ["<hash1>", "<hash2>", ...],
  observacao:      "<observação do review, se houver>"
)
```

O MCP automaticamente:
1. Pausa o timer de "review"
2. Registra evolução com percentual 100
3. Move para "teste"
4. Encerra a sessão IA (finaliza com `arquivosTocados` e `commits`)
5. Limpa o estado do workflow

A partir deste ponto, o QA assume. O dev não atua mais nessa task.

---

## 5. Evolução — padrão de qualidade

Toda evolução deve ser descritiva o suficiente para que alguém que nunca viu o card entenda
o que foi feito. Evitar textos genéricos como "progresso" ou "trabalhando".

**Bom:**
```
"Implementado endpoint POST /contagem-apf com validação de funcionalidade e persistência
nas tabelas SUP_APF_CONTAGEM e SUP_APF_FUNCAO. Testes unitários passando."
```

**Ruim:**
```
"Progresso feito"
```

**Percentual sugerido por estágio:**
- Início / análise: 5–15%
- Primeiro commit: 20–35%
- Funcionalidade parcial: 40–60%
- Funcionalidade completa sem testes: 70%
- Com testes: 75–80%
- `sig_finish_coding` move automaticamente para 80%
- `sig_pass_review` move automaticamente para 100%

---

## 6. Wiki = brain do projeto

A wiki do SIG é a **documentação viva e completa** do projeto — não apenas notas soltas de
task. É o repositório institucional de conhecimento: regras de negócio, decisões de
arquitetura, histórico de sprints e especificações de cada funcionalidade. O agente deve
tratá-la como **fonte de verdade** e mantê-la atualizada como parte obrigatória do "done".

### 6.1 Três escopos de página

#### Escopo `projeto` — memória institucional permanente

Páginas de nível de projeto vivem para além das sprints. Criar/atualizar quando houver
mudanças estruturais no sistema ou no processo:

| Tipo de conteúdo | `tipo` | Exemplos de título |
|---|---|---|
| Visão geral, objetivo e stack | `"pagina"` | "Visão do Projeto", "Stack Técnica" |
| Arquitetura e integrações | `"pagina"` | "Arquitetura de Serviços", "Integrações Externas" |
| Glossário de termos do domínio | `"pagina"` | "Glossário — Termos de APF" |
| Decisões de arquitetura (ADR) | `"decisao"` | "ADR: Escolha de MSSQL vs PostgreSQL" |
| Runbooks operacionais | `"runbook"` | "Deploy no Portainer", "Rollback de Migração" |
| Releases e changelog | `"pagina"` | "Release v2.1 — O que mudou" |
| Contagem de Pontos de Função | `"pagina"` | "APF — Sprint 12 — Total 42 PF" |

```
list_wiki_pages(escopo: "projeto")
create_wiki_page(titulo: "...", conteudo: "...", escopo: "projeto", tipo: "decisao")
```

#### Escopo `sprint` — memória da iteração

Cada sprint deve ter ao menos uma página que documente o que foi planejado, o que foi
entregue e as lições aprendidas. Documentar projeto e sprint é **parte do "done"** —
não é opcional.

| Tipo de conteúdo | `tipo` | Exemplos de título |
|---|---|---|
| Objetivo e escopo da sprint | `"pagina"` | "Sprint 12 — Objetivo e Escopo" |
| Riscos e dependências identificadas | `"pagina"` | "Sprint 12 — Riscos" |
| Notas de planning / refinamento | `"pagina"` | "Sprint 12 — Notas de Planning" |
| O que foi entregue (resumo de retro) | `"retro"` | "Sprint 12 — Retrospectiva" |

```
list_wiki_pages(escopo: "sprint")
create_wiki_page(
  titulo:  "Sprint 12 — Retrospectiva",
  conteudo: "...",
  escopo:  "sprint",
  tipo:    "retro"
)
```

Atualizar a página de sprint sempre que um marco relevante for concluído dentro da sprint.

#### Escopo `caso` — spec e solução da task

Cada funcionalidade relevante deve ter sua própria página vinculada ao card:

| Tipo de conteúdo | `tipo` | Exemplos de título |
|---|---|---|
| Especificação da funcionalidade | `"spec"` | "HU42 — Filtro de Data no Relatório" |
| Solução técnica implementada | `"solucao"` | "HU42 — Solução: endpoint + cache" |
| Decisão técnica específica da task | `"decisao"` | "HU42 — Decisão: paginação no backend" |

```
create_wiki_page(
  titulo:   "HU42 — <Nome da Funcionalidade>",
  conteudo: "<HTML com spec, decisões, APIs>",
  escopo:   "caso",
  codCaso:  <caseId>,
  tipo:     "spec",
  icone:    "file-text"
)
```

### 6.2 Tipos de página disponíveis

| `tipo` | Uso |
|---|---|
| `"pagina"` | Documentação geral, visão, stack, glossário, releases |
| `"spec"` | Especificação de funcionalidade (o quê e por quê) |
| `"solucao"` | Solução técnica implementada (como foi feito) |
| `"decisao"` | ADR ou decisão técnica justificada |
| `"runbook"` | Procedimento operacional passo a passo |
| `"retro"` | Retrospectiva ou resumo de entregáveis de sprint |

### 6.3 Tools da wiki

```
list_wiki_pages(escopo: "projeto" | "sprint" | "caso")
search_wiki(query: "<termo, HU, funcionalidade>")

create_wiki_page(
  titulo:   "<título>",
  conteudo: "<HTML rico>",
  escopo:   "projeto" | "sprint" | "caso",
  codCaso:  <caseId — obrigatório quando escopo "caso">,
  tipo:     "pagina" | "spec" | "solucao" | "decisao" | "runbook" | "retro",
  icone:    "file-text"
)

update_wiki_page(
  pageId:   <id da página existente>,
  conteudo: "<conteúdo atualizado>"
)
```

**Regra de ouro:** antes de `create_wiki_page`, sempre chamar `search_wiki` para verificar
se a página já existe. Se existir, usar `update_wiki_page` em vez de criar duplicata.

### 6.4 Quando ler a wiki

| Momento | Ação obrigatória |
|---|---|
| Ao pegar uma task (`sig_pick_task`) | `search_wiki(query: "<HU ou funcionalidade>")` + `list_wiki_pages(escopo: "caso")` |
| Ao responder dúvida de regra de negócio | `search_wiki` antes de qualquer resposta |
| Ao início de sessão nova | `list_wiki_pages(escopo: "projeto")` para carregar contexto geral |

Se a regra de negócio relevante não existir na wiki, perguntar ao dev antes de assumir.

### 6.5 Quando atualizar a wiki

| Marco no workflow | Escopo a atualizar | O que registrar |
|---|---|---|
| `sig_sync_progress` com funcionalidade significativa | `caso` | Spec parcial ou decisão técnica tomada |
| `sig_finish_coding` | `caso` | Solução técnica final implementada |
| `sig_pass_review` | `caso` + `sprint` (se houver mudança de escopo/entrega) | Finalizar solução; atualizar resumo de sprint |
| Decisão de arquitetura tomada | `projeto` | ADR com contexto, decisão e consequências |
| Sprint concluída / retrospectiva | `sprint` | Retrospectiva: o que foi entregue, impedimentos, lições |
| Release publicada | `projeto` | Registro de release e changelog técnico |
| Contagem APF gerada | `projeto` + `caso` | PF estimado/contado vinculado à sprint e ao card |

### 6.6 Conteúdo mínimo por tipo de página

**Spec de caso** (`tipo: "spec"`, `escopo: "caso"`):
- Descrição da funcionalidade e regra de negócio
- Critérios de aceite (o que define "pronto")
- APIs criadas ou modificadas (endpoint, parâmetros, retorno)
- Comportamentos de borda e tratamentos de erro
- Dependências entre funcionalidades

**Solução de caso** (`tipo: "solucao"`, `escopo: "caso"`):
- Como foi implementado (arquivos, camadas, padrões usados)
- Commits relevantes
- Considerações de performance ou segurança, se houver

**Decisão de projeto** (`tipo: "decisao"`, `escopo: "projeto"`):
- Contexto (por que a decisão foi necessária)
- Opções consideradas com trade-offs
- Decisão tomada e justificativa
- Consequências e próximos passos

**Retrospectiva de sprint** (`tipo: "retro"`, `escopo: "sprint"`):
- Objetivo da sprint e o que foi acordado no planning
- O que foi entregue (lista de HUs/cards concluídos)
- Impedimentos encontrados e como foram resolvidos
- Lições aprendidas e pontos de melhoria

### 6.7 Gap documentado — `sig_ensure_project_brain`

A API atual não possui uma tool para garantir a existência da árvore padrão de páginas da
wiki (ex.: verificar se "Visão do Projeto", "Stack Técnica" e "Sprint N — Objetivo" existem
e criá-las automaticamente quando não existirem).

**Gap futuro:** implementar `sig_ensure_project_brain(projectId, sprintId)` que cria as
páginas de estrutura mínima ausentes e retorna os IDs para uso imediato.

Enquanto essa tool não existir: ao iniciar trabalho em projeto sem wiki estruturada, criar
manualmente as páginas de projeto e sprint via `create_wiki_page` antes de pegar a primeira
task.

---

## 7. Alimentar o card durante o desenvolvimento

Além das evoluções automáticas do workflow composto, o agente deve alimentar o card com
informações qualitativas.

### 7.1 Comentários

```
add_comment(
  caseId:     <caseId>,
  comentario: "<observação, comunicação, decisão ou contexto>"
)
```

Usar para: comunicar bloqueios ao PO, registrar decisões tomadas em conversa, informar
dependências identificadas durante o desenvolvimento.

### 7.2 Subtarefas

```
toggle_subtask(caseId: <caseId>, subtaskId: <id>)
```

Marcar subtarefas como concluídas à medida que os critérios de aceite forem atendidos.
Verificar o estado atual das subtarefas antes de `sig_finish_coding`.

### 7.3 Solução técnica

`sig_finish_coding` define a solução automaticamente. Se precisar atualizar antes:

```
set_solution(caseId: <caseId>, solucao: "<descrição técnica>")
```

### 7.4 Impedimentos

```
report_impediment(caseId: <caseId>, motivo: "<descrição do bloqueio>")
resolve_impediment(caseId: <caseId>, impedimentId: <id>)
```

Registrar impedimento imediatamente ao identificar um bloqueio real (dependência externa,
ausência de dado de teste, acesso negado). Resolver assim que o bloqueio for removido.

---

## 8. APF nativa — Contagem de Pontos de Função

**Caminho principal:** tools nativas MCP (tabelas `SUP_APF_*`). Não usar `sig_apf` legado
como fluxo padrão.

| Ação | Tool |
|------|------|
| Listar contagens do projeto | `list_apf_contagens(projectId?, sprintId?)` |
| Detalhe (grupos/itens IFPUG) | `get_apf_contagem(contagemId)` |
| Criar contagem vazia | `create_apf_contagem(projectId?, sprintId?, aplicacao?, …)` |
| Importar JSON da skill | `import_apf_json(json, projectId?, sprintId?, contagemId?)` |

Fluxo típico:
1. Gerar JSON com a skill `contagem-ponto-funcao`.
2. `import_apf_json(json: <objeto>, projectId, sprintId?)`.
3. Documentar na wiki (`escopo: projeto` e/ou `caso`) o total de PF.

Frontend: `/contagem-apf` (`ContagemApfPage.vue`).

### Épicos

```
list_epics(projectId?)
create_epic(nome, descricao?, projectId?)
update_epic(epicId, nome, descricao?, projectId?)
create_task(..., codEpico: <COD_EPICO>)
```

---

## 9. Checklist — antes de `sig_finish_coding` (automático)

Quando estes itens estiverem verdes, chamar `sig_finish_coding` **sem** esperar
o usuário dizer “terminei” / “pronto”:

- [ ] Todos os critérios de aceite do card foram atendidos (subtarefas marcadas)
- [ ] Testes unitários e de integração relevantes escritos e passando
- [ ] Build sem erros (`npm run build` ou equivalente)
- [ ] Evoluções registram o que foi feito com percentual coerente
- [ ] Wiki do **caso** atualizada com spec e solução técnica implementada (`escopo: "caso"`)
- [ ] Se houve decisão de arquitetura: página criada/atualizada no escopo `projeto`
- [ ] Impedimentos abertos foram resolvidos ou comentados
- [ ] Nenhum `console.log` de debug, segredo ou dado sensível no código
- [ ] Lints sem erros nos arquivos tocados
- [ ] `sig_workflow_status` confirma task ativa em "fazendo"

---

## 10. Checklist — antes de `sig_pass_review` (automático)

Quando §9 estiver verde e a solução definida, executar este checklist e, se OK,
chamar `sig_pass_review` **sem** esperar “review ok” / “pode testar”:

- [ ] Revisão própria do diff concluída (agente atua como revisor)
- [ ] Lista de `arquivosTocados` preparada (`git diff --name-only`)
- [ ] Lista de `commits` preparada (`git log` / hashes da sessão)
- [ ] `resumo` descreve o trabalho completo, não apenas o último commit
- [ ] Card não tem impedimentos abertos
- [ ] Wiki do **caso** reflete o estado final (spec + solução)
- [ ] Se a entrega mudou o escopo da sprint: página de sprint atualizada

---

## 11. Sinais do usuário (opcionais — não bloqueiam autonomia)

| Sinal | Ação |
|---|---|
| Override de task (“pega o #123”) | `sig_pick_task` nesse alvo |
| Pausa explícita | `sig_pause_current` |
| Retomada explícita | `sig_resume_current` |
| Pedido de estado | `sig_workflow_status` |
| Bloqueio real | `report_impediment` |
| Dúvida de regra de negócio | `search_wiki` |

Frases como “terminei” / “review ok” **não são necessárias** para avançar se os checklists
§9/§10 estiverem verdes — o agente já deve ter avançado.

---

## 12. Regras críticas

- **Modo autônomo é o padrão** — auto-pick, auto finish, auto pass_review
- **Play/pause** gerenciado pelas tools compostas
- **O fluxo do dev termina em "teste"** — nunca Teste → Concluído aqui
- **NUNCA `move_task` com workflow ativo** — usar tools compostas
- **Wiki antes de implementar** + wiki no done
- **Telemetria obrigatória** — `sig-telemetry` em todo turno com task ativa
- **Não perguntar task** se houver elegível; não esperar “terminei”/“review ok”
- **Gates humanos** só em corretiva e Concluído (skill QA)
- **Kanban ≠ telemetria** — não mover coluna só para gerar atividade
- **Zero elegíveis** → informar e parar; não inventar card

---

## 13. Skills irmãs

| Skill | Caminho | Quando acionar |
|---|---|---|
| Telemetria operacional | `.agents/skills/sig-telemetry/SKILL.md` | Sempre que houver task SIG ativa |
| Criação de cards | `.agents/skills/sig-criar-cards/SKILL.md` | Criar nova task, bug ou card de melhoria |
| QA e corretivas | `.agents/skills/sig-qa-corretiva/SKILL.md` | Testar, documentar erros, abrir corretiva |
