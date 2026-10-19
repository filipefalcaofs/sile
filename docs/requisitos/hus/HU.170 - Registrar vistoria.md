# HU.170 – REGISTRAR VISTORIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O analista encaminha o processo a um setor de vistoria, com motivo. O processo vai à caixa daquele setor, sem responsável, para vistoriar, e a origem fica registrada para a volta. Quem preenche a ficha vê a fila de vistorias: a designar, em campo ou concluída. Abrir a ficha cria na primeira vez ou retoma. Ficha de outro vistoriador não pode ser editada. O rascunho é parcial. Concluir exige parecer, de 3 a 10000 caracteres. A ficha concluída fica imutável e não é reaberta por novo rascunho. Se o processo está em vistoria, a conclusão marca a vistoria como realizada. Com origem registrada, o processo volta ao setor e ao analista, em análise. Sem essa origem, permanece vistoriado para retorno manual. A vistoria não defere nem indefere. Anexos são baixados por quem analisa ou por quem preenche a ficha, sem endereço público. O desenho do imóvel pode ser refeito no rascunho. Abrir a ficha de análise não cria vistoria; só informa se já existe. Distribuir processo que está em vistoria exige destinatário autorizado a preencher a ficha. Na configuração inicial, analista servidor, analista terceirizado, chefias e administrador preenchem ficha. Os perfis Vistoriador e Analista Vistoriador nascem sem essa autorização até o administrador configurar.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico-ep10--análise-técnica-sedur)
- [História de Usuário: Registrar vistoria](#história-de-usuário-registrar-vistoria)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Vistorias](#tela-1--vistorias)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista da SEDUR **quero** pedir a vistoria e receber o processo de volta**, de modo que** o que foi visto em campo fique registrado sem decidir a viabilidade.

## História de Usuário: Registrar vistoria

**Como** vistoriador autorizado a preencher a ficha **quero** preencher e concluir a vistoria com parecer**, de modo que** o processo volte ao analista de origem quando o encaminhamento registrou a volta.

## Pré-requisitos

- Analista autorizado a analisar processos, para encaminhar
- Vistoriador autorizado a preencher a ficha, para a fila e a edição
- Setor de vistoria e motivo no encaminhamento
- Parecer na conclusão

## Dependências

**Passadas**

- Distribuir processo que está em vistoria exige destinatário autorizado a preencher a ficha

**Futuras**

- A decisão da viabilidade continua na análise

---

## Interface de Usuário

### Tela 1 — Vistorias

**Caminho no menu:** Operação > Vistorias

*[Print da tela](screenshots/gestao-vistorias.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sem autorização para a ficha**
**Dado que** a pessoa não está autorizada a preencher a ficha
**Quando** abre a fila ou tenta editar
**Então** o acesso é negado

**Cenário 2: Encaminhar**
**Dado que** o analista informa setor de vistoria e motivo
**Quando** encaminha o processo
**Então** o processo fica para vistoriar, sem responsável, na caixa daquele setor, com a origem registrada

**Cenário 3: Conclusão sem parecer**
**Dado que** a ficha está em rascunho
**Quando** o vistoriador tenta concluir sem parecer
**Então** a ficha não é selada

**Cenário 4: Conclusão com parecer e origem**
**Dado que** há parecer e o encaminhamento registrou a volta
**Quando** o vistoriador conclui
**Então** a ficha fica imutável e o processo volta ao analista de origem, em análise

**Cenário 5: Ficha de outro vistoriador**
**Dado que** a ficha foi aberta por outro vistoriador
**Quando** um terceiro tenta editar
**Então** a edição é negada

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
