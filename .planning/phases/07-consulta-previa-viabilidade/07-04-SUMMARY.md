---
phase: 07-consulta-previa-viabilidade
plan: 04
subsystem: backend
tags: [consulta-viabilidade, dto-readonly, veredito-propagado, agregacao-motores, snake-case, versoes-regras, rn-002, rn-004, anti-fachada, sqlite]

# Dependency graph
requires:
  - phase: 04-georreferenciamento
    provides: "GeocodeResult + TerritoryResult (toArray/versoes/restricoes) — sub-resultados agregados e padrão readonly espelhado"
  - phase: 05-motor-louos
    provides: "EnquadramentoResult (consolidado {resultado,motivo,fundamentacao,condicionantes} + versoes()) + ResultadoViabilidade (Pendente) — fonte do veredito propagado"
  - phase: 06-classificacao-de-risco
    provides: "RiscoResult (municipal/sanitario/encaminhamento/fundamentacao/versoes) — dimensão agregada; RiscoInput/EnquadramentoInput como padrão de named constructors"
provides:
  - "ConsultaViabilidadeInput readonly: 3 entradas honestas (paraEndereco/paraCnae/paraInscricao) com tipo explícito"
  - "ConsultaViabilidadeResult readonly: snapshot que agrega geocode+território+enquadramento+risco como sub-resultados reais (sem recomputar)"
  - "vereditoLocacional() PROPAGA o consolidado do motor LOUOS (resultado/label/motivo) — o orquestrador nunca decide"
  - "versoes() agrega as versões de TODAS as regras (territorio + louos + risco — RN-002/RN-004)"
  - "toArray() snake_case: contrato do endpoint JSON (07-06) e do snapshot do histórico (07-03/07), com seções HU-057/058/059"
  - "fundamentacao() une as referências legais dos dois motores sem duplicar"
affects: [07-05-servico-orquestrador, 07-06-endpoint-publico, 07-07-historico, 07-08-ui-consulta]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "DTO de saída AGREGADOR: espelha RiscoResult/EnquadramentoResult/TerritoryResult, mas compõe sub-resultados TIPADOS (?GeocodeResult/?TerritoryResult/EnquadramentoResult/RiscoResult) em vez de arrays soltos"
    - "Veredito PROPAGADO, não recomputado: vereditoLocacional() lê enquadramento->resultado()/consolidado — a degradação 'sem zona → pendente' (HU-044) é verdade única do motor, aqui espelhada (anti-fachada)"
    - "Degradação honesta por nulidade: geocode/território ausentes (consulta por CNAE) → chaves presentes em null e versoes.territorio [] — nunca inventa ponto/zona/versão"
    - "versoes() de TODAS as regras num só lugar (territorio/louos/risco) — insumo único da auditoria e da reprodução por época do EP07"

key-files:
  created:
    - app/Services/Viabilidade/ConsultaViabilidadeInput.php
    - app/Services/Viabilidade/ConsultaViabilidadeResult.php
    - tests/Unit/Viabilidade/ConsultaViabilidadeResultTest.php
  modified: []

key-decisions:
  - "vereditoLocacional() retorna {resultado, label, motivo} 100% derivado do EnquadramentoResult (resultado()/consolidado['motivo'] + ResultadoViabilidade::from()->label()); o Result NÃO atribui veredito próprio nem recomputa a HU-044"
  - "toArray() trava a ordem EXATA de 10 chaves (entrada, geocode, territorio, enquadramento, risco, restricoes, veredito_locacional, fundamentacao, avisos, versoes) — geocode/territorio sempre presentes (null quando ausentes), espelhando o lock dos DTOs irmãos"
  - "'restricoes' é atalho direto a territory->restricoes (HU-059) além de já estar dentro de 'territorio' — conveniência para a UI sem duplicar lógica"
  - "fundamentacao() = array_values(array_unique(LOUOS.consolidado.fundamentacao + risco.fundamentacao)) — união sem inventar referência nova"
  - "Testes do Input no mesmo arquivo do Result (espelha EnquadramentoResultTest/RiscoDtoTest) — cobre os construtores nomeados da Task 1 com teste real, dentro do files_modified"

patterns-established:
  - "ConsultaViabilidadeResult é o SNAPSHOT único da fase: o serviço (07-05) preenche, o endpoint (07-06) serializa via toArray(), o histórico (07-03/07) persiste o toArray() e as versoes(), a UI (07-08) consome o JSON"
  - "Sub-resultados nulos (geocode/território) sinalizam a entrada por CNAE/inscrição; o consumidor lê 'avisos' para a degradação comunicada"

# Metrics
duration: ~4 min
completed: 2026-06-14
---

# Phase 7 Plan 04: DTOs da Consulta Prévia (Input + Result com veredito propagado) Summary

**`ConsultaViabilidadeInput` (3 entradas honestas: paraEndereco/paraCnae/paraInscricao com tipo explícito) e `ConsultaViabilidadeResult` readonly — o SNAPSHOT da consulta que AGREGA geocode + território + enquadramento LOUOS + risco como sub-resultados reais TIPADOS, sem recomputar nenhum. O veredito locacional é PROPAGADO do consolidado do motor LOUOS (`enquadramento->resultado()`/`->consolidado['motivo']`), nunca decidido pelo orquestrador — a degradação "sem zona → pendente" (HU-044) é verdade única do motor, aqui apenas espelhada. `toArray()` é o contrato snake_case (10 chaves, ordem travada) do endpoint JSON e do snapshot do histórico, com seções de enquadramento (HU-057), risco (HU-058) e restrições (HU-059); `versoes()` agrega as versões de TODAS as regras (território + LOUOS + risco — RN-002/RN-004); `fundamentacao()` une as referências dos dois motores sem duplicar.**

## Performance

- **Duration:** ~4 min
- **Started:** 2026-06-14T08:03:56Z
- **Completed:** 2026-06-14T08:07:22Z
- **Tasks:** 2 (Task 1 estrutural + pint; Task 2 TDD RED → GREEN → pint)
- **Files created:** 3 | **modified:** 0

## Accomplishments
- `ConsultaViabilidadeInput` readonly com as 3 entradas honestas e `tipo` explícito (constantes TIPO_ENDERECO/TIPO_CNAE/TIPO_INSCRICAO).
- `ConsultaViabilidadeResult` readonly agregando sub-resultados TIPADOS (`?GeocodeResult`, `?TerritoryResult`, `EnquadramentoResult`, `RiscoResult`) + `vereditoLocacional()` (propagado), `fundamentacao()` (unida sem duplicar), `versoes()` (todas as regras) e `toArray()` (snake_case).
- Unit test `ConsultaViabilidadeResultTest` com **8 casos** (mín. do plano: 4): prova a propagação do veredito (não recomputo), a agregação de versões, o shape exato do `toArray()`, a degradação honesta na consulta por CNAE e a união sem duplicatas da fundamentação — além de cobrir os 3 construtores do Input.
- Suíte SQLite: **612/612 verde** (604 baseline + 8 novos), sem regressão; `--filter=ConsultaViabilidadeResultTest` 8/8 (37 asserções).

## Task Commits

Cada task com commits atômicos (TDD na Task 2). Histórico interleaved com os executores paralelos das fases irmãs (07-01/02/03) — todos os commits abaixo são ancestrais lineares do HEAD:

1. **Task 1: ConsultaViabilidadeInput** - `1b67339` (feat)
2. **Task 2 — RED (testes Result + Input)** - `f8c33fe` (test)
3. **Task 2 — GREEN (ConsultaViabilidadeResult)** - `7fab7b8` (feat)

**Plan metadata:** este SUMMARY (docs).

## Contrato dos DTOs (insumo direto de 07-05/06/07/08)

### `ConsultaViabilidadeInput` (entrada — 07-05 monta a partir do request)
```
final readonly class ConsultaViabilidadeInput
const TIPO_ENDERECO = 'endereco'; const TIPO_CNAE = 'cnae'; const TIPO_INSCRICAO = 'inscricao';

__construct(string $tipo, string $cnae, ?float $area = null, ?string $endereco = null, ?string $inscricao = null)

paraEndereco(string $endereco, string $cnae, ?float $area = null): self   // tipo = endereco
paraCnae(string $cnae, ?float $area = null): self                          // tipo = cnae
paraInscricao(string $inscricao, string $cnae, ?float $area = null): self  // tipo = inscricao
```
- `cnae` é sempre exigido (a viabilidade é sempre de uma atividade). `area` (m²) alimenta o Quadro 7 (HU-057); sem área, o Quadro 7 não enquadra e o consolidado do motor degrada para pendente — honesto.

### `ConsultaViabilidadeResult::toArray()` — shape EXATO (contrato 07-06/07/08), ordem travada
```php
[
    'entrada' => array,                       // metadados: {tipo, cnae, cnae_formatado, area, endereco?, inscricao?} — o serviço 07-05 define
    'geocode' => ?array,                      // GeocodeResult::toArray() {latitude, longitude, display_name, confidence, address} | null
    'territorio' => ?array,                   // TerritoryResult::toArray() {bairro, via, zona, lote, restricoes} | null
    'enquadramento' => array,                 // EnquadramentoResult::toArray() {quadro7, quadro10, quadro11, quadro11a, consolidado, versoes} — HU-057
    'risco' => array,                         // RiscoResult::toArray() {municipal, sanitario, encaminhamento, fundamentacao, versoes} — HU-058
    'restricoes' => ?array,                   // atalho a territory->restricoes — HU-059 (null sem território)
    'veredito_locacional' => array,           // {resultado, label, motivo} — PROPAGADO do motor LOUOS
    'fundamentacao' => array,                 // list<string> — união LOUOS + risco sem duplicar
    'avisos' => array,                        // list<string> — degradações honestas comunicadas
    'versoes' => array,                       // {territorio: {...}, louos: {...}, risco: {...}} — RN-002/RN-004
]
```

### Veredito PROPAGADO (confirmação anti-fachada)
`vereditoLocacional()` é 100% derivado do `EnquadramentoResult`:
```php
$resultado = $this->enquadramento->resultado();           // = consolidado['resultado'] (vindo do motor LOUOS)
['resultado' => $resultado,
 'label' => ResultadoViabilidade::from($resultado)->label(),
 'motivo' => $this->enquadramento->consolidado['motivo'] ?? null]
```
O Result **não** atribui veredito próprio nem recomputa a HU-044. Provado por `test_veredito_locacional_propaga_o_consolidado_do_motor`: para um `EnquadramentoResult` consolidado em `pendente`, o veredito sai `{resultado:'pendente', label:'Pendente de análise técnica', motivo:'Permissão por zona pendente da base oficial (SEDUR)'}` — todos vindos do sub-resultado do motor, sem decisão do orquestrador.

### `versoes()` (RN-002/RN-004)
`{ territorio: TerritoryResult::versoes()|[], louos: EnquadramentoResult::versoes(), risco: RiscoResult::versoes() }`. Território ausente (consulta por CNAE) → `territorio: []` (sem inventar versão). É o insumo único da auditoria e da reprodução por época do EP07.

### `fundamentacao()`
`array_values(array_unique([...enquadramento.consolidado.fundamentacao, ...risco.fundamentacao]))` — união honesta das referências já produzidas pelos motores, sem duplicar.

## Files Created
- `app/Services/Viabilidade/ConsultaViabilidadeInput.php` — DTO readonly de entrada (3 construtores nomeados + tipo explícito).
- `app/Services/Viabilidade/ConsultaViabilidadeResult.php` — DTO readonly de saída (agrega motores + veredito propagado + versoes de todas as regras + fundamentação unida + toArray snake_case).
- `tests/Unit/Viabilidade/ConsultaViabilidadeResultTest.php` — 8 casos (3 Input + 5 Result), sub-resultados montados à mão (sem banco).

## Decisions Made
- **Veredito propagado, não recomputado:** `vereditoLocacional()` deriva tudo do `EnquadramentoResult`. Duplicar a consolidação da HU-044 no orquestrador seria fachada e fonte de divergência — a regra mora no motor.
- **Sub-resultados tipados (não arrays soltos):** o construtor recebe `?GeocodeResult/?TerritoryResult/EnquadramentoResult/RiscoResult`, preservando os contratos das Fases 4/5/6 e deixando o `toArray()` delegar a cada `->toArray()`. Evita reimplementar shape e mantém uma única fonte de verdade por motor.
- **Ordem das 10 chaves travada em teste** (`assertSame` em `array_keys`): o `toArray()` é contrato de endpoint e de snapshot persistido; travar a ordem (incluindo `geocode`/`territorio`, omitidos na lista do plano mas sempre presentes) espelha o lock dos DTOs irmãos e protege 07-06/07/08 de regressão silenciosa.
- **`restricoes` como atalho** além de já constar em `territorio`: a UI (07-08, HU-059) consome restrições diretamente; o atalho evita navegação aninhada sem duplicar dado.
- **Verificação em SQLite (`--exclude-group postgis`):** os DTOs são puros (sem banco/geometria); a suíte `:memory:` os prova integralmente. O grupo `postgis` (território real) não é exercitado por este plano.

## Deviations from Plan

Nenhum bug, correção crítica, bloqueio ou mudança arquitetural. Apenas **enriquecimentos aditivos de teste**, todos dentro dos 3 `files_modified` do 07-04 (zero scope creep, zero dependência nova):

