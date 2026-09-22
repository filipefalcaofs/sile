---
name: sig-telemetry
description: >
  Camada permanente de telemetria operacional do SIG via MCP user-sig-dashboard.
  Use automaticamente sempre que houver trabalho em task/sprint do SIG (dev ou QA),
  em qualquer harness (Cursor, Claude Code, Codex). Mantém sessão, eventos, heartbeat
  e timeline — sem o usuário pedir "registre no SIG". Não move kanban; não substitui
  sig-dev nem sig-qa-corretiva.
---

# Skill — Telemetria operacional SIG

O SIG é o **canal oficial de observabilidade** da execução do agente.
Esta skill é a camada de comportamento; o MCP é só o transporte.

```text
Harness → Agent → sig-telemetry (+ skill de papel) → MCP → SIG
```

**Observabilidade ≠ chain-of-thought.** Registrar ações, eventos, comandos, arquivos,
testes, erros, estados e resultados. Nunca registrar raciocínio privado do modelo.

---

## 1. Quando ativar

Ativar automaticamente quando:

- Houver task/sprint/projeto SIG em contexto; ou
- `sig-dev` / `sig-qa-corretiva` / agent `sig-dev` estiverem em uso; ou
- O usuário iniciar trabalho que resulte em `sig_pick_task` / play em Teste.

O usuário **não** precisa dizer "registre no SIG".

**Obrigatoriedade:** com task SIG ativa, esta skill vale em **todo turno** do harness —
não é opcional nem “quando der tempo”.

---

## 2. Fronteiras

| Faz | Não faz |
|-----|---------|
| Sessão, eventos, heartbeat, sanitização | Mover colunas do kanban |
| Adaptador MCP compat/native | Decidir Concluído / Review de QA |
| Espelhar transições de `sig-dev`/`sig-qa` | Substituir wiki ou checklists de entrega |
| fail-soft se MCP falhar | Parar o coding por falha de telemetria |

Skills de papel: `sig-dev` (até Teste), `sig-qa-corretiva` (Teste→Concluído / corretivas).

---

## 3. Sessão

Envelope de contexto (manter atualizado):

- `session_id`, projeto, sprint, caseId, usuário, harness, agente, branch, repository
- `status` operacional + atividade atual

### Início

Assim que houver task identificada:

1. Resolver harness: `cursor` | `claude_code` | `codex` | `copilot` | `antigravity` | `outro`
2. Emitir `session_start` pelo adaptador **uma única vez** se ainda não houver sessão ativa
   (em `compat`, essa emissão já invoca `start_session({ codCaso, ferramenta })` e `log_work`)
3. Emitir `activity` "Sessão iniciada"
4. Iniciar política de heartbeat
5. Incluir contexto (branch/repo, se disponíveis) no payload da emissão

Se `sig_pick_task` já abriu sessão, reutilizar o `sessionId` — não duplicar sem necessidade.
Em conflito 409 (sessão ativa), usar `sessaoAtivaId` retornado e continuar.
Em `compat`, após emitir `session_start`, **não chamar novamente** `start_session` nem `log_work`
separadamente para a mesma abertura.

### Fim

Ao concluir, pausar longo, trocar de task ou encerrar harness com trabalho feito:

1. Emitir `session_end` pelo adaptador **uma única vez**, com resumo operacional (arquivos,
   testes, erros resolvidos/pendentes, duração e commits)
2. Em `compat`, essa emissão já invoca `end_session({ sessionId, resumo, arquivosTocados, commits })`;
   não chamar `end_session` novamente para o mesmo encerramento.

---

## 4. Catálogo de eventos

Envelope: `session_id`, `timestamp`, `type`, `summary`, `payload`, `harness`, `agent`.

| type | Quando | Payload |
|------|--------|---------|
| `session_start` / `session_end` | Abrir/fechar sessão | contexto / status_final |
| `activity` | Mudança de intenção operacional | fase + frase curta |
| `file_changed` | Arquivo criado/alterado/removido com efeito na task | path, op, +/− |
| `command` | Comando relevante | cmd sanitizado, exit_code |
| `test_result` | Suite executada | suite, total, passed, failed, skipped, duration, status |
| `error` / `error_resolved` | Falha relevante e resolução | kind, message curta |
| `status` | Estado da execução | analyzing, in_progress, testing, review, completed, blocked, failed |
| `heartbeat` | Sessão viva | last_activity_at, active/idle |
| `progress` | Marco de entrega (commit, feature parcial) | texto, % opcional |
| `blocked` / `unblocked` | Impedimento real | motivo |

