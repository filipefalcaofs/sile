# HU-129 — Consultar sala de controle

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Reunir, numa tela, o tempo de viabilidade, o volume decidido, a automação, a distância para a referência federal, a série de Salvador e os gargalos.

## História de Usuário
**Como** gestor, **quero** abrir a sala de controle com o recorte de datas, **para** ver o desempenho sem percorrer cada relatório, e sem perder o resto da tela se o benchmark não tiver competência completa.

## Atores e permissões
- `consultar-relatorios` em `/gestao/sala-de-controle`.
- Consulta auditada (`consulta-sala-de-controle`), com os filtros aplicados.

## Fluxo evidenciado
1. `SalaDeControleService` monta cinco blocos a partir de serviços já existentes: tempo do período, operação, distância ao benchmark, evolução de Salvador e gargalos.
2. Operação conta processos com decisão no período, separa expresso e semi-expresso e calcula a taxa de automação (expresso sobre o total). Sem decisão, a taxa é nula.
3. O benchmark entra pela série e pela distância. Falha ou ausência dessa referência não zera os outros blocos: o serviço monta cada bloco à parte.
4. Não há `?formato=` nesta rota.

## Regras de negócio evidenciadas
- A sala não recalcula regra de negócio nova: compõe indicadores existentes.
- Taxa de automação sem decisão não é zero.
- Período recorta a decisão (`decided_at`), não a data de protocolo, neste bloco de operação.

## Validações
- Filtros do `RelatorioFiltersRequest`, em especial `data_de` e `data_ate`.

## Critérios de aceite
- A resposta traz tempo, operação, distância, evolução e gargalos.
- Período sem decisão devolve taxa de automação nula e total zero.
- A abertura é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/SalaDeControleController.php`
- `app/Services/Relatorios/SalaDeControleService.php`
- `routes/gestao.php` (`gestao.sala-de-controle`)

## Não evidenciado
- Exportação desta tela pelo contrato `?formato=`.
- Decisão de processo a partir da sala.
