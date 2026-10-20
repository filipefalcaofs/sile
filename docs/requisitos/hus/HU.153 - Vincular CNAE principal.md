# HU.153 – VINCULAR CNAE PRINCIPAL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Quem tem vínculo ativo define ou troca o CNAE principal da empresa a partir da tabela oficial de CNAEs ativos. A busca devolve no máximo vinte opções, por prefixo da subclasse ou pela descrição. A tela pede confirmação, porque a troca impacta o enquadramento das próximas solicitações.

O principal anterior deixa de ser principal e continua vinculado. A empresa fica com um único principal. CNAE inativo ou inexistente é recusado. O enquadramento da LOUOS não roda no momento em que o principal é salvo. A troca fica registrada com a subclasse anterior e a nova.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Vincular CNAE principal](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** que a empresa tenha um CNAE principal ativo**, de modo que** a atividade principal do cadastro venha da tabela oficial

## História de Usuário: Vincular CNAE principal

**Como** cidadão com vínculo ativo **quero** escolher o CNAE principal**, de modo que** a atividade principal da empresa fique registrada

## Pré-requisitos

- Cidadão autenticado no portal, com e-mail confirmado e termo aceito
- Vínculo ativo com a empresa; em representação, o vínculo do representado
- CNAE ativo na tabela oficial

## Dependências

**Passadas**

- HU-151 — empresa cadastrada
- HU-034 — CNAE ativo na tabela oficial

**Futuras**

- O enquadramento das próximas solicitações usa este principal; ele não é calculado ao salvar

---

## Interface de Usuário

### Tela 1 — CNAE principal

**Caminho no menu:** Serviços > Minhas empresas > detalhe da empresa

*[Print da tela](screenshots/portal-empresas.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Definir principal ativo**
**Dado que** há vínculo ativo e um CNAE ativo da tabela oficial
**Quando** o cidadão confirma a definição
**Então** esse CNAE fica como único principal, o anterior permanece vinculado sem ser principal, a mensagem é "CNAE principal definido com sucesso." e a troca fica registrada

**Cenário 2: CNAE inativo ou inexistente**
**Dado que** o CNAE está inativo ou não existe
**Quando** a definição é enviada
**Então** o sistema recusa e informa que o CNAE não está ativo na tabela oficial

**Cenário 3: Sem vínculo ativo**
**Dado que** o usuário não tem vínculo ativo
**Quando** tenta definir o principal
**Então** o sistema recusa

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
