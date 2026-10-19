# HU.042 – APLICAR CONDICIONANTES URBANÍSTICAS
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O motor junta as condições urbanísticas do parecer e, quando elas incidem, muda o resultado de permitido para permitido com condições. Nenhuma delas, sozinha, torna o uso não permitido. A coleta só ocorre quando o parecer ainda não caiu em não permitido, pendente ou bloqueio.

Incidem, e rebaixam o resultado: zona com permitido com condições, texto de via que não seja só "sim" nem vedação ou análise da CNLU, vagas abaixo do exigido e restrição territorial. Não rebaixam: vagas conformes, vagas sem exigência definida para o grupo e textos da planilha, inclusive os de escritório virtual. Sem grupo de uso, a verificação de vagas não roda. Sem exigência definida, o item de vagas fica sem conformidade e não muda um resultado que seria permitido. Com exigência, cada item declarado precisa ser pelo menos o exigido; item omitido conta como zero. A tabela de vagas por grupo nasce vazia: o sistema não traz os índices da lei preenchidos. Não há tela exclusiva para aplicar condicionante.

---

## Sumário

- [Épico: EP05 — Motor de Regras da LOUOS](#épico)
- [História de Usuário: Aplicar condicionantes urbanísticas](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP05 — Motor de Regras da LOUOS

**Como** a SEDUR **quero** listar as condições urbanísticas do caso**, de modo que** o parecer registre o que precisa ser observado sem transformar condição em proibição

## História de Usuário: Aplicar condicionantes urbanísticas

**Como** analista **quero** ver as condições que acompanham um uso permitido**, de modo que** eu distinga o que rebaixa o parecer para permitido com condições do que só informa

## Pré-requisitos

- Parecer que ainda não é não permitido, pendente ou bloqueado
- Quadro 10 e Quadro 11A já resolvidos
- Exigência de vagas do grupo, quando a SEDUR a tiver definido; sem isso, a conformidade de vagas fica em aberto

## Dependências

**Passadas**

- HU-039 — permitido com condições da zona
- HU-041 — texto de condição da via
- HU-019 — textos numerados da planilha de tratamento
- HU-043 — restrição territorial

**Futuras**

- HU-044 — o resultado único usa a presença de condicionante incidente

---

## Interface de Usuário

### Tela 1 — Condicionantes do parecer

**Caminho no menu:** O resultado aparece em Consultar viabilidade e no processo. A manutenção fica em Regras > Motor de regras

*[Print da tela](screenshots/gestao-louos.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Zona condicionada**
**Dado que** o Quadro 10 devolve permitido com condições e o restante não veda o uso
**Quando** o parecer é consolidado
**Então** existe condicionante de zona e o resultado é permitido com condições

**Cenário 2: Vagas sem exigência definida**
**Dado que** não há exigência de vagas para o grupo
**Quando** as condicionantes são coletadas
**Então** o item de vagas fica sem conformidade e não muda um resultado que seria permitido

**Cenário 3: Vagas abaixo do exigido**
**Dado que** a exigência do grupo está definida e o declarado fica abaixo
**Quando** o parecer é consolidado
**Então** o resultado é permitido com condições e o motivo fala em imóvel não conforme quanto a vagas

**Cenário 4: Texto da planilha**
**Dado que** o ramo cita condicionantes da planilha ou texto de escritório virtual
**Quando** as condicionantes são coletadas
**Então** o texto entra no parecer e, sozinho, não muda permitido para permitido com condições nem para não permitido

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
