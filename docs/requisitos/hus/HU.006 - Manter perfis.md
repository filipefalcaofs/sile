# HU.006 – MANTER PERFIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador cria e ajusta perfis e o conjunto exato do que cada um pode fazer. O que está marcado na tela substitui o conjunto anterior. A lista busca pelo nome, mostra as capacidades, a quantidade de usuários e se o perfil é estrutural, e pode ser exportada em CSV, planilha ou PDF.

Perfil protegido do sistema, e o perfil do cidadão, não mudam de nome e não são excluídos. Perfil com usuário vinculado também não é excluído. O perfil de administrador não pode ficar sem acesso ao console. Não há cópia de perfil, nem remoção de uma capacidade do catálogo do sistema: a tela só associa capacidades que já existem.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter perfis](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** manter os perfis de acesso**, de modo que** cada papel faça só o que lhe foi marcado

## História de Usuário: Manter perfis

**Como** administrador **quero** criar, renomear, marcar capacidades e excluir perfis que não sejam protegidos nem tenham usuários**, de modo que** eu separe o que cada papel pode fazer no Viabiliza

## Pré-requisitos

- Administrador autenticado com acesso à manutenção de perfis
- Nome obrigatório, com até 255 caracteres e ainda não usado
- Cada capacidade marcada precisa já existir no catálogo

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- HU-005 — o papel atribuído à conta vem destes perfis

---

## Interface de Usuário

### Tela 1 — Perfis

**Caminho no menu:** Administração > Perfis

*[Print da tela](screenshots/gestao-perfis.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Criar com capacidades existentes**
**Dado que** o administrador informa um nome ainda não usado e um conjunto de capacidades já cadastradas
**Quando** cria o perfil
**Então** o perfil passa a ter exatamente esse conjunto

**Cenário 2: Perfil protegido**
**Dado que** o perfil é protegido ou é o perfil do cidadão
**Quando** o administrador tenta renomeá-lo ou excluí-lo
**Então** o nome permanece e a exclusão é recusada

**Cenário 3: Administrador sem acesso ao console**
**Dado que** o perfil é o de administrador
**Quando** a atualização deixa de marcar o acesso ao console
**Então** a gravação é recusada

**Cenário 4: Exclusão com usuários**
**Dado que** o perfil não é protegido e tem ao menos um usuário
**Quando** o administrador tenta excluí-lo
**Então** o perfil permanece e a mensagem informa que ele possui usuários vinculados

**Cenário 5: Atualizar o conjunto**
**Dado que** o perfil não é protegido
**Quando** o administrador salva outro conjunto de capacidades
**Então** o perfil fica só com o que foi marcado, e o registro guarda o conjunto anterior e o novo

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
