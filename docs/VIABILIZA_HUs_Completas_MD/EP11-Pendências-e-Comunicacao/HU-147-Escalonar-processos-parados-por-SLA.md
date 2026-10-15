# HU-147 — Escalonar processos parados por SLA

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Generaliza o padrão do indeferimento por BAP (HU-134): toda etapa tem prazo, e prazo estourado tem tratamento definido.

## Épica
**EP11 — Pendências e Comunicação**

## Objetivo
Monitorar, por rotina agendada, processos parados além do prazo parametrizado em cada etapa (em análise, aguardando convite, aguardando integração) e aplicar o tratamento configurado: notificar gestor, redistribuir ou tratar conforme regra.

## História de Usuário
**Como** gestor SEDUR,  
**quero** ser alertado de processos parados além do prazo da etapa,  
**para** agir antes que o prazo total estoure — em vez de descobrir no relatório mensal.

## Contexto de Negócio
A distorção de prazos do legado (19 dias reportados) nasce de processos esquecidos em etapas intermediárias. Com a timeline por etapa (HU-129) e regras de prazo corretas (HU-137), o escalonamento fecha o ciclo: medir → alertar → agir.

## Fluxo Principal
1. Rotina agendada percorre processos ativos e calcula tempo na etapa atual (regras de prazo parametrizadas).
2. Para cada etapa com SLA configurado (HU-014): ao atingir limiar de alerta, notifica o analista responsável; ao estourar, notifica o gestor do setor.
3. Tratamentos configuráveis por etapa: somente notificar, sugerir redistribuição (HU-080), aplicar regra automática quando prevista (ex.: HU-134 para BAP).
4. Painel do gestor lista escalonamentos ativos (integra com a visão de fila, HU-144).
5. Toda notificação e ação registrada em auditoria.

## Regras de Negócio
- RN-001: SLAs por etapa e por tipo de serviço são parametrizáveis (HU-014); etapas sem SLA configurado não escalonam.
- RN-002: A contagem usa as mesmas regras de prazo da medição (HU-129/HU-137) — fonte única.
- RN-003: Ação automática só onde houver regra explícita (como HU-134); o padrão é notificação, nunca decisão silenciosa.
- RN-004: A rotina deve ser idempotente e não duplicar notificações (controle de último alerta por processo/etapa).
- RN-005: Convite sem resposta no prazo segue o tratamento parametrizado próprio (vínculo com HU-083/HU-091).

## Critérios de Aceite — BDD

### CA-01 — Alerta por etapa
**Dado** um processo parado além do limiar da etapa,  
**Quando** a rotina executar,  
**Então** o responsável (e o gestor, no estouro) deve ser notificado pelos canais habilitados, uma única vez por limiar.

### CA-02 — Fonte única de prazo
**Dado** um processo atravessando feriado cadastrado,  
**Quando** o tempo na etapa for calculado,  
**Então** o resultado deve coincidir com a medição da HU-129 para o mesmo processo.

### CA-03 — Auditoria
**Dado** qualquer escalonamento,  
**Quando** ocorrer notificação ou ação,  
**Então** processo, etapa, regra aplicada e destinatários devem constar da auditoria.

## Dependências
- HU-014 (parâmetros), HU-137 (feriados), HU-129 (medição por etapa), HU-144 (fila/painel), HU-134 (caso BAP), EP11 (canais).

## Prioridade
Média-alta

## Observações
Implementação natural via scheduler Laravel (padrão já validado no SIGVISA: rotinas horárias com `withoutOverlapping`).
