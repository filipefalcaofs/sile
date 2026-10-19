# HU-094 — Enviar e-mail

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Enviar as comunicações de processo pelo canal de e-mail quando o mapa e o toggle permitem, registrar o resultado no ledger e usar o servidor de e-mail padrão ativo quando houver um cadastrado.

## História de Usuário
**Como** sistema, **quero** entregar por e-mail as notificações de processo cujo mapa inclui o canal, **para** que o destinatário receba o aviso e a retaguarda saiba se foi enviado, se falhou ou se o canal estava desligado.

## Atores e permissões
- O envio das notificações de processo não tem tela: passa pelo `NotificationDispatcher` e pelo mailer do Laravel.
- Cadastrar, alterar, excluir e testar servidor SMTP: `manter-config-email`, em `config-email`. A senha não é reexibida. O teste envia `EmailServerTestMail` com a credencial cadastrada.
- No boot, se existe `EmailServer` padrão e ativo, a config de mail passa a ser esse SMTP. Sem servidor, ou se a senha não descriptografa, permanece o mailer do ambiente. Sem tabela, o boot não altera a config.

## Fluxo evidenciado
1. Para cada canal do tipo no mapa, com toggle ligado, o dispatcher cria `communications` em `na_fila` e chama `notify`.
2. O tipo com e-mail no mapa padrão: pendência aberta, pendência expirada, prazo vencendo, escalonamento de SLA e resultado. Pendência respondida, no padrão, é só in-app.
3. `RegistrarEnvioComunicacao` marca `enviado` em `NotificationSent` e `falhou` em `NotificationFailed`, só para os drivers `mail` e `database`, pelo id congelado na notificação. WhatsApp não é atualizado por esse listener.
4. Toggle `notificacao_email` desligado, com o canal ainda no mapa, grava `desativado` e audita. Não marca enviado.
5. Destinatário sem e-mail entregável não chega a enfileirar nenhum canal.
6. O teste de conexão do servidor é um envio real de mensagem de teste, separado do ledger de processo.

## Regras de negócio evidenciadas
- Não há status "enviado" sem o evento de envio do canal de e-mail.
- Falha grava a mensagem da exceção, ou o texto "Falha no envio da notificação." se não houver mensagem.
- Notificação de conta (verificação de e-mail ou senha) não usa este mapa: sem ids congelados, o listener de processo não mexe nela.

## Validações
- E-mail vazio ou terminado em `.invalid` impede o `deliver`.
- Servidor de e-mail: regras de `StoreEmailServerRequest` e `UpdateEmailServerRequest` no cadastro. Esta história não redefine esses campos.

## Critérios de aceite
1. Canal de e-mail ligado e presente no mapa cria linha `na_fila` e, com o mailer aceitando, passa a `enviado`.
2. Falha do mailer marca `falhou` com o motivo, não `enviado`.
3. Toggle desligado marca `desativado` e audita.
4. Servidor padrão ativo passa a ser o SMTP usado no boot. Sem servidor ativo, o mailer do ambiente permanece.
5. Sem `manter-config-email`, o cadastro do servidor responde 403. O envio das notificações de processo não depende dessa permissão.

## Divergências da redação anterior
- A redação anterior tratava e-mail como ação genérica de usuário. O código envia pelas notificações de processo e separa o cadastro do servidor, que só troca o mailer quando há padrão ativo.

## Evidências
- `app/Services/Comunicacao/NotificationDispatcher.php`
- `app/Listeners/RegistrarEnvioComunicacao.php`
- `app/Providers/MailConfigServiceProvider.php`
- `app/Http/Controllers/Gestao/EmailServerController.php`
- `app/Services/Email/EmailServerConnectionTester.php`
- `config/viabiliza.php` (`mapa_canais`, `notificacao_email`)
- `routes/gestao.php` (`config-email`)

## Não evidenciado
- Envio de e-mail de processo quando o destinatário não tem e-mail entregável.
- Reexibição da senha do servidor SMTP.
