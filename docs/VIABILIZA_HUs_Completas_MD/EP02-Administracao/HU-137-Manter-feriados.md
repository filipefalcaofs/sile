# HU-137 — Manter feriados

> **Status: Confirmada (2026-06-11)** — cadastro existente no SAPS legado (Configurações → Feriado). Impacta contagem de prazos e indeferimento automático.

## Épica
**EP02 — Administração**

## Objetivo
Administrar calendário de feriados municipais e nacionais usados na contagem de prazos operacionais.

## História de Usuário
**Como** administrador,  
**quero** manter feriados,  
**para** calcular prazos corretamente (BAP, convites, indeferimento automático).

## Regras de Negócio
- RN-001: Feriados administráveis sem deploy (HU-014).
- RN-002: Alterações versionadas e auditadas.
- RN-003: Integração com regras de contagem de prazo parametrizadas (úteis vs corridos; fins de semana).

## Dependências
- HU-014, HU-134, HU-083.

## Prioridade
Média
