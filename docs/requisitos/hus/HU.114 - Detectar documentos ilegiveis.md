# HU.114 – DETECTAR DOCUMENTOS ILEGÍVEIS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Revisão pela implementação vigente | 2.0 | Sudoeste Informática |
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

A detecção de documento ilegível faz parte da mesma leitura por visão da transcrição. Não é uma função separada e nenhum fluxo de anexo a chama hoje. Quando a leitura marca o documento como ilegível, a sugestão exige revisão humana e não vira conteúdo do processo. Não indefere, não devolve o anexo e não abre pendência ao requerente por conta própria.

Com a leitura desligada, não surge alerta de ilegibilidade. O que fica gravado, quando a leitura ocorre, é a sugestão de leitura; não há um aviso separado só de ilegibilidade.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Detectar documentos ilegíveis](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** analista **quero** ser avisado quando o documento não puder ser lido**, de modo que** uma transcrição falha não seja tratada como conteúdo do processo

## História de Usuário: Detectar documentos ilegíveis

**Como** analista **quero** que a ilegibilidade peça revisão humana da sugestão**, de modo que** o processo não seja indeferido nem devolvido só por esse aviso

## Pré-requisitos

- A mesma leitura documental ligada, com provedor de visão, para haver detecção
- Documento já gravado

## Dependências

**Passadas**

- Leitura documental do anexo

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Documento ilegível

**Caminho no menu:** Não há tela própria. Nenhum envio de anexo dispara esta detecção hoje.

*[Print da tela](screenshots/gestao-ia.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Documento marcado como ilegível**
**Dado que** a leitura identifica o anexo como ilegível
**Quando** a sugestão é gravada
**Então** ela pede revisão humana e não altera a decisão do processo

**Cenário 2: Leitura desligada**
**Dado que** a leitura documental está desligada
**Quando** o anexo é tratado
**Então** não surge alerta de ilegibilidade

**Cenário 3: Sem pendência automática**
**Dado que** o documento foi marcado como ilegível na sugestão
**Quando** o processo segue
**Então** o requerente não recebe pendência automática e o protocolo não é recusado por este aviso

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
