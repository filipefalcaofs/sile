# HU-061 — Deferir automaticamente

## Épica
**EP09 — Fluxo Expresso**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Deferir no fluxo expresso o processo elegível cujo veredito consolidado é permitido ou permitido com condições, e também a exclusão de atividade que a regra manda deferir sem novo enquadramento, sempre com decisão imutável e número de TVL.

## História de Usuário
**Como** sistema,  
**quero** deferir automaticamente os casos que a regra autoriza,  
**para** encerrar o processo sem análise humana e registrar o produto.

## Atores e permissões
- O processamento do `FluxoExpressoService`. Na contingência, o operador autorizado entra como ator da decisão. No job disparado pelo protocolo do portal, o ator é nulo, salvo reavaliação com `atorId`.
- Não há botão de deferimento expresso para o analista neste serviço.

## Fluxo evidenciado
1. `emitir` cria `ViabilityDecision` com fluxo `expresso`, resultado `deferida` e o consolidado resolvido quando este não é `nao_permitido`.
2. Gera `tvl_product_number` por `TvlNumberGenerator` (prefixo padrão `TVL`, sequência única sem reinício anual, início configurável em 3000000).
3. Grava por CNAE, versões de regra, fundamentação vinda do motor e o traço da decisão. O motivo da decisão fica nulo no deferimento por este caminho.
4. Se a inscrição é abrigada de escritório virtual, a decisão referencia o TVL da sede.
5. A máquina transiciona para `deferida`. Se o processo estava na análise sem dono, limpa status, etapa e prazo de análise.
6. Audita `expresso` / `decisao` na mesma transação. Depois do commit, dispara `ResultadoEmitido`.
7. Exclusão exclusiva de CNAEs, sem o gatilho de sede, defere sem consultar enquadramento, com fundamentação da exclusão, TVL e por CNAE só com a intenção `excluir`.
8. Exclusão confirmada do CNAE gatilho, pelo titular da sede ativa, defere do mesmo modo e, após o commit, desvincula a inscrição. Sem a confirmação explícita (`confirma_perda_condicao_sede` exatamente verdadeiro), não defere: encaminha à análise.

## Regras de negócio evidenciadas
- Permitido e permitido com condições, no caminho `emitir`, geram deferimento. Não há status intermediário de "deferido com condições".
- Todo deferimento deste serviço recebe número de TVL. Indeferimento não recebe.
- A decisão é uma linha por solicitação. Corrida que esbarra na unicidade não cria segunda decisão nem redispara o evento.
- Alto risco sem ramo expresso da planilha não chega ao deferimento, exceto o ramo curado como expresso explícito, que a planilha manda decidir.
- Veredito pendente não é deferido.

## Validações
- Status de entrada: protocolada, ou em análise ainda não assumida e sem decisão.
- Confirmação de perda de sede não aceita nulo nem falso como sim.
- Titularidade do vínculo de sede: outra empresa na mesma inscrição não defere a exclusão da sede alheia.

## Critérios de aceite
- Dado processo elegível com consolidado permitido ou permitido com condições, quando o motor emite, então a decisão fica deferida, nasce número de TVL, o status da solicitação fica `deferida` e, após o commit, o evento de resultado é disparado.
- Dado somente CNAEs marcados para excluir, sem o CNAE gatilho da sede, quando decide, então defere sem passar pelo enquadramento e ainda gera TVL.
- Dado exclusão do CNAE gatilho pelo titular, com confirmação verdadeira e sede ativa, quando decide, então defere e dispara a desvinculação depois do commit.
- Dado a mesma exclusão sem confirmação verdadeira, quando decide, então não defere e encaminha à análise.
- Dado consolidado pendente ou CNAE fora do expresso sem veto locacional, quando decide, então não há deferimento.

## Divergências da redação anterior
O deferimento não espera DAM pago nem devolve PDF ao cidadão. Permitido com condições também defere. A exclusão de atividade defere sem rodar o enquadramento locacional, com registro próprio na fundamentação.

## Evidências
- `App\Services\Expresso\FluxoExpressoService::emitir`, `deferirExclusao`, `deferirExclusaoDeSede`
- `App\Services\Expresso\TvlNumberGenerator`
- `App\Services\Solicitacao\ViabilityRequestStateMachine` (saída `protocolada` → `deferida`)
- `App\Events\ResultadoEmitido`

## Não evidenciado
- Deferimento automático com veredito pendente ou com alto risco fora do ramo expresso da planilha.
- Número de TVL em indeferimento.
- Entrega do PDF do TVL ao requerente neste fluxo. A geração sob demanda do PDF é outro serviço, da análise.
