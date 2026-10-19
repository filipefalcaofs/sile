# HU.105 – INTEGRAR COM RECEITA FEDERAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não consulta a Receita Federal. Não há validação de DBE nem de comprovante de inscrição federal por esse canal.

A consulta de CNPJ no cadastro de empresa, quando está ligada, fala com a base cadastral da SEDUR. Ela devolve razão social, nome fantasia, telefone e endereço quando a SEDUR responde. Porte, natureza jurídica e atividade econômica ficam em branco de propósito: a SEDUR não os envia e o sistema não os completa por outra fonte. Com a consulta desligada, o cadastro pede preenchimento manual e também não consulta a Receita.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com Receita Federal](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** cidadão **quero** que o CNPJ seja conferido na Receita Federal quando esse serviço existir**, de modo que** o cadastro não trate dado federal como se tivesse sido consultado

## História de Usuário: Integrar com Receita Federal

**Como** cidadão **quero** saber que a Receita Federal ainda não é consultada**, de modo que** porte, natureza e atividade não apareçam como se viessem de lá

## Pré-requisitos

- Nenhum, porque o serviço não está ligado

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Consulta de CNPJ

**Caminho no menu:** Serviços > Minhas empresas, na consulta de CNPJ. Essa consulta não é a Receita Federal.

#### Comportamento Esperado Tela 1

**Cenário 1: Receita Federal**
**Dado que** o cidadão informa um CNPJ
**Quando** o cadastro consulta dados federais
**Então** o sistema ainda não consulta este serviço

**Cenário 2: Consulta ligada na base da SEDUR**
**Dado que** a consulta de CNPJ do cadastro está ligada e a SEDUR responde
**Quando** o cidadão consulta
**Então** razão social, nome fantasia, telefone e endereço podem voltar, e porte, natureza jurídica e atividade permanecem em branco

**Cenário 3: Consulta desligada**
**Dado que** a consulta de CNPJ está desligada
**Quando** o cidadão abre o cadastro
**Então** o sistema pede preenchimento manual e não consulta a Receita Federal

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
