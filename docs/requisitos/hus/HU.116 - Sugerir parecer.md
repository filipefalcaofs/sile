# HU.116 – SUGERIR PARECER
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

A minuta de parecer sai da pré-análise já gravada pelo motor, para o analista revisar. Não é a decisão. Sem a função ligada, sem provedor de texto ou sem o registro do motor na ficha, a minuta não é criada e nenhum quadro ou artigo é redigido. A ficha informa se o parecer está disponível e o motivo quando não está.

Com as três condições, a minuta fica como sugestão. Ela não escreve a ficha nem a decisão. Concluir a análise continua sendo ato do analista.

Na mesma ficha há uma sugestão de justificativa da atividade, só depois que o analista escolheu deferida ou indeferida, e só para preencher campo vazio. Ela também não grava a decisão e não faz parte desta história.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Sugerir parecer](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** analista **quero** uma minuta de parecer fundada no que o motor já registrou**, de modo que** eu edite o texto antes de concluir

## História de Usuário: Sugerir parecer

**Como** analista **quero** receber a minuta só quando a pré-análise estiver gravada**, de modo que** nenhum parecer genérico apareça no lugar do motor

## Pré-requisitos

- Ficha de análise com o registro do motor preenchido
- Função de minuta ligada e provedor de texto ativo

## Dependências

**Passadas**

- Pré-análise do processo pelo motor

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Minuta de parecer

**Caminho no menu:** Operação > Caixa de entrada, na ficha de análise

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Pré-análise gravada e função ligada**
**Dado que** a ficha tem o registro do motor e a função está ligada
**Quando** o analista pede a minuta
**Então** surge a sugestão de parecer e o processo continua sem decisão nova

**Cenário 2: Sem registro do motor**
**Dado que** a ficha não tem o registro do motor, a função está desligada ou não há provedor de texto
**Quando** o analista pede a minuta
**Então** a tela explica a indisponibilidade e não cria parecer nem redige quadro ou artigo

**Cenário 3: Conclusão**
**Dado que** a minuta está na ficha
**Quando** o processo precisa ser decidido
**Então** a conclusão continua sendo ação do analista, distinta da sugestão

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
