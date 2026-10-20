# HU.055 – APLICAR REGRA DE MÉDIO RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Médio risco é o nome da SEDUR para o nível do meio do risco municipal. A ficha do CNAE, a importação do decreto e o resultado da classificação mostram Médio risco. Na planilha do decreto, esse nível entra pelo texto do meio, entre Baixo e Alto. O sistema não cria um segundo nível municipal com outro nome.

No mapa padrão, Médio risco segue no fluxo expresso, o mesmo destino padrão de Baixo. A severidade fica entre Baixo e Alto. Quem mantém os parâmetros pode alterar o destino. O mapa sem o nível Médio risco não é aceito.

O Médio risco sanitário é outra lista. Ele só decide o encaminhamento quando a SEDUR define o risco sanitário como a lista que decide. O padrão é a lista municipal. Ramo da planilha de tratamento e exceção de risco ativa continuam prevalecendo, como nos outros níveis.

O sistema ainda não oferece uma condicionante municipal que troque o CNAE de Médio risco para outro nível. O mapa padrão também não manda Médio risco para análise.

---

## Sumário

- [Épico: EP06 — Classificação de Risco](#épico)
- [História de Usuário: Aplicar regra de médio risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP06 — Classificação de Risco

**Como** a SEDUR **quero** classificar e encaminhar o Médio risco municipal**, de modo que** a operação veja o nome usado pela SEDUR e o mapa aplique o destino desse nível

## História de Usuário: Aplicar regra de médio risco

**Como** analista ou gestor **quero** que o Médio risco municipal apareça com esse nome e siga o mapa de encaminhamento**, de modo que** ele não se confunda com o Médio risco sanitário

## Pré-requisitos

- Nível gravado na ficha do CNAE, por quem mantém o cadastro, ou na importação da versão de risco municipal
- Destino do Médio risco definido no mapa de encaminhamento, por quem mantém os parâmetros
- Lista que decide o encaminhamento: municipal, no padrão; sanitária somente quando a SEDUR assim definir

## Dependências

**Passadas**

- HU.053 — Classificar CNAE por risco

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Aplicar regra de médio risco

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Nível do meio importado do decreto**
**Dado que** a planilha do decreto traz o nível do meio
**Quando** a linha é importada e classificada no risco municipal
**Então** a tela mostra Médio risco e o encaminhamento segue o destino desse nível no mapa

**Cenário 2: Planilha sem a palavra médio**
**Dado que** a célula municipal do decreto é o nível do meio
**Quando** a importação municipal lê a linha
**Então** o CNAE fica em Médio risco, mesmo que a planilha não traga a palavra médio

**Cenário 3: Lista municipal continua decidindo**
**Dado que** a lista que decide o encaminhamento continua sendo a municipal
**Quando** o sistema encaminha um CNAE de Médio risco
**Então** usa o Médio risco municipal e não o Médio risco sanitário

**Cenário 4: Valor fora dos três níveis**
**Dado que** a célula municipal do decreto não é Baixo, o nível do meio nem Alto
**Quando** a importação lê a linha
**Então** essa célula não vira classificação

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
