# Escritório virtual — ficha de análise e envio de TVL para análise — design

**Data:** 2026-07-16 · **Revisão:** 2 (F-2/F-3 fechados; F-1 pendente SEDUR)  
**Origem:** Reunião SEDUR 2026-07-16 + prints.  
**Artefatos UI:** `inventario-telas-ui.md` (T02, T03, T06) + `prints/catalogo/02*`, `03*`, `04*`, `09*`.  
**Status:** RASCUNHO — `[OPEN-F-2/F-3]` fechados (SEDUR 2026-07-16); `[OPEN-F-1]` ainda pendente (lista de campos da ficha; não bloqueia o "enviar p/ análise"). Ficha depende do motor.  
**Relacionado:**  
- `2026-07-16-escritorio-virtual-motor-design.md`  
- `2026-07-14-status-analise-processo-design.md`  
- `2026-07-14-desfecho-analise-produto-design.md`  
- `2026-06-14-analise-tecnica-design.md`

---

## 1. Problema

Na demo, a operação:

1. Abre a **ficha de análise** da sede, marca “Sede de Escritório Virtual”, vê CNAE/LOUOS/TLL e sente falta de dados que hoje busca em outro sistema (abrigados / SEFAZ).
2. Usa **condicionantes** com busca no legado.
3. Usa a tela **Enviar processo de TVL para análise** para empurrar processos à fila — com modal “não encontrado” (contraste ruim).

O SILE precisa cobrir esses fluxos com paridade + UX melhor, sem inventar regra fiscal.

## 2. Objetivos / Não-objetivos

**Objetivos**
- Ficha de análise EV: pergunta/flag sede, destaque CNAE 8211-3/00, condicionantes com autocomplete, painel de abrigados da inscrição.
- Aba Produto: metadados EV legíveis além do PDF (TVL sede, validade, tipo sede/abrigado).
- Tela **Enviar TVL para análise** com pesquisa, preview, confirmação, modal acessível, auditoria.

**Não-objetivos**
- Motor de trava/lista CNAE (spec motor) — esta spec **consome** essas regras.
- Relatórios (spec telas-relatórios).
- Adaptador SEFAZ falso para “preencher campos” — se a API não estiver disponível, campos externos ficam **bloqueados/avisados**.

## 3. Ficha de análise (T02)

### 3.1 Paridade

Do print `02b`:
- Pergunta/resposta: prestar serviço de escritório virtual / ser sede (Sim/Não).
- Exibição CNAE (ex.: 8211-3/00), LOUOS, grupo de uso, TLL, gatilho “Sede de Escritório Virtual”.
- Controle do analista: Deferida / Indeferida / Análise por atividade.
- Condicionantes na ficha.

### 3.2 Melhorias pedidas / propostas

| Item | Origem | Comportamento |
|---|---|---|
| Campos que hoje vão à SEFAZ/outro sistema | Lisa | Embutir na ficha lista parametrizável de campos `[OPEN-F-1]` — até a lista chegar, UI mostra placeholder “campos pendentes da SEDUR” |
| Condicionantes | Lisa | Cadastro versionado + **busca/autocomplete**; relacionar condicionante + observação |
| Painel abrigados | Melhoria (decorrente do motor) | Na ficha da sede: tabela dos abrigados ativos da inscrição (nº TVL, razão social, validade) |
| Destaque visual | Melhoria | Banner se gatilho sede disparou |

### 3.3 CA

**CA-F-01** Analista altera Sede=Sim|Não e grava na ficha com auditoria.  
**CA-F-02** Autocomplete de condicionante retorna itens do cadastro vigente.  
**CA-F-03** Com sede deferida, painel lista abrigados da mesma inscrição (vazio se nenhum).

## 4. Aba Produto (T03)

### 4.1 Paridade

- Viewer do TVL/PDF interno.
- Campos: End. Virtual - TVL Nº (abrigado), condicionantes, atividades, localização LOUOS, vagas.

### 4.2 Melhorias

- Card lateral “Escritório virtual”: tipo (Sede|Abrigado), TVL sede, inscrição, validade sede, status produto.
- Respeitar visibilidade interna (spec desfecho).
- Contraste e controles do viewer acessíveis.

### 4.3 CA

**CA-P-01** Abrigado exibe End. Virtual = TVL da sede.  
**CA-P-02** Requerente externo **não** acessa o PDF do produto pelo SILE.

## 5. Enviar processo de TVL para análise (T06)

### 5.1 Paridade

**Print:** `09`, `09b`, `10`  
- Campo Processo + Pesquisar.  
- Se não achar: mensagem “Processo de TVL não encontrado”.  
- Se achar: permitir envio para análise (estado/caixa conforme motor de status).

### 5.2 Melhorias

- Alertas com contraste AA (Alert do design system; **nunca** texto amarelo em fundo claro).
- Preview: protocolo, serviço, status atual, requerente (minimizar PII na UI conforme papel).
- Confirmação antes de enviar; idempotência se já estiver em análise.
- Permissão dedicada; trilha RN-002.
- Rota sugerida: `/gestao/processos/enviar-para-analise`.

### 5.3 Regras

- **Sem trava por status (`[OPEN-F-2]` fechado, SEDUR 2026-07-16):** QUALQUER status permite enviar para análise — não há filtro de elegibilidade por estado. Exigências: o processo **existir**, permissão dedicada, auditoria (RN-002) e **idempotência** (se já estiver em análise, não duplica tramitação).
- **Vale para todos (`[OPEN-F-3]` fechado):** sede **e** abrigado — a tela não distingue tipo.
- “Não encontrado” ≠ erro 500; resposta 422/404 de domínio com mensagem parametrizada.

### 5.4 CA

**CA-E-01** Processo inexistente → mensagem clara + FECHAR; sem alteração de estado.  
**CA-E-02** Processo encontrado (qualquer status) → após confirmar, entra na caixa/análise com status operacional adequado e auditoria.  
**CA-E-03** Processo já em análise → aviso “já está em análise” sem duplicar tramitação.  
**CA-E-04** Sem permissão → 403.

## 6. Parametrização

- Textos de modal “não encontrado” e de confirmação.
- Feature toggle da tela de envio (se operação quiser desligar).
- Lista de campos extras da ficha (quando SEDUR enviar).

## 7. Fontes

- Transcrição: ~13:37–17:00 (ficha), ~34:00–35:30 (enviar para análise)  
- Prints: `02`, `02b`, `03*`, `04`, `09*`, `10`  
- Specs status/desfecho 2026-07-14  

## 8. Questões abertas

- `[OPEN-F-1]` **PENDENTE (SEDUR vai enviar):** lista exata dos campos SEFAZ/“abrigados” a embutir na ficha. Até chegar, a ficha mostra placeholder “campos pendentes da SEDUR” (§3.2) — **não bloqueia** o motor nem o "enviar p/ análise".  
- ~~`[OPEN-F-2]`~~ **FECHADO (SEDUR 2026-07-16):** qualquer status pode ser enviado para análise (sem trava de elegibilidade). Ver §5.3.  
- ~~`[OPEN-F-3]`~~ **FECHADO (SEDUR 2026-07-16):** vale para todos (sede e abrigado). Ver §5.3.
