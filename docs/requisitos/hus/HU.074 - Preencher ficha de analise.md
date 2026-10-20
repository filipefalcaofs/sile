# HU.074 – PREENCHER FICHA DE ANÁLISE
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

O analista abre a revisão vigente da ficha, salva o rascunho, finaliza de forma imutável e abre nova revisão quando precisa reeditar. Preencher não decide o processo. Concluir é outro passo e exige quem pode concluir a análise. Os resultados lidos na conclusão são deferido e indeferido; não há um terceiro resultado de “manter em análise” nesta ficha.

Sem atribuição de analisar processos, a ficha humana não abre. A ficha de processo já decidido pelo fluxo expresso abre em leitura para quem consulta a solicitação ou analisa processos, sem criar revisão humana. O perfil de apoio não preenche a ficha.

Abrir a ficha traz localização, dados do documento de viabilidade, recepção, tramitação, opções de enquadramento e de conclusão, setores ativos, escritório virtual, textos padrão e o indicador de ficha de vistoria já existente. Abrir a ficha de análise não cria vistoria. Se não houver revisão utilizável, a pré-análise tenta criar a primeira. A abertura fica na auditoria, inclusive a consulta ao cadastro imobiliário.

O rascunho grava só o que veio: escolha por atividade, preservando a sugestão do motor, condicionantes, vagas, parecer, sede de escritório virtual e confirmação de endereço. O motivo de análise enviado junto com o rascunho não é gravado. A sugestão do motor fica guardada para a divergência e não vem marcada como escolha do analista. Revisão finalizada não recebe rascunho.

Finalizar marca data e analista, registra divergência quando a escolha difere do sugerido e não decide o processo. Finalizar de novo é recusado. Nova revisão copia a última como rascunho e a finalizada permanece. A comparação mostra duas revisões. Sugestão de parecer, de justificativa e resumo são apoio de leitura e não gravam a decisão. A decisão humana só lê ficha finalizada.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Preencher ficha de análise](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista **quero** preencher a ficha do processo**, de modo que** a escolha por atividade, as condicionantes, as vagas e o parecer fiquem registrados antes da conclusão

## História de Usuário: Preencher ficha de análise

**Como** analista **quero** salvar rascunho e finalizar a revisão**, de modo que** a ficha finalizada não seja editada no lugar e o processo não mude de situação só por esse preenchimento

## Pré-requisitos

- Atribuição de analisar processos, para a ficha humana
- Processo em análise, ou processo do fluxo expresso apenas para leitura

## Dependências

**Passadas**

- Processo encaminhado à análise
- Pré-análise, quando a primeira revisão ainda não existe

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Ficha de análise

**Caminho no menu:** Operação > Caixa de entrada, na ficha do processo

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sem atribuição de análise**
**Dado que** o usuário não analisa processos
**Quando** tenta abrir a ficha humana
**Então** o acesso é negado

**Cenário 2: Ficha do fluxo expresso**
**Dado que** o processo já foi decidido pelo fluxo expresso
**Quando** quem consulta a solicitação abre a ficha
**Então** a ficha abre em leitura e não cria revisão humana

**Cenário 3: Rascunho**
**Dado que** a revisão está em rascunho
**Quando** o analista salva parte dos campos
**Então** o que foi enviado fica gravado, a sugestão do motor permanece e a escolha do analista não vem pré-marcada

**Cenário 4: Finalizar**
**Dado que** o analista finaliza a revisão
**Quando** a escolha difere do sugerido pelo motor
**Então** a revisão fica imutável, a divergência é registrada e a situação do processo não muda

**Cenário 5: Nova revisão**
**Dado que** a revisão vigente está finalizada
**Quando** o analista abre nova revisão
**Então** a finalizada permanece e a nova nasce como rascunho

**Cenário 6: Rascunho em ficha finalizada**
**Dado que** a revisão já foi finalizada
**Quando** alguém tenta gravar por cima
**Então** a gravação é recusada

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
