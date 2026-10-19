# HU-185 — Auditoria preditiva

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Pontuar deferimentos automáticos do fluxo expresso e submeter anomalias à revisão humana, sem alterar o desfecho do processo.

## História de Usuário
**Como** gestor, **quero** ver anomalias nos deferimentos expressos, **para** confirmar ou descartar o sinal antes de qualquer providência, sabendo que a pontuação não decide o processo.

## Atores e permissões
- Comando `ia:auditoria-preditiva` (`PredictiveAuditService`).
- Tela `/gestao/auditoria-preditiva` com `gerenciar-alertas-abuso` (a mesma governança dos alertas de abuso).
- Com `features.ia_auditoria_preditiva` desligado, o ciclo não grava anomalia. A tela informa se o recurso está ligado.

## Fluxo evidenciado
1. A varredura lê deferimentos automáticos do fluxo `expresso` na janela `ia.auditoria_preditiva.janela_dias` (padrão 30).
2. A pontuação é determinística, sem provedor de IA: volume de deferimentos por CNPJ (peso padrão 40, limite reutilizado de `abuso.volume_cnpj.limite`), inscrição imobiliária repetida (peso 30) e requerente que prosseguiu apesar de alerta (peso 40).
3. Score acima de `ia.auditoria_preditiva.limiar_score` (padrão 70) cria `PredictiveAnomaly`. Severidade usa cortes `alta` 80 e `media` 60. Anomalia aberta do mesmo processo não duplica.
4. Severidade alta pode encaminhar à malha fina. O status do processo não muda.
5. A tela lista por score, filtra severidade, status e período, mostra efetividade e permite confirmar ou descartar com justificativa. Só a anomalia é atualizada. A consulta e a resolução são auditadas no log `ia`.

## Regras de negócio evidenciadas
- “Preditiva” aqui é antecipação por regra e peso para revisão. Não há modelo que defira ou indefira.
- Confirmar ou descartar não indefere, não cassa e não mexe na malha fina já criada.
- Sem anomalias, a taxa de efetividade é nula.
- A tela mostra se a anomalia já tem encaminhamento à malha fina (`fine_mesh_referral_id`).

## Validações
- Justificativa obrigatória, até 2000 caracteres.
- Data de filtro somente `YYYY-MM-DD`.
- Paginação 10, 20, 50 ou 100.

## Critérios de aceite
- Com o recurso desligado, o comando não cria anomalia.
- Deferimento expresso acima do limiar gera anomalia aberta; o processo continua deferido.
- Confirmar grava justificativa, responsável e data na anomalia, e não transiciona o processo.
- A ordenação da lista prioriza o maior score.

## Divergências da redação anterior
Não havia redação anterior neste pacote. Não é um modelo de aprendizado que decida viabilidade.

## Evidências
- `app/Services/Ia/PredictiveAuditService.php`
- `app/Console/Commands/AuditoriaPreditivaCommand.php`
- `app/Http/Controllers/Gestao/AuditoriaPreditivaController.php`
- `routes/gestao.php` (grupo `auditoria-preditiva`)

## Não evidenciado
- Chamada a provedor de IA nesta varredura.
- Punição automática do processo a partir do score.
