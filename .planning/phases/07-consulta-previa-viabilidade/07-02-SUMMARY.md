---
phase: 07-consulta-previa-viabilidade
plan: 02
subsystem: integrations
tags: [inscricao-imobiliaria, cadastro, lotes, contrato, binding, blocked, realty, hu-055]

requires:
  - phase: 03-cadastro-empresarial
    plan: 02
    provides: Padrão de integração atrás de contrato (interface + DTO readonly + exceções tipadas + provider + binding) — CnpjLookup/BrasilApiCnpjLookup espelhado aqui
  - phase: 04-georreferenciamento
    provides: GeocodeResult (DTO readonly com latitude/longitude NOMEADAS + toArray snake_case) — modelo do PropertyRegistryResult
provides:
  - Contrato App\Services\Realty\PropertyRegistryLookup (resolve(string $inscricao): PropertyRegistryResult) — binding trocável na Fase 13
  - DTO readonly PropertyRegistryResult (latitude/longitude nomeadas, inscricao, source, raw; toArray snake_case)
  - Exceções tipadas PropertyRegistryUnavailableException (base pendente SEDUR) e PropertyNotFoundException (inscrição inexistente)
  - Provider real UnavailablePropertyRegistryLookup — SEMPRE lança unavailable (HU-055 bloqueada, nunca inventa ponto)
  - Binding PropertyRegistryLookup -> UnavailablePropertyRegistryLookup no AppServiceProvider
affects: [07-05 (service consome o contrato na entrada por inscrição), 07-06 (endpoint de consulta), 13-integracoes (HU-106 troca SÓ o binding pela base oficial)]

tech-stack:
  added: []
  patterns:
    - "Integração indisponível atrás de contrato: o provider REAL degrada honestamente (sempre lança unavailable), nunca adaptador falso — a fase de integração troca SÓ o binding"
    - "Teste com fake implementando o contrato prova que a lógica resolve de ponta a ponta quando a base oficial chegar (anti-fachada)"
    - "DTO readonly com latitude/longitude NOMEADAS + toArray snake_case (espelha GeocodeResult; evita ambiguidade [lat,lng] vs [lng,lat])"

key-files:
  created:
    - app/Services/Realty/PropertyRegistryLookup.php
    - app/Services/Realty/PropertyRegistryResult.php
    - app/Services/Realty/PropertyRegistryUnavailableException.php
    - app/Services/Realty/PropertyNotFoundException.php
    - app/Services/Realty/UnavailablePropertyRegistryLookup.php
    - tests/Feature/Viabilidade/PropertyRegistryLookupTest.php
  modified:
    - app/Providers/AppServiceProvider.php

key-decisions:
  - "PropertyRegistryResult espelha GeocodeResult: latitude/longitude NOMEADAS (sem ambiguidade de ordem), source e raw (payload bruto p/ auditoria), toArray snake_case"
  - "UnavailablePropertyRegistryLookup é o provider REAL e SEMPRE lança PropertyRegistryUnavailableException — HU-055 fica explicitamente BLOQUEADA na resolução do ponto, jamais simulada"
  - "Teste com fake anônimo implementando PropertyRegistryLookup prova que a lógica resolve inscrição→coordenada quando a base de lotes chegar (Fase 13 troca SÓ o binding)"

requirements-completed: [HU-055]

duration: ~8min
completed: 2026-06-14
---

# Phase 7 Plan 02: Contrato PropertyRegistryLookup (inscrição imobiliária — BLOQUEADO) Summary

**HU-055 entregue como contrato honestamente bloqueado: interface `PropertyRegistryLookup::resolve(inscricao): PropertyRegistryResult` + DTO readonly + exceções tipadas + provider real `UnavailablePropertyRegistryLookup` que SEMPRE lança `PropertyRegistryUnavailableException` (base de lotes/Cadastro pendente SEDUR — nunca inventa ponto) + binding no `AppServiceProvider`. Um fake implementando o mesmo contrato prova que a lógica resolve inscrição→coordenada quando a base oficial chegar; a Fase 13 (HU-106) troca SÓ o binding.**

