# HU.116 – GERAR RESUMO DA SOLICITAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Na revisão da solicitação, antes do protocolo, o requerente pode pedir um resumo do que declarou. O resumo é sugestão de conferência: não protocola, não decide e não afirma deferimento nem indeferimento. O cartão pede o texto sob demanda e não carrega isso no primeiro acesso à etapa.

O mesmo interruptor liga ou desliga este resumo e o resumo do analista. Com a função desligada ou sem provedor de texto, o sistema não inventa resumo; o cartão mostra só o que já estiver registrado. Com a função ligada, o texto gerado não altera a situação da solicitação. Repetir a mesma declaração não gera outro resumo igual.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Gerar resumo da solicitação](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** requerente **quero** ler um resumo do que declarei**, de modo que** eu confira o preenchimento antes de protocolar

## História de Usuário: Gerar resumo da solicitação

**Como** requerente **quero** um texto de conferência na revisão da solicitação**, de modo que** o resumo não protocole e não decida a viabilidade

## Pré-requisitos

- Solicitação em montagem no portal, com o requerente autenticado
- Função de resumo ligada e provedor de texto ativo, para gerar texto novo

## Dependências

**Passadas**

- Dados já informados na solicitação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Resumo na revisão

**Caminho no menu:** Serviços > Minhas solicitações, na revisão do rascunho

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Pedir o resumo**
**Dado que** o requerente está na revisão e a função está ligada
**Quando** o cartão pede o resumo
**Então** o texto de conferência pode ser gerado ou reutilizado, sem carregar isso no primeiro acesso e sem mudar a situação da solicitação

**Cenário 2: Função desligada**
**Dado que** a função de resumo está desligada ou não há provedor de texto
**Quando** o requerente abre o cartão
**Então** a tela não mostra resumo inventado

**Cenário 3: O resumo não decide**
**Dado que** um resumo foi gerado
**Quando** o requerente continua a solicitação
**Então** o texto não protocola e não afirma deferimento nem indeferimento

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
