# HU-129 — Tempo médio de análise

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Mostrar o tempo por etapa, o tempo de emissão da TVL agregado, os percentis de viabilidade e os gargalos do recorte.

## História de Usuário
**Como** gestor, **quero** ver onde o tempo de análise se concentra, **para** comparar etapas e não só uma média única.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/tempo`.
- Consulta auditada (`consulta-tempo`).

## Fluxo evidenciado
1. A tela recebe `tempoPorEtapa`, `tempoEmissaoTvl` agregado, percentis do período, percentis por categoria e gargalos.
2. A lista processo a processo da emissão da TVL é outra rota (`tempo-emissao-tvl`).
3. `?formato=` exporta o detalhe por etapa (`TempoAnaliseReportSource`). Não exporta a lista de sedes de escritório virtual, que saiu desta rota.
4. Filtros comuns do request de relatório.

## Regras de negócio evidenciadas
- Tempo de etapa e tempo de emissão agregado convivem na mesma tela, com páginas próprias para o detalhe de TVL.
- Exportação desta rota é a família de tempo por etapa.

## Validações
- Datas, setor, analista, bairro, CNAE e categoria conforme `RelatorioFiltersRequest`.
- Formato de exportação limitado a csv, xlsx e pdf habilitados.

## Critérios de aceite
- A tela mostra etapa, emissão agregada, percentis e gargalos do mesmo recorte.
- Exportar não devolve a lista de escritório virtual.
- A consulta é auditada.

## Divergências da redação anterior
O texto falava num indicador único de tramitação. O código entrega várias medidas e separa a tabela de emissão da TVL.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`tempo`)
- `app/Services/Relatorios/TempoAnaliseService.php`
- `app/Services/Relatorios/TempoViabilidadeService.php`
- `app/Services/Relatorios/GargalosService.php`

## Não evidenciado
- Uma única média, sem etapa, como único conteúdo da rota.
