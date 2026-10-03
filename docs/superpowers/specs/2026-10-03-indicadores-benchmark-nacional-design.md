# Spec — Camada de indicadores gerenciais e benchmark nacional de viabilidade

Data: 2026-10-03
Relacionado: EP15 (HU-122 a HU-131, HU-145), spec `2026-09-18-dashboards-p0-design.md`, serviços em `app/Services/Relatorios/`.

## 1. Problema

O EP15 entregou indicadores operacionais reais: `IndicadoresViabilidadeService`, `TempoAnaliseService`, `SlaVencimentosService`, `ExpressoQuedaService`, telas sob `gestao/relatorios` e KPIs no painel. Falta a camada **gerencial**, que responde a três perguntas que o conjunto atual não responde:

1. **Qual é a cauda?** Os indicadores de tempo hoje reportam média e recortes operacionais. Média esconde a cauda: o gestor não consegue afirmar "95% das viabilidades saem em menos de X" nem ver que existe um resíduo de processos de horas.
2. **Salvador está bem comparada a quem?** Não existe referência externa no sistema. Toda afirmação de desempenho é autorreferente.
3. **O novo sistema melhorou o quê, exatamente?** Sem a série histórica medida pela régua de fora, o ganho do VIABILIZA é alegação, não medida.

Há ainda um risco que esta spec existe para evitar: **comparar tempos medidos por réguas diferentes**. O tempo que o VIABILIZA mede (relógio interno, da nossa fila) e o tempo que o governo federal publica (horas úteis, descontando o período em que o processo está com o cidadão) não são a mesma grandeza. Exibi-los lado a lado sem equivalência produziria um número bonito e falso.

## 2. Fontes verificadas (medições próprias, 2026-10-03)

Todas as medições abaixo foram feitas contra os serviços ao vivo, sem autenticação, de máquina de desenvolvimento.

### 2.1 API de estatísticas da REDESIM — **fonte primária adotada**

Base: `https://estatistica.redesim.gov.br/tempos-abertura-redesim`

| Item | Medição |
|---|---|
| `/periodos-disponiveis` | HTTP 200, JSON. Série de **2019 a 2026** (2026 até o mês 9) |
| `/exportar/solicitacoes/{UF}?ano=&mes=` | HTTP 200, XLSX, abas `dados` e `dicionario` — **microdados por solicitação** |
| `/tempo-medio-total-abertura/{UF}` | `{"dias":1,"horas":7}` para BA em 08/2026 |
| `/percentual-viabilidade/{UF}` | distribuição em 4 faixas, não tempo médio — **não serve para ranking** |
| Volume BA, 08/2026 | 858 KB, 1.904 solicitações, 171 municípios |
| Volume SP, 08/2026 | 16,1 MB (maior UF); MG 4,5 MB; RJ 1,5 MB |

Campos relevantes da aba `dados` (nomes exatos do cabeçalho, que fica na **linha 2**; a linha 1 é o agrupamento):

`SERIAL`, `DT.INIC. VIABILIDADE`, `HH. INIC. VIABILIDADE`, `DT. FIM VIABILIDADE NOME`, `QTDE.  HH VIABILIDADE NOME`, `DT. FIM VIABILIDADE END`, `QTDE.  HH VIABILIDADE END`, `QTDE.  HH VIABILIDADE TOTAL`, `QTDE HH. TRANSMISSÃO`, `QTDE. HH. LIBERAÇÃO DBE`, `QTDE. HH. RECEBIMENTO DBE`, `QTDE. HORAS DEFERIMENTO`, `MUNICÍPIO`, `UF`, `NAT. JUR.`, `ATIVIDADE  ECONÔMICA PRINCIPAL`, `TIPO DE UNIDADE`.

Atenção aos nomes: `QTDE.  HH VIABILIDADE END` e `ATIVIDADE  ECONÔMICA PRINCIPAL` têm **espaço duplo**. O parser casa por cabeçalho normalizado, nunca por índice fixo.

Definição oficial no dicionário da própria planilha, que é a régua que esta spec adota:

> `QTDE.  HH VIABILIDADE END` — Quantidade de horas úteis entre o início e o fim da análise de viabilidade de ENDEREÇO. Não considera fins de semana ou feriados.

**Salvador/BA, agosto de 2026**, calculado a partir desses microdados: n = 665, média 23,24 h, P50 7,00 h, P75 44,00 h, P90 73,00 h, P95 97,00 h, máximo 101,02 h.

Os CNAEs mais lentos de Salvador no mesmo mês, com pelo menos 10 casos: 6911701 (54,2 h), 5611203 (41,5 h), 5611201 (37,5 h), 4712100 (31,4 h).

