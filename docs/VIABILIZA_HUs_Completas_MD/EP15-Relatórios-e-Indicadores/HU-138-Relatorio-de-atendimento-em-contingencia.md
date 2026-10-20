# HU-138 — Relatório de atendimento em contingência

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Medir quanto do volume protocolado entrou pelo atendimento de contingência e por quais motivos.

## História de Usuário
**Como** gestor, **quero** ver o volume e os motivos do canal de operador, **para** comparar com o total protocolado no período.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/contingencia`.
- Consulta auditada (`consulta-contingencia`).

## Fluxo evidenciado
1. `ContingenciaRelatorioService` devolve resumo (participação sobre o total protocolado e ranking de motivos) e a lista paginada.
2. O filtro usado na tela é o período (`data_de` e `data_ate`).
3. `?formato=` exporta o mesmo recorte (`ContingenciaReportSource`).

## Regras de negócio evidenciadas
- A participação usa o total protocolado no período como base, não só os atendimentos de contingência.
- O relatório não abre novo atendimento.

## Validações
- Datas opcionais do request de relatório.
- Página: 10, 15, 25 ou 50.

## Critérios de aceite
- A tela mostra resumo, motivos e lista do período.
- Exportar repete o recorte.
- A consulta é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`contingencia`)
- `app/Services/Relatorios/ContingenciaRelatorioService.php`
- `routes/gestao.php` (`relatorios.contingencia`)

## Não evidenciado
- Registro de novo atendimento de contingência nesta rota.
