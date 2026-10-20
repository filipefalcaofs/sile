# HU.043 – IDENTIFICAR BAIRRO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O sistema descobre o bairro oficial do ponto pela camada de bairros vigente e pode gravá-lo no processo quando a feição é encontrada. O nome vem do bairro da feição. Nenhuma feição: não encontrado. Camada ausente ou pendente de fonte: indisponível, sem consulta espacial. Bairro não encontrado não é substituído por um bairro padrão. Indisponível ou não encontrado não apaga o bairro oficial já gravado. Nome vazio na feição não é gravado.

O bairro identificado entra na lista da observação (a) do Quadro 10. O bairro digitado pelo requerente no endereço não substitui o bairro oficial da camada: a lista lê o bairro do território identificado, não o texto livre. A carga da camada é da manutenção de camadas ou da base oficial de bairros. Arquivo ausente ou inválido deixa a camada pendente, sem inventar polígono.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Identificar bairro](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** saber o bairro que contém o ponto**, de modo que** ele seja registrado no processo e usado na observação (a) do Quadro 10

## História de Usuário: Identificar bairro

**Como** analista **quero** ver o bairro oficial do imóvel**, de modo que** a lista de bairros da regra use o território identificado, e não o texto livre do endereço

## Pré-requisitos

- Consulta do ponto por quem consulta o território
- Camada de bairro vigente, com feições
- Ponto com latitude e longitude válidas

## Dependências

**Passadas**

- HU-039 — a identificação do ponto
- HU-026 — a camada de bairros vigente

**Futuras**

- HU-047 — a observação (a) do Quadro 10 compara o bairro identificado

---

## Interface de Usuário

### Tela 1 — Bairro na consulta territorial

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Ponto dentro de um bairro**
**Dado que** existe camada de bairro vigente com feições e o ponto cai dentro de um polígono
**Quando** o território é identificado
**Então** o bairro volta identificado, com o nome e a versão da camada, e pode ser gravado como bairro oficial do processo

**Cenário 2: Ponto fora de todos os bairros**
**Dado que** o ponto não cai em nenhum bairro da camada vigente
**Quando** o território é identificado
**Então** a situação é não encontrado e o bairro do processo não é preenchido por essa chamada

**Cenário 3: Sem camada vigente**
**Dado que** não há camada vigente de bairro
**Quando** o território é identificado
**Então** a situação é indisponível e não há consulta espacial

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
