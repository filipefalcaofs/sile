# HU.067 – VALIDAR DOCUMENTOS OBRIGATÓRIOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

No rascunho, o requerente vê quais documentos obrigatórios faltam. O protocolo é recusado enquanto algum deles não tiver anexo ligado ao requisito. A conferência é de presença do anexo, não do conteúdo. O sistema ainda não oferece, neste passo, conferência humana ou automática do que está escrito no documento.

Entram os requisitos ativos e obrigatórios ligados a algum CNAE do pedido, sem repetir. Também entram, se estiverem cadastrados e ativos, a foto da fachada e, quando o imóvel é área pública, o termo de concessão. Código que não existe ou está inativo é ignorado. O sistema não cria requisito que não esteja cadastrado. Anexo sem requisito não cobre exigência. Sem vínculo por CNAE, só entram esses condicionais que existirem e estiverem ativos.

Se ainda faltar documento, o protocolo mostra os nomes e não gera número. Na contingência, a mesma falta desfaz a tentativa e remove os documentos gravados nela. Pedido com origem na Junta Comercial não é barrado por documento faltante neste passo.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Validar documentos obrigatórios](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** ver quais documentos obrigatórios faltam**, de modo que** eu anexe antes de protocolar e o sistema recuse o protocolo se ainda faltar algum

## História de Usuário: Validar documentos obrigatórios

**Como** requerente dono do rascunho **quero** que o protocolo só siga com os requisitos cobertos**, de modo que** um anexo sem requisito não conte como documento obrigatório

## Pré-requisitos

- Rascunho com os CNAEs do pedido
- Requisitos documentais cadastrados, ativos e marcados como obrigatórios quando ligados ao CNAE
- Foto da fachada e termo de concessão cadastrados e ativos, quando essas exigências se aplicarem

## Dependências

**Passadas**

- HU.066 — Anexar documentos
- HU.064 — Informar atividade econômica

**Futuras**

- HU.068 — Protocolar solicitação

---

## Interface de Usuário

### Tela 1 — Validar documentos obrigatórios

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Foto da fachada faltando**
**Dado que** o rascunho tem a foto da fachada como requisito ativo e sem anexo desse requisito
**Quando** o dono abre o preenchimento e tenta protocolar
**Então** o requisito aparece entre os faltantes e o protocolo é recusado com a lista de nomes, sem número

**Cenário 2: Requisitos cobertos**
**Dado que** a fachada e os demais obrigatórios têm anexo ligado ao requisito
**Quando** protocola
**Então** essa trava não dispara

**Cenário 3: Área pública sem termo**
**Dado que** o imóvel é área pública e o termo de concessão está ativo sem anexo
**Quando** protocola
**Então** o termo entra na lista que bloqueia

**Cenário 4: Requisito que não se aplica**
**Dado que** o requisito do CNAE está inativo, não é obrigatório ou não está ligado aos CNAEs do pedido
**Quando** o sistema resolve os obrigatórios
**Então** ele não entra na lista

**Cenário 5: Origem na Junta**
**Dado que** a solicitação tem origem na Junta Comercial
**Quando** protocola
**Então** a falta de documento não bloqueia este passo

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
