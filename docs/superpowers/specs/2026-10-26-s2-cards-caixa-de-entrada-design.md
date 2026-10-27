# S2 — Cards da Caixa de entrada

Origem: documento "Solicitações de Alterações e Ajustes - Viabiliza" (23/10), itens 1 e 2.

## Pedidos
- Três cards novos na Caixa de entrada do analista: Vistoriar, Retorno Vistoria, Retorno Convite.
- O card "Analisados (30 dias)" passa a se chamar "Concluídos".

## Regras (contagem do usuário logado)
- **Vistoriar:** processos que ele mandou para a vistoria e que seguem em `vistoriar` (último encaminhamento com `analista_origem_user_id` ou, sem ele, `encaminhado_por_user_id` igual ao usuário).
- **Retorno Vistoria:** processos em `vistoriado` atribuídos a ele, ou de volta ao setor de origem sem responsável cujo último encaminhamento à vistoria foi dele.
- **Retorno Convite:** processos da caixa dele com `analysis_status = convite_respondido`.
- **Concluídos (N dias):** mesma contagem de hoje (`contadorAnalisados`), só o nome muda; a aba "Analisados" também passa a "Concluídos".

## Onde
`ProcessoQueryService::contadores`, `ProcessoController::fila`, `resources/js/pages/gestao/processos/fila.tsx`.
