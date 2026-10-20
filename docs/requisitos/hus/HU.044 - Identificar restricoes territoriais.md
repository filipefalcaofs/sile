# HU.044 – IDENTIFICAR RESTRIÇÕES TERRITORIAIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O sistema lista as feições de restrição que cruzam o ponto e, no processo, avalia as regras de lugar da versão vigente. Camada bloqueada: indisponível, lista vazia, sem consulta espacial. Sem interseção: não encontrado. Com interseção: cada feição vira condicionante de restrição e pode levar o parecer a permitido com condições, quando o Quadro 10 não proibiu o uso. Isso não decide o risco sozinho.

A RT-01 indefere quando o CNAE da solicitação, que não está marcado para exclusão, consta da regra e o logradouro é o mesmo. O indeferimento tem precedência e encerra a avaliação das poligonais seguintes. Se o cadastro imobiliário estiver indisponível e o CNAE for da regra, o processo vai à análise em vez de a regra ser descartada. As poligonais RT-02, RT-03 e RT-04 encaminham à análise quando o ponto está dentro e, se houver lista de endereços, fora dela. Estar na lista, como em Itaigara, não defere: só deixa de encaminhar por essa regra. Ponto ausente, camada vazia ou mapa indisponível encaminham à análise. As regras RT-05-A e RT-05-B não passam por essa avaliação: elas alteram o Quadro 10. A observação (c), de depósitos de produtos perigosos, não existe. Sem versão vigente das regras de lugar, não há incidência. A feição de ZEIS desta identificação não aciona, sozinha, o gatilho de risco de ZEIS.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Identificar restrições territoriais](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** saber quais restrições e regras de lugar incidem sobre o imóvel**, de modo que** o parecer seja condicionado ou saia da resposta automática quando a regra mandar

## História de Usuário: Identificar restrições territoriais

**Como** analista **quero** ver as restrições do ponto e o efeito das regras de lugar**, de modo que** um imóvel em área especial não seja tratado como uso livre

## Pré-requisitos

- Consulta do ponto por quem consulta o território
- Avaliação no processo sobre a versão vigente das regras territoriais
- Publicar ou editar as regras: administrador que mantém o motor, com rascunho e outra pessoa para publicar

## Dependências

**Passadas**

- HU-039 — a identificação do ponto
- HU-032 — a versão vigente das regras territoriais

**Futuras**

- HU-050 — a feição vira condicionante do parecer; a RT-01 e as poligonais decidem antes

---

## Interface de Usuário

### Tela 1 — Restrições na consulta territorial

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Feição de restrição no ponto**
**Dado que** a camada de restrição vigente cruza o ponto e o uso não foi proibido pelo Quadro 10
**Quando** o território é identificado e o parecer é consolidado
**Então** cada feição vira condicionante de restrição e o resultado pode ser permitido com condições

**Cenário 2: RT-01 indefere**
**Dado que** a RT-01 vigente casa o CNAE e o logradouro
**Quando** o processo é avaliado
**Então** o veredito é indeferir, com o texto legal da regra, e as poligonais seguintes não são aplicadas

**Cenário 3: Ponto na poligonal**
**Dado que** o ponto está na poligonal de uma regra ativa e fora da lista de endereços, quando houver lista
**Quando** a camada responde
**Então** o veredito é encaminhar à análise, com o motivo gravado na regra

**Cenário 4: Observação (c) inexistente**
**Dado que** se procura a regra da observação (c), de depósitos de produtos perigosos
**Quando** as regras de lugar são lidas
**Então** ela não existe: só há RT-05-A e RT-05-B, aplicadas no Quadro 10

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
