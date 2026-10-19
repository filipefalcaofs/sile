# HU-076 — Emitir resultado expresso

## Épica
**EP09 — Fluxo Expresso**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Materializar o resultado do fluxo expresso numa decisão imutável, disparar o evento depois do commit e permitir que a retaguarda consulte a lista e o detalhe das solicitações já decididas.

## História de Usuário
**Como** servidor com permissão de consultar solicitações,  
**quero** ver o resultado expresso já gravado,  
**para** auditar veredito, TVL, fundamentação e o estado informado da transmissão, sem alterar a decisão.

## Atores e permissões
- Emissão: job e serviço, sem permissão de tela.
- Consulta: permissão `consultar-solicitacoes` em `gestao/resultados-expresso`.
- A consulta é somente leitura. Solicitação sem decisão responde 404 no detalhe.

## Fluxo evidenciado
1. `SolicitacaoProtocolada` faz `AvaliarFluxoExpresso` enfileirar `DecidirFluxoExpressoJob` com o id do processo. O job recarrega o registro, tenta de novo a localização se falta ponto e há inscrição, pede o polígono por ponto e chama `decide`.
2. Tentativas, timeout e intervalo vêm de `viabiliza.expresso.job` (padrão 3 tentativas, 120 segundos, intervalos 30, 60 e 120). Esgotadas as tentativas, `failed` audita `decisao-falha` e não grava decisão fictícia.
3. O comando `expresso:reavaliar` roda a cada dez minutos, com `withoutOverlapping` e `onOneServer`, para protocoladas sem decisão e para reavaliação dos casos ainda sem analista.
4. Na emissão bem-sucedida, a `ViabilityDecision` guarda fluxo, resultado, consolidado, TVL só no deferimento, por CNAE, versões, fundamentação, traço, motivo no indeferimento, autor e data. O evento `ResultadoEmitido` sai depois do commit.
5. A lista da retaguarda junta solicitações que têm decisão, filtra por resultado, protocolo ou TVL e período de `decided_at`, pagina 10, 15, 25 ou 50 e audita `consulta-resultado-lista`. Período com data inicial posterior à final devolve lista vazia e a mensagem de período inválido.
6. O detalhe carrega a decisão, a explicação projetada do traço gravado e o status de transmissão lido da auditoria `integracoes`: eventos `regin-parecer` e `sefaz-viabilidade`. Sucesso vira "Transmitido"; `bloqueado` vira pendente de canal; `ignorado` vira não aplicável; sem registro, "Aguardando processamento". A tela não marca enviado sem auditoria de sucesso.

## Regras de negócio evidenciadas
- A decisão do expresso não é editada nesta consulta.
- O número de TVL nasce na decisão deferida. O PDF do TVL não é gerado por este controller: `TvlPdfService` emite sob demanda, para decisão deferida, em outro fluxo da análise.
- O cidadão não recebe o PDF nesta emissão. A notificação é a HU-077.
- Transmissão à SEFAZ no deferimento passa por gateway que, no binding indisponível, resulta em auditoria `bloqueado`. Indeferimento é auditado como `ignorado` para a SEFAZ.
- Na contingência, `decide` também roda no pedido HTTP, além do job disparado pelo evento de protocolo. A segunda passagem é idempotente.

## Validações
- Filtro de resultado só aceita valor do enum `DecisionOutcome`.
- Datas de filtro só no formato `AAAA-MM-DD`; texto inválido é ignorado.
- Paginação fora de 10, 15, 25 e 50 cai no parâmetro `ui.cnaes.per_page` ou em 15.

## Critérios de aceite
- Dado protocolo que o motor consegue decidir, quando o job conclui, então existe uma `ViabilityDecision` e o evento de resultado foi disparado uma vez para essa emissão.
- Dado job que esgota as tentativas, quando falha, então a auditoria registra `decisao-falha` e não há resultado inventado.
- Dado usuário com `consultar-solicitacoes`, quando abre a lista, então vê só solicitações com decisão, com protocolo, resultado, TVL e data, e a consulta fica auditada.
- Dado solicitação sem decisão, quando abre o detalhe do resultado expresso, então a resposta é 404.
- Dado auditoria de SEFAZ com resultado `bloqueado`, quando abre o detalhe, então o canal aparece como pendente, não como transmitido.

## Divergências da redação anterior
A redação juntava comunicação bem-sucedida ao Regin, envio à SEFAZ e PDF de retaguarda como passos da emissão. O código grava a decisão e o evento. O PDF é outra emissão, sob demanda. O envio à SEFAZ, com o gateway indisponível, fica bloqueado e a tela mostra isso. Não há entrega de TVL ao requerente.

## Evidências
- `App\Jobs\DecidirFluxoExpressoJob`
- `App\Listeners\AvaliarFluxoExpresso`
- `App\Services\Expresso\FluxoExpressoService::emitir`
- `App\Http\Controllers\Gestao\ResultadoExpressoController`
- `App\Services\Expresso\TvlNumberGenerator`
- `routes/console.php` (`expresso:reavaliar`)
- `routes/gestao.php` (`resultados-expresso`, permissão `consultar-solicitacoes`)

## Não evidenciado
- PDF do TVL gerado automaticamente na emissão expresso ou anexado ao cidadão.
- Transmissão à SEFAZ com sucesso no binding padrão indisponível.
- Edição da decisão na tela de resultado.
