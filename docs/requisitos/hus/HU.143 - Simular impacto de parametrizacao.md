# HU.143 – SIMULAR IMPACTO DE PARAMETRIZAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem mantém as regras da lei urbanística simula um rascunho do Quadro 10 ou do Quadro 11A e vê quantos cenários mudariam de resultado. A simulação não grava o resultado do motor. Atualizar a página faz o relatório sumir. A publicação do rascunho exige outra pessoa, diferente do autor. A simulação em si fica na auditoria e não publica a versão.

A tela lista os rascunhos desses dois quadros, com quadro, domínio, rótulo, versão, autor e data. A amostra padrão é de 50 cenários, salvo o valor definido na aplicação. Esse tamanho não é um item que o gestor altera na tela de configuração. O pedido pode informar outra amostra, de no mínimo um cenário. Os cenários são atividades ativas, limitadas à amostra, com área fixa de 50 m² e uma zona: a zona da versão vigente do Quadro 10 com mais permissões de atividade permitida. O território desses cenários é de ensaio. Sem zona vigente ou sem permissões, a amostra fica vazia.

O relatório mostra domínio, versão do rascunho, amostra usada, total, quantos mudariam, distribuição e divergências. Rascunho inexistente é recusado com a mensagem «Não há um rascunho deste Quadro da LOUOS com esse identificador.» A tela não simula processos protocolados, janela de datas, risco municipal, planilha de tratamento, gatilhos nem regras territoriais.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Simular impacto de parametrização](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** simular o rascunho do Quadro 10 ou do Quadro 11A**, de modo que** eu veja quantos cenários mudariam antes de publicar

## História de Usuário: Simular impacto de parametrização

**Como** administrador da SEDUR **quero** publicar o rascunho só com outra pessoa**, de modo que** a simulação não grave o motor e o autor não publique o próprio rascunho

## Pré-requisitos

- Atribuição de manter as regras da lei urbanística
- Rascunho existente do Quadro 10 ou do Quadro 11A

## Dependências

**Passadas**

- Rascunho do quadro ainda não publicado

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Simulação de regras

**Caminho no menu:** Regras > Simulação de regras

*[Print da tela](screenshots/gestao-simulacao.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Simular**
**Dado que** existe rascunho do Quadro 10 ou do Quadro 11A
**Quando** quem mantém as regras simula
**Então** recebe total, quantos mudariam, distribuição, divergências e a amostra usada, e o resultado do motor não fica gravado

**Cenário 2: Amostra informada**
**Dado que** o mesmo rascunho está na tela
**Quando** a simulação pede dez cenários
**Então** o relatório registra a amostra usada como dez, limitada às atividades ativas encontradas

**Cenário 3: Rascunho inexistente**
**Dado que** a versão não é rascunho daquele quadro
**Quando** alguém tenta simular
**Então** a simulação é recusada com a mensagem de que não há rascunho desse quadro com esse identificador

**Cenário 4: Publicar**
**Dado que** o rascunho pode ser simulado
**Quando** o autor tenta publicar
**Então** a publicação é recusada; outra pessoa que mantém as regras pode publicar

**Cenário 5: Atualizar a página**
**Dado que** o relatório acabou de ser exibido
**Quando** a página é atualizada
**Então** o relatório não permanece

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
