# Ata — Reunião cliente SEDUR (2026-07-16)

> **Revisão 2026-07-24:** auditoria da transcrição contra esta ata e as specs. Itens 6–8 da seção 3.A, 17b, 20 e pendências `[OPEN-EV-1/5/6]` foram acrescentados — estavam na gravação e não tinham chegado aos documentos.

**Participantes:** Lisa Sousa Cerqueira Santos (cliente / SAPS-Simplifica) e equipe Sudoeste Fábrica (Viabiliza).  
**Objetivo:** passar itens novos e tirar dúvidas, com demo do legado SAPS.  
**Fonte:** vídeo Meet `dhv-isxt-ibp` (~37 min) + prints em `prints/catalogo/`.  
**Transcrição bruta:** `transcricao.md` (Whisper; há erros de ASR — “cédia/sede”, “quinae/CNAE”, “Kinai”, “Malho Afínio/Malha Fina”, “redi-sim/REDESIM”, etc.).

---

## 1. Conceito de negócio (escritório virtual)

- **Escritório virtual:** empresa (sede) cede o endereço para outras empresas (**abrigados**) exercerem atividades administrativas/de escritório.
- Existe **lista oficial de atividades (CNAEs) permitidas** para abrigados.
- **Sede:**
  1. Entra com viabilidade **padrão**.
  2. Precisa do CNAE **8211-3/00** — *Serviços combinados de escritório e apoio administrativo*.
  3. No cadastro há **pergunta de regra**: se vai ser sede de escritório virtual (Sim/Não).
  4. Se informa que **vai ser sede** → processo vai para **análise humana**.
  5. Analista marca na ficha “Sede de Escritório Virtual = Sim”.
  6. Ao **deferir**, a **inscrição imobiliária fica vinculada/travada** à sede (não pode ser usada livremente por outra empresa fora do modelo).
  7. Produto da sede traz **condicionante** de prestação de serviços de escritório virtual.
  8. Viabilidade interna da sede: gerada internamente; **não fica disponível ao requerente** da mesma forma (conforme explicado pela cliente).
- **Abrigado:**
  - Pode haver **N abrigados** por sede.
  - Fluxo pode ser **expresso** (sem análise SEDUR) se a sede já estiver no local.
  - Produto do abrigado referencia o **número da TVL/viabilidade da sede** (End. Virtual - TVL Nº).
  - Validade do abrigado acompanha a validade da sede.
  - Inscrição imobiliária = a da sede; atividades do abrigado devem estar na **lista de permitidos**.
  - Se solicitar atividade fora da lista → **sistema não permite cadastrar**.

### Problema atual (pedido explícito)

Quando a **sede muda de endereço** (inscrição A → inscrição B), o legado **não desvincula** a viabilidade da inscrição antiga. Isso **bloqueia** empresas não-sede que querem usar a inscrição A.  
**Pedido:** ao sair da inscrição, **desvincular** a viabilidade da inscrição antiga e **comunicar à SEFAZ** (e tratar os abrigados vinculados).

---

## 2. Telas demonstradas (legado SAPS) → referência visual

Ver `prints/catalogo/` e índice no `README.md`.

| Tema | Print | Observação |
|---|---|---|
| Consulta Processo (filtros) | `01-…` | Inclui flag “Sede de Escritório” |
| Detalhe + polígono | `02-…` | Mapa, zona LOUOS, ficha de análise |
| Atividades / CNAE / sede | `02b-…` | Pergunta EV, LOUOS, TLL, gatilho |
| Produto TVL sede | `03-…`, `03b-…` | Condicionantes + End. Virtual |
| Produto abrigado EV | `04-…`, `05-…` | Serviço “TVL - Atividades em Escritórios Virtuais” |
| Relatório sede × abrigados | `06-…` | Lista por nº da sede |
| Relatório tempo emissão | `07-…`, `08-…` | Viabilidade/revisão, Excel |
| Enviar TVL para análise | `09-…`, `09b-…`, `10-…` | Inclui modal “não encontrado” |
| Portal SEDUR | `11-…` | Atalhos SAPS / SIGS / CLE / SADS |

---

## 3. Itens novos / melhorias pedidas (candidatos a spec)

### A. Motor / regra — Sede e abrigados

