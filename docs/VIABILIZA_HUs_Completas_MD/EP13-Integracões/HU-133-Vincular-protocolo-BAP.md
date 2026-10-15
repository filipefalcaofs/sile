# HU-133 — Vincular protocolo BAP

> **Status: Confirmada (2026-06-11)** — após preenchimento do formulário SEDUR no Regin, o requerente gera o **protocolo BAP** na Junta. O SAPS vincula BAP ↔ processo em 10–20 min antes de processar.

## Épica
**EP13 — Integrações**

## Objetivo
Receber o protocolo BAP do Regin/Junta e vinculá-lo ao número de processo SEDUR, liberando o motor de decisão.

## História de Usuário
**Como** sistema,  
**quero** vincular o protocolo BAP ao processo,  
**para** iniciar análise automática ou encaminhamento humano após confirmação na Junta.

## Fluxo Principal
1. Processo criado aguarda status "espera BAP" após formulário SEDUR concluído.
2. Regin/Junta envia BAP via integração (HU-103).
3. Sistema valida correspondência (CNPJ, inscrição, token) e vincula BAP ↔ processo.
4. Sistema atualiza timeline (histórico) e dispara classificação/decisão.
5. Registro em auditoria com payload e timestamp.

## Regras de Negócio
- RN-001: Processo carrega três identificadores: número processo SEDUR, protocolo BAP e número produto TVL (quando deferido).
- RN-002: Vinculação duplicada ou BAP já usado deve ser rejeitada.
- RN-003: Falha na vinculação gera pendência operacional visível — nunca decisão silenciosa.

## Dependências
- HU-103, HU-134 (indeferimento se BAP não chegar no prazo).

## Prioridade
Alta
