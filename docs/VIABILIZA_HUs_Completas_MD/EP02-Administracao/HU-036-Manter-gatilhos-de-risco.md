# HU-036 — Manter gatilhos de risco

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Ajustar título, motivo e situação dos gatilhos que já existem. O código do gatilho não nasce nesta tela.

## História de Usuário
**Como** administrador com `manter-gatilhos-risco`,
**quero** reescrever o motivo do gatilho e ligá-lo ou desligá-lo,
**para** o motor deixar de aplicar o gatilho inativo no roteamento do expresso.

## Atores e permissões
- A tela exige `manter-gatilhos-risco`. Menu Regras, item Gatilhos de risco (`/gestao/gatilhos-risco`).
- O grau municipal do CNAE é a HU-035, na ficha do CNAE, com outra permissão.

## Fluxo evidenciado
1. A lista não pagina. Ordena por `codigo` e devolve id, código, rótulo do código, título, motivo e `ativo` de cada linha de `risk_triggers`.
2. Atualizar grava título e motivo. Código e categoria enviados são ignorados.
3. A ativação inverte `ativo` e não exclui a linha. Gatilho reativado informa que os processos voltam a ser encaminhados à análise técnica. Gatilho desativado informa que os processos passam a concluir no fluxo expresso.
4. A alteração é auditada pelo `HasAuditoria` do model. O controller declara que o motor avalia o escopo `ativos()`.

## Regras de negócio evidenciadas
- Não há criação nem exclusão. Código novo entra por enum, motor e seed.
- O enum `TipoGatilho` declara `enquadramento_ausente`, `zeis_especial`, `dados_do_processo`, `regra_territorial`, `localizacao_divergente` e `zona_empate`. O comentário do controller ainda fala em três gatilhos conhecidos. A tela lista o que estiver na tabela, não um número fixo escrito na interface.
- Desligar o gatilho é a ação que muda o roteamento. O código em si não é editável.

## Validações
- `titulo` obrigatório, até 255.
- `motivo` obrigatório, até 1000.
- Código e categoria não têm regra e não chegam a `validated()`.

## Critérios de aceite
### CA-01 — Motivo
**Dado** um gatilho já semeado e um usuário com `manter-gatilhos-risco`,
**Quando** grava título e motivo,
**Então** o código permanece e título e motivo passam a ser os enviados.

### CA-02 — Desligar
**Dado** um gatilho ativo,
**Quando** o administrador aciona a ativação,
**Então** `ativo` fica falso, o registro continua na lista e a mensagem informa o efeito no fluxo expresso.

### CA-03 — Sem código novo
**Dado** as rotas `gatilhos-risco`,
**Quando** se procura store ou destroy,
**Então** só existem listagem, atualização e ativação.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `gatilhos-risco.index`, `update`, `ativacao.update`.
- `app/Http/Controllers/Gestao/RiskTriggerController.php`.
- `app/Http/Requests/Gestao/UpdateRiskTriggerRequest.php`.
- `app/Enums/TipoGatilho.php`.
- `resources/js/navigation/gestao-nav.ts`: Gatilhos de risco em Regras.

## Não evidenciado
- Cadastro ou exclusão de gatilho pela tela.
- Alteração do código ou da categoria.
- A ficha de grau municipal do CNAE. Isso permanece na HU-035.
