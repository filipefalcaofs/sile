# HU.090 – NOTIFICAR VENCIMENTOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Antes do vencimento, o sistema avisa o analista responsável sobre o prazo de análise e o requerente sobre o prazo do convite. A antecedência padrão é de três dias. O aviso não muda situação, não redistribui e não indefere. Prazo já vencido não entra aqui: o tratamento do vencido é outro passo.

Entram processos em análise, com responsável e com prazo ainda no futuro, dentro da antecedência. Entram convites abertos na mesma janela. Sem responsável, o processo é pulado. Sem requerente, o convite é pulado. E-mail vazio ou inválido não cria comunicação. No padrão, o aviso vai por e-mail e pelo aviso interno.

O mesmo aviso não se repete na janela: para o processo, desde o início da etapa; para o convite, desde o começo da antecedência. O sistema ainda não oferece este alerta para processo sem responsável.

---

## Sumário

- [Épico: EP11 — Pendências e Comunicação](#épico)
- [História de Usuário: Notificar vencimentos](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP11 — Pendências e Comunicação

**Como** a SEDUR **quero** avisar quem responde por um prazo que está perto de vencer**, de modo que** a fila e o convite sejam tratados a tempo, sem mudar a situação

## História de Usuário: Notificar vencimentos

**Como** analista responsável **quero** ser avisado do prazo de análise ainda não vencido**, de modo que** o requerente também seja avisado do prazo do convite, uma vez na janela

## Pré-requisitos

- Processo em análise com responsável e prazo futuro dentro da antecedência, ou convite aberto na mesma janela
- Destinatário com e-mail utilizável

## Dependências

**Passadas**

- Prazo de análise já gravado no processo
- HU.087 — Solicitar pendência, para o prazo do convite

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Notificar vencimentos

**Caminho no menu:** Operação > Consulta de processos

*[Print da tela](screenshots/gestao-notificacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Prazo de análise**
**Dado que** o processo está em análise, tem responsável e o prazo está no futuro, dentro da antecedência
**Quando** a verificação diária roda
**Então** o analista é avisado uma vez na janela

**Cenário 2: Prazo do convite**
**Dado que** o convite está aberto e o prazo está na mesma janela
**Quando** a verificação diária roda
**Então** o requerente é avisado uma vez

**Cenário 3: Repetição na mesma janela**
**Dado que** o aviso daquela janela já foi registrado
**Quando** a verificação roda de novo
**Então** a comunicação não se duplica

**Cenário 4: Prazo já vencido**
**Dado que** o prazo já venceu
**Quando** a verificação roda
**Então** este aviso não é enviado e nenhuma situação muda

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
