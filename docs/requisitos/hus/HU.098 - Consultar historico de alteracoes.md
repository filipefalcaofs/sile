# HU.098 – CONSULTAR HISTÓRICO DE ALTERAÇÕES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O auditor consulta, na trilha de auditoria, somente os registros em que algum dado mudou. Acesso, consulta e evento sem diferença de conteúdo não entram nesta visão. A ordem é da alteração mais recente para a mais antiga. A própria consulta fica registrada na trilha.

Os filtros de período, usuário, tipo de registro, evento e resultado valem quando preenchidos. Filtro vazio é ignorado. Data inválida não restringe a lista. A página pode ter 10, 20, 50 ou 100 itens; outro tamanho usa o padrão de 20.

Não há comparação visual além do que a lista já mostra. Alteração de regra da lei urbanística que não tenha ficado nesta trilha não aparece aqui.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico)
- [História de Usuário: Consultar histórico de alterações](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** auditor **quero** ver só o que mudou nos dados**, de modo que** a conferência não se misture com acessos e eventos sem alteração

## História de Usuário: Consultar histórico de alterações

**Como** auditor **quero** filtrar o histórico de alterações**, de modo que** eu confira o que mudou, quem alterou e quando

## Pré-requisitos

- Servidor autenticado no console, com o termo de proteção de dados aceito
- Atribuição de consultar a auditoria
- Sem essa atribuição, o acesso é negado e a tentativa fica registrada

## Dependências

**Passadas**

- Trilha de auditoria com alterações já gravadas

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Histórico de alterações

**Caminho no menu:** Auditoria > Trilha de auditoria, na visão de alterações

*[Print da tela](screenshots/gestao-auditoria.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Só o que mudou**
**Dado que** a trilha tem registros com e sem mudança de dados
**Quando** o auditor abre a visão de alterações
**Então** a lista mostra apenas os registros em que algum dado mudou

**Cenário 2: Filtros**
**Dado que** o auditor informa período, usuário ou o registro alterado
**Quando** aplica a consulta
**Então** a lista fica restrita a esse recorte, da mais recente para a mais antiga, e a consulta entra na trilha

**Cenário 3: Período invertido ou data inválida**
**Dado que** a data informada não é válida
**Quando** o auditor consulta
**Então** essa data não restringe o resultado

**Cenário 4: Sem permissão de auditoria**
**Dado que** o servidor não consulta a auditoria
**Quando** tenta abrir a tela
**Então** o acesso é negado

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
