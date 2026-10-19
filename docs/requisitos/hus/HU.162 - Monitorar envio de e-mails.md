# HU.162 – MONITORAR ENVIO DE E-MAILS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem acompanha os e-mails consulta a fila e o resultado dos envios registrados. A tela é só de leitura: não reenvia, não exclui e não edita o registro. A lista filtra pela situação quando ela é informada, ordena do mais recente e pagina, com padrão de 20 itens. Cada linha mostra destinatário (e-mail e nome), o tipo do aviso, a situação, a mensagem de erro, a entrada na fila, o envio, a falha e a data do registro. O endereço de e-mail não é mascarado. Os contadores da página são três, sobre todos os registros e não só sobre a página filtrada: na fila, enviado e falhou. Sem filtro, a lista mostra todos.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Monitorar envio de e-mails](#história-de-usuário-monitorar-envio-de-e-mails)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Monitoramento de e-mails](#tela-1--monitoramento-de-e-mails)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** operador da SEDUR **quero** ver o que está na fila, o que saiu e o que falhou**, de modo que** a falha de envio fique visível com a mensagem de erro.

## História de Usuário: Monitorar envio de e-mails

**Como** operador autorizado a monitorar e-mails **quero** filtrar os envios por situação e ver o erro dos que falharam**, de modo que** eu saiba o destino e o resultado sem reenviar desta tela.

## Pré-requisitos

- Operador autorizado a monitorar e-mails
- Envios já registrados

## Dependências

**Passadas**

- O servidor usado no envio é mantido na configuração de servidores de e-mail

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Monitoramento de e-mails

**Caminho no menu:** Configuração > Monitoramento de e-mails

*[Print da tela](screenshots/gestao-emails.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Filtro de falha**
**Dado que** há envios em mais de uma situação
**Quando** o operador filtra pelos que falharam
**Então** a página traz só esses registros, com a mensagem de erro quando houver

**Cenário 2: Contadores**
**Dado que** há envios na fila, enviados e com falha
**Quando** a lista abre
**Então** os contadores informam as quantidades de cada uma dessas três situações, sobre todos os registros

**Cenário 3: Somente consulta**
**Dado que** o operador está na tela
**Quando** procura reenviar, cancelar ou editar um envio
**Então** só existe a consulta

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
