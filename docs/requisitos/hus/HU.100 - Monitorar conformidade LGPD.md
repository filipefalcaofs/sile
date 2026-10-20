# HU.100 – MONITORAR CONFORMIDADE LGPD
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

Quem acompanha a proteção de dados vê três números, sem nome, CPF, CNPJ nem texto de atividade: aceite do termo vigente, retenção dos registros de acesso e volume de consultas a dado pessoal. Não há filtro nem formulário. Abrir a tela fica registrado como dado pessoal.

Com termo vigente, a tela mostra a versão, os aceites e quem ainda precisa aceitar de novo. Sem termo publicado, a tela declara a ausência e não inventa versão nem simula aceite. Sem usuários ou sem termo, o percentual de aceite fica em zero; não há percentual de conformidade inventado.

A retenção informa por quantos dias os registros de acesso são guardados e qual foi a última limpeza realmente ocorrida. Decisões de viabilidade não entram nessa limpeza. As consultas a dado pessoal aparecem só como volume, sem listar o conteúdo.

A seção de direitos do titular informa que eliminação e anonimização ainda dependem de definição do encarregado e não têm rito nesta tela. Não há pedido do titular, portabilidade nem relatório nominativo de quem acessou dado pessoal.

---

## Sumário

- [Épico: EP12 — Auditoria e Compliance](#épico)
- [História de Usuário: Monitorar conformidade LGPD](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP12 — Auditoria e Compliance

**Como** administrador da SEDUR **quero** acompanhar aceite, retenção e consultas a dado pessoal**, de modo que** a conformidade seja vista sem abrir o dado na tela

## História de Usuário: Monitorar conformidade LGPD

**Como** administrador da SEDUR **quero** ver os indicadores de proteção de dados**, de modo que** eu saiba a cobertura do termo e a retenção dos acessos

## Pré-requisitos

- Atribuição de monitorar a proteção de dados
- Termo de proteção de dados publicado, quando a versão e os aceites precisarem aparecer

## Dependências

**Passadas**

- Termo de proteção de dados e registros de acesso já existentes

**Futuras**

- Nenhuma

---

## Interface de Usuário

### Tela 1 — Conformidade da proteção de dados

**Caminho no menu:** Não há item de menu com esse nome. A tela fica no console para quem monitora a proteção de dados.

*[Print da tela](screenshots/gestao-auditoria.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Termo vigente**
**Dado que** existe termo de proteção de dados publicado
**Quando** o administrador abre a tela
**Então** vê a versão, os aceites, quem ainda precisa aceitar de novo, e a abertura fica registrada como dado pessoal

**Cenário 2: Sem termo publicado**
**Dado que** não há termo publicado
**Quando** o administrador abre a tela
**Então** a tela declara a ausência e não simula aceite nem inventa versão

**Cenário 3: Direitos do titular**
**Dado que** o administrador consulta a seção de direitos do titular
**Quando** a tela é exibida
**Então** ela informa que não há eliminação nem anonimização automatizada

**Cenário 4: O dado pessoal não aparece**
**Dado que** houve consultas a dado pessoal
**Quando** o administrador olha o painel
**Então** vê só o volume, sem nome, CPF, CNPJ ou texto da atividade

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
