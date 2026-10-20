# HU-038 — Geocodificar endereço

## Épica
**EP04 — Georreferenciamento e Território**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Converter um texto de endereço em latitude e longitude, usando o provedor de geocodificação configurado, e recusar resultado fora de Salvador.

## História de Usuário
**Como** servidor com permissão de consultar território,  
**quero** informar um endereço e receber a coordenada correspondente,  
**para** posicionar o imóvel no mapa sem digitar latitude e longitude.

## Atores e permissões
- Consulta: permissão `consultar-territorio` (papéis administrador, Analista Terceirizado e Coordenador). O cidadão não tem essa permissão.
- O sistema chama o provedor HTTP. O parâmetro `geocoding` liga ou desliga a chamada.
- A URL base fica em `integrations.geocoding.base_url` (padrão de configuração aponta para o Nominatim de homologação da Prefeitura).

## Fluxo evidenciado
1. A tela de consulta territorial (`/gestao/territorio`) envia `POST /gestao/territorio/geocodificar` com o campo `address`.
2. Se o parâmetro `geocoding` está desligado, a resposta é 422, nenhuma chamada HTTP sai, e a auditoria registra `bloqueado` com motivo `toggle-desativado`. A mensagem pede para informar o ponto no mapa.
3. Com o recurso ligado, `NominatimGeocoder` consulta `{base_url}/search` (`format=jsonv2`, `addressdetails=1`, `countrycodes=br`, `limit=1`), com User-Agent, timeout, retentativas e cache.
4. O primeiro resultado vira `GeocodeResult` (latitude, longitude, nome de exibição, confiança a partir de `importance`, endereço detalhado).
5. `estaEmSalvador()` recusa estado diferente de Bahia e município diferente de Salvador. Sem nome de município, usa a caixa aproximada de latitude −13,02 a −12,73 e longitude −38,72 a −38,30.
6. Sucesso devolve 200 e audita `sucesso` com o host do provedor. Endereço sem resultado devolve 404. Falha de rede, HTTP ou exceção inesperada devolve 503. Em todos os casos a trilha `territorio` / `geocodificacao` é gravada.
7. A consulta prévia por endereço (`ConsultaViabilidadeService::consultarPorEndereco`) reutiliza o mesmo geocoder e propaga a recusa de fora de Salvador (`ForaDeSalvadorException`).

## Regras de negócio evidenciadas
- O cache (TTL em `integrations.geocoding.cache_ttl`, padrão 24 horas) guarda só sucesso. Endereço não encontrado e falha do provedor não ficam em cache.
- A rota usa o limitador `geocoding` (padrão 60 requisições por minuto).
- Com a geocodificação desligada, o posicionamento manual no mapa continua disponível.
- A ordem das coordenadas no DTO é nomeada (latitude e longitude). GeoJSON usa longitude, latitude.

## Validações
- `address` obrigatório, texto, mínimo 3 e máximo 255 caracteres.
- Corpo vazio, HTML ou objeto sem o primeiro resultado é tratado como endereço não localizado.
- Resultado fora do município de Salvador é bloqueado com HTTP 422.

## Critérios de aceite
**Dado** que a geocodificação está ligada e o provedor devolve um ponto em Salvador,  
**Quando** o servidor envia um endereço válido,  
**Então** o sistema devolve latitude, longitude, nome de exibição, confiança e endereço, e registra auditoria de sucesso.

**Dado** que o parâmetro `geocoding` está desligado,  
**Quando** a geocodificação é solicitada,  
**Então** nenhuma chamada HTTP é feita, a resposta é 422 e a auditoria fica `bloqueado`.

**Dado** que o provedor localiza um endereço fora de Salvador, ou não devolve resultado, ou está indisponível,  
**Quando** a geocodificação é solicitada,  
**Então** a resposta é 422, 404 ou 503, respectivamente, com auditoria, sem coordenada inventada.

## Divergências da redação anterior
A redação anterior descrevia um fluxo genérico de “validar, integrar e auditar”, sem o provedor Nominatim, o recorte municipal, o interruptor, o cache só de sucesso nem os códigos HTTP reais.

## Evidências
- `GeocodeController`, `NominatimGeocoder`, `GeocodeResult`, `GeocodeRequest`
- Rota `gestao.territorio.geocodificar`
- `ConsultaViabilidadeService::consultarPorEndereco`

## Não evidenciado
- Geocodificação autenticada no portal do cidadão pela rota de território da gestão.
- Escolha entre vários candidatos: a consulta pede `limit=1`.
- Confirmação humana do ponto antes de gravar o imóvel. A tela de gestão só devolve a coordenada.
