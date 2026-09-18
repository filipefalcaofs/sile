# Spec — Dashboards P0: Minha Mesa (analista) + Visão Geral da Operação com SLA (gestão)

Data: 2026-09-18
Origem: auditoria de dashboards (conversa desta data) — relatório completo nas seções 1–16 da análise.
Abordagem escolhida: **A** — services de consulta dedicados, seguindo o padrão de `IndicadoresViabilidadeService` / `TempoAnaliseService`.

## Decisões de design (fechadas com o usuário)

1. **Navegação:** Minha Mesa vira a landing do analista; Visão Geral substitui a home de gestão (`/gestao`). Fila/Caixa do setor continuam existindo como ferramenta de trabalho, apenas deixam de ser a porta de entrada.
2. **SLA:** seção dentro da Visão Geral (tela única de gestão), não página própria.
3. **KPIs administrativos** (total de usuários, perfis, CNAEs, acessos): removidos da home sem substituto — os módulos já exibem esses totais nas próprias listas.

## Contexto e evidências (auditoria)

- Entidade central: `ViabilityRequest` (`viability_requests`). Não existe model `Processo`.
- Dois eixos de status: canônico (`ViabilityRequestStatus`) e operacional (`AnalysisStatus`, 11 valores).
- SLA materializado: `analysis_due_at`, `analysis_stage`, `analysis_stage_started_at`; dias úteis via `BusinessDeadlineCalculator` + `holidays`; parâmetros `analise.sla.*`.
- Atribuição: `assigned_user_id`, `assigned_at`, `sector_id` (roteamento ao setor é pendência SEDUR — coluna pode ser nula).
- Convites: `analysis_pendencies` (`status`, `due_at`, `responded_at`).
- Decisões: `viability_decisions` (`flow`, `outcome`, `decided_at`, `decided_by_user_id`), 1:1 com a solicitação.
- Quedas do expresso: `expresso_quedas` + `ExpressoQuedaService` (taxa de resposta expressa × meta).
- Semáforo de SLA já existe na fila via `AnalysisSlaService` — reusar, não duplicar.
- Protocolo: métricas pós-protocolo filtram `protocoled_at IS NOT NULL` (rascunhos têm protocolo nulo).

## Unidade 1 — Minha Mesa (analista)

### Propósito

Responder "o que eu preciso fazer hoje, em que ordem?" — a tela que o analista abre de manhã.

### Backend

- **`App\Services\Analise\MesaAnalistaService`**
  - Entrada: `User` (o analista logado).
  - Saída:
    - `contadores`: meus processos por status operacional, vencidos, vencendo hoje, convites abertos aguardando resposta do requerente, convites respondidos aguardando retomada.
    - `lista`: processos atribuídos (`assigned_user_id = user`) em status não terminal, ordenados por urgência:
      1. vencidos (`analysis_due_at < now()`) primeiro;
      2. demais por `analysis_due_at` ascendente (nulos por último).
    - Cada item com `acao` derivada do `analysis_status`: `analisar` (analisar/em_analise/para_distribuir/encaminhado), `retomar_convite` (convite_respondido), `aguardando_requerente` (em_convite), `vistoriar` (vistoriar), `concluir` (analise_concluida/vistoriado).
  - Semáforo de prazo reusando `AnalysisSlaService` (mesma regra da fila; não duplicar).
- **`App\Http\Controllers\Gestao\MesaController@index`** → `GET /gestao/minha-mesa`, nome `gestao.minha-mesa`, middleware `permission:analisar-processos`.
- **Redirect pós-login:** analista (tem `analisar-processos` e não tem `consultar-relatorios`) cai em `gestao.minha-mesa`; gestor/admin caem em `gestao.dashboard`. Implementar no ponto de redirect pós-login da gestão (`Gestao\LoginController` / intended), preservando `redirect()->intended()` quando houver URL alvo.

### Frontend

- **`resources/js/pages/gestao/minha-mesa.tsx`**
  - KPI cards dos contadores.
  - Lista acionável (não é gráfico): protocolo, endereço resumido, CNAE primário, badge de ação, semáforo de prazo, data limite. Clique abre a ficha/processo.
  - Link visível para a Fila de trabalho / Caixa do setor.
- Menu lateral (`gestao-layout.tsx`): entrada "Minha Mesa" para quem tem `analisar-processos`.

### Permissões / LGPD

Escopo sempre o próprio usuário logado. Nenhum dado de terceiros. Sem gate adicional.

## Unidade 2 — Visão Geral da Operação (gestor) — nova home de gestão

### Propósito

Responder em 10 segundos: quanto entrou, quanto saiu, quanto está acumulado, quanto está atrasado, como está o SLA e a taxa do expresso.

### Backend