O município é identificado por **nome em maiúsculas e sem acento** mais a UF (`SALVADOR`/`BA`). **Não há código IBGE** no arquivo.

### 2.2 Dados abertos do MEMP (XLSX mensal) — **descartado como fonte primária**

| Item | Medição |
|---|---|
| Janela disponível | apenas 4 competências no ar (mai, jun, jul e ago de 2026) — sem série histórica |
| Granularidade | só `Tempo Médio de Abertura` **total**, sem separar viabilidade de registro |
| Salvador, jul/2026 | `cod_ibge 292740`, 20,41 h, posição 1667 de 3099 municípios com valor numérico |
| Qualidade | ago/2026 traz o texto `Em revisão. Será atualizado posteriormente` no lugar do tempo; 2.471 municípios trazem `-` |
| Estabilidade do esquema | `cod_ibge` ora texto ora inteiro; cabeçalho muda entre competências (`UF` presente em ago, ausente em jul) |

### 2.3 Boletim quadrimestral (PDF) — **descartado**

Traz a tabela de capitais com viabilidade, registro e total (Salvador no 2º quadrimestre de 2025: viabilidade 5 h, registro 1 h, 8º lugar). É derivado da mesma base da API, porém arredondado para horas inteiras, limitado às capitais, quadrimestral e com layout tabular instável entre edições. Raspar o PDF seria substituir o dado de origem por uma cópia degradada.

### 2.4 O que já existe no VIABILIZA e não será recriado

| Necessidade do desenho original | Onde já existe |
|---|---|
| `viabilidade_eventos` com timestamps por etapa | `viability_request_transitions` (`from_status`, `to_status`, `actor_user_id`, `created_at`) e `analysis_status_transitions` |
| Marco de decisão | `viability_decisions.decided_at`, `flow`, `outcome` |
| Tempo com o cidadão | `analysis_pendencies.created_at` / `responded_at` |
| Expresso contra manual | `viability_requests.analysis_category` e `expresso_quedas` |
| Horas úteis com feriados | `BusinessDeadlineCalculator::businessDurationBetween` + `HolidayProvider` |
| Gráficos | ECharts via `resources/js/components/ui/chart/chart.tsx` |

## 3. Decisões

1. **Três camadas de medição, nunca misturadas na mesma caixa.**
   - **(A) Telemetria VIABILIZA** — Salvador agora, pelo nosso relógio, em tempo real.
   - **(B) Espelho federal de Salvador** — como a REDESIM mede Salvador, mensal, desde 2019.
   - **(C) Benchmark** — os demais municípios e as capitais, pela mesma régua de (B).

   O ranking compara **(B) com (C)**, porque só aí a régua é idêntica. A validação de que o sistema mede certo compara **(A) com (B)**. O ganho do novo VIABILIZA é a evolução de **(B) ao longo do tempo** — Salvador contra Salvador, metodologia constante.

2. **Nenhuma tabela de eventos nova.** `viability_request_transitions` já é a trilha de etapas com timestamp. Criar `viabilidade_eventos` produziria duas verdades sobre o mesmo processo. Os indicadores derivam das tabelas existentes.

3. **Fonte primária é a API da REDESIM**, pelo export de microdados por UF e competência. O XLSX do MEMP e o boletim em PDF não entram como fonte: o primeiro não separa a viabilidade e só tem 4 meses no ar, o segundo é derivado e arredondado.

4. **Métrica interna equivalente.** Entra `viabilidade_horas_uteis` em `viability_requests`: horas úteis entre o protocolo e a decisão, descontando fins de semana, feriados ativos e todo intervalo em que houve pendência aberta aguardando o cidadão. É a transcrição literal da definição federal de `QTDE.  HH VIABILIDADE END`. Sem ela, a comparação seria inválida.

5. **Só o agregado persiste.** O XLSX é processado em streaming e descartado. Ficam `benchmark_municipality_metrics` (por município e competência) e `benchmark_activity_metrics` (por CNAE e competência, **restrito a Salvador e ao total Brasil**). Guardar o agregado por CNAE de todos os municípios daria cerca de 6 milhões de linhas; restrito, fica em torno de 145 mil.

6. **O agregado por CNAE é gravado desde a primeira ingestão**, embora a tela por atividade fique para depois. O backfill desde 2019 são 27 UFs por 96 competências, com São Paulo a 16 MB cada; incluir o CNAE depois exigiria refazer a carga inteira.

