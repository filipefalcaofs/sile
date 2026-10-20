# HU-107 — Integrar com Cadastro Imobiliário

## Épica
**EP13 — Integrações**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar o cadastro imobiliário da SEFAZ, pelo BFF da SEDUR, para obter endereço e coordenada do imóvel a partir da inscrição.

## História de Usuário
**Como** sistema, **quero** resolver a inscrição imobiliária na base oficial quando a consulta está habilitada, **para** usar o ponto e o endereço devolvidos, e falhar de forma explícita quando o serviço não responde.

## Atores e permissões
- O binding de `PropertyRegistryLookup` é `SedurSefazPropertyRegistryLookup`.
- A consulta só sai se `features.cadastro_imobiliario` está ligado. URL de homologação ou produção é parâmetro, com teste de conexão em `/gestao/config-inscricao-imobiliaria/testar` (`manter-parametros`).
- Quem abre processo (portal ou job do REGIN) dispara a consulta; não há tela pública desta HU.

## Fluxo evidenciado
1. Inscrição sem dígitos gera não encontrado.
2. Com o recurso desligado, a consulta lança `PropertyRegistryUnavailableException` e não inventa coordenada.
3. Com o recurso ligado, há GET na URL configurada, com timeout, retentativa em erro de servidor ou falha de rede, e log com inscrição, HTTP e `CodigoRetorno`. Nome do proprietário e histórico de propriedade são descartados.
4. HTTP 404 ou `CodigoRetorno` diferente de `0` é imóvel não encontrado. Falha de rede, HTTP não sucedido ou corpo inválido é indisponibilidade.
5. Coordenada UTM 24S é convertida para WGS84. Endereço sai do logradouro, número e complemento do imóvel devolvido.

## Regras de negócio evidenciadas
- Desligado não simula ponto.
- Dado de proprietário não entra no resultado mapeado.
- A classe `UnavailablePropertyRegistryLookup` existe e não é o binding atual.

## Validações
- Inscrição é reduzida a dígitos antes da chamada.
- Timeout, retentativas e intervalo vêm de `viabiliza.integrations.inscricao_imobiliaria`.

## Critérios de aceite
- Com a consulta habilitada e retorno `CodigoRetorno` `0`, o resultado traz inscrição, endereço e coordenada convertida, sem nome de proprietário.
- Com a consulta desligada, nenhuma chamada HTTP é feita e a exceção de indisponibilidade é lançada.
- Imóvel inexistente não vira coordenada padrão.

## Divergências da redação anterior
O texto genérico não dizia o bloqueio do interruptor nem o descarte do proprietário. A integração em uso é o BFF da SEDUR sobre o cadastro da SEFAZ, não um adaptador vazio.

## Evidências
- `app/Services/Realty/SedurSefazPropertyRegistryLookup.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Gestao/InscricaoImobiliariaIntegrationController.php`
- `routes/gestao.php` (`config-inscricao-imobiliaria`)

## Não evidenciado
- Consulta com o recurso desligado produzindo endereço fictício.
- Persistência do nome do proprietário.
