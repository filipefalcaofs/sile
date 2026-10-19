# HU.061 – CRIAR SOLICITAÇÃO DE VIABILIDADE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O cidadão autenticado, com e-mail confirmado e termo de proteção de dados aceito, abre um rascunho de solicitação no portal. Escolhe um tipo de serviço ativo e uma empresa com vínculo ativo. O rascunho nasce com origem no portal, para continuar o preenchimento e, depois, protocolar. O sistema ainda não oferece, neste passo, abertura com origem na Junta Comercial nem bloqueio por pedido repetido.

Se a mesma empresa já tem solicitação não cancelada dentro da janela definida pela SEDUR, ou ainda protocolada, o rascunho é gravado mesmo assim e o cidadão vê o alerta do processo anterior. A repetição por inscrição imobiliária não é verificada aqui. Em representação, o requerente gravado é quem está sendo representado; quem criou continua sendo o usuário da sessão.

A lista mostra só as solicitações desse requerente. Dá para filtrar por protocolo ou razão social e pela situação, ordenar e paginar. Só o rascunho pode continuar sendo editado. O cancelamento aparece quando a situação está entre as que a SEDUR deixou canceláveis. Se a abertura de solicitação no portal estiver desligada, a criação avisa a indisponibilidade e o rascunho não nasce. Listar solicitações já existentes continua disponível.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Criar solicitação de viabilidade](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o cidadão autenticado **quero** abrir um rascunho de solicitação**, de modo que** eu continue o preenchimento e depois protocole o pedido

## História de Usuário: Criar solicitação de viabilidade

**Como** cidadão autenticado no portal **quero** criar um rascunho escolhendo o tipo de serviço e a empresa**, de modo que** o pedido fique vinculado a um serviço ativo e a uma empresa do meu vínculo

## Pré-requisitos

- Sessão no portal, e-mail confirmado e termo de proteção de dados aceito
- Tipo de serviço ativo
- Empresa com vínculo ativo do requerente efetivo
- Abertura de solicitação no portal ligada

## Dependências

**Passadas**

- Cadastro da empresa e do tipo de serviço

**Futuras**

- HU.062 a HU.068, para imóvel, atividade, documentos e protocolo

---

## Interface de Usuário

### Tela 1 — Criar solicitação de viabilidade

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Rascunho criado**
**Dado que** o cidadão autenticado tem vínculo ativo na empresa e escolhe um tipo de serviço ativo
**Quando** confirma a criação
**Então** surge um rascunho de origem no portal, com ele como requerente, e a lista confirma a criação

**Cenário 2: Pedido anterior da mesma empresa**
**Dado que** já existe solicitação não cancelada da mesma empresa dentro da janela, ou ainda protocolada
**Quando** a nova é criada
**Então** o rascunho é gravado e o cidadão vê o alerta do processo anterior

**Cenário 3: Empresa ou serviço inválidos**
**Dado que** a empresa não tem vínculo ativo, o tipo de serviço está inativo ou quem tenta editar não é o requerente
**Quando** tenta criar ou editar
**Então** o sistema recusa a ação

**Cenário 4: Abertura desligada**
**Dado que** a abertura de solicitação no portal está desligada
**Quando** o cidadão tenta criar
**Então** recebe o aviso de indisponibilidade e o rascunho não é criado

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
