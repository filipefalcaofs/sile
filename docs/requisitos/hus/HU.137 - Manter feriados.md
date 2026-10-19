# HU.137 – MANTER FERIADOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador que mantém os dados do sistema cadastra, corrige e inativa os feriados que o cálculo de dias úteis desconta. Não há exclusão. Feriado inativo sai da contagem e continua na lista. A data é única: não há dois feriados no mesmo dia. Feriado novo nasce ativo e sem repetir todo ano, quando esses campos não vêm no envio.

A lista busca pelo nome, filtra a situação e mostra 10, 15, 25 ou 50 itens, no padrão de 15, da data mais recente para a mais antiga. Cada item traz data, nome, se repete todo ano e se está ativo. Incluir e editar gravam esses campos. A data é obrigatória. O nome é obrigatório e tem até 255 caracteres. A recorrência anual e a situação são informadas no cadastro. Não há importação de calendário por arquivo. Esta tela não escolhe se o prazo é em dias úteis ou em dias corridos: ela só mantém o calendário usado na contagem de dias úteis.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter feriados](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** cadastrar, corrigir e inativar feriados**, de modo que** o prazo em dias úteis não conte essas datas

## História de Usuário: Manter feriados

**Como** administrador da SEDUR **quero** um calendário com data única e recorrência anual**, de modo que** inativar preserve o histórico em vez de apagar o feriado

## Pré-requisitos

- Atribuição de manter os dados do sistema

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Feriados

**Caminho no menu:** Configuração > Feriados

*[Print da tela](screenshots/gestao-feriados.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Data já usada**
**Dado que** já existe feriado numa data
**Quando** o administrador tenta cadastrar outro na mesma data
**Então** a data é recusada

**Cenário 2: Recorrência anual**
**Dado que** o administrador cadastra o feriado com repetição todo ano
**Quando** o registro é gravado
**Então** o feriado fica marcado para repetir anualmente

**Cenário 3: Inativar**
**Dado que** o feriado está ativo
**Quando** o administrador inativa
**Então** ele deixa de contar nos dias úteis e continua na lista filtrada por inativos

**Cenário 4: Sem exclusão**
**Dado que** o administrador está na lista
**Quando** procura apagar o feriado
**Então** não há exclusão; a saída do calendário de contagem é a inativação

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