**1. [Aditivo] 3 testes do Input no mesmo arquivo do Result**
- **Motivo:** espelha a convenção dos DTOs irmãos (`EnquadramentoResultTest`/`RiscoDtoTest` testam Input + Result juntos) e dá cobertura de teste real aos construtores nomeados da Task 1 (cujo `verify` no plano era só `pint`).
- **Arquivos:** `tests/Unit/Viabilidade/ConsultaViabilidadeResultTest.php`.

**2. [Aditivo] Teste `test_to_array_degrada_geocode_e_territorio_nulos_na_consulta_por_cnae`**
- **Motivo:** evidência anti-fachada da degradação honesta da consulta por CNAE (HU-056): `geocode`/`territorio`/`restricoes` saem `null` e `versoes.territorio` sai `[]` — o veredito segue `pendente` vindo do motor. O plano pedia 4 testes do Result; este 5º torna a degradação explícita.
- **Arquivos:** idem.

**3. [Fixture de teste] Sobreposição de referência para provar a deduplicação real**
- **Motivo:** o plano dava valores de exemplo sem sobreposição entre as fundamentações dos dois motores, o que não exercitaria o `array_unique`. O helper inclui `'Decreto Municipal nº 32.636/2020'` tanto no consolidado do enquadramento quanto na fundamentação do risco, para que `test_fundamentacao_une_motor_louos_e_risco_sem_duplicar` prove a remoção da duplicata (referência comum aparece 1×).
- **Arquivos:** idem.

**4. [Contrato mais forte] `toArray()` trava a ordem exata das 10 chaves**
- **Motivo:** o plano listou 8 chaves na asserção; o `toArray()` retorna 10 (inclui `geocode` e `territorio`, sempre presentes). O teste assere `array_keys` completo, protegendo o contrato do endpoint/snapshot.
- **Arquivos:** idem.

---

**Total deviations:** 0 correções (bug/crítica/bloqueio/arquitetural). 4 enriquecimentos aditivos de teste.
**Impact on plan:** Sem scope creep. Somente os 3 arquivos do `files_modified` do 07-04 foram tocados. Nada do 07-01 (parâmetros/RateLimiter), 07-02 (contrato inscrição), 07-03 (viability_queries) nem do 07-05 (serviço) foi alterado.

## Issues Encountered
- **Execução paralela das fases irmãs (esperada):** durante o 07-04 havia executores concorrentes nos planos 07-01/02/03 commitando no mesmo branch — o histórico ficou interleaved. Verificado que os 3 commits do 07-04 (`1b67339`/`f8c33fe`/`7fab7b8`) são ancestrais lineares do HEAD e que só os arquivos do `files_modified` foram tocados (`git merge-base --is-ancestor` + `git log -- app/Services/Viabilidade/ tests/Unit/Viabilidade/`). Nenhum `git add -A`; staging sempre por caminho explícito. Sem colisão de `index.lock` neste plano.

## User Setup Required
None — sem configuração de serviço externo. Sem dependência nova (confirmado: ZERO deps adicionadas).

## Next Phase Readiness
- **07-05 (serviço orquestrador):** preenche o `ConsultaViabilidadeResult` a partir de `ConsultaViabilidadeInput` — pipeline Geocoder → TerritoryService → LouosEnquadramentoService(`EnquadramentoInput::paraConsulta`) → RiscoClassificationService(`RiscoInput::paraCnae`) → `new ConsultaViabilidadeResult(...)`. O Result já expõe a `entrada` (metadados), os `avisos` (degradações) e o veredito propagado; o serviço só monta e audita.
- **07-06 (endpoint público):** serializa `->toArray()` (shape de 10 chaves acima, ordem estável) — contrato JSON pronto para o React.
- **07-07 (histórico):** persiste `->toArray()` (snapshot) e `->versoes()` (rules_versions) na tabela imutável `viability_queries` (07-03) quando autenticado.
- **07-08 (UI):** consome o JSON — seções de enquadramento (HU-057), risco (HU-058), restrições/bairro/via (HU-059) e o `veredito_locacional` (pendente com motivo, sem mostrar Permitido/Não permitido sem zona).
- **Bloqueios herdados (não introduzidos por este plano):** veredito locacional permitido/não permitido depende da base de zona (SIGIS/CA 2000, Fase 13) e a resolução por inscrição depende da base de lotes (contrato 07-02) — ambos degradam honestamente via `avisos` + veredito `pendente`.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*
