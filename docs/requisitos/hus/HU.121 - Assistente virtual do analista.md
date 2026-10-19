# HU.121 – ASSISTENTE VIRTUAL DO ANALISTA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Não há tela em que o analista envie uma pergunta e receba resposta em conversa. O que existe é a guarda e a busca de casos parecidos, sem página que use essa busca na ficha. Sem provedor dessa busca, nenhum caso parecido é inventado. A busca devolve os precedentes mais próximos de um texto, não redige parecer e não decide o processo. A carga desses precedentes é uma rotina de preparação, não um assistente.

Minuta de parecer, resumo e justificativa são outras funções da ficha e não um diálogo. Ligar a função de assistente não abre conversa, porque não há essa checagem numa tela de perguntas.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Assistente virtual do analista](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** analista **quero** perguntar sobre regras e casos parecidos quando a conversa existir**, de modo que** a busca de precedentes não seja apresentada como diálogo

## História de Usuário: Assistente virtual do analista

**Como** analista **quero** saber que ainda não há pergunta e resposta na ficha**, de modo que** eu não espere um parecer vindo de uma conversa inexistente

## Pré-requisitos

- Provedor de busca por semelhança ativo, apenas para guardar ou localizar precedentes
- Não há pré-requisito de conversa, porque a tela não existe

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Assistente do analista

**Caminho no menu:** Não há tela de conversa. A ficha de análise não usa a busca de casos parecidos.

*[Print da tela](screenshots/gestao-ia.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Pergunta em conversa**
**Dado que** o analista procura um assistente para perguntar sobre o processo
**Quando** percorre o console
**Então** não há tela de conversa e o sistema ainda não responde este serviço

**Cenário 2: Busca de casos parecidos**
**Dado que** a busca de precedentes é usada e há provedor dessa busca
**Quando** um texto é comparado
**Então** voltam os casos mais próximos e nenhuma decisão é gravada

**Cenário 3: Sem provedor**
**Dado que** não há provedor de busca por semelhança
**Quando** se tenta guardar um precedente
**Então** nada é indexado e nenhum caso parecido é inventado

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
