# HU-084 — Receber complementação

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Registrar a resposta do requerente ao convite aberto e devolver o processo à análise.

## História de Usuário
**Como** requerente da solicitação, **quero** responder o convite no portal, **para** que a análise seja reaberta com o texto que enviei.

## Atores e permissões
- Portal: `GET` e `POST` em `solicitacoes/{solicitacao}/pendencias`. A policy `view` restringe ao dono ou ao representado. Terceiro recebe 403.
- A pendência precisa pertencer à solicitação; caso contrário, 404.
- Não há tela da gestão para o requerente colar a resposta. O analista só cancela o convite (HU-083), o que também reabre a análise, com parecer, sem gravar resposta do requerente.

## Fluxo evidenciado
1. A tela lista pendências com status `aberta` da solicitação.
2. `PendenciaService::responder` exige pendência aberta e processo em `em_pendencia`. Fora disso, nada é gravado e o portal volta com a mensagem da exceção.
3. Na transação grava `response`, `responded_at` e status `respondida`, transiciona para `em_analise` com o motivo "Convite respondido pelo requerente" e audita `pendencia-respondida`.
4. Depois do commit dispara `PendenciaRespondida`, que notifica o analista atribuído (HU-092).

## Regras de negócio evidenciadas
- Uma pendência já respondida, expirada ou cancelada não aceita nova resposta.
- A reabertura é a transição `em_pendencia` → `em_analise` na mesma transação da resposta. Não há ação separada de "receber" na caixa do analista.

## Validações
- Campo `response`: obrigatório, texto, no máximo 5000 caracteres.

## Critérios de aceite
1. Requerente autorizado responde convite aberto e o processo volta a `em_analise`, com a resposta e a data gravadas.
2. Usuário sem acesso à solicitação recebe 403.
3. Pendência de outro processo na URL recebe 404.
4. Resposta com pendência fechada ou processo fora de `em_pendencia` não altera dados e exibe o erro.

## Divergências da redação anterior
- A redação anterior descrevia recebimento genérico pela SEDUR. O código recebe o texto no portal, pelo requerente, e a gestão não lança a complementação no lugar dele.

## Evidências
- `app/Http/Controllers/Portal/PendenciaRespostaController.php`
- `app/Http/Requests/Portal/ResponderPendenciaRequest.php`
- `app/Services/Analise/PendenciaService.php` (`responder`)
- `routes/portal.php`

## Não evidenciado
- Upload de arquivo como complementação nesta resposta.
- Fila específica "complementações recebidas" além da volta do processo para `em_analise` e do aviso ao analista atribuído.
