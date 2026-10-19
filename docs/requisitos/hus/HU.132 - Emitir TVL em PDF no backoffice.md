# HU.132 – EMITIR TVL EM PDF NO BACKOFFICE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Quem pode emitir o documento de viabilidade gera o PDF de uma decisão deferida no console e baixa por um link temporário. O arquivo não fica em endereço público. O cidadão não emite por este caminho. No cadastro padrão, a emissão está com o analista servidor, o analista terceirizado, as chefias e o administrador.

Sem decisão, nenhum arquivo é gerado e a tela informa que não há decisão para o PDF. Falha ao montar o documento também não entrega o arquivo e mostra a mensagem correspondente. A emissão bem-sucedida informa o identificador, o número de verificação, a data e o link. O link vale cinco minutos, ou o prazo definido para esse download. Sem link válido, o arquivo não é entregue. O download com link válido entrega o PDF, com nome da viabilidade, e fica na auditoria.

Esta emissão não é o deferimento: o número do documento nasce na decisão. O documento de indeferimento é outra emissão, também restrita a quem pode emitir. Não há download deste PDF no portal do cidadão.

---

## Sumário

- [Épico: EP10 — Análise Técnica SEDUR](#épico)
- [História de Usuário: Emitir TVL em PDF no backoffice](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP10 — Análise Técnica SEDUR

**Como** analista **quero** emitir e baixar o PDF da viabilidade deferida**, de modo que** o documento fique no console e não em endereço público

## História de Usuário: Emitir TVL em PDF no backoffice

**Como** analista **quero** o PDF da decisão deferida por um link temporário**, de modo que** quem não pode emitir não receba o arquivo

## Pré-requisitos

- Atribuição de emitir o documento de viabilidade
- Processo com decisão deferida

## Dependências

**Passadas**

- Decisão deferida, em que o número do documento já foi gerado

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — PDF da viabilidade

**Caminho no menu:** Operação > Consulta de processos, na emissão do documento do processo deferido

*[Print da tela](screenshots/gestao-expresso.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Sem atribuição**
**Dado que** o usuário não pode emitir o documento
**Quando** tenta emitir ou baixar
**Então** o acesso é negado

**Cenário 2: Sem decisão**
**Dado que** o processo não tem decisão
**Quando** alguém pede o PDF
**Então** nenhum arquivo é gerado e a tela informa que não há decisão para o PDF

**Cenário 3: Emissão**
**Dado que** a decisão deferida pode gerar o documento
**Quando** a emissão ocorre
**Então** a tela devolve o link temporário, com o prazo de cinco minutos ou o prazo definido para esse download

**Cenário 4: Download**
**Dado que** o link ainda é válido
**Quando** quem pode emitir baixa o arquivo
**Então** recebe o PDF e o download fica na auditoria

**Cenário 5: Link vencido**
**Dado que** o link não é mais válido
**Quando** alguém tenta baixar
**Então** o arquivo não é entregue

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
