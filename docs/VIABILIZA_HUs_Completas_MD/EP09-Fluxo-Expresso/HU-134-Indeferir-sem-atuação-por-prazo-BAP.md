# HU-134 — Indeferir sem atuação por prazo BAP

> **Status: Confirmada (2026-06-11)** — sem BAP vinculado em **48h** (parametrizável), status "indeferido sem atuação". Causas: falha envio/recepção ou requerente não gerou BAP na Junta.

## Épica
**EP09 — Fluxo Expresso**

## Objetivo
Indeferir automaticamente processos que permanecerem aguardando protocolo BAP além do prazo administrável.

## História de Usuário
**Como** sistema,  
**quero** indeferir processos sem BAP no prazo,  
**para** evitar fila indefinida e refletir regra operacional do SAPS.

## Regras de Negócio
- RN-001: Prazo padrão 48h, parametrizável (HU-014); contagem respeita feriados/fins de semana conforme parametrização (HU-137).
- RN-002: Motivo registrado: "indeferido sem atuação".
- RN-003: Indeferimento comunicado ao Regin (HU-104); **não** envia dados à SEFAZ (HU-110).
- RN-004: Job agendado deve reprocessar casos falhos (bug observado: processos de janeiro sem BAP não indeferidos).

## Dependências
- HU-103, HU-133, HU-014, HU-137.

## Prioridade
Alta
