# HU-123 — Solicitações por período

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Contar solicitações protocoladas por dia, no recorte informado.

## História de Usuário
**Como** gestor, **quero** ver quantas solicitações entraram em cada dia, **para** ler a demanda do período.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/indicadores`.
- A consulta é auditada (`consulta-indicadores`).

## Fluxo evidenciado
1. `IndicadoresViabilidadeService::porPeriodo` agrupa `protocoled_at` por dia, só processos protocolados, com filtros de período, setor, analista, bairro, categoria e CNAE.
2. Dia sem protocolo não aparece. A lista pode vir vazia.
3. Data inicial posterior à final não consulta: a tela devolve séries vazias e a mensagem de período invertido. Taxas nesse erro ficam com total zero e taxa nula.
4. `?formato=` exporta as solicitações do recorte (`SolicitacoesReportSource`), não um gráfico.

## Regras de negócio evidenciadas
- A população é a protocolada, não o rascunho.
- A agregação é SQL, por dia.

## Validações
- `data_de` e `data_ate` são datas opcionais (`RelatorioFiltersRequest`).
- Período invertido é rejeitado na renderização, com aviso, sem trocar as datas em silêncio.

## Critérios de aceite
- O recorte devolve uma linha por dia com total de protocolos.
- Período invertido mostra o erro e não lista dia inventado.
- A consulta fica na trilha de relatórios.

## Divergências da redação anterior
O texto não dizia que a série divide a tela de indicadores com zona, CNAE, risco e taxas, nem o bloqueio de período invertido.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`indicadores`)
- `app/Services/Relatorios/IndicadoresViabilidadeService.php` (`porPeriodo`)

## Não evidenciado
- Série de rascunhos ou de processos ainda sem `protocoled_at`.
