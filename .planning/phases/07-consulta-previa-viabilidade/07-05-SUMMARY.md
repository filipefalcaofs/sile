---
phase: 07-consulta-previa-viabilidade
plan: 05
subsystem: backend
tags: [consulta-viabilidade, orquestrador, veredito-propagado, auditoria, rn-002, degradacao-honesta, anti-fachada, hu-054, hu-055, hu-056, sqlite]

# Dependency graph
requires:
  - phase: 04-georreferenciamento
    provides: "Geocoder (endereço→ponto) + TerritoryService (ponto→bairro/via/zona/lote/restrições) atrás de contrato; FakeSpatialRepository para testar sem PostGIS"
  - phase: 05-motor-louos
    provides: "LouosEnquadramentoService::enquadrar + EnquadramentoInput::paraConsulta — veredito consolidado (HU-044) que o orquestrador apenas propaga"
  - phase: 06-classificacao-de-risco
    provides: "RiscoClassificationService::classify + RiscoInput::paraCnae — dimensão de risco real (Decreto 32.636/2020 + VISA)"
  - phase: 07-consulta-previa-viabilidade
    plan: 02
    provides: "Contrato PropertyRegistryLookup + PropertyRegistryUnavailableException — entrada por inscrição (resolve quando a base existir; degrada enquanto pendente SEDUR)"
  - phase: 07-consulta-previa-viabilidade
    plan: 04
    provides: "ConsultaViabilidadeInput (paraEndereco/paraCnae/paraInscricao) + ConsultaViabilidadeResult (snapshot agregador com veredito propagado, versoes() e toArray())"
provides:
  - "ConsultaViabilidadeService: orquestrador Geocoder→Território→LOUOS→Risco que só COMPÕE e AUDITA (não recomputa veredito)"
  - "consultarPorEndereco (HU-054): geocodifica → identifica território → enquadra → classifica — pipeline de ponta a ponta"
  - "consultarPorCnae (HU-056): risco real + Quadro 7 por área, SEM território; veredito pendente + aviso de que não avalia o local"
  - "consultarPorInscricao (HU-055): resolve via contrato (pipeline completa) ou degrada para a via CNAE + aviso — NUNCA inventa ponto"
  - "Auditoria 'viabilidade'/'consulta' (RN-002) com tipo, cnae, veredito, encaminhamento de risco, avisos e versões de TODAS as regras"
affects: [07-06-controller-endpoint, 07-07-historico, 07-08-ui-consulta, 07-10-golden-comando]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Orquestrador puro: o serviço só passa adiante as saídas dos motores e audita; o veredito vem de EnquadramentoResult::resultado() (propagação), nunca recomputado — grep prova 0 literais de veredito decididos pelo service"
    - "Pipeline central única (consultarPorPonto) reusada por endereço e inscrição-resolvida; montagem do Result + auditoria extraídas em comporEResultar (DRY entre as 3 entradas)"
    - "Degradação honesta por exceção tipada: PropertyRegistryUnavailableException → cai para consultarPorCnaeComEntrada com aviso; nunca adaptador falso, nunca coordenada inventada"
    - "Teste com fake do contrato (PropertyRegistryLookup) prova a pipeline completa da inscrição para quando a base de lotes chegar (Fase 13) — anti-fachada"

key-files:
  created:
    - app/Services/Viabilidade/ConsultaViabilidadeService.php
    - tests/Feature/Viabilidade/ConsultaViabilidadeServiceTest.php
  modified: []

key-decisions:
  - "O serviço NUNCA decide o veredito: veredito_locacional é lido de ConsultaViabilidadeResult::vereditoLocacional() (que propaga EnquadramentoResult); grep confirma ausência de literais 'permitido'/'nao_permitido' no service"
  - "consultarPorEndereco NÃO captura AddressNotFoundException/GeocoderException: deixa propagar (o controller 07-06 traduz) — endereço não localizado jamais vira resultado falso"
  - "Inscrição indisponível degrada para a MESMA análise por CNAE (consultarPorCnaeComEntrada), com aviso que sugere o endereço; geocode e território ficam null — prova de que nenhum ponto é inventado"
  - "Auditoria única por consulta no método compartilhado comporEResultar (logName 'viabilidade', event 'consulta'); causer null quando anônimo é resolvido dentro do AuditService"

requirements-completed: [HU-054, HU-055, HU-056]

# Metrics
duration: ~18 min
completed: 2026-06-14
---

# Phase 7 Plan 05: ConsultaViabilidadeService (orquestrador) Summary

