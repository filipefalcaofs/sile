# HU.171 – PROTOCOLAR SOLICITAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O requerente protocola o próprio rascunho e recebe um número único, no formato definido pela SEDUR: prefixo, ano e sequência. O padrão do prefixo é VIA e a sequência tem seis dígitos. A situação passa de rascunho para protocolada, com data de protocolo. A lista confirma o número. Em seguida o sistema avalia se cabe resposta automática.

Antes do número, o sistema recusa rascunho incompleto e documento obrigatório faltante, salvo origem na Junta Comercial, que dispensa o desenho do imóvel e a trava documental. Os dados mínimos do portal são empresa, desenho do imóvel, área maior que zero e CNAE principal. Simulação desfavorável não impede o protocolo. A ciência de que o local não é permitido só é registrada quando o cidadão confirma que deseja seguir mesmo assim. A simulação não é refeita neste passo.

Depois do número, o sistema tenta completar território pela inscrição, zona, bairro e endereço. Falha da SEFAZ ou do mapa não desfaz o protocolo. O comprovante em PDF é gerado na hora, para o dono, quando já existe número. Traz protocolo, empresa, endereço, área, CNAEs e um código de verificação. Cada emissão fica registrada. O PDF não fica guardado. Rascunho não tem comprovante. O sistema ainda não oferece pagamento ou DAM como condição do protocolo, nem reprocessar a viabilidade neste passo.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Protocolar solicitação](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** protocolar o rascunho**, de modo que** eu receba um número oficial e acompanhe o processo

## História de Usuário: Protocolar solicitação

**Como** requerente dono do rascunho **quero** protocolar quando os dados mínimos e os documentos obrigatórios estiverem completos**, de modo que** eu baixe o comprovante do protocolo

## Pré-requisitos

- Rascunho do próprio requerente
- Empresa, desenho do imóvel, área maior que zero e CNAE principal, no portal
- Documentos obrigatórios cobertos, salvo origem na Junta Comercial
- Abertura de solicitação no portal ligada

## Dependências

**Passadas**

- HU.164 — Criar solicitação de viabilidade
- HU.165, HU.166, HU.167 e HU.170

**Futuras**

- Resposta automática, avaliada depois do protocolo
- HU.175 — Consultar protocolo

---

## Interface de Usuário

### Tela 1 — Protocolar solicitação

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Protocolo gerado**
**Dado que** o rascunho do dono tem empresa, desenho do imóvel, área positiva, CNAE principal e documentos obrigatórios cobertos
**Quando** protocola
**Então** a situação passa a protocolada, nasce o número no formato definido e a lista confirma o número

**Cenário 2: Dados ou documentos faltando**
**Dado que** falta dado mínimo ou documento obrigatório, e a origem não é a Junta Comercial
**Quando** protocola
**Então** não há número novo e o erro é exibido

**Cenário 3: Ciência de local não permitido**
**Dado que** a simulação consolidada é não permitido e o requerente confirma que deseja seguir
**Quando** protocola
**Então** essa ciência fica registrada; sem a confirmação, o sinal não é gravado

**Cenário 4: Comprovante**
**Dado que** a solicitação já foi protocolada
**Quando** o dono pede o comprovante
**Então** recebe o PDF com o código de verificação; em rascunho, o comprovante não é emitido

**Cenário 5: Terceiro**
**Dado que** outra pessoa tenta protocolar ou baixar o comprovante
**Quando** aciona a ação
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
