# HU.111 – REALIZAR OCR DOCUMENTAL
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

A leitura de anexo por visão produz uma transcrição guardada como sugestão. Ela não vira fato do processo, não altera a ficha de análise e não altera a decisão. Enviar o documento no portal não dispara essa leitura. Não há botão na ficha que a peça. Se uma sugestão de leitura já estiver registrada, a ficha apenas a lista.

Com a função desligada ou sem provedor de visão, nada é transcrito e nenhum texto simulado é gravado. Com a função ligada e provedor de visão ativo, a transcrição pode ser gerada e fica como sugestão de leitura. Documento ilegível, nessa mesma leitura, exige revisão humana da sugestão. A leitura é tratada como dado pessoal. O documento precisa existir para a leitura usar o arquivo já gravado.

---

## Sumário

- [Épico: EP14 — Inteligência Artificial](#épico)
- [História de Usuário: Realizar OCR documental](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP14 — Inteligência Artificial

**Como** analista **quero** uma transcrição do documento anexado quando a leitura estiver disponível**, de modo que** o texto ajude a leitura sem decidir a viabilidade

## História de Usuário: Realizar OCR documental

**Como** analista **quero** que a transcrição, se existir, apareça só como sugestão**, de modo que** o arquivo original continue sendo o documento do processo

## Pré-requisitos

- Função de leitura ligada e provedor de visão ativo, para haver transcrição
- Documento já gravado

## Dependências

**Passadas**

- Anexo já enviado na solicitação

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Leitura do documento

**Caminho no menu:** Não há tela que dispare a leitura. A ficha de análise, em Operação > Consulta de processos, só mostra sugestão de leitura já registrada.

*[Print da tela](screenshots/gestao-ia.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Função desligada**
**Dado que** a leitura está desligada ou não há provedor de visão
**Quando** se pede a transcrição
**Então** nenhuma sugestão é criada e nenhum texto simulado aparece como lido

**Cenário 2: Função ligada**
**Dado que** a leitura está ligada e há provedor de visão
**Quando** a transcrição é produzida
**Então** ela fica como sugestão e não muda a situação do processo nem a decisão

**Cenário 3: Envio do anexo**
**Dado que** o cidadão envia um documento no portal
**Quando** o anexo é gravado
**Então** essa leitura não é chamada

**Cenário 4: Ficha de análise**
**Dado que** já existe sugestão de leitura gravada
**Quando** o analista abre a ficha
**Então** a ficha lista essa sugestão e não cria uma leitura nova

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
