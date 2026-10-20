# HU-091 — Escalonar processos parados por SLA

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Avisar, pelo semáforo do prazo de análise, o analista na faixa amarela e quem tem permissão de receber escalonamento na faixa vencida, sem decidir o processo.

## História de Usuário
**Como** sistema, **quero** escalar por notificação os processos em análise que entram em alerta ou vencem o SLA, **para** que o responsável e quem recebe escalonamento vejam o atraso, sem redistribuir nem encerrar sozinho.

## Atores e permissões
- Comando `notificacoes:escalonar-sla`, de hora em hora, com `withoutOverlapping` e `onOneServer`.
- Faixa amarela, no tratamento padrão `notificar_analista`: o `assignedTo`. Sem responsável, ninguém é avisado nessa faixa.
- Faixa vencida, no tratamento padrão `notificar_gestor`: todos os usuários cujo perfil tem `receber-escalonamento-sla`. No seeder, essa permissão está nas chefias. Não há filtro pelo setor do processo.
- Se não existe nenhum usuário com a permissão, o serviço registra aviso de log e a lista vem vazia.

## Fluxo evidenciado
1. Entram processos `em_analise` com `analysis_due_at` e `analysis_stage_started_at` preenchidos.
2. O semáforo é `AnalysisSlaService` sobre essas duas datas, a mesma origem da fila. Verde não gera aviso.
3. O que fazer em cada faixa vem de `notificacoes.escalonamento.tratamento`. Padrão: amarelo notifica o analista; vencido notifica os destinatários da permissão. Outro texto de tratamento não notifica e não executa ação.
4. A notificação é `ProcessoEscalonadoNotification`, tipo `escalonamento_sla`. No mapa padrão: e-mail e in-app.
5. Idempotência: não repete para o mesmo processo, destinatário e comunicação desse tipo desde `analysis_stage_started_at`.
6. O comando não transiciona status, não redistribui e não indefere.

## Regras de negócio evidenciadas
- Escalonar, no código, é notificar. Tratamento que não seja `notificar_analista` ou `notificar_gestor` é ignorado.
- O destinatário do vencido não é "o gestor do setor". É quem tem `receber-escalonamento-sla`, em qualquer setor.
- Processo fora de `em_analise`, ou sem as duas datas de prazo, não entra.

## Validações
- Não há formulário. A faixa verde é descartada no próprio comando.
- E-mail não entregável impede o dispatcher de criar a comunicação daquele destinatário.

## Critérios de aceite
1. Processo em análise no amarelo, com responsável, gera um aviso ao analista na etapa atual e não muda o status.
2. Processo no vermelho gera aviso a cada usuário com `receber-escalonamento-sla`, uma vez por etapa.
3. Segunda execução na mesma etapa não duplica.
4. Tratamento diferente dos dois nomes de notificação não redistribui, não encerra e não notifica.
5. Verde não gera comunicação.

## Divergências da redação anterior
- A redação anterior podia ser lida como movimentação do processo parado. O código só notifica. Não move de setor, não troca analista e não conclui.
- O vencido não vai ao gestor do setor do processo. Vai a quem tem a permissão `receber-escalonamento-sla`.

## Evidências
- `app/Console/Commands/EscalonarSlaCommand.php`
- `app/Services/Comunicacao/DestinatariosEscalonamento.php`
- `app/Services/Analise/AnalysisSlaService.php` (leitura do semáforo)
- `routes/console.php`
- `config/viabiliza.php` (`escalonamento.tratamento`, `escalonamento_sla`)
- `database/seeders/RolesAndPermissionsSeeder.php`

## Não evidenciado
- Redistribuição automática ou decisão por SLA.
- Destinatário calculado pelo setor do processo.
