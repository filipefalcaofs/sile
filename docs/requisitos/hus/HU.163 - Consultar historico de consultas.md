# HU.163 – CONSULTAR HISTÓRICO DE CONSULTAS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |
| 19/10/2026 | Retirada das HUs sem funcionalidade no sistema, renumeração e reordenação das demais (retaguarda primeiro, portal depois) | 2.2 | Sudoeste Informática |

---

## Visão Geral

Quem está logado no portal, com e-mail confirmado e termo de proteção de dados aceito, vê as próprias consultas prévias que deram resultado. O link Ver minhas consultas anteriores fica na página de consulta. Não há item próprio no menu lateral. O sistema ainda não oferece esse histórico no menu.

Cada item guarda o tipo de entrada (endereço, CNAE ou inscrição), o que foi informado, o veredito, a data e o resultado completo da época, inclusive as versões de regra. Reabrir o item mostra a mesma tela de resultado, sem recalcular. Veredito ausente não ganha nome.

Consulta sem login, consulta com erro de localização e consulta de outra pessoa não entram na lista. O histórico é do usuário da sessão, não de quem ele representa. A lista vai da mais recente para a mais antiga, em páginas. Não há busca nesta tela. Abrir a lista fica registrado.

---

## Sumário

- [Épico: EP07 — Consulta Prévia de Viabilidade](#épico)
- [História de Usuário: Consultar histórico de consultas](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP07 — Consulta Prévia de Viabilidade

**Como** o cidadão autenticado **quero** rever minhas consultas anteriores**, de modo que** eu reabra o resultado gravado na época, com a entrada e o veredito

## História de Usuário: Consultar histórico de consultas

**Como** cidadão autenticado **quero** ver minhas consultas bem-sucedidas**, de modo que** eu consulte de novo o que foi respondido, sem novo cálculo

## Pré-requisitos

- Sessão no portal, e-mail confirmado e termo de proteção de dados aceito
- Consulta prévia concluída com resultado, feita por esse usuário

## Dependências

**Passadas**

- HU.157, HU.158 ou HU.159, conforme a consulta que gerou o item

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Consultar histórico de consultas

**Caminho no menu:** Serviços > Consultar viabilidade > Ver minhas consultas anteriores

*[Print da tela](screenshots/portal-historico.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Consulta bem-sucedida de quem está logado**
**Dado que** o usuário está logado e a consulta devolveu resultado
**Quando** a consulta termina
**Então** surge um item desse usuário com o que foi informado, o resultado e as versões; consulta sem login ou com falha de localização não cria item

**Cenário 2: Reabrir o resultado**
**Dado que** existem itens do usuário
**Quando** ele abre o histórico
**Então** vê os seus, do mais recente ao mais antigo, e reabre o resultado gravado sem novo cálculo

**Cenário 3: Consulta de outra pessoa**
**Dado que** existem consultas de outra pessoa
**Quando** o usuário abre o histórico
**Então** elas não aparecem

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
