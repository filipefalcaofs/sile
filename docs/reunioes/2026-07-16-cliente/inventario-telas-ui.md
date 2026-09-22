# Inventário UI — Telas SAPS da reunião (2026-07-16)

**Fonte:** prints em `prints/catalogo/` + demo Lisa Santos (~13:15–36:40).  
**Objetivo:** recriar no Viabiliza o que a SEDUR usa hoje e melhorar UX/a11y/fluxo sem perder paridade funcional.

Legenda de status Viabiliza:
- **EXISTE** — já há superfície equivalente (pode precisar de gap)
- **PARCIAL** — dado/export existe; tela do legado ainda não espelhada
- **NOVO** — não existe no Viabiliza

---

## T01 — Consulta Processo (filtros)

| | |
|---|---|
| Print | `01-consulta-processo-filtros.jpg` |
| Legado | `/saps/...` Consulta Processo / Resultado de Pesquisa |
| SILE hoje | **EXISTE** — `/gestao/processos` + `ProcessoQueryService` (HU-082) |

**Como é (legado)**
- Filtros: Grupo Status, Status Processo, Status Tramitação, Nº Produto, Nº Processo, BAP
- Avançados: Grupo Serviço, Serviço, Setor, Zona, datas, inscrição, nome, CPF/CNPJ, CEP, logradouro, bairro, nº porta
- Checkboxes categoria: Expresso, Semi-Expresso, Malha Fina, **Sede de Escritório**
- Ações: Limpar Filtros, Pesquisar → tabela paginada

**Melhorias no Viabiliza**
- Manter filtros; garantir label clara “Sede de Escritório” (não só flag interna)
- Filtros colapsáveis com contador de ativos; URL sync (já padrão Inertia)
- Empty state e skeleton; contraste AA nos chips de categoria

---

## T02 — Detalhar Processo (polígono / ficha)

| | |
|---|---|
| Print | `02-detalhar-processo-poligono.jpg`, `02b-…cnae.jpg` |
| SILE hoje | **PARCIAL** — detalhe processo + mapa existem; ficha EV com pergunta sede/CNAE precisa fechar paridade |

**Como é**
- Abas: SemiExpresso, MalhaFina, Informações, Polígono, Anexos, Histórico, DAM, **Produto**, Vistoria
- Seção atividades: CNAE 8211-3/00, pergunta “escritório virtual / sede?”, LOUOS, TLL, gatilho “Sede de Escritório Virtual”
- Polígono: mapa + redesenhar + consultar dados + confirmação polígono ≠ requerente

**Melhorias**
- Destacar visualmente o gatilho sede (Sim/Não) e o CNAE 8211-3/00
- Mostrar na ficha: lista de abrigados da inscrição (hoje consultam fora) — ver Spec 3
- Condicionantes com autocomplete (não só texto livre)

---

## T03 — Aba Produto (TVL / alvará PDF)

| | |
|---|---|
| Print | `03-…`, `03b-…`, `04-…`, `05-…` |
| SILE hoje | **PARCIAL** — `TvlDocument` / visualização interna (spec desfecho 2026-07-14) |

**Como é**
- Cabeçalho: processo, BAP, abertura, status, serviço, requerente, contatos
- Viewer PDF embutido (“CarregarAlvara”)
- Campos-chave EV: **End. Virtual - TVL Nº**, condicionante escritório virtual, atividades CNAE, vagas, LOUOS

**Melhorias**
- Produto só interno (já decidido na spec de desfecho)
- Painel lateral com metadados EV (sede vs abrigado, TVL da sede, validade) sem depender só do PDF
- Download/print com auditoria; zoom acessível

---

## T04 — Relatório Sede de Escritório Virtual

| | |
|---|---|
| Print | `06-relatorio-sede-escritorio-virtual.jpg` |
| SILE hoje | **PARCIAL** — export `?relatorio=escritorio-virtual` em Tempo de análise; **sem** tela de lista com filtros do legado |

**Como é**
- Título: Sede de Escritório Virtual
- Filtros: **Sede** (nº TVL/viabilidade), checkbox **Exibir Expirados**
- Ações: Gerar Excel, Pesquisar
- Tabela: Nº TVL, Razão Social, Data Emissão, Data Vencimento

**Pedido explícito da Lisa**
- Dois pontos de pesquisa: **nº da sede** **e** **inscrição imobiliária**

**Melhorias**
- Tela dedicada (não só export) com DataTable do design system
- Filtros: sede + inscrição (+ opcional razão social / período)
- Coluna vínculo abrigado↔sede; empty state; “Exibir expirados” honesto
- Excel via pipeline de export já existente (RN-005)

---

## T05 — Relatório Tempo de Emissão de TVL

| | |
|---|---|
| Print | `07-…`, `08-relatorio-tempo-emissao-tvl.jpg` |
| SILE hoje | **PARCIAL** — KPI/média em `/gestao/relatorios/tempo`; tabela estilo SAPS incompleta |

**Como é**
- Filtros: Relatório (Tempo de emissão de TVL), Serviço, Data inicial/final, radio Deferido|Indeferido
- Ações: Limpar, Gerar Excel, Pesquisar
- Colunas: Processo, Serviço, Tipo (Expresso/Semi…), Abertura, DAM×4, TVL Disponível, Nº TVL, Emissão, Emissão−Abertura

**Pedidos Lisa**
- Incluir serviço **Atividades em residência**
- Filtro por **CNAE**
- Distinguir **viabilidade** vs **revisão** (4 tipos de revisão; entrada via REDESIM — bloqueio parcial)

**Melhorias**
- Seletor de colunas (muitas colunas DAM vazias no print)
- Unidade clara do tempo (dias úteis vs horas) — alinhar a `TempoAnaliseService`
- Scroll horizontal com sticky first column; export Excel idêntico ao recorte da tela

---

## T06 — Enviar processo de TVL para análise

| | |
|---|---|
| Print | `09-…`, `09b-…`, `10-…` |
| SILE hoje | **NOVO** (ou fluxo equivalente via status/caixa — confirmar gap) |

**Como é**
- Campo Processo + Pesquisar
- Modal “Processo de TVL não encontrado” (amarelo; contraste ruim)
- Fluxo para empurrar TVL elegível à fila de análise

**Melhorias**
- Alertas com contraste AA (não texto amarelo em fundo claro)
- Após achar: preview do processo + confirmação antes de enviar
- Auditoria + permissão explícita; feedback Inertia flash

---

## T07 — Portal SEDUR Revista Digital (fora do Viabiliza)

| | |
|---|---|
| Print | `11-portal-sedur-revista-digital.jpg` |
| SILE | **FORA DE ESCOPO** — portal institucional; só referência de atalhos (SAPS/SIGS/CLE/SADS) |

---

## Ordem sugerida de implementação UI

1. T04 Relatório sede (gap mais claro vs pedido da reunião)  
2. T05 Tempo emissão (paridade de filtros/colunas)  
3. T06 Enviar para análise  
4. T02/T03 Ficha + produto EV (depende do motor Spec 1)  
5. T01 gaps residuais de filtro/label
