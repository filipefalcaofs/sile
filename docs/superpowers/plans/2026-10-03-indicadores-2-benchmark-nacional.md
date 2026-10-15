# Indicadores gerenciais 2/3 — Benchmark nacional e ranking das capitais

> **Para agentes:** usar `superpowers:subagent-driven-development` ou `superpowers:executing-plans`, tarefa a tarefa. TDD estrito: teste falhando antes de qualquer código de produção.

**Spec:** `docs/superpowers/specs/2026-10-03-indicadores-benchmark-nacional-design.md`
**Depende de:** plano 1 (`2026-10-03-indicadores-1-tempo-viabilidade.md`), que entrega o value object `Percentis`.

**Objetivo:** ingerir os microdados oficiais de tempo de viabilidade da REDESIM, de 2019 em diante, agregá-los por município e competência, e expor a posição de Salvador no país, o ranking das 27 capitais e a evolução histórica — tudo pela mesma régua, com lacuna explícita onde o dado falta.

**Stack:** Laravel 13 / PHP 8.5, PHPUnit 12, Inertia v3 + React 19, ECharts. Leitura de XLSX com `openspout/openspout` (`^4.0`), **já presente no `composer.json`** — é leitor em streaming, que é exatamente o que o volume exige. Nenhuma dependência nova.

## Restrições globais

- Fonte única: `https://estatistica.redesim.gov.br/tempos-abertura-redesim/exportar/solicitacoes/{UF}?ano={ano}&mes={mes}`. O XLSX do MEMP e o boletim em PDF **não** são consumidos.
- O arquivo baixado é processado e descartado. Microdado não persiste.
- O agregado por CNAE é gravado **apenas** para Salvador e para o total Brasil.
- Nenhum valor é interpolado, estimado ou herdado de outra competência.
- Toda chamada externa fora do caminho de requisição, dentro de comando ou job.
- `vendor/bin/pint --dirty --format agent` antes de cada commit com PHP.
- Commits em português, sem coautoria nem menção a ferramenta ou IA.
- `tests/TestCase.php` desliga integrações HTTP por padrão: os testes do cliente ligam o interruptor e usam `Http::fake`.
- Rodar só os testes da tarefa a cada passo; a suíte inteira na última tarefa, depois de perguntar ao usuário.

## Fatos da fonte (medidos em 2026-10-03, usar como fixture)

