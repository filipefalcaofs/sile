# Nota técnica — Polígono por inscrição imobiliária como segunda fonte de geometria do lote

**Emitente:** Sudoeste Informática — Viabiliza
**Data:** 2026-10-02
**Relacionado:** ata da reunião de 2026-10-02, decisões 6 e 8 e pendência 9.

---

## 1. O pedido, em uma frase

Autorização e condições de uso para o Viabiliza consultar a camada poligonal **SEMAP (Inscrições Geo)**, publicada no ArcGIS do Município, como **segunda fonte** da geometria do lote quando o Endereçamento da SEDUR não tiver polígono para o endereço.

## 2. Por que isso importa

A reunião de 02/10 fechou duas coisas (decisões 6 e 8):

- **Ponto localiza, polígono zoneia.** O art. 18, § 2º, da Lei nº 9.148/2016 manda aplicar a zona que contiver a **maior porção** do terreno. Isso é interseção de área, não de ponto.
- **Onde o endereçamento tem polígono, usa-se o endereçamento. Onde não tem, não se abre desenho livre ao cidadão.**

Ficou o vazio da pendência 9: o que fazer onde o endereçamento não tem polígono. Hoje a resposta é cair no ponto da inscrição, que não zoneia lote grande nem lote de divisa. Esta nota propõe preencher esse vazio com dado oficial já existente, em vez de desenho do requerente.

## 3. O que foi medido (02/10/2026, contra os serviços ao vivo)

### 3.1 Endereçamento da SEDUR — a primeira fonte

| Item | Medição |
|---|---|
| Serviço | `https://geoserver.sedur.salvador.ba.gov.br/geoserver` — WFS 1.1, `enderecamento:VM_ENDERECAMENTO` |
| Feições | 47.308 polígonos |
| Chave | `CODLOG` + `NUMERACAO_METRICA` (+ `COMPLEMENTO`) |
| Limite | Só endereço que passou por endereçamento. `CODLOG` + número **não é chave única**: `CODLOG 9745`, nº 73 devolve 4 polígonos distintos (blocos A, B, C, D); o complemento desempata |

A estimativa de 58 mil citada pela Elba na reunião está na mesma ordem de grandeza do medido (47.308).

### 3.2 SEMAP (Inscrições Geo) — a fonte proposta como fallback

| Item | Medição |
|---|---|
| Serviço | `https://geo.salvador.ba.gov.br/arcgis/rest/services/MAPA_BASE/MAPA_BASE_SEFAZ/MapServer/18` |
| Nome publicado | `SEMAP (Inscrições Geo)` |
| Geometria | `esriGeometryPolygon` |
| Feições | **155.259**, das quais **155.202 com o campo `INSCRICAO` preenchido** |
| Chave | `INSCRICAO` (texto). Também traz `CODLOG`, `PORTA`, `N_METRICO`, `SUBUNIDADE`, `BLOCO`, `LOTE`, `QUADRA`, `CONDOMINIO`, `AREA_M2`, `CAT_DE_USO` |
| Consulta | `/query?where=INSCRICAO='...'&returnGeometry=true&outSR=4326&f=geojson` responde GeoJSON em **~0,25 s** |
| Projeção nativa | EPSG:31984 (SIRGAS 2000 / UTM 24S), com reprojeção para 4326 pelo próprio serviço |
| Acesso hoje | Responde **sem token** a partir da rede de desenvolvimento |

O ambiente é o ArcGIS Enterprise 11.5 do Município (`geo.salvador.ba.gov.br`), por trás do portal “GeoSalvador — Cadastro Multifinalitário de Salvador”. O mesmo serviço `MAPA_BASE_SEFAZ` republica, na camada 16, o **Endereçamento (SEDUR)** — ou seja, as duas fontes já convivem no mesmo mapa base.

### 3.3 O caso que prova o ganho

A inscrição **344672** (Avenida Lafayette Coutinho, `CODLOG 212`, nº 6) é o processo real que motivou a correção de localização em setembro: a via mais próxima apontava outro logradouro e o nº 6 da via 212 **não existe no endereçamento**.

| Consulta | Resultado medido |
|---|---|
| Endereçamento SEDUR, `CODLOG 212` e nº 6 | **0 feições** |
| Endereçamento SEDUR, toda a via 212 | 11 feições |
| SEMAP, `CODLOG 212` | **101 feições** |
| SEMAP, `INSCRICAO = '344672'` | **1 polígono**, 5 vértices, 234,02 m², `CODLOG 212`, `PORTA 06`, uso comercial |

Ou seja: exatamente onde a primeira fonte não tem geometria, a segunda tem — e com a inscrição como chave, que é o dado que o processo da JUCEB já traz.

## 4. O que se propõe

Ordem de precedência da geometria do lote no Viabiliza, sem desenho livre do cidadão em nenhuma hipótese:

