# HU-035 — Manter classificação de risco

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Registrar, na ficha do CNAE, o grau de risco municipal da versão vigente.

## História de Usuário
**Como** administrador com `manter-cnaes`,
**quero** informar o grau de risco municipal ao cadastrar ou editar o CNAE,
**para** a classificação vigente da subclasse ficar gravada sem uma segunda tela.

## Atores e permissões
- A ficha de criação e de edição exige `manter-cnaes`. A listagem que leva à ficha exige `consultar-cnaes`, mas abrir a edição exige a permissão de manutenção.
- Menu Regras, item CNAEs. Não há item de menu «Classificação de risco».
- Os gatilhos que derrubam o processo para análise técnica são outra tela, a HU-036 (`/gestao/gatilhos-risco`).

## Fluxo evidenciado
1. Criar e editar enviam a lista `niveisMunicipais`: valores `baixo_a`, `baixo_b` e `alto`, com rótulos Baixo, Médio Risco e Alto.
2. Se não houver versão vigente do domínio `risco_municipal`, a ficha de edição informa `semVersaoMunicipal` e a gravação é recusada.
3. Na gravação, `RiskClassification` faz `updateOrCreate` pela versão vigente e pelo código do CNAE, com o `risco_municipal` informado.
4. Essa gravação da ficha não abre rascunho nem exige segundo usuário. O comentário do controller reserva a publicação em lote com quatro olhos ao import oficial (`RiscoMunicipalImportService`).
5. Trocar o código do CNAE realoca a classificação que apontava para o código anterior.

## Regras de negócio evidenciadas
- Os níveis persistidos são os três do enum `RiscoMunicipal`. A SEDUR os chama Baixo, Médio risco e Alto. Médio risco é a chave `baixo_b`. A severidade usada pelo motor é Baixo < Médio risco < Alto.
- O enum documenta o Decreto nº 41.758/2026 como fonte dos três níveis. O comentário do `CnaeController` e a exceção de nível desconhecido em `fromDecreto` ainda citam o Decreto nº 32.636/2020.
- Nível fora de `BAIXO A`, `BAIXO B` e `ALTO` não é convertido por `fromDecreto`.
- A ficha não grava condicionante sanitária nem reclassifica o grau pela resposta do requerente.

## Validações
- `risco_municipal` obrigatório no cadastro e na edição, restrito ao enum `RiscoMunicipal`.
- Sem versão vigente, o controller devolve erro e não grava a classificação.

## Critérios de aceite
### CA-01 — Grau na ficha
**Dado** versão vigente de risco municipal e usuário com `manter-cnaes`,
**Quando** salva o CNAE com um dos três graus,
**Então** a classificação daquele código na versão vigente fica com o grau informado.

### CA-02 — Grau obrigatório
**Dado** um envio de cadastro ou edição sem `risco_municipal` válido,
**Quando** a validação roda,
**Então** a gravação é recusada.

### CA-03 — Sem quatro olhos na ficha
**Dado** o autor da alteração na ficha,
**Quando** salva o grau,
**Então** a linha vigente é atualizada na hora, sem proposta de segundo usuário.

### CA-04 — Rótulos
**Dado** a abertura da ficha,
**Quando** o servidor monta `niveisMunicipais`,
**Então** `baixo_a` aparece como Baixo, `baixo_b` como Médio Risco e `alto` como Alto.

## Divergências da redação anterior
A redação anterior pedia classificação parametrizável por CNAE e por condicionante, baixo risco no fluxo expresso e alto risco sempre em análise técnica. A tela desta HU só grava o grau municipal na versão vigente. Condicionante sanitária saiu da ficha (HU-030). O roteamento do expresso pelos gatilhos é a HU-036, não esta ficha. A publicação em lote com quatro olhos não é o fluxo da tela.

## Evidências
- `CnaeController::store`, `update`, `niveisMunicipais`, `versaoMunicipalVigente`.
- `StoreCnaeRequest` e `UpdateCnaeRequest`, campo `risco_municipal`.
- `app/Enums/RiscoMunicipal.php`.
- `routes/gestao.php`: escrita de CNAE sob `manter-cnaes`. A rota `/gestao/gatilhos-risco` é outra permissão (`manter-gatilhos-risco`).

## Não evidenciado
- Tela separada de classificação de risco em `/gestao/risco`. O controller declara que a ficha única substituiu essa rota.
- Quatro olhos na alteração feita pela ficha.
- Parametrização, nesta ficha, da regra «baixo risco segue expresso» ou «alto risco vai para análise».
