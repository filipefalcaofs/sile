# HU-118 — Sugerir parecer

## Épica
**EP14 — Inteligência Artificial**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Redigir uma minuta de parecer a partir da pré-análise já gravada pelo motor, para o analista revisar. A minuta não é a decisão.

## História de Usuário
**Como** analista, **quero** uma minuta de parecer fundamentada no que o motor já registrou, **para** editar antes de concluir. Sem essa pré-análise, a minuta não é inventada.

## Atores e permissões
- Ficha de análise. A prop adiada chama `SugestaoParecerService`. O endpoint `sugerirParecer` repete a chamada sob demanda.
- Portão `features.ia_parecer` e provedor de texto.
- Exige ficha com `engine_available` e `engine_snapshot` não vazio.

## Fluxo evidenciado
1. Sem interruptor, sem provedor ou sem snapshot do motor: não despacha, não chama o modelo e não redige quadro ou artigo.
2. Com as três condições, o job grava `ai_suggestions` do tipo `parecer`.
3. O job não escreve `AnalysisRecord` nem `ViabilityDecision`.
4. A ficha informa se o parecer está disponível e o motivo quando não está.
5. Há ainda sugestão de justificativa da atividade (`features.ia_justificativa`), só depois que o analista escolheu deferida ou indeferida, e só preenche campo vazio. Também não grava decisão. Não é esta HU; convive na mesma ficha.

## Regras de negócio evidenciadas
- A minuta é revisável. Quem decide é o analista no fluxo de conclusão.
- Sem motor não há fundamentação para citar: a função não desbloqueia a tela com texto genérico.

## Validações
- Snapshot ausente impede a sugestão.
- O portão é conferido de novo no job.

## Critérios de aceite
- Com pré-análise gravada e função ligada, surge minuta em `ai_suggestions` e o processo continua sem decisão nova.
- Sem snapshot, a resposta explica a indisponibilidade e não cria parecer.
- Concluir a análise continua sendo ação humana distinta.

## Divergências da redação anterior
O texto já dizia minuta não vinculante. O código acrescenta a condição de só citar o snapshot do motor e o silêncio quando esse snapshot não existe.

## Evidências
- `app/Services/Ai/SugestaoParecerService.php`
- `app/Jobs/Ai/SugestaoParecerJob.php`
- `app/Ai/Agents/SugestaoParecerAgent.php`
- `app/Http/Controllers/Gestao/AnalysisRecordController.php` (`sugerirParecer`, `parecerTemMotor`)

## Não evidenciado
- Parecer que conclua a ficha ou gere `ViabilityDecision`.
- Minuta quando o motor não deixou snapshot.
