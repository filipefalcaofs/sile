# Indicadores gerenciais 3/3 — Sala de Controle

> **Para agentes:** usar `superpowers:subagent-driven-development` ou `superpowers:executing-plans`, tarefa a tarefa. TDD estrito: teste falhando antes de qualquer código de produção.

**Spec:** `docs/superpowers/specs/2026-10-03-indicadores-benchmark-nacional-design.md`
**Depende de:** planos 1 e 2. O plano 1 entrega `TempoViabilidadeService`, `GargalosService` e `Percentis`; o plano 2 entrega `BenchmarkRankingService`.

**Objetivo:** reunir numa tela executiva o desempenho de Salvador agora, a referência externa, a distância entre os dois com a ressalva metodológica visível no mesmo bloco, a evolução desde 2019 e os principais gargalos.

**Stack:** Laravel 13 / PHP 8.5, PHPUnit 12, Inertia v3 + React 19, ECharts.

## Restrições globais

- A tela **não calcula nada**: consome os serviços dos planos 1 e 2. Nenhuma regra de agregação é reescrita aqui.
- A distância para a referência compara sempre a mesma grandeza — horas úteis de viabilidade — nos dois lados.
- A ressalva metodológica fica **no mesmo bloco** do número, não em rodapé nem em tooltip.
- Sem dado, o bloco informa a ausência e o motivo. Zero nunca substitui ausência.
- Interna, atrás de `consultar-relatorios`. Nenhuma rota do portal é tocada.
- `vendor/bin/pint --dirty --format agent` antes de cada commit com PHP.
- Commits em português, sem coautoria nem menção a ferramenta ou IA.
- Mudança em TSX exige `npm run build` e commit de `public/build`.

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `app/Services/Relatorios/DistanciaBenchmarkService.php` (criar) | compara telemetria interna com a referência |
| `app/Services/Relatorios/SalaDeControleService.php` (criar) | compõe os blocos num único payload |
| `app/Http/Controllers/Gestao/SalaDeControleController.php` (criar) | rota |
| `routes/gestao.php` (alterar) | rota `gestao.sala-de-controle` |
| `resources/js/pages/gestao/sala-de-controle.tsx` (criar) | tela |
| `resources/js/navigation/gestao-nav.ts`, `gestao-nav-icons.tsx` (alterar) | item de menu e ícone exclusivo |

---

### Tarefa 1: Distância para a referência (CA-DT-01 a CA-DT-03)

**Arquivos:** `app/Services/Relatorios/DistanciaBenchmarkService.php`, teste `tests/Feature/Relatorios/DistanciaBenchmarkServiceTest.php`.

- [ ] Testes que falham, um CA por teste: com telemetria interna e competência de referência ingerida, o serviço devolve tempo interno, tempo de referência, diferença absoluta, diferença relativa e a competência usada (CA-DT-01); sem competência ingerida, devolve ausência com motivo e **não** calcula diferença (CA-DT-02); os dois lados vêm em horas úteis de viabilidade, e o teste falha se o lado interno for lido de qualquer outra coluna (CA-DT-03); referência igual a zero não produz divisão por zero.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=DistanciaBenchmarkService`.
- [ ] Implementar: lado interno vem de `TempoViabilidadeService` sobre o período pedido; lado de referência vem da métrica de Salvador na competência mais recente com ingestão bem-sucedida. Devolver array com `interno`, `referencia`, `competencia`, `diferenca_horas`, `diferenca_percentual` e `ressalva` — o texto que identifica as duas réguas, vindo do serviço e não do componente, para que a tela não possa renderizar o número sem ele.
- [ ] Verificar o verde e commitar: `feat: calcula distancia entre telemetria e referencia nacional`.

### Tarefa 2: Composição do payload

**Arquivos:** `app/Services/Relatorios/SalaDeControleService.php`, teste `tests/Feature/Relatorios/SalaDeControleServiceTest.php`.

- [ ] Testes que falham: o serviço devolve os cinco blocos — tempo, volume e automação, distância, evolução e gargalos; cada bloco ausente vem com motivo próprio, sem derrubar os demais; falha do benchmark não impede os blocos internos de responder.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=SalaDeControleService`.
- [ ] Implementar como composição pura dos serviços já existentes: `TempoViabilidadeService`, `IndicadoresViabilidadeService` (volume e taxa de automação, já entregue no EP15), `DistanciaBenchmarkService`, `BenchmarkRankingService::serieSalvador` e `GargalosService`. Cada bloco é montado em isolamento, de modo que a indisponibilidade de um não contamine os outros.
- [ ] Verificar o verde e commitar: `feat: compoe payload da sala de controle`.

### Tarefa 3: Rota, tela e menu (CA-UI-03 a CA-UI-07)

**Arquivos:** `app/Http/Controllers/Gestao/SalaDeControleController.php`, `routes/gestao.php`, `resources/js/pages/gestao/sala-de-controle.tsx`, `resources/js/navigation/gestao-nav.ts`, `resources/js/navigation/gestao-nav-icons.tsx`, testes `tests/Feature/Relatorios/SalaDeControleTelaTest.php` e `resources/js/navigation/gestao-nav.test.ts`.

- [ ] Testes que falham: a rota entrega os cinco blocos num único carregamento (CA-UI-03); sem `consultar-relatorios` responde 403 (CA-UI-04); o item aparece no grupo Indicadores e `assertGestaoNavHealth` continua verde (CA-UI-05); o bloco de distância entrega a ressalva junto do número, e o teste do componente verifica que os dois são renderizados no mesmo bloco (CA-UI-06); sem dado de benchmark, a tela mostra o estado vazio com o motivo (CA-UI-07).
- [ ] Verificar o vermelho: `php artisan test --compact --filter=SalaDeControleTela` e `npx vitest run resources/js/navigation/gestao-nav.test.ts`.
- [ ] Rota `gestao/sala-de-controle` com middleware `permission:consultar-relatorios`, seguindo o padrão das rotas irmãs.
- [ ] Item no grupo **Indicadores** de `gestao-nav.ts`, posicionado como **primeiro do grupo** por ser o mais usado no dia a dia pela gestão, com nome "Sala de Controle", href `/gestao/sala-de-controle`, permissão `consultar-relatorios` e **ícone exclusivo novo** em `gestao-nav-icons.tsx`.
- [ ] Tela com os blocos na ordem: tempo (média e P90 em destaque, demais percentis ao lado), automação e volume, distância para a referência com a ressalva na mesma moldura, evolução desde 2019 com a quebra metodológica de dezembro de 2025 sinalizada, e gargalos em barras horizontais. Gráficos pelo wrapper de chart existente. Layout em grade responsiva, legível em projeção.
- [ ] `npm run build` e commitar `public/build`.
- [ ] Verificar o verde e commitar: `feat: adiciona sala de controle executiva`.

### Tarefa 4: Fechamento

- [ ] Registrar em `.planning/STATE.md`: camada de indicadores gerenciais concluída, com a separação das três camadas de medição e o que depende de ingestão para aparecer.
- [ ] Perguntar ao usuário antes de rodar a suíte inteira.
- [ ] `php artisan test --compact`, `npx vitest run` e `vendor/bin/pint --dirty --format agent` limpo.
