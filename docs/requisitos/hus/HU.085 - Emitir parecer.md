# HU.085 – EMITIR PARECER
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O analista escreve o parecer na ficha, com texto padrão quando houver. O rascunho é gravado automaticamente e não decide. Ficha finalizada deixa de aceitar alteração. O motivo interno da análise não é editável pelo analista.

No indeferimento, o texto da decisão usa, nesta ordem, o parecer informado na conclusão, o parecer da ficha ou as justificativas das atividades marcadas como indeferidas. Sem nenhum texto, a decisão é recusada. No deferimento, o parecer informado só é gravado se não estiver em branco; pode ficar sem texto.

Quem analisa abre e grava a ficha humana. A ficha do fluxo expresso abre para quem consulta solicitações ou analisa, e não cria revisão humana. Concluir o processo, decidir e gravar a justificativa para a Junta exigem também concluir a análise. No cadastro padrão, Coordenador, Subcoordenador, Chefe de setor e Analista Servidor concluem. Analista Terceirizado analisa e não conclui. Apoio não analisa. Administrador, no cadastro padrão, não analisa nem conclui.

Há sugestão de parecer por inteligência artificial na ficha. A sugestão não grava a decisão. O sistema ainda não oferece publicação do parecer à Junta como entrega concluída: o envio existe e, com o canal indisponível, fica registrado como bloqueado, sem marcar enviado. O PDF de indeferimento é outro passo.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Emitir parecer](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** o analista **quero** escrever o parecer na ficha**, de modo que** a conclusão grave esse motivo na decisão de indeferimento

## História de Usuário: Emitir parecer

**Como** analista que analisa processos **quero** guardar o parecer no rascunho e usá-lo ao concluir**, de modo que** indeferir sem texto seja recusado e quem não conclui a análise não publique a decisão

## Pré-requisitos

- Processo em análise, com ficha
- Quem analisa, para gravar o rascunho
- Quem também conclui a análise, para decidir e gravar a justificativa da Junta
- Parecer de conclusão com até 5000 caracteres, quando informado

## Dependências

**Passadas**

- Ficha de análise do processo

**Futuras**

- HU.086 — Deferir solicitação
- HU.087 — Indeferir solicitação

---

## Interface de Usuário

### Tela 1 — Emitir parecer

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Parecer no rascunho**
**Dado que** o analista grava o parecer no rascunho
**Quando** a ficha é finalizada
**Então** a gravação automática deixa de ser aceita

**Cenário 2: Indeferimento sem texto**
**Dado que** não há parecer na conclusão, nem na ficha, nem justificativa por atividade
**Quando** tenta indeferir
**Então** a decisão é recusada e não é gravada

**Cenário 3: Indeferimento com parecer**
**Dado que** há parecer
**Quando** indefere
**Então** o motivo da decisão fica preenchido

**Cenário 4: Sem concluir a análise**
**Dado que** a pessoa analisa e não conclui a análise
**Quando** tenta decidir, concluir a ficha ou gravar a justificativa da Junta
**Então** o sistema recusa

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
