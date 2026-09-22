# Reunião SEFAZ — consulta de TVL — 2026-08-18

Participante principal: **Dayvson Reis** (SEFAZ / WS SIGS).
Tema: **endpoint de consulta de TVL** que o Viabiliza exporá para o SIGS/SEFAZ ao substituir o Simplifica.

## Pacote

| Ordem | Arquivo | Uso |
|---|---|---|
| 1 | [`ata-decisoes.md`](./ata-decisoes.md) | Decisões, fatos de domínio e pendências |
| 2 | [`contrato-endpoint-tvl.md`](./contrato-endpoint-tvl.md) | Contrato JSON atual (WS SIGS) + enum de status |
| 3 | [`transcricao.md`](./transcricao.md) | Transcrição revisada com timestamps |
| 4 | [`2026-08-18-endpoint-consulta-tvl-sefaz-design.md`](../../superpowers/specs/2026-08-18-endpoint-consulta-tvl-sefaz-design.md) | Spec de design do endpoint no Viabiliza |

## Decisão central

O Viabiliza **expõe** o endpoint de consulta de TVL (o SIGS/SEFAZ consome), respondendo no **mesmo
padrão de JSON** do `buscarPorNumeroTVLRegin` atual. 404 = não encontrado. O bloco `taxas` é
obrigatório — é dele que a SEFAZ gera o DAM.

## Próximos passos

1. Receber do Dayvson o JSON de exemplo do endpoint de migração (pendência 1 da ata).
2. Review da spec (fechar `[OPEN-TVL-*]`).
3. Planejar implementação (`/gsd-plan-phase` ou writing-plans) após o review.
