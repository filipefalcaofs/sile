# HU-089 — Encerrar processo

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Encerrar o processo pela conclusão da análise: deferido e indeferido saem de `em_analise`; desativado, cassado e revogado encerram a análise em curso ou substituem um deferimento já emitido.

## História de Usuário
**Como** usuário com `analisar-processos` e `concluir-analise`, **quero** concluir a análise com um resultado de encerramento, **para** tirar o processo da fila e fechar o eixo operacional.

## Atores e permissões
- Conclusão na ficha, decidir e conclusão de análise: `analisar-processos` e `concluir-analise`.
- Conclusão pela caixa de malha fina: `analisar-malha-fina` e `concluir-analise`, e só baixa encaminhamento que ainda está aberto.
- No seeder, Analista Terceirizado não conclui. Administrador tem `analisar-malha-fina` e não tem `concluir-analise`, então não conclui a malha fina por essa rota.

## Fluxo evidenciado
1. Deferido e indeferido, com processo em `em_analise`, passam por `AnaliseTecnicaDecisionService` e o status canônico vai para `deferida` ou `indeferida`. Isso é o encerramento desses dois resultados.
2. Desativado, cassado e revogado partem de `em_analise` ou de `deferida`. Fora desses status, a conclusão é recusada. Não geram número de TVL.
3. Se ainda não há decisão e o processo está em análise, esses três resultados criam `ViabilityDecision` com `flow` `analise_tecnica`, `per_cnae` vazio e `reason` com o parecer informado. O parecer pode ser nulo nesse caminho: o model só obriga `reason` quando o outcome é indeferida.
4. Processo que já era deferido e recebe desativado, cassado ou revogado muda de status e não reenvia parecer ao REGIN. Encerrar a partir de `em_analise` criando decisão nova chama o listener do REGIN na hora.
5. O eixo operacional vai para `analise_concluida`, com `force` se a transição normal não for permitida.
6. Resultado que não é deferido desvincula sede ativa de escritório virtual, se houver trava.

## Regras de negócio evidenciadas
- Não existe encerramento sem um resultado da conclusão. Deferido e indeferido também encerram.
- Processo deferido não volta a indeferido por esta conclusão.
- Indeferida e deferida saem da fila do analista: a fila só contém `em_analise` e `em_pendencia`.

## Validações
- `ConcluirAnaliseRequest`: resultado obrigatório entre os outcomes de conclusão; parecer opcional, até 5000 caracteres.
- Transição de status não permitida pela máquina canônica é recusada com a situação atual na mensagem.

## Critérios de aceite
1. Sem `concluir-analise`, encerrar pela ficha, pelo decidir ou pela malha fina responde 403.
2. Deferir ou indeferir em `em_analise` tira o processo desses status e fecha o eixo em `analise_concluida` quando a conclusão passa por `ConclusaoAnaliseService`.
3. Desativado, cassado ou revogado a partir de deferido altera o status, não gera TVL e não reenvia o parecer ao REGIN.
4. Conclusão de malha fina sem encaminhamento aberto avisa e não muda o status.

## Divergências da redação anterior
- A redação anterior descrevia encerrar como ação genérica de qualquer perfil compatível. O código exige `concluir-analise` além de analisar, e o encerramento é um resultado nomeado, não um botão sem desfecho.
- Não há encerramento automático por SLA nesta história. O escalonamento só notifica (HU-147).

## Evidências
- `app/Services/Analise/ConclusaoAnaliseService.php`
- `app/Services/Analise/AnaliseTecnicaDecisionService.php`
- `app/Http/Controllers/Gestao/MalhaFinaController.php` (`concluir`)
- `app/Services/Analise/ProcessoQueryService.php` (`STATUS_FILA`)
- `routes/gestao.php`

## Não evidenciado
- Encerramento por arquivamento sem resultado de conclusão.
- Cassação automática vinda do REDESIM.
