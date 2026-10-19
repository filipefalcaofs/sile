# HU.072 – CONFIRMAR PAGAMENTO DO DAM
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não oferece a confirmação de pagamento do DAM. Não há baixa automática nem baixa manual da guia, e o processo não é indeferido por falta de pagamento.

O protocolo e a resposta automática seguem sem consultar pagamento. O deferimento gera o número do TVL e comunica o resultado sem exigir guia paga. O envio da viabilidade à SEFAZ, no deferimento, está indisponível e não lê retorno de pagamento. O sistema ainda não oferece liberar o documento só depois da quitação, nem cancelar guia vencida.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Confirmar pagamento do DAM](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** a SEDUR **quero** confirmar o pagamento do DAM junto à SEFAZ**, de modo que** a situação da guia e as consequências do pagamento fiquem no processo

## História de Usuário: Confirmar pagamento do DAM

**Como** gestor **quero** que o sistema confirme a quitação da guia**, de modo que** o pagamento ou a falta dele alterem o andamento do pedido

## Pré-requisitos

- O sistema ainda não oferece esta confirmação
- Não há rotina diária, baixa pelo operador nem consulta de pagamento

## Dependências

**Passadas**

- HU.071 — Visualizar DAM da solicitação, que também ainda não é oferecida

**Futuras**

- Confirmação do pagamento na SEFAZ, quando esse serviço estiver disponível para a SEDUR

---

## Interface de Usuário

### Tela 1 — Confirmar pagamento do DAM

**Caminho no menu:** O sistema ainda não oferece esta confirmação

#### Comportamento Esperado Tela 1

**Cenário 1: Pagamento não consultado**
**Dado que** o processo é protocolado ou decidido
**Quando** o sistema conclui o protocolo ou a resposta automática
**Então** não consulta pagamento de DAM e não atualiza guia

**Cenário 2: Deferimento sem quitação**
**Dado que** o processo é deferido
**Quando** o número do TVL é gerado
**Então** a decisão não espera DAM pago e o sistema ainda não oferece indeferir por guia vencida

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
