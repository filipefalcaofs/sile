# HU-179 — Painel por bairro

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Agregar solicitações protocoladas por bairro canônico e por zona urbanística já gravada no processo, com decisões e taxa de deferimento.

## História de Usuário
**Como** gestor, **quero** ver onde a atividade se concentra e como foi decidida, **para** ler bairro e zona a partir do que foi identificado no imóvel, sem criar zona para quem não tem.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/geo-bairro`.
- Consulta auditada (`consulta-geo-bairro`).

## Fluxo evidenciado
1. `GeoBairroIndicadorService` devolve resumo, agregação por bairro e agregação por zona.
2. O bairro canônico usa o oficial materializado e, na falta, o digitado. O resumo conta total, bairros distintos e quantos ficaram sem bairro.
3. A zona usa só `zona_codigo` gravado na identificação. Quem não tem zona entra no resumo `sem_zona` e não vira um grupo com zona inventada.
4. Bairro sem decisão fica com taxa nula, não 0%.
5. `?formato=` exporta as solicitações filtradas (`SolicitacoesReportSource`), não a tabela agregada.

## Regras de negócio evidenciadas
- A população é a protocolada (`protocoled_at`).
- Zona desta tela é a materializada no processo, diferente da série “por zona” dos indicadores, que ainda agrupa bairro e declara degradação.

## Validações
- Filtros comuns, inclusive bairro.
- Formato de exportação csv, xlsx ou pdf.

## Critérios de aceite
- A resposta separa resumo, bairros e zonas.
- Processo sem zona não recebe código de zona nesta agregação.
- Taxa de deferimento sem decisão no bairro é nula.
- A consulta é auditada.

## Divergências da redação anterior
Não havia redação anterior neste pacote.

## Evidências
- `app/Http/Controllers/Gestao/RelatorioController.php` (`geoBairro`)
- `app/Services/Relatorios/GeoBairroIndicadorService.php`
- `routes/gestao.php` (`relatorios.geo-bairro`)

## Não evidenciado
- Mapa desenhado nesta rota. O retorno é agregação tabular.
- Consulta ao GeoServer no momento de abrir o painel: a zona usada é a já gravada no processo.
