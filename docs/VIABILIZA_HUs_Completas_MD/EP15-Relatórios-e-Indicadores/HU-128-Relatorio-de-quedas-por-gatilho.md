# HU-128 — Relatório de quedas por gatilho

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Medir a taxa de resposta do fluxo expresso e ranquear os motivos pelos quais o processo elegível não foi decidido por ele.

## História de Usuário
**Como** gestor, **quero** ver a taxa de resposta expressa e o ranking de motivos de queda, **para** saber o que tirou o processo da decisão automática.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/quedas`.
- Consulta auditada (`consulta-quedas`).

## Fluxo evidenciado
1. A tela recebe `taxa` (`taxaRespostaExpressa`), `serie` temporal e `ranking` de motivos.
2. A taxa usa o recorte de elegíveis e respondidas. Sem base, o serviço não transforma ausência em zero de desempenho quando devolve taxa nula (o mesmo padrão do painel).
3. `?formato=` exporta as quedas filtradas (`ExpressoQuedaReportSource`).
4. O painel executivo reutiliza a taxa e o ranking; esta rota é o relatório dedicado.

## Regras de negócio evidenciadas
- O ranking é motivo de queda para análise, não uma lista genérica de indeferimentos.
- Exportação segue o mesmo filtro da tela.

## Validações
- Filtros comuns do request de relatório.
- Formato somente csv, xlsx ou pdf habilitados.

## Critérios de aceite
- A rota devolve taxa, série e ranking do mesmo recorte.
- Exportar usa a fonte de quedas, não a de solicitações genéricas.
- A consulta é auditada.

## Divergências da redação anterior
O texto citava gatilhos de CNAE, enquadramento ausente, dado pendente e divergência do motor como lista fechada da redação. O ranking evidenciado é o que `ExpressoQuedaService::rankingMotivos` agrega dos registros, não uma lista fixa reescrita nesta HU.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`quedas`)
- `app/Services/Relatorios/ExpressoQuedaService.php`
- `app/Services/Relatorios/Export/Sources/ExpressoQuedaReportSource.php`

## Não evidenciado
- Ação nesta tela que altere parâmetro ou reenquadre processo.
