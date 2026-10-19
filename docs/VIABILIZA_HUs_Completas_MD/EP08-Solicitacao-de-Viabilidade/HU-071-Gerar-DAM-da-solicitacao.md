# HU-071 — Visualizar DAM da solicitação

## Épica
**EP08 — Solicitação de Viabilidade**

## Situação na implementação
- **Status:** Não evidenciada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O código não exibe um DAM da solicitação. Não há rota, tela nem consulta à SEFAZ que mostre número, valor, vencimento, código de barras ou PDF de guia.

## História de Usuário
**Como** analista ou requerente,  
**quero** visualizar o DAM vinculado à solicitação,  
**para** acompanhar a guia de arrecadação no processo.

Essa história não tem fluxo correspondente no código.

## Atores e permissões
Não há ator, permissão nem rota de visualização de DAM.

## Fluxo evidenciado
Não há fluxo de visualização de DAM.

O que existe ao lado, e não cumpre esta HU:

- `TllCalculoService::calcular` obtém, para um exercício com versão vigente de `tll_valores`, o maior valor de TLL entre os CNAEs que tenham `codigo_tll`, soma a taxa de serviço e, se algum item daquele código exige fator e o parâmetro `tll.fator_multiplicador` é positivo e diferente de 1, multiplica o valor. Sem versão vigente ou sem valor, devolve nulo.
- `EnviarViabilidadeSefaz` chama esse cálculo só para gravar a taxa na auditoria do envio de viabilidade à SEFAZ, no deferimento. O gateway padrão é `UnavailableSefazViabilidadeGateway`, que lança indisponibilidade. O listener audita resultado `bloqueado` e não apresenta guia.
- A ficha de análise pode mostrar o valor de TLL resolvido ou o texto "Pendente (tabela de taxas/DAM)". Isso não é um DAM.

## Regras de negócio evidenciadas
Nenhuma regra de exibição, numeração ou código de barras de DAM está implementada.

## Validações
Nenhuma validação de DAM.

## Critérios de aceite
Não há critério atendido de visualização de DAM. Não existe tela que liste número, situação, vencimento ou PDF da guia.

## Divergências da redação anterior
O título de catálogo passou a "Visualizar DAM da solicitação" e a redação descrevia consulta à API SEFAZ, com número, valor, vencimento, status e PDF. O código não consulta nem exibe DAM. A redação mais antiga, de gerar guia com número `DAM-AAAA-NNNNNN`, código de barras FEBRABAN, vencimento e PDF com selo, também não está no código. O cálculo de TLL e o envio bloqueado à SEFAZ não substituem a guia.

## Evidências
- Ausência de controller, rota, modelo ou página de DAM em `app/` e `resources/js`.
- `App\Services\Analise\TllCalculoService` (cálculo de valor, não guia).
- `App\Listeners\EnviarViabilidadeSefaz`
- `App\Services\Sefaz\UnavailableSefazViabilidadeGateway`
- `resources/js/pages/gestao/ficha-analise/show.tsx` (texto de valor TLL pendente).

## Não evidenciado
- Tela ou aba "Visualizar DAM".
- Consulta de DAM na SEFAZ.
- Número sequencial de DAM, código de barras, vencimento, PDF da guia, cancelamento de DAM e watermark de pago ou cancelado.
