# HU.171 – REPROCESSAR PROCESSO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem está autorizado pede, na ficha do processo, que as etapas do motor que ficaram indisponíveis sejam refeitas, parando na primeira que continuar sem serviço. O botão aparece quando há etapa indisponível. Sem autorização, o pedido é negado. Processo inexistente não é encontrado. Se não há etapa indisponível e também não há etapa gravada, e o fluxo expresso ainda pode reavaliar, o passe completo é refeito. Se não há o que refazer, a mensagem informa que não há etapa indisponível, ou que o processo já foi decidido, ou que a análise já foi assumida. Se a localização pela inscrição continua indisponível, o passe para nessa etapa, avisa que nova tentativa foi agendada e não decide. As etapas seguintes não rodam. Com localização possível, o território é completado. Se falta o desenho do imóvel e há ponto, e o expresso pode reavaliar, o veredito sai em seguida. Caso contrário, a reavaliação ocorre na hora. O registro guarda onde parou, a situação do processo e se a decisão ficou para depois, com resultado de sucesso ou parcial. O reprocesso não inventa veredito, não troca responsável e não muda o setor. Não é o envio manual à análise nem a redistribuição. A verificação automática das etapas indisponíveis existe à parte; esta história é o pedido feito na ficha.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico-ep10--análise-técnica-sedur)
- [História de Usuário: Reprocessar processo](#história-de-usuário-reprocessar-processo)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Reprocessar na ficha do processo](#tela-1--reprocessar-na-ficha-do-processo)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista da SEDUR **quero** retomar as etapas que ficaram sem serviço**, de modo que** localização, território e decisão sejam reavaliados sem inventar veredito.

## História de Usuário: Reprocessar processo

**Como** analista autorizado a reprocessar **quero** pedir o reprocesso na ficha do processo**, de modo que** o passe pare na primeira etapa que continuar indisponível.

## Pré-requisitos

- Pessoa autorizada a reprocessar processos; na configuração inicial, analista servidor, analista terceirizado, chefias e administrador têm essa autorização, e o apoio não tem
- Processo existente
- Etapa indisponível, ou processo ainda reavaliável pelo fluxo expresso sem etapa gravada

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- O envio manual à análise é outra história

---

## Interface de Usuário

### Tela 1 — Reprocessar na ficha do processo

**Caminho no menu:** Não há item de menu. O botão Reprocessar fica na ficha do processo, no bloco das etapas, quando alguma etapa está indisponível.

#### Comportamento Esperado Tela 1

**Cenário 1: Sem autorização**
**Dado que** a pessoa não está autorizada a reprocessar
**Quando** pede o reprocesso
**Então** o pedido é negado

**Cenário 2: Localização ainda indisponível**
**Dado que** a etapa de localização pela inscrição continua indisponível
**Quando** o reprocesso é pedido
**Então** o passe para nessa etapa, o resultado fica parcial, nova tentativa é avisada e o processo não é decidido

**Cenário 3: Etapas disponíveis**
**Dado que** a localização é possível
**Quando** o reprocesso completa o território
**Então** a reavaliação ocorre na hora ou o veredito sai em seguida, e a ficha mostra a mensagem do resultado

**Cenário 4: Nada a reprocessar**
**Dado que** não há etapa pendente e o processo não pode ser reavaliado
**Quando** o reprocesso é pedido
**Então** o veredito não muda e a mensagem informa que não há o que reprocessar

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
