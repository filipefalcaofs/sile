# HU-097 — Consultar regras aplicadas

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Mostrar, a partir do que foi gravado na decisão, os passos de regra que fundamentaram o desfecho.

## História de Usuário
**Como** analista ou auditor que abre o processo, **quero** ver as regras aplicadas na decisão, **para** entender a fundamentação sem o motor rodar de novo.

## Atores e permissões
- Quem consulta o processo na gestão e recebe a prop `explicacao`.
- Não há rota própria “regras aplicadas”. A projeção sai em `ProcessoController` quando existe `ViabilityDecision`.
- A mesma leitura do `decision_trace` é usada na ficha de resultado do expresso e no registro de análise.

## Fluxo evidenciado
1. Se o processo não tem decisão, a explicação não é montada.
2. `DecisionExplanationService::explain` lê `decision_trace`, `rules_versions` e fundamentação já gravados.
3. Com trace, a projeção segue a ordem registrada por CNAE: entrada, risco, enquadramento LOUOS, consolidação e desfecho.
4. Sem trace (decisão anterior a esse snapshot), a explicação é compacta, a partir de `per_cnae` e fundamentação, e cada passo não snapshotado é marcado como não registrado.
5. O serviço não instancia o motor nem recalcula zona, risco ou quadro.

## Regras de negócio evidenciadas
- A fonte é o registro da decisão. Reexecutar a regra vigente mudaria o passado e não ocorre.
- Decisão legada não recebe valores inventados para preencher passo ausente.
- Títulos e motivos dos passos não registrados vêm do catálogo `decision_texts`, com texto de fábrica se o parâmetro não existir.

## Validações
- Sem `ViabilityDecision`, não há explicação.
- Shape associativo legado de fundamentação não deve derrubar a leitura; o resource trata o payload gravado.

## Critérios de aceite
- Com `decision_trace` preenchido, a tela do processo mostra os passos na ordem gravada, com versão de regra do snapshot.
- Com trace vazio, a resposta marca `legado` e não fabrica quadro, artigo ou zona.
- A consulta não dispara nova execução do motor.

## Divergências da redação anterior
A redação pedia uma consulta genérica do auditor. O código projeta a decisão já gravada na ficha do processo, sem tela de auditoria separada e sem nova chamada ao motor.

## Evidências
- `app/Services/Auditoria/DecisionExplanationService.php`
- `app/Http/Resources/DecisionExplanationResource.php`
- `app/Http/Controllers/Gestao/ProcessoController.php` (prop `explicacao`)

## Não evidenciado
- Reprocessar a LOUOS na hora da consulta para “atualizar” a explicação.
- Lista global de regras aplicadas fora de um processo decidido.
