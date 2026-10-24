# Ficha de análise por analista — design

Data: 23/10/2026. Origem: HU.183 (`docs/requisitos/hus/HU.183 - Manter fichas de analise por analista e historico de tramitacao.md`).

## Problema

A ficha vigente é a revisão de maior número do processo, de quem for. O `autosave` troca o dono da ficha para quem salvou, e a nova revisão copia a última ficha de qualquer analista, mesmo sem a anterior estar finalizada. Em homologação (23/10): 70 revisões foram gravadas por mais de um usuário (68 já finalizadas), dois processos têm rascunhos duplicados do mesmo analista (1135 e 2504) e 18 processos em "Para conclusão" têm a cópia da chefia registrada em nome de quem analisou.

## Objetivo

A ficha pertence a quem a preencheu. Ninguém grava na ficha de outro. A ficha nova parte da última ficha do próprio analista; sem ficha própria, parte da base do motor. Nada é apagado. A decisão aponta para a ficha em que se baseou.

Fora do escopo: reabrir processo decidido, revisar decisão já emitida (comportamento atual mantido), devolução de "Para conclusão" (SPEC-07) e quem vê as fichas dos outros (todos que acessam o processo, como hoje).

## Regras

1. **Abrir a ficha só mostra.** `AnalysisRecordService::current($processo, $usuario)` devolve:
   1. a última revisão do usuário no processo;
   2. senão, a base disponível: o rascunho sem dono mais recente. A cópia de conclusão (rascunho sem dono com `based_on_record_id`) só serve a quem pode concluir a análise;
   3. senão, a última ficha do processo, que é de outro analista e abre só para leitura (`de_outro_analista`).

   Abrir não cria ficha, porque hoje a ficha também é a tela de consulta da análise de outra pessoa (`FichaConsultaPorTodosTest`). A ficha do usuário nasce no botão "Abrir minha ficha" (rota de nova revisão). Sem usuário (`current($processo)`), o comportamento antigo é mantido: a revisão de maior número. Serve a rotinas internas, PDFs e IA.
2. **Rascunho sem dono é base, não ficha.** O primeiro usuário que salva ou finaliza uma base passa a ser o dono. A base não tem conteúdo humano de ninguém, exceto a cópia de conclusão, que é oferecida só a quem conclui.
3. **Ninguém grava na ficha de outro.** `autosave` e `finalizar` em ficha com dono diferente do usuário lançam `FichaDeOutroAnalistaException` (403 na rota). O dono não muda mais ao salvar.
4. **Nova revisão só a partir da própria ficha.** Se o usuário tem rascunho aberto, `novaRevisao` devolve esse rascunho em vez de criar outro. Se a última ficha dele está finalizada, a nova revisão copia essa ficha (`based_on_record_id`). Sem ficha própria, devolve a base disponível para ele ou cria a base do motor.
5. **Base do motor.** Cópia da última revisão do processo com o conteúdo humano zerado:
   - por atividade, `status_escolhido` volta ao `status_sugerido` e `justificativa` fica vazia;
   - `parecer`, `address_confirmed` e `is_virtual_office_hq` ficam vazios.
   Ficam o snapshot do motor, as versões de regra, os motivos do sistema, o enquadramento por atividade (códigos LOUOS e TLL, condicionantes do motor), as condicionantes gerais e as vagas. Decisão minha: não dá para separar, nessas listas, o que veio do motor do que foi editado, e esses dados descrevem o imóvel e a atividade, não a opinião do analista. Quando a revisão 1 ainda está sem dono, ela é a base e não se cria outra.
6. **Conclusão.** `guardarParaConclusao` finaliza a ficha de quem encaminhou e, com `viabiliza.analise.ficha.conclusao_parte_da_ficha_do_analista = true` (padrão provisório, igual ao comportamento atual), cria a cópia de conclusão sem dono com `based_on_record_id` apontando para a ficha finalizada. Com `false`, não cria cópia: quem conclui recebe a base do motor. A SEDUR ainda vai decidir. A chave muda o comportamento sem migration.
7. **Decisão ligada à ficha.** `viability_decisions.analysis_record_id` recebe a ficha usada por `AnaliseTecnicaDecisionService::decide`. A conclusão usa a ficha que quem conclui vê (`current($processo, $ator)`). Pode ser a de outro analista, como hoje: nesse caso, a ficha é finalizada no nome de quem a escreveu (`finalizarParaConcluir`) e a decisão fica no nome de quem concluiu.
8. **Trava.** Índice único parcial: um rascunho por (processo, analista) quando há analista.
9. **Situação "substituída".** Novo valor de `AnalysisRecordStatus`, usado só na correção de dados antigos. Não aceita edição, e o conteúdo é mantido.

## Migration

Em ordem, numa só migration:

1. Colunas `analysis_records.based_on_record_id` (nullable, FK para a própria tabela, `nullOnDelete`) e `viability_decisions.analysis_record_id` (nullable, FK, `nullOnDelete`).
2. Rascunhos duplicados do mesmo analista: o de maior revisão fica aberto e os demais passam a `substituida`, com evento `ficha-substituida` na auditoria.
3. Cópias de conclusão intocadas: processo em `para_conclusao`, última revisão em rascunho com `created_at = updated_at`, revisão anterior finalizada pelo mesmo analista. O dono é retirado (fica sem dono) e `based_on_record_id` aponta para a revisão anterior. O conteúdo não muda.
4. Vínculo das decisões: a revisão do último evento `decisao` da auditoria do processo. Sem esse evento, a única ficha finalizada antes da decisão. Fora desses casos, fica vazio.
5. Índice único parcial `(viability_request_id, analyst_user_id) where status = 'rascunho' and analyst_user_id is not null`.

## Tela e tramitação

- A tramitação já mostra o autor de cada evento a partir da auditoria (`causer`). Nada a mudar nesse ponto.
- A ficha de outro analista abre sem edição, com aviso de quem é. O botão "Criar nova ficha" passa a "Abrir minha ficha" nesse caso.
- "Decidir processo (conforme ficha)" aparece só em ficha finalizada, como antes.

## Testes

Feature tests em `tests/Feature/Analise/FichaPorAnalistaTest.php`:
- dono não muda ao salvar;
- outro analista é recusado com 403;
- o segundo analista recebe a base do motor sem as escolhas do primeiro;
- a nova revisão copia a própria ficha;
- nova revisão com rascunho aberto não duplica;
- cópia de conclusão com a chave ligada e desligada;
- a decisão grava `analysis_record_id`;
- o terceirizado é recusado em todas as rotas de conclusão.

Teste da migration com os cenários de dados antigos.
