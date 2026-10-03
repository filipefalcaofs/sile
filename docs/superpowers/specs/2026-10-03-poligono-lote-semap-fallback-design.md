# Spec — SEMAP (Inscrições Geo) como fonte do polígono do lote, em cadeia com o Endereçamento

Data: 2026-10-03
Relacionado: ata de 2026-10-02 (decisões 6 e 8, pendência 9), nota técnica `docs/integracoes/proposta-poligono-por-inscricao-sefaz.md`, spec `2026-10-02-poligono-enderecamento-zona-design.md`.

## 1. Problema

A zona do processo decide a viabilidade pelo Quadro 10, e o art. 18, § 2º, da Lei 9.148/2016 manda aplicar a zona de **maior porção do terreno**. O decisor da maior porção já existe (`ZonaPorcaoDecisor`, `ZonaLimitrofeService`), mas **nenhuma fonte alimenta o polígono do lote**: o endereçamento da SEDUR ainda não foi implementado e o processo da JUCEB não traz geometria. Sem polígono, a regra responde "falta a geometria do terreno" e todo processo de divisa vai à análise.

O endereçamento da SEDUR, quando entrar, não resolve sozinho: são 47.308 feições, só de endereço licenciado, e `CODLOG` + número **não é chave única**. O caso real que motivou a correção de setembro (inscrição 344672, `CODLOG 212`, nº 6) **não existe** no endereçamento.

## 2. Fontes verificadas (produção, 2026-10-03)

Medições próprias, contra o serviço ao vivo, sem token, de máquina de desenvolvimento.

| Item | Medição |
|---|---|
| Serviço | `https://geo.salvador.ba.gov.br/arcgis/rest/services/MAPA_BASE/MAPA_BASE_SEFAZ/MapServer/18` (`SEMAP — Inscrições Geo`) |
| Consulta | `/query?where=INSCRICAO='...'&returnGeometry=true&outSR=4326&f=geojson` |
| Inscrição 344672 | HTTP 200, 1 feição, `Polygon` de 5 vértices, em 4326 na ordem [longitude, latitude], `CODLOG 212`, `PORTA 06` |
| Inscrições 3991326 e 5756944 | HTTP 200, 1 feição cada |
| Inscrição inexistente (`00000000000`) e sem polígono (`9919295`) | HTTP 200, `features: []` — ausência é resposta normal, não erro |
| Latência | ~0,21 s em regime; **primeira chamada ao serviço levou 15,6 s** (partida a frio) |
| `AREA_M2` | voltou **0** na feição 344672, embora a nota de 02/10 tenha medido 234,02 m² — **campo não confiável** |
| Campo pessoal | nenhum. A varredura de 02/10 registrou a camada 18 explicitamente como sem campo pessoal |

Para contraste, o Endereçamento (`enderecamento:VM_ENDERECAMENTO`, WFS do GeoServer SEDUR) tem 47.308 feições, chave `CODLOG` + `NUMERACAO_METRICA` (+ `COMPLEMENTO`), e **0 feições** para `CODLOG 212` nº 6.

## 3. Decisões

1. **Cadeia de fontes, não fonte única.** Um resolvedor percorre fontes de polígono do lote em ordem e para na primeira que devolve geometria: **(1) Endereçamento SEDUR → (2) SEMAP por inscrição → (3) nenhuma**. Sem polígono em nenhuma, a zona **não** é decidida por maior porção e o processo segue como hoje (ponto localiza; veredito locacional pendente vai à análise). Desenho livre do cidadão continua fora (decisão 8 da ata).

2. **Ambíguo e não encontrado caem para a próxima fonte.** O endereçamento é ambíguo justamente quando `CODLOG` + número devolve blocos distintos; a inscrição do SEMAP desempata. `indisponivel` (falha HTTP, toggle desligado) também tenta a próxima, e o motivo de cada fonte fica registrado.

3. **A chave do SEMAP é a inscrição imobiliária**, que o processo da JUCEB já traz. Sem inscrição, a fonte devolve `nao_encontrado` sem chamar o serviço.

4. **Só geometria e identificação do lote são retidas.** Guardamos o GeoJSON, `INSCRICAO`, `CODLOG` e `PORTA`. A camada não tem campo pessoal. As camadas 210 (`SAT`) e `Inscricoes_IPTU/1` do mesmo ambiente, que expõem nome e CPF, **não são e não serão consumidas**.

5. **A área vem do PostGIS, nunca do `AREA_M2`.** O campo voltou 0 numa feição com área real. A interseção e as porções já são medidas em `geography` no banco da aplicação.

6. **Reprojeção é do serviço.** Pedimos `outSR=4326` e validamos que a geometria chega em graus, ordem [longitude, latitude]. A pendência 7 da ata (datum nativo da camada) não bloqueia o uso, porque não lemos a projeção nativa.

7. **Consulta fora do caminho da resposta.** A partida a frio medida foi de 15,6 s. A busca roda depois do commit do protocolo, junto de `localizarPelaInscricao` (que já é pós-transação), e no job do recebimento REGIN. O ack do REGIN e o protocolo do portal não esperam o ArcGIS. Timeout de 20 s, uma tentativa, memo por inscrição na execução.

8. **Interruptor e URL parametrizados.** `features.arcgis_semap` (booleano, default ligado) e a URL base da camada entram no catálogo de parâmetros — é integração externa que muda entre ambientes, exatamente o caso previsto na regra de parametrização. Sem limiar percentual: a lei diz maior porção.

