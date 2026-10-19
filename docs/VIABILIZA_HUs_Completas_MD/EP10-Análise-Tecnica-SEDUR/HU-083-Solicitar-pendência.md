# HU-083 — Solicitar pendência

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Abrir um convite de complementação ao requerente, tirar o processo de análise e passar a aguardar resposta no prazo em horas úteis.

## História de Usuário
**Como** analista com `analisar-processos`, **quero** abrir um convite descrevendo o que falta, **para** que o requerente responda e o processo saia de `em_analise` enquanto espera.

## Atores e permissões
- `POST processos/{processo}/pendencias`: `analisar-processos`. Não exige `concluir-analise`.
- Cancelar o convite aberto usa a mesma permissão e exige parecer.
- Quem abre fica em `requested_by_user_id`.

## Fluxo evidenciado
1. O analista envia a descrição. O serviço só abre se o status canônico é `em_analise`; caso contrário lança exceção e o controller responde 422, sem gravar.
2. Na mesma transação cria `analysis_pendencies` com status aberta, prazo em horas úteis (`analise.convite.prazo_resposta_horas_uteis`, padrão 48, pulando fim de semana e feriado cadastrado), transiciona para `em_pendencia` e audita `pendencia-aberta`.
3. Depois do commit dispara `PendenciaSolicitada`. A notificação ao requerente é do listener (HU-090), não deste serviço.
4. A mensagem de sucesso da tela fala em convite aberto.
5. Cancelar exige convite aberto e processo em `em_pendencia`, grava parecer, status cancelada, autor e data, e devolve o processo a `em_analise`.

## Regras de negócio evidenciadas
- Não abre pendência fora de `em_analise`.
- O prazo gravado é `due_at` calculado em horas úteis, não em dias corridos.
- O vencimento sem resposta não é tratado nesta ação: o comando `pendencias:expirar` expira e indefere (HU-091).

## Validações
- `descricao`: obrigatória, texto, no máximo 2000 caracteres.
- Cancelamento: `parecer` obrigatório, no máximo 2000 caracteres. Sem convite aberto, a tela volta com erro e nada muda.

## Critérios de aceite
1. Em `em_analise`, com descrição válida, o processo vai para `em_pendencia`, a pendência nasce aberta e a auditoria registra abertura e prazo.
2. Fora de `em_analise`, a abertura responde 422 e não cria pendência.
3. Descrição vazia ou acima de 2000 caracteres é recusada na validação.
4. Cancelar convite aberto reabre a análise e exige o parecer do motivo.

## Divergências da redação anterior
- A redação anterior não distinguia convite, prazo em horas úteis nem o cancelamento com parecer. O código não chama a complementação de pendência na mensagem ao analista: a tela diz convite, e o registro continua sendo `AnalysisPendency`.

## Evidências
- `app/Http/Controllers/Gestao/ProcessoPendenciaController.php`
- `app/Services/Analise/PendenciaService.php` (`abrir`, `cancelar`)
- `app/Http/Requests/Gestao/AbrirPendenciaRequest.php`
- `app/Http/Requests/Gestao/CancelarConviteRequest.php`

## Não evidenciado
- Anexo obrigatório na abertura do convite.
- Indeferimento no ato de abrir a pendência.
