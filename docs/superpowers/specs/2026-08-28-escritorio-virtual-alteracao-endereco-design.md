# Escritório virtual — alteração de endereço de sede e abrigado — design

**Data:** 2026-08-28 · **Revisão:** 1
**Origem:** `docs/artefatos/Alteração de Endereço - Virtual.pdf` (pacote normativo SEDUR 2026-08-28).
**Status:** RASCUNHO — bloqueado por `[OPEN-EV-7]` e `[OPEN-EV-10]` do motor.
**Relacionado:** `2026-07-16-escritorio-virtual-motor-design.md` (domínio), `2026-08-28-escritorio-virtual-constituicao-design.md`, `2026-07-14-desfecho-analise-produto-design.md`

---

## 1. Problema

A alteração de endereço é o fluxo que a SEDUR apontou como **bug do legado**: quando a sede sai da inscrição, os abrigados ficam pendurados num endereço que não existe mais. O requisito fecha o comportamento e acrescenta duas obrigações que hoje não temos: notificar os abrigados e comunicar a SEFAZ, ambas com registro e reprocessamento.

## 2. Objetivos / Não-objetivos

**Objetivos**
- Ramificar o fluxo pela finalidade declarada, distinta para sede e para abrigado.
- Reaproveitar integralmente os fluxos de constituição onde o requisito manda reaproveitar.
- Garantir os efeitos colaterais do deferimento: desvinculação, notificação, comunicação.

**Não-objetivos**
- Reescrever as validações de zona/via — são as mesmas da constituição.
- Definir o contrato SEFAZ (motor).

## 3. Finalidades

O sistema apresenta opções conforme o enquadramento atual da empresa.

**Sede**
1. A empresa deixará de prestar o serviço de sede.
2. A sede mudou de endereço.

**Abrigado**
1. A mudança de endereço será para outro escritório virtual.
2. A atividade deixará de ser abrigada em escritório virtual.

A opção selecionada determina o fluxo. Ela é registrada na trilha (RN-AE-07).

## 4. Regras de negócio

### RN-AE-01 — Sede deixa de prestar o serviço

1. Verifica se existe **outra** sede na inscrição. Existindo → indeferimento automático com o parecer *"Já existe uma sede de escritório virtual vinculada a esta inscrição imobiliária."*
2. Não existindo → apresenta os desmembramentos da resposta "Não" da pergunta vinculada ao CNAE, conforme a planilha de regras.
3. Selecionada a opção, valida zona e via pelos Quadros 10 e 11A. Permitidas → defere. Não permitidas → indefere com o motivo.

> A verificação do passo 1 é contraintuitiva: a empresa **é** a sede da inscrição, então "existe outra sede" só pode significar uma segunda sede além dela. Se a implementação comparar sem excluir a própria solicitação, toda saída de sede será indeferida. Registrar como armadilha no plano.

### RN-AE-02 — Efeitos do deferimento da saída da sede

Deferida a opção 1, executar, nesta ordem:

1. Identificar todos os abrigados ativos da sede.
2. Notificar cada um: *"A Sede de Escritório Virtual à qual sua empresa está vinculada deixou de prestar o serviço de Sede neste endereço. Para regularização do cadastro, deverá ser solicitada a alteração de endereço da empresa."*
3. Comunicar à SEFAZ: número da solicitação, CNPJ da sede, inscrição imobiliária, endereço anterior, data do deferimento e a informação de que deixou de prestar o serviço.
4. Registrar a comunicação (RN-EV-10 do motor).

Tudo pelo `DesvincularInscricaoService` compartilhado (RN-EV-06 do motor).

### RN-AE-03 — Sede mudou de endereço

Aplica **integralmente** o fluxo de constituição de sede sobre a nova inscrição: verificação de outra sede, validação de atividades, Quadros 10 e 11A, regra do 8211-3/00, indeferimentos parametrizados, encaminhamento à SEDUR com a flag do §2º art. 6º.

Deferida, executa os mesmos efeitos da RN-AE-02 sobre o endereço **anterior**.

### RN-AE-04 — Abrigados não são transferidos

A mudança de endereço da sede **não** transfere os abrigados para a nova inscrição. Cada abrigado é notificado e precisa solicitar sua própria Alteração de Endereço, para ser validado individualmente contra a nova sede.

Fecha `[OPEN-EV-1]`.

### RN-AE-05 — Abrigado muda para outro escritório virtual

Aplica o fluxo de constituição de abrigado sobre a nova inscrição: existência de sede, CNPJ da sede, consulta SEFAZ com os sete tratamentos, correspondência de inscrição, enquadramento, zona e via.

