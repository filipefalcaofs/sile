# HU-136 — Encaminhar para malha fina

> **Status: Atualizada (2026-09-30)** — malha fina é **provocação humana** (ação do analista), distinta da caixa de entrada. Vale só para o fluxo expresso: encaminhar à malha fina significa que o processo precisa de análise humana. Processo que já está em análise humana não oferece a ação.

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Permitir encaminhar à malha fina um processo do fluxo expresso, para que passe por análise humana, com motivo e rastreabilidade.

## História de Usuário
**Como** analista ou gestor SEDUR,  
**quero** encaminhar um processo do fluxo expresso para malha fina,  
**para** submetê-lo à análise humana.

## Regras de Negócio
- RN-001: A ação existe apenas para processo do fluxo expresso. Malha fina é o encaminhamento à análise humana de um processo que o sistema decidiu sem analista. Inclui o deferido e o indeferido pelo expresso.
- RN-002: Motivo obrigatório; operação auditada.
- RN-003: Malha fina distinta de semi-expresso (gatilho automático) e de caixa de entrada (chegada automática).
- RN-004: Encaminhamento **em lote** suportado (seleção múltipla na consulta), somente entre processos do fluxo expresso, com motivo único aplicado e auditoria por processo.
- RN-005: Processo que já está em análise humana não exibe "Encaminhar para a malha fina". A análise humana já está em curso; a ação seria redundante.

## Dependências
- HU-135, HU-082.

## Prioridade
Média
