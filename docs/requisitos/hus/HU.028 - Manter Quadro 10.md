# HU.028 – MANTER QUADRO 10
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O servidor consulta a versão vigente do Quadro 10 da LOUOS, com as permissões por zona. A alteração não reescreve a versão que já decidiu processos: toda edição de linha ocorre num rascunho. Publicar exige outra pessoa, diferente de quem elaborou o rascunho. A versão anterior permanece. Ativar uma versão já publicada troca a vigente sem apagar o histórico. Descartar o rascunho não altera a vigente.

A permissão é permitido, permitido com condições ou proibido. Na importação, os sinais da matriz oficial (S, N, S(c) e equivalentes) também são aceitos. Zona citada no rascunho e ausente do cadastro ativo de zonas bloqueia a publicação. O Quadro 7 e o Quadro 11 não entram neste mapa. As regras territoriais são outro fluxo do mesmo Motor de regras.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter Quadro 10](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** versionar as permissões do Quadro 10 por zona**, de modo que** a regra mude sem reescrever a versão que já decidiu processos

## História de Usuário: Manter Quadro 10

**Como** administrador **quero** abrir um rascunho do Quadro 10, editar linhas, importar arquivo e publicar com outra pessoa**, de modo que** a permissão por zona entre em vigor como versão nova

## Pré-requisitos

- Consultar a aba de quadros: quem consulta as regras da LOUOS
- Abrir rascunho, gravar linha, importar, publicar, descartar e ativar versão: administrador que mantém o motor
- Zona e grupo de uso obrigatórios; a zona precisa existir e estar ativa no cadastro de zonas
- Quem publica não pode ser o autor do rascunho
- Arquivo CSV ou de texto, com até 5 MB

## Dependências

**Passadas**

- Cadastro de zonas ativo, conferido na publicação

**Futuras**

- HU-047 — o motor lê a versão vigente, não o rascunho

---

## Interface de Usuário

### Tela 1 — Quadro 10

**Caminho no menu:** Regras > Motor de regras > Quadros

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Consulta da vigente**
**Dado que** a pessoa consulta as regras da LOUOS e existe versão vigente do Quadro 10
**Quando** abre o quadro
**Então** vê as permissões por zona dessa versão e o histórico publicado

**Cenário 2: Edição só no rascunho**
**Dado que** o administrador inclui ou altera uma linha
**Quando** a mudança é gravada
**Então** ela fica no rascunho e a versão vigente conserva as linhas anteriores

**Cenário 3: Quem elabora não publica**
**Dado que** o rascunho foi elaborado pelo usuário autenticado
**Quando** esse usuário tenta publicar
**Então** a publicação é recusada e a mensagem pede publicador diferente do autor

**Cenário 4: Zona inexistente**
**Dado que** o rascunho cita zona ausente do cadastro ativo
**Quando** outra pessoa publica
**Então** a publicação falha, a lista das zonas ausentes é informada e a vigente não muda

**Cenário 5: Descartar ou reativar**
**Dado que** existe rascunho ou uma versão já publicada
**Quando** o administrador descarta o rascunho ou ativa uma versão antiga
**Então** descartar não altera a vigente, e ativar troca a vigente sem apagar o histórico; rascunho não é ativado

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