### Activities permitidas (exemplos)

Analisando arquitetura · Investigando bug · Localizando implementação ·
Implementando endpoint · Criando componente · Alterando service · Executando testes ·
Investigando falha · Corrigindo erro · Executando lint · Revisando alterações ·
Validando integração

Trocar `activity` só quando a intenção operacional mudar.

### Exclusões

- CoT / pensamento privado
- Conteúdo integral de arquivo / diff completo
- Secrets (ver sanitização)
- Micro-eventos (keystroke, leitura sem efeito)

---

## 5. Adaptador MCP

### Modos

- `native` — se a tool `emit_event` existir no MCP
- `compat` — caso contrário (padrão atual)

### Mapa compat

| Evento | Tool | Formato |
|--------|------|---------|
| session_start | adaptador: `start_session` + `log_work` | uma única emissão; contexto no resumo |
| session_end | adaptador: `end_session` | uma única emissão; resumo + arquivos + commits |
| heartbeat | `log_work` | proxy last_seen + atividade |
| activity / status | `add_evolution` | `[activity] …` / `[status] …` |
| file_changed | `log_work.arquivosTocados` + `add_evolution` batchelada | `[file] path · op · +N/-M` |
| command | `add_evolution` batchelada | `[cmd] … (exit X)` |
| test_result | `add_evolution` | `[test] suite · passed/total · status` |
| error / error_resolved | `add_evolution` | `[error] …` / `[ok] …` |
| progress | `sig_sync_progress` | texto de entrega (sem prefixo de telemetria fina) |
| blocked / unblocked | tools de impedimento da skill de papel + evolução | motivo |

`sig_sync_progress` é reservado a `progress` e marcos de entrega. Para telemetria fina
(`activity`, `status`, `[file]`, `[cmd]`, `[test]`, `[error]`), usar `add_evolution`
(ou `log_work` quando indicado no mapa), mesmo com workflow ativo.

### Batching

No mesmo turno: agrupar `file_changed` e pares `command`+`test_result`.
Flush imediato: `error`, `blocked`, `session_start`, `session_end`.
Respeitar ~255 chars em evolução (2–3 linhas curtas por chamada).
`log_work` sobrescreve — reenviar lista acumulada de arquivos do turno/sessão.

### Heartbeat

- `compat`: no máximo 1× por turno com trabalho ativo, ou quando `activity` mudar
- `native` (futuro): alvo 60–120s

### Sanitização (obrigatória)

Antes de enviar, se `command`/`summary`/`payload` contiver:
`password`, `token`, `secret`, `api_key`, `.env`, `credentials`, `private key`
→ omitir o comando ou mascarar com `***`.

### Gaps futuros (não implementar nesta skill)

```text
emit_event({ sessionId, type, summary, payload, timestamp? })
heartbeat({ sessionId, state: "active" | "idle", lastActivityAt })
```

Opcional: `get_session_timeline(sessionId)`.

---

## 6. Fail-soft

1. Falha de telemetria não interrompe coding.
2. Anotar no turno que falhou e seguir.
3. No próximo marco OK, reconciliar (`log_work` + evolução de catch-up).
4. Nunca inventar `sessionId` / `caseId`.

---

## 7. Sinais automáticos (sem pedido do usuário)

| Sinal | Eventos |
|-------|---------|
| Task selecionada / play QA | session_start, status, activity |
| Mudança de foco operacional | activity, status |
| Arquivo editado com efeito | file_changed |
| Comando relevante | command |
| Testes | test_result (+ error se falhar) |
| Build/lint/runtime falha | error → depois error_resolved |
| Commit / marco | progress (+ sync da skill de papel) |
| Impedimento (skill de papel) | blocked / unblocked |
| Pausa / fim | heartbeat idle / session_end |

---

## 8. Critério de sucesso

Sem o usuário pedir registro no SIG, a timeline deve permitir reconstruir:

sessão → atividades → arquivos → comandos → testes → erros → resolução → resultado → fim

Kanban continua responsabilidade de `sig-dev` / `sig-qa-corretiva`.