# Estatísticas da REDESIM — microdados de tempo de abertura

Fonte do benchmark nacional de viabilidade. Todas as medições abaixo foram feitas contra o serviço ao vivo, sem autenticação, em 2026-10-03.

## 1. Natureza da fonte

É a API que alimenta o painel público de tempos de abertura em `estatistica.redesim.gov.br/tempos-abertura`, identificada a partir do bundle JavaScript do próprio painel.

**Não é publicada como contrato estável.** Não há documentação, versionamento declarado nem compromisso de disponibilidade. Por isso a integração tem interruptor próprio (`features.benchmark_redesim`), falha visível e evidência de cada chamada registrada em `benchmark_ingestions`.

## 2. Endpoints

Base: `https://estatistica.redesim.gov.br/tempos-abertura-redesim`

| Endpoint | Resposta medida |
|---|---|
| `/periodos-disponiveis` | JSON com a série disponível — **2019 a 2026** |
| `/exportar/solicitacoes/{UF}?ano=&mes=` | **XLSX com microdados por solicitação** — é o que o VIABILIZA consome |
| `/tempo-medio-total-abertura/{UF}?ano=&mes=` | `{"dias":1,"horas":7}` para BA em 08/2026 |
| `/percentual-viabilidade/{UF}?ano=&mes=` | distribuição em 4 faixas, **não** tempo médio |
| `/solicitacoes-abertura/{UF}?ano=&mes=` | contagem simples |

Só o export de microdados é usado: os demais são agregados por UF, e o benchmark precisa de recorte municipal e de percentis.

## 3. Estrutura do arquivo

Abas `dados` e `dicionario`. Na aba `dados`, a **linha 1 é agrupamento** e a **linha 2 traz os nomes dos campos**.

Campos consumidos:

| Campo no arquivo | Uso |
|---|---|
| `MUNICÍPIO` | chave do agregado, junto com a UF |
| `UF` | idem |
| `QTDE.  HH VIABILIDADE END` | a medida — horas úteis da análise de viabilidade de endereço |
| `ATIVIDADE  ECONÔMICA PRINCIPAL` | agregado por CNAE (Salvador e Brasil) |

Definição oficial, transcrita do dicionário da própria planilha:

> `QTDE.  HH VIABILIDADE END` — Quantidade de horas úteis entre o início e o fim da análise de viabilidade de ENDEREÇO. Não considera fins de semana ou feriados.

O painel acrescenta que os períodos em que o processo aguarda providência do cidadão não são contabilizados. É essa régua que `TempoViabilidadeCalculator` reproduz no VIABILIZA — sem ela, comparar os dois lados seria comparar grandezas diferentes.

## 4. Armadilhas medidas

- **Espaço duplo** em `QTDE.  HH VIABILIDADE END` e `ATIVIDADE  ECONÔMICA PRINCIPAL`. O parser casa por cabeçalho normalizado (maiúsculas, sem acento, sem pontuação, espaços colapsados), nunca por posição.
- **A ordem das colunas muda entre competências.** Mesma defesa.
- **Não há código IBGE.** O município é identificado por nome em maiúsculas e sem acento mais a UF (`SALVADOR`/`BA`).
- **`iconv` com `//TRANSLIT` não serve para normalizar**: depende da libc e, no macOS, transforma `Í` em `'I`. O código usa `Str::ascii`.
- **Linhas sem valor numérico** no campo de horas existem no arquivo oficial e são descartadas com contagem registrada em `linhas_descartadas`.
- **Volume**: São Paulo entrega cerca de 16 MB por competência (MG 4,5 MB, RJ 1,5 MB, BA 858 KB). A leitura é em streaming com `openspout`, e o timeout do cliente é de 180 s.
- **Quebra metodológica em 12/2025**: com a entrada do módulo tributário da Receita, o CNPJ passou a ser emitido depois da etapa tributária e os tempos apurados subiram. A série antes e depois desse marco não é diretamente comparável, e a tela sinaliza isso.

## 5. Linha de base medida

Salvador/BA, competência 08/2026, calculada a partir dos microdados:

| Medida | Valor |
|---|---|
| Solicitações | 665 |
| Média | 23,24 h |
| Mediana (P50) | 7,00 h |
| P75 | 44,00 h |
| P90 | 73,00 h |
| P95 | 97,00 h |
| Máximo | 101,02 h |

CNAEs mais lentos de Salvador no mês, com pelo menos 10 casos: 6911701 (54,2 h), 5611203 (41,5 h), 5611201 (37,5 h), 4712100 (31,4 h).

## 6. Fontes descartadas

**Dados abertos do MEMP** (XLSX mensal em `gov.br/memp`): traz só o `Tempo Médio de Abertura` **total**, sem separar viabilidade de registro; mantém apenas 4 competências no ar; e a qualidade oscila — em 08/2026 o campo vem com o texto `Em revisão. Será atualizado posteriormente`, e 2.471 municípios trazem `-`.

**Boletim quadrimestral (PDF)**: traz viabilidade, registro e total por capital, mas é derivado da mesma base, arredondado para horas inteiras, limitado às capitais e com layout tabular instável entre edições.

## 7. Operação

```bash
# Última competência fechada, nas 27 UFs
php artisan benchmark:importar-redesim

# Uma competência específica de uma UF
php artisan benchmark:importar-redesim --ano=2026 --mes=8 --uf=BA

# Backfill desde 2019 (pesado; executar em lotes)
php artisan benchmark:importar-redesim --desde=2019-01
```

Agendado mensalmente no dia 10, às 03h, porque a origem consolida a competência ao longo dos primeiros dias do mês seguinte. Competência já importada com sucesso é pulada; UF que falhar fica registrada como lacuna e entra na próxima passagem.

A posição no ranking só é publicada quando as 27 UFs da competência foram importadas com sucesso. Cobertura parcial devolve a lista do que faltou, e a tela mostra a lacuna — nenhum valor é estimado.
