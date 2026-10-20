# HU.146 – CONSULTAR HISTÓRICO DE ACESSOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O cidadão consulta os acessos da própria conta: entrada, falha, bloqueio, conta inativa, indisponibilidade da rede da SEDUR e falha ao sincronizar o setor. Cada linha mostra o evento, o endereço de rede, se foi no portal ou no console, e a data. O navegador não aparece. Falhas anteriores ao vínculo da conta também entram quando o e-mail do registro é o da pessoa.

O administrador consulta o histórico de outra conta a partir de Administração > Usuários, na ação Acessos, e pode exportar o conjunto em CSV, planilha ou PDF. Essa consulta é tratada como dado pessoal e fica registrada. O cidadão não escolhe outra conta e não exporta. Não há filtro por período, canal ou tipo de evento, nem histórico de saída, nem edição ou exclusão desses registros nesta consulta.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Consultar histórico de acessos](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** mostrar o histórico de acessos da conta**, de modo que** a pessoa reconheça tentativas e o administrador apure o uso de outra conta

## História de Usuário: Consultar histórico de acessos

**Como** cidadão **quero** ver meus acessos, falhas e bloqueios**, de modo que** eu reconheça tentativas na minha conta

## Pré-requisitos

- No portal: pessoa autenticada, com e-mail confirmado e termo aceito
- No console: administrador autenticado, com acesso ao console e termo aceito, consultando a partir da lista de usuários
- A lista é paginada

## Dependências

**Passadas**

- HU-001 — os eventos de entrada, falha, bloqueio e conta inativa nascem na autenticação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Histórico de acessos

**Caminho no menu:** Minha conta > Histórico de acessos. No console: Administração > Usuários, ação Acessos

*[Print da tela](screenshots/portal-acessos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cidadão vê os próprios registros**
**Dado que** o cidadão tem registros de entrada e de falha no e-mail dele
**Quando** abre o Histórico de acessos
**Então** a lista pagina esses eventos com endereço de rede, ambiente e data, sem escolha de outra pessoa

**Cenário 2: Servidor sem a consulta administrativa**
**Dado que** o servidor não é quem pode consultar acessos de qualquer conta
**Quando** tenta abrir o histórico de outra pessoa
**Então** o sistema impede a consulta

**Cenário 3: Consulta administrativa registrada**
**Dado que** o administrador abre o histórico de outra conta sem pedir exportação
**Quando** a lista é exibida
**Então** ele vê os registros daquela pessoa ou daquele e-mail e a consulta fica registrada como acesso a dado pessoal

**Cenário 4: Exportação**
**Dado que** o administrador pede o histórico em CSV, planilha ou PDF
**Quando** confirma a exportação
**Então** o sistema entrega o arquivo do conjunto daquela conta, e não a tela de lista

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
