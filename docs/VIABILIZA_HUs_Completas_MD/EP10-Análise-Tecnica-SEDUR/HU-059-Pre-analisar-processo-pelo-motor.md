# HU-059 — Pré-analisar processo pelo motor

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Ao encaminhar o processo à análise, rodar o mesmo motor da solicitação e gravar a revisão 1 da ficha com o que o motor souber, sem pré-marcar a decisão do analista.

## História de Usuário
**Como** sistema, **quero** pré-analisar o processo encaminhado à análise, **para** que a ficha chegue preenchida com o veredito do motor e o analista confirme ou altere.

## Atores e permissões
- Não há rota própria. O listener `PreAnalisarProcesso` reage a `EncaminhadoParaAnalise`, disparado pelo encaminhamento do expresso (HU-069) e pelo envio manual (HU-085).
- Abrir a ficha também chama `preAnalisar` se ainda não houver revisão utilizável. Quem abre a ficha humana precisa de `analisar-processos`.

## Fluxo evidenciado
1. Se a revisão 1 já existe e não é rascunho vazio do motor, o método devolve a existente e não reexecuta.
2. Rascunho vazio é apagado e a revisão é recriada.
3. O motor é `SolicitacaoViabilityResolver`. A revisão 1 nasce em rascunho com `engine_snapshot`, versões de regra e `per_cnae`. `status_sugerido` fica gravado para a divergência. `status_escolhido` nasce nulo e o parecer nasce em branco. Condicionantes, vagas e justificativa por atividade seguem pré-preenchidas quando o motor as traz.
4. Exceção do motor grava revisão 1 em modo manual, com motor indisponível e ficha vazia. Veredito locacional pendente não esvazia a ficha: entra o que o motor souber, com status de análise, sem inventar deferimento.
5. A execução é auditada.
6. `atualizarRascunho` só regrava ficha intocada (rascunho, sem analista, sem parecer, única revisão). Ficha assumida ou preenchida não é tocada. Motor indisponível nessa atualização deixa a ficha como está.

## Regras de negócio evidenciadas
- A pré-análise não decide o processo e não marca a escolha do analista.
- Idempotência pela revisão 1: reabrir não reexecuta o motor. Recalcular é nova revisão (HU-074).
- Corrida na criação da revisão 1 devolve a linha já gravada.

## Validações
- Não há formulário. A falha do motor vira ficha manual, não erro silencioso de decisão.

## Critérios de aceite
1. Primeiro encaminhamento cria revisão 1 com sugestão do motor e escolha do analista vazia.
2. Segundo encaminhamento ou reabertura não duplica a revisão 1 nem reexecuta se a ficha já tem conteúdo.
3. Falha do motor deixa revisão manual com motor indisponível, sem resultado inventado.
4. Ficha já preenchida não é substituída na reavaliação.

## Divergências da redação anterior
- A redação anterior descrevia pré-análise como ação de usuário com permissão genérica. O código dispara no evento de encaminhamento e na abertura da ficha, e não decide.

## Evidências
- `app/Services/Analise/PreAnaliseService.php`
- `app/Listeners/PreAnalisarProcesso.php`
- `app/Services/Analise/AnalysisRecordService.php` (`current`)

## Não evidenciado
- Botão isolado "pré-analisar" com permissão própria.
- Exibição de `status_sugerido` como decisão já escolhida na ficha.
