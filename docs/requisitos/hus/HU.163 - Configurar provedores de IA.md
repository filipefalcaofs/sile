# HU.163 – CONFIGURAR PROVEDORES DE IA
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O administrador mantém as configurações de inteligência artificial, com chave protegida, teste de conexão real e uma configuração padrão. A lista ordena a padrão primeiro. Incluir e editar gravam nome, provedor, capacidade, endereço, chave, modelo, temperatura, tamanho máximo da resposta, tempo limite, situação e a marca de padrão. Os provedores aceitos são OpenAI, Anthropic, Gemini, Azure e compatível. As capacidades aceitas são texto, visão e embeddings. Configuração ativa no cadastro exige chave. Endereço recusado, por não ser seguro ou por estar fora da lista permitida, não é gravado. Na edição, chave vazia não substitui a atual. A chave não volta em claro e não entra no registro. Ativar uma configuração sem chave é recusado, com o pedido para informar a chave. Testar chama o provedor de verdade. Sucesso e falha ficam registrados, sem a chave. Excluir remove o registro. Os recursos de inteligência artificial usam a configuração padrão ativa.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Configurar provedores de IA](#história-de-usuário-configurar-provedores-de-ia)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — Configuração de IA](#tela-1--configuração-de-ia)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** manter o provedor de inteligência artificial padrão**, de modo que** os recursos usem a configuração ativa e a chave não apareça em claro.

## História de Usuário: Configurar provedores de IA

**Como** administrador autorizado a configurar a inteligência artificial **quero** cadastrar o provedor, testar o endereço e ativá-lo**, de modo que** a ativação sem chave seja recusada e o teste registre sucesso ou falha.

## Pré-requisitos

- Administrador autorizado a configurar a inteligência artificial
- Provedor e capacidade dentre os aceitos na tela
- Endereço aceito pela verificação de segurança
- Chave informada, quando a configuração nasce ativa

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Configuração de IA

**Caminho no menu:** Configuração > Configuração de IA

*[Print da tela](screenshots/gestao-ia.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Ativa sem chave**
**Dado que** o cadastro pede situação ativa e a chave está vazia
**Quando** o administrador salva
**Então** a mensagem pede a chave para ativar a configuração e nada é gravado

**Cenário 2: Endereço recusado**
**Dado que** o endereço não é aceito pela verificação de segurança
**Quando** o administrador salva
**Então** a configuração não é gravada

**Cenário 3: Teste registrado**
**Dado que** a configuração está gravada
**Quando** o administrador testa a conexão
**Então** o provedor é chamado e o registro mostra sucesso ou falha, sem a chave

**Cenário 4: Chave preservada**
**Dado que** a configuração já tem chave
**Quando** a edição envia a chave vazia
**Então** a chave armazenada permanece

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
