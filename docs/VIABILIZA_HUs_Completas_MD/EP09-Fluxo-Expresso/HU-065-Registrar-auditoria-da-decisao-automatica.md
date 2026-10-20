# HU-065 — Registrar auditoria da decisão automática

## Épica
**EP09 — Fluxo Expresso**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Gravar, na mesma transação da decisão expressa, o resultado, o motivo, o por CNAE, a versão de regra representativa e, na decisão, o traço e a fundamentação usados depois na consulta.

## História de Usuário
**Como** sistema,  
**quero** auditar a decisão automática no momento em que ela é gravada,  
**para** que a conferência posterior não dependa do evento nem de um reprocessamento do motor.

## Atores e permissões
- A escrita é do `FluxoExpressoService` e, no prazo BAP, do `IndeferirSemBapService`.
- A leitura na retaguarda exige `consultar-solicitacoes` (HU-063). Cada abertura de lista ou detalhe também audita a consulta (`consulta-resultado-lista` e `consulta-resultado`).

## Fluxo evidenciado
1. No deferimento, no indeferimento e no encaminhamento à análise, `AuditService::log` grava log `expresso`, evento `decisao`, com id da solicitação, protocolo, resultado e o conteúdo da passagem.
2. Na emissão, o resultado da auditoria é `deferida` ou `indeferida`. As propriedades incluem consolidado, TVL (nulo no indeferimento) e resumo por CNAE (código, tendência e fluxo). `rulesVersion` é a primeira versão não vazia na ordem Quadro 10, risco de tratamento, Quadro 11A, risco municipal, risco sanitário, zona e bairro.
3. A linha `viability_decisions` guarda `per_cnae` com código TLL, fundamentação de cada CNAE, `rules_versions`, `fundamentacao` consolidada sem duplicar e `decision_trace` montado do array já resolvido, sem rodar o motor de novo.
4. No encaminhamento, o resultado da auditoria é `analise`, com motivo, setor, consolidado e por CNAE quando houve resolução. Não há `ViabilityDecision`. A queda estruturada fica em `expresso_quedas`, com gatilho e dimensão quando o motor os trouxe; sem isso, a linha não inventa gatilho.
5. Exclusão de atividade audita os CNAEs excluídos e o TVL, com `rulesVersion` nulo, porque não houve enquadramento.
6. Se o job esgota as tentativas, `decisao-falha` registra o id e a mensagem do erro, com resultado `falha`.
7. O detalhe da retaguarda projeta a explicação a partir do traço já gravado.

## Regras de negócio evidenciadas
- A auditoria da decisão é síncrona, dentro da transação. O evento posterior não é a fonte da trilha.
- A fundamentação exibida é a que os motores devolveram, reunida sem duplicar. Na exclusão sem enquadramento, o texto gravado é o da própria regra de exclusão.
- Consultar a lista ou o detalhe gera outra auditoria, de consulta, e não altera a decisão.
- Versão representativa ausente fica nula. O mapa completo permanece na decisão quando houve resolução.

## Validações
- Não há formulário. A trilha acompanha a transição válida da máquina de estados (`solicitacoes` / `transicao`), além do log `expresso`.

## Critérios de aceite
- Dado deferimento ou indeferimento emitido, quando a transação conclui, então existem a `ViabilityDecision` e o log `expresso`/`decisao` com protocolo, resultado e por CNAE, mesmo que um listener do evento falhe depois.
- Dado encaminhamento à análise, quando a transação conclui, então o log registra resultado `analise` e o motivo, e não existe decisão.
- Dado queda sem gatilho conhecido, quando grava `expresso_quedas`, então `tipo_gatilho` fica nulo em vez de um código inventado.
- Dado falha definitiva do job, quando `failed` roda, então o log é `decisao-falha` e não surge decisão.
- Dado usuário autorizado no detalhe, quando a explicação é montada, então ela lê o traço gravado e não recalcula o veredito.

## Divergências da redação anterior
A redação pedia um registro genérico de "todas as regras". O código grava a decisão, a versão representativa, o mapa de versões, a fundamentação devolvida pelo motor e o traço por CNAE. Não há trilha separada que reexecute a LOUOS na hora da consulta.

## Evidências
- `App\Services\Expresso\FluxoExpressoService` (chamadas `audit->log` em `emitir`, `encaminharAnalise`, `deferirExclusao`, `indeferirTerritorial`, `indeferirPerguntaSemResposta`)
- `App\Services\Auditoria\DecisionTraceBuilder`
- `App\Jobs\DecidirFluxoExpressoJob::failed`
- `App\Http\Controllers\Gestao\ResultadoExpressoController`
- `App\Services\Solicitacao\ViabilityRequestStateMachine::transition`

## Não evidenciado
- Auditoria da decisão automática feita só pelo listener, depois do commit, sem o log síncrono.
- Reexecução do motor ao abrir a tela de explicação.
- Registro de regras que o motor não devolveu.
