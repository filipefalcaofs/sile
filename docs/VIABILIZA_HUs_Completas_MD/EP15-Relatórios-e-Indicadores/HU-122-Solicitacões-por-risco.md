# HU-122 — Solicitações por risco

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Agrupar solicitações protocoladas pelo risco municipal vigente do CNAE principal e, na falta dele, pela categoria de análise já gravada.

## História de Usuário
**Como** gestor, **quero** ver o volume por nível de risco, **para** distinguir o que tem classificação do decreto do que só tem a categoria derivada do processo.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/indicadores`.

## Fluxo evidenciado
1. Com versão vigente de risco municipal, o nível é o `risco_municipal` do CNAE principal nessa versão.
2. Sem essa classificação, usa `analysis_category`. Sem as duas, o nível é `nao_classificado`.
3. Cada linha traz `fonte`: `real`, `derivada` ou `indefinida`. Sem versão vigente, a fonte real não aparece.
4. A contagem é de processos distintos. Não inventa nível.

## Regras de negócio evidenciadas
- A fonte acompanha o número para não tratar categoria derivada como classificação do decreto.
- CNAE principal é o marcado `is_primary` na pivô.

## Validações
- Filtro `categoria` é texto opcional.
- Período invertido não agrega.

## Critérios de aceite
- Linha com classificação vigente marca fonte `real`.
- Processo sem classificação e sem categoria sai como `nao_classificado` e fonte `indefinida`.
- Ausência de versão vigente não consulta tabela de classificação para fabricar risco.

## Divergências da redação anterior
O texto pedia só “por classificação”. O código separa risco do decreto, categoria derivada e não classificado.

## Evidências
- `app/Services/Relatorios/IndicadoresViabilidadeService.php` (`porRisco`)
- `app/Http/Controllers/Gestao/RelatorioController.php` (`indicadores`)

## Não evidenciado
- Nível de risco calculado na hora da consulta, fora da versão vigente e da categoria já gravada.
