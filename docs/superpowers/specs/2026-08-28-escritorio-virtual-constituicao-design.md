# Escritório virtual — constituição de sede e abrigado — design

**Data:** 2026-08-28 · **Revisão:** 2 (respostas SEDUR 2026-08-31)
**Origem:** `docs/artefatos/Constituição - Virtual.pdf` (pacote normativo SEDUR 2026-08-28).
**Status:** RASCUNHO — `[OPEN-EV-7]` e `[OPEN-EV-10]` fechados pela SEDUR em 2026-08-31. Bloqueado agora por `[OPEN-EV-12]` (confirmação formal do escopo da consulta SEFAZ) e `[OPEN-EV-13]` (dado que o REGIN entrega).
**Relacionado:** `2026-07-16-escritorio-virtual-motor-design.md` (domínio), `2026-08-28-escritorio-virtual-alteracao-endereco-design.md`, `2026-08-28-escritorio-virtual-alteracao-atividade-design.md`

---

## 1. Problema

A constituição é o ponto onde a empresa entra no domínio de escritório virtual — como sede ou como abrigada. Hoje o Viabiliza tem o booleano `wants_virtual_office_hq` e a trava por inscrição, mas não tem a sequência de perguntas, as duas listas de atividade, a consulta SEFAZ nem os indeferimentos automáticos parametrizados que o requisito exige.

O documento da SEDUR fecha 13 critérios de aceite. Esta spec os espelha um a um.

## 2. Objetivos / Não-objetivos

**Objetivos**
- Identificar a modalidade pretendida a partir das duas perguntas (RN-EV-01 do motor).
- Aplicar os indeferimentos automáticos parametrizados, com mensagem e CNAE identificado.
- Encaminhar a sede à análise com a flag do §2º art. 6º do Decreto 35.062/2021.
- Enquadrar o abrigado só após a cadeia SEFAZ validar CNPJ → viabilidade → sede → inscrição.

**Não-objetivos**
- Modelo de dados e contratos de integração (motor).
- Alteração de endereço e de atividade (specs irmãs).
- Telas — a spec de telas cobre a UI; aqui está a regra.

## 3. Fluxo

```
Pergunta geral: "Deseja ser abrigado de escritório virtual?"
├── SIM  → RN-C-05 (fluxo abrigado)
└── NÃO  → contém CNAE 8211-3/00?
           ├── SIM → pergunta vinculada
           │         ├── SIM → RN-C-02 (fluxo sede)
           │         └── NÃO → desmembramentos comuns → RN-C-08
           └── NÃO → RN-C-08 (fluxo comum)
```

## 4. Regras de negócio

### RN-C-01 — Uma sede por inscrição imobiliária

Só uma sede de EV por inscrição. Existindo sede vinculada, nova sede é indeferida automaticamente com o parecer *"Já existe uma sede de escritório virtual vinculada a esta inscrição imobiliária."*

A existência de sede **não** impede o cadastro de abrigados.

### RN-C-02 — CNAE 8211-3/00 com intenção de abrigado

Pergunta geral respondida **Sim** e a solicitação contém 8211-3/00 → indeferimento automático, fluxo interrompido:

> "O CNAE 8211-3/00 não é permitido para exercício em escritório virtual e coworking, conforme as disposições do Anexo B do Decreto Municipal nº 35.062/2021."

### RN-C-03 — Validação das atividades da sede

Com intenção de sede, as atividades da solicitação são verificadas contra **{8211-3/00} ∪ Anexo A**. O 8211-3/00 é **excluído** da conferência contra o Anexo A: ele caracteriza a sede, não é atividade dela. Havendo qualquer outra atividade fora do Anexo A, indeferimento automático citando o Anexo A. Todas dentro → segue para RN-C-01.

`[OPEN-EV-7]` fechado (SEDUR 2026-08-31): a ausência do 8211-3/00 no Anexo A é deliberada. O Anexo A lista o que um estabelecimento **que já é sede** pode acumular — no próprio processo de constituição ou depois, por alteração de atividade. Ver RN-EV-05c do motor.

Sem essa exceção a regra indeferiria toda sede pelo próprio CNAE que a define. É o defeito que o `[OPEN-EV-7]` apontava, e a exceção é a correção.

