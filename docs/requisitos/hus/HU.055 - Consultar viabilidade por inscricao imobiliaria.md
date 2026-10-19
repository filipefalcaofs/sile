# HU.055 – CONSULTAR VIABILIDADE POR INSCRIÇÃO IMOBILIÁRIA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O cidadão, com ou sem login, informa a inscrição imobiliária e o CNAE na mesma página de Consultar viabilidade, na aba Inscrição imobiliária. Quando o Cadastro Imobiliário devolve a coordenada do imóvel, o sistema usa esse ponto: identifica o território, enquadra pela LOUOS e classifica o risco, sem localizar o endereço de novo. Se não houver coordenada, mas houver logradouro e número, tenta localizar o endereço. Ponto em Salvador segue o mesmo caminho.

Sem ponto utilizável, a consulta não inventa coordenada nem apresenta veredito como se o imóvel tivesse sido localizado. O sistema avisa o motivo: cadastro indisponível, inscrição não encontrada ou imóvel sem coordenada do lote. O resultado fica sem análise territorial. Nome de proprietário e histórico do imóvel não são mostrados. Inscrição sem dígitos também não chama o cadastro.

Quem está logado guarda o histórico, inclusive nesse resultado sem território. A consulta fica registrada nos dois casos. O sistema ainda não oferece veredito do local quando o cadastro não devolve ponto, nem consulta por inscrição sem CNAE.

---

## Sumário

- [Épico: EP07 — Consulta Prévia de Viabilidade](#épico)
- [História de Usuário: Consultar viabilidade por inscrição imobiliária](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP07 — Consulta Prévia de Viabilidade

**Como** o cidadão **quero** consultar a viabilidade pela inscrição imobiliária**, de modo que** o veredito use o ponto do imóvel quando o cadastro localizar esse ponto

## História de Usuário: Consultar viabilidade por inscrição imobiliária

**Como** cidadão, com ou sem login, **quero** informar a inscrição imobiliária e o CNAE**, de modo que** eu veja o veredito do ponto ou o aviso de que o imóvel não foi localizado

## Pré-requisitos

- Inscrição imobiliária, texto de até 60 caracteres
- CNAE obrigatório, até 14 caracteres
- Área opcional, numérica, de 0 a 9999999
- Cadastro Imobiliário disponível, para obter a coordenada

## Dependências

**Passadas**

- HU.054 — Consultar viabilidade por endereço, no mesmo resultado de território, enquadramento e risco quando há ponto

**Futuras**

- HU.060 — Consultar histórico de consultas, para quem está logado

---

## Interface de Usuário

### Tela 1 — Consultar viabilidade por inscrição imobiliária

**Caminho no menu:** Serviços > Consultar viabilidade

*[Print da tela](screenshots/portal-viabilidade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Imóvel localizado**
**Dado que** o Cadastro Imobiliário devolve a coordenada, ou o endereço do imóvel é localizado em Salvador
**Quando** o cidadão consulta pela inscrição e pelo CNAE
**Então** o resultado inclui o território e o veredito daquele ponto

**Cenário 2: Sem ponto utilizável**
**Dado que** o cadastro está indisponível, a inscrição não existe ou o imóvel não tem coordenada utilizável
**Quando** o cidadão consulta
**Então** o sistema avisa o motivo, não inventa coordenada e não apresenta análise territorial como se o imóvel tivesse sido localizado

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
