# Utilidade gerencial dos indicadores

Avaliação do conteúdo dos painéis do Viabiliza depois das correções de cálculo
(commits `d7cb42520` e `b2711e12a`). Muda só o que tem dado e regra definidos;
o resto fica registrado como pendência da SEDUR.

## Decisões

| Indicador | Tela | Decisão | Motivo |
|---|---|---|---|
| Variação de protocolos | Painel | Modificado: cor neutra | Verde para alta e vermelho para queda avaliam a demanda sem critério da SEDUR |
| Saldo do período | Painel | Modificado: cor neutra | Vermelho para saldo positivo avalia sem meta de estoque definida |
| Faixas de envelhecimento | Painel | Modificado: escala neutra | As faixas 0–5/6–10/11–20/>20 dias não são limites oficiais; verde→vermelho sugeria um |
| Barra deferidas × indeferidas por analista | Painel | Modificado: cores neutras | Deferir não é desempenho melhor que indeferir |
| Etapa acima do prazo em vermelho | Painel | Modificado: só quando a média e o prazo têm a mesma unidade | Média em dias úteis contra prazo em dias corridos não sustenta o alerta |
| Decisões no período: "X% expresso" | Painel | Modificado: taxa de automação com numerador e denominador | O número era a taxa de resposta expressa (outra métrica, pendente) e o rótulo dizia fração das decisões |
| Taxa de automação | Sala de Controle | Modificado: período anterior equivalente | Sem comparação o número não diz se a automação avança |
| Tempo médio | Sala de Controle | Modificado: nota "com tempo apurado" | O n conta só processos com horas apuradas, não todos os decididos |
| Decisões no período: "deferidas + indeferidas" | Indicadores | Modificado: nota descreve o universo real | O total inclui desativado, cassado e revogado |
| Prazo vencido, vence em breve, atrasados | Painel | Mantido com cor | Critério definido: prazo parametrizado |
| Meta da taxa expressa | Painel, Quedas | Mantido com cor | Meta é parâmetro do sistema |
| Demais cards, séries e tabelas | Todas | Mantidos | Respondem perguntas distintas; não há redundância sem benefício |
| Estoque "parado" | — | Proposto, depende da SEDUR | Falta definir quantos dias sem movimentação caracterizam parado |
| Tempo com o requerente por processo | Sala | Proposto, depende da SEDUR | Exige decidir se o prazo pausa durante o convite |
| Série histórica do estoque | Painel | Descartado agora | Não há fotografia diária do estoque; reconstruir pelas transições é estimativa |

## Ficha: taxa de automação no painel

1. **Nome e objetivo:** taxa de automação da janela do painel.
2. **Pergunta:** quanto do que foi decidido na janela o motor concluiu sozinho?
3. **Regra:** expressa = concluída pelo motor (decisão `flow = expresso` sem revisão humana posterior); vale também quando disparada por integração com usuário.
4. **Fórmula e unidade:** concluídos pelo motor ÷ decididos × 100, em %.
5. **Numerador / denominador:** processos com decisão na janela classificados como automáticos / todos os processos com decisão na janela.
6. **Fonte e período:** `viability_decisions` + `analysis_records` + transições, por `decided_at` no dia de Salvador; mesma janela do painel.
7. **Limitações:** a revisão regrava `decided_at` (pendência de histórico); decidido sem ficha finalizada fica em "sem análise concluída".
8. **Benefício:** mesmo número da Sala de Controle para a mesma janela; deixa de mostrar a taxa de resposta expressa com rótulo de fração das decisões.
9. **Testes:** valor e base no payload do painel por fluxos reais; nulo sem decisões; igual à `ClassificacaoFluxoService::contagem` do mesmo período.

## Ficha: período anterior da taxa de automação

1. **Nome e objetivo:** taxa de automação no período anterior equivalente.
2. **Pergunta:** a automação aumentou ou diminuiu em relação ao período anterior de mesma duração?
3. **Regra:** a mesma classificação; período com o mesmo número de dias de calendário, terminando na véspera do início do recorte.
4. **Fórmula e unidade:** taxa do período anterior (%) e diferença em pontos percentuais; quantidades absolutas dos dois períodos.
5. **Numerador / denominador:** como a taxa principal, no período anterior.
6. **Fonte e período:** igual à taxa principal; só existe quando o recorte tem data inicial e final.
7. **Limitações:** sem as duas datas não há período equivalente e a comparação não é exibida; diferença é nula quando um dos períodos não tem decisão.
8. **Benefício:** mostra a direção da automação sem classificar como bom ou ruim.
9. **Testes:** datas do período anterior; diferença em pontos; nulo sem datas e sem decisões.

## Pendências da SEDUR

- Limite de dias para considerar um processo parado.
- Limites oficiais de envelhecimento do estoque (as faixas atuais são de leitura).
- Meta de estoque ou de saldo, se houver.
- Pausa do prazo durante o convite.