### RN-C-04 — Zona e via da sede

Atividades validadas contra os Quadros 10 e 11A da LOUOS.

- Alguma não permitida → identificar o(s) CNAE(s), indeferir automaticamente com o texto parametrizado.
- Todas permitidas → **não defere automaticamente**: encaminha à análise da SEDUR com a flag *"Verificar se atende ao §2º do artigo 6º do Decreto Municipal nº 35.062, de 29 de dezembro de 2021."*

Deferida a análise, a inscrição fica vinculada à sede (trava — RN-EV-03 do motor). Indeferida, a inscrição fica livre para novas solicitações.

> **`[OPEN-EV-9]`.** O protocolo real `docs/artefatos/Processo - sede de virtual.pdf` saiu **deferido automaticamente**, sem passar por análise. Se esta regra vale, é mudança de comportamento frente ao legado — confirmar que é intencional.

### RN-C-05 — Atividades do abrigado

Todas as atividades verificadas contra o **Anexo B**. Fora da lista → indeferimento automático, fluxo interrompido, com a mensagem citando o Anexo B e **identificando o CNAE**:

> "O CNAE XXXX-X/XX (atividade não permitida) não é permitido para exercício em escritório virtual e coworking, conforme as disposições do Anexo B do Decreto Municipal nº 35.062/2021."

Com mais de um CNAE reprovado, todos são identificados. A regra de composição da mensagem para N CNAEs é parametrizada.

### RN-C-06 — Existência de sede para o abrigado (REVISTA)

Sem sede vinculada à inscrição → indeferimento automático:

> "Não existe uma sede de escritório virtual vinculada a esta inscrição imobiliária para que a empresa seja abrigada."

Com sede vinculada, o sistema **identifica a viabilidade da sede** e segue para a validação de zona e via.

**O que mudou na revisão 2.** A revisão 1 previa que o sistema pedisse o CNPJ da sede ao requerente e consultasse a SEFAZ, com sete tratamentos de retorno — é o que `Constituição` §7.2.1 e §8 descrevem. A SEDUR informou em 2026-08-31 que essa etapa **não acontece no nosso sistema**: a solicitação de abrigado é feita no REGIN, e é lá que o abrigado informa o CNPJ da sede. Nosso papel é só resolver a viabilidade da sede vinculada.

Saem do escopo: o campo de CNPJ, a consulta à SEFAZ por CNPJ, os sete tratamentos do §8 e o registro de consultas do §11.

> **`[OPEN-EV-12]`.** Isso contradiz o requisito escrito, que atribui as três coisas ao nosso sistema. Não implementar a remoção — nem a manutenção — antes de confirmação formal. Ver RN-EV-08 do motor.

> **`[OPEN-EV-13]`.** Qual dado o REGIN entrega: o CNPJ da sede ou a inscrição imobiliária? Hoje `AbrigadoResolver` resolve pela sede ativa na inscrição. Se o REGIN mandar o CNPJ, é preciso conferir os dois e definir o que fazer quando divergirem.

### RN-C-07 — Zona e via do abrigado

Confirmada a sede, as atividades são validadas contra os Quadros 10 e 11A. Todas permitidas → **defere**. Alguma não permitida → indefere com o texto parametrizado.

Diferença relevante frente à sede: o abrigado **defere automaticamente**; a sede vai à análise.

### RN-C-08 — Resposta "Não" à pergunta geral

- Existe sede vinculada à inscrição → indeferimento automático: *"Inscrição imobiliária vinculada a uma sede de escritório virtual. Para exercer atividades nesse local, deverá ser abrigado da sede vinculada."*
- Não existe sede → segue o fluxo comum de enquadramento e zoneamento, sem regra de EV.

### RN-C-09 — Rastreabilidade

Toda consulta SEFAZ é registrada conforme RN-EV-10 do motor.

## 5. Critérios de aceite

Espelham os do documento SEDUR (§13), na mesma numeração.

