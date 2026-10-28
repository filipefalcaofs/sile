# Nota técnica — SEMAP (Inscrições Geo): polígono da inscrição e ponto do cadastro

**Emitente:** Sudoeste Informática — Viabiliza
**Data:** 23/10/2026
**Uso:** reunião de 26/10/2026
**Relacionado:** nota de 02/10/2026, `docs/integracoes/proposta-poligono-por-inscricao-sefaz.md`

---

## 1. Objeto

Responder ao encaminhamento do NTI/SEDUR de 23/10/2026 sobre a base geo das inscrições: se o dado está desatualizado, se a API da SEFAZ (X e Y) substitui essa base, e se existem polígonos de inscrição em Salvador.

A resposta separa três fontes que convivem no mesmo cadastro e não se substituem.

## 2. Três fontes

Medição de 02/10/2026, contra o ArcGIS municipal ao vivo (`geo.salvador.ba.gov.br`, serviço `MAPA_BASE/MAPA_BASE_SEFAZ`). A API da SEFAZ é a já consumida pelo Viabiliza para localizar o imóvel.

| Fonte | Onde está | Geometria | Volume medido | Papel no Viabiliza |
|---|---|---|---|---|
| SEMAP (Inscrições Geo) | Camada 18 | Polígono (`esriGeometryPolygon`) | 155.259 feições; 155.202 com `INSCRICAO` | Segunda fonte do polígono do lote, pela inscrição |
| SAT | Camada 210 | Ponto (coordenada do imóvel) | 697.707 registros | Não é consumida |
| Cadastro Imobiliário | API da SEFAZ | Ponto (X, Y) | — | Primeira localização do imóvel quando a consulta está ligada |
| Endereçamento SEDUR | GeoServer, `enderecamento:VM_ENDERECAMENTO` | Polígono | 47.308 feições | Primeira fonte do polígono, por CODLOG + número |

Em 23/10/2026 a camada 18 responde código 499 (token obrigatório). A contagem acima não foi refeita nessa data.

## 3. O que a camada 18 é

Nome publicado: `SEMAP (Inscrições Geo)`.

Chave de consulta: `INSCRICAO`. A feição também traz `CODLOG`, `PORTA`, `N_METRICO`, `SUBUNIDADE`, `BLOCO`, `LOTE`, `QUADRA`, `CONDOMINIO`, `CAT_DE_USO` e `PADRAOCONS`. Projeção nativa EPSG:31984 (SIRGAS 2000 / UTM 24S); a consulta pede a saída em 4326.

Caso medido, inscrição **344672** (Avenida Lafayette Coutinho, CODLOG 212, nº 6):

| Consulta | Resultado em 02/10/2026 |
|---|---|
| Endereçamento SEDUR, CODLOG 212 e nº 6 | 0 feições |
| SEMAP, CODLOG 212 | 101 feições |
| SEMAP, inscrição 344672 | 1 polígono, 5 vértices, CODLOG 212, porta 06 |

Em 03/10/2026 a mesma inscrição voltou de novo um polígono. O campo `AREA_M2` voltou 0 e não é usado: a área, quando precisa, sai da geometria.

## 4. O que confere no encaminhamento de 23/10

- As inscrições do **SAT** são pontos. A camada 210 traz a coordenada do imóvel, não o polígono do lote.
- A API da SEFAZ traz X e Y. O Viabiliza já localiza o imóvel por esse ponto.
- Se a carga do SEMAP estiver defasada em relação a esse X e Y, o alerta procede. Na amostra de 02/10, `FONTE`, `REVISOR` e `DATA_DO_GE` vieram vazios. Não há, nesta nota, data de carga.

## 5. O que não descreve o SEMAP

- "Não existem polígonos" vale para o SAT. Não vale para a camada 18, medida como polígono, com 155.259 feições.
- "Quantidade mínima, frente ao total de inscrições georreferenciadas" não descreve esses 155 mil polígonos (cerca de um quinto dos 697 mil registros do SAT). Se a frase se refere a outra camada — polígonos de georreferenciamento de processos — o nome, o serviço e a quantidade precisam ser informados na reunião. Essa camada não é a 18.

## 6. Por que o X e Y não substituem o polígono

O art. 18, § 2º, da Lei nº 9.148/2016 manda aplicar a zona que contiver a maior porção do terreno. Isso é interseção de área. Um ponto no meio do lote não mede a porção e erra lote de divisa e lote grande.

Ordem já adotada no Viabiliza, sem desenho livre do requerente:

1. Endereçamento da SEDUR (CODLOG + número + complemento).
2. SEMAP, pela inscrição, quando o endereçamento não tem polígono ou é ambíguo.
3. Ponto da API da SEFAZ, quando não houver polígono. O ponto localiza. Não vira polígono inventado e não decide a zona por área.

O processo grava qual fonte forneceu a geometria.

## 7. O que a reunião precisa fechar

| # | Pergunta | Por quê |
|---|---|---|
| 1 | Data da última carga do SEMAP e a periodicidade | Sem isso não dá para saber se o polígono está defasado em relação ao X e Y |
| 2 | O polígono é o lote ou a projeção da edificação | Muda o que a maior porção representa |
| 3 | Inscrição de unidade em condomínio (apartamento) devolve o lote, a unidade ou nada | A chave do processo é a inscrição que a Junta envia |
| 4 | Quando o ponto atual da API cair fora do polígono do SEMAP, qual fonte prevalece | Evita o sistema escolher sozinho |
| 5 | Nome e quantidade da camada de polígonos de processo citada em 23/10, se for outra que não a 18 | Para não misturar as bases de novo |

Enquanto 1 e 4 não tiverem resposta, o ponto da API continua localizando o imóvel e o polígono do SEMAP continua sendo a segunda fonte de área, com a fonte registrada em cada processo.
