# HU-134 — Relatório de trilha por processo

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Montar, a partir do número de protocolo, a trilha daquele processo e permitir imprimir o PDF.

## História de Usuário
**Como** gestor, **quero** informar o protocolo e ver a história daquele processo, **para** prestar contas do que ocorreu, e baixar o PDF quando o processo existir.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/trilha` e `/gestao/relatorios/trilha/imprimir`.
- Consulta com protocolo auditada (`consulta-trilha-processo`). Impressão auditada (`imprime-trilha-processo`).

## Fluxo evidenciado
1. Sem protocolo, a tela abre a busca e não consulta.
2. Com protocolo, `TrilhaProcessoService` junta transições dos dois eixos, `activity_log` e decisão, em ordem de data.
3. Protocolo inexistente devolve trilha nula na tela. Não cria processo.
4. Imprimir com protocolo inexistente responde 404. Não gera PDF vazio.
5. O PDF usa a view `documentos.trilha-processo`, folha A4, com emissor e data de emissão, e o nome `trilha-{protocolo}.pdf`.
6. Esta impressão não passa pelo exportador tabular `?formato=`.

## Regras de negócio evidenciadas
- A trilha é do protocolo informado, não a trilha global da auditoria.
- Processo não encontrado não é preenchido com eventos de outro protocolo.

## Validações
- O protocolo vai na query, aparado. Vazio não busca.
- Impressão sem processo encontrado aborta em 404.

## Critérios de aceite
- Protocolo existente devolve o processo e os eventos ordenados.
- Protocolo desconhecido mostra estado vazio na tela e 404 na impressão.
- O PDF identifica quem emitiu e quando.
- Consulta e impressão são auditadas.

## Divergências da redação anterior
Não havia redação anterior neste pacote. A trilha global com fontes atividade, alteração e acesso é outra tela.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`trilha`, `trilhaImprimir`)
- `app/Services/Relatorios/TrilhaProcessoService.php`
- `routes/gestao.php` (`relatorios.trilha`, `relatorios.trilha.imprimir`)

## Não evidenciado
- Exportação CSV ou XLSX desta trilha pelo contrato único.
- Busca por nome ou CNPJ nesta rota.
