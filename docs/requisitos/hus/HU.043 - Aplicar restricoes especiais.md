# HU.043 – APLICAR RESTRIÇÕES ESPECIAIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Restrição da camada e regra de lugar são mecanismos diferentes. A feição que cruza o ponto vira condicionante de restrição, com o nome da feição, e pode rebaixar o parecer para permitido com condições. Não torna o uso não permitido e não classifica o risco. Toda feição cruzada entra; ZEIS é um exemplo do texto, não um filtro. Camada indisponível ou sem feição não gera condicionante e não simula ZEIS.

As regras RT-01 a RT-04 e as listas RT-05-A e RT-05-B estão na identificação territorial. A RT-01 indefere. Poligonal incidente encaminha à análise. Lista de endereços não defere. RT-05-A e RT-05-B alteram o Quadro 10: fora da lista, proibido; sem número do logradouro e sem bairro, indisponível. A observação (c) não é carregada nem executada. A feição de restrição não aciona, sozinha, o gatilho de risco de ZEIS na consulta prévia.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Aplicar restrições especiais](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** aplicar a restrição do lugar**, de modo que** um imóvel em área com regra especial não seja tratado como uso livre

## História de Usuário: Aplicar restrições especiais

**Como** analista **quero** ver a restrição incidente e o efeito da regra de lugar**, de modo que** o parecer condicione, indefira ou encaminhe à análise conforme a regra

## Pré-requisitos

- Território do ponto já identificado
- Versão vigente das regras de lugar, quando a avaliação for a da RT
- Parecer que ainda admite condicionante, para a feição rebaixar o resultado

## Dependências

**Passadas**

- HU-035 — feições de restrição e regras RT
- HU-039 — observações (a) e (b) do Quadro 10

**Futuras**

- HU-044 — o resultado único recebe a condicionante de restrição

---

## Interface de Usuário

### Tela 1 — Restrições no parecer

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo. A manutenção das regras fica em Regras > Motor de regras

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Feição no ponto**
**Dado que** a camada de restrição identifica uma ou mais feições no ponto
**Quando** o parecer chega à coleta de condicionantes
**Então** cada feição vira condicionante de restrição e pode rebaixar o resultado para permitido com condições

**Cenário 2: Camada sem incidência**
**Dado que** a camada de restrição está indisponível ou não encontra feição
**Quando** as restrições são avaliadas
**Então** nenhuma condicionante de restrição é criada

**Cenário 3: Fora da lista da observação**
**Dado que** a linha do Quadro 10 tem observação (a) ou (b)
**Quando** o logradouro ou o bairro não está na regra RT-05 correspondente
**Então** a permissão aplicada é proibida

**Cenário 4: Observação (c) ausente**
**Dado que** se procura a regra da observação (c)
**Quando** as regras vigentes são lidas
**Então** ela não é carregada nem executada

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
