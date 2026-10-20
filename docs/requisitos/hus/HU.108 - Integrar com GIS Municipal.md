# HU.108 – INTEGRAR COM GIS MUNICIPAL
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

A identificação do imóvel consulta o mapa oficial da SEDUR. A zona sai do ponto e, quando há polígono, da maior parte do imóvel. A classificação da via sai do logradouro. O endereço sai do logradouro e do número, ou do ponto. Se o mapa oficial não devolve o lote, a consulta pode usar o cadastro de lotes da SEMAP. As demais camadas consultadas são as cadastradas; camada sem cadastro não é consultada com um nome inventado.

Falha de rede ou feição ausente não recebe geometria substituta. Sem feição de zona, a zona não é preenchida. Sem retorno do mapa e sem o lote da SEMAP, não há endereço oficial fabricado. Nota interna de falha de camada não vai como justificativa à Junta.

A zona e o bairro oficial gravados no processo alimentam o painel por bairro. Na tela de indicadores, a série chamada “por zona” ainda agrupa por bairro e avisa que a zona não entrou naquela agregação. Quem mantém a configuração ajusta os endereços do mapa e das camadas. A consulta territorial da gestão é de quem consulta o território.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com GIS Municipal](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** analista **quero** que zona, via e camadas venham do mapa oficial**, de modo que** a restrição territorial não nasça de um polígono inventado

## História de Usuário: Integrar com GIS Municipal

**Como** analista **quero** ver a identificação geográfica do imóvel a partir do serviço de mapas**, de modo que** a ausência de feição fique visível em vez de um endereço fabricado

## Pré-requisitos

- Ponto do imóvel ou logradouro, conforme a consulta
- Camada cadastrada, quando a consulta for de uma camada específica

## Dependências

**Passadas**

- Localização do imóvel no processo ou na consulta territorial

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Consulta ao mapa oficial

**Caminho no menu:** Território > Consulta territorial. As camadas ficam em Território > Camadas do GeoServer.

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Zona encontrada**
**Dado que** o ponto do imóvel cai numa zona do mapa oficial
**Quando** o processo identifica o território
**Então** a zona gravada é a resposta do serviço, e a maior parte do imóvel prevalece quando há polígono

**Cenário 2: Sem feição**
**Dado que** o mapa não devolve zona para o ponto
**Quando** a identificação ocorre
**Então** a zona não é preenchida

**Cenário 3: Endereço e lote**
**Dado que** o mapa oficial não devolve o endereço e o lote da SEMAP também não
**Quando** o endereçamento é consultado
**Então** não há endereço oficial fabricado

**Cenário 4: Indicador por zona**
**Dado que** o gestor abre a série de solicitações por zona nos indicadores
**Quando** a agregação é exibida
**Então** o agrupamento é por bairro e a tela avisa que a zona urbanística não entrou nessa série

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