**O coração da Fase 7: `ConsultaViabilidadeService` ORQUESTRA os motores reais (Geocoder → TerritoryService → LouosEnquadramentoService → RiscoClassificationService), compõe o `ConsultaViabilidadeResult` e AUDITA (RN-002) — sem nenhuma lógica de decisão própria. O veredito locacional é PROPAGADO do consolidado do motor LOUOS (HU-044); a degradação honesta "sem zona → pendente" vem do motor e é apenas espelhada. Três entradas: endereço (HU-054, pipeline completa), CNAE (HU-056, risco + Quadro 7 por área sem território) e inscrição (HU-055, via contrato `PropertyRegistryLookup` — resolve a pipeline completa quando a base existir, degrada para a via CNAE + aviso enquanto pendente SEDUR, NUNCA inventando ponto). Provado por 9 feature tests com motores REAIS (seeds da Lei 9.148/2016 e do Decreto 32.636/2020) e fakes de Geocoder/SpatialRepository/PropertyRegistryLookup.**

## Performance

- **Duration:** ~18 min
- **Completed:** 2026-06-14
- **Tasks:** 3 (todas em TDD estrito RED → GREEN → pint)
- **Files:** 2 (1 service criado, 1 feature test criado)

## Contrato do service (insumo direto de 07-06/07/08/10)

Construtor (injeção dos motores reais — bindings já existentes no `AppServiceProvider`):

```php
public function __construct(
    private Geocoder $geocoder,
    private TerritoryService $territory,
    private LouosEnquadramentoService $louos,
    private RiscoClassificationService $risco,
    private PropertyRegistryLookup $propertyRegistry,
    private AuditService $audit,
) {}
```

### Três métodos públicos (o controller 07-06 chama estes)

```php
// HU-054 — pipeline completa: geocodifica (real) → território → motores.
public function consultarPorEndereco(string $endereco, string $cnae, ?float $area = null): ConsultaViabilidadeResult

// HU-056 — risco real + Quadro 7 por área, SEM território.
public function consultarPorCnae(string $cnae, ?float $area = null): ConsultaViabilidadeResult

// HU-055 — via contrato: resolve→pipeline completa OU degrada para CNAE + aviso.
public function consultarPorInscricao(string $inscricao, string $cnae, ?float $area = null): ConsultaViabilidadeResult
```

`cnae` é sempre exigido (a viabilidade é sempre de uma atividade); `area` (m²) alimenta o Quadro 7. Os três retornam o `ConsultaViabilidadeResult` (snapshot do 07-04) — o endpoint serializa `->toArray()` e o histórico persiste `->toArray()`/`->versoes()`.

### Pipeline interna (privada)

- `consultarPorPonto(float $lat, float $lng, ConsultaViabilidadeInput $input, ?GeocodeResult $geocode)` — usada por endereço e por inscrição-resolvida: `territory->identify()` → `louos->enquadrar(EnquadramentoInput::paraConsulta(area, cnae, $territory))` → `risco->classify(RiscoInput::paraCnae(cnae))`. Sem zona identificada acrescenta o aviso de UI (o veredito JÁ vem pendente do motor).
- `consultarPorCnaeComEntrada(ConsultaViabilidadeInput $input, array $avisos)` — reusada pela via CNAE pura e pela inscrição degradada: `enquadrar(EnquadramentoInput::paraConsulta(area, cnae, null))` (território NULL → Quadro 10 indisponível → consolidado pendente; Quadro 7 por área roda) + `classify`.
- `comporEResultar(...)` — montagem do `ConsultaViabilidadeResult` + auditoria, único ponto de criação do Result e de log (DRY entre as 3 entradas).

## Propagação do veredito (anti-fachada)

O serviço **não** atribui veredito. Em `comporEResultar`, o campo auditado e exposto é `$result->vereditoLocacional()['resultado']`, que o `ConsultaViabilidadeResult` (07-04) deriva de `EnquadramentoResult::resultado()`. Verificação objetiva: `grep "'permitido'\|'nao_permitido'"` no service retorna **0** — nenhum veredito é decidido aqui. Sem zona, o consolidado do motor é `pendente` e o serviço apenas o propaga (testes 2 e 3).

## Evento de auditoria (RN-002)

Uma chamada por consulta, dentro de `comporEResultar`:

```php
$this->audit->log(
    logName: 'viabilidade',
    event: 'consulta',
    description: "Consulta de viabilidade ({$input->tipo}) do CNAE {$input->cnae}",
    properties: [
        'tipo' => $input->tipo,                                  // endereco | cnae | inscricao
        'cnae' => $cnaeNormalizado,                              // dígitos (4712100)
        'area' => $input->area,
        'veredito' => $result->vereditoLocacional()['resultado'], // propagado do motor
        'encaminhamento_risco' => $risco->encaminhamento['fluxo'] ?? null,
        'avisos' => $result->avisos,
        'versoes' => $result->versoes(),                         // {territorio, louos, risco}
    ],
    result: 'sucesso',
);
```

