# HU.154 – MANTER TIPOS DE IMÓVEL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador mantém os tipos de imóvel reconhecidos no texto que chega do integrador e a marca que tira o processo do fluxo expresso. A lista busca, filtra a situação, ordena e pagina em 10, 15, 25 ou 50 itens. No cadastro entram código, rótulo, a marca de encaminhar à análise, a situação e as grafias alternativas. O código usa letras minúsculas, números e sublinhado, com até 50 caracteres, e é único. Tipo novo nasce ativo quando a situação não é informada. Na edição mudam rótulo, marca, situação e grafias. O código não muda. Se a marca ou a situação não vierem na edição, o valor atual permanece. Grafia já usada por outro tipo é recusada. Desativar não apaga o tipo: ele sai do catálogo ativo e o histórico permanece. Com a marca ligada, o tipo tira o processo do fluxo expresso e o encaminha à análise.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter tipos de imóvel](#história-de-usuário-manter-tipos-de-imóvel)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Tipos de imóvel](#tela-1--tipos-de-imóvel)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** reconhecer o tipo de imóvel que chega do integrador**, de modo que** o processo vá à análise quando a marca desse tipo estiver ligada.

## História de Usuário: Manter tipos de imóvel

**Como** administrador autorizado a manter tipos de imóvel **quero** cadastrar o tipo, as grafias alternativas e se ele encaminha à análise**, de modo que** o texto vindo do integrador seja reconhecido e o código não mude depois do cadastro.

## Pré-requisitos

- Administrador autorizado a manter tipos de imóvel
- Código ainda não usado, em letras minúsculas, números e sublinhado

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Tipos de imóvel

**Caminho no menu:** Regras > Tipos de imóvel

*[Print da tela](screenshots/gestao-tipos-imovel.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Tipo que encaminha à análise**
**Dado que** o administrador está autorizado
**Quando** cadastra código, rótulo e a marca de encaminhar à análise ligada
**Então** o tipo é gravado com essa marca e passa a valer nas leituras seguintes

**Cenário 2: Edição sem informar a situação**
**Dado que** o tipo está inativo
**Quando** a edição envia rótulo e grafias sem informar a situação
**Então** o código permanece e o tipo continua inativo

**Cenário 3: Grafia repetida**
**Dado que** uma grafia já está vinculada a outro tipo
**Quando** o administrador tenta gravá-la de novo
**Então** a grafia é recusada

**Cenário 4: Desativar**
**Dado que** o tipo está ativo
**Quando** o administrador inverte a situação
**Então** o tipo fica inativo, sai do catálogo ativo e o registro permanece

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
