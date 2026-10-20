# HU.174 – RENOVAR VIABILIDADE
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O cidadão com vínculo ativo com a empresa protocola a renovação de uma viabilidade locacional provisória já deferida neste portal, com número de viabilidade. Com o serviço de solicitação desligado, a jornada volta ao painel e informa que a solicitação e a renovação estão temporariamente indisponíveis. A lista mostra as solicitações deferidas, com número, das empresas do usuário. Em representação, lista, consulta e protocolo usam o representado. Empresa sem vínculo ativo não abre a renovação. Opcionalmente, o cidadão consulta o CNPJ e vê razão social, nome fantasia, telefone, endereço e as viabilidades deferidas com número desse CNPJ que pertençam às suas empresas. O assistente só abre se a origem for elegível. Os passos são confirmar a viabilidade (somente leitura), contato, declarações vigentes do tipo Renovação de viabilidade locacional e revisão. Endereço, área e atividades não são editáveis. Telefone é obrigatório. As declarações obrigatórias precisam estar marcadas. Ao protocolar, nasce solicitação ligada à origem, protocolada e em seguida em análise semi-expressa, sem deferimento automático. A mensagem informa que a renovação foi protocolada e encaminhada para análise. Viabilidade definitiva não pode ser renovada. Renovação da mesma origem já deferida dentro do intervalo configurado, com padrão de 3 meses e mínimo de 1, é recusada. Renovação ainda protocolada, em análise ou em pendência impede outra até a conclusão ou a desistência. Sem o tipo de serviço de renovação cadastrado, o protocolo é recusado. A viabilidade do sistema anterior continua naquele sistema e não entra nesta lista. Se houver setor de triagem ativo configurado, o processo segue para essa caixa; sem ele, segue sem caixa.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico-ep08--solicitação-de-viabilidade)
- [História de Usuário: Renovar viabilidade](#história-de-usuário-renovar-viabilidade)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Renovar viabilidade](#tela-1--renovar-viabilidade)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** cidadão **quero** prorrogar a viabilidade provisória já deferida**, de modo que** o processo vá à análise semi-expressa sem reeditar endereço e atividades.

## História de Usuário: Renovar viabilidade

**Como** cidadão com vínculo ativo com a empresa **quero** renovar uma viabilidade provisória deferida neste portal**, de modo que** a prorrogação seja protocolada e encaminhada à análise, sem deferimento automático.

## Pré-requisitos

- Cidadão autenticado, com e-mail verificado e termo aceito
- Serviço de solicitação e renovação ligado
- Viabilidade provisória deferida neste portal, com número, de empresa com vínculo ativo
- Fora do intervalo de bloqueio e sem outra renovação da mesma origem em aberto
- Declarações obrigatórias marcadas e telefone informado

## Dependências

**Passadas**

- A solicitação de origem já está deferida neste portal, com número de viabilidade

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Renovar viabilidade

**Caminho no menu:** Serviços > Renovar viabilidade

*[Print da tela](screenshots/portal-renovacao.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Protocolar provisória elegível**
**Dado que** a viabilidade provisória está deferida, com número, de empresa com vínculo ativo, fora do intervalo de bloqueio, sem renovação em aberto, e as declarações obrigatórias estão marcadas
**Quando** o cidadão protocola
**Então** nasce uma solicitação de renovação protocolada, em análise semi-expressa, ligada à origem, e o cidadão cai no detalhe do processo

**Cenário 2: Viabilidade definitiva, renovação recente ou ainda em análise**
**Dado que** a viabilidade é definitiva, foi renovada dentro do intervalo ou ainda há renovação em análise
**Quando** o cidadão tenta abrir ou protocolar
**Então** o sistema recusa com o motivo correspondente e não cria outra solicitação

**Cenário 3: Empresa de outra pessoa**
**Dado que** a empresa da solicitação não tem vínculo ativo com o usuário efetivo
**Quando** ele abre ou protocola a renovação
**Então** o acesso é negado

**Cenário 4: Serviço indisponível**
**Dado que** o serviço de solicitação e renovação está desligado
**Quando** o cidadão abre a jornada
**Então** volta ao painel com o aviso de indisponibilidade temporária

**Cenário 5: Declaração ou telefone ausente**
**Dado que** falta telefone ou alguma declaração obrigatória
**Quando** o cidadão tenta protocolar
**Então** a renovação não é protocolada e a mensagem pede o dado faltante

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
