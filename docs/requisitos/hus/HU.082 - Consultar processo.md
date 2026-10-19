# HU.082 – CONSULTAR PROCESSO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem consulta solicitações busca e abre processos na retaguarda. A lista aceita filtros de protocolo, BAP, TVL, serviço, setor, analista, categoria, inscrição, nome, CNPJ, CEP, logradouro, bairro, período, texto livre, fluxo, situação e situação da análise. A ordenação pode ser por mais recentes, por prazo ou por situação. A página pode ter 10, 15, 25 ou 50 itens. Filtro vazio é ignorado. A consulta fica registrada, inclusive os filtros preenchidos, e a leitura do detalhe é tratada como dado pessoal.

Período com data inicial posterior à final não devolve processos e a tela avisa que o período está invertido. A consulta mesmo assim fica registrada. Com período válido, a lista filtrada pode ser exportada em planilha ou PDF, e a exportação fica registrada.

O detalhe mostra empresa, setor, responsável, decisão, ficha vigente, tramitação e encaminhamento, além do parecer e da explicação da decisão quando existirem. Processo inexistente não abre. Esta consulta é só leitura. Mudar a situação da análise é outro passo, de quem analisa. O cidadão não usa esta tela: acompanha o próprio pedido em Minhas solicitações. O sistema ainda não oferece, nesta consulta, decisão automática nem encaminhamento por regra ausente.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Consultar processo](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** o servidor da retaguarda **quero** buscar e abrir um processo**, de modo que** eu veja dados, decisão, ficha vigente e tramitação

## História de Usuário: Consultar processo

**Como** usuário da gestão que consulta solicitações **quero** filtrar a fila e exportar o conjunto**, de modo que** a consulta fique registrada e o período invertido não mostre processo

## Pré-requisitos

- Atribuição de consultar solicitações
- Datas no formato dia, mês e ano, quando o período for informado

## Dependências

**Passadas**

- Solicitação já existente na retaguarda

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Consultar processo

**Caminho no menu:** Operação > Consulta de processos

#### Comportamento Esperado Tela 1

**Cenário 1: Acesso**
**Dado que** a pessoa não consulta solicitações
**Quando** tenta abrir a lista ou o detalhe
**Então** o sistema recusa

**Cenário 2: Filtros**
**Dado que** os filtros foram informados
**Quando** a lista é exibida
**Então** respeita os filtros e registra a consulta, inclusive os filtros preenchidos

**Cenário 3: Período invertido**
**Dado que** a data inicial é posterior à final
**Quando** a consulta ou a exportação é pedida
**Então** nenhum processo é devolvido e a tela informa o erro

**Cenário 4: Exportação**
**Dado que** o período é válido e o formato é planilha ou PDF
**Quando** exporta
**Então** o conjunto é o da lista filtrada e a exportação fica registrada

**Cenário 5: Detalhe**
**Dado que** o processo existe
**Quando** o detalhe é aberto
**Então** a consulta do processo fica registrada como dado pessoal; processo inexistente não abre

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
