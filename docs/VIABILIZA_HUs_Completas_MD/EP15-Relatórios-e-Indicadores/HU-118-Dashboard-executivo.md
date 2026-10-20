# HU-118 — Dashboard executivo

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Mostrar o painel operacional da gestão para quem acompanha indicadores, e mandar quem analisa processo para a fila de trabalho.

## História de Usuário
**Como** gestor com permissão de relatórios, **quero** ver o movimento da operação na entrada do console, **para** acompanhar fila, prazo e resposta expressa. Quem está na cadeira de analista não cai neste painel.

## Atores e permissões
- Rota `/gestao` (`DashboardController`), com `acessar-gestao` e termo LGPD aceito.
- KPIs só se o usuário pode `consultar-relatorios`.
- Analista terceirizado ou analista servidor com `analisar-processos` é redirecionado para `/gestao/processos/fila`, mesmo que também tenha relatórios.
- Sem relatórios e com `analisar-processos`, vai para a fila. Sem isso e com `distribuir-processos`, vai para `/gestao/caixa-setor`.

## Fluxo evidenciado
1. A janela é 7, 30 ou 90 dias (`?janela=`). Fora disso vale `relatorios.dashboard.janela_dias` (padrão 30).
2. `PainelOperacaoService` monta protocolos do período e do período anterior, variação, decisões por fluxo, estoque, atrasados, saldo, pico, projeção de 30 dias, taxa expressa e meta, série, tempo até a decisão, cumprimento de prazo, ações, etapas, aging e carga de setores e analistas.
3. A carga nominal (nome do analista) só entra com `relatorios.produtividade.nominal`. Sem ela, a carga sai anonimizada.
4. Servidor sem papel vê o pedido de acesso, não um KPI fictício.
5. Taxa expressa e variação ficam nulas quando não há base. Projeção não vira número se a janela é zero.

## Regras de negócio evidenciadas
- O painel não substitui a caixa de quem opera o processo.
- Indicador sem amostra não é exibido como zero de desempenho quando o serviço devolve nulo.
- Os números saem das mesmas fontes dos relatórios (SLA, quedas, pendências, falhas, decisões).

## Validações
- `janela` só aceita 7, 30 e 90.

## Critérios de aceite
- Gestor com `consultar-relatorios` e sem papel de analista vê os KPIs da janela.
- Analista servidor ou terceirizado abre a fila, não o painel.
- Sem a permissão nominal, a carga de analistas não traz nome.
- Período sem base não fabrica taxa.

## Divergências da redação anterior
O texto era um dashboard genérico para qualquer gestor. O código separa analista da visão executiva e só calcula KPI com `consultar-relatorios`.

## Evidências
- `app/Http/Controllers/Gestao/DashboardController.php`
- `app/Services/Relatorios/PainelOperacaoService.php`
- `routes/gestao.php` (`gestao.dashboard`)

## Não evidenciado
- Painel executivo para o perfil de analista na rota `/gestao`.
