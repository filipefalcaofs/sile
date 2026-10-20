# HU-123 — Taxa de deferimento

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Calcular a parcela de decisões deferidas sobre as decisões do recorte.

## História de Usuário
**Como** gestor, **quero** a taxa de deferimento do período, **para** ver o resultado das decisões, e não um percentual quando não houve decisão.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/indicadores`.

## Fluxo evidenciado
1. `taxaDeferimento` conta decisões do recorte e as de desfecho deferida.
2. A taxa é `deferidas / total × 100`, com uma casa. Total zero devolve taxa nula, com os dois contadores em zero.
3. O denominador é decisão, não protocolo sem decisão.
4. Período invertido devolve o mesmo formato nulo, sem consultar.

## Regras de negócio evidenciadas
- Ausência de decisão não vira 0%.
- A taxa não inclui indeferimento no numerador.

## Validações
- Filtros comuns do relatório. Período invertido interrompe a consulta.

## Critérios de aceite
- Com decisões, a taxa fecha com os contadores devolvidos.
- Sem decisões, `taxa` é nula e `total` é zero.
- A consulta é auditada junto com os outros indicadores da tela.

## Divergências da redação anterior
O texto não dizia que a base é a decisão do recorte nem que a taxa fica nula sem amostra.

## Evidências
- `app/Services/Relatorios/IndicadoresViabilidadeService.php` (`taxaDeferimento`)
- `app/Http/Controllers/Gestao/RelatorioController.php` (`indicadores`)

## Não evidenciado
- Meta de deferimento nesta série.
