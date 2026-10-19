# HU.014 – MANTER PARÂMETROS DO SISTEMA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador consulta o catálogo de parâmetros já existente, por grupo, grava ou propõe um valor e vê o histórico. Não cria nem apaga uma chave nesta tela. Parâmetro operacional passa a valer na hora. Parâmetro de decisão só vale depois que outra pessoa aprova. Valor sensível não aparece na tela; se o campo for enviado vazio, o valor guardado não muda, e o histórico mostra anterior e novo como criptografado.

A indicação de que a chave pede teste de conexão é exibida, mas o teste não é feito aqui: ele fica nas telas de integração. Feriados, valores da TLL, termos, textos decisórios, minutas, APIs, servidores de e-mail e provedores de inteligência artificial têm telas próprias. Esta tela não define se o prazo conta em dias úteis ou corridos; o calendário descontado no cálculo é o de feriados.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter parâmetros do sistema](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** alterar o catálogo de parâmetros já existente**, de modo que** prazo, limiar ou credencial mudem sem publicação de versão do sistema

## História de Usuário: Manter parâmetros do sistema

**Como** administrador **quero** consultar o catálogo por grupo, propor ou gravar um valor e ver o histórico**, de modo que** eu mude o que a operação ajusta sem chamar desenvolvimento

## Pré-requisitos

- Administrador autenticado com acesso à manutenção de parâmetros
- O valor precisa atender às regras da própria chave
- O perfil do cidadão, quando alterado por esta chave, não pode ser um perfil com acesso ao console
- Em parâmetro de decisão, quem propõe não aprova a própria proposta

## Dependências

**Passadas**

- O catálogo já precisa existir; esta tela não inclui chave nova

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Parâmetros

**Caminho no menu:** Configuração > Parâmetros

*[Print da tela](screenshots/gestao-parametros.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Operacional com efeito imediato**
**Dado que** o parâmetro é operacional e o valor passa nas regras da chave
**Quando** o administrador grava
**Então** o valor vigente muda, o histórico registra o anterior e o novo, e o valor novo passa a ser usado sem nova publicação do sistema

**Cenário 2: Sensível em branco**
**Dado que** o parâmetro é sensível e já tem valor
**Quando** o administrador envia o campo vazio
**Então** o valor armazenado não muda e a resposta é "Valor mantido."

**Cenário 3: Segunda pessoa aprova**
**Dado que** o parâmetro é de decisão
**Quando** um usuário grava um valor novo
**Então** surge proposta pendente e o valor vigente só muda depois que outro usuário aprova; rejeitar mantém o vigente

**Cenário 4: Histórico**
**Dado que** já houve alterações naquela chave
**Quando** o administrador abre o histórico
**Então** vê valor anterior, valor novo, responsável e data, com o sensível mascarado como criptografado

**Cenário 5: Exportação sem segredo**
**Dado que** o administrador pede CSV, planilha ou PDF
**Quando** a exportação é gerada
**Então** o catálogo sai no arquivo e o valor sensível não aparece em claro

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
