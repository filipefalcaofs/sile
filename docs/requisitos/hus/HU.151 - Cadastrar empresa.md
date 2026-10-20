# HU.151 – CADASTRAR EMPRESA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O cidadão cadastra uma empresa no portal e se torna responsável ativo dela. A origem deste cadastro é sempre manual: protocolo da REDESIM não é preenchido aqui. A busca de CNPJ, quando usada, só pré-preenche; gravar é outro passo. O cadastro não pede CNAE. CNAE principal e secundários entram depois, no detalhe.

Em representação ativa, a empresa e o vínculo são criados para o representado. Não há cadastro por servidor da SEDUR nesta tela, nem escolha de outro papel: quem grava torna-se responsável. A criação da empresa e do vínculo fica registrada.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Cadastrar empresa](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** que o cidadão cadastre a empresa no portal**, de modo que** ele fique como responsável ativo

## História de Usuário: Cadastrar empresa

**Como** cidadão autenticado **quero** informar os dados da empresa**, de modo que** ela fique vinculada à minha conta como responsável

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- CNPJ válido e ainda não cadastrado
- Razão social obrigatória, com até 255 caracteres
- Nome fantasia, natureza, porte, endereço, e-mail e telefone são opcionais, com os limites da ficha; UF com 2 caracteres, CEP com 8 dígitos e telefone com DDD e até 11 dígitos

## Dependências

**Passadas**

- HU-145 — termo vigente aceito
- HU-150 — a busca de CNPJ, quando ligada, só pré-preenche

**Futuras**

- HU-153 e HU-154 — CNAE principal e secundários no detalhe

---

## Interface de Usuário

### Tela 1 — Cadastrar empresa

**Caminho no menu:** Serviços > Minhas empresas > Cadastrar empresa

*[Print da tela](screenshots/portal-empresas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro com vínculo de responsável**
**Dado que** o CNPJ é válido, ainda não está cadastrado, e a razão social foi informada
**Quando** o cidadão envia o formulário
**Então** a empresa é gravada com origem manual, o usuário efetivo fica com vínculo ativo de responsável e a mensagem é "Empresa cadastrada com sucesso."

**Cenário 2: CNPJ repetido**
**Dado que** já existe empresa com o mesmo CNPJ
**Quando** o cidadão tenta cadastrar
**Então** o sistema recusa e informa que já existe empresa cadastrada com este CNPJ

**Cenário 3: Representação**
**Dado que** há uma representação ativa
**Quando** o procurador cadastra a empresa
**Então** o vínculo de responsável é do representado

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
