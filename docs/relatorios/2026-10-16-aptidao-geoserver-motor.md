# Relatório de aptidão do GeoServer SEDUR para o motor de regras do Viabiliza

Data: 16/10/2026
Servidor avaliado: `https://geoserver.sedur.salvador.ba.gov.br/geoserver` (WFS 1.1.0 / 2.0.0)

## 1. Conclusão

**O GeoServer está apto para a maior parte do motor, mas não para todo ele.** O servidor responde, as 23 camadas configuradas estão no ar e as consultas por ponto funcionam. Há dois problemas ativos:

| # | Problema | Efeito no motor | Responsável pela correção |
|---|---|---|---|
| 1 | Consultas com filtro `BBOX(...)` (e `INTERSECTS` com `POLYGON`) no `CQL_FILTER` recebem a página **"URL Bloqueada"** com HTTP 500 | A zona pela **maior porção do lote** fica `indisponível` | Viabiliza (trocar o filtro) ou SEDUR (liberar no WAF) |
| 2 | Consulta de **endereçamento por ponto** leva ~30 s | Só funciona em fila, com timeout de 45 s; no fluxo síncrono estoura | SEDUR (índice/otimização da camada) |

Um terceiro ponto precisa de acompanhamento: **504 (~5 s)** em volume alto (74.380 em 15/10 e 1.955 em 16/10). A origem por camada ainda não foi apurada.

## 2. O que foi testado

### 2.1 Disponibilidade e camadas
- `GetCapabilities`: HTTP 200 em 0,45 s.
- `GetFeature` (`maxFeatures=1`) nas 23 camadas do config (20 zonas, endereçamento, logradouros, classificação viária): **todas HTTP 200**, de 0,14 a 0,25 s (máquina de desenvolvimento) e de 0,04 a 0,14 s (dentro do pod).

### 2.2 Por tipo de consulta usada pelo motor

| Consulta | Onde é usada | Resultado |
|---|---|---|
| `INTERSECTS(GEOMETRY,SRID=4326;POINT(...))` nas camadas de zona | `GeoServerWfsZonaClient`, `GeoServerWfsCamadaClient` | **OK**: 200 em 0,4 a 0,7 s (5 rodadas). No pod, `identificar(-12.9777,-38.5016)` retornou `identificado`, zona `ZPR 3`. |
| `bbox=x1,y1,x2,y2,EPSG:4326` (parâmetro, fora do CQL) | teste de alternativa | **OK**: 200 em 0,58 s |
| `CQL_FILTER=BBOX(GEOMETRY,...,'EPSG:4326')` | `GeoServerZonaMaiorPorcaoResolver.php:231` | **FALHA**: 500, "URL Bloqueada", ~0,12 s |
| `CQL_FILTER=INTERSECTS(GEOMETRY,SRID=4326;POLYGON(...))` | teste | **FALHA**: 500, "URL Bloqueada" |
| `CQL_FILTER=CODLOG=<n>` (classificação viária) | `GeoServerClassificacaoViariaClient` | HTTP 200 (o código de teste não existe nessa camada; a consulta não é bloqueada) |
| `INTERSECTS` por ponto na camada de endereçamento | `GeoServerEnderecamentoClient` | HTTP 200, porém **30,5 s** (consulta sem resultado) |

Não foi testada a paginação (`sortBy/count/startIndex`), usada só na importação de camadas.

### 2.3 Histórico do pod (`integration_calls`, chamadas ao GeoServer)

| Dia | 200 | 500 | 504 | Sem resposta |
|---|---|---|---|---|
| 14/10 | 102.690 | 0 | 0 | 15 |
| 15/10 | 32.952 | 0 | 74.380 | 49 |
| 16/10 (parcial) | 6.758 | 2.400 | 1.955 | 0 |

Tempo médio dos 200: 171 a 280 ms; máximo 37 a 45 s (consultas de endereçamento).

## 3. Causa do erro 500

Os 500 de 16/10 têm todos o mesmo formato: `CQL_FILTER=BBOX(GEOMETRY,-38.5150708,-12.9812312,-38.5146177,-12.9807086,'EPSG:4326')` em camadas de zona, duração de ~52 ms. O corpo da resposta é a página HTML "URL Bloqueada", não um erro do GeoServer. Isso aponta para um **WAF/proxy** à frente do GeoServer, cuja regra passou a barrar essas expressões.

O código registra que o formato `BBOX` foi conferido em produção em 02/10/2026, e não há 500 em 14 e 15/10. A regra, portanto, é posterior a essas datas.

Limitação: a regra exata do WAF não é conhecida. A relação "BBOX/POLYGON no CQL são bloqueados; INTERSECTS com POINT e `bbox=` passam" vem dos testes acima.

## 4. Impacto no motor de regras

- Etapas que usam **zona por ponto**, **via/classificação viária** e **demais camadas por ponto**: funcionam.
- Etapa de **zona pela maior porção do lote** (`GeoServerZonaMaiorPorcaoResolver`): retorna `indisponivel` ("Camada de zona sem resposta do GeoServer SEDUR") em todo processo com polígono, enquanto o filtro `BBOX` for usado.
- **Endereçamento por ponto**: depende de execução em fila; no caminho síncrono estoura o timeout.

## 5. Recomendações

1. **Viabiliza:** trocar o `CQL_FILTER=BBOX(...)` do resolver pelo parâmetro `bbox=x1,y1,x2,y2,EPSG:4326`, que passa no WAF. O resto do resolver (interseção local no PostGIS) não muda. Incluir teste automatizado do formato da URL.
2. **SEDUR (alternativa ou complemento):** liberar no WAF os filtros `BBOX(` e `POLYGON(` no `CQL_FILTER` do endpoint `/geoserver/wfs`, ou informar qual regra foi aplicada.
3. **SEDUR:** otimizar a camada `enderecamento:VM_ENDERECAMENTO` (índice espacial) para consulta por ponto abaixo de 5 s.
4. **Viabiliza:** apurar no pod os 504 agrupados por `typeName` e por hora, para separar sobrecarga do gateway de consulta pesada.
5. **Monitoramento:** acompanhar a proporção de 5xx em `integration_calls` por dia, com alerta acima de 1%.

## 6. Como reproduzir

```bash
# falha (WAF)
curl -G 'https://geoserver.sedur.salvador.ba.gov.br/geoserver/wfs' \
  --data-urlencode service=WFS --data-urlencode version=1.1.0 --data-urlencode request=GetFeature \
  --data-urlencode typeName=louos_zpr3:VM_L_Z_USO_ZPR_3 --data-urlencode outputFormat=application/json \
  --data-urlencode "CQL_FILTER=BBOX(GEOMETRY,-38.5150708,-12.9812312,-38.5146177,-12.9807086,'EPSG:4326')"

# funciona
curl -G 'https://geoserver.sedur.salvador.ba.gov.br/geoserver/wfs' \
  --data-urlencode service=WFS --data-urlencode version=1.1.0 --data-urlencode request=GetFeature \
  --data-urlencode typeName=louos_zpr3:VM_L_Z_USO_ZPR_3 --data-urlencode outputFormat=application/json \
  --data-urlencode "bbox=-38.5150708,-12.9812312,-38.5146177,-12.9807086,EPSG:4326"
```