1. Gatilho CNAE **8211-3/00** + resposta “quero ser sede” → análise + opção de marcar sede. **A confirmar:** no legado o CNAE sozinho parece puxar para análise (transcrição ~00:19:00; exemplo com resposta “Não” já em análise). Ver `[OPEN-EV-5]`.
2. Travamento de inscrição imobiliária **somente** se CNAE + flag sede = Sim.
3. Lista parametrizável de CNAEs permitidos para abrigados (cliente vai passar a lista).
4. Produto do abrigado com vínculo ao nº da TVL da sede; validade alinhada à sede.
5. **Desvinculação** da inscrição quando sede muda de endereço + notificação SEFAZ + tratamento dos abrigados. Destino do abrigado **a confirmar** (`[OPEN-EV-1]` reaberto).
6. **Campo estruturado** “Sede de escritório virtual? Sim/Não” no produto — **fora** do texto de condicionante — para envio assertivo à SEFAZ (~00:17:45).
7. Validações do cadastro do abrigado: barrar “não” em inscrição travada; cruzar CNPJ da sede × viabilidade × inscrição; dados da sede somente leitura (~00:09:31).
8. Consulta/relatório por inscrição opera só sobre vínculo **ativo** (sede antiga não aparece; histórico na auditoria) (~00:26:34).

### B. Ficha de análise (melhorias de UX/dados)

6. Exibir na ficha campos que hoje as analistas buscam em outro sistema (“abrigados” / dados SEFAZ), para não precisar consultar fora.
7. Condicionantes na ficha com **cadastro + busca/autocomplete** (relacionar condicionante e observação).
8. Produto = resultado da **última ficha** de análise.

### C. Relatório — Sede de Escritório Virtual

9. Relatório listando abrigados de uma sede.
10. Filtros pedidos: **número da sede (TVL/viabilidade)** **e** **inscrição imobiliária**.
11. Exportação Excel (já existe no legado).

### D. Relatório — Tempo de emissão

12. Relatório de tempo de emissão de TVL (deferido / indeferido).
13. Escopo: **viabilidade** ou **revisão**.
14. Quatro tipos de revisão citados:
    - alteração de endereço;
    - alteração de espaço (ex.: troca de sala);
    - exclusão de atividade;
    - inclusão de atividade.
15. Revisões entram via **REDESIM** (integração).
16. Incluir serviço **“Atividades em residência”** no filtro de tipos de serviço (além de padrão, EV, edifício comercial, culto religioso).
17. Filtro adicional por **CNAE** (e abertura para mais filtros se a cliente enviar).
17b. Tempo **sempre em hora ou dia útil** (exclui fim de semana e feriado cadastrado); só processos concluídos; export **Excel e PDF** (~00:32:32–00:33:45).

### E. Envio para análise

18. Função “Enviar processo de TVL para análise” (tela dedicada + pesquisa por nº do processo).
19. Tratamento de processo não encontrado (modal).
20. Reenvio para análise **não** notifica a SEFAZ — só mudança de status final (cassado / revogado / desativado) (~00:35:20).

### F. Pendências / materiais que a cliente vai enviar

- Lista de CNAEs permitidos para escritório virtual (endpoint já apontado; confirmar payload).
- Eventuais filtros extras do relatório de tempo (WhatsApp).
- Campos da ficha que devem ser embutidos (lista a passar) — `[OPEN-F-1]`.
- **API SEFAZ para desvinculação de inscrição** (alvará de funcionamento) — pauta Anderson/Deiró. `[OPEN-EV-6]`.
- Confirmar gatilho da sede: CNAE sozinho vs CNAE + “Sim”. `[OPEN-EV-5]`.
- Confirmar destino do abrigado quando a sede muda de endereço. `[OPEN-EV-1]`.

---

## 4. Dúvidas / alinhamentos já feitos na reunião

- Autônomo (ex.: médico em vários hospitais) **não** é o mesmo que escritório virtual.
- Sede **sempre** vai para análise quando o CNAE/gatilho se aplica; abrigado pode ser expresso. (Precisa cravar se “gatilho” = CNAE sozinho — `[OPEN-EV-5]`.)
- Pedido de **máxima semelhança visual com o SAPS**, em especial a ficha (~00:12:30, ~00:16:42) — critério de aceitação das analistas; tensiona com melhorias de UX e deve ser gerido como preferência/densidade, não como cópia pixel a pixel.
- Integração REDESIM mencionada no fluxo de revisões (dependência externa — não simular).

---

## 5. Specs geradas (2026-07-16)

1. [`docs/superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md`](../../superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md) — seção 3.A  
2. [`docs/superpowers/specs/2026-07-16-escritorio-virtual-telas-relatorios-design.md`](../../superpowers/specs/2026-07-16-escritorio-virtual-telas-relatorios-design.md) — seções 3.C/D + inventário T01/T04/T05  
3. [`docs/superpowers/specs/2026-07-16-escritorio-virtual-ficha-envio-analise-design.md`](../../superpowers/specs/2026-07-16-escritorio-virtual-ficha-envio-analise-design.md) — seções 3.B/E + inventário T02/T03/T06  

Inventário UI: [`inventario-telas-ui.md`](./inventario-telas-ui.md).
