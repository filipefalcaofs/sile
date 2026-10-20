# HU-064 — Notificar resultado ao cidadão

## Épica
**EP09 — Fluxo Expresso**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Avisar o requerente quando o fluxo expresso emite decisão, por e-mail e pelo canal interno, sem anexar o TVL.

## História de Usuário
**Como** requerente com e-mail,  
**quero** ser avisado do deferimento ou do indeferimento,  
**para** saber que a análise automática terminou e onde acompanhar o resultado.

## Atores e permissões
- Destinatário: o `requester` da solicitação.
- Não há permissão de tela. O aviso nasce do listener de `ResultadoEmitido`.
- O interruptor é `features.notificacao_resultado_expresso` (padrão verdadeiro).

## Fluxo evidenciado
1. `NotificarResultadoExpresso` escuta `ResultadoEmitido`, o mesmo evento da decisão expressa e do indeferimento sem BAP.
2. Com o parâmetro desligado, não envia e audita `notificacoes` / `resultado-expresso` com resultado `desativado`.
3. Sem requerente ou sem e-mail, audita `sem-destinatario` e não envia.
4. Com destinatário, `NotificationDispatcher` entrega `ResultadoExpressoNotification`.
5. O e-mail usa o assunto de config `expresso.notificacao.assunto_deferida` ou `assunto_indeferida`, informa o protocolo e o resultado e diz que o documento oficial segue pelos canais da Junta e, no deferimento, também da SEFAZ. Não há chamada de anexo.
6. O conteúdo interno traz título, mensagem, protocolo, resultado e URL da consulta autenticada do portal.
7. Os canais efetivos vêm do dispatcher (mapa de canais e toggles). Sem canais congelados, `via` cai em e-mail. A notificação implementa fila (`ShouldQueue`).

## Regras de negócio evidenciadas
- O aviso não carrega PDF de TVL nem de indeferimento.
- Desligar o parâmetro não apaga a decisão. Só deixa o aviso de lado, com auditoria.
- Falha de destinatário não inventa envio.
- A auditoria da decisão (HU-065) já ocorreu na transação e não depende deste listener.

## Validações
- Exige requerente com e-mail para enviar.
- O resultado exibido é o `DecisionOutcome` da decisão já gravada.

## Critérios de aceite
- Dado deferimento ou indeferimento expresso com requerente que tem e-mail e o parâmetro ligado, quando o evento é tratado, então o dispatcher recebe a notificação com o protocolo e o resultado, e o e-mail não anexa arquivo.
- Dado parâmetro desligado, quando o evento ocorre, então não há entrega e a auditoria fica `desativado`.
- Dado requerente sem e-mail, quando o evento ocorre, então não há entrega e a auditoria fica `sem-destinatario`.
- Dado a notificação interna, quando é montada com id da solicitação, então a URL aponta para `portal.solicitacoes.show`.

## Divergências da redação anterior
A redação falava em ciência pelos canais oficiais do Regin como parte desta HU. O código desta HU envia e-mail e aviso interno do Viabiliza, e declara que o documento oficial não vai no e-mail. Não há, neste listener, postagem do parecer na Junta.

## Evidências
- `App\Listeners\NotificarResultadoExpresso`
- `App\Notifications\ResultadoExpressoNotification`
- `App\Events\ResultadoEmitido`
- Parâmetro `features.notificacao_resultado_expresso`
- Config `viabiliza.expresso.notificacao.assunto_deferida` e `assunto_indeferida`

## Não evidenciado
- Anexo do TVL ou de PDF de indeferimento na notificação.
- Envio ao Regin dentro deste listener.
- Notificação de encaminhamento à análise. O evento usado é o de resultado emitido, não o de encaminhamento.
