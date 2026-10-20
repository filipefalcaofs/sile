# HU.007 – MANTER SETORES
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

O administrador cadastra os setores da SEDUR, inativa e define quais analistas pertencem a cada um, para a caixa de análise distribuir processos por setor. O nome é único. Não há exclusão. Setor inativo deixa de receber distribuição nova e continua consultável. Setor novo nasce ativo quando a situação não é enviada.

A lista busca pelo nome, filtra a situação, ordena por nome e mostra 10, 15, 25 ou 50 itens, no padrão de 15. Cada setor mostra quantos analistas tem, quantos processos tem e quem está vinculado. Os candidatos ao vínculo são usuários ativos que analisam processos.

Inativar um setor que tem processo em análise ou em pendência ocorre mesmo assim. A resposta avisa a quantidade e pede redistribuição na distribuição. O histórico e o vínculo permanecem. Esta tela não redistribui os processos. A inativação não é bloqueada por processo aberto.

Sincronizar analistas grava o conjunto exato daquele setor. Tirar um analista deste setor não o remove de outro. Um analista pode estar em mais de um setor. A auditoria registra quem estava antes e quem ficou depois.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter setores](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** cadastrar setores e definir os analistas de cada um**, de modo que** a caixa de análise distribua processos por setor

## História de Usuário: Manter setores

**Como** administrador da SEDUR **quero** inativar o setor sem apagá-lo**, de modo que** processo em aberto permaneça nele até a redistribuição em outra tela

## Pré-requisitos

- Atribuição de manter setores

## Dependências

**Passadas**

- Usuários ativos que analisam processos, para o vínculo

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Setores

**Caminho no menu:** Administração > Setores

*[Print da tela](screenshots/gestao-setores.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro**
**Dado que** o nome ainda não foi usado
**Quando** o administrador cadastra o setor
**Então** o setor entra ativo, salvo se a situação vier inativa

**Cenário 2: Nome repetido**
**Dado que** já existe setor com aquele nome
**Quando** outro cadastro repete o nome
**Então** o nome é recusado

**Cenário 3: Inativar com processo aberto**
**Dado que** o setor ativo tem processo em análise ou em pendência
**Quando** o administrador inativa
**Então** o setor fica inativo, os processos continuam nele e a tela avisa a quantidade em aberto

**Cenário 4: Analista em mais de um setor**
**Dado que** o analista já está no setor A
**Quando** o administrador inclui esse analista no setor B
**Então** ele permanece no setor A e passa a constar também no setor B

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
