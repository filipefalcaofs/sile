# HU.015 – CONFIGURAR APIS DE INTEGRAÇÃO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

O administrador ajusta, por aba, os endereços, as credenciais e os interruptores das integrações que já estão no catálogo. As abas são REGIN, Inscrição imobiliária, CPF e CNPJ, Autenticação SEDUR, GOV.BR, Geocodificação, GeoServer e Cadastro municipal (SEMAP). Aba desconhecida na abertura cai em REGIN. Na gravação, aba desconhecida é recusada. Senha já gravada não volta preenchida: a tela só indica se já há valor. Senha em branco mantém a credencial atual. Valor igual ao atual não gera registro de alteração. Valor diferente é gravado e a alteração fica registrada; se o dado é sensível, o registro não mostra o conteúdo. Quando a aba tem endereço de produção e de homologação, o interruptor escolhe qual deles passa a valer, se estiver preenchido. O teste de conexão existe só no REGIN e na inscrição imobiliária, em ação separada da gravação. As outras abas não têm teste. Não é possível incluir uma integração fora do catálogo. Esta gravação vale na hora, sem a revisão de um segundo usuário exigida em outros cadastros sensíveis.

---

## Sumário

- [Épico: EP02 — Administração](#épico-ep02--administração)
- [História de Usuário: Configurar APIs de integração](#história-de-usuário-configurar-apis-de-integração)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
  - [Tela 1 — APIs](#tela-1--apis)
  - [Comportamento Esperado Tela 1](#comportamento-esperado-tela-1)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP02 — Administração

**Como** administrador da SEDUR **quero** ajustar cada integração já prevista**, de modo que** homologação e produção mudem sem expor a senha gravada.

## História de Usuário: Configurar APIs de integração

**Como** administrador autorizado a manter os parâmetros **quero** gravar endereço, credencial e interruptor de cada aba**, de modo que** a senha em branco preserve a credencial e o teste só exista onde a tela o oferece.

## Pré-requisitos

- Administrador autorizado a manter os parâmetros
- Integração já presente no catálogo da tela

## Dependências

**Passadas**

- Nenhuma

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — APIs

**Caminho no menu:** Configuração > APIs

*[Print da tela](screenshots/gestao-apis.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Senha em branco**
**Dado que** a integração já tem senha gravada
**Quando** o administrador salva a aba com o campo de senha vazio
**Então** a credencial armazenada não muda

**Cenário 2: Endereço alterado**
**Dado que** o endereço informado é diferente do atual e o dado não é sensível
**Quando** o administrador salva
**Então** o valor muda e o registro mostra o anterior e o novo

**Cenário 3: Aba inexistente**
**Dado que** o identificador da aba não está no catálogo
**Quando** o administrador envia a atualização
**Então** a aba é recusada e nada dessa integração é gravado

**Cenário 4: Teste só onde existe**
**Dado que** o administrador abre as abas
**Quando** a tela monta REGIN ou inscrição imobiliária
**Então** essas abas oferecem teste de conexão, e CPF e CNPJ, autenticação SEDUR, GOV.BR, geocodificação, GeoServer e SEMAP não oferecem teste

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