`causer` null quando anônimo (resolvido dentro do `AuditService`); origem/IP enriquecidos pela `RecordActivityAction`.

## Estratégia de degradação

| Caminho | Disponível | Indisponível (pendente SEDUR) |
|---|---|---|
| **Zona** (HU-054) | Quadro 10 decide o veredito | Quadro 10 indisponível → consolidado `pendente` (motor) + aviso de UI "Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR)." |
| **Inscrição** (HU-055) | `propertyRegistry->resolve()` devolve o ponto → pipeline completa | `PropertyRegistryUnavailableException` → cai para `consultarPorCnaeComEntrada` com aviso "Resolução por inscrição imobiliária indisponível (base de lotes pendente SEDUR)…"; geocode/território = null (NUNCA inventa ponto) |
| **CNAE** (HU-056) | risco + Quadro 7 por área | não aplicável (já é a via sem território); aviso "Consulta por CNAE não avalia o local…" |

## Como o território é injetado nos testes (sem PostGIS)

- **Geocoder**: classe anônima implementando `Geocoder` que devolve um `GeocodeResult` fixo de Salvador; bind via `$this->app->instance(Geocoder::class, $fake)`.
- **SpatialRepository**: `Tests\Support\Geo\FakeSpatialRepository` com `setContaining(GeoLayerType::Bairro, [...])` + uma camada `GeoLayer::factory()->vigente()` de bairro; bind via `$this->app->instance(SpatialRepository::class, $fake)`. Sem camada de zona → `TerritoryService` devolve zona `indisponivel` (reproduz o cenário real pendente SEDUR).
- **PropertyRegistryLookup**: classe anônima que resolve em `PropertyRegistryResult` (teste do caminho disponível); o caminho indisponível usa o binding REAL (`UnavailablePropertyRegistryLookup`).
- Os MOTORES rodam REAIS sobre seeds reais (`LouosQuadro7Seeder`, `RiscoMunicipalSeeder`, `RiscoSanitarioSeeder`). O território PostGIS real é coberto no fechamento 07-10 (`@group postgis`).

## Task Commits

1. **Task 1 (TDD): endereço + pipeline central + auditoria + propagação** — `b488f00` (feat)
2. **Task 2 (TDD): consultarPorCnae sem território** — `e6e8d74` (feat)
3. **Task 3 (TDD): consultarPorInscricao via contrato (resolve/degrada)** — `be3f872` (feat)

_Cada task em TDD estrito: teste primeiro (RED verificado pelo motivo certo — classe/método inexistente), código mínimo (GREEN), `pint`._

## Files Created

- `app/Services/Viabilidade/ConsultaViabilidadeService.php` — orquestrador (3 públicos + pipeline central + CNAE-com-entrada + comporEResultar + normalização/formatação de CNAE).
- `tests/Feature/Viabilidade/ConsultaViabilidadeServiceTest.php` — 9 feature tests (motores reais + fakes de Geocoder/SpatialRepository/PropertyRegistryLookup).

## Decisions Made

- **Veredito propagado, nunca recomputado:** o serviço lê `vereditoLocacional()` do Result (que deriva do motor LOUOS). Duplicar a HU-044 aqui seria fachada e fonte de divergência. `grep` de literais de veredito no service = 0.
- **`consultarPorCnaeComEntrada` introduzido já na Task 2 e reusado na Task 3:** o privado nasce DRY (a via CNAE pura e a inscrição degradada compartilham a montagem/auditoria), em vez de duplicar e só então refatorar.
- **Exceção do geocoder propaga:** `consultarPorEndereco` não a captura — endereço não localizado/serviço indisponível é responsabilidade de tradução do controller (07-06), nunca um resultado falso (provado por teste).
- **CNAE normalizado em dígitos + `cnae_formatado` 0000-0/00 na entrada:** espelha o precedente do import (motores guardam dígitos); a formatação não inventa formato quando não houver 7 dígitos.

## Deviations from Plan

Sem bug, correção crítica, bloqueio ou mudança arquitetural. Ajustes para honrar a realidade dos seeds e reforçar o anti-fachada:

**1. [Realidade do seed] Teste 5 — cenário `nao_encontrado` realizado por CNAE fora do Quadro 7, não por "área 0"**
- **O plano supôs:** "CNAE válido sem área (null) → Quadro 7 nao_encontrado (área 0)".
- **Realidade:** TODAS as 40 faixas do Quadro 7 seedado (Lei 9.148/2016) começam em `area_min = 0` — logo, área 0 SEMPRE enquadra para um CNAE listado. Para obter `nao_encontrado` de forma honesta, o teste usa `6201-5/01` (desenvolvimento de software): classificado no risco municipal (BAIXO A), porém AUSENTE do Quadro 7 → `quadro7.status = nao_encontrado`, `grupo = null` (não inventa grupo), veredito `pendente`, risco real. A consulta é feita SEM área (caminho null→0.0 exercido).
- **Arquivos:** `tests/Feature/Viabilidade/ConsultaViabilidadeServiceTest.php` (`test_consulta_por_cnae_fora_do_quadro7_fica_pendente_sem_inventar_grupo`).

