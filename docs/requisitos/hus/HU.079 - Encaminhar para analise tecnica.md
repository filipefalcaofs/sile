# HU.079 – ENCAMINHAR PARA ANÁLISE TÉCNICA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quando o fluxo expresso não decide o processo, ele vai para a fila da análise humana. A situação fica em análise, aguardando distribuição, com o prazo da etapa de distribuição e o setor de triagem. O motivo da queda fica no encaminhamento e no registro. Não há tela neste passo: o analista encontra o processo na caixa. O envio manual e a mudança forçada de situação são outro passo.

Depois que o encaminhamento é gravado, a pré-análise é disparada. Ela não faz parte da mesma gravação. Se o processo já está em análise e ninguém o assumiu, uma nova avaliação atualiza a queda, o motivo e o rascunho da ficha que ainda não foi mexido, sem abrir outra tramitação. Se o setor ainda estiver em branco, o processo é depositado no setor de triagem atual.

O sistema ainda não oferece, neste encaminhamento, escolha manual de setor ou de analista. A distribuição é outro passo.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Encaminhar para análise técnica](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** a SEDUR **quero** receber na análise técnica o processo que o fluxo expresso não decide**, de modo que** a fila tenha prazo de distribuição e o motivo da queda

## História de Usuário: Encaminhar para análise técnica

**Como** analista **quero** encontrar na caixa o processo encaminhado**, de modo que** eu o distribua e analise com o motivo registrado

## Pré-requisitos

- Processo que o fluxo expresso não decide
- Setor de triagem existente e ativo, para o depósito inicial

## Dependências

**Passadas**

- HU.073 — Identificar elegibilidade para fluxo expresso

**Futuras**

- HU.080 — Distribuir processo
- Pré-análise, disparada depois do encaminhamento

---

## Interface de Usuário

### Tela 1 — Encaminhar para análise técnica

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Processo na fila**
**Dado que** o fluxo expresso não decide o processo
**Quando** o encaminhamento é gravado
**Então** a situação fica em análise, aguardando distribuição, com prazo de distribuição e setor de triagem

**Cenário 2: Motivo da queda**
**Dado que** o encaminhamento ocorreu
**Quando** o analista consulta o processo
**Então** o motivo da queda aparece no encaminhamento e no registro

**Cenário 3: Pré-análise**
**Dado que** o encaminhamento foi gravado
**Quando** a gravação termina
**Então** a pré-análise é acionada em seguida e não desfaz o encaminhamento

**Cenário 4: Reavaliação sem analista**
**Dado que** o processo já está em análise e não foi assumido
**Quando** é reavaliado
**Então** o motivo e o rascunho intocado são atualizados, sem duplicar a tramitação

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
