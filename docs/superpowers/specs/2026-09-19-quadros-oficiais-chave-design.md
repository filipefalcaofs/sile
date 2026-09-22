# Spec — Quadros e regras oficiais como única chave

Data: 2026-09-19
Origem: ficha de análise com “sem regra no Quadro 10” sobre ZCN-1; PDF oficial do Quadro 10 sem essa zona.

## Decisão

A grafia do PDF da Lei nº 9.148/2016 é a chave. GIS, seed de demo e lookup do motor convertem para ela. Não existe zona inventada na regra.

## Fonte

| Domínio | Arquivo |
|---|---|
| Quadro 10 | `database/data/louos/oficial/quadro10-permissoes.csv` |
| Quadro 11A | `database/data/louos/oficial/quadro11a-condicoes-via.csv` |
| Tratamento / risco | `database/data/regras-20-08-26/` |

CSVs de exemplo (`database/data/louos/quadro10-permissoes.csv` com ZCN-1/ZM-1/ZPAM e `quadro11a-condicoes-via.csv` com `via_local`) saem do repositório.

## Zonas do Quadro 10 (21)

`ZPR 1` `ZPR 2` `ZPR 3` `ZEIS 1`–`5` `ZCMe 1/01` `ZCMe 1/02` `ZCMe 1/03` `ZCMe 2` `ZCMe - CA` `ZCMu 1 - IPITANGA` `ZCMu 2` `ZCLMe` `ZCLMu` `ZDE 1` `ZDE 2` `ZUSI` `ZIT`

`ZCN-1` não é zona da lei. `ZPAM` é camada de restrição, não coluna do Quadro 10.

## Canonicalização

`Quadro10Zona::oficializar()` mapeia alias GIS (`ZPR-1`, `ZPR_1`, `ZPR 1`) para a grafia oficial (`ZPR 1`). Desconhecido permanece, sem inventar permissão.

Usado em:

1. `GeoServerWfsZonaClient` (código da feição)
2. Lookup do Quadro 10 (`buscarPermissaoQuadro10`)

## Demo

`ZonaFicticiaDevSeeder` pinta o Centro com **`ZCMe 2`** (oficial; nR1 permitido no PDF). Geometria continua fictícia e gated. A regra é a matriz oficial.

## Fora de escopo

- Recarregar geometria oficial do GeoServer
- Inventar ZPAM no Quadro 10
- Renomear fixtures isolados de CRUD/sandbox que criam a própria linha (`ZPR-1` sintético)
