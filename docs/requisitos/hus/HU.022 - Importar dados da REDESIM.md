# HU.022 – IMPORTAR DADOS DA REDESIM
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

A operação inclui ou atualiza empresa e CNAEs a partir de um arquivo no formato de referência tratado como REDESIM. Não há tela no portal nem no console para o cidadão ou o analista fazer essa importação. O arquivo pode ser processado na hora ou deixado para processamento em seguida. A estrutura do arquivo é referência, ainda a validar com a SEDUR. O sistema não consulta a REDESIM, o REGIN nem a JUCEB: a origem dos dados é o arquivo.

Empresa nova nasce como vinda da REDESIM, com o protocolo e a data da sincronização. Empresa já existente é atualizada sem mudar a origem. O conjunto de CNAEs do item substitui o anterior. A empresa importada não aparece em Minhas empresas até existir vínculo por outro fluxo. O arquivo não abre solicitação de viabilidade. Área ocupada, inscrição imobiliária e forma de atuação são ignoradas.

---

## Sumário

- [Épico: EP03 — Cadastro Empresarial](#épico)
- [História de Usuário: Importar dados da REDESIM](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP03 — Cadastro Empresarial

**Como** a SEDUR **quero** incluir ou atualizar empresas a partir do arquivo de referência da REDESIM**, de modo que** o cadastro e os CNAEs entrem sem abrir viabilidade

## História de Usuário: Importar dados da REDESIM

**Como** gestor da operação **quero** importar um arquivo de empresas**, de modo que** o cadastro e os CNAEs sejam criados ou atualizados sem solicitação de viabilidade

## Pré-requisitos

- Arquivo existente e legível, com um protocolo ou uma lista de protocolos
- Cada item precisa de protocolo, razão social, CNPJ válido e CNAE principal existente na tabela oficial
- Não há tela para o cidadão nem para o analista

## Dependências

**Passadas**

- HU-011 — o CNAE principal precisa existir na tabela oficial

**Futuras**

- O vínculo do cidadão com a empresa importada depende de outro fluxo; esta importação não o cria

---

## Interface de Usuário

### Tela 1 — Importação por arquivo

**Caminho no menu:** Não há tela no portal nem no console; a operação processa o arquivo

#### Comportamento Esperado Tela 1

**Cenário 1: Arquivo válido cria ou atualiza**
**Dado que** o item tem protocolo, razão social, CNPJ válido e CNAE principal existente
**Quando** a importação processa o arquivo
**Então** a empresa é criada ou atualizada, os CNAEs do item substituem o conjunto anterior e o relatório fica registrado

**Cenário 2: Item inválido não grava empresa**
**Dado que** o item está sem protocolo, sem razão social, com CNPJ inválido ou com CNAE principal fora da tabela
**Quando** a importação roda
**Então** o item entra em rejeitados e não é gravado

**Cenário 3: Avisos que não rejeitam**
**Dado que** o CNAE principal está inativo, ou um secundário está inativo
**Quando** o item é processado
**Então** o sistema registra aviso e segue; secundário inexistente ou igual ao principal é ignorado com aviso

**Cenário 4: Sem canal da REDESIM e sem viabilidade**
**Dado que** a importação executa
**Quando** o relatório é produzido
**Então** a origem dos dados é o arquivo, nenhuma solicitação de viabilidade é aberta e, se todos os itens forem rejeitados, o resultado é falha

**Cenário 5: Arquivo ausente ou ilegível**
**Dado que** o arquivo não existe ou não pode ser lido
**Quando** a operação dispara a importação
**Então** o sistema informa que o arquivo não foi encontrado ou que o conteúdo é inválido, e nada é gravado

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
