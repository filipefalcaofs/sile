# HU-072 — Confirmar pagamento do DAM

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O código não confirma pagamento de DAM, não baixa guia e não indefere processo por falta de pagamento.

## História de Usuário
**Como** sistema,  
**quero** confirmar o pagamento do DAM junto à SEFAZ,  
**para** atualizar a situação da guia e aplicar as consequências do pagamento ou do não pagamento.

Essa história não tem fluxo correspondente no código.

## Atores e permissões
Não há ator de baixa automática nem de baixa manual de DAM.

## Fluxo evidenciado
Não há fluxo de confirmação de pagamento.

O protocolo e o fluxo expresso seguem sem consultar pagamento. O deferimento gera número de TVL na decisão e dispara `ResultadoEmitido` sem exigir DAM pago. O envio da viabilidade à SEFAZ, no deferimento, está bloqueado pelo gateway indisponível e não lê retorno de pagamento.

## Regras de negócio evidenciadas
Nenhuma regra de baixa, idempotência de pagamento, liberação de TVL por quitação ou indeferimento por DAM vencido está implementada.

## Validações
Nenhuma validação de valor pago, data de pagamento ou código de barras de DAM.

## Critérios de aceite
Não há critério atendido. Não existe rotina agendada, comando ou endpoint que consulte pagamento de DAM e atualize o processo.

## Divergências da redação anterior
A redação descrevia scheduler horário, consulta `POST /DAM/ConsultarDamsSEMOP` em lotes, baixa automática ou manual, liberação de TVL após a quitação e indeferimento do primeiro DAM vencido. Nada disso aparece no código da solicitação nem no do fluxo expresso.

## Evidências
- Ausência de modelo, job, comando ou controller de pagamento de DAM.
- `App\Services\Expresso\FluxoExpressoService::emitir` (decisão sem etapa de pagamento).
- `App\Listeners\EnviarViabilidadeSefaz` (envio de viabilidade bloqueado; não consulta pagamento).
- `routes/console.php` (não agenda baixa de DAM).

## Não evidenciado
- Confirmação de pagamento na SEFAZ.
- Baixa manual pelo operador.
- Cancelamento de DAM vencido e indeferimento por não pagamento.
- Liberação de documento condicionada à quitação da guia.
