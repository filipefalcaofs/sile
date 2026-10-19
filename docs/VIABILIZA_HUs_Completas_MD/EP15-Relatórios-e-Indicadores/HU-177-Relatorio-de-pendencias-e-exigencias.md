# HU-177 — Relatório de pendências e exigências

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Resumir pendências abertas, vencidas, respondidas e expiradas, com o tempo médio de resposta do requerente, e listar as pendências do recorte.

## História de Usuário
**Como** gestor, **quero** ver as exigências e o tempo de resposta, **para** acompanhar o que ainda está com o requerente e o que já voltou.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/pendencias`.
- Consulta auditada (`consulta-pendencias`).

## Fluxo evidenciado
1. `PendenciasRelatorioService` devolve o resumo e a lista.
2. A lista ordena as abertas pelo prazo primeiro e pagina.
3. O filtro de situação é o da pendência: `aberta`, `respondida`, `expirada` ou `cancelada`. Não é o status do processo.
4. O período recorta `data_de` e `data_ate`.
5. `?formato=` exporta o mesmo recorte (`PendenciasReportSource`).

## Regras de negócio evidenciadas
- Tempo médio de resposta é do requerente, calculado sobre as pendências reais do recorte.
- Cancelada entra no filtro, além dos quatro grupos do resumo citados no controller (abertas, vencidas, respondidas, expiradas).

## Validações
- `status_pendencia` só aceita os quatro valores. Outro valor falha a validação do request.
- Página: 10, 15, 25 ou 50.

## Critérios de aceite
- A tela mostra resumo e lista paginada do recorte.
- Filtrar por situação da pendência não filtra pelo status do processo.
- Exportar repete o recorte.
- A consulta é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`pendencias`)
- `app/Services/Relatorios/PendenciasRelatorioService.php`
- `app/Http/Requests/Gestao/RelatorioFiltersRequest.php`
- `routes/gestao.php` (`relatorios.pendencias`)

## Não evidenciado
- Abertura ou resposta de pendência nesta tela.
