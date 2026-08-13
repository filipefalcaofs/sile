# HU-145 — Relatório de quedas por gatilho (expansão do expresso)

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Transforma a meta de ampliar o expresso (~405 CNAEs hoje) em processo contínuo guiado por dados.

## Épica
**EP15 — Relatórios e Indicadores**

## Objetivo
Medir e ranquear os motivos pelos quais processos elegíveis (baixo/médio risco) caem para análise humana — gatilhos CNAE, enquadramento ausente, dado pendente, divergência do motor — orientando qual parametrização criar ou corrigir.

## História de Usuário
**Como** gestor SEDUR,  
**quero** ver o ranking de motivos de queda para análise,  
**para** decidir qual parametrização atacar e ampliar a taxa de resposta expressa.

## Contexto de Negócio
O expresso subutilizado é a aposta central do projeto (reunião 2026-06-11). Cada queda registrada com motivo (HU-073 RN-008) vira dado; este relatório fecha o ciclo: medir → priorizar → parametrizar (com simulação de impacto, HU-143) → medir de novo.

## Fluxo Principal
1. O gestor acessa o relatório no módulo de indicadores.
2. Filtros: período, setor, zona, CNAE, categoria (semi-expresso/malha fina), tipo de motivo.
3. Visões: ranking de motivos de queda (volume e % do total), CNAEs que mais caem, série temporal da taxa de resposta expressa (% decidido automaticamente).
4. Drill-down: do motivo para a lista de processos; de cada processo para a ficha e as divergências analista × motor (HU-140 RN-003).
5. Exportação (padrão EP15) e leitura em dashboard executivo (HU-122).

## Regras de Negócio
- RN-001: A fonte é o motivo estruturado registrado na queda (HU-073/HU-079) e as divergências da pré-análise (HU-140) — nunca texto livre não classificado.
- RN-002: O indicador-mestre é a **taxa de resposta expressa** (decididos automaticamente ÷ elegíveis), com meta acompanhável no tempo.
- RN-003: O relatório deve sugerir o vínculo de ação: motivo → mantenedor correspondente (ex.: "enquadramento ausente" → HU-015; "condicionante sem pergunta" → HU-019).
- RN-004: Janelas e metas parametrizáveis (HU-014).

## Critérios de Aceite — BDD

### CA-01 — Ranking com drill-down
**Dado** processos caídos para análise no período,  
**Quando** o gestor abrir o relatório,  
**Então** os motivos devem aparecer ranqueados com volume, % e acesso à lista de processos de cada motivo.

### CA-02 — Taxa de resposta expressa
**Dado** o período filtrado,  
**Quando** o relatório carregar,  
**Então** a série temporal da taxa de resposta expressa deve refletir os dados reais dos processos.

### CA-03 — Exportação
**Dado** qualquer visão filtrada,  
**Quando** o gestor exportar,  
**Então** o arquivo deve refletir exatamente os filtros aplicados.

## Dependências
- HU-073 (motivos de queda), HU-140 (divergências), HU-143 (simulação de parametrização), HU-122 (dashboard).

## Prioridade
Média-alta

## Observações
Fecha o ciclo de melhoria contínua do expresso: este relatório aponta o alvo; a HU-143 valida a mudança; a série temporal comprova o ganho.
