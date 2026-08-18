# HU-136 — Encaminhar para malha fina

> **Status: Confirmada (2026-06-11)** — malha fina é **provocação humana** (ação do analista), distinta da caixa de entrada. Qualquer processo, inclusive finalizado, pode ser encaminhado.

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Permitir encaminhamento manual de qualquer processo para revisão em malha fina, com motivo e rastreabilidade.

## História de Usuário
**Como** analista ou gestor SEDUR,  
**quero** encaminhar processo para malha fina,  
**para** revisão humana adicional ou auditoria interna.

## Regras de Negócio
- RN-001: Disponível para processos em qualquer status, inclusive deferidos/indeferidos (corrigir bug legado que recusava deferidos).
- RN-002: Motivo obrigatório; operação auditada.
- RN-003: Malha fina distinta de semi-expresso (gatilho automático) e de caixa de entrada (chegada automática).
- RN-004: Encaminhamento **em lote** suportado (seleção múltipla na consulta), com motivo único aplicado e auditoria por processo.

## Dependências
- HU-135, HU-082.

## Prioridade
Média
