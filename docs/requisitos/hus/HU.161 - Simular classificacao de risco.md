# HU.161 – SIMULAR CLASSIFICAÇÃO DE RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

A classificação de risco aparece no resultado da consulta prévia, por endereço, por CNAE ou por inscrição. Não há tela só para simular o risco. O sistema ainda não oferece essa simulação isolada.

A seção Classificação de risco mostra o nível municipal, o nível sanitário e o encaminhamento: resposta automática ou análise. A fundamentação do risco entra na lista única de fundamentação da consulta. A consulta por CNAE também classifica o risco, mesmo sem território. Tipo de imóvel e categoria da empresa não entram nesta consulta pública.

A consulta não abre pedido de viabilidade e não dispara a resposta automática. O encaminhamento fica registrado junto da consulta. O sistema ainda não oferece deferimento ou indeferimento a partir desta consulta.

---

## Sumário

- [Épico: EP07 — Consulta Prévia de Viabilidade](#épico)
- [História de Usuário: Simular classificação de risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP07 — Consulta Prévia de Viabilidade

**Como** o cidadão **quero** ver o risco da atividade na consulta prévia**, de modo que** eu conheça o nível e o encaminhamento sem abrir processo

## História de Usuário: Simular classificação de risco

**Como** cidadão que consulta a viabilidade **quero** ver o risco municipal, o risco sanitário e se o caso seguiria para resposta automática ou para análise**, de modo que** essa leitura não vire pedido

## Pré-requisitos

- Consulta prévia concluída com CNAE
- Área opcional, numérica, de 0 a 9999999

## Dependências

**Passadas**

- HU.053 — Classificar CNAE por risco
- HU.157, HU.158 ou HU.159, conforme a forma da consulta

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Simular classificação de risco

**Caminho no menu:** Serviços > Consultar viabilidade

*[Print da tela](screenshots/portal-viabilidade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Risco no resultado**
**Dado que** a consulta prévia foi concluída com CNAE
**Quando** o resultado é exibido
**Então** a seção de classificação mostra risco municipal, risco sanitário e se o encaminhamento é resposta automática ou análise

**Cenário 2: Sem abertura de processo**
**Dado que** o encaminhamento foi calculado
**Quando** a consulta termina
**Então** nenhum pedido de viabilidade é criado por causa dessa classificação

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
