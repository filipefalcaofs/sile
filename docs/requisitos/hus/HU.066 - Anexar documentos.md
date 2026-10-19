# HU.066 – ANEXAR DOCUMENTOS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

No rascunho, o requerente anexa os documentos do pedido, baixa o que enviou e remove o que não deve ficar. O documento fica guardado de forma reservada: só quem tem acesso à solicitação baixa, pela própria tela, e o download fica registrado. Não há endereço público permanente.

O anexo pode ser avulso ou ligado a um requisito. Quando o requisito é informado, o novo documento substitui o anterior daquele requisito. Tipos aceitos, no padrão, são PDF, JPEG e PNG. O tamanho máximo, no padrão, é 10 MB. A SEDUR pode alterar esses limites. Documento de outra solicitação não é encontrado. Incluir e remover só valem em rascunho. O dono continua podendo baixar depois do protocolo.

O sistema ainda não oferece, neste passo, antivírus, leitura automática do conteúdo ou conferência de autenticidade, nem anexo pelo requerente depois do protocolo.

---

## Sumário

- [Épico: EP08 — Solicitação de Viabilidade](#épico)
- [História de Usuário: Anexar documentos](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP08 — Solicitação de Viabilidade

**Como** o requerente **quero** anexar os documentos do rascunho**, de modo que** eu cumpra os requisitos documentais antes do protocolo e consiga baixar o que enviei

## História de Usuário: Anexar documentos

**Como** requerente dono do rascunho **quero** incluir, baixar e remover documentos**, de modo que** cada requisito informado fique com um único anexo

## Pré-requisitos

- Rascunho da própria solicitação, para incluir ou remover
- Dono da solicitação, para baixar em qualquer situação
- Documento no tipo permitido e dentro do tamanho máximo
- Requisito existente, quando o anexo for ligado a um requisito

## Dependências

**Passadas**

- HU.061 — Criar solicitação de viabilidade

**Futuras**

- HU.067 — Validar documentos obrigatórios, no protocolo

---

## Interface de Usuário

### Tela 1 — Anexar documentos

**Caminho no menu:** Serviços > Minhas solicitações

*[Print da tela](screenshots/portal-nova.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Documento aceito**
**Dado que** o dono do rascunho envia um PDF dentro do limite
**Quando** anexa
**Então** o documento fica guardado, com a identificação do conteúdo, e o envio fica registrado

**Cenário 2: Segundo documento do mesmo requisito**
**Dado que** já existe anexo daquele requisito
**Quando** o dono envia outro para o mesmo requisito
**Então** o anterior é removido e permanece o novo

**Cenário 3: Download**
**Dado que** o dono baixa o documento
**Quando** a tela entrega o conteúdo
**Então** ele recebe o documento gravado; outra pessoa não baixa

**Cenário 4: Fora do rascunho**
**Dado que** a solicitação não está em rascunho
**Quando** tenta incluir ou remover
**Então** o sistema recusa; o download do dono continua permitido

**Cenário 5: Tipo ou tamanho fora da regra**
**Dado que** o tipo ou o tamanho não são aceitos
**Quando** envia
**Então** o sistema recusa e nada é gravado

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
