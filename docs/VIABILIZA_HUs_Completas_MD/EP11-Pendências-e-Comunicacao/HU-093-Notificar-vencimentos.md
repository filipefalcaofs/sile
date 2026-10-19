# HU-093 — Notificar vencimentos

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Alertar, antes do vencimento, o analista sobre o prazo de análise e o requerente sobre o prazo do convite, sem mudar status.

## História de Usuário
**Como** sistema, **quero** avisar quem é responsável por um prazo que ainda não venceu e já entrou na antecedência, **para** que a fila e o convite sejam tratados a tempo.

## Atores e permissões
- Comando `notificacoes:alertar-vencimentos`, agendado todo dia às 07:00, com `withoutOverlapping` e `onOneServer`.
- Não há permissão de usuário. O analista alertado é o `assignedTo`. O requerente alertado é o `requester` da pendência aberta.

## Fluxo evidenciado
1. A antecedência é `notificacoes.vencimento.antecedencia_dias`, padrão 3 na config.
2. Processos `em_analise`, com responsável e com `analysis_due_at` maior que agora e menor ou igual ao limite, geram `PrazoVencendoNotification` para o analista. Prazo já vencido não entra aqui.
3. Pendências abertas com `due_at` na mesma janela geram o aviso para o requerente, com texto de convite e link do portal.
4. Os dois usam o dispatcher. O tipo no mapa padrão é `prazo_vencendo`: e-mail e in-app.
5. Idempotência pelo ledger: se já existe comunicação desse tipo para o processo e o destinatário desde o início da etapa (processo) ou desde `due_at` menos a antecedência (pendência), não envia de novo.
6. O comando não transiciona status e não grava timeline de tramitação.

## Regras de negócio evidenciadas
- Vencido é assunto do escalonamento (HU-147), não deste comando.
- Sem responsável, o processo é pulado. Sem requerente, a pendência é pulada.
- E-mail não entregável faz o dispatcher não criar comunicação.

## Validações
- `analysis_due_at` ou `due_at` nulos ficam de fora da consulta.
- Status diferente de `em_analise` no processo, ou pendência que não está aberta, não é alertado.

## Critérios de aceite
1. Processo em análise com prazo dentro da antecedência e ainda no futuro alerta o analista uma vez na janela.
2. Convite aberto na mesma janela alerta o requerente uma vez.
3. Segunda execução na mesma janela não duplica a comunicação.
4. Prazo já vencido não é alertado por este comando, e nenhum status muda.

## Divergências da redação anterior
- A redação anterior não separava alerta antecipado de escalonamento nem fixava a fonte do prazo. O código só notifica e usa `analysis_due_at` e `due_at` já gravados.

## Evidências
- `app/Console/Commands/AlertarVencimentosCommand.php`
- `routes/console.php`
- `config/viabiliza.php` (`notificacoes.vencimento`, `prazo_vencendo`)
- `app/Services/Comunicacao/NotificationDispatcher.php`

## Não evidenciado
- Mudança de responsável, redistribuição ou indeferimento por este alerta.
- Alerta de processo sem `assigned_user_id`.