### RN-AE-06 — Abrigado deixa de ser abrigado

1. Verifica se existe sede de EV na inscrição informada. Existindo → indeferimento automático com o parecer de sede vinculada.
2. Não existindo → valida zona e via. Permitidas → enquadra como estabelecimento **não abrigado**, defere e atualiza o cadastro. Não permitidas → indefere e **não** efetiva a alteração cadastral.

> A assimetria da opção 2 do abrigado é intencional no requisito: sair da condição de abrigado só é possível num endereço **sem** sede. Se há sede, a empresa tem que ser abrigada dela (mesma lógica da RN-C-08 da constituição).

### RN-AE-07 — Comunicação à SEFAZ em toda alteração de abrigado

Toda alteração de endereço de abrigado deferida comunica à SEFAZ, **independentemente da opção** — tanto a mudança para outro EV quanto a saída da condição de abrigado.

**Falha na comunicação não desfaz o deferimento.** Registra a ocorrência, guarda o código de erro, disponibiliza para acompanhamento e permite reprocessamento (RN-EV-09 do motor).

### RN-AE-08 — Integridade dos vínculos

Após o deferimento:
- a empresa não permanece vinculada à sede anterior quando houve mudança de endereço;
- os vínculos dos abrigados com a sede anterior são identificados para notificação;
- não é criada uma segunda sede na mesma inscrição;
- o histórico é preservado (RN-EV-05b do motor).

### RN-AE-09 — Rastreabilidade

Registrar: opção selecionada, inscrição anterior, nova inscrição, resultado das validações, regra aplicada, resultado do zoneamento, resultado do processo, abrigados identificados, notificações enviadas, comunicação SEFAZ e data/hora de cada evento.

## 5. Critérios de aceite

| CA | Enunciado | RN |
|---|---|---|
| 11.1 | Sede, opção "deixará de prestar", sem outra sede → apresenta opções do CNAE e valida zona/via | RN-AE-01 |
| 11.2 | Outra sede existente → indefere com o parecer definido | RN-AE-01 |
| 11.3 | Encerramento deferido → identifica abrigados, notifica e comunica SEFAZ | RN-AE-02 |
| 11.4 | Opção "sede mudou de endereço" → executa o fluxo de constituição de sede | RN-AE-03 |
| 11.5 | Abrigado, opção "outro escritório virtual" → executa o fluxo de constituição de abrigado | RN-AE-05 |
| 11.6 | Abrigado deixa de ser abrigado, sem sede na inscrição → valida zona e via | RN-AE-06 |
| 11.7 | Sem sede e zoneamento permitido → defere e atualiza o enquadramento | RN-AE-06 |
| 11.8 | Sem sede e zoneamento não permitido → indefere | RN-AE-06 |
| 11.9 | Endereço da sede alterado → **não** transfere abrigados; notifica para solicitarem | RN-AE-04 |
| 11.10 | Saída da sede do endereço anterior deferida → registra e encaminha comunicação à SEFAZ | RN-AE-02 |
| 11.11 | Alteração de endereço de abrigado deferida → comunica SEFAZ em qualquer das duas opções | RN-AE-07 |
| 11.12 | Comunicação realizada → registra envio, resultado e retorno da integração | RN-AE-07 |

## 6. Matriz de rastreabilidade

| CA SEDUR | RN desta spec | Onde testar |
|---|---|---|
| 11.1, 11.2 | RN-AE-01 | Feature de encerramento de sede |
| 11.3, 11.10 | RN-AE-02 | `DesvincularInscricaoService` + notificações |
| 11.4 | RN-AE-03 | Reuso do fluxo de constituição de sede |
| 11.5 | RN-AE-05 | Reuso do fluxo de constituição de abrigado |
| 11.6, 11.7, 11.8 | RN-AE-06 | Feature de saída da condição de abrigado |
| 11.9 | RN-AE-04 | Teste de não-transferência de abrigados |
| 11.11, 11.12 | RN-AE-07 | Log de comunicação SEFAZ + reprocessamento |

## 7. Questões abertas

Herdadas do motor. Além delas, uma específica deste fluxo:

- `[OPEN-AE-1]` **ABERTO:** a RN-AE-01 manda verificar "outra sede" na inscrição de onde a sede está saindo. Confirmar se a intenção é excluir a própria solicitação da comparação (leitura provável) ou se existe cenário real de duas sedes coexistindo na mesma inscrição — o que contradiria a RN-C-01.
