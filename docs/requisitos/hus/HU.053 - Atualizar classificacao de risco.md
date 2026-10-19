# HU.053 – ATUALIZAR CLASSIFICAÇÃO DE RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem mantém os CNAEs grava o risco municipal do código na versão vigente. A gravação vale na hora: a próxima classificação sem data já lê o valor novo. A ficha não abre versão nova nem exige outra pessoa para publicar. A carga da planilha oficial grava numa versão de risco e rejeita nível desconhecido.

Sem versão municipal vigente, criar ou editar o CNAE com nível é recusado e nada é gravado. A ficha não grava condicionante, observação nem risco sanitário. O risco sanitário tem carga própria, com Baixo, Médio risco e Alto, e a ficha do CNAE não o atualiza.

Na importação municipal, o cabeçalho precisa ser o esperado. Código que não tenha sete dígitos, código repetido ou nível fora de Baixo, do nível do meio e de Alto entra no relatório de rejeitados e não é gravado. Atualizar o texto da condicionante não muda o nível. Reproduzir uma decisão antiga continua possível informando a data: a ficha não altera essa versão histórica, só a vigente.

Desligar um gatilho de risco não apaga o registro. O sistema deixa de aplicá-lo, e a mudança fica registrada. O sistema ainda não oferece quatro olhos na edição do nível pela ficha, nem publicação que transforme condicionante em outro nível.

---

## Sumário

- [Épico: EP06 — Classificação de Risco](#épico)
- [História de Usuário: Atualizar classificação de risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP06 — Classificação de Risco

**Como** a SEDUR **quero** atualizar o risco municipal do CNAE na versão vigente**, de modo que** a próxima classificação leia o nível novo

## História de Usuário: Atualizar classificação de risco

**Como** quem mantém os CNAEs **quero** gravar Baixo, Médio risco ou Alto na versão vigente**, de modo que** o sistema passe a usar esse nível sem abrir outra versão pela ficha

## Pré-requisitos

- Versão municipal vigente já publicada
- Quem mantém os CNAEs cria e edita o nível na ficha
- Importação oficial da planilha de risco municipal, com o cabeçalho esperado
- Quem mantém os gatilhos de risco liga ou desliga exceção, com registro da mudança

## Dependências

**Passadas**

- HU.047 — Classificar CNAE por risco
- Versão de risco municipal publicada

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Atualizar classificação de risco

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Gravação na versão vigente**
**Dado que** há versão municipal vigente
**Quando** quem mantém os CNAEs salva o código com Baixo, Médio risco ou Alto
**Então** a versão vigente desse código fica com o nível informado

**Cenário 2: Sem versão vigente**
**Dado que** não há versão municipal vigente
**Quando** quem mantém os CNAEs tenta criar ou editar o CNAE com nível
**Então** a operação é recusada e a mensagem diz que não há versão vigente

**Cenário 3: Planilha com nível desconhecido**
**Dado que** a planilha traz nível diferente de Baixo, do nível do meio e de Alto
**Quando** a importação municipal roda
**Então** a linha é rejeitada e nenhum nível extra é criado

**Cenário 4: Ficha não publica versão nova**
**Dado que** a ficha é salva
**Quando** se observa o que foi gravado
**Então** não nasce versão nova e não há segunda pessoa publicando

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
