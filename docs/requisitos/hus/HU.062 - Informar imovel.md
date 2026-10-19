# HU.062 – INFORMAR IMÓVEL
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

No rascunho, o requerente demarca o imóvel no mapa, informa a área utilizada e o ponto de referência. Pode informar logradouro, número, complemento, bairro, CEP e inscrição imobiliária. O ponto de referência é obrigatório. Os demais textos do endereço são opcionais. O complemento é texto livre.

O desenho precisa ser um contorno fechado, com pelo menos quatro pontos e coordenadas numéricas. O sistema calcula o centro do contorno e identifica o território. Sem zona identificada, a resposta segue sem inventar zona e sem impedir o salvamento. O sistema ainda não oferece bloqueio do salvamento quando a zona não é identificada, nem preenchimento do imóvel a partir de mensagem da Junta neste passo.

Na mesma gravação ficam o endereço, os indicadores do local e o desenho. Os indicadores aceitos são escritório virtual, sede, abrigado, área pública e acesso independente. Se sede ou abrigado não vierem no envio, o valor já gravado permanece. A identificação territorial neste passo não recalcula o veredito. Mudar o imóvel invalida a simulação anterior. Só o requerente efetivo edita, e somente enquanto a solicitação está em rascunho.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Informar imóvel](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** demarcar o imóvel no rascunho**, de modo que** o processo tenha a localização usada na análise territorial

## História de Usuário: Informar imóvel

**Como** requerente dono do rascunho **quero** desenhar o imóvel e informar o ponto de referência**, de modo que** zona, bairro e endereço fiquem gravados no pedido

## Pré-requisitos

- Rascunho da própria solicitação
- Contorno fechado do imóvel, com pelo menos quatro pontos
- Ponto de referência, com até 255 caracteres
- Abertura de solicitação no portal ligada

## Dependências

**Passadas**

- HU.061 — Criar solicitação de viabilidade

**Futuras**

- HU.063 — Informar área utilizada, gravada no mesmo passo
- HU.068 — Protocolar solicitação, que exige o desenho do imóvel

---

## Interface de Usuário

### Tela 1 — Informar imóvel

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Imóvel gravado**
**Dado que** o dono do rascunho informa um contorno válido e o ponto de referência
**Quando** salva o imóvel
**Então** o desenho, o endereço e os indicadores ficam gravados e a simulação anterior deixa de valer

**Cenário 2: Desenho ou ponto de referência inválidos**
**Dado que** o desenho está ausente, não é um contorno fechado, tem menos de quatro pontos ou o ponto de referência está vazio
**Quando** envia o formulário
**Então** o sistema recusa e nada é gravado

**Cenário 3: Quem não pode editar**
**Dado que** o usuário não é o requerente efetivo, ou a solicitação não está em rascunho
**Quando** tenta salvar o imóvel
**Então** o sistema recusa a edição

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