7. **Percentis, não só média.** Média, P50, P75, P90, P95, P99 e máximo, tanto na telemetria interna quanto no benchmark — o benchmark só tem percentil porque a fonte é microdado. O cálculo fica num único value object `Percentis`, compartilhado pelas duas camadas, em PHP e não em SQL, porque `percentile_cont` não existe no SQLite da suíte.

8. **Ingestão idempotente com evidência.** `benchmark_ingestions` guarda UF, ano, mês, URL chamada, bytes recebidos, hash do arquivo, linhas lidas, linhas descartadas, status e instante. Reprocessar a mesma competência substitui o agregado sem duplicar. Competência que falhou fica marcada como falha.

9. **Degradação honesta.** UF ausente numa competência vira lacuna explícita na tela, com a lista do que faltou. A posição no ranking **não é exibida** quando a cobertura da competência está incompleta. Nenhum valor é interpolado, estimado ou herdado do mês anterior.

10. **A Sala de Controle exibe o comparativo direto em destaque, com a ressalva metodológica visível no mesmo bloco.** O cartão de distância traz o tempo do VIABILIZA, o tempo de referência, a diferença, e, na mesma moldura, a competência de referência e a frase que identifica as duas réguas. A ressalva não é nota de rodapé nem tooltip.

11. **Gargalos são derivados, não digitados.** O tempo em cada faixa sai das transições: análise manual (`em_analise`), pendência documental (`em_pendencia`), vistoria, aguardando BAP, e queda do expresso por `expresso_quedas.tipo_gatilho`. Nenhum campo novo para o analista preencher.

12. **Parametrização mínima.** Entram no catálogo existente apenas `features.benchmark_redesim` (booleano, default ligado) e a URL base da integração — exatamente os dois casos que a regra de parametrização prevê. Lista de capitais, nomes de coluna e percentis apurados ficam no código.

13. **Menu sem grupo novo.** Os dois itens novos entram no grupo **Indicadores**, que já existe e hoje tem 6 itens. A tela de percentis **estende a `tempo.tsx` existente** em vez de criar um terceiro item sobre tempo.

14. **Permissão reaproveitada.** Tudo atrás de `consultar-relatorios`, como as demais telas de indicadores. Sem permissão nova.

15. **Sem exposição pública.** A Sala de Controle é interna. Nenhuma rota do portal do cidadão é tocada.

## 4. Onde isso aparece

| Superfície | O que muda |
|---|---|
| `gestao/relatorios/tempo` | Bloco de percentis na régua federal, com os filtros de período |
| `gestao/relatorios/benchmark` (nova) | Posição de Salvador, ranking das capitais, evolução desde 2019, distância para a referência |
| `gestao/sala-de-controle` (nova) | Visão executiva: tempo, automação, volume, distância para a referência, evolução e gargalos |
| Menu, grupo Indicadores | Dois itens novos: Sala de Controle e Benchmark nacional |
| Auditoria | Evento `benchmark/ingestao` com UF, competência, linhas, hash e resultado |
| Agendamento | Importação mensal automática da competência fechada |

## 5. Critérios de aceite (viram testes)

### Métrica interna equivalente

- **CA-TI-01** Processo protocolado numa sexta às 17h e decidido na segunda às 10h registra 17 horas úteis, não as 65 de relógio. Um dia útil vale 24 horas: a régua desconta fins de semana e feriados, não expediente comercial — igual à definição federal.
- **CA-TI-02** Feriado ativo entre protocolo e decisão não conta como tempo.
- **CA-TI-03** Período com pendência aberta aguardando o cidadão é descontado; pendência respondida volta a contar.
- **CA-TI-04** Pendência ainda aberta na data da decisão desconta até a decisão.
- **CA-TI-05** Duas pendências sobrepostas descontam o intervalo uma única vez.
- **CA-TI-06** Processo sem decisão não recebe valor e fica fora dos percentis.
- **CA-TI-07** O comando de recálculo preenche processos já decididos e é idempotente.

### Percentis

- **CA-PC-01** Conjunto conhecido devolve média, P50, P75, P90, P95, P99 e máximo com interpolação linear, conferidos contra valores calculados à mão.
- **CA-PC-02** Conjunto vazio devolve estrutura nula, sem divisão por zero e sem zero travestido de resultado.
- **CA-PC-03** Conjunto de um elemento devolve o mesmo valor em todos os percentis.
- **CA-PC-04** O resultado não depende da ordem de entrada.

### Ingestão do benchmark

