# HU-062 — Indeferir automaticamente

## Épica
**EP09 — Fluxo Expresso**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Indeferir no fluxo expresso o processo cujo veredito locacional é não permitido, cuja regra territorial manda indeferir ou cuja pergunta necessária ao enquadramento chegou sem resposta, com decisão imutável e sem número de TVL.

## História de Usuário
**Como** sistema,  
**quero** indeferir automaticamente os casos em que a regra impede o uso no local ou falta resposta indispensável,  
**para** encerrar o processo com fundamentação, sem análise humana.

## Atores e permissões
- O mesmo processamento da HU-061. Não há ação manual de indeferimento expresso neste serviço.
- O indeferimento por prazo de BAP é a HU-066, outro serviço.

## Fluxo evidenciado
1. Se a regra territorial devolve indeferir, `indeferirTerritorial` cria decisão `indeferida`, consolidado `nao_permitido`, motivo e fundamentação da regra, versões incluindo a versão territorial, sem TVL. Transiciona para `indeferida`, audita e, após o commit, dispara `ResultadoEmitido`.
2. Se há pergunta da planilha sem resposta, o consolidado não é `nao_permitido` e `viabiliza.expresso.indeferir_pergunta_sem_resposta` está ativo (padrão verdadeiro), `indeferirPerguntaSemResposta` indefere com o texto do catálogo, o número, o enunciado e o CNAE da pergunta.
3. Se o consolidado resolvido é `nao_permitido`, `emitir` indefere antes do gate de alto risco. A decisão leva por CNAE, versões, fundamentação do motor e o motivo montado por `JustificativaIndeferimentoBuilder`. `tvl_product_number` fica nulo.
4. Alto risco, inelegibilidade, pendência, gatilho de sede e regras territoriais de análise não indeferem: encaminham à análise (HU-060).

## Regras de negócio evidenciadas
- Veto locacional (`nao_permitido`) indefere mesmo quando há alto risco. A análise só recebe o que ainda pode ser deferido.
- Indeferimento expresso não gera número de TVL.
- Pergunta sem resposta não indefere quando o consolidado já é `nao_permitido`: esse caso usa o indeferimento locacional. Com o config desligado, a pergunta sem resposta não entra neste atalho.
- A decisão é única por solicitação. Segunda tentativa concorrente não redispara o evento.
- O motivo do indeferimento por emissão é a justificativa do veredito, não um texto genérico fixo.

## Validações
- As mesmas guardas de status e de lock da decisão.
- Config `viabiliza.expresso.indeferir_pergunta_sem_resposta` liga ou desliga o indeferimento por pergunta sem resposta.

## Critérios de aceite
- Dado consolidado `nao_permitido` depois das regras territoriais que não indeferiram antes, quando o motor emite, então a solicitação fica `indeferida`, a decisão registra o motivo e não há número de TVL.
- Dado veredito territorial de indeferir, quando decide, então a decisão usa o motivo da regra e o consolidado não permitido, sem TVL.
- Dado pergunta necessária sem resposta, consolidado diferente de não permitido e o config ativo, quando decide, então indefere citando número, enunciado e CNAE.
- Dado alto risco sem veto locacional, quando decide, então não indefere e encaminha à análise.
- Dado o evento após o commit, quando o resultado é indeferimento, então `EnviarViabilidadeSefaz` não chama o gateway e audita o envio como `ignorado`.

## Divergências da redação anterior
Nem todo resultado desfavorável de risco vira indeferimento automático. Alto risco sem veto locacional vai à análise. Não há indeferimento por falta de pagamento de DAM neste serviço. O indeferimento por BAP sem atuação é outra HU e grava consolidado `sem_atuacao_bap`.

## Evidências
- `App\Services\Expresso\FluxoExpressoService::indeferirTerritorial`, `indeferirPerguntaSemResposta`, `emitir`
- `App\Services\Expresso\JustificativaIndeferimentoBuilder`
- `App\Listeners\EnviarViabilidadeSefaz` (indeferimento ignorado para a SEFAZ)
- Config `viabiliza.expresso.indeferir_pergunta_sem_resposta`

## Não evidenciado
- Indeferimento automático por alto risco quando o local ainda permite a atividade.
- Número de TVL na decisão indeferida.
- Indeferimento por DAM não pago.
