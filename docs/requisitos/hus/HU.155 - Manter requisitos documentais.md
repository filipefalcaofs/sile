# HU.155 – MANTER REQUISITOS DOCUMENTAIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador mantém o requisito documental e os CNAEs ativos aos quais ele se vincula. A lista busca por código ou nome, filtra a situação, ordena e pagina em 10, 15, 25 ou 50 itens, com padrão de 15. Cada item mostra código, nome, descrição, obrigatoriedade, situação, instruções de validação e os CNAEs vinculados. No cadastro entram código, nome, descrição, obrigatoriedade e instruções. Sem a marca de obrigatório, o requisito nasce como não obrigatório. Na edição mudam nome, descrição, obrigatoriedade e instruções. Código e situação não entram nessa gravação. A ativação inverte a situação e não exclui. A busca de CNAEs para o vínculo só oferece CNAEs ativos. Gravar o vínculo substitui o conjunto inteiro: só CNAE ativo entra, e a troca fica registrada com os códigos anteriores e os posteriores. CNAE inativo é recusado. Desativar o requisito preserva o registro. A obrigatoriedade vinculada ao CNAE é o que a validação documental cobra na solicitação.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Manter requisitos documentais](#história-de-usuário-manter-requisitos-documentais)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Requisitos documentais](#tela-1--requisitos-documentais)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** definir qual documento a atividade exige**, de modo que** a validação cobre só o que estiver vinculado ao CNAE ativo.

## História de Usuário: Manter requisitos documentais

**Como** administrador autorizado a manter requisitos documentais **quero** cadastrar o requisito, marcar se é obrigatório e escolher os CNAEs**, de modo que** o conjunto vinculado seja exatamente o que foi gravado.

## Pré-requisitos

- Administrador autorizado a manter requisitos documentais
- CNAEs ativos, para o vínculo

## Dependências

**Passadas**

- Cadastro de CNAEs ativos

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Requisitos documentais

**Caminho no menu:** Regras > Requisitos documentais

*[Print da tela](screenshots/gestao-requisitos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Requisito obrigatório**
**Dado que** o administrador está autorizado e informa um código novo
**Quando** cadastra o requisito como obrigatório
**Então** ele aparece na lista como obrigatório

**Cenário 2: CNAE inativo**
**Dado que** o requisito existe e o CNAE está inativo
**Quando** a sincronização inclui esse CNAE
**Então** o vínculo é recusado

**Cenário 3: Conjunto exato**
**Dado que** o requisito está vinculado aos CNAEs A e B
**Quando** a sincronização envia só o CNAE ativo A
**Então** B deixa de estar vinculado e o registro mostra os códigos anteriores e os posteriores

**Cenário 4: Desativar**
**Dado que** o requisito está ativo
**Quando** o administrador inverte a situação
**Então** o requisito fica inativo e o registro permanece

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