| CA | Enunciado | RN |
|---|---|---|
| 13.1 | Sem sede na inscrição e todas as atividades permitidas em sede, zona e via → encaminha à análise com a flag do §2º art. 6º | RN-C-04 |
| 13.2 | Com sede na inscrição, nova sede → indefere | RN-C-01 |
| 13.3 | Atividade não permitida em EV, pedido de abrigado → indefere automaticamente informando o CNAE | RN-C-05 |
| 13.4 | Pedido de abrigado sem sede na inscrição → indefere informando a ausência de sede | RN-C-06 |
| 13.5 | ~~Com sede na inscrição, CNPJ informado → consulta SEFAZ e valida CNPJ + inscrição~~ | **Fora de escopo** — REGIN (`[OPEN-EV-12]`) |
| 13.6 | Sede vinculada à inscrição identificada → enquadra como abrigada e valida zoneamento | RN-C-06, RN-C-07 |
| 13.7 | ~~CNPJ não localizado → impede enquadramento e permite corrigir~~ | **Fora de escopo** — REGIN (`[OPEN-EV-12]`) |
| 13.8 | ~~Inscrição da sede divergente → impede enquadramento e pede correção do CNPJ~~ | **Fora de escopo** — REGIN (`[OPEN-EV-12]`) |
| 13.9 | ~~API indisponível → não defere nem indefere; permite nova tentativa~~ | **Fora de escopo** — REGIN (`[OPEN-EV-12]`) |
| 13.10 | Sede confirmada, atividades permitidas em EV e na zona/via → defere | RN-C-07 |
| 13.11 | Sede confirmada, atividade não permitida na zona/via → indefere | RN-C-07 |
| 13.12 | Sede na inscrição e resposta "Não" → indefere orientando a se abrigar | RN-C-08 |
| 13.13 | Sem sede e resposta "Não" → permite o cadastro e analisa zoneamento normalmente | RN-C-08 |

## 6. Matriz de rastreabilidade

| CA SEDUR | RN desta spec | Onde testar |
|---|---|---|
| 13.1, 13.2 | RN-C-01, RN-C-04 | Feature de constituição de sede |
| 13.3 | RN-C-05 | Feature de constituição de abrigado |
| 13.4, 13.5, 13.6 | RN-C-06 | Feature de constituição de abrigado + gateway de consulta |
| 13.7, 13.8, 13.9 | RN-EV-08 (motor) | Testes do gateway de consulta SEFAZ |
| 13.10, 13.11 | RN-C-07 | Feature de zoneamento do abrigado |
| 13.12, 13.13 | RN-C-08 | Feature de fluxo comum em inscrição travada |

Casos derivados dos protocolos de teste em `docs/artefatos/`:

| Protocolo | Cenário | Resultado esperado |
|---|---|---|
| `Processo - sede de virtual.pdf` | 8211-3/00, pergunta vinculada "Sim", ZCMe-1/01, VA-I, inscrição 7140088 | Sede — mas o legado deferiu automaticamente; ver `[OPEN-EV-9]` |
| `Processo - abrigado da sede 2108519.pdf` | 8219-9/99 (consta no Anexo A **e** no Anexo B), mesma inscrição 7140088, TVL Sede 2108519 | Abrigado deferido após cadeia SEFAZ |
| `Processo 33072.pdf` | Inscrição imobiliária = 0 | Sem regra definida — `[OPEN-EV-11]` |

## 7. Questões abertas

Fechadas pela SEDUR em 2026-08-31: `[OPEN-EV-7]` (o 8211-3/00 caracteriza a sede e sai da conferência contra o Anexo A), `[OPEN-EV-9]` (sede vai sempre à análise quando o CNAE está presente e a resposta é "Sim"), `[OPEN-EV-10]` (a identificação da sede acontece no REGIN).

Continuam abertas, herdadas do motor: `[OPEN-EV-8]` (enunciado oficial das perguntas), `[OPEN-EV-11]` (inscrição imobiliária ausente), `[OPEN-EV-12]` (confirmação formal de que a consulta SEFAZ sai do escopo), `[OPEN-EV-13]` (dado que o REGIN entrega) e `[OPEN-EV-14]` (a vedação do 8211-3/00 na Alteração de Atividade se refere ao abrigado?).

`[OPEN-EV-12]` e `[OPEN-EV-13]` impedem a geração do plano do fluxo de abrigado. O fluxo de **sede** está desbloqueado.
