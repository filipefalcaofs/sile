# HU-066 — Indeferir sem atuação por prazo BAP

## Épica
**EP09 — Fluxo Expresso**

## Situação na implementação
- **Status:** Bloqueada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Indeferir solicitação que permaneça em `aguardando_bap` depois do prazo de atuação na Junta. O comando e o serviço existem. Em produção nenhum processo entra nesse status, então a rotina agendada não indefere ninguém.

## História de Usuário
**Como** sistema,  
**quero** indeferir o processo cujo prazo de BAP venceu sem atuação,  
**para** encerrar a espera na Junta com decisão registrada.

O indeferimento em si está escrito. A entrada no estado que o dispara não está ligada a uma fonte que devolva vínculo.

## Atores e permissões
- Comando de console `expresso:indeferir-sem-bap`, agendado de hora em hora, com `withoutOverlapping` e `onOneServer`.
- Não há ator humano. `decided_by_user_id` fica nulo.
- Não há tela.

## Fluxo evidenciado
1. O comando lê `expresso.bap.prazo_horas` (padrão 48) e busca solicitações em `aguardando_bap`.
2. Considera vencida a que tem `bap_due_at` no passado ou, sem essa data e com `bap_linked_at`, a data derivada por `BusinessDeadlineCalculator` a partir do vínculo e do prazo. Sem as duas datas, não entra na leva.
3. Para cada vencida, `IndeferirSemBapService::indeferir` exige status `aguardando_bap`. Outro status lança `InvalidArgumentException` e não grava decisão.
4. Cria `ViabilityDecision` de fluxo `expresso`, resultado indeferida, consolidado `sem_atuacao_bap`, sem TVL, sem por CNAE, sem versões e sem fundamentação. O motivo é o texto `justificativa.indeferimento.sem_bap`.
5. Transiciona para `indeferida` com razão "indeferido sem atuação", audita `expresso` / `decisao` e, após o commit, dispara `ResultadoEmitido`.
6. O listener da SEFAZ ignora indeferimento e audita `ignorado`, sem chamar o gateway.
7. Se a leva está vazia, o comando informa que não há processo aguardando BAP vencido e termina com sucesso.

A máquina de estados permite ir de `protocolada` para `aguardando_bap` e sair de `aguardando_bap` para deferida, indeferida ou em análise. Nenhum serviço de produção chama a transição de entrada. `UnavailableBapRegistry::findLinkage` devolve nulo: sem vínculo, o processo não é colocado em `aguardando_bap`.

## Regras de negócio evidenciadas
- Só `aguardando_bap` com vencimento calculável e já vencido é indeferido por este comando.
- O consolidado não é `nao_permitido`. O código registra `sem_atuacao_bap`, para não tratar o caso como veredito locacional.
- Não há número de TVL.
- O indeferimento não envia viabilidade à SEFAZ.
- A varredura seguinte não repete quem já foi para `indeferida`.
- Com a fila vazia, o comando não inventa indeferimento.

## Validações
- Status diferente de `aguardando_bap` é erro de programação no serviço, não um indeferimento.
- Prazo lido do parâmetro, com padrão 48 horas, aplicado pelo calculador de prazo quando só existe `bap_linked_at`.

## Critérios de aceite
- Dado solicitação já colocada em `aguardando_bap` com `bap_due_at` vencido, quando o comando roda, então a decisão fica indeferida, o consolidado é `sem_atuacao_bap`, o status vai a `indeferida` e o evento de resultado é disparado.
- Dado solicitação protocolada, em análise ou já indeferida, quando o comando varre, então ela não é alterada.
- Dado nenhuma solicitação em `aguardando_bap`, quando o comando roda em produção, então a mensagem é de nenhum processo vencido e o código de saída é sucesso.
- Dado o binding atual de BAP, quando se procura vínculo, então o retorno é nulo e nenhum fluxo evidenciado move o processo para `aguardando_bap`.

## Divergências da redação anterior
A redação tratava o prazo de 48 horas e o indeferimento "sem atuação" como rotina já operando sobre processos reais, com reprocesso de falha de janeiro e comunicação ao Regin. O prazo e o indeferimento estão no código. A comunicação específica ao Regin não está neste serviço: ele dispara `ResultadoEmitido`. A SEFAZ não recebe o indeferimento. Como nada entra em `aguardando_bap`, a agenda horária não produz indeferimento na operação atual.

## Evidências
- `App\Console\Commands\ExpressoIndeferirSemBapCommand`
- `App\Services\Expresso\IndeferirSemBapService`
- `App\Services\Regin\BapRegistry`
- `App\Services\Regin\UnavailableBapRegistry`
- `App\Services\Solicitacao\ViabilityRequestStateMachine` (transição permitida, sem chamador de entrada)
- `routes/console.php` (agenda horária)
- `App\Listeners\EnviarViabilidadeSefaz` (indeferimento com resultado `ignorado`)

## Não evidenciado
- Processo de produção cuja origem seja este comando enquanto o registro de BAP devolve nulo.
- Gravador de `bap_due_at` ou transição para `aguardando_bap` no fluxo do portal, da contingência ou do `FluxoExpressoService`.
- Contagem do prazo em dias úteis dentro do `IndeferirSemBapService`. O comando delega o vencimento a `BusinessDeadlineCalculator` somente quando deriva a data a partir de `bap_linked_at`.
- Envio do indeferimento à SEFAZ.
