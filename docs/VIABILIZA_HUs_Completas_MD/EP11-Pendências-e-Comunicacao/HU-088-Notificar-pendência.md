# HU-088 — Notificar pendência

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Avisar o requerente, pelos canais habilitados no mapa, de que a análise abriu um convite.

## História de Usuário
**Como** sistema, **quero** notificar o requerente quando um convite é aberto, **para** que ele responda no portal dentro do prazo, sem o serviço de pendência enviar o aviso por conta própria.

## Atores e permissões
- Não há tela. O listener `NotificarPendencia` reage a `PendenciaSolicitada`, disparado depois do commit de `PendenciaService::abrir` (HU-087).
- O destinatário é o `requester` do processo. Quem abre o convite precisa de `analisar-processos`; a notificação em si não tem permissão de perfil.

## Fluxo evidenciado
1. Sem requerente, o listener audita `sem-destinatario` e não cria comunicação.
2. Com requerente, `NotificationDispatcher::deliver` só continua se o e-mail é entregável (não vazio e não terminado em `.invalid`). Sem isso, retorna sem gravar ledger e sem notificar, inclusive o canal in-app.
3. O tipo `pendencia_aberta` usa o mapa `notificacoes.mapa_canais`. No padrão da config, os canais são e-mail e in-app. WhatsApp não está nesse tipo.
4. Canal do mapa com o toggle `notificacao_{canal}` ligado entra na fila (`na_fila`) e a notificação é enfileirada. Canal do mapa com toggle desligado grava status `desativado` e audita, sem fingir envio.
5. E-mail e in-app passam a `enviado` ou `falhou` no listener `RegistrarEnvioComunicacao`, conforme o evento do Laravel. A preferência por usuário não filtra: o método que a consultaria devolve sempre verdadeiro.

## Regras de negócio evidenciadas
- O `PendenciaService` não notifica. Só o listener notifica, para não duplicar.
- A abertura da pendência e a transição para `em_pendencia` já ocorreram antes deste aviso. Falha de canal não desfaz o convite.
- Toggle de e-mail vem de `features.notificacao_email` (padrão verdadeiro na config). In-app tem o toggle correspondente.

## Validações
- Requerente ausente: auditoria e parada.
- E-mail não entregável: nenhuma linha de comunicação é criada.

## Critérios de aceite
1. Abrir convite em processo com requerente e e-mail entregável gera comunicações dos canais do mapa que estão ligados, em `na_fila`, e dispara a notificação.
2. Canal desligado no toggle fica `desativado`, com auditoria, e não é marcado como enviado.
3. Processo sem requerente não gera comunicação e audita `sem-destinatario`.
4. Uma única abertura não dispara dois listeners de notificação: o registro é pela descoberta do evento.

## Divergências da redação anterior
- A redação anterior descrevia notificação genérica, com integração e permissão indefinidas. O código notifica só o requerente, só depois da abertura efetivada, e só nos canais do mapa. WhatsApp não faz parte do mapa padrão deste tipo.

## Evidências
- `app/Listeners/NotificarPendencia.php`
- `app/Services/Comunicacao/NotificationDispatcher.php`
- `app/Listeners/RegistrarEnvioComunicacao.php`
- `config/viabiliza.php` (`notificacoes.mapa_canais`, `features.notificacao_email`)
- `app/Services/Analise/PendenciaService.php`

## Não evidenciado
- Envio por WhatsApp neste tipo de comunicação. O mapa padrão não inclui o canal, e o gateway está indisponível (HU-093).
- Escolha de canal pelo requerente. A preferência por usuário não é consultada.
