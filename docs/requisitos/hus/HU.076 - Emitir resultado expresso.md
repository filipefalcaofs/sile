# HU.076 – EMITIR RESULTADO EXPRESSO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem consulta solicitações vê, em Resultados do fluxo expresso, a lista e o detalhe das solicitações já decididas. A consulta é só leitura. A decisão não é editada nesta tela. Solicitação sem decisão não abre detalhe.

A lista filtra por resultado, protocolo ou TVL e pelo período da decisão, e pagina em 10, 15, 25 ou 50. Período com data inicial posterior à final volta vazio e avisa que o período é inválido. A consulta fica registrada. O detalhe mostra a decisão, a explicação já gravada e o estado informado da transmissão: Transmitido quando houve sucesso, pendente de canal quando o envio foi bloqueado, não aplicável quando foi ignorado e Aguardando processamento quando ainda não há registro. A tela não marca enviado sem registro de sucesso.

O número de TVL nasce na decisão deferida. O PDF do TVL não é gerado nesta emissão: sai sob demanda, para decisão deferida, em outro passo da análise. O cidadão não recebe o PDF aqui. O aviso ao requerente é outro passo. O envio à SEFAZ no deferimento, com o canal indisponível, fica bloqueado e a tela mostra pendente de canal. Indeferimento fica como não aplicável para a SEFAZ. Se a decisão não conclui depois das tentativas, o sistema registra a falha e não inventa resultado. O sistema ainda não oferece transmissão à SEFAZ com sucesso enquanto o canal estiver indisponível.

---

## Sumário

- [Épico: EP09 — Fluxo Expresso](#épico)
- [História de Usuário: Emitir resultado expresso](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP09 — Fluxo Expresso

**Como** a SEDUR **quero** gravar o resultado do fluxo expresso e consultá-lo na retaguarda**, de modo que** veredito, TVL, fundamentação e o estado da transmissão fiquem visíveis sem alterar a decisão

## História de Usuário: Emitir resultado expresso

**Como** servidor que consulta solicitações **quero** ver o resultado expresso já gravado**, de modo que** eu audite a decisão e saiba se a transmissão ocorreu, ficou pendente ou não se aplica

## Pré-requisitos

- Decisão já gravada, para a lista e o detalhe
- Quem consulta solicitações abre a tela
- Datas do filtro no formato dia, mês e ano

## Dependências

**Passadas**

- HU.074 — Deferir automaticamente
- HU.075 — Indeferir automaticamente

**Futuras**

- HU.077 — Notificar resultado ao cidadão

---

## Interface de Usuário

### Tela 1 — Emitir resultado expresso

**Caminho no menu:** Relatórios > Resultados do fluxo expresso

*[Print da tela](screenshots/gestao-expresso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Decisão gravada**
**Dado que** o protocolo pode ser decidido pelo fluxo expresso
**Quando** a avaliação conclui
**Então** existe uma decisão e o resultado é comunicado uma vez

**Cenário 2: Falha da avaliação**
**Dado que** as tentativas se esgotam
**Quando** a avaliação falha
**Então** a falha fica registrada e não há resultado inventado

**Cenário 3: Lista da retaguarda**
**Dado que** o servidor pode consultar solicitações
**Quando** abre a lista
**Então** vê só solicitações com decisão, com protocolo, resultado, TVL e data, e a consulta fica registrada

**Cenário 4: Sem decisão**
**Dado que** a solicitação não tem decisão
**Quando** abre o detalhe do resultado expresso
**Então** o detalhe não é exibido

**Cenário 5: Envio bloqueado**
**Dado que** o registro da SEFAZ está bloqueado
**Quando** abre o detalhe
**Então** o canal aparece como pendente, não como transmitido

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
