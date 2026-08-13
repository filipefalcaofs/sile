# Escritório virtual — telas e relatórios (paridade SAPS + melhorias) — design

**Data:** 2026-07-16 · **Revisão:** 2 (OPENs de UI fechados)  
**Origem:** Reunião SEDUR 2026-07-16 + prints do Meet.  
**Artefatos UI:** `docs/reunioes/2026-07-16-cliente/inventario-telas-ui.md` e `prints/catalogo/`.  
**Status:** RASCUNHO — `[OPEN-UI-1/2/3]` fechados (2026-07-16). Pronto para plano (depois do motor).  
**Relacionado:** `2026-07-16-escritorio-virtual-motor-design.md`, fase 15 (TempoAnalise / export EV), HU-082, HU-129.

---

## 1. Problema

A Lisa demonstrou telas do SAPS que a operação usa diariamente. O SILE já tem **parte** (consulta de processos, KPI de tempo de emissão, export de sedes), mas:

- o relatório **Sede × abrigados** não tem tela com os filtros pedidos (nº sede **e** inscrição imobiliária);
- o relatório **Tempo de Emissão** não espelha colunas/filtros do SAPS (serviço “Atividades em residência”, CNAE, deferido/indeferido em grade);
- a SEDUR precisa **reconhecer** a tela (paridade) e ao mesmo tempo ganhar UX/a11y (melhoria).

## 2. Princípio de UI

1. **Paridade funcional** com o print do legado (campos, ações, colunas essenciais).  
2. **Melhoria controlada** no design system SILE (Inertia/React, tipografia, contraste AA, empty states, URL sync).  
3. **Sem fachada:** Excel e pesquisa usam o mesmo recorte de dados (RN-005).

Referência visual obrigatória: `prints/catalogo/06-*.jpg`, `08-*.jpg`, `01-*.jpg`.

## 3. Tela R1 — Relatório Sede de Escritório Virtual

**Print:** `06-relatorio-sede-escritorio-virtual.jpg`  
**Rota sugerida:** `/gestao/relatorios/escritorio-virtual` (ou aba em Relatórios Administrativos)

### 3.1 Paridade (como é)

| Elemento | Legado |
|---|---|
| Título | Sede de Escritório Virtual |
| Filtro | Sede (nº TVL/viabilidade) |
| Checkbox | Exibir Expirados |
| Ações | Pesquisar, Gerar Excel |
| Colunas | Nº TVL, Razão Social, Data Emissão, Data Vencimento |

### 3.2 Pedido novo (reunião)

- Segundo filtro obrigatório/disponível: **Inscrição imobiliária** (Lisa: “dois pontos de pesquisa”).

### 3.3 Melhorias

- DataTable do SILE + paginação server-side.
- Coluna opcional “Tipo” (Sede | Abrigado) e “TVL da sede” para abrigados.
- Empty state se sede sem abrigados; mensagem se sede não encontrada.
- Contraste do botão Excel AA; ícones com `aria-label`.

### 3.4 CA

**CA-R1-01** DADO nº sede válido QUANDO pesquisar ENTÃO lista abrigados (+ sede) da inscrição.  
**CA-R1-02** DADO inscrição imobiliária QUANDO pesquisar ENTÃO mesmo recorte.  
**CA-R1-03** Gerar Excel = mesmas linhas da pesquisa (RN-005).  
**CA-R1-04** “Exibir Expirados” inclui/exclui conforme validade do produto.

## 4. Tela R2 — Tempo de Emissão de TVL

**Print:** `08-relatorio-tempo-emissao-tvl.jpg`  
**Rota:** evoluir `/gestao/relatorios/tempo` (hoje KPI) com **modo tabela SAPS** ou subpágina.

### 4.1 Paridade

Filtros: Relatório (Tempo de emissão de TVL), Serviço, Data inicial/final, Deferido|Indeferido.  
Ações: Limpar, Gerar Excel, Pesquisar.  
Colunas mínimas do print: Processo, Serviço, Tipo, Abertura, blocos DAM (podem vir vazios), TVL Disponível, Nº TVL, Emissão, Emissão−Abertura.

### 4.2 Pedidos novos

- Serviço **Atividades em residência** no filtro (parametrizar tipos de serviço).
- Filtro por **CNAE**.
- Distinção **Viabilidade** vs **Revisão** (alteração endereço / espaço / exclusão atividade / inclusão atividade).  
  > Revisões via REDESIM: se integração não homologada, filtro “Revisão” fica **visível porém desabilitado** com aviso — nunca simula dados.

### 4.3 Melhorias

- Seletor de colunas (ocultar DAM vazios por padrão).
- Sticky coluna Processo; unidade do tempo documentada (úteis).
- Reusar `TempoAnaliseService` / sources de export.

### 4.4 CA

**CA-R2-01** Pesquisa por período + deferido retorna linhas com TVL emitido no recorte.  
**CA-R2-02** Filtro CNAE restringe o builder.  
**CA-R2-03** Excel = recorte da tela.  
**CA-R2-04** Serviço “Atividades em residência” aparece no dropdown quando parametrizado.  
**CA-R2-05** Modo Revisão desabilitado com mensagem se REDESIM bloqueado.

## 5. Tela C1 — Consulta Processo (gap residual)

**Print:** `01-consulta-processo-filtros.jpg`  
**SILE:** `/gestao/processos` (HU-082) — **EXISTE**.

Gaps a fechar só se testes/SEDUR apontarem:
- Labels idênticos aos checkboxes Expresso / Semi-Expresso / Malha Fina / Sede de Escritório.
- Filtros avançados equivalentes ao print (já planejados no 10-14).

Não redesenhar do zero; checklist de paridade no plano de execução.

## 6. Fora de escopo desta spec

- Motor sede/abrigado (spec motor).
- Ficha de análise / enviar TVL para análise (spec ficha-envio).
- Portal Revista Digital (T07).

## 7. Fontes

- Inventário: `inventario-telas-ui.md` T01, T04, T05  
- Transcrição: ~27:45–34:00 (relatórios)  
- Código: `TempoAnaliseService`, `EscritorioVirtualReportSource`, `resources/js/pages/gestao/relatorios/tempo.tsx`

## 8. Questões abertas

- ~~`[OPEN-UI-1]`~~ **DECIDIDO (nossa, aceito SEDUR):** **páginas separadas** no menu SILE (padrão atual), com paridade de conteúdo — não replicar o seletor único do SAPS.  
- ~~`[OPEN-UI-2]`~~ **DECIDIDO (aceito SEDUR):** manter as colunas DAM disponíveis para paridade, mas **ocultar as vazias por padrão** via seletor de colunas (§4.3).  
- ~~`[OPEN-UI-3]`~~ **CONFIRMADO:** enquanto o REDESIM não estiver homologado, o filtro "Revisão" fica **visível porém desabilitado** com aviso (§4.2) — nunca simula dados.  

Nenhuma questão aberta remanescente.
