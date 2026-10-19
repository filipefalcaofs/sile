# HU.054 – CONSULTAR VIABILIDADE POR ENDEREÇO
**PROJETO: Viabiliza — Sistema de Licenciamento Eletrônico**

---

## Histórico de Revisão

| Data | Resumo das Alterações | Versão | Responsável |
|:---:|---|:---:|:---:|
| 16/10/2026 | Texto revisto para a linguagem de quem usa o sistema | 2.1 | Sudoeste Informática |

---

## Visão Geral

O cidadão, com ou sem login, consulta a viabilidade de uma atividade a partir de um endereço em Salvador. Informa o endereço, o CNAE e, se quiser, a área em metros quadrados. O sistema localiza o ponto, identifica o território, enquadra pela LOUOS e classifica o risco. O veredito do local é o da LOUOS, não um cálculo à parte.

Se a resposta trouxer perguntas da planilha do CNAE, a tela as mostra e o cidadão reenvia as respostas. Com ponto em Salvador e zona identificada, o resultado traz o mapa, o veredito, o risco e o território. Sem zona identificada, o veredito já vem pendente e a consulta avisa que a zona está pendente.

Endereço fora de Salvador, endereço não localizado ou localização indisponível não viram resultado de viabilidade e não geram histórico pessoal. Consultas repetidas em pouco tempo são limitadas. Quem está logado e obtém resultado guarda a consulta no próprio histórico. Quem consulta sem login não gera histórico pessoal. O sistema ainda não oferece consulta por endereço sem CNAE nem um interruptor que desligue esta página.

---

## Sumário

- [Épico: EP07 — Consulta Prévia de Viabilidade](#épico)
- [História de Usuário: Consultar viabilidade por endereço](#história-de-usuário)
- [Pré-requisitos](#pré-requisitos)
- [Dependências](#dependências)
- [Interface de Usuário](#interface-de-usuário)
- [Aprovação do Requisito](#aprovação-do-requisito)

---

## Épico: EP07 — Consulta Prévia de Viabilidade

**Como** o cidadão **quero** consultar a viabilidade de uma atividade num endereço de Salvador**, de modo que** eu veja o veredito do local, o risco e o território daquele ponto

## História de Usuário: Consultar viabilidade por endereço

**Como** cidadão, com ou sem login, **quero** informar endereço, CNAE e, se quiser, a área**, de modo que** eu saiba se a atividade pode funcionar naquele ponto

## Pré-requisitos

- Endereço com no mínimo 3 e no máximo 255 caracteres
- CNAE obrigatório, com até 14 caracteres; valem os dígitos e, com sete dígitos, o formato 0000-0/00 também aparece
- Área opcional, numérica, de 0 a 9999999; sem área, o enquadramento usa zero
- Consulta aberta ao público, pelo menu Consultar viabilidade e pelo card da página inicial

## Dependências

**Passadas**

- Localização do endereço, enquadramento da LOUOS e classificação de risco

**Futuras**

- HU.060 — Consultar histórico de consultas, só para consulta bem-sucedida de quem está logado

---

## Interface de Usuário

### Tela 1 — Consultar viabilidade por endereço

**Caminho no menu:** Serviços > Consultar viabilidade

*[Print da tela](screenshots/portal-viabilidade.png)*

#### Comportamento Esperado Tela 1

**Cenário 1: Endereço em Salvador**
**Dado que** o endereço é localizado em Salvador e o CNAE foi informado
**Quando** o cidadão consulta
**Então** vê o veredito da LOUOS, o risco e o território e, se estiver logado, a consulta entra no histórico

**Cenário 2: Endereço fora de Salvador**
**Dado que** o endereço está fora do município de Salvador
**Quando** o cidadão consulta
**Então** o sistema informa que a consulta abrange apenas Salvador, sem resultado simulado e sem histórico

**Cenário 3: Endereço não localizado**
**Dado que** o endereço não é localizado
**Quando** o cidadão consulta
**Então** o sistema pede para revisar o endereço ou consultar por CNAE, sem resultado de viabilidade

**Cenário 4: Localização indisponível**
**Dado que** o serviço de localização está indisponível
**Quando** o cidadão consulta
**Então** o sistema pede para tentar de novo em instantes, sem inventar um veredito

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
