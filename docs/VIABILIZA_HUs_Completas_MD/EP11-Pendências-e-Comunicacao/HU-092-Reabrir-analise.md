# HU-092 — Reabrir análise

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Devolver o processo de `em_pendencia` para `em_analise` quando o requerente responde ou quando o analista cancela o convite, e avisar o analista atribuído no caso da resposta.

## História de Usuário
**Como** sistema, **quero** reabrir a análise ao receber a resposta do convite, **para** o responsável continuar o processo, e avisar esse analista. **Como** analista, **quero** cancelar o convite com parecer, **para** reabrir a análise sem esperar a resposta.

## Atores e permissões
- Reabertura pela resposta: requerente autorizado na solicitação (HU-091). Não há rota chamada "reabrir".
- Reabertura pelo cancelamento: `analisar-processos`, parecer obrigatório (HU-083).
- O aviso usa o `assignedTo`. Sem responsável, audita `sem-destinatario` e não inventa destinatário.

## Fluxo evidenciado
1. `responder` grava a pendência como `respondida` e transiciona `em_pendencia` → `em_analise` na mesma transação, com o motivo "Convite respondido pelo requerente".
2. Depois do commit, `PendenciaRespondida` aciona `NotificarRespostaPendencia`.
3. O dispatcher entrega `RespostaPendenciaNotification` ao analista atribuído. No mapa padrão, o tipo `pendencia_respondida` só tem o canal in-app.
4. O cancelamento também transiciona para `em_analise`, com o parecer no motivo, mas não dispara `PendenciaRespondida` e não notifica por esse listener.
5. Convite expirado não reabre: o comando indefere (HU-091).

## Regras de negócio evidenciadas
- Só reabre pendência aberta com processo em `em_pendencia`.
- A reabertura pela resposta não redistribui o processo. O responsável continua o `assigned_user_id` já gravado.
- E-mail não entregável do analista faz o dispatcher sair antes de criar a comunicação in-app.

## Validações
- As da resposta (HU-091) e as do cancelamento (parecer obrigatório, até 2000 caracteres).

## Critérios de aceite
1. Resposta válida coloca o processo em `em_analise` e, com analista atribuído e e-mail entregável, gera comunicação `pendencia_respondida` no canal do mapa.
2. Sem analista atribuído, a análise reabre e a notificação audita `sem-destinatario`.
3. Cancelamento com parecer reabre a análise e não usa o listener da resposta.
4. Pendência expirada não volta para `em_analise` por este fluxo.

## Divergências da redação anterior
- A redação anterior tratava reabrir como funcionalidade genérica, possivelmente manual e independente. No código, reabrir é o efeito de responder ou de cancelar o convite. Não há reabertura de processo já indeferido ou deferido nesta história.

## Evidências
- `app/Services/Analise/PendenciaService.php` (`responder`, `cancelar`)
- `app/Listeners/NotificarRespostaPendencia.php`
- `config/viabiliza.php` (`pendencia_respondida`)

## Não evidenciado
- Botão de reabrir análise concluída, indeferida ou deferida.
- Notificação ao analista quando o próprio analista cancela o convite.
