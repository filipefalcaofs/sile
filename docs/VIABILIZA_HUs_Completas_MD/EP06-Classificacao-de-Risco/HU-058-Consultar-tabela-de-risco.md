# HU-058 — Consultar tabela de risco

## Épica
**EP06 — Classificação de Risco**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar o nível de risco municipal vigente do CNAE na ficha de cadastro e obter a classificação completa pelo motor.

## História de Usuário
**Como** servidor que consulta CNAEs,  
**quero** ver o risco municipal vigente do código,  
**para** conferir a classificação sem abrir a planilha do decreto.

## Atores e permissões
- Listagem de CNAEs: `consultar-cnaes`.
- Abrir a ficha, mesmo para leitura na prática da tela, exige `manter-cnaes`. O controller registra que a ficha não tem modo somente leitura.
- Gatilhos: consulta e edição sob `manter-gatilhos-risco` (`/gestao/gatilhos-risco`). Não há rota separada de consulta.
- Não há rota `/gestao/risco`. A ficha do CNAE substitui essa tela.

## Fluxo evidenciado
1. A listagem mostra código, descrição, situação e classe do CNAE. Não traz a coluna de risco.
2. A ficha de edição carrega a versão vigente de `risco_municipal` e a linha do CNAE. O campo `risco_municipal` é a chave (`baixo_a`, `baixo_b` ou `alto`) ou nulo.
3. As opções de nível são os três casos do enum, com o rótulo de tela: Baixo, Médio Risco e Alto.
4. Sem versão municipal vigente, a ficha marca `semVersaoMunicipal` e a gravação é recusada.
5. A ficha não lista classificação sanitária nem perguntas de condicionante.
6. `RiscoClassificationService::classify` devolve as duas dimensões, o encaminhamento, a fundamentação e as versões. A consulta de viabilidade usa esse resultado. O comando `risco:classificar` aceita gatilhos opcionais no contexto.
7. A exportação de CNAEs segue o exportador de relatórios da listagem. Não é uma tabela de risco separada.

## Regras de negócio evidenciadas
- A consulta da ficha lê somente a versão vigente, não o histórico de versões do mesmo CNAE.
- A opção Médio risco da ficha municipal é `baixo_b`, nome usado pela SEDUR. A ficha não oferece uma segunda chave municipal `medio`.
- Condicionantes textuais da linha municipal não aparecem nesta ficha. Elas vão para a fundamentação quando o motor classifica.

## Validações
- A ficha só abre para quem tem `manter-cnaes`.
- Nível exibido é um valor do enum ou ausência de linha.

## Critérios de aceite
**Dado** que existe versão municipal vigente e linha para o CNAE,  
**Quando** o mantenedor abre a ficha,  
**Então** o campo mostra `baixo_a`, `baixo_b` ou `alto`.

**Dado** que não há versão municipal vigente,  
**Quando** a ficha abre,  
**Então** o sistema informa a ausência da versão e não apresenta uma classificação inventada.

**Dado** que a consulta de viabilidade classifica o CNAE,  
**Quando** o resultado de risco é lido,  
**Então** municipal, sanitário, encaminhamento, fundamentação e versões vêm do motor.

## Divergências da redação anterior
A redação anterior descrevia uma consulta genérica da tabela de risco. O código consulta o nível na ficha do CNAE e a classificação completa só pelo serviço.

## Evidências
- `CnaeController::index` e `edit`
- `RiscoClassificationService::classify`
- `RiscoClassificarCommand`
- Ausência de rota `gestao/risco`

## Não evidenciado
- Tela que liste, filtrável, todos os CNAEs com nível municipal e sanitário lado a lado.
- Consulta do histórico de versões de risco na ficha.
- Exibição das condicionantes textuais do decreto na ficha do CNAE.
