# HU.107 – INTEGRAR COM CADASTRO IMOBILIÁRIO
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

Quando a consulta da inscrição imobiliária está ligada, o sistema busca endereço e ponto do imóvel na base oficial, pelo serviço da SEDUR sobre o cadastro da SEFAZ. Quem abre o processo no portal, ou o recebimento vindo da Junta, dispara essa consulta. Não há tela pública só desta integração. Quem mantém a configuração pode testar a conexão.

A inscrição é usada só com os dígitos. Inscrição sem dígitos é tratada como não encontrada. Com a consulta desligada, o sistema não consulta o serviço e não inventa coordenada. Com a consulta ligada, imóvel inexistente não vira ponto padrão. Falha de rede, resposta sem sucesso ou corpo inválido deixam o serviço indisponível, sem endereço fabricado. Erro momentâneo do serviço ou falha de rede pode ser tentado de novo, dentro do tempo definido para essa consulta.

Quando o cadastro devolve o imóvel, o resultado traz inscrição, endereço e o ponto convertido para o mapa, sem nome do proprietário e sem histórico de propriedade. O endereço sai do logradouro, número e complemento devolvidos.

---

## Sumário

- [Épico: EP13 — Integrações](#épico)
- [História de Usuário: Integrar com Cadastro Imobiliário](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP13 — Integrações

**Como** cidadão **quero** que a inscrição imobiliária seja resolvida na base oficial**, de modo que** o endereço e o ponto do mapa venham do cadastro, e não de um ponto inventado

## História de Usuário: Integrar com Cadastro Imobiliário

**Como** cidadão **quero** ver o imóvel localizado quando a consulta está ligada**, de modo que** a falta do serviço apareça como indisponibilidade, sem coordenada fictícia

## Pré-requisitos

- Inscrição imobiliária informada
- Consulta da inscrição ligada, para haver chamada ao cadastro

## Dependências

**Passadas**

- Abertura de processo no portal ou recebimento da declaração da Junta

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Localização pela inscrição

**Caminho no menu:** Serviços > Minhas solicitações, ao informar o imóvel. O teste de conexão fica na configuração de quem mantém os dados do sistema.

*[Print da tela](screenshots/portal-viabilidade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Cadastro devolve o imóvel**
**Dado que** a consulta está ligada e o cadastro encontra a inscrição
**Quando** o processo é aberto
**Então** o resultado traz inscrição, endereço e ponto no mapa, sem o nome do proprietário

**Cenário 2: Consulta desligada**
**Dado que** a consulta da inscrição está desligada
**Quando** o processo precisa do imóvel
**Então** o sistema não consulta o serviço e não inventa coordenada

**Cenário 3: Imóvel inexistente**
**Dado que** a consulta está ligada e o cadastro não encontra a inscrição
**Quando** a busca ocorre
**Então** o imóvel não recebe um ponto padrão

**Cenário 4: Serviço indisponível**
**Dado que** a rede falha ou a resposta não pode ser lida
**Quando** a consulta é feita
**Então** o serviço fica indisponível e nenhum endereço é fabricado

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