## Performance

- **Duration:** ~8 min
- **Completed:** 2026-06-14
- **Tasks:** 2 (Task 1 simples; Task 2 em TDD estrito RED→GREEN)
- **Files modified:** 7 (6 criados, 1 modificado)

## Accomplishments
- Contrato de resolução por inscrição imobiliária atrás de interface, espelhando o padrão `CnpjLookup`/`Geocoder`: `app(PropertyRegistryLookup::class)` resolve para `UnavailablePropertyRegistryLookup` (binding correto provado por `assertInstanceOf` no teste).
- Degradação honesta (anti-fachada): o provider real SEMPRE lança `PropertyRegistryUnavailableException` com a inscrição preservada e mensagem pt-BR "...pendente da SEDUR." — zero ponto inventado, jamais adaptador falso.
- Prova de que a lógica roda quando a base existir: um fake anônimo `implements PropertyRegistryLookup` resolve `'999'` em `PropertyRegistryResult(-12.97, -38.50, ...)` e o teste assevera latitude/longitude/inscricao/`toArray()` — o contrato é REAL, só o provider está indisponível.
- DTO `PropertyRegistryResult` readonly com `latitude`/`longitude` NOMEADAS (sem ambiguidade de ordem), `source` e `raw` (payload bruto para auditoria) e `toArray()` snake_case.
- Binding registrado no `AppServiceProvider` ao lado de `CnpjLookup`/`Geocoder`/`SpatialRepository` — a Fase 13 (HU-106) liga a base oficial (Cadastro/SEFAZ) trocando SÓ o binding, sem tocar call sites.

## Assinatura e shape do contrato (insumo de 07-05 e 07-06)

Interface:

```php
public function resolve(string $inscricao): PropertyRegistryResult;
// @throws PropertyNotFoundException quando a inscrição não existe na base
// @throws PropertyRegistryUnavailableException quando a base está indisponível/pendente
```

DTO `PropertyRegistryResult` (final readonly) e seu `toArray()` snake_case:

```php
new PropertyRegistryResult(
    float $latitude,
    float $longitude,
    string $inscricao,
    ?string $source = null,
    array $raw = [],
);

// toArray():
[
    'latitude'  => float,
    'longitude' => float,
    'inscricao' => string,
    'source'    => ?string,
    'raw'       => array,
]
```

Exceções tipadas (namespace `App\Services\Realty`):

| Exceção | Construtor | Mensagem pt-BR |
|---|---|---|
| `PropertyRegistryUnavailableException` | `(string $inscricao, ?string $motivo = null)` | "Resolução por inscrição imobiliária indisponível: a base de lotes (Cadastro) está pendente da SEDUR." |
| `PropertyNotFoundException` | `(string $inscricao)` | "Inscrição imobiliária {inscricao} não encontrada na base de lotes." |

## Decisão do binding

`AppServiceProvider::register()`:

```php
$this->app->bind(PropertyRegistryLookup::class, UnavailablePropertyRegistryLookup::class);
```

`UnavailablePropertyRegistryLookup::resolve()` lança `PropertyRegistryUnavailableException($inscricao)` incondicionalmente. A Fase 13 (HU-106) substitui APENAS este binding pelo provider conveniado (Cadastro Multifinalitário / SEFAZ) — nenhum call site (07-05 service, 07-06 endpoint) muda.

## Task Commits

1. **Task 1: contrato + DTO + 2 exceções** - `df78318` (feat)
2. **Task 2 (TDD): teste do contrato** - `11d68fc` (test — RED verificado: `Target [...PropertyRegistryLookup] is not instantiable`)
3. **Task 2 (TDD): provider indisponível + binding** - `4e43f16` (feat — GREEN: 3/3 testes verdes)

_TDD estrito na Task 2: RED (teste primeiro, falha pelo motivo certo — binding ausente) → GREEN (provider + binding) → pint._

