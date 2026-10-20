# HU.036 – MANTER GATILHOS DE RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador ajusta título, motivo e situação dos gatilhos que já existem. O gatilho não nasce nesta tela e não é excluído. A lista não pagina e ordena pelo código. Cada linha mostra código, rótulo, título, motivo e se está ativo. Atualizar grava título e motivo. Código e categoria enviados são ignorados. Desligar inverte a situação e não apaga o registro. Gatilho reativado informa que os processos voltam a ser encaminhados à análise técnica. Gatilho desativado informa que os processos passam a concluir no fluxo expresso. O motor só aplica os gatilhos ativos no roteamento do expresso. Os gatilhos conhecidos são enquadramento ausente, ZEIS especial, dados do processo, regra territorial, localização divergente e zona em empate. A tela lista os que estiverem cadastrados, não um número fixo escrito na interface. O grau municipal do CNAE continua na ficha do CNAE.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter gatilhos de risco](#história-de-usuário-manter-gatilhos-de-risco)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Gatilhos de risco](#tela-1--gatilhos-de-risco)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** ligar ou desligar um gatilho já existente**, de modo que** o fluxo expresso deixe de aplicar o gatilho inativo.

## História de Usuário: Manter gatilhos de risco

**Como** administrador autorizado a manter gatilhos de risco **quero** reescrever o motivo e ligar ou desligar o gatilho**, de modo que** o código permaneça e a mensagem explique o efeito no expresso.

## Pré-requisitos

- Administrador autorizado a manter gatilhos de risco
- Gatilho já cadastrado
- Título com até 255 caracteres e motivo com até 1000

## Dependências

**Passadas**

- O gatilho já existe; código novo não nasce nesta tela

**Futuras**

- O grau municipal do CNAE permanece na ficha do CNAE

---

## Interface de Usuário

### Tela 1 — Gatilhos de risco

**Caminho no menu:** Regras > Gatilhos de risco

*[Print da tela](screenshots/gestao-gatilhos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Motivo**
**Dado que** o gatilho já existe e o administrador está autorizado
**Quando** grava título e motivo
**Então** o código permanece e título e motivo passam a ser os enviados

**Cenário 2: Desligar**
**Dado que** o gatilho está ativo
**Quando** o administrador inverte a situação
**Então** o gatilho fica inativo, continua na lista e a mensagem informa que os processos passam a concluir no fluxo expresso

**Cenário 3: Religar**
**Dado que** o gatilho está inativo
**Quando** o administrador inverte a situação
**Então** a mensagem informa que os processos voltam a ser encaminhados à análise técnica

**Cenário 4: Sem código novo**
**Dado que** o administrador está na tela
**Quando** procura cadastrar ou excluir um gatilho
**Então** só existem a listagem, a atualização de título e motivo e a ativação

---

## Aprovação do Requisito

A Sudoeste Informática esclarece que o desenvolvimento das funcionalidades descritas neste documento foi realizado de acordo com os requisitos e especificações fornecidos pelo cliente. Nossa equipe seguiu rigorosamente as instruções recebidas, garantindo que o sistema esteja alinhado com o que foi solicitado.

Para evitar possíveis retrabalhos ou ajustes futuros, sugerimos que o cliente revise atentamente os requisitos apresentados, verificando se eles atendem plenamente às suas necessidades e expectativas, bem como se estão em conformidade com eventuais normas aplicáveis ao seu negócio.

Reafirmamos nosso compromisso de entregar um software de qualidade que agregue valor às operações do cliente, e a validação cuidadosa dos requisitos é um passo importante para garantir o sucesso do projeto.

| Data | Setor | Nome | Assinatura |
|------|-------|------|------------|
|      |       |      |            |
|      |       |      |            |
|      |       |      |            |
