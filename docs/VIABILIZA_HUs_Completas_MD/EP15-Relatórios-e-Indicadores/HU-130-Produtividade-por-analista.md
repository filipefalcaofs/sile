# HU-130 — Produtividade por analista

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Medir decisões de análise técnica por analista, com nome só para quem tem permissão nominal.

## História de Usuário
**Como** gestor, **quero** ver a produtividade da equipe, **para** acompanhar volume decidido. Sem a permissão de nome, vejo só o meu recorte e sem identificação.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/produtividade`.
- Nome do analista e visão de todos exigem `relatorios.produtividade.nominal`.
- Sem essa permissão, o escopo é o próprio usuário e o retorno é anonimizado. A consulta é auditada como dado pessoal quando o modo é nominal.

## Fluxo evidenciado
1. `ProdutividadeAnalistaService::porAnalista` lê decisões com `decided_by_user_id`, no recorte.
2. A exportação usa `ProdutividadeReportSource` com o mesmo par nominal/escopo.
3. A tela informa o booleano `nominal` para o front não exibir nome à parte.

## Regras de negócio evidenciadas
- O padrão sem permissão extra é o mais restrito: próprio analista, sem nome.
- A permissão de relatórios sozinha não libera o nome.

## Validações
- Filtros comuns do relatório.
- O escopo não vem da query string; vem da permissão e do usuário autenticado.

## Critérios de aceite
- Com permissão nominal, a lista identifica analistas do recorte.
- Sem ela, não há nome e o conjunto fica no usuário logado.
- Exportar respeita o mesmo corte.

## Divergências da redação anterior
O texto não condicionava o nome a uma permissão específica nem restringia o analista aos próprios números.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`produtividade`)
- `app/Services/Relatorios/ProdutividadeAnalistaService.php`
- `app/Services/Relatorios/Export/Sources/ProdutividadeReportSource.php`

## Não evidenciado
- Ranking nominativo para qualquer usuário que só tenha `consultar-relatorios`.
