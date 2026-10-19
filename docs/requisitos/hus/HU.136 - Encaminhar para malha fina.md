# HU.136 – ENCAMINHAR PARA MALHA FINA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem encaminha para a malha fina envia um ou vários processos já decididos pelo fluxo expresso, com um motivo, sem mudar a situação do processo. Quem só encaminha não opera a caixa. A caixa é de quem analisa a malha fina, no cadastro padrão as chefias e o administrador. Concluir na caixa exige também poder concluir a análise. O administrador, no cadastro padrão, vê a caixa e não conclui.

O encaminhamento humano exige decisão do fluxo expresso ainda fora da análise humana. Fora isso, o item falha com a mensagem de que a malha fina só se aplica a processo decidido pelo expresso, e os demais itens do lote seguem. Motivo vazio ou só com espaços recusa o lote inteiro antes de gravar. O motivo vale para o lote, com no máximo dois mil caracteres. O setor é opcional. Falha de um processo não cancela os outros. Repetir o encaminhamento cria novo registro. Cada encaminhamento fica na auditoria.

Há também encaminhamento feito pelo próprio sistema, sem analista, quando uma verificação encaminha o processo. Esse caminho também não muda a situação e não exige que a decisão tenha sido do fluxo expresso.

A caixa lista os encaminhamentos abertos e as baixas já concluídas, com os filtros das outras caixas, e a consulta fica registrada. Concluir só segue se existe encaminhamento aberto. O resultado pode ser deferido, indeferido, desativado, cassado ou revogado, com as regras de encerramento da análise. Sem encaminhamento aberto, a tela avisa e não muda a situação.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Encaminhar para malha fina](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista **quero** enviar o processo do fluxo expresso à malha fina com motivo**, de modo que** o revisor veja o caso na caixa sem que o encaminhamento mude a situação

## História de Usuário: Encaminhar para malha fina

**Como** analista **quero** encaminhar um ou vários processos e, quem analisa a caixa, registrar o resultado**, de modo que** encaminhar, ver a caixa e concluir sejam atos distintos

## Pré-requisitos

- Atribuição de encaminhar para a malha fina, para o envio
- Atribuição de analisar a malha fina, para ver a caixa
- Atribuição de concluir a análise, além de analisar a malha fina, para registrar o resultado

## Dependências

**Passadas**

- Decisão do fluxo expresso, no encaminhamento humano

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Malha fina

**Caminho no menu:** Operação > Malha fina

*[Print da tela](screenshots/gestao-malha.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Encaminhar processo do expresso**
**Dado que** o processo foi decidido pelo fluxo expresso, ainda não entrou na análise humana, e o motivo foi informado
**Quando** quem encaminha envia o processo
**Então** ele entra na caixa e a situação do processo não muda

**Cenário 2: Motivo em branco**
**Dado que** o motivo está vazio ou só tem espaços
**Quando** o lote é enviado
**Então** nenhum item é gravado

**Cenário 3: Item que não cabe**
**Dado que** um processo do lote já está na análise humana ou não tem decisão do expresso
**Quando** o lote é encaminhado
**Então** esse item falha e os demais seguem

**Cenário 4: Concluir**
**Dado que** há encaminhamento aberto e o revisor pode concluir a análise
**Quando** registra o resultado
**Então** a conclusão da análise é aplicada e o encaminhamento aberto é baixado

**Cenário 5: Sem encaminhamento aberto**
**Dado que** não há encaminhamento em aberto
**Quando** alguém tenta concluir
**Então** a tela avisa e a situação do processo não muda

**Cenário 6: Sem atribuição de concluir**
**Dado que** o usuário só analisa a malha fina ou só encaminha
**Quando** tenta concluir
**Então** a conclusão é negada

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
