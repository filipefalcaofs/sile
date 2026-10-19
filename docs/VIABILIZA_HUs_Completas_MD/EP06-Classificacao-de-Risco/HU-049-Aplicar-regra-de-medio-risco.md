# HU-049 — Aplicar regra de médio risco

## Épica
**EP06 — Classificação de Risco**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência. O termo operacional da SEDUR para `baixo_b` é Médio risco.

## Objetivo
Aplicar o Médio risco municipal. A SEDUR chama assim o nível cuja chave interna é `baixo_b`. A tela, a ficha e o resultado da classificação usam o rótulo «Médio Risco». A chave gravada e o mapa de encaminhamento continuam `baixo_b`.

## História de Usuário
**Como** sistema,  
**quero** classificar e encaminhar o Médio risco municipal,  
**para** a operação ver o nome que a SEDUR usa, com a chave `baixo_b` no decreto e no mapa.

## Atores e permissões
- A chave municipal é gravada na classificação do CNAE (`manter-cnaes`) ou na importação da versão `risco_municipal`.
- O destino de `baixo_b` está no parâmetro `risco.mapa_encaminhamento` (`manter-parametros`).
- A dimensão sanitária `medio` só decide o fluxo quando `risco.dimensao_tvl` é `sanitario`. O padrão é `municipal`.

## Fluxo evidenciado
1. O risco municipal tem três níveis. A SEDUR os exibe como Baixo, Médio risco e Alto. As chaves são `baixo_a`, `baixo_b` e `alto`. Médio risco é `baixo_b`.
2. `fromDecreto` lê a planilha do decreto como `BAIXO A`, `BAIXO B` e `ALTO`. `BAIXO B` vira `baixo_b` e, na tela, Médio risco.
3. O rótulo de `RiscoMunicipal::BaixoB` é «Médio Risco». A chave e o mapa de encaminhamento permanecem `baixo_b`.
4. O padrão do mapa manda o Médio risco (`baixo_b`) para `expresso`, o mesmo destino padrão do Baixo (`baixo_a`).
5. A severidade do Médio risco fica entre Baixo e Alto.
6. A dimensão sanitária é outra. Nela, a chave `medio` também se chama «Médio Risco». Essa chave não substitui o Médio risco municipal.
7. Com dimensão decisiva sanitária, o mapa é lido por `baixo`, `medio` e `alto`. O validador do parâmetro municipal exige `baixo_a`, `baixo_b` e `alto`.
8. Ramo da planilha e gatilho ativo continuam prevalecendo, como nos outros níveis.

## Regras de negócio evidenciadas
- Médio risco municipal é o nome da SEDUR para `baixo_b` (`RiscoMunicipal::label`).
- A importação do decreto entra por `BAIXO B`. O sistema não grava uma segunda chave municipal chamada `medio`.
- O Médio risco sanitário (`RiscoSanitario::Medio`, chave `medio`) permanece na dimensão sanitária e não reclassifica o municipal.

## Validações
- Célula municipal diferente de `BAIXO A`, `BAIXO B` e `ALTO` não vira classificação.
- Mapa sem `baixo_b` é inválido.

## Critérios de aceite
**Dado** que a planilha do decreto traz `BAIXO B`,  
**Quando** a linha é importada e classificada na dimensão municipal,  
**Então** o nível gravado é `baixo_b`, a tela mostra Médio risco e o fluxo segue a chave `baixo_b` do mapa.

**Dado** que a célula municipal do decreto é `BAIXO B`,  
**Quando** a importação municipal lê a linha,  
**Então** o CNAE fica em Médio risco. A planilha não precisa trazer a palavra «médio».

**Dado** que a dimensão decisiva continua municipal,  
**Quando** o motor encaminha um CNAE de Médio risco,  
**Então** usa `baixo_b` e não a chave sanitária `medio`.

## Divergências da redação anterior
A revisão de 16/10/2026 tratou Médio risco como se o nível não existisse. A SEDUR mantém o termo: Médio risco é `baixo_b`. A chave interna não muda. O `medio` da dimensão sanitária é outro nível, de outra tabela.

## Evidências
- `RiscoMunicipal` (`baixo_a`, `baixo_b`, `alto`; rótulo de `baixo_b`)
- `RiscoSanitario::Medio`
- `RiscoMunicipalImportService` (contagem só das três chaves)
- Parâmetro `risco.mapa_encaminhamento` (“Decreto 32.636/2020 não tem nível médio”)
- `AppServiceProvider` (mapa exige `baixo_a`, `baixo_b` e `alto`)

## Não evidenciado
- Uma chave municipal chamada `medio`, separada de `baixo_b`. O nome Médio risco já cobre esse nível.
- Mapa padrão que mande o Médio risco à análise. O padrão de `baixo_b` é `expresso`.
- Condicionante municipal que troque o CNAE de Médio risco para outro nível.
