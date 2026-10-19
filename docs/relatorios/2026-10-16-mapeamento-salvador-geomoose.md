# Inspeção do visualizador "Mapeamento Salvador" (GeoMoose)

Data: 16/10/2026
URL inspecionada: `https://mapeamento.salvador.ba.gov.br/geo/desktop/index.html`
Objetivo: saber se o visualizador oferece uma fonte de lote ou de localização por inscrição que o Viabiliza possa usar no lugar do SEMAP, que hoje recusa as consultas com "499 Token Required".

Método: somente leituras públicas (arquivos estáticos do visualizador e consultas de metadados e de amostra aos serviços que o próprio navegador consulta). Nenhum dado de processo foi enviado. Os arquivos baixados ficaram fora do repositório.

## 1. Conclusão

**O visualizador não resolve o problema do SEMAP.** Ele tem uma camada de lotes desenhada, mas sem atributo algum e sem como consultá-la por inscrição.

| Pergunta | Resposta |
|---|---|
| Tem camada de lote? | Tem desenho de lotes ("Área de Propriedade Particular"), visível no WMS. |
| Dá para achar um lote por inscrição? | **Não.** A camada não tem atributos (só geometria). |
| Dá para achar o lote por ponto? | **Não confirmado.** Uma consulta de feição por ponto (GetFeatureInfo) e o WFS devolveram vazio. |
| Há outra fonte aberta de logradouro? | O serviço de eixos é só um intermediário do GeoServer da SEDUR, e hoje está quebrado (item 4.3). |

## 2. O que é

- Aplicação **GeoMoose** (cliente web de mapas, MIT) servida em `/geo/desktop/`, sobre **MapServer** (CGI em `/app/mapserv`).
- Configuração em `config.js`: `mapserver_url: '/app/mapserv'` e `mapfile_root: '/srv/geoapp/data/'`.
- O mapa é descrito por `mapbook.xml` (43,6 KB, 90 fontes, 173 camadas). Centro padrão da vista: `[-4278080, -1445884]` (Web Mercator, ~ `-12.879, -38.431`). **O `loc` do link recebido é esse centro padrão**, não um imóvel específico.
- Projeção adicional registrada: `EPSG:31984` (SIRGAS 2000 / UTM 24S).
- Serviços registrados no cliente: identificar, buscar, selecionar, geocodificar (OSM), "encontre-me", zoom, medição e impressão. A busca por logradouro (`search-eixos`) usa a camada `eixos-wfs/eixos`, campo `Toponim`.
- O `app.js` mantém restos do exemplo do GeoMoose, como o aviso "THIS IS FOR DEVELOPMENT PURPOSES ONLY" e uma busca de parcelas de demonstração (`vector-parcels/parcels`, com campos `OWNER_NAME` e outros de um exemplo norte-americano). Essa camada não existe no mapbook.

## 3. Inventário das 90 fontes do mapbook

86 são `mapserver`; as demais: 1 `vector` (rascunho), 1 `mapserver-wfs` (`eixos-wfs`), 1 `wms` (ortoimagem) e 1 `xyz` (tiles do OpenStreetMap).

| Grupo (prefixo) | Qtde | Mapfile | Conteúdo |
|---|---|---|---|
| `cbge` | 17 | `cdgv/cbge/cbge.map` | Classes base do MTGE: propriedade particular, quadra, arruamento, passeio, meio-fio, praça, largo, estacionamento etc. |
| `hid` | 15 | `cdgv/hid/hid.map` | Hidrografia |
| `tra` | 8 | `cdgv/tra/tra.map` | Transporte (ponte, viaduto, pátio) |
| `rel` | 7 | `cdgv/rel/rel.map` | Relevo (aterro, corte) |
| `emu` | 6 | `cdgv/emu/emu.map` | Mobiliário urbano (ciclovia, escadaria, acesso) |
| `edf` | 5 | `cdgv/edf/edf.map` | Edificações (total, cobertura, marquise, teto de pavimento) |
| `laz` | 5 | `cdgv/laz/laz.map` | Lazer (piscina, campo, arquibancada) |
| `enc` | 4 | `cdgv/enc/enc.map` | Energia e comunicação |
| `hdv`, `ver` | 2 + 2 | `hdv.map`, `ver.map` | Hidrovias; árvores e jardim |
| `aer`, `dut`, `fer`, `veg` | 1 cada | `cdgv/*` | Aeroporto, dutos, ferrovia, vegetação |
| shapefiles | 8 | `shp/*` | `apoiobasico`, `area_aprovada`, `artsic2006`, `art1k`, `eixos`, `bairros`, `pbairros`, `apcp`, `limitessa` |
| base | 4 | — | Ortoimagem 2016/2017 (WMS), OpenStreetMap, 2 réguas de escala |

