# HU-124 — Taxa de indeferimento

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Calcular a parcela de decisões indeferidas sobre as decisões do recorte.

## História de Usuário
**Como** gestor, **quero** a taxa de indeferimento, **para** ver o peso dos indeferimentos no conjunto decidido.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/indicadores`.

## Fluxo evidenciado
1. `taxaIndeferimento` conta decisões do recorte e as de desfecho indeferida.
2. A taxa usa uma casa decimal. Sem decisões, a taxa é nula.
3. O denominador é o mesmo universo da taxa de deferimento: decisões filtradas, não protocolos em aberto.
4. Período invertido não calcula.

## Regras de negócio evidenciadas
- Zero decisões não é taxa zero.
- Deferimento não entra no numerador.

## Validações
- Mesmos filtros e a mesma regra de período da tela de indicadores.

## Critérios de aceite
- Os contadores `indeferidas` e `total` batem com a taxa quando há decisão.
- Sem decisão, a taxa é nula.
- A série sai na mesma resposta dos outros indicadores.

## Divergências da redação anterior
O texto ligava a taxa a “gargalos e restrições” sem definir a base. A base evidenciada é o desfecho indeferido sobre as decisões do filtro.

## Evidências
- `app/Services/Relatorios/IndicadoresViabilidadeService.php` (`taxaIndeferimento`)
- `app/Http/Controllers/Gestao/RelatorioController.php` (`indicadores`)

## Não evidenciado
- Ranking de motivo de indeferimento nesta taxa (motivos de queda do expresso estão em outro relatório).
