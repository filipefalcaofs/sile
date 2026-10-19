# HU.029 – GEOCODIFICAR ENDEREÇO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O servidor informa um endereço e recebe a coordenada correspondente, para posicionar o imóvel no mapa sem digitar latitude e longitude. Quem consulta o território é o administrador, o analista terceirizado e o coordenador. O cidadão não usa esta tela. A consulta prévia por endereço reutiliza o mesmo localizador e também recusa ponto fora de Salvador.

O resultado precisa ser em Salvador, na Bahia. Se o município não vier no retorno, o sistema só aceita o ponto se cair na área aproximada do município. Endereço já localizado com sucesso pode ser reaproveitado por um período; endereço não encontrado e falha do serviço não ficam guardados. Há um limite de pedidos por minuto. A consulta pede um único candidato. Com a localização por endereço desligada, nenhuma chamada externa sai e a orientação é informar o ponto no mapa. A tela devolve a coordenada; não há confirmação humana do ponto antes de gravar o imóvel nesta consulta.

---

## Sumário

- [Épico: EP04 — Georreferenciamento e Território](#épico)
- [História de Usuário: Geocodificar endereço](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP04 — Georreferenciamento e Território

**Como** a SEDUR **quero** converter o endereço em coordenada dentro de Salvador**, de modo que** o imóvel seja posicionado sem coordenada inventada

## História de Usuário: Geocodificar endereço

**Como** analista **quero** informar um endereço e receber a coordenada correspondente**, de modo que** eu posicione o imóvel no mapa sem digitar latitude e longitude

## Pré-requisitos

- Servidor que consulta o território: administrador, analista terceirizado ou coordenador
- Localização por endereço ligada
- Endereço obrigatório, com no mínimo 3 e no máximo 255 caracteres

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- HU-030 — o ponto obtido pode ser identificado no mapa

---

## Interface de Usuário

### Tela 1 — Consulta territorial

**Caminho no menu:** Território > Consulta territorial

*[Print da tela](screenshots/gestao-territorio.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Endereço em Salvador**
**Dado que** a localização por endereço está ligada e o serviço devolve um ponto em Salvador
**Quando** o servidor envia um endereço válido
**Então** o sistema devolve latitude, longitude, nome de exibição, confiança e endereço, e registra sucesso

**Cenário 2: Localização desligada**
**Dado que** a localização por endereço está desligada
**Quando** a geocodificação é solicitada
**Então** nenhuma chamada externa é feita, o sistema pede para informar o ponto no mapa e registra o bloqueio

**Cenário 3: Fora de Salvador, sem resultado ou serviço fora**
**Dado que** o serviço localiza um endereço fora de Salvador, não devolve resultado ou está indisponível
**Quando** a geocodificação é solicitada
**Então** o sistema recusa, informa o motivo correspondente e não inventa coordenada

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