1. **Endereçamento da SEDUR** (`CODLOG` + número + complemento). Primeira fonte, mantida como está.
2. **SEMAP (Inscrições Geo)** pela inscrição imobiliária. Entra só quando a primeira não tem polígono ou é ambígua.
3. **Ponto da inscrição** (Cadastro Imobiliário), como é hoje, quando não houver polígono em nenhuma das duas.

Em todos os casos o processo registra **qual fonte decidiu a zona** e, havendo mais de uma zona sob o lote, o **percentual de cada porção** — visível na ficha de análise e na auditoria. Divergência relevante entre as duas fontes encaminha o processo à análise técnica em vez de arbitrar.

## 5. O que precisamos da SEDUR, da SEFAZ e da COGEL

| # | Questão | A quem cabe |
|---|---|---|
| 1 | Autorização formal para o Viabiliza consultar a camada 18 (`SEMAP — Inscrições Geo`) em homologação e em produção | SEFAZ (titular do dado) com anuência da SEDUR |
| 2 | Quem é o titular e o mantenedor atual do serviço `MAPA_BASE_SEFAZ` — SEFAZ ou COGEL, após a transferência do ambiente | SEFAZ / COGEL |
| 3 | Forma de acesso em produção: segue aberto, passa a exigir token ou entra em allowlist de IP (como já ocorre com o GeoServer da SEDUR) | COGEL |
| 4 | Periodicidade de atualização da camada e data da última carga (os campos `FONTE`, `REVISOR` e `DATA_DO_GE` vieram vazios na amostra) | SEFAZ |
| 5 | Cobertura: 155 mil polígonos cobrem qual parcela do cadastro; há plano de ampliação | SEFAZ |
| 6 | O polígono do SEMAP representa o **lote** ou a **projeção da edificação**; e como tratar inscrição de unidade em condomínio (apartamento) | SEFAZ / SEDUR |
| 7 | Confirmação do datum da camada (SIRGAS 2000 / 24S), fechando também a pendência 7 da ata | Elba / Anderson Deiró |
| 8 | Precedência oficial em caso de divergência entre o Endereçamento (SEDUR) e o SEMAP (SEFAZ) para o mesmo endereço | SEDUR e SEFAZ |
| 9 | Disponibilidade e SLA do serviço para consulta síncrona no protocolo | COGEL |

## 6. Observação de segurança — fora do escopo da proposta, mas urgente

Na varredura do catálogo, duas camadas do mesmo ambiente respondem **sem autenticação** e expõem **dado pessoal e fiscal de contribuinte**:

- `MAPA_BASE/MAPA_BASE_SEFAZ/MapServer/210` — `SAT`: nome do contribuinte, CPF/CNPJ, valor venal, IPTU lançado e cobrado.
- `SEFAZ/Inscricoes_IPTU/MapServer/1` — `DRM_IPTU_ANALITICO`: nome/razão social, CPF/CNPJ e total devido.

Confirmamos apenas que os campos retornam preenchidos, sem extrair, armazenar ou tratar qualquer registro. O Viabiliza **não consome e não pretende consumir** essas camadas: a proposta é restrita à camada 18, que não tem campo pessoal algum.

Recomendamos que SEFAZ e COGEL avaliem a exposição à luz da LGPD. Sugerimos tratar este item em comunicação própria, separada desta proposta, pelo canal de segurança da informação.

## 7. Como encaminhar

1. **Caminho técnico (imediato):** enviar esta nota à Elba e ao André, vinculada à pendência 9 da ata de 02/10. A Elba responde pelo cadastro técnico e pelas camadas; o André está conduzindo o acesso às bases e o levantamento de sistemas sem dono.
2. **Caminho formal (autorização):** o dado é da SEFAZ, em ambiente que migrou para a COGEL. A SEDUR sozinha não autoriza. O pedido formal deve sair da SEDUR para a SEFAZ e a COGEL, com as nove questões da seção 5. O André é quem já tem esse trânsito e a interlocução com a procuradoria.
3. **Item de segurança:** a seção 6 segue em mensagem separada, para não misturar a autorização de uso com o alerta de exposição de dado pessoal.
4. **Pauta:** a reunião de segunda, 9h, na SEMIT, é sobre o dado da JUCEB. Esta pauta é outra e não deve disputar aquele espaço — vale como ponto de agenda própria com Elba e SEFAZ.

## 8. Enquanto não houver autorização

O Viabiliza segue com o Endereçamento da SEDUR como fonte única do polígono e o ponto da inscrição como alternativa, exatamente como a decisão 8 da ata. Nada do SEMAP entra no produto antes da autorização formal: a feature fica registrada como bloqueada por dependência externa, com a abstração pronta para ligar quando a resposta vier.
