# HU.098 – CONSULTAR TRILHA DE AUDITORIA
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

O auditor consulta a trilha global do que aconteceu no sistema e, na mesma tela, o histórico de acessos. A visão padrão é a de atividades. A visão de alterações mostra só o que mudou. A visão de acessos lista entrada, saída, falha e bloqueio, com usuário, e-mail, endereço de rede, evento e canal. Atividade e acesso não se misturam numa única linha do tempo.

Os filtros de atividade são período, usuário, registro, evento e resultado. Em acessos valem período, usuário e evento. Fonte não reconhecida volta para as atividades, sem mensagem de erro fabricada. A página pode ter 10, 20, 50 ou 100 itens; outro tamanho usa o padrão de 20. Datas só entram quando o período é reconhecido. A consulta fica registrada na trilha e é tratada como dado pessoal.

Pedir planilha ou PDF na mesma tela segue para a exportação. A trilha de um protocolo específico não é esta tela: ela está no relatório por processo.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico)
- [História de Usuário: Consultar trilha de auditoria](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** auditor **quero** filtrar a trilha do sistema**, de modo que** eu fiscalize quem fez o quê, quando e com qual resultado

## História de Usuário: Consultar trilha de auditoria

**Como** auditor **quero** ver atividades e acessos na mesma tela, em visões separadas**, de modo que** o histórico de quem entrou no sistema não se confunda com o que foi alterado

## Pré-requisitos

- Servidor do console com atribuição de consultar a auditoria
- Sem essa atribuição, o acesso é negado

## Dependências

**Passadas**

- Atividades e acessos já registrados

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Trilha de auditoria

**Caminho no menu:** Auditoria > Trilha de auditoria

*[Print da tela](screenshots/gestao-auditoria.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Visão padrão**
**Dado que** o auditor pode consultar a auditoria
**Quando** abre a trilha
**Então** vê as atividades mais recentes e a consulta fica registrada

**Cenário 2: Acessos**
**Dado que** o auditor escolhe a visão de acessos
**Quando** consulta
**Então** a lista mostra entradas, saídas, falhas e bloqueios, e não as atividades do sistema

**Cenário 3: Alterações**
**Dado que** o auditor escolhe a visão de alterações
**Quando** consulta
**Então** a lista fica só com o que mudou

**Cenário 4: Visão não reconhecida**
**Dado que** a visão pedida não existe
**Quando** a tela abre
**Então** o sistema mostra as atividades, sem inventar um erro

**Cenário 5: Sem atribuição**
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
