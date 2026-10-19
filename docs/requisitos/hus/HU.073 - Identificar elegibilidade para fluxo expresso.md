# HU.073 – IDENTIFICAR ELEGIBILIDADE PARA FLUXO EXPRESSO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Depois do protocolo, o sistema decide de novo se o processo cabe no fluxo expresso ou vai para análise técnica. A simulação de antes do protocolo não define essa escolha. Não há tela para o cidadão ou o analista marcar o processo como elegível. O resultado consultável fica em Resultados do fluxo expresso.

O sistema só decide solicitação protocolada, ou em análise ainda sem analista, sem decisão e aguardando distribuição. Fora isso, não muda a situação. Com o fluxo expresso desligado, tudo o que ainda pode ser decidido vai para análise, com o motivo de desativado, e não nasce decisão.

O processo é elegível quando há CNAE e todos resolvem para fluxo expresso. Um único CNAE de análise, ou lista vazia, tira o conjunto do expresso e o processo vai para análise, com o motivo e o CNAE que o tirou. Alto risco sem ramo da planilha marcado como fluxo expresso também vai para análise, salvo se o local já for não permitido: nesse caso a decisão indefere. Veredito pendente vai para análise, sem inventar permitido ou não permitido. Empate de zona, divergência entre desenho e endereço ou inscrição, e regra territorial que pede análise também encaminham. Camada territorial indisponível, dentro da janela e com o processo ainda protocolado, deixa o caso em espera naquela passagem, sem encaminhar.

Exclusão só de atividades, sem o CNAE que caracteriza a sede, segue para deferimento sem novo enquadramento. Exclusão desse CNAE em inscrição com sede ativa só defere se o requerente for o titular e tiver confirmado a perda da condição. Sem confirmação, ou se não for o titular, vai para análise. CNAE que caracteriza sede, com intenção de sede, também vai para análise.

O encaminhamento deixa o processo em análise, no setor de triagem quando ele existe e está ativo, com prazo da etapa de distribuição, e registra a queda. Não comunica resultado ao cidadão nessa ida à análise. O sistema ainda não oferece ignorar o veto de local não permitido em favor do encaminhamento por alto risco.

---

## Sumário

- [Épico: EP09 — Fluxo Expresso](#épico)
- [História de Usuário: Identificar elegibilidade para fluxo expresso](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP09 — Fluxo Expresso

**Como** a SEDUR **quero** identificar se o processo protocolado cabe no fluxo expresso**, de modo que** a decisão automática só ocorra quando a regra autorizar e os demais casos vão à análise

## História de Usuário: Identificar elegibilidade para fluxo expresso

**Como** analista **quero** que o sistema reavalie o processo protocolado**, de modo que** eu receba na fila só o que não pode ser decidido no fluxo expresso, com o motivo da queda

## Pré-requisitos

- Solicitação protocolada, ou em análise ainda sem analista e sem decisão
- Fluxo expresso ligado, para haver decisão automática
- Enquadramento recalculado depois do protocolo

## Dependências

**Passadas**

- HU.068 — Protocolar solicitação
- Classificação de risco e enquadramento da LOUOS

**Futuras**

- HU.074 — Deferir automaticamente
- HU.075 — Indeferir automaticamente
- HU.079 — Encaminhar para análise técnica

---

## Interface de Usuário

### Tela 1 — Identificar elegibilidade para fluxo expresso

**Caminho no menu:** Relatórios > Resultados do fluxo expresso

*[Print da tela](screenshots/gestao-expresso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Processo elegível**
**Dado que** o processo está protocolado, todos os CNAEs resolvem para fluxo expresso, o consolidado não é pendente e não há veto, alto risco fora do expresso, gatilho de sede nem regra territorial de análise
**Quando** o sistema decide
**Então** segue para emitir a decisão

**Cenário 2: CNAE fora do fluxo expresso**
**Dado que** algum CNAE tem encaminhamento de análise
**Quando** o sistema decide
**Então** a situação vai para em análise, sem decisão e sem aviso de resultado ao cidadão

**Cenário 3: Fluxo expresso desligado**
**Dado que** o fluxo expresso está desligado
**Quando** o sistema decide um processo protocolado
**Então** encaminha à análise com o motivo de desativado

**Cenário 4: Veredito pendente**
**Dado que** o consolidado está pendente
**Quando** o sistema decide
**Então** encaminha à análise e não inventa permitido nem não permitido

**Cenário 5: Processo já decidido ou assumido**
**Dado que** o processo já foi decidido ou está em análise com analista
**Quando** a avaliação roda de novo
**Então** não há nova mudança de situação

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
