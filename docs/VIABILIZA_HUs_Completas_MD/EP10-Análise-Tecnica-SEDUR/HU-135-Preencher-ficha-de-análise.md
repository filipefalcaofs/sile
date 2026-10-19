# HU-135 — Preencher ficha de análise

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Abrir a revisão vigente da ficha, salvar o rascunho, finalizá-la de forma imutável e abrir nova revisão quando for preciso reeditar.

## História de Usuário
**Como** analista com `analisar-processos`, **quero** preencher a ficha do processo, **para** registrar a escolha por atividade, condicionantes, vagas e parecer antes da conclusão.

## Atores e permissões
- Ficha humana (abrir, autosave, finalizar, nova revisão, diff, sugestões de IA): `analisar-processos`.
- Ficha de processo já decidido pelo expresso: `consultar-solicitacoes` ou `analisar-processos`, somente leitura, sem criar revisão humana.
- Concluir o processo pela ficha: também `concluir-analise` (HU-086 e HU-087). Preencher não exige concluir.

## Fluxo evidenciado
1. Abrir a ficha chama a revisão vigente. Se não houver revisão utilizável, a pré-análise tenta criar a revisão 1. A abertura audita `ficha-consulta` e a consulta ao cadastro imobiliário.
2. A tela recebe localização, dados de TVL, recepção, tramitação, opções de enquadramento e de conclusão, setores ativos, escritório virtual, textos padrão e o indicador de ficha de vistoria já existente. Abrir a ficha de análise não cria vistoria.
3. Autosave atualiza só o que veio: escolha por CNAE (preservando `status_sugerido` do motor), condicionantes, vagas, parecer, sede de escritório virtual e confirmação de endereço. O motivo de análise enviado pelo cliente é ignorado.
4. Finalizar marca a revisão como finalizada, grava data e analista, materializa divergências entre o sugerido pelo motor e o escolhido, e audita. Finalizar de novo é recusado. Finalizar não decide o processo.
5. Nova revisão copia a última como rascunho. A finalizada permanece.
6. O diff compara duas revisões. Sugestão de parecer, de justificativa e resumo de IA são apoio de leitura; não gravam a decisão.

## Regras de negócio evidenciadas
- Revisão finalizada não recebe autosave.
- A sugestão do motor fica gravada para a divergência e não pré-marca `status_escolhido`: na pré-análise esse campo nasce nulo.
- Decisão humana só lê ficha finalizada.

## Validações
- Autosave em ficha finalizada: erro de imutabilidade, respondido como validação.
- Campos do rascunho seguem `AnalysisRecordRequest` (parecer opcional; listas quando enviadas).

## Critérios de aceite
1. Sem `analisar-processos`, a ficha humana responde 403. A ficha do expresso abre para quem consulta a solicitação.
2. Rascunho aceita gravação parcial e guarda a sugestão do motor.
3. Finalizar torna a revisão imutável e registra divergência quando a escolha difere do sugerido.
4. Nova revisão não altera a finalizada.
5. Preencher a ficha não muda o status canônico do processo.

## Divergências da redação anterior
- A redação anterior dizia que a ficha serve para deferir, indeferir ou manter atividades em análise como se preencher já decidisse. Decidir é outro endpoint e exige `concluir-analise`.
- Não há manutenção de atividade "em análise" como terceiro resultado da decisão da ficha: os resultados lidos na conclusão são deferido e indeferido.

## Evidências
- `app/Http/Controllers/Gestao/AnalysisRecordController.php`
- `app/Services/Analise/AnalysisRecordService.php`
- `app/Http/Requests/Gestao/AnalysisRecordRequest.php`
- `routes/gestao.php`

## Não evidenciado
- Edição da ficha finalizada no lugar. A reedição é revisão nova.
- Preenchimento da ficha pelo perfil apoio.
