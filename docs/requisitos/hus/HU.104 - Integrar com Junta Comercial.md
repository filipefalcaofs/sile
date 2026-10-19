# HU.104 – INTEGRAR COM JUNTA COMERCIAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Depois da decisão, deferida ou indeferida, o sistema devolve o parecer já gravado à Junta Comercial. O envio só conta como feito quando a Junta confirma o recebimento. Credencial ausente, tempo esgotado ou resposta diferente dessa confirmação deixam a pendência registrada, e o processo não é dado como enviado. A decisão já gravada não muda.

No pedido do analista, o envio ocorre depois que a tela já respondeu, para não segurar o analista. Na rotina, o envio ocorre na hora. Sem usuário ou senha da Junta, a chamada não autentica e a pendência fica registrada. Senha e credencial de acesso não aparecem no registro nem na mensagem de falha.

O parecer informa deferida ou não deferida. A justificativa usa o texto gravado no processo; se não houver, usa motivo, fundamentação e condicionantes, até dez mil caracteres. Notas internas do motor, inclusive falha de mapa, indisponibilidade e encaminhamento à análise, não vão para a Junta. Processo sem protocolo externo da Junta não compõe este envio.

A rotina de reenvio manda de novo quem tem decisão e ainda não teve confirmação. Quem já foi confirmado não é reenviado. Falhas seguidas interrompem a passada, no padrão de três. A passada de conferência só lista, sem enviar. Esta história não registra uma chamada feita agora contra o ambiente da Junta: o sucesso continua dependendo da confirmação dela. O indeferimento por prazo de atuação na Junta não cria o vínculo de protocolo BAP; o sistema ainda não consulta este serviço.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com Junta Comercial](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** gestor **quero** devolver o parecer à Junta só com confirmação dela**, de modo que** uma falha de comunicação não seja tratada como envio feito

## História de Usuário: Integrar com Junta Comercial

**Como** gestor **quero** que o resultado municipal chegue à Junta depois da decisão**, de modo que** a decisão local permaneça mesmo quando o serviço não confirma

## Pré-requisitos

- Processo já decidido, deferido ou indeferido, com protocolo externo da Junta
- Usuário e senha da integração informados na configuração

## Dependências

**Passadas**

- Decisão de viabilidade já gravada
- Declaração recebida do integrador federal, quando houver protocolo externo

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Envio do parecer à Junta

**Caminho no menu:** Não há tela de envio. O parecer sai depois da decisão. O resultado fica em Auditoria > Log de integrações. A configuração fica em Configuração > APIs.

*[Print da tela](screenshots/gestao-integracoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: A Junta confirma**
**Dado que** o processo está decidido e a Junta confirma o recebimento do parecer
**Quando** o envio ocorre
**Então** a trilha registra sucesso

**Cenário 2: Serviço indisponível ou sem confirmação**
**Dado que** falta credencial, o tempo se esgota ou a resposta não é a confirmação de recebimento
**Quando** o envio é tentado
**Então** a trilha registra o bloqueio, o processo não é dado como enviado e a decisão permanece

**Cenário 3: Reenvio**
**Dado que** há decisões ainda sem confirmação e outras já confirmadas
**Quando** a rotina de reenvio passa
**Então** só as pendentes são enviadas de novo, e falhas seguidas interrompem a passada

**Cenário 4: Notas internas**
**Dado que** a decisão tem nota interna de mapa, indisponibilidade ou encaminhamento à análise
**Quando** o parecer é montado
**Então** essas notas não vão para a Junta

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
