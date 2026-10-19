# HU.111 – MIGRAR DADOS DO SISTEMA LEGADO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema ainda não traz processos, viabilidades nem cadastros do sistema anterior. Não há carga, correspondência de dados nem convivência com o legado. Ajustar a numeração de documentos de viabilidade já gravados no formato antigo não importa base externa e não cria processo histórico. Decisão antiga sem o caminho detalhado das regras é explicada como não registrada; isso não copia o sistema anterior. Menção a formulário ou tela antiga no produto também não é migração.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Migrar dados do sistema legado](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** gestor **quero** trazer o histórico do sistema anterior quando essa carga existir**, de modo que** decisão antiga sem detalhe não seja lida como processo migrado

## História de Usuário: Migrar dados do sistema legado

**Como** gestor **quero** saber que a migração ainda não ocorre**, de modo que** a renumeração de documentos já gravados não seja tomada por importação

## Pré-requisitos

- Nenhum, porque a carga não está disponível

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Migração do sistema anterior

**Caminho no menu:** Não há tela nem rotina de importação do sistema anterior.

#### Comportamento Esperado Tela 1

**Cenário 1: Trazer o histórico**
**Dado que** o gestor espera os processos do sistema anterior
**Quando** procura a carga
**Então** o sistema ainda não consulta nem traz este serviço

**Cenário 2: Renumerar documento já gravado**
**Dado que** há documentos de viabilidade no formato antigo de numeração
**Quando** a numeração interna é ajustada
**Então** só os números já gravados mudam e nenhum processo externo é criado

**Cenário 3: Decisão antiga sem detalhe**
**Dado que** uma decisão não tem o caminho das regras gravado
**Quando** alguém consulta a explicação
**Então** os passos ausentes aparecem como não registrados e o sistema anterior não é copiado

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
