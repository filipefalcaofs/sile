# HU.142 – EXIBIR HISTÓRICO DECISÓRIO DO IMÓVEL E DO CNAE NA ZONA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Na ficha, o analista vê decisões anteriores do mesmo imóvel e a estatística da atividade na zona. O painel não decide o processo e não recalcula o motor. A consulta fica registrada com o número da revisão vigente. Quem não analisa processos não vê este painel. O portal do cidadão não o exibe. Processo inexistente não abre a consulta.

O imóvel lista decisões anteriores da mesma inscrição: protocolo, desfecho, data, tipo de serviço e analista, no que o serviço de precedentes devolve, sem dado pessoal além disso. A zona é a que o motor gravou na ficha. Sem atividade ou sem zona, a seção da atividade na zona não calcula estatística e não inventa precedente. A janela da estatística é a definida para esse apoio. Sem histórico, o painel degrada sem fabricar caso. A decisão humana continua sendo a ficha finalizada.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Exibir histórico decisório do imóvel e do CNAE na zona](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista **quero** ver precedentes do imóvel e da atividade na zona**, de modo que** o painel apoie a análise sem decidir o processo

## História de Usuário: Exibir histórico decisório do imóvel e do CNAE na zona

**Como** analista **quero** as decisões anteriores da mesma inscrição e, quando houver zona, a estatística da atividade**, de modo que** a falta de zona não fabrique número

## Pré-requisitos

- Atribuição de analisar processos
- Ficha do processo, com revisão vigente

## Dependências

**Passadas**

- Pré-análise ou revisão vigente da ficha
- Decisões anteriores da mesma inscrição, quando existirem

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Precedentes na ficha

**Caminho no menu:** Operação > Caixa de entrada, na ficha de análise

*[Print da tela](screenshots/gestao-fila.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Imóvel e zona**
**Dado que** o analista abre os precedentes e a ficha tem atividade e zona gravadas
**Quando** o painel é exibido
**Então** vê as decisões anteriores da mesma inscrição e a estatística da atividade na zona, e a consulta fica registrada

**Cenário 2: Sem zona**
**Dado que** a ficha não tem zona no registro do motor
**Quando** o painel é exibido
**Então** a seção da atividade na zona não fabrica números

**Cenário 3: Sem atribuição**
**Dado que** o usuário não analisa processos
**Quando** tenta abrir os precedentes
**Então** o acesso é negado

**Cenário 4: O painel não decide**
**Dado que** há precedentes na ficha
**Quando** o analista os consulta
**Então** nenhuma decisão nova é gravada e o precedente não bloqueia o deferimento

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
