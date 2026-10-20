# HU.067 – REGISTRAR SOLICITAÇÃO EM CONTINGÊNCIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O operador autorizado registra, no console, uma solicitação completa com origem contingência, quando o pedido não chega pelo canal do integrador. O cidadão e a empresa precisam já existir: o cidadão é localizado pelo CPF e a empresa pelo CNPJ. Esta tela não cria conta nem empresa. O operador informa motivo, referência externa se houver, o desenho do imóvel no mapa, a área, o CNAE principal e, se couber, complementares e anexos. A referência externa já usada em solicitação não cancelada impede um segundo processo e informa o protocolo existente. Outra solicitação da mesma empresa gera alerta e não bloqueia. Com os dados mínimos e os documentos obrigatórios, a solicitação é protocolada pelo mesmo caminho do portal e, em seguida, o fluxo expresso é decidido na hora, com o operador como autor da ação. Se faltar dado mínimo ou documento obrigatório, nada permanece protocolado e os anexos da tentativa são removidos. O ponto de referência do imóvel é opcional nesta tela. A inscrição, quando informada, guarda só os dígitos. Anexo que não corresponda a um requisito existente é ignorado. Ao concluir, o operador vai para a ficha do processo, com o número de protocolo.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico-ep08--solicitação-de-viabilidade)
- [História de Usuário: Registrar solicitação em contingência](#história-de-usuário-registrar-solicitação-em-contingência)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Nova solicitação em contingência](#tela-1--nova-solicitação-em-contingência)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** operador da SEDUR **quero** protocolar um pedido que não veio do integrador**, de modo que** a solicitação entre no mesmo fluxo de protocolo e de decisão expressa do portal.

## História de Usuário: Registrar solicitação em contingência

**Como** operador autorizado a registrar contingência **quero** lançar o pedido com os dados do formulário, o motivo e a referência externa**, de modo que** o processo seja protocolado com origem contingência.

## Pré-requisitos

- Operador autorizado a registrar contingência
- Cidadão já cadastrado, localizado pelo CPF
- Empresa já cadastrada, localizada pelo CNPJ
- Desenho do imóvel, área maior que zero e CNAE principal ativo

## Dependências

**Passadas**

- O protocolo usa o mesmo caminho da solicitação do portal, do rascunho até protocolada

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Nova solicitação em contingência

**Caminho no menu:** Operação > Nova solicitação (contingência)

*[Print da tela](screenshots/gestao-contingencia.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Registro completo**
**Dado que** o operador está autorizado e informa cidadão e empresa já cadastrados, desenho do imóvel, área e CNAE principal
**Quando** registra a solicitação
**Então** ela nasce com origem contingência, é protocolada e o fluxo expresso é decidido em seguida, com abertura da ficha do processo

**Cenário 2: Referência externa repetida**
**Dado que** a referência externa já está em uma solicitação não cancelada
**Quando** o operador tenta registrar de novo
**Então** outro processo não é criado e o protocolo existente é informado

**Cenário 3: Dado mínimo ou documento obrigatório ausente**
**Dado que** falta dado mínimo ou documento obrigatório
**Quando** o operador registra
**Então** nada permanece protocolado e os anexos da tentativa são removidos

**Cenário 4: CPF ou CNPJ sem cadastro**
**Dado que** o CPF ou o CNPJ não corresponde a um cadastro existente
**Quando** o operador envia o formulário
**Então** o registro é recusado

**Cenário 5: Pessoa sem autorização**
**Dado que** a pessoa não está autorizada a registrar contingência
**Quando** tenta abrir a tela
**Então** o acesso é negado

**Cenário 6: Mesma empresa em outro processo**
**Dado que** a empresa já tem outra solicitação
**Quando** o operador registra
**Então** o processo é criado e um alerta de reincidência é exibido, sem bloquear o registro

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
