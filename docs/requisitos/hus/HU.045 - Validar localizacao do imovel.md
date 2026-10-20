# HU.045 – VALIDAR LOCALIZAÇÃO DO IMÓVEL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O servidor compara o polígono informado com o lote oficial e recebe alerta quando a cobertura fica abaixo do percentual definido. Enquanto a camada de lote está pendente, a comparação não roda: a situação é indisponível, a cobertura fica sem valor, o alerta é falso e não há consulta espacial. Indisponibilidade do lote não gera alerta falso de cobertura baixa. O percentual mínimo é cinquenta por cento, se outro valor entre 1 e 100 não estiver definido. Abaixo dele, há alerta. Igual ou acima, a localização é validada.

No rascunho, a área declarada é comparada à área do próprio polígono, com tolerância. Acima da tolerância há alerta registrado, e o salvamento não é bloqueado. A resposta automática encaminha o processo à análise quando a zona do desenho é diferente da zona do endereçamento ou da zona da inscrição. Isso ocorre depois do indeferimento territorial por logradouro e antes do encaminhamento das poligonais. A tela da consulta usa a camada vigente agora. Não há bloqueio do rascunho por cobertura baixa com o lote oficial, e o lote oficial da cidade continua pendente.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Validar localização do imóvel](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** comparar o desenho com o lote oficial**, de modo que** um polígono deslocado fique registrado, sem alerta inventado enquanto o lote estiver pendente

## História de Usuário: Validar localização do imóvel

**Como** analista **quero** saber se o polígono informado cobre o lote oficial**, de modo que** um desenho deslocado não passe sem registro

## Pré-requisitos

- Servidor que consulta o território
- Polígono informado
- Camada de lote vigente para calcular a cobertura; pendente, a validação não roda

## Dependências

**Passadas**

- HU-042 — a base de lotes, hoje pendente de fonte

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Validar localização

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Lote pendente**
**Dado que** a camada de lote está pendente de fonte
**Quando** um polígono é enviado para validação
**Então** a situação é indisponível, a cobertura fica sem valor, o alerta é falso e não há consulta espacial

**Cenário 2: Cobertura abaixo do mínimo**
**Dado que** existe camada de lote vigente e a maior cobertura é menor que o percentual definido
**Quando** o polígono é validado
**Então** a situação é alerta de cobertura e o alerta fica registrado

**Cenário 3: Cobertura suficiente**
**Dado que** a cobertura é igual ou maior que o percentual definido
**Quando** o polígono é validado
**Então** a situação é validada

**Cenário 4: Zona do desenho diverge**
**Dado que** a zona do desenho é diferente da zona da inscrição ou do endereçamento
**Quando** a resposta automática avalia a localização
**Então** o processo vai à análise

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
