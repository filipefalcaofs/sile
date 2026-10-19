# HU-095 — Enviar WhatsApp

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Bloqueada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Entregar notificação de processo por WhatsApp quando houver provedor. Enquanto o gateway concreto não existe, o canal não transmite e não pode constar como enviado.

## História de Usuário
**Como** sistema, **quero** registrar a tentativa de WhatsApp como bloqueada quando o provedor não está disponível, **para** não informar envio que não aconteceu.

## Atores e permissões
- Não há permissão de usuário para "enviar WhatsApp". O canal entra se o tipo estiver no mapa `notificacoes.mapa_canais` e o toggle `notificacao_whatsapp` estiver ligado.
- O binding atual de `WhatsAppGateway` é `UnavailableWhatsAppGateway`.
- No padrão da config, `notificacao_whatsapp` é falso e nenhum tipo do mapa inclui `whatsapp`. Nesse padrão o canal nem é enfileirado: ou fica de fora do mapa, ou, se for colocado no mapa com o toggle desligado, nasce `desativado`.

## Fluxo evidenciado
1. O dispatcher, com o canal no mapa e o toggle ligado, cria a comunicação em `na_fila` e segue para o canal.
2. `WhatsAppChannel` resolve o telefone em `routeNotificationFor('whatsapp')`. Sem destino, retorna sem transmitir e sem marcar a linha.
3. O gateway indisponível lança `WhatsAppUnavailableException`. O canal marca a comunicação como `bloqueado`, audita `notificacoes` com resultado `bloqueado` e não relança a exceção.
4. O rótulo do status é "Bloqueado". Não há chamada a `markAsSent` nesse caminho.
5. `RegistrarEnvioComunicacao` ignora o driver `whatsapp` de propósito. O Laravel pode emitir `NotificationSent` mesmo no bloqueio, porque a exceção não é relançada; se esse listener atualizasse a linha, trocaria bloqueado por enviado. Ele não atualiza.

## Regras de negócio evidenciadas
- Gateway indisponível nunca marca `enviado`.
- O fluxo da notificação dos outros canais não quebra por causa do WhatsApp.
- Sucesso com `markAsSent` só ocorreria se outro binding de `WhatsAppGateway` transmitisse sem lançar `WhatsAppUnavailableException`. Esse binding não está registrado.

## Validações
- Telefone em branco: não transmite e não altera o status da linha já criada pelo dispatcher (permanece `na_fila` se o canal chegou a enfileirar e o destino veio vazio).
- Toggle desligado: status `desativado`, que não é bloqueio de provedor.

## Critérios de aceite
1. Com o gateway atual, uma notificação que chegue ao `WhatsAppChannel` e tenha telefone não fica `enviado`. A linha localizada em `na_fila` fica `bloqueado` e a auditoria registra bloqueio.
2. O listener de e-mail e in-app não promove essa linha a `enviado`.
3. Com o toggle falso, o canal do mapa não é enviado e fica `desativado`.
4. O mapa padrão não inclui WhatsApp em nenhum tipo de comunicação de processo.

## Divergências da redação anterior
- A redação anterior descrevia envio de WhatsApp como funcionalidade executável, com sucesso genérico. O código não transmite: o único gateway registrado recusa o envio e o status honesto é bloqueado, não enviado.

## Evidências
- `app/Services/Whatsapp/UnavailableWhatsAppGateway.php`
- `app/Services/Whatsapp/WhatsAppGateway.php`
- `app/Notifications/Channels/WhatsAppChannel.php`
- `app/Listeners/RegistrarEnvioComunicacao.php`
- `app/Enums/CommunicationStatus.php`
- `app/Providers/AppServiceProvider.php` (binding do gateway)
- `config/viabiliza.php` (`notificacao_whatsapp`, `mapa_canais`)

## Não evidenciado
- Provedor HTTP, credencial de API comercial ou mensagem entregue ao aparelho.
- Tipo de comunicação de processo que, no padrão, saia por WhatsApp.
