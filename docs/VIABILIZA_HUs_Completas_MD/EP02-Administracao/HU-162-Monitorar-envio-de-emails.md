# HU-162 — Monitorar envio de e-mails

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar a fila e o resultado dos e-mails registrados em `email_logs`.

## História de Usuário
**Como** operador com `monitorar-emails`,
**quero** filtrar os envios por situação e ver o erro dos que falharam,
**para** saber o que está na fila, o que saiu e o que falhou.

## Atores e permissões
- A tela exige `monitorar-emails`. Menu Configuração, item Monitoramento de e-mails (`/gestao/emails`).
- Não há ação de reenviar, excluir ou editar o log neste controller.

## Fluxo evidenciado
1. A lista filtra por `status` quando o parâmetro vem preenchido, ordena do mais recente e pagina com `ui.email_logs.per_page` (20 se ausente).
2. Cada linha traz destinatário (e-mail e nome), rótulo do tipo (`EmailLog::notificationLabel`), status, mensagem de erro, data de entrada na fila, data de envio, data de falha e data de criação.
3. Os contadores da página são três consultas: `na_fila`, `enviado` e `falhou`, sobre a tabela inteira, não só sobre a página filtrada.

## Regras de negócio evidenciadas
- A tela é somente leitura.
- O e-mail do destinatário é exibido. Esta consulta não mascara o endereço.
- Os status contados são `na_fila`, `enviado` e `falhou`.

## Validações
- Não há Form Request. O filtro `status` é aplicado quando vem na query. Valor ausente lista todos.

## Critérios de aceite
### CA-01 — Filtro de falha
**Dado** um usuário com `monitorar-emails` e logs em mais de um status,
**Quando** abre `/gestao/emails?status=falhou`,
**Então** a página traz só registros com status `falhou`, com `error_message` quando houver.

### CA-02 — Contadores
**Dado** logs nos três status,
**Quando** a lista abre,
**Então** `counts` informa as quantidades de `na_fila`, `enviado` e `falhou`.

### CA-03 — Sem reenvio
**Dado** as rotas `emails`,
**Quando** se procura uma ação além do index,
**Então** só existe a consulta.

## Divergências da redação anterior
Não havia HU. A capacidade existia no código sem rastreabilidade.

## Evidências
- `routes/gestao.php`: `emails.index` com `monitorar-emails`.
- `app/Http/Controllers/Gestao/EmailLogController.php`.
- `resources/js/navigation/gestao-nav.ts`: Monitoramento de e-mails em Configuração.

## Não evidenciado
- Reenvio, cancelamento ou edição do log.
- Mascaramento do e-mail do destinatário nesta tela.
- Configuração do servidor SMTP. Isso é a HU-161.
