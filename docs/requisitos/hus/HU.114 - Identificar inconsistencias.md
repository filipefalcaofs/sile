# HU.114 – IDENTIFICAR INCONSISTÊNCIAS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O alerta de inconsistência compara o que foi declarado com o que a visão lê no documento e fica como sugestão. Não decide, não muda a situação do processo e não abre malha fina. Abrir a ficha não dispara a detecção: a ficha só mostra sugestão já registrada, no cartão de alertas.

Com a função desligada, não há alerta simulado. Sem provedor de visão, a função permanece indisponível. Sem imagem, nada é inventado. Quando há imagem, a primeira é usada, com preferência pela exigência de foto de fachada. O alerta previsto informa o campo, o valor declarado, o valor lido no documento, a gravidade e a origem. Conferência de área, de polígono e da Receita Federal não é feita aqui. O sistema ainda não consulta a Receita Federal.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Identificar inconsistências](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** analista **quero** ver divergência entre a declaração e o documento**, de modo que** eu confira o processo sem punição automática

## História de Usuário: Identificar inconsistências

**Como** analista **quero** que o alerta, quando já estiver registrado, não decida o processo**, de modo que** a malha fina e o desfecho continuem sendo atos próprios

## Pré-requisitos

- Função de inconsistências ligada e provedor de visão ativo, para haver detecção
- Imagem já anexada; sem imagem, não há alerta

## Dependências

**Passadas**

- Documentos da solicitação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Alertas da ficha

**Caminho no menu:** Operação > Consulta de processos, na ficha de análise, no cartão de alertas. Abrir a ficha não dispara a detecção.

*[Print da tela](screenshots/gestao-ia.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Abrir a ficha**
**Dado que** o analista abre a ficha
**Quando** o cartão de alertas é exibido
**Então** só aparece sugestão já registrada, e a detecção não é disparada

**Cenário 2: Função desligada**
**Dado que** a identificação de inconsistências está desligada
**Quando** se espera um alerta
**Então** nenhum alerta simulado é criado

**Cenário 3: Sem imagem**
**Dado que** o processo não tem imagem
**Quando** a detecção seria feita
**Então** nenhuma inconsistência fictícia é gerada

**Cenário 4: Sugestão já gravada**
**Dado que** existe alerta de inconsistência registrado
**Quando** o analista o consulta
**Então** a situação do processo não muda

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
