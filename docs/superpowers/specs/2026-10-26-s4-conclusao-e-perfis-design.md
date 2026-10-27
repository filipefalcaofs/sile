# S4 — Regras de conclusão e perfis

Origem: Lisa 22/10 20:29 e 23/10 14:09–14:50.

## Regras informadas
- Status do processo fica Em análise até sair resultado; Para conclusão é aba, não status. (O código já trata assim: finalizar ficha não muda o status; só a decisão muda.)
- Chefia precisa conseguir fazer convite e mandar para a vistoria (coberto em S1).
- Analista terceirizado não finaliza processo: analisa e passa para quem conclui. (Já: `concluir-analise` não está no perfil Analista Terceirizado.)

## Item 8 — processo em Para conclusão voltando para análise
Hoje qualquer perfil com `analisar-processos`, inclusive o Terceirizado, devolve Para conclusão → Em análise pela API. A leitura adotada: só quem conclui (permissão `concluir-analise`) tira o processo de Para conclusão. Análise concluída não reabre (trabalho de 23/10).

## Mudança
`AnalysisStatus::proximasPara(false)` em Para conclusão devolve lista vazia; `atualizarStatusAnalise` recusa a devolução para quem não tem `concluir-analise`, mesmo com `force`.