- **CA-BI-01** XLSX de uma UF e competência é lido e agregado por município, com n, média e os percentis.
- **CA-BI-02** O parser casa colunas por cabeçalho normalizado — arquivo com as colunas em outra ordem produz o mesmo resultado.
- **CA-BI-03** Linha sem valor numérico de viabilidade de endereço é descartada e contabilizada em `linhas_descartadas`.
- **CA-BI-04** Reprocessar a mesma UF e competência substitui o agregado, sem duplicar linhas.
- **CA-BI-05** HTTP 500 ou timeout marca a competência como falha, com o motivo, sem gravar agregado parcial.
- **CA-BI-06** `features.benchmark_redesim` desligado impede a chamada.
- **CA-BI-07** O agregado por CNAE é gravado para Salvador e para o total Brasil, e não para os demais municípios.
- **CA-BI-08** A ingestão audita `benchmark/ingestao` com UF, competência, linhas lidas, descartadas e hash.
- **CA-BI-09** Município é casado por UF mais nome normalizado em maiúsculas e sem acento.

### Ranking e séries

- **CA-RK-01** Com todas as 27 UFs presentes numa competência, a posição nacional de Salvador é o número de municípios com tempo estritamente menor, mais um.
- **CA-RK-02** Empate no tempo produz a mesma posição para os empatados.
- **CA-RK-03** Competência com UF faltando não exibe posição nacional; devolve a lista das UFs ausentes.
- **CA-RK-04** O ranking das capitais usa a lista fixa de 27 capitais e ignora os demais municípios.
- **CA-RK-05** Capital sem dado na competência aparece como lacuna, não é omitida nem recebe zero.
- **CA-RK-06** A série histórica de Salvador devolve um ponto por competência ingerida, em ordem cronológica, sem preencher buracos.

### Distância para a referência

- **CA-DT-01** O cartão devolve o tempo interno, o tempo de referência, a diferença absoluta e a relativa, mais a competência de referência.
- **CA-DT-02** Sem competência de referência ingerida, o cartão informa a ausência e não calcula diferença.
- **CA-DT-03** A comparação usa sempre a mesma grandeza — horas úteis de viabilidade — nos dois lados.

### Gargalos

- **CA-GA-01** As horas úteis por faixa somam o tempo total do processo, com tolerância de um minuto.
- **CA-GA-02** Processo que nunca entrou em análise manual não contribui para a faixa de análise manual.
- **CA-GA-03** As faixas vêm ordenadas por participação decrescente, com percentual.

### Telas

- **CA-UI-01** `gestao/relatorios/tempo` entrega os percentis na régua federal junto dos dados já existentes.
- **CA-UI-02** `gestao/relatorios/benchmark` entrega posição, ranking de capitais e série histórica.
- **CA-UI-03** `gestao/sala-de-controle` entrega os blocos executivos num único carregamento.
- **CA-UI-04** As três rotas exigem `consultar-relatorios`; sem a permissão, respondem 403.
- **CA-UI-05** Os dois itens novos aparecem no grupo Indicadores e o teste de saúde do menu continua verde.
- **CA-UI-06** O bloco de distância renderiza a ressalva metodológica junto do número, não em rodapé.
- **CA-UI-07** Sem dado de benchmark, as telas mostram o estado vazio com o motivo, nunca zero.

## 6. Riscos

- **A API da REDESIM não é documentada como contrato público.** Foi descoberta a partir do bundle do painel oficial. Mitigação: o interruptor desliga a integração, a ingestão falha de forma visível e as camadas internas seguem funcionando sem ela.
- **O backfill de 2019 a 2026 é pesado** — 27 UFs por 96 competências, São Paulo a 16 MB. Mitigação: comando por competência, idempotente, executável em lotes, fora do caminho de requisição.
- **Mudança de metodologia federal em dezembro de 2025** (entrada do MAT na Receita) elevou os tempos apurados e quebra a comparabilidade da série. Mitigação: a quebra é marcada na série histórica e exibida na tela.
- **Município casado por nome** pode divergir se a base federal mudar a grafia. Mitigação: nome normalizado, e a ingestão registra nomes que não casaram com a lista de capitais.
- **A telemetria interna e o espelho federal vão divergir** por diferença de escopo de medição. Isso é esperado e é informação, não defeito: a divergência fica visível e serve para auditar a própria régua.

## 7. Fora de escopo

- Tela de comparação por CNAE (o agregado é gravado; a tela fica para fase posterior).
- Qualquer exposição pública ou no portal do cidadão.
- Importar o XLSX do MEMP ou raspar o boletim em PDF.
- Projeção, meta ou previsão de tempo.
- Alterar o significado dos indicadores do EP15 já entregues.
