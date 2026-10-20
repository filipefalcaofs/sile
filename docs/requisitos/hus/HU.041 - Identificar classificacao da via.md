# HU.041 – IDENTIFICAR CLASSIFICAÇÃO DA VIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O sistema obtém o número do logradouro do imóvel e a classe viária da LOUOS usada pelo Quadro 11A. A classe não é inferida pela geometria nem pela distância do ponto à linha. As classes oficiais são VA I, VA II, VC I, VC II, VE, VL e VP. Hífen e espaço não distinguem a classe: VC-II e VC II são a mesma. Valor fora dessa lista não vira classe.

A camada local de vias permanece pendente até a carga oficial dos logradouros. Com a via local identificada, o sistema lê o número do logradouro e consulta a classificação no mapa oficial da SEDUR. Se essa camada não tiver feição, tenta a hierarquia do logradouro; falha dessa segunda consulta fica como não encontrada, sem derrubar a consulta inteira. O número do logradouro tributário da inscrição prevalece sobre o da via mais próxima, quando a inscrição o traz. Consulta desligada, número vazio, serviço fora, logradouro sem linha ou classe fora das sete oficiais não produzem classe inventada. O Quadro 11A só roda quando a classe oficial está preenchida. Não há Quadro 11 separado desta classificação.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Identificar classificação da via](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** saber o logradouro e a classe oficial da via**, de modo que** o Quadro 11A só seja aplicado quando a classe existir

## História de Usuário: Identificar classificação da via

**Como** analista **quero** ver a classe viária oficial do imóvel**, de modo que** as condições de instalação do Quadro 11A não usem uma classe inventada

## Pré-requisitos

- Identificação do ponto por quem consulta o território
- Consulta da classe da via ligada no mapa oficial da SEDUR
- Número do logradouro preenchido; vazio, a classe não é consultada

## Dependências

**Passadas**

- HU-039 — a via mais próxima, quando a camada local está vigente

**Futuras**

- HU-048 — o Quadro 11A usa a classe oficial

---

## Interface de Usuário

### Tela 1 — Classe da via na consulta territorial

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Classe oficial encontrada**
**Dado que** a consulta da classe está ligada e o número do logradouro tem classe entre as sete oficiais
**Quando** a via é identificada
**Então** a classe da LOUOS recebe a grafia oficial e a etapa da via fica concluída

**Cenário 2: Camada de vias pendente**
**Dado que** a camada local de vias está pendente e não há número de logradouro da inscrição
**Quando** o ponto é identificado
**Então** a via fica indisponível e o Quadro 11A não recebe classe

**Cenário 3: Classificação fora das sete classes**
**Dado que** o mapa oficial devolve uma classificação fora de VA I, VA II, VC I, VC II, VE, VL e VP
**Quando** o logradouro é consultado
**Então** a classe não é gravada e o motivo registra que o valor está fora do Quadro 11A

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
