# HU-091 — Responder pendência

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que o requerente responda o convite aberto e que o sistema encerre, por indeferimento, o convite que venceu sem resposta.

## História de Usuário
**Como** requerente, **quero** enviar a resposta do convite no portal, **para** recolocar a solicitação em análise. **Como** sistema, **quero** expirar o convite vencido, **para** indeferir o processo que ficou sem resposta no prazo.

## Atores e permissões
- Resposta: portal, policy `view` da solicitação (dono ou representado). Terceiro recebe 403. A pendência tem de ser da mesma solicitação.
- Expiração: comando `pendencias:expirar`, agendado todo dia às 06:00, sem usuário. Não há permissão de perfil.

## Fluxo evidenciado
1. A resposta exige pendência aberta e processo em `em_pendencia`. Grava o texto, a data e o status `respondida`, transiciona para `em_analise` e audita. O aviso ao analista é a HU-092.
2. Fora desse estado, nada é gravado e o portal mostra o erro.
3. O comando seleciona pendências abertas com `due_at` anterior a agora. Marca `expirada` e audita `pendencia-expirada`.
4. Se o processo ainda está `em_pendencia`, `IndeferirPorPrazoConviteService` cria decisão indeferida, `flow` `analise_tecnica`, consolidado `convite_expirado`, `decided_by_user_id` nulo e `reason` do catálogo `justificativa.indeferimento.convite_expirado`. O status canônico vai para `indeferida`. `ResultadoEmitido` dispara depois do commit.
5. Em seguida notifica o analista atribuído ou, se não houver, quem abriu o convite, tipo `pendencia_expirada` (e-mail e in-app no mapa padrão).
6. Segunda execução não acha de novo a pendência aberta vencida.

## Regras de negócio evidenciadas
- Responder reabre a análise. Não responder até o `due_at` indefere. O comentário antigo do agendamento dizia que o comando não indeferia; o comando que roda indefere.
- O indeferimento por prazo não preenche CNAE nem versão de regra. O motivo legível é o texto do catálogo, então o model aceita a gravação.
- Só indefere se o status ainda é `em_pendencia`.

## Validações
- Resposta: `response` obrigatório, texto, no máximo 5000 caracteres.
- Comando: `due_at` nulo não entra. Pendência já respondida ou cancelada não entra.

## Critérios de aceite
1. Requerente autorizado responde convite aberto e a pendência fica `respondida`, com o processo em `em_analise`.
2. Resposta vazia é recusada. Resposta fora do estado aberto não grava.
3. Convite vencido passa a `expirada` e o processo em `em_pendencia` fica `indeferida`, com justificativa do catálogo e sem autor humano.
4. Rodar o comando de novo não gera segunda decisão para o mesmo convite.

## Divergências da redação anterior
- A redação anterior não descrevia o indeferimento automático. O código não mantém o processo em pendência depois do prazo: indefere.
- A resposta não é lançada pela gestão. O analista cancela o convite por outro caminho (HU-083), com parecer, sem texto do requerente.

## Evidências
- `app/Http/Controllers/Portal/PendenciaRespostaController.php`
- `app/Services/Analise/PendenciaService.php` (`responder`)
- `app/Console/Commands/ExpirarPendenciasCommand.php`
- `app/Services/Analise/IndeferirPorPrazoConviteService.php`
- `routes/console.php`
- `config/viabiliza.php` (`pendencia_expirada`)

## Não evidenciado
- Prorrogação do prazo pelo analista.
- Resposta com anexo.