**2. [Aditivo] Teste extra do caminho null→0.0 para CNAE enquadrável**
- **Motivo:** cobrir explicitamente que um CNAE enquadrável (`4712-1/00`) consultado SEM área continua com veredito `pendente` (sem território) — JAMAIS um `permitido` inventado — e o risco segue real.
- **Arquivos:** idem (`test_consulta_por_cnae_sem_area_nao_forca_veredito`).

**3. [Aditivo] Teste de propagação da exceção de endereço não localizado**
- **Motivo:** evidência anti-fachada de que `consultarPorEndereco` propaga `AddressNotFoundException` (não inventa resultado) — alinhado ao "endereço não localizado NÃO vira resultado falso".
- **Arquivos:** idem (`test_endereco_nao_localizado_propaga_excecao_e_nao_inventa_resultado`).

**4. [Variação de grep da aceitação] `paraConsulta(...null)` usa `$input->area`/`$input->cnae`**
- O critério da Task 2 grep literal `paraConsulta((float) ($area ?? 0.0), $cnae, null)`. O código final, pelo refactor DRY exigido pela Task 3 (`consultarPorCnaeComEntrada(ConsultaViabilidadeInput $input, …)`), usa `EnquadramentoInput::paraConsulta((float) ($input->area ?? 0.0), $input->cnae, null)`. O SEMÂNTICO (território NULL na via CNAE) é preservado e testado; mudam só os nomes das variáveis pelo escopo do método.

**Total:** 0 correções; 3 enriquecimentos de teste + 1 variação de nome de variável. Sem scope creep — só os 2 arquivos do `files_modified` do 07-05 foram tocados; nada de 07-06/07/08/10.

## Issues Encountered

- **Premissa do plano vs. seed do Quadro 7 (resolvida):** ver Deviation 1. Optei por dado real (CNAE fora do Quadro 7) em vez de forçar a premissa irreal — a lógica processa dados reais; muda a carga, não o comportamento.

## Verification

- `php artisan test --compact --filter=ConsultaViabilidadeServiceTest` → **9/9 verde** (46 asserções).
- `php artisan test --compact tests/Feature/Viabilidade tests/Unit/Viabilidade` → **24/24 verde** (107 asserções; inclui 07-02/03/04).
- `php artisan test --compact --exclude-group postgis` → **625/625 verde** (3169 asserções) = 616 baseline + 9 novos, **zero regressão**.
- `vendor/bin/pint` (escopado nos 2 arquivos) → passed.
- **Greps de aceitação:** `class ConsultaViabilidadeService` (1), `EnquadramentoInput::paraConsulta` (2), `RiscoInput::paraCnae` (2), `logName: 'viabilidade'` (1), `public function consultarPorCnae` (1), `public function consultarPorInscricao` (1), `consultarPorCnaeComEntrada` (3), `catch (PropertyRegistryUnavailableException` (1); literais de veredito decididos pelo service = **0**.
- **Evidência anti-fachada:** teste 2 (sem zona → pendente, motor não inventa), teste 6 (fake prova a inscrição de ponta a ponta quando a base chegar), teste 7 (indisponível degrada para CNAE + aviso, geocode/território null — nenhum ponto inventado).

## Next Phase Readiness

- **07-06 (controller/endpoint):** chama `consultarPorEndereco/consultarPorCnae/consultarPorInscricao` e serializa `->toArray()`; deve traduzir `AddressNotFoundException`/`GeocoderException` (propagadas) em mensagem honesta ("endereço não localizado / serviço indisponível").
- **07-07 (histórico):** persiste `->toArray()` (snapshot) e `->versoes()` na tabela imutável `viability_queries` quando autenticado.
- **07-08 (UI):** consome o JSON — seções de enquadramento (HU-057), risco (HU-058), restrições/bairro/via (HU-059) e `veredito_locacional` (pendente com motivo, sem mostrar Permitido/Não permitido sem zona).
- **07-10 (golden/comando):** exercita o serviço de ponta a ponta com território real (`@group postgis`).
- **Bloqueios herdados (não introduzidos aqui):** veredito permitido/não permitido depende da base de zona (SIGIS/CA 2000, Fase 13); resolução por inscrição depende da base de lotes (contrato 07-02, Fase 13 troca só o binding). Ambos degradam honestamente via `avisos` + veredito `pendente`.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*
