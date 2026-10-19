# HU.030 – LOCALIZAR IMÓVEL NO MAPA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

A consulta territorial abre o mapa centrado em Salvador e identifica bairro, via, zona, lote e restrições a partir de um ponto. Cada dimensão volta identificada, não encontrada ou indisponível, com a versão da camada. A identificação fica registrada mesmo quando uma dimensão está indisponível: a falta de base aparece no conteúdo, não como falha da chamada.

Camada ausente ou pendente de fonte não dispara consulta daquela dimensão e não inventa feição. A zona pode ser lida no mapa oficial da SEDUR quando essa consulta está ligada. A via mais próxima é buscada dentro do raio definido, cinquenta metros se outro valor não estiver definido. No rascunho do requerente, o polígono é a fonte: o centro dele alimenta a identificação, e zona, bairro oficial e número do logradouro só são gravados quando a dimensão veio identificada. Valor indisponível ou não encontrado não apaga o que já estava gravado. Sem ponto do imóvel, nada é gravado no processo. É possível identificar com a camada que vigorava numa data.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Localizar imóvel no mapa](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** identificar o território a partir de um ponto**, de modo que** bairro, via, zona, lote e restrições venham da camada vigente, sem feição inventada

## História de Usuário: Localizar imóvel no mapa

**Como** analista **quero** posicionar um ponto no mapa e ver o que o território devolve**, de modo que** eu confira o imóvel sem depender só do texto do endereço

## Pré-requisitos

- Servidor que consulta o território
- Latitude entre −90 e 90 e longitude entre −180 e 180
- O requerente, ao salvar o imóvel do próprio rascunho, dispara a mesma identificação pelo centro do polígono, sem precisar da consulta territorial do console

## Dependências

**Passadas**

- HU-036 — as camadas vigentes que a identificação lê

**Futuras**

- HU-031 a HU-035 — o detalhe de cada dimensão

---

## Interface de Usuário

### Tela 1 — Consulta territorial

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Ponto válido**
**Dado que** o servidor informa um ponto válido
**Quando** solicita a identificação
**Então** o sistema devolve as cinco dimensões com a situação e a versão da camada, e registra a consulta

**Cenário 2: Camada pendente ou ausente**
**Dado que** uma camada está pendente de fonte ou não existe
**Quando** o ponto é identificado
**Então** essa dimensão volta indisponível, com o motivo, sem feição inventada; a zona pode ser lida no mapa oficial da SEDUR quando essa consulta está ligada

**Cenário 3: Polígono do requerente**
**Dado que** o requerente grava o polígono do imóvel
**Quando** o centro é identificado
**Então** zona, bairro oficial e número do logradouro só são gravados se a dimensão veio identificada

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