9. **Uma coluna de polígono do lote, com a fonte.** `lote_poligono_geojson`, `lote_poligono_fonte` (`enderecamento` | `semap`) e `lote_poligono_consultado_em` em `viability_requests`, fora do `fillable`. **Esta coluna substitui** o `address_polygon_geojson` proposto no plano de 02/10: aquele plano, ao ser executado, registra a fonte `enderecamento` nesta mesma coluna, em vez de criar outra.

10. **O zoneamento passa a preferir o polígono oficial.** `ZonaLimitrofeService` hoje recebe o desenho do requerente (`property_polygon_geojson`). Passa a receber, em ordem: polígono do lote oficial → desenho do requerente → nenhum. Assim o processo REGIN, que nunca tem desenho, ganha zoneamento por área.

11. **Divergência de zona entre o polígono oficial e o desenho encaminha à análise**, sem arbitrar, espelhando a HU-037 RN-005. A zona gravada é a do polígono oficial.

12. **Autorização é pendência registrada, não bloqueio técnico.** O serviço responde publicamente hoje e a camada não tem dado pessoal. A feature entra ligada, com a origem e a data de consulta gravadas em cada processo; se a SEFAZ ou a COGEL exigirem token ou allowlist, o interruptor desliga a fonte e a cadeia degrada para o endereçamento, sem reescrita. As nove questões da seção 5 da nota técnica seguem em aberto, em especial a 6 (o polígono é lote ou projeção da edificação) e a 8 (precedência oficial em caso de divergência).

## 4. Onde isso aparece

| Superfície | O que muda |
|---|---|
| Processo | `lote_poligono_fonte` e a data da consulta; zona por maior porção quando há polígono |
| Inventário de camadas | `zona.fonte` e as porções por zona já existem; passam a ser preenchidos nos processos com polígono |
| Ficha de análise e detalhe do processo | Polígono do lote no mapa, com a fonte identificada |
| Auditoria | Evento `geo/poligono-lote` com fonte, status de cada fonte tentada, motivo e contagem de candidatas |

## 5. Critérios de aceite (viram testes)

- **CA-SM-01** Inscrição com uma feição no SEMAP devolve `encontrado`, com o GeoJSON em 4326 e a fonte `semap`.
- **CA-SM-02** Inscrição sem feição devolve `nao_encontrado` e não grava polígono.
- **CA-SM-03** Processo sem inscrição não chama o serviço.
- **CA-SM-04** HTTP 500 ou timeout devolve `indisponivel` com motivo, nunca exceção para o chamador.
- **CA-SM-05** `features.arcgis_semap` desligado devolve `indisponivel` sem chamar o serviço.
- **CA-SM-06** Duas feições com geometrias distintas para a mesma inscrição devolvem `ambiguo` e nada é gravado; geometrias idênticas contam como uma e são gravadas.
- **CA-SM-07** Resposta que não é `FeatureCollection`, ou feição sem geometria de área (`LineString`), não vira polígono do lote.
- **CA-SM-08** Uma consulta por inscrição na mesma execução (memo).
- **CA-CD-01** A cadeia usa o endereçamento quando ele devolve polígono e não chama o SEMAP.
- **CA-CD-02** Endereçamento `nao_encontrado` ou `ambiguo` cai para o SEMAP.
- **CA-CD-03** Todas as fontes sem polígono devolvem `nao_encontrado` e o processo segue sem zona por área.
- **CA-CD-04** O status de cada fonte tentada fica na auditoria.
- **CA-PL-01** O protocolo grava `lote_poligono_geojson`, `lote_poligono_fonte` e `lote_poligono_consultado_em`.
- **CA-PL-02** A gravação acontece fora da transação do protocolo: falha da fonte não desfaz o protocolo.
- **CA-PL-03** O job do recebimento REGIN busca o polígono antes de decidir o expresso.
- **CA-ZN-01** Com polígono do lote, a zona sai da maior porção, ignorando o ponto.
- **CA-ZN-02** Sem polígono do lote e com desenho do requerente, o desenho é usado.
- **CA-ZN-03** Sem polígono nenhum, a zona segue pelo ponto e o veredito pendente vai à análise com o motivo da geometria ausente.

## 6. Riscos

- **Cobertura.** 155.259 feições cobrem parte do cadastro; a parcela exata é a pendência 5. Mitigação: `lote_poligono_fonte` deixa explícito em cada processo o que decidiu.
- **Partida a frio de 15 s.** Mitigação: consulta sempre fora do caminho da resposta, timeout de 20 s, uma tentativa, memo.
- **O polígono pode ser a projeção da edificação, não o lote** (pendência 6). Mitigação: a fonte fica registrada; divergência de zona com o desenho encaminha à análise.
- **Acesso pode fechar.** Mitigação: o interruptor desliga a fonte e a cadeia continua.
- **Dependência de PostGIS** para medir a interseção: fora do PostgreSQL o resultado é `indisponivel` e a zona não sai por área.

## 7. Fora de escopo

- Consumir qualquer outra camada do ArcGIS municipal, em especial as que têm dado pessoal.
- Implementar o cliente do Endereçamento: é o plano de 02/10; aqui entra só o ponto de encaixe na cadeia.
- Desenhar polígono a partir do ponto e da área.
- Aplicar parâmetros urbanísticos por porção do terreno.
