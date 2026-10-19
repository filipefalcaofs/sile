# HU.031 – IDENTIFICAR ZONA URBANÍSTICA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O sistema informa a zona urbanística do ponto para o Quadro 10 consultar a permissão, sem inventar zona. A camada local de zona nasce pendente de fonte e não cobre o município. Quando a consulta ao mapa oficial da SEDUR está ligada e alguma camada ativa contém o ponto, a zona volta identificada. Desligada, sem camada ativa ou sem resposta, a zona fica indisponível, com o motivo de base de zoneamento pendente, e nenhuma permissão é inventada.

Se a camada local existir e o ponto não cair numa feição, a mesma consulta ao mapa oficial é tentada. No processo, a zona só é gravada quando veio identificada e o nome não está vazio. Com polígono de endereçamento, a zona do processo é a da maior porção do lote. Porções iguais são empate: a resposta automática encaminha o processo à análise. Zona não identificada deixa o Quadro 10 indisponível e o resultado pendente. Não há regra urbanística de desempate além de registrar o empate e encaminhar à análise.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Identificar zona urbanística](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** obter a zona do imóvel da fonte disponível**, de modo que** o Quadro 10 consulte a permissão sem inventar zona

## História de Usuário: Identificar zona urbanística

**Como** analista **quero** saber a zona urbanística do ponto**, de modo que** a permissão por zona só seja consultada quando a zona existir

## Pré-requisitos

- Identificação avulsa: servidor que consulta o território
- No processo, a gravação da zona é do sistema, fora do preenchimento do cidadão
- Consulta ao mapa oficial da SEDUR ligada, com camada ativa, para a zona sair da base local pendente

## Dependências

**Passadas**

- HU-030 — a identificação do ponto

**Futuras**

- HU-039 — o Quadro 10 usa a zona identificada

---

## Interface de Usuário

### Tela 1 — Zona na consulta territorial

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Zona identificada no mapa oficial**
**Dado que** a consulta ao mapa oficial da SEDUR está ligada e alguma camada ativa contém o ponto
**Quando** o território é identificado
**Então** a zona volta identificada, com o nome da zona

**Cenário 2: Consulta desligada ou sem resposta**
**Dado que** a consulta ao mapa oficial está desligada, não há camada ativa ou o serviço não responde, e a camada local está pendente de fonte
**Quando** o ponto é identificado
**Então** a zona volta indisponível e nenhuma permissão de zona é inventada

**Cenário 3: Empate de zonas**
**Dado que** o polígono do endereçamento tem a mesma área em duas zonas
**Quando** a resposta automática avalia a maior porção
**Então** o processo vai à análise pelo empate de zona

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
