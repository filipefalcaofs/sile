# HU-096 — Consultar histórico de comunicações

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar, por processo, todas as comunicações do ledger, com o status real de cada canal, no portal para o requerente e na gestão com o diagnóstico interno.

## História de Usuário
**Como** requerente, **quero** ver as comunicações da minha solicitação, **para** saber o que foi registrado em cada canal. **Como** usuário da gestão com `consultar-solicitacoes`, **quero** ver o mesmo histórico com a mensagem de erro do canal, **para** diagnosticar falha sem consultar outra tabela.

## Atores e permissões
- Portal: `GET solicitacoes/{solicitacao}/comunicacoes`, policy `view`. Terceiro recebe 403.
- Gestão: `GET processos/{processo}/comunicacoes`, `consultar-solicitacoes`. Não há permissão exclusiva de comunicação.
- A gestão vê `error_message`. O portal não recebe esse campo.

## Fluxo evidenciado
1. A fonte é a tabela `communications` do processo, com o destinatário, ordenada da mais recente para a mais antiga.
2. Entram todos os canais e tipos gravados: e-mail, in-app e, se existir linha, WhatsApp, com status `na_fila`, `enviado`, `falhou`, `bloqueado` ou `desativado`.
3. A consulta audita `historico-consultado` com a origem (`portal` ou `gestao`) e o total.
4. Não há gravação nem reenvio nesta tela. E-mail genérico de conta, sem `viability_request_id`, não entra nesta lista.

## Regras de negócio evidenciadas
- O histórico não recalcula envio. Mostra o que o dispatcher e os canais já gravaram.
- Status bloqueado de WhatsApp aparece como bloqueado. Esta consulta não o converte em enviado.
- O erro interno do canal fica só na retaguarda.

## Validações
- Processo de outro requerente no portal: 403 pela policy.
- Processo inexistente: 404.

## Critérios de aceite
1. O requerente autorizado vê as comunicações do próprio processo, sem `error_message`.
2. A gestão com `consultar-solicitacoes` vê as mesmas linhas e o erro interno.
3. A lista inclui o status honesto, inclusive `bloqueado` e `desativado`.
4. Cada consulta gera auditoria com a origem e a quantidade.

## Divergências da redação anterior
- A redação anterior não fixava a fonte nem a diferença entre portal e gestão. O código não junta log genérico de e-mail com a central de notificações: a lista é o ledger do processo.

## Evidências
- `app/Http/Controllers/ComunicacaoHistoricoController.php`
- `app/Http/Resources/CommunicationResource.php` (uso de `withErrorMessage`)
- `routes/gestao.php`
- `routes/portal.php`
- `app/Enums/CommunicationStatus.php`

## Não evidenciado
- Reenvio a partir desta tela.
- Histórico de e-mails de conta que não têm solicitação associada.
