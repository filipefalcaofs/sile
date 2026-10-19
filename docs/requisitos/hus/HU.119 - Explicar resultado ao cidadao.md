# HU.119 – EXPLICAR RESULTADO AO CIDADÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O requerente autenticado, dono da consulta do protocolo, pode receber uma explicação em linguagem simples da decisão já registrada. A explicação não cria desfecho, não reabre o mérito e não substitui o parecer oficial. O parecer oficial — resultado, motivo e fundamentação — continua sendo lido da decisão, sem esta reescrita.

O cartão só aparece se já existe decisão. Sem decisão, sem a função ligada ou sem provedor de texto, nenhuma explicação é inventada. Com decisão e função ligada, a sugestão permanece fiel ao que foi registrado e o desfecho não muda. Repetir o mesmo conteúdo não gera outra explicação igual. Com a função desligada, o parecer oficial continua visível e não aparece texto simulado.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Explicar resultado ao cidadão](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** requerente **quero** uma explicação simples do resultado do meu protocolo**, de modo que** eu entenda a decisão sem que o texto crie outro desfecho

## História de Usuário: Explicar resultado ao cidadão

**Como** requerente **quero** ver a explicação só quando a decisão já existir**, de modo que** não haja texto no lugar de um resultado que ainda não foi dado

## Pré-requisitos

- Protocolo do próprio requerente, com decisão gravada
- Função de explicação ligada e provedor de texto ativo, para gerar o texto

## Dependências

**Passadas**

- Decisão de viabilidade já registrada

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Explicação do resultado

**Caminho no menu:** Serviços > Minhas solicitações, na consulta do protocolo

*[Print da tela](screenshots/portal-solicitacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Protocolo sem decisão**
**Dado que** o protocolo ainda não tem decisão
**Quando** o requerente consulta
**Então** não aparece explicação gerada

**Cenário 2: Decisão e função ligada**
**Dado que** o protocolo está decidido e a função está ligada
**Quando** o requerente abre a explicação
**Então** a sugestão é gravada ou reutilizada e o desfecho permanece o mesmo

**Cenário 3: Função desligada**
**Dado que** a função de explicação está desligada
**Quando** o requerente consulta o protocolo decidido
**Então** o parecer oficial continua visível e não aparece texto simulado

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
