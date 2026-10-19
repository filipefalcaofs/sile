# HU.020 – MANTER CLASSIFICAÇÃO DE RISCO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O grau de risco municipal da subclasse é informado na ficha do CNAE, não numa tela separada de classificação de risco. Os níveis são Baixo, Médio risco e Alto, do Decreto nº 41.758/2026. Na comparação usada pelo motor, Baixo é menor que Médio risco, e Médio risco é menor que Alto.

A gravação atualiza a linha da versão vigente na hora, sem proposta de segunda pessoa. A publicação em lote, com outra pessoa, fica na importação oficial da planilha, fora desta ficha. Sem versão vigente, a ficha avisa e a gravação é recusada. A ficha não grava condicionante sanitária e não define, aqui, se Baixo segue na resposta automática ou se Alto vai para análise. Os gatilhos que encaminham o processo à análise técnica ficam em Regras > Gatilhos de risco.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter classificação de risco](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** registrar o grau de risco municipal na ficha do CNAE**, de modo que** a classificação vigente da subclasse fique gravada sem uma segunda tela

## História de Usuário: Manter classificação de risco

**Como** administrador **quero** informar o grau de risco municipal ao cadastrar ou editar o CNAE**, de modo que** a classificação vigente da subclasse fique registrada na hora

## Pré-requisitos

- Administrador que mantém CNAEs; a lista que leva à ficha pode ser consultada por quem consulta CNAEs, mas abrir a edição exige a manutenção
- Existir versão vigente do risco municipal
- Grau obrigatório: Baixo, Médio risco ou Alto

## Dependências

**Passadas**

- HU-011 — a ficha do CNAE é a mesma desta classificação

**Futuras**

- O roteamento da resposta automática pelos gatilhos não é esta ficha

---

## Interface de Usuário

### Tela 1 — Grau de risco na ficha do CNAE

**Caminho no menu:** Regras > CNAEs

*[Print da tela](screenshots/gestao-cnaes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Grau na ficha**
**Dado que** existe versão vigente de risco municipal
**Quando** o administrador salva o CNAE com Baixo, Médio risco ou Alto
**Então** a classificação daquela subclasse na versão vigente fica com o grau informado

**Cenário 2: Grau obrigatório**
**Dado que** o cadastro ou a edição segue sem um dos três graus
**Quando** a pessoa tenta gravar
**Então** o sistema recusa a gravação

**Cenário 3: Sem segunda pessoa na ficha**
**Dado que** o administrador altera o grau na ficha
**Quando** salva
**Então** a linha vigente é atualizada na hora, sem proposta de outro usuário

**Cenário 4: Rótulos**
**Dado que** a ficha é aberta
**Quando** o servidor vê os graus
**Então** as opções aparecem como Baixo, Médio risco e Alto

**Cenário 5: Troca da subclasse**
**Dado que** a edição muda a subclasse do CNAE
**Quando** a ficha é salva
**Então** a classificação que apontava para a subclasse anterior passa a apontar para a nova

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