Nomes que lembram lote ou cadastro: `cbge_area_de_propriedade_particular_a`, `cbge_quadra_a`, `edf_edificacao_total`, `area_aprovada`. Não existe camada com o nome de lote, parcela, inscrição ou IPTU. Detalhes das camadas `area_aprovada`, `apcp`, `art1k` e `artsic2006` não foram inspecionados.

## 4. Endpoints e resultados dos testes

### 4.1 Endpoints encontrados

| Serviço | URL | Acesso |
|---|---|---|
| WMS de ortoimagem | `http://mapeamento.salvador.ba.gov.br/wms` (redireciona 301 para https) | Aberto. Camada `Ortoimagem_Salvador_2016_2017`, projeção EPSG:31984. |
| MapServer, classes base | `/app/mapserv?map=/srv/geoapp/data/cdgv/cbge/cbge.map` | Aberto. WMS 1.3.0 e WFS 2.0.0 respondem GetCapabilities sem token. |
| MapServer, edificações | `/app/mapserv?map=/srv/geoapp/data/cdgv/edf/edf.map` | Aberto (WFS GetCapabilities 200). |
| MapServer, eixos | `/app/mapserv?map=/srv/geoapp/data/shp/eixos/eixos.map` | Aberto, mas quebrado (item 4.3). |

Nenhum dos testes pediu token. O WFS do `cbge` declara 17 feature types, com operações GetCapabilities, DescribeFeatureType, GetFeature, GetPropertyValue e consultas armazenadas.

### 4.2 Camadas de lote e quadra

Teste de referência: lote 1125 (`CODLOG` 4923, número 7532), polígono obtido na camada `enderecamento:VM_ENDERECAMENTO` do GeoServer da SEDUR (centro em UTM 24S x=564831,7 y=8569581,0; cerca de 85 m x 92 m).

| Consulta | Resultado |
|---|---|
| WMS GetMap de `cbge_area_de_propriedade_particular_a` em 300 m ao redor do lote | Imagem de 14 KB. **Desenha parcelas lado a lado dentro da quadra**, como lotes. |
| WMS GetMap de `cbge_quadra_a` | Imagem de 11 KB. Desenha o contorno da quadra. |
| WMS GetMap de `edf_edificacao_total` | Imagem de 17 KB (tem desenho). |
| WFS `resultType=hits` em `cbge_area_de_propriedade_particular_a`, `cbge_quadra_a`, `cbge_trecho_arruamento_a`, `edf_edificacao_total` e `_p` | **0 feições** em todas. |
| WFS GetFeature sem filtro, `COUNT=2` | Sem feições. |
| WFS GetFeature com BBOX de 80 m no lote 1125 | Sem feições. |
| WFS DescribeFeatureType de `cbge_area_de_propriedade_particular_a` | **Só o campo `msGeometry`. Nenhum atributo.** |
| WMS GetFeatureInfo no centro do lote 1125 (texto e GML) | Sem resultados. |

O WMS desenha dado e o WFS conta zero, o que sugere configuração do serviço (por exemplo, a projeção declarada) ou camada só de desenho. A causa não foi investigada. Atenção: as camadas declaram `urn:ogc:def:crs:EPSG::26915` (NAD83 / UTM 15N, usado em exemplos nos EUA), que não é um sistema de Salvador. A extensão geográfica declarada cobre Salvador (`-38.533, -13.017` a `-38.293, -12.896`), o que indica rótulo de CRS errado, não dado deslocado.

