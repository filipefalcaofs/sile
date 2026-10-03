# Notificação de exposição de dados pessoais

ArcGIS municipal — geo.salvador.ba.gov.br

| Campo | Valor |
|---|---|
| Identificador | SI-ADV-2026-1002 |
| Versão | 1.0 |
| Data | 2 de outubro de 2026 |
| Classificação | TLP:AMBER+STRICT |
| Status | Notificação inicial ao controlador. Sem divulgação pública. |
| Emitente | Sudoeste Informática |
| Contato | fillipe.falcao@sudoesteinformatica.com.br |

TLP:AMBER+STRICT limita o compartilhamento à organização destinatária e às pessoas que precisam agir sobre o achado. Não republicar.

## 1. Síntese

| | |
|---|---|
| Severidade | Crítica |
| Ativo | geo.salvador.ba.gov.br (ArcGIS Enterprise 11.5) |
| Condição | Serviços REST respondem sem autenticação e devolvem dado pessoal e fiscal |
| Volume | Cerca de 1,4 milhão de registros |
| Dado | Nome, CPF ou CNPJ, situação fiscal e, na camada SAT, coordenada do imóvel |
| Pedido | Restringir o acesso hoje e avaliar o incidente à luz da Lei nº 13.709/2018 |

O achado foi incidental. Enquanto se verificava se a Prefeitura publicava uma camada poligonal de lote por inscrição imobiliária, para uso legítimo no novo sistema de viabilidade locacional da SEDUR, estas camadas apareceram no catálogo.

## 2. O que esta notificação é

Notificação ao controlador presumido (SEFAZ e COGEL, que publica o ambiente). Não é teste de invasão, não é inventário completo do SIG municipal e não é comunicação à ANPD. A obrigação do art. 48 da Lei nº 13.709/2018, se houver, é do controlador.

A varredura percorreu 358 serviços consultáveis e selecionou camadas cujo nome de campo indica dado pessoal. Em seguida, uma consulta de um único registro confirmou que o campo volta preenchido. Isso não substitui o inventário do controlador.

## 3. Tratamento da evidência

| Feito | Não feito |
|---|---|
| Contagem de registros devolvida pelo próprio serviço | Extração, cópia ou armazenamento de qualquer registro |
| Confirmação de que o campo de documento volta preenchido | Leitura integral de nome, documento ou valor fiscal |
| Registro dos endpoints e da data | Nova consulta depois desta notificação |

Nenhum dado pessoal foi retido. A verificação ocorreu em 2 de outubro de 2026, por HTTP público, de máquina de desenvolvimento, sem token, sem VPN e sem credencial.

## 4. Ativos afetados

Medição de 2 de outubro de 2026. "Registros" é a contagem do serviço.

| Severidade | Serviço / camada | Registros | Dado exposto |
|---|---|---|---|
| Crítica | ANALISES/ANÁLISES/MapServer/1 ("Uso") e camadas 2, 3, 4, 6 e 7 | 697.707 | Nome e CPF/CNPJ do contribuinte |
| Crítica | MAPA_BASE_SEFAZ/MapServer/210 ("SAT") | 697.707 | Nome, CPF/CNPJ, valor venal, alíquota, IPTU e coordenada do imóvel |
| Crítica | MAPA_BASE_SEFAZ/MapServer/0 ("EMBASA") | 686.475 | Nome e CPF/CNPJ de consumidor de terceiro |
| Crítica | SEFAZ/Inscricoes_IPTU/MapServer/1 ("DRM_IPTU_ANALITICO") e camadas 0 e 3 a 12 | 99.877 | Nome ou razão social, CPF/CNPJ e total devido |
| Alta | MAPA_BASE_SEFAZ/MapServer/19 ("Ofícios localizados") | 116 | Dois pares de nome e CPF por registro |
| Alta | SEFAZ/Inscricoes_IPTU/MapServer/0 ("DRM IPTU — órgãos públicos") | 419 | Nome ou razão social e CPF/CNPJ |
| A revisar | Três camadas "Registro de Imóveis" (MAPA_BASE_SEFAZ/195, MAPA_BASE_Consulta/21, MAPAS_PIDI/21) | 10 cada | CNPJ, telefone e e-mail de cartório. Pode ser dado institucional. |

Fora do escopo: `MAPA_BASE_SEFAZ/MapServer/18` ("SEMAP — Inscrições Geo"), procurada para o lote por inscrição, não tem campo pessoal.

Observações:

- SAT é o conjunto de maior sensibilidade: identificação, situação fiscal e coordenada no mesmo registro.
- EMBASA republica dado de consumidor de outra entidade.
- Camadas nomeadas "SEM CPF/CNPJ" e "SEM Nº DE PORTA" são recortes de qualidade cadastral e também carregam os campos de identificação.

## 5. Como o controlador reproduz

Sem autenticação, nesta ordem:

1. O portal público revela o servidor real em `helperServices`: `https://www.sefaz.salvador.ba.gov.br/geosalvador/sharing/rest/portals/self?f=json`
2. O catálogo lista os serviços: `https://geo.salvador.ba.gov.br/arcgis/rest/services?f=json`
3. Os campos de cada camada estão em `{serviço}/{tipo}/layers?f=json`
4. Um único registro confirma o preenchimento: `query?where=1=1&outFields={campo}&returnGeometry=false&resultRecordCount=1&f=json`

Não é necessário paginar nem pedir geometria para confirmar o achado.

## 6. Impacto

- Confidencialidade: dado pessoal e fiscal de contribuinte acessível a quem conhece a URL.
- Escala: os serviços anunciam paginação e devolvem até 2.000 registros por requisição.
- Correlação: pessoa identificada, imóvel georreferenciado e situação fiscal no mesmo registro. Serve a fraude dirigida.
- Terceiro: dado de consumidor da EMBASA no mesmo ambiente.
- Lei nº 13.709/2018, art. 46: o controlador adota medidas de segurança aptas a proteger o dado. O art. 48 trata da comunicação de incidente que possa acarretar risco ou dano relevante. A avaliação é do controlador e do encarregado.

## 7. Recomendações

Imediato, neste dia:

1. Restringir os serviços da seção 4 por autenticação ou por rede.
2. Preservar os registros de acesso do servidor anteriores a esta data.

Em seguida:

3. Retirar nome e documento das camadas cuja função não depende deles. Camada de uso, padrão construtivo ou qualidade cadastral não precisa de CPF.
4. O encarregado avalia a comunicação à ANPD e aos titulares.
5. Inventário próprio das demais camadas. A varredura por nome de campo não esgota o ambiente.

## 8. Linha do tempo

| Quando | Fato |
|---|---|
| 2 de outubro de 2026 | Descoberta incidental no catálogo público, durante a busca de camada de lote por inscrição |
| 2 de outubro de 2026 | Confirmação de campo preenchido, sem retenção de registro |
| 2 de outubro de 2026 | Esta notificação, versão 1.0. Nenhuma divulgação fora do destinatário |

A Sudoeste não voltará a consultar estas camadas. A demonstração da verificação fica à disposição de quem a SEFAZ e a COGEL indicarem.
