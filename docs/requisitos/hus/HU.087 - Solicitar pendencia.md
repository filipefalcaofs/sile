# HU.087 – SOLICITAR PENDÊNCIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O analista abre um convite de complementação enquanto o processo está em análise. Descreve o que falta, em até 2000 caracteres. O processo sai de em análise e fica em pendência, com o convite aberto e o prazo em horas úteis. O padrão é 48 horas úteis, pulando fim de semana e feriado cadastrado. Quem abre fica registrado. A tela confirma que o convite foi aberto. O aviso ao requerente sai depois, em outro passo.

Fora de em análise, o convite não é criado. Cancelar o convite aberto exige o processo em pendência e um parecer de até 2000 caracteres. O convite fica cancelado e o processo volta para em análise. Sem convite aberto, nada muda.

O sistema ainda não oferece anexo obrigatório na abertura do convite nem indeferimento no ato de abrir a pendência. O vencimento sem resposta é outro passo.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Solicitar pendência](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** o analista **quero** abrir um convite descrevendo o que falta**, de modo que** o requerente responda e o processo aguarde fora da análise

## História de Usuário: Solicitar pendência

**Como** analista que analisa processos **quero** abrir e, se preciso, cancelar o convite**, de modo que** o prazo seja em horas úteis e o cancelamento volte o processo à análise com parecer

## Pré-requisitos

- Processo em análise, para abrir
- Descrição do que falta, até 2000 caracteres
- Parecer, até 2000 caracteres, para cancelar convite aberto

## Dependências

**Passadas**

- Processo em análise, atribuído a quem analisa

**Futuras**

- HU.088 — Notificar pendência
- HU.176 — Receber complementação
- HU.177 — Responder pendência, inclusive o vencimento sem resposta

---

## Interface de Usuário

### Tela 1 — Solicitar pendência

**Caminho no menu:** Operação > Caixa de entrada

*[Print da tela](screenshots/gestao-pendencias.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Convite aberto**
**Dado que** o processo está em análise e a descrição é válida
**Quando** o analista abre o convite
**Então** o processo vai para em pendência, o convite nasce aberto e o registro guarda a abertura e o prazo

**Cenário 2: Fora da análise**
**Dado que** o processo não está em análise
**Quando** o analista tenta abrir o convite
**Então** o sistema recusa e não cria pendência

**Cenário 3: Descrição inválida**
**Dado que** a descrição está vazia ou tem mais de 2000 caracteres
**Quando** envia
**Então** o sistema recusa

**Cenário 4: Cancelar o convite**
**Dado que** o convite está aberto e o processo está em pendência
**Quando** o analista cancela com o parecer
**Então** a análise é reaberta; sem convite aberto, nada muda

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