- Cabeçalho na **linha 2** da aba `dados`; a linha 1 é o agrupamento. A aba `dicionario` descreve os campos.
- Colunas usadas: `MUNICÍPIO`, `UF`, `QTDE.  HH VIABILIDADE END`, `ATIVIDADE  ECONÔMICA PRINCIPAL`. As duas últimas têm **espaço duplo** no nome.
- Município vem em maiúsculas e sem acento (`SALVADOR`, `BARREIRAS`). **Não há código IBGE.**
- Definição oficial de `QTDE.  HH VIABILIDADE END`: horas úteis entre o início e o fim da análise de viabilidade de endereço, desconsiderando fins de semana e feriados.
- Valores de referência para o teste de ponta a ponta — Salvador/BA em 08/2026: n = 665, média 23,24 h, P50 7,00 h, P75 44,00 h, P90 73,00 h, P95 97,00 h, máximo 101,02 h.
- `/periodos-disponiveis` devolve a série de 2019 a 2026.

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/*_create_benchmark_ingestions_table.php` (criar) | controle e evidência de ingestão |
| `database/migrations/*_create_benchmark_municipality_metrics_table.php` (criar) | agregado por município e competência |
| `database/migrations/*_create_benchmark_activity_metrics_table.php` (criar) | agregado por CNAE, só Salvador e Brasil |
| `app/Models/BenchmarkIngestion.php`, `BenchmarkMunicipalityMetric.php`, `BenchmarkActivityMetric.php` (criar) | models e factories |
| `app/Support/Capitais.php` (criar) | lista fixa das 27 capitais por UF e nome normalizado |
| `app/Services/Benchmark/RedesimClient.php` (criar) | baixa o XLSX da competência |
| `app/Services/Benchmark/RedesimSolicitacaoParser.php` (criar) | XLSX para linhas normalizadas |
| `app/Services/Benchmark/BenchmarkIngestionService.php` (criar) | orquestra, agrega, grava, audita |
| `app/Services/Benchmark/BenchmarkRankingService.php` (criar) | posição, capitais, série histórica |
| `app/Console/Commands/ImportarBenchmarkRedesim.php` (criar) | comando e agendamento |
| `config/viabiliza.php`, `database/seeders/ParameterSeeder.php` (alterar) | interruptor e URL |
| `app/Http/Controllers/Gestao/BenchmarkController.php` (criar) | tela |
| `routes/gestao.php`, `resources/js/navigation/gestao-nav.ts`, `gestao-nav-icons.tsx` (alterar) | rota, item de menu e ícone |
| `resources/js/pages/gestao/relatorios/benchmark.tsx` (criar) | tela |
| `tests/Fixtures/Benchmark/redesim-ba-202608.xlsx` (criar) | recorte real do arquivo da Bahia |

---

### Tarefa 1: Esquema, models e lista de capitais

**Arquivos:** as três migrations, os três models com factories, `app/Support/Capitais.php`, teste `tests/Feature/Benchmark/BenchmarkSchemaTest.php`.

- [ ] Teste que falha: as três tabelas aceitam escrita pelas factories; `benchmark_ingestions` tem unicidade em (`uf`, `ano`, `mes`); `benchmark_municipality_metrics` tem unicidade em (`uf`, `municipio`, `ano`, `mes`); `Capitais::todas()` devolve 27 pares e `Capitais::ehCapital('BA', 'SALVADOR')` é verdadeiro.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=BenchmarkSchema`.
- [ ] `benchmark_ingestions`: `uf`, `ano`, `mes`, `status` (`sucesso` ou `falha`), `url`, `bytes`, `hash`, `linhas_lidas`, `linhas_descartadas`, `motivo` nulo, timestamps, índice único em (`uf`, `ano`, `mes`).
- [ ] `benchmark_municipality_metrics`: `uf`, `municipio`, `ano`, `mes`, `n`, `media`, `p50`, `p75`, `p90`, `p95`, `p99`, `maximo`, todos decimais nulos, índice único em (`uf`, `municipio`, `ano`, `mes`) e índice em (`ano`, `mes`).
- [ ] `benchmark_activity_metrics`: `escopo` (`salvador` ou `brasil`), `cnae`, `ano`, `mes`, `n`, `media`, `p50`, `p90`, índice único em (`escopo`, `cnae`, `ano`, `mes`).
- [ ] `Capitais`: constante com os 27 pares `['BA' => 'SALVADOR', 'SP' => 'SAO PAULO', 'PA' => 'BELEM', ...]`, nomes em maiúsculas sem acento, mais `todas()`, `ehCapital(string $uf, string $municipio): bool` e `nomeNormalizado(string $valor): string` (maiúsculas, sem acento, espaços colapsados).
- [ ] Verificar o verde e commitar: `feat: cria esquema de benchmark nacional de viabilidade`.

### Tarefa 2: Interruptor e URL no catálogo de parâmetros (CA-BI-06)

**Arquivos:** `config/viabiliza.php`, `database/seeders/ParameterSeeder.php`, `tests/TestCase.php`, teste `tests/Feature/Benchmark/BenchmarkParametrosTest.php`.

- [ ] Teste que falha: `features.benchmark_redesim` existe no catálogo com default ligado; `config('viabiliza.integrations.redesim_estatistica.base_url')` aponta para `https://estatistica.redesim.gov.br/tempos-abertura-redesim`.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=BenchmarkParametros`.
- [ ] `config/viabiliza.php`: `features.benchmark_redesim => true` e bloco `integrations.redesim_estatistica` com `base_url`, `timeout => 120` e `user_agent`. Timeout alto porque São Paulo baixa 16 MB.
- [ ] `ParameterSeeder`: as duas entradas, com descrição citando a origem do dado e a definição de horas úteis.
- [ ] `tests/TestCase.php`: desligar `viabiliza.features.benchmark_redesim` por padrão, como já é feito com as outras integrações.
- [ ] Verificar o verde e commitar: `feat: parametriza integracao de benchmark da redesim`.

### Tarefa 3: Cliente HTTP da REDESIM (CA-BI-05, CA-BI-06)

**Arquivos:** `app/Services/Benchmark/RedesimClient.php`, teste `tests/Feature/Benchmark/RedesimClientTest.php`.

- [ ] Testes que falham, com `Http::fake` e o interruptor ligado: competência válida devolve o conteúdo binário, os bytes e o hash; HTTP 500 devolve falha com motivo, sem lançar para o chamador (CA-BI-05); timeout devolve falha com motivo; interruptor desligado não faz chamada e devolve falha (CA-BI-06, verificar com `Http::assertNothingSent`); a URL montada tem a UF no caminho e `ano` e `mes` na query.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=RedesimClient`.
- [ ] Implementar `baixar(string $uf, int $ano, int $mes): RedesimDownload`, com `RedesimDownload` readonly carregando `sucesso`, `conteudo`, `bytes`, `hash` (`sha256`), `url` e `motivo`. Timeout do config, `connectTimeout(10)`, sem retry.
- [ ] Verificar o verde e commitar: `feat: adiciona cliente de microdados da redesim`.

### Tarefa 4: Parser do XLSX (CA-BI-02, CA-BI-03, CA-BI-09)

**Arquivos:** `app/Services/Benchmark/RedesimSolicitacaoParser.php`, fixture `tests/Fixtures/Benchmark/redesim-ba-202608.xlsx`, teste `tests/Feature/Benchmark/RedesimSolicitacaoParserTest.php`.

- [ ] Gerar a fixture a partir do arquivo real da Bahia de agosto de 2026, mantendo as duas linhas de cabeçalho e um recorte de linhas que inclua Salvador e ao menos dois outros municípios, mais uma linha com valor não numérico. Documentar no teste a origem e a data da captura.
- [ ] Testes que falham: o parser lê o cabeçalho da linha 2 e devolve linhas com município normalizado, UF, horas e CNAE; arquivo com as colunas em ordem diferente produz o mesmo resultado (CA-BI-02); linha com valor não numérico em `QTDE.  HH VIABILIDADE END` é descartada e contada (CA-BI-03); nome de município com espaço extra ou acento é normalizado (CA-BI-09); cabeçalho com espaço duplo é reconhecido.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=RedesimSolicitacaoParser`.
- [ ] Implementar com o `Reader` XLSX do `openspout/openspout`, linha a linha, sem carregar a planilha inteira em memória — São Paulo tem 16 MB por competência. Ler apenas a aba `dados`, ignorar a linha 1 (agrupamento) e montar o mapa de colunas a partir da linha 2. Casar colunas por cabeçalho normalizado (maiúsculas, sem acento, espaços colapsados), nunca por índice. Devolver um `Generator` de arrays com `municipio`, `uf`, `horas` e `cnae`, mais um contador de descartadas.
- [ ] Verificar o verde e commitar: `feat: adiciona parser de microdados da redesim`.

### Tarefa 5: Ingestão idempotente (CA-BI-01, CA-BI-04, CA-BI-07, CA-BI-08)

**Arquivos:** `app/Services/Benchmark/BenchmarkIngestionService.php`, teste `tests/Feature/Benchmark/BenchmarkIngestionServiceTest.php`.

- [ ] Testes que falham: ingerir a fixture agrega por município com n, média e percentis, conferidos contra os valores de Salvador da seção de fatos (CA-BI-01); reprocessar a mesma competência substitui o agregado sem duplicar (CA-BI-04); o agregado por CNAE é gravado para Salvador e para o escopo Brasil, e não para os demais municípios (CA-BI-07); a ingestão audita `benchmark/ingestao` com UF, competência, linhas lidas, descartadas e hash (CA-BI-08); download que falhou grava a competência como falha e não grava agregado parcial (CA-BI-05).
- [ ] Verificar o vermelho: `php artisan test --compact --filter=BenchmarkIngestionService`.
- [ ] Implementar `ingerir(string $uf, int $ano, int $mes): BenchmarkIngestion`: chama o cliente, alimenta o parser, acumula as horas por município num mapa, calcula `Percentis::de()` por município, grava com `updateOrCreate` na chave única, acumula o CNAE apenas quando o município é Salvador ou para o total Brasil, e registra a ingestão. Tudo dentro de uma transação por competência, para que falha no meio não deixe agregado pela metade. Auditoria com `AuditService`, `logName: 'benchmark'`, `event: 'ingestao'`.
- [ ] Verificar o verde e commitar: `feat: ingere e agrega benchmark da redesim por competencia`.

### Tarefa 6: Comando e agendamento

**Arquivos:** `app/Console/Commands/ImportarBenchmarkRedesim.php`, `routes/console.php`, teste `tests/Feature/Benchmark/ImportarBenchmarkRedesimCommandTest.php`.

- [ ] Testes que falham: `benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA` ingere uma UF; sem `--uf`, percorre as 27; `--desde=2019-01` percorre todas as competências do intervalo até a última fechada; o comando devolve código de saída diferente de zero quando alguma competência falha, listando quais; competências já ingeridas com sucesso são puladas, salvo com `--forcar`.
- [ ] Verificar o vermelho: `php artisan test --compact --filter=ImportarBenchmarkRedesim`.
- [ ] Implementar com `php artisan make:command --no-interaction`. Saída tabular por competência com UF, linhas lidas, descartadas e status. Agendar mensalmente em `routes/console.php` para a competência fechada, respeitando o interruptor.
- [ ] Verificar o verde e commitar: `feat: adiciona comando de importacao do benchmark nacional`.

### Tarefa 7: Ranking, capitais e série histórica (CA-RK-01 a CA-RK-06)

**Arquivos:** `app/Services/Benchmark/BenchmarkRankingService.php`, teste `tests/Feature/Benchmark/BenchmarkRankingServiceTest.php`.

- [ ] Testes que falham, um CA por teste: com as 27 UFs ingeridas, a posição de Salvador é o número de municípios com tempo estritamente menor mais um (CA-RK-01); empate produz a mesma posição para os empatados (CA-RK-02); competência com UF faltando não devolve posição e devolve a lista das UFs ausentes (CA-RK-03); o ranking das capitais usa só as 27 e ignora os demais municípios (CA-RK-04); capital sem dado aparece como lacuna, sem zero (CA-RK-05); a série de Salvador devolve um ponto por competência ingerida, em ordem cronológica, sem preencher buracos (CA-RK-06).
- [ ] Verificar o vermelho: `php artisan test --compact --filter=BenchmarkRankingService`.
- [ ] Implementar três métodos: `posicaoNacional(int $ano, int $mes)`, `rankingCapitais(int $ano, int $mes)` e `serieSalvador(?int $anoInicial)`. A cobertura é checada contra `benchmark_ingestions` com status de sucesso: menos de 27 UFs na competência significa cobertura incompleta e posição não exibida.
- [ ] Verificar o verde e commitar: `feat: calcula posicao nacional e ranking de capitais`.

### Tarefa 8: Tela de benchmark (CA-UI-02, CA-UI-04, CA-UI-05, CA-UI-07)

**Arquivos:** `app/Http/Controllers/Gestao/BenchmarkController.php`, `routes/gestao.php`, `resources/js/pages/gestao/relatorios/benchmark.tsx`, `resources/js/navigation/gestao-nav.ts`, `resources/js/navigation/gestao-nav-icons.tsx`, testes `tests/Feature/Benchmark/BenchmarkTelaTest.php` e `resources/js/navigation/gestao-nav.test.ts`.

- [ ] Testes que falham: a rota `gestao.relatorios.benchmark` entrega posição, ranking de capitais e série (CA-UI-02); sem `consultar-relatorios` responde 403 (CA-UI-04); sem competência ingerida a tela recebe estado vazio com o motivo, nunca zero (CA-UI-07); o item aparece no grupo Indicadores e `assertGestaoNavHealth` continua verde (CA-UI-05).
- [ ] Verificar o vermelho: `php artisan test --compact --filter=BenchmarkTela` e `npx vitest run resources/js/navigation/gestao-nav.test.ts`.
- [ ] Rota sob o prefixo `gestao/relatorios` com middleware `permission:consultar-relatorios`, seguindo exatamente o padrão das rotas irmãs.
- [ ] Item no grupo **Indicadores** de `gestao-nav.ts` com nome "Benchmark nacional", href `/gestao/relatorios/benchmark`, permissão `consultar-relatorios` e **ícone exclusivo novo** em `gestao-nav-icons.tsx` — não reaproveitar ícone de outro item.
- [ ] Tela com três blocos: posição de Salvador na competência mais recente com cobertura completa, ranking das 27 capitais com Salvador destacada e lacunas marcadas, e série histórica desde 2019 com a quebra metodológica de dezembro de 2025 sinalizada. Gráficos pelo wrapper de chart existente.
- [ ] `npm run build` e commitar `public/build`.
- [ ] Verificar o verde e commitar: `feat: adiciona tela de benchmark nacional de viabilidade`.

### Tarefa 9: Fechamento

- [ ] Documentar a integração em `docs/integracoes/`, no padrão dos arquivos que já estão lá: endpoints medidos, formato do arquivo, definição oficial de horas úteis, limites conhecidos e o fato de a API não ser um contrato público documentado.
- [ ] Registrar em `.planning/STATE.md`: benchmark ingerido, cobertura obtida e competências que falharam.
- [ ] Perguntar ao usuário antes de rodar a suíte inteira e antes de disparar o backfill de 2019.
- [ ] `php artisan test --compact` e `vendor/bin/pint --dirty --format agent` limpo.
