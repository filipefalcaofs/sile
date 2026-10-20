# HU-080 — Indeferir solicitação

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Concluir como indeferido o processo em análise, com justificativa legível gravada na decisão, e indeferir por prazo quando o convite vence sem resposta.

## História de Usuário
**Como** usuário com `analisar-processos` e `concluir-analise`, **quero** indeferir com o motivo visível ao requerente, **para** encerrar a análise sem TVL. **Como** sistema, **quero** indeferir o convite vencido sem resposta, **para** aplicar o prazo do convite.

## Atores e permissões
- Indeferimento humano: as mesmas rotas e as duas permissões da HU-079 (`analisar-processos` e `concluir-analise`). Analista Terceirizado não indefere. Apoio não indefere.
- Indeferimento por prazo: comando `pendencias:expirar`, sem usuário. `decided_by_user_id` fica nulo. O texto vem do catálogo `justificativa.indeferimento.convite_expirado`.
- Emitir o PDF de indeferimento e baixá-lo: `emitir-tvl`, URL assinada, disco não público. A rota existe; o documento só é emitido para decisão indeferida.

## Fluxo evidenciado
1. Indeferir exige ao menos uma atividade marcada como indeferida na ficha. Sem marcação, a decisão é recusada.
2. A justificativa é o parecer da conclusão, senão o parecer da ficha, senão as justificativas das atividades indeferidas. Sem texto, a decisão é recusada antes de gravar.
3. O model `ViabilityDecision` impede salvar indeferimento com `reason` em branco.
4. O status vai para `indeferida`, sem número de TVL. O consolidado é `nao_permitido`. Auditoria na mesma transação. `ResultadoEmitido` sai depois do commit.
5. Renovação com condicionantes não atendidos força indeferimento mesmo que o escolhido fosse outro, e ainda assim precisa de justificativa.
6. Processo já `deferida` não é indeferido por esta conclusão.
7. Convite aberto com `due_at` vencido: o comando marca a pendência como expirada, indefere se o processo ainda está `em_pendencia` e notifica o analista atribuído ou, na falta dele, quem abriu o convite. O consolidado desse caminho é `convite_expirado`, sem CNAE e sem versão de regra.

## Regras de negócio evidenciadas
- Indeferimento humano sem justificativa não existe no banco.
- O indeferimento por prazo é procedimental: não reavalia o motor.
- A segunda passagem do comando não reprocessa convite que já deixou de estar aberto.

## Validações
- Escolha `indeferida` com nenhuma atividade indeferida: erro de domínio na tela.
- `reason` vazio no model: exceção de domínio no `saving`.

## Critérios de aceite
1. Sem `concluir-analise`, o indeferimento humano responde 403.
2. Com atividade indeferida e parecer, o processo fica `indeferida`, `reason` preenchido e sem TVL.
3. Sem justificativa em nenhum dos três textos, nada é gravado.
4. Convite vencido sem resposta gera decisão indeferida com `decided_by_user_id` nulo e motivo do catálogo, e notifica o analista.

## Divergências da redação anterior
- A redação anterior atribuía o indeferimento a qualquer analista e a um fluxo genérico do sistema. O indeferimento humano exige `concluir-analise`. O automático é só o prazo do convite, com texto fixo do catálogo, não uma regra da LOUOS reexecutada.
- A redação não exigia justificativa. O model e o serviço recusam indeferimento sem `reason`.

## Evidências
- `app/Services/Analise/AnaliseTecnicaDecisionService.php`
- `app/Models/ViabilityDecision.php`
- `app/Console/Commands/ExpirarPendenciasCommand.php`
- `app/Services/Analise/IndeferirPorPrazoConviteService.php`
- `app/Services/Decisao/DecisionTextCatalog.php`
- `routes/gestao.php` (rotas `indeferimento`)
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Envio do indeferimento à SEFAZ. O listener ignora decisão não deferida e audita essa omissão, sem chamar o gateway.
- Transmissão concluída do parecer ao REGIN. O listener captura a indisponibilidade e audita bloqueio.