- **`App\Services\Relatorios\VisaoGeralOperacaoService`**
  - Entrada: período (`data_de`, `data_ate`; default = janela do parâmetro `relatorios.dashboard.janela_dias`, default 30).
  - Saída:
    - `protocolos_periodo`: count de `viability_requests` com `protocoled_at` no período.
    - `decisoes_periodo`: count de `viability_decisions` com `decided_at` no período, split por `flow` (`expresso` × `analise`).
    - `estoque_atual`: count por `analysis_status` dos processos não decididos (sem `viability_decisions` e status canônico não terminal).
    - `atrasados_agora`: count de não decididos com `analysis_due_at < now()`.
    - `taxa_expresso_30d`: reuso de `ExpressoQuedaService::taxaRespostaExpressa` na janela.
    - `serie_entrada_saida`: série diária de protocolados × decididos no período.
    - **Seção SLA:**
      - `sla_cumprido_percentual`: % de decisões do período com `decided_at <= analysis_due_at` (denominador: decisões com `analysis_due_at` não nulo).
      - `aging_estoque`: distribuição do estoque atual por faixa de consumo do prazo: 0–50%, 50–80%, 80–100%, >100% (base: `analysis_stage_started_at` → `analysis_due_at`; processos sem prazo entram em faixa própria `sem_prazo`).
      - `convites_vencendo_48h`: count de `analysis_pendencies` com `status = aberta` e `due_at` nas próximas 48h.
- **`Gestao\DashboardController`** reescrito: remove KPIs administrativos (`Cnae::count()`, `User::count()`, `Role`/`Permission::count()`, `AccessLog`) e passa a servir `VisaoGeralOperacaoService`. Blocos operacionais sob `permission:consultar-relatorios` (padrão já existente).

### Frontend

- **`resources/js/pages/gestao/dashboard.tsx`** reescrito:
  - 5 KPI cards: protocolos, decisões (expresso/humano), estoque total, atrasados, taxa do expresso × meta.
  - Gráfico de linha: entrada × saída (ECharts, padrão existente via `components/ui/chart`).
  - Barras: estoque por etapa operacional.
  - Seção SLA: cards (% cumprido, convites vencendo) + barras de aging por faixa.
  - Drill-down: cada card/seção linka para a lista de processos com o filtro correspondente (ex.: atrasados → fila filtrada por vencidos).
  - Remover atalhos/KPIs administrativos e card de sessão se não agregarem à operação.
- Filtro: apenas período (default 30d). **Sem filtro de setor** (roteamento é pendência SEDUR — coluna ficaria vazia).

### Permissões / LGPD

Somente agregados; drill-down respeita as permissões atuais de consulta de processos. Sem dados pessoais de requerentes nos agregados.

## Unidade 3 — Testes (TDD estrito)

- `tests/Feature/Analise/MesaAnalistaServiceTest.php`
  - ordenação: vencidos primeiro, depois prazo ascendente, nulos por último;
  - contadores corretos por cenário (vencido, vencendo hoje, convite aberto, convite respondido);
  - escopo: processos de outro analista não aparecem;
  - badge de ação por `analysis_status`;
  - processos decididos/cancelados fora da lista.
- `tests/Feature/Gestao/MesaControllerTest.php`
  - rota responde 200 para analista, 403 para quem não tem `analisar-processos`;
  - redirect pós-login: analista → minha-mesa; gestor → dashboard; intended URL preservada.
- `tests/Feature/Relatorios/VisaoGeralOperacaoServiceTest.php`
  - cada KPI com cenário montado via factories;
  - rascunhos (sem `protocoled_at`) ignorados;
  - SLA: decisão dentro/fora do prazo; decisão sem `analysis_due_at` fora do denominador;
  - aging por faixa incluindo `sem_prazo`;
  - cenário vazio (zero em tudo, sem divisão por zero).
- Ajuste dos testes existentes do dashboard de gestão (remoção dos KPIs admin).

## Fora de escopo (explícito)

- Filtro por setor (depende de roteamento SEDUR).
- Mapas/território, cache/materialização de agregações (volumes atuais não justificam).
- Alterações na Fila de trabalho / Caixa do setor (continuam como estão).
- Painel de Administração (P2 — os KPIs admin são simplesmente removidos).
- Indicadores por setor, SLA de BAP, saturação oficial (P3 — bloqueios externos).

## Critérios de pronto

- Todos os testes novos e ajustados passando (`php artisan test --compact` nos arquivos tocados).
- `vendor/bin/pint --dirty --format agent` sem pendências.
- Home de gestão renderiza com dados reais do banco (sem nenhum valor simulado/hardcoded).
- Analista loga e cai na Minha Mesa; gestor loga e cai na Visão Geral.
- Nenhum KPI administrativo na home de gestão.
