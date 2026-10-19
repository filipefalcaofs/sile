# HU.158 – MANTER TEXTOS DECISÓRIOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador corrige o texto e a descrição dos textos que o motor já emite em documento oficial. Não há criação, exclusão nem desativação nesta tela. A lista agrupa os textos e não pagina. Os grupos são bases legais, LOUOS, consulta pública, análise técnica, justificativa fundamentada, explicação da decisão, regras territoriais, localização do imóvel, zona urbanística, planilha de tratamento e fluxo expresso. Grupo sem rótulo aparece com o próprio identificador. Cada texto mostra o identificador, o texto e a descrição, e a descrição indica os trechos substituíveis. Atualizar grava o texto e a descrição de um identificador que já existe. Identificador desconhecido não é encontrado. O identificador enviado junto com o texto é ignorado. A alteração vale na leitura seguinte do documento. Textos novos entram pelo motor, não por esta tela. As minutas do parecer são outra tela.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter textos decisórios](#história-de-usuário-manter-textos-decisórios)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Textos decisórios](#tela-1--textos-decisórios)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** corrigir o texto que o documento oficial já usa**, de modo que** a redação mude sem criar identificador órfão.

## História de Usuário: Manter textos decisórios

**Como** administrador autorizado a manter os parâmetros **quero** corrigir o texto e a descrição de um texto decisório existente**, de modo que** o documento passe a usar a redação nova.

## Pré-requisitos

- Administrador autorizado a manter os parâmetros
- Texto decisório já existente
- Descrição com até 255 caracteres

## Dependências

**Passadas**

- O identificador já existe porque o motor o emite

**Futuras**

- As minutas do parecer ficam na história de minutas e textos-padrão

---

## Interface de Usuário

### Tela 1 — Textos decisórios

**Caminho no menu:** Configuração > Textos decisórios

*[Print da tela](screenshots/gestao-textos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Editar texto existente**
**Dado que** o texto já existe e o administrador está autorizado
**Quando** grava outro texto e outra descrição
**Então** o identificador permanece e o texto vigente passa a ser o enviado

**Cenário 2: Identificador desconhecido**
**Dado que** o identificador não existe
**Quando** o administrador tenta atualizar
**Então** o texto não é encontrado e nada é gravado

**Cenário 3: Sem cadastro novo**
**Dado que** o administrador está na tela de textos decisórios
**Quando** procura incluir, excluir ou desativar
**Então** só existem a listagem e a atualização de texto já existente

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