## Files Created/Modified
- `app/Services/Realty/PropertyRegistryLookup.php` - Interface `resolve(string $inscricao): PropertyRegistryResult`
- `app/Services/Realty/PropertyRegistryResult.php` - DTO readonly (lat/lng nomeadas, source, raw; `toArray` snake_case)
- `app/Services/Realty/PropertyRegistryUnavailableException.php` - Indisponibilidade (base pendente SEDUR), carrega `inscricao` e `motivo`
- `app/Services/Realty/PropertyNotFoundException.php` - Inscrição inexistente na base de lotes
- `app/Services/Realty/UnavailablePropertyRegistryLookup.php` - Provider real: SEMPRE lança unavailable (nunca inventa ponto)
- `app/Providers/AppServiceProvider.php` - `bind(PropertyRegistryLookup::class, UnavailablePropertyRegistryLookup::class)`
- `tests/Feature/Viabilidade/PropertyRegistryLookupTest.php` - 3 testes (provider real indisponível; fake resolve coordenada; exceção de inexistência), herméticos (sem DB)

## Decisions Made
- **DTO espelha `GeocodeResult`, não `CnpjData`:** o resultado é uma coordenada, então adotei `latitude`/`longitude` NOMEADAS com o aviso de ordem (Pitfall de [lat,lng] vs [lng,lat]) e `raw` para o payload bruto da base de lotes (auditoria), em vez do shape cadastral do `CnpjData`.
- **Provider indisponível como provider REAL (não stub de teste):** seguindo `entrega-funcional`/`agentes-sile`, a indisponibilidade vive em produção como degradação honesta (`UnavailablePropertyRegistryLookup`), não como mock. O fake só existe DENTRO do teste para provar a lógica futura.
- **Teste sem `RefreshDatabase`:** o contrato não toca banco (resolve binding, fake e exceções) — teste hermético e rápido.

## Deviations from Plan
None - plano executado exatamente como escrito.

## Issues Encountered
- **Sessões paralelas no mesmo working directory (esperado):** o working tree continha alterações não commitadas de outros planos da Fase 7 (07-01: `FortifyServiceProvider`, `config/sile.php`, `ParameterSeeder`, seeders de teste; 07-03: `ViabilityQuery` + migration/factory `viability_queries`; 07-04: `ConsultaViabilidadeInput`). Mantive o foco no escopo do 07-02: `git add` por caminho explícito (nunca `-A`) e Pint escopado em `app/Services/Realty` + `app/Providers/AppServiceProvider.php` (evitando reformatar arquivos de outras sessões). Nenhum arquivo fora do `files_modified` do 07-02 entrou nos meus commits.

## User Setup Required
None - sem credenciais nem serviço externo. O contrato está propositalmente bloqueado: a resolução real por inscrição depende da base de lotes/Cadastro Multifinalitário (pendência SEDUR HU-033/HU-106).

## Next Phase Readiness
- **07-05 (ConsultaViabilidadeService):** consome `PropertyRegistryLookup` na entrada por inscrição (HU-055). Deve capturar `PropertyRegistryUnavailableException` e degradar com aviso (sugerir endereço), nunca inventar ponto; e `PropertyNotFoundException` quando a base existir e a inscrição não.
- **07-06 (endpoint):** o shape `PropertyRegistryResult::toArray()` (snake_case) é o contrato JSON da entrada por inscrição quando destravada.
- **Fase 13 (HU-106):** o provider oficial (Cadastro/SEFAZ) substitui APENAS o binding `PropertyRegistryLookup::class` no `AppServiceProvider` — muda a carga, não a lógica.

## Verification
- `php artisan test --compact --filter=PropertyRegistryLookupTest` → 3/3 verdes (11 asserções).
- `php artisan test --compact --exclude-group postgis` → 612/612 verdes (3110 asserções) — o binding novo é inerte (nenhum call site existente consome o contrato).
- `vendor/bin/pint` (escopado) → passed.
- Evidência anti-fachada: o provider real lança unavailable (zero ponto inventado); o fake prova que o contrato resolve quando a base existir.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*