### 4.3 Camada de eixos (busca de logradouro)

`eixos.map` não tem dado próprio. Ele consulta o GeoServer da SEDUR:

`https://geoserver.sedur.salvador.ba.gov.br/geoserver/sedur/wfs?SERVICE=WFS&VERSION=1.0.0&TYPENAME=sedur:LOGRADOURO`

No teste, o GetFeature de `ms:eixos` falhou com *"Could not resolve host: geoserver.sedur.salvador.ba.gov.br"* (erro de DNS no próprio servidor do mapeamento). **A busca por logradouro do visualizador depende do mesmo GeoServer e está sem funcionar.**

A camada `sedur:LOGRADOURO` existe e responde no GeoServer (LineString, HTTP 200). Ela é diferente da `logradouros:VM_LOGRADOURO` que o Viabiliza usa. Seus atributos não foram inspecionados.

## 5. Observações de segurança (para quem mantém o serviço)

Registro de fatos observados, sem teste de exploração.

1. `config.js` publica o caminho interno `mapfile_root: '/srv/geoapp/data/'`, e o parâmetro `map=` do MapServer aceita o caminho do mapfile pela URL. Os testes usaram só os mapfiles que o mapbook já lista.
2. A mensagem de erro do MapServer expõe o endereço do GeoServer de origem (`geoserver.sedur.salvador.ba.gov.br/geoserver/sedur/wfs`, `TYPENAME=sedur:LOGRADOURO`).
3. O `app.js` ainda traz o aviso "THIS IS FOR DEVELOPMENT PURPOSES ONLY" e o `mapbook` traz restos de exemplo.
4. Os serviços respondem sem autenticação, o que pode ser intencional para um mapa público.

## 6. O que isso significa para o Viabiliza

- **Não substitui o SEMAP.** A camada de propriedade particular não tem atributo e não dá para consultar (WFS vazio, GetFeatureInfo vazio). Só serve como imagem.
- **Possível uso só visual:** o WMS de `cbge_area_de_propriedade_particular_a` e a ortoimagem podem servir para a análise técnica conferir o lote no mapa, se alguém quiser incorporar a imagem na tela do processo. Isso não foi avaliado, e há a ressalva do CRS declarado.
- **A busca por eixos não é independente:** depende do mesmo GeoServer. Ela não acrescenta cobertura.
- O caminho para os processos sem polígono continua sendo o token do SEMAP (ou a decisão sobre usar a rua como linha, e um ponto aproximado).

## 7. O que não foi verificado

- Os atributos reais dos shapefiles (`area_aprovada`, `apcp`, `art1k`, `artsic2006`, `bairros`, `limitessa`, `apoiobasico`).
- Por que o WFS conta 0 feições onde o WMS desenha dado.
- Se o GetFeatureInfo falhou por consulta fora de uma parcela ou porque a camada não é consultável (a consulta foi num único ponto).
- A qualidade e a atualidade dos lotes desenhados (o mapeamento é de 2016/2017).
- Termos de uso e licença dos dados.

## 8. Como reproduzir

```bash
# Capacidades do WFS das classes base (aberto)
curl -s 'https://mapeamento.salvador.ba.gov.br/app/mapserv?map=/srv/geoapp/data/cdgv/cbge/cbge.map&SERVICE=WFS&VERSION=2.0.0&REQUEST=GetCapabilities'

# Desenho dos lotes ao redor de um ponto (UTM 24S, EPSG:31984)
curl -s -o lotes.png 'https://mapeamento.salvador.ba.gov.br/app/mapserv?map=/srv/geoapp/data/cdgv/cbge/cbge.map&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&LAYERS=cbge_area_de_propriedade_particular_a&STYLES=&SRS=EPSG:31984&BBOX=564681,8569431,564981,8569731&WIDTH=400&HEIGHT=400&FORMAT=image/png&TRANSPARENT=TRUE'
```
