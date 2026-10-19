# HU.015 – MANTER QUADRO 7
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Não há um cadastro próprio do Quadro 7. O enquadramento da atividade — identificação LOUOS, faixas de área, classificação e identificação da TLL — fica na planilha de tratamento, na aba Enquadramento do Motor de regras. Quem consulta CNAEs abre essa aba. Quem só consulta os quadros da LOUOS não a abre.

A manutenção é o arquivo, não a linha avulsa: não há inclusão, edição ou exclusão de uma linha isolada. Importar uma planilha ainda não usada abre rascunho e informa quantas regras ficaram sem ramo explícito. A versão vigente só muda na publicação. O autor do rascunho pode publicar; esta planilha não exige outra pessoa. Arquivo já importado ou já vigente não gera versão nova.

---

## Sumário

- [Épico: EP02 — Administração](#épico)
- [História de Usuário: Manter Quadro 7](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** a SEDUR **quero** manter o enquadramento pela planilha de tratamento**, de modo que** a identificação LOUOS e as faixas de área vigentes venham do arquivo publicado

## História de Usuário: Manter Quadro 7

**Como** administrador **quero** importar a planilha de regras de tratamento e publicar o rascunho**, de modo que** o enquadramento vigente passe a ser o da planilha importada

## Pré-requisitos

- Consulta da aba Enquadramento: quem consulta CNAEs
- Importar e publicar: administrador que mantém o motor de regras
- Arquivo de planilha, com até 20 MB
- Publicar só um rascunho desta planilha

## Dependências

**Passadas**

- HU-011 — a busca da lista usa o CNAE e a denominação da tabela oficial

**Futuras**

- HU-038 — o motor lê o ramo desta planilha vigente, e não um Quadro 7 separado

---

## Interface de Usuário

### Tela 1 — Enquadramento

**Caminho no menu:** Regras > Motor de regras > Enquadramento

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Consulta da vigente**
**Dado que** a pessoa consulta CNAEs e existe versão vigente da planilha de tratamento
**Quando** abre a aba Enquadramento
**Então** vê a versão, as contagens e as linhas com identificação LOUOS, faixas de área e identificação da TLL

**Cenário 2: Importar planilha nova**
**Dado que** o administrador envia uma planilha ainda não importada
**Quando** o arquivo é recebido
**Então** abre rascunho e a versão vigente permanece até a publicação

**Cenário 3: Arquivo repetido**
**Dado que** o conteúdo do arquivo já gerou versão
**Quando** o administrador importa de novo
**Então** nenhuma versão nova é criada e a mensagem informa que o arquivo já está vigente ou já foi importado

**Cenário 4: Publicar sem segunda pessoa**
**Dado que** existe rascunho da planilha
**Quando** o autor publica
**Então** a versão entra em uso; esta publicação não exige outro publicador

**Cenário 5: Arquivo que não é planilha**
**Dado que** o arquivo não é uma planilha no formato aceito ou não foi enviado
**Quando** o administrador tenta importar
**Então** o sistema pede a planilha de regras e informa que ela precisa ser do tipo aceito

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
