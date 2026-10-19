# HU.006 – ACEITAR TERMO LGPD
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

Antes de usar o portal ou o console, a pessoa autenticada lê e aceita a versão vigente do termo de proteção de dados. O aceite guarda a versão, o endereço de rede, o navegador e a data. Aceite de uma versão anterior não vale para a versão nova. Aceitar de novo a mesma versão não duplica o registro.

Se não houver termo publicado, o bloqueio não se aplica e a pessoa segue. Não há registro de recusa, download em PDF nem assinatura digital nesta tela. A publicação de uma versão nova do texto fica em Configuração, em Termos legais, e não faz parte deste aceite. O aceite não consulta mapa, REDESIM nem a LOUOS.

---

## Sumário

- [Épico: EP01 — Identidade, Acesso e Segurança](#épico)
- [História de Usuário: Aceitar termo LGPD](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP01 — Identidade, Acesso e Segurança

**Como** a SEDUR **quero** registrar o aceite do termo vigente**, de modo que** o uso do portal e do console ocorra com o consentimento da versão publicada

## História de Usuário: Aceitar termo LGPD

**Como** usuário autenticado que ainda não aceitou o termo vigente **quero** ler e aceitar o texto publicado**, de modo que** eu siga no ambiente em que entrei

## Pré-requisitos

- Pessoa autenticada no portal, com e-mail confirmado, ou autenticada no console
- Existir termo de proteção de dados publicado, com data de publicação já alcançada
- A pessoa marca o aceite; sem a marcação, o envio não conclui

## Dependências

**Passadas**

- HU-002 — sessão aberta no portal ou no console
- HU-005 — e-mail confirmado, no portal

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Aceitar termo de proteção de dados

**Caminho no menu:** Tela apresentada automaticamente antes do uso do portal ou do console, fora do menu

*[Print da tela](screenshots/gestao-termo.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Portal bloqueado até o aceite**
**Dado que** o cidadão está autenticado e verificado e ainda não aceitou o termo vigente
**Quando** tenta usar o portal
**Então** o sistema apresenta o termo daquele ambiente e não segue sem o aceite

**Cenário 2: Aceite libera o console**
**Dado que** o servidor está autenticado no console e o termo vigente está pendente
**Quando** envia o aceite marcado
**Então** o sistema guarda a versão, o endereço de rede e a data, informa que o termo foi aceito com sucesso e segue para o Painel

**Cenário 3: Sem termo publicado**
**Dado que** não há termo de proteção de dados publicado
**Quando** a pessoa autenticada navega
**Então** o sistema não apresenta a tela de aceite e o uso segue

**Cenário 4: Aceite já registrado**
**Dado que** a pessoa já aceitou a versão vigente
**Quando** a tela do termo é aberta de novo
**Então** o formulário não reaparece: o portal segue ao destino pretendido ou ao Meu painel, e o console segue ao Painel

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
