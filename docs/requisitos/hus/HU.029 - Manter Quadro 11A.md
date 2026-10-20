# HU.029 – MANTER QUADRO 11A
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador consulta e versiona o Quadro 11A da LOUOS, com as condições complementares por classe de via. A versão vigente não é editada no lugar. O rascunho segue o mesmo ciclo do Quadro 10: abrir ou retomar, incluir, alterar e excluir linha, importar arquivo, ver a diferença em relação à vigente, publicar com outra pessoa, descartar e reativar versão publicada.

Classe de via citada no rascunho e inexistente ou inativa no cadastro de vias bloqueia a publicação. O Quadro 11 e o Quadro 11B não entram neste fluxo. Há um manual de montagem do arquivo desta planilha de texto.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter Quadro 11A](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** versionar as condições do Quadro 11A por classe de via**, de modo que** elas entrem em vigor como versão nova, sem alterar a vigente no lugar

## História de Usuário: Manter Quadro 11A

**Como** administrador **quero** manter o rascunho do Quadro 11A e publicá-lo com outra pessoa**, de modo que** as condições por classe de via passem a valer na decisão

## Pré-requisitos

- Consulta: quem consulta as regras da LOUOS
- Escrita, importação e publicação: administrador que mantém o motor
- Classe de via obrigatória e ativa no cadastro de vias
- Quem publica não pode ser o autor do rascunho
- Arquivo CSV ou de texto, com até 5 MB, com classe de via, grupo de uso, condições e base legal

## Dependências

**Passadas**

- Cadastro de vias ativo, conferido na publicação
- HU-028 — o mesmo ciclo de rascunho e de publicação do Quadro 10

**Futuras**

- HU-048 — o motor lê a versão vigente do Quadro 11A

---

## Interface de Usuário

### Tela 1 — Quadro 11A

**Caminho no menu:** Regras > Motor de regras > Quadros

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Lista vigente**
**Dado que** existe versão vigente do Quadro 11A e a pessoa consulta as regras da LOUOS
**Quando** seleciona o Quadro 11A
**Então** vê classe de via, grupo de uso, condições e base legal dessa versão

**Cenário 2: Rascunho isolado**
**Dado que** o administrador altera uma linha no rascunho
**Quando** a alteração é gravada
**Então** a versão vigente permanece até a publicação

**Cenário 3: Via não cadastrada**
**Dado que** o rascunho cita classe de via inativa ou inexistente
**Quando** outra pessoa publica
**Então** a publicação é recusada e a vigente não muda

**Cenário 4: Autor não publica**
**Dado que** o usuário é o autor do rascunho
**Quando** ele mesmo tenta publicar
**Então** a publicação é recusada

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
