# HU.102 – CONSULTAR LOG DE INTEGRAÇÕES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O auditor consulta as chamadas externas já gravadas, com direção, resultado e o conteúdo da troca. A lista pagina 20 registros, do mais recente ao mais antigo. Os filtros aceitos são direção de entrada ou saída, resultado de sucesso ou erro, e a integração já registrada. Valor de filtro fora da lista permitida é ignorado e a lista não quebra. Cada linha mostra data, direção, integração, forma da chamada, endereço, situação da resposta, resultado, duração e mensagem de erro. Os nomes conhecidos são REGIN/JUCEB, GeoServer, geocodificação, CNPJ, gov.br, inscrição imobiliária, SEDUR contato, SEDUR autenticação e IA. Integração sem nome conhecido aparece como foi gravada. As opções saem do que já foi registrado. O detalhe acrescenta se o conteúdo foi cortado, a correlação, o endereço de origem, o navegador, os cabeçalhos e os conteúdos do pedido e da resposta. A tela não dispara nova chamada nem reprocessa falha. Não há indicador agregado de saúde nesta consulta. Cada listagem e cada detalhe ficam registrados como consulta de dado pessoal. O painel de saúde das integrações é outra tela.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico-ep12--auditoria-e-compliance)
- [História de Usuário: Consultar log de integrações](#história-de-usuário-consultar-log-de-integrações)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Log de integrações](#tela-1--log-de-integrações)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** auditor da SEDUR **quero** ver o que foi enviado e o que voltou nas integrações**, de modo que** a falha seja lida no registro, sem repetir a chamada nesta tela.

## História de Usuário: Consultar log de integrações

**Como** auditor autorizado a consultar a auditoria **quero** filtrar as chamadas por direção, resultado e integração**, de modo que** o detalhe mostre o conteúdo gravado e a consulta fique registrada.

## Pré-requisitos

- Auditor autorizado a consultar a auditoria
- Chamadas externas já gravadas

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- O painel de saúde das integrações, com fila de nova tentativa, é outra tela

---

## Interface de Usuário

### Tela 1 — Log de integrações

**Caminho no menu:** Auditoria > Log de integrações

*[Print da tela](screenshots/gestao-integracoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Só erros**
**Dado que** há chamadas com sucesso e com erro
**Quando** o auditor filtra pelo resultado de erro
**Então** a lista traz só as chamadas com esse desfecho

**Cenário 2: Detalhe**
**Dado que** a chamada está na lista
**Quando** o auditor abre o detalhe
**Então** vê o conteúdo e os cabeçalhos gravados, e a consulta fica registrada

**Cenário 3: Sem reenvio**
**Dado que** o auditor está na lista ou no detalhe
**Quando** procura repetir a integração
**Então** não há ação de reenvio

**Cenário 4: Filtro fora da lista**
**Dado que** a direção ou o resultado informado não é um valor aceito
**Quando** a lista abre
**Então** esse filtro é ignorado e a consulta não quebra

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
