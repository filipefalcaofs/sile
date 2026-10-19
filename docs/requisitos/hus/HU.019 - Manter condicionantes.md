# HU.019 – MANTER CONDICIONANTES
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Não há um cadastro próprio de condicionante, com pergunta reutilizável, regra numérica, crítica e textos de parecer. O que a pessoa vê são as condicionantes da planilha de tratamento vigente, na aba Enquadramento do Motor de regras: número, texto e observação. Elas mudam quando uma planilha nova é importada e publicada. Não dá para criar, editar ou excluir uma condicionante isolada.

No rascunho do Quadro 10, a referência de condicionante é um texto opcional de até 50 caracteres. Ela não é conferida contra uma lista de perguntas. Quando a permissão é permitido com condições, o motor usa essa referência no parecer consolidado. A ficha do CNAE não edita condicionante sanitária, e esta tela não reclassifica o risco pela resposta do requerente.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter condicionantes](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** exibir as condicionantes da planilha vigente**, de modo que** o texto que acompanha a versão publicada seja consultável

## História de Usuário: Manter condicionantes

**Como** servidor que consulta o motor **quero** ver as condicionantes da planilha de tratamento vigente**, de modo que** eu saiba quais textos acompanham a versão publicada

## Pré-requisitos

- Leitura: quem consulta CNAEs, na aba Enquadramento
- Troca da lista: importar e publicar a planilha, por quem mantém o motor
- No Quadro 10, a referência de condicionante é opcional e tem até 50 caracteres

## Dependências

**Passadas**

- HU-015 — a lista vem da versão vigente da planilha de tratamento
- HU-016 — a referência textual fica na linha do Quadro 10

**Futuras**

- HU-042 — o motor usa essas condicionantes no parecer

---

## Interface de Usuário

### Tela 1 — Condicionantes na aba Enquadramento

**Caminho no menu:** Regras > Motor de regras > Enquadramento

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Leitura na aba Enquadramento**
**Dado que** a versão vigente da planilha de tratamento tem condicionantes
**Quando** quem consulta CNAEs abre a aba Enquadramento
**Então** vê número, texto e observação de cada condicionante dessa versão, e a contagem no resumo

**Cenário 2: Sem cadastro isolado**
**Dado que** a pessoa procura criar, alterar ou excluir uma condicionante sozinha
**Quando** percorre o Motor de regras
**Então** não há essa manutenção; a alteração vem da importação da planilha

**Cenário 3: Referência no Quadro 10**
**Dado que** existe rascunho do Quadro 10
**Quando** o administrador grava a referência de condicionante
**Então** o texto de até 50 caracteres fica na linha do rascunho, sem consulta a um cadastro de perguntas

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
