# Indicadores gerenciais 1/3 — Tempo de viabilidade em régua federal, percentis e gargalos

> **Para agentes:** usar `superpowers:subagent-driven-development` ou `superpowers:executing-plans`, tarefa a tarefa. TDD estrito: teste falhando antes de qualquer código de produção.

**Spec:** `docs/superpowers/specs/2026-10-03-indicadores-benchmark-nacional-design.md`

**Objetivo:** fazer o VIABILIZA medir o tempo de viabilidade pela mesma régua que o governo federal usa — horas úteis, sem fins de semana, feriados nem o tempo em que o processo está com o cidadão — e expor média, P50, P75, P90, P95, P99 e máximo, mais a decomposição dos gargalos. Sem isso, nenhuma comparação externa é legítima.

**Stack:** Laravel 13 / PHP 8.5, PHPUnit 12, Inertia v3 + React 19, ECharts.

## Restrições globais

- Nenhuma tabela de eventos nova. A trilha é `viability_request_transitions`; a decisão é `viability_decisions.decided_at`; o tempo com o cidadão é `analysis_pendencies.created_at` / `responded_at`.
- Coluna nova em `viability_requests`: `viabilidade_horas_uteis`, **fora do `fillable`**.
- Percentis em PHP, nunca em SQL: `percentile_cont` não existe no SQLite da suíte.
- Conjunto vazio devolve estrutura nula. Zero nunca substitui ausência de dado.
- `vendor/bin/pint --dirty --format agent` antes de cada commit com PHP.
- Commits em português, sem coautoria nem menção a ferramenta ou IA.
- Rodar só os testes da tarefa a cada passo; a suíte inteira na última tarefa, depois de perguntar ao usuário.
- Mudança em TSX exige `npm run build` e commit de `public/build`.

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/*_add_viabilidade_horas_uteis_to_viability_requests_table.php` (criar) | coluna da métrica equivalente |
| `app/Models/ViabilityRequest.php` (alterar) | cast da coluna nova |
| `app/Support/Metricas/Percentis.php` (criar) | value object de distribuição, compartilhado com o plano 2 |
| `app/Services/Relatorios/TempoViabilidadeCalculator.php` (criar) | horas úteis de um processo, régua federal |
| `app/Services/Relatorios/TempoViabilidadeService.php` (criar) | percentis por período |
| `app/Services/Relatorios/GargalosService.php` (criar) | horas úteis por faixa, derivadas das transições |
| `app/Console/Commands/RecalcularTempoViabilidade.php` (criar) | backfill dos processos já decididos |
| `app/Services/Decisao/*` (alterar) | gravar a métrica no momento da decisão |
| `app/Http/Controllers/Gestao/RelatorioController.php` (alterar) | novos blocos em `tempo` |
| `resources/js/pages/gestao/relatorios/tempo.tsx` (alterar) | bloco de percentis e de gargalos |

---

### Tarefa 1: Value object de percentis

**Arquivos:** `app/Support/Metricas/Percentis.php`, teste `tests/Unit/Metricas/PercentisTest.php`.

- [ ] Testes que falham, um critério por teste: conjunto conhecido de 20 valores devolve média, P50, P75, P90, P95, P99 e máximo conferidos à mão com interpolação linear (CA-PC-01); conjunto vazio devolve `n = 0` e todos os percentis `null`, sem divisão por zero (CA-PC-02); conjunto de um elemento repete o valor em todos os percentis (CA-PC-03); embaralhar a entrada não muda o resultado (CA-PC-04).
- [ ] Verificar o vermelho: `php artisan test --compact --filter=Percentis`.
- [ ] Implementar `final readonly class Percentis` com `public static function de(array $valores): self`, propriedades `int $n`, `?float $media`, `?float $p50`, `?float $p75`, `?float $p90`, `?float $p95`, `?float $p99`, `?float $maximo`, e `toArray()`. Interpolação linear sobre o vetor ordenado: posição `k = (n - 1) * p / 100`, valor `v[floor(k)] + (v[ceil(k)] - v[floor(k)]) * (k - floor(k))`.
- [ ] Verificar o verde: `php artisan test --compact --filter=Percentis`.
- [ ] Commit: `feat: adiciona value object de percentis para indicadores`.

### Tarefa 2: Coluna da métrica equivalente

**Arquivos:** migration, `app/Models/ViabilityRequest.php`, teste `tests/Feature/Relatorios/TempoViabilidadeSchemaTest.php`.

- [ ] Teste que falha: `forceFill(['viabilidade_horas_uteis' => 12.5])` persiste e `fresh()` devolve float; a coluna não é preenchível por `create()`.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=TempoViabilidadeSchema`.
- [ ] Migration aditiva: `decimal('viabilidade_horas_uteis', 10, 2)->nullable()->index()`. Índice porque a coluna é a base de todos os percentis por período.
- [ ] Cast `'viabilidade_horas_uteis' => 'float'` no método `casts()`, seguindo a convenção dos casts já existentes no model.
- [ ] Verificar o verde e commitar: `feat: adiciona coluna de tempo de viabilidade em horas uteis`.

### Tarefa 3: Cálculo das horas úteis na régua federal (CA-TI-01 a CA-TI-06)

**Arquivos:** `app/Services/Relatorios/TempoViabilidadeCalculator.php`, teste `tests/Feature/Relatorios/TempoViabilidadeCalculatorTest.php`.

- [ ] Testes que falham, com `Carbon::setTestNow` e as factories existentes, um CA por teste: protocolo sexta 17h e decisão segunda 10h devolve 17 horas, e não as 65 de relógio (CA-TI-01); feriado ativo no meio não conta (CA-TI-02); pendência aberta e respondida dentro do intervalo é descontada, e o tempo após a resposta volta a contar (CA-TI-03); pendência ainda aberta na decisão desconta até a decisão (CA-TI-04); duas pendências sobrepostas descontam o intervalo uma vez só (CA-TI-05); processo sem decisão devolve `null` (CA-TI-06).
- [ ] Verificar o vermelho: `php artisan test --compact --filter=TempoViabilidadeCalculator`.
- [ ] Implementar `horasUteis(ViabilityRequest $request): ?float`: sem `protocoled_at` ou sem decisão, devolve `null`; caso contrário calcula `businessDurationBetween(protocoled_at, decided_at)` e subtrai a soma dos intervalos de pendência, cada um também medido em minutos úteis. Antes de somar, **mesclar os intervalos de pendência sobrepostos** — ordenar por início e fundir quando o próximo começa antes do fim do anterior. Converter minutos para horas com duas casas. Resultado nunca negativo.
- [ ] Injetar `BusinessDeadlineCalculator` pelo construtor, para herdar o `HolidayProvider` do container.
- [ ] Verificar o verde e commitar: `feat: calcula tempo de viabilidade em horas uteis na regua federal`.

### Tarefa 4: Gravação na decisão e comando de recálculo (CA-TI-07)

**Arquivos:** serviço que registra a decisão em `app/Services/`, `app/Console/Commands/RecalcularTempoViabilidade.php`, testes `tests/Feature/Relatorios/TempoViabilidadeGravacaoTest.php`.

- [ ] Localizar o serviço que cria `ViabilityDecision` (procurar por `viability_decisions` e `decided_at` em `app/Services`) e usá-lo como ponto de gravação. Não criar serviço novo.
- [ ] Testes que falham: decidir um processo preenche `viabilidade_horas_uteis`; o comando `viabilidade:recalcular-horas-uteis` preenche processos decididos antes da coluna existir; rodar o comando duas vezes produz o mesmo resultado (CA-TI-07); processo sem decisão continua com `null`.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=TempoViabilidadeGravacao`.
- [ ] Implementar a gravação com `forceFill` logo após a decisão, fora do caminho crítico de validação. Implementar o comando com `chunkById` sobre processos com decisão, aceitando `--desde=` opcional no formato `YYYY-MM-DD`.
- [ ] Verificar o verde e commitar: `feat: grava tempo de viabilidade na decisao e adiciona recalculo`.

### Tarefa 5: Serviço de percentis por período

**Arquivos:** `app/Services/Relatorios/TempoViabilidadeService.php`, teste `tests/Feature/Relatorios/TempoViabilidadeServiceTest.php`.

- [ ] Testes que falham: `porPeriodo(ReportFilters)` devolve `Percentis` sobre os processos decididos no intervalo; processos fora do intervalo não entram; processos sem valor não entram; período sem processos devolve a estrutura nula; o recorte por `analysis_category` separa expresso de semi-expresso; o total por categoria bate com o geral.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=TempoViabilidadeService`.
- [ ] Implementar seguindo o padrão dos serviços irmãos em `app/Services/Relatorios/`: classe sem estado, recebe `ReportFilters`, usa `from()` e `to()` para o recorte, devolve array. Buscar só a coluna `viabilidade_horas_uteis` com `pluck`, e entregar ao `Percentis`.
- [ ] Verificar o verde e commitar: `feat: adiciona percentis de tempo de viabilidade por periodo`.

### Tarefa 6: Gargalos derivados das transições (CA-GA-01 a CA-GA-03)

**Arquivos:** `app/Services/Relatorios/GargalosService.php`, teste `tests/Feature/Relatorios/GargalosServiceTest.php`.

- [ ] Testes que falham: processo que passou por análise manual e por pendência devolve as duas faixas, e a soma das horas bate com o tempo total com tolerância de um minuto (CA-GA-01); processo que nunca entrou em `em_analise` não contribui para essa faixa (CA-GA-02); as faixas vêm ordenadas por participação decrescente, cada uma com horas e percentual (CA-GA-03); período sem processos devolve lista vazia, não faixas zeradas.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=GargalosService`.
- [ ] Implementar: para cada processo do recorte, percorrer `viability_request_transitions` ordenadas por `created_at`, formando intervalos entre transições consecutivas, e medir cada intervalo com `businessDurationBetween`. Mapear o `to_status` do intervalo para a faixa: `em_analise` para análise manual, `em_pendencia` para pendência documental, vistoria para a própria faixa, `aguardando_bap` para aguardando BAP. Somar as quedas do expresso por `expresso_quedas.tipo_gatilho` como faixa própria. O último intervalo vai da última transição até `decided_at`.
- [ ] Verificar o verde e commitar: `feat: deriva gargalos de tempo a partir das transicoes`.

### Tarefa 7: Exposição na tela de tempo (CA-UI-01, CA-UI-04, CA-UI-07)

**Arquivos:** `app/Http/Controllers/Gestao/RelatorioController.php`, `resources/js/pages/gestao/relatorios/tempo.tsx`, teste `tests/Feature/Relatorios/RelatorioControllerTest.php`.

- [ ] Testes que falham, no estilo do `DashboardKpisTest` existente, com `AssertableInertia`: a rota `gestao.relatorios.tempo` passa a entregar as props `percentis` e `gargalos`; sem `consultar-relatorios` responde 403 (CA-UI-04); período sem dado entrega a estrutura nula e a tela não recebe zero (CA-UI-07).
- [ ] Verificar o vermelho: `php artisan test --compact --filter=RelatorioController`.
- [ ] Injetar os dois serviços no construtor do controller, seguindo o padrão dos que já estão lá, e acrescentar as props no `Inertia::render` do método `tempo`. Não alterar as props existentes.
- [ ] Na `tempo.tsx`, acrescentar um bloco de percentis (cartões com média, P50, P75, P90, P95, P99 e máximo, em formato de horas) e um bloco de gargalos com barras horizontais via o wrapper de chart já existente em `resources/js/components/ui/chart/chart.tsx`. Estado vazio explícito com o motivo quando `percentis.n` for zero.
- [ ] `npm run build` e commitar `public/build` junto.
- [ ] Verificar o verde e commitar: `feat: exibe percentis e gargalos na tela de tempo`.

### Tarefa 8: Fechamento

- [ ] Registrar em `.planning/STATE.md`: métrica equivalente à régua federal disponível, com a definição citada, e a dependência dos planos 2 e 3.
- [ ] Perguntar ao usuário antes de rodar a suíte inteira.
- [ ] `php artisan test --compact` e `vendor/bin/pint --dirty --format agent` limpo.
