# HU.069 – CONSULTAR PROTOCOLO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O requerente acompanha a própria solicitação em Minhas solicitações. Vê número, situação, datas, tipo de serviço, empresa, área, endereço resumido, CNAEs e a linha do tempo das mudanças já ocorridas, com o motivo. A linha do tempo não é inventada. Em rascunho, a pendência é concluir e protocolar. Em pendência, há convite aguardando ação. Nas demais situações, não há pendência nesta tela.

Quando já existe número, a página oferece um link temporário de acompanhamento. A validade padrão é de 30 dias. Quem recebe o link, mesmo sem login, vê só o número, a situação pública, a data de protocolo, as etapas com nome e data, e o prazo estimado. Não vê empresa, documento, endereço, CNAE, motivo interno, quem atuou nem pendências. O prazo estimado usa o valor definido pela SEDUR, no padrão de 30 dias, com o aviso de que é estimativa e não vincula a SEDUR. Situação cancelada, deferida ou indeferida não traz prazo. Link expirado ou adulterado não abre. Rascunho sem número não gera link.

Toda consulta fica registrada. Quando há decisão, a página autenticada oferece o comprovante, o parecer e, se houver, uma explicação sugerida. Essa explicação não altera a decisão. O sistema ainda não oferece consulta pública digitando o número, sem o link, nem prazo calculado pelo histórico de processos parecidos.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Consultar protocolo](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** consultar o andamento do protocolo e compartilhar um link de acompanhamento**, de modo que** quem recebe o link veja só a situação pública

## História de Usuário: Consultar protocolo

**Como** requerente **quero** abrir a minha solicitação e gerar o link temporário**, de modo que** eu acompanhe a linha do tempo sem expor os dados completos

## Pré-requisitos

- Requerente efetivo da solicitação, para a consulta completa
- Número de protocolo, para o link público
- Link dentro do prazo de validade, para o visitante

## Dependências

**Passadas**

- HU.061 — Criar solicitação de viabilidade
- HU.068 — Protocolar solicitação, para haver número e comprovante

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Consultar protocolo

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-solicitacoes.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Consulta do requerente**
**Dado que** o requerente efetivo abre a solicitação
**Quando** a página é exibida
**Então** vê os dados do processo, a linha do tempo com motivo e a consulta fica registrada

**Cenário 2: Link de acompanhamento**
**Dado que** a solicitação tem número
**Quando** a página autenticada é montada
**Então** existe link temporário; em rascunho sem número, o link não é oferecido

**Cenário 3: Visitante com link válido**
**Dado que** o link está válido
**Quando** um visitante sem login abre o acompanhamento
**Então** vê número, situação pública, data de protocolo, etapas sem motivo e o prazo com a ressalva, e a consulta fica registrada

**Cenário 4: Link inválido ou outra pessoa**
**Dado que** o link expirou ou foi alterado, ou outro usuário autenticado abre a consulta
**Quando** tenta ver o protocolo
**Então** o sistema recusa

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
