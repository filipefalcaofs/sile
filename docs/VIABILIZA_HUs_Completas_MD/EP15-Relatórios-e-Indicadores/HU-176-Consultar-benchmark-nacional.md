# HU-176 — Consultar benchmark nacional

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Mostrar a posição de Salvador na régua federal de tempo de viabilidade, entre as capitais, somente quando a competência tem as 27 UFs.

## História de Usuário
**Como** gestor, **quero** ver como a REDESIM mede Salvador diante das outras capitais, **para** comparar com a referência externa, sem misturar esse relógio com o tempo interno do Viabiliza.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/benchmark`.
- Consulta auditada (`consulta-benchmark`), com a competência usada.

## Fluxo evidenciado
1. `BenchmarkRankingService` lê a última competência em que as 27 UFs foram ingeridas.
2. Sem essa competência, a tela devolve posição nula, total zero, capitais vazias, cobertura incompleta e as UFs ausentes do mês anterior. A série histórica de Salvador ainda é enviada.
3. Com competência completa, devolve posição nacional, ranking das capitais e a série de Salvador.
4. A carga vem de `RedesimClient`, que baixa a planilha pública de estatística da REDESIM quando `benchmark_redesim` está ligado. Desligado, o download falha com motivo e não inventa planilha. Esta tela não dispara o download: lê o que já foi ingerido.
5. Não há `?formato=` nesta rota.

## Regras de negócio evidenciadas
- Ranking parcial não é apresentado como nacional.
- O número federal não substitui o tempo medido nas rotas internas.
- Falha de download na ingestão fica registrada como falha, não como métrica zero.

## Validações
- A rota não recebe filtros de período do relatório interno. A competência é a última completa armazenada.

## Critérios de aceite
- Sem as 27 UFs, a tela informa a lacuna e não monta posição nacional.
- Com a competência completa, mostra posição, capitais e série.
- A consulta é auditada.
- Interruptor de importação desligado não gera arquivo fictício na carga.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/BenchmarkController.php`
- `app/Services/Benchmark/BenchmarkRankingService.php`
- `app/Services/Benchmark/RedesimClient.php`
- `routes/gestao.php` (`relatorios.benchmark`)

## Não evidenciado
- Posição nacional calculada com UF faltando.
- Uso do tempo interno do Viabiliza como se fosse o número da REDESIM.
