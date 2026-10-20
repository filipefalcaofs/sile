# HU.025 – MANTER CAMADAS DO GEOSERVER
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador cadastra, ajusta e liga ou desliga as camadas do GeoServer que a identificação de zona consulta, sem apagar o histórico. A lista mostra todas as camadas, sem paginação, na ordem definida. Cada item mostra o espaço de trabalho, o nome do tipo, o nome completo, o rótulo, a ordem e se está ativa. Incluir grava esses dados. Sem informar a situação, a camada nasce ativa. Na edição mudam rótulo, ordem e, se vier, a situação. Espaço de trabalho e nome do tipo enviados na edição são descartados. O par é único. Trocar a camada é desativar a antiga e cadastrar outra. Desativar não exclui: a camada sai da consulta de identificação da zona. Reativar faz a camada voltar a ser consultada. Quem só consulta o território não edita este catálogo. Cadastrar a camada não identifica zona se a consulta ao GeoServer estiver desligada ou se o servidor não responder. Esta tela não testa a conexão. A classificação viária e as poligonais de restrição territorial não usam esta lista.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico-ep04--georreferenciamento-e-território)
- [História de Usuário: Manter camadas do GeoServer](#história-de-usuário-manter-camadas-do-geoserver)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Camadas do GeoServer](#tela-1--camadas-do-geoserver)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** administrador do território **quero** manter as camadas usadas na identificação da zona**, de modo que** uma zona nova da LOUOS entre na consulta assim que a camada existir no servidor.

## História de Usuário: Manter camadas do GeoServer

**Como** administrador autorizado a manter o território **quero** cadastrar, ajustar e ligar ou desligar uma camada**, de modo que** o espaço de trabalho e o nome do tipo permaneçam e a camada inativa deixe de ser consultada.

## Pré-requisitos

- Administrador autorizado a manter o território
- Espaço de trabalho em letras minúsculas, números e sublinhado
- Nome do tipo ainda não usado nesse espaço de trabalho
- Ordem de 0 a 65535

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- A identificação da zona só consulta as camadas ativas quando a consulta ao GeoServer está ligada

---

## Interface de Usuário

### Tela 1 — Camadas do GeoServer

**Caminho no menu:** Território > Camadas do GeoServer

*[Print da tela](screenshots/gestao-geoserver.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro**
**Dado que** o administrador informa espaço de trabalho, nome do tipo e ordem ainda não cadastrados
**Quando** grava a camada
**Então** o registro é criado e a mensagem confirma o cadastro

**Cenário 2: Ajuste de rótulo ou ordem**
**Dado que** a camada já existe
**Quando** o administrador altera o rótulo ou a ordem
**Então** o espaço de trabalho e o nome do tipo permanecem os gravados

**Cenário 3: Desativar**
**Dado que** a camada está ativa
**Quando** o administrador inverte a situação
**Então** o registro continua na lista, deixa de ser consultado na identificação da zona e a mensagem informa a desativação

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
