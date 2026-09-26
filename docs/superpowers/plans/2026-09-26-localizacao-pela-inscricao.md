# Localização do imóvel pela inscrição imobiliária — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Spec:** `docs/superpowers/specs/2026-09-26-localizacao-pela-inscricao-design.md`

**Goal:** Localizar o imóvel pela inscrição imobiliária (ponto e logradouro tributário do Cadastro SEFAZ), eliminar o polígono fictício da simulação REGIN, dar localização aos processos REGIN reais e bloquear a decisão automática quando o polígono desenhado diverge da zona da inscrição (HU-037 RN-005).

**Architecture:** `LocalizacaoInscricaoService` consulta a SEFAZ uma vez por inscrição e por execução. O processo ganha ponto gravado (`property_point_lat/lng`) e origem da localização (`property_location_source`); `ViabilityRequest::pontoDoImovel()` passa a ser a fonte única do ponto (centroide do polígono ou ponto gravado). O protocolo localiza pela inscrição quando falta polígono e grava o CODLOG tributário. O expresso ganha a checagem RN-005 entre o indeferimento territorial e as demais regras territoriais.

**Tech Stack:** Laravel 13, PHPUnit 12, PostGIS, Inertia v3, React 19.

## Global Constraints

- Nunca desenhar polígono em volta de um ponto; nunca inventar localização. SEFAZ indisponível degrada com auditoria.
- A via mais próxima só define `cod_log` quando não há logradouro tributário.
- Sem parâmetro novo no `ParameterSeeder` (RN-005 é binária por zona).
- Textos de motivo no `DecisionTextCatalog` + descrição no `DecisionTextSeeder`.
- Fakes do Cadastro SEFAZ só nos testes (`$this->app->instance(PropertyRegistryLookup::class, ...)`).
- pt-BR em mensagens, testes e comentários; `vendor/bin/pint --dirty --format agent` após PHP; `npm run build` e commit de `public/build` após TSX.
- Não commitar até o usuário pedir.

---

### Task 1: `LocalizacaoInscricaoService` (SEFAZ memorizada)

**Files:**
- Create: `app/Services/Realty/LocalizacaoInscricao.php` (DTO readonly)
- Create: `app/Services/Realty/LocalizacaoInscricaoService.php`
- Modify: `app/Providers/AppServiceProvider.php` (`scoped` binding do serviço)
- Create: `tests/Feature/Realty/LocalizacaoInscricaoServiceTest.php`

**Interfaces:**
- Produces: `LocalizacaoInscricaoService::localizar(?string $inscricao): LocalizacaoInscricao` com `status` ∈ `localizada | sem_coordenada | nao_encontrada | indisponivel | sem_inscricao`, `lat`, `lng`, `codLogTributario`, `logradouroTributario`.

- [ ] **Step 1: Testes que falham** — com fake de `PropertyRegistryLookup` contando chamadas:
  - `test_localiza_inscricao_com_ponto_e_logradouro_tributario` (lat/lng, `codLogTributario = '212'`, status `localizada`);
  - `test_inscricao_sem_coordenada_devolve_sem_coordenada_mas_mantem_o_logradouro`;
  - `test_cadastro_indisponivel_devolve_indisponivel`;
  - `test_inscricao_inexistente_devolve_nao_encontrada`;
  - `test_sem_inscricao_nao_consulta_a_sefaz` (0 chamadas);
  - `test_consulta_a_sefaz_uma_vez_por_inscricao_na_mesma_execucao` (2 chamadas a `localizar` → 1 ao fake).
- [ ] **Step 2: RED** — `php artisan test --compact tests/Feature/Realty/LocalizacaoInscricaoServiceTest.php` falha (classe inexistente).
- [ ] **Step 3: Implementar**

```php
final readonly class LocalizacaoInscricao
{
    public const LOCALIZADA = 'localizada';
    public const SEM_COORDENADA = 'sem_coordenada';
    public const NAO_ENCONTRADA = 'nao_encontrada';
    public const INDISPONIVEL = 'indisponivel';
    public const SEM_INSCRICAO = 'sem_inscricao';

    public function __construct(
        public string $status,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $codLogTributario = null,
        public ?string $logradouroTributario = null,
    ) {}

    public function temPonto(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }
}
```

`LocalizacaoInscricaoService::localizar()`: normaliza para dígitos; vazio → `SEM_INSCRICAO`; memo `array<string, LocalizacaoInscricao>`; `resolve()` com `PropertyNotFoundException` → `NAO_ENCONTRADA`, `PropertyRegistryUnavailableException` → `INDISPONIVEL`; `codLogTributario` = `ltrim(trim(raw['CodigoLogradouroTributario']), '0')` quando string/numérico não vazio; `LOCALIZADA` se lat/lng presentes, senão `SEM_COORDENADA`. Registrar com `$this->app->scoped(LocalizacaoInscricaoService::class)` para o memo durar só a requisição/job.

- [ ] **Step 4: GREEN** — mesmo comando passa.

---

### Task 2: Ponto e origem da localização no processo

**Files:**
- Create: migração `add_property_point_to_viability_requests_table` (`php artisan make:migration ... --no-interaction`)
- Create: `app/Enums/PropertyLocationSource.php` (`Poligono = 'poligono'`, `CadastroImobiliario = 'cadastro_imobiliario'`, `CatalogoSimulacao = 'catalogo_simulacao'`, `label()` em pt-BR)
- Modify: `app/Models/ViabilityRequest.php`
- Create: `tests/Unit/Models/ViabilityRequestPontoDoImovelTest.php` (ou feature, seguindo a pasta dos testes do model)

- [ ] **Step 1: Testes que falham**
  - `test_ponto_do_imovel_e_o_centroide_do_poligono_quando_existe` (mesmo cálculo do resolver);
  - `test_ponto_do_imovel_e_o_ponto_gravado_quando_nao_ha_poligono`;
  - `test_sem_poligono_e_sem_ponto_nao_ha_ponto_do_imovel`.
- [ ] **Step 2: RED**
- [ ] **Step 3: Implementar**
  - Migração: `decimal('property_point_lat', 10, 7)->nullable()`, `decimal('property_point_lng', 10, 7)->nullable()`, `string('property_location_source', 30)->nullable()` após `cod_log`; `down()` remove as três.
  - Model: colunas **fora** do fillable (escritas pelo sistema); casts `'property_point_lat' => 'float'`, `'property_point_lng' => 'float'`, `'property_location_source' => PropertyLocationSource::class`; método `pontoDoImovel(): ?array` com o centroide (média dos vértices únicos do anel exterior, sem o vértice de fechamento — copiar de `SolicitacaoViabilityResolver::centroid()`), senão o ponto gravado.
  - Atualizar o docblock do model (território materializado inclui ponto e origem).
- [ ] **Step 4: GREEN** + `php artisan migrate` no banco de testes pela suíte.

---

### Task 3: Motor e território usam `pontoDoImovel()`

**Files:**
- Modify: `app/Services/Solicitacao/SolicitacaoViabilityResolver.php` (`$ponto = $request->pontoDoImovel();`, remover `centroid()` privado)
- Modify: `app/Services/Geo/TerritorioProcessoService.php` (`materializar()` usa `$request->pontoDoImovel()`, remover `centroid()` privado)
- Modify: `tests/Feature/Expresso/FluxoExpressoTerritorialTest.php`

- [ ] **Step 1: Teste que falha (CA-L7)** — `test_regras_de_poligonal_avaliam_o_ponto_do_cadastro_quando_nao_ha_poligono`: processo sem polígono, com `property_point_*` gravado, CNAE fora da RT-01, `Http::fake` de feição vazia → **deferido** (hoje degrada para análise por "camada indisponível").
- [ ] **Step 2: RED**
- [ ] **Step 3: Implementar** as duas trocas.
- [ ] **Step 4: GREEN** + `php artisan test --compact tests/Feature/Expresso tests/Feature/Geo tests/Feature/Solicitacao`.

---

### Task 4: Protocolo localiza pela inscrição e grava o CODLOG tributário

**Files:**
- Modify: `app/Services/Geo/TerritorioProcessoService.php` (novos `localizarPelaInscricao()` e `aplicarLogradouroTributario()`)
- Modify: `app/Services/Solicitacao/ProtocolarSolicitacaoService.php`
- Create: `tests/Feature/Geo/LocalizacaoPelaInscricaoTest.php`

**Interfaces:**
- `TerritorioProcessoService::localizarPelaInscricao(ViabilityRequest): bool` — só sem polígono e sem ponto; `LOCALIZADA` grava ponto + `CadastroImobiliario`; qualquer outro status não grava e audita (`logName: 'geo'`, `event: 'localizacao-inscricao'`, `result: status`).
- `TerritorioProcessoService::aplicarLogradouroTributario(ViabilityRequest): bool` — com `codLogTributario`, grava em `cod_log` mesmo se já havia valor da via; se o valor anterior era diferente, audita `event: 'logradouro-tributario-diverge'` com os dois códigos.

- [ ] **Step 1: Testes que falham**
  - CA-L1 `test_processo_regin_sem_poligono_protocola_com_ponto_do_cadastro_imobiliario` (ponto, origem, zona/bairro materializados com `FakeSpatialRepository`);
  - CA-L2 `test_cadastro_indisponivel_protocola_sem_ponto_e_audita`;
  - CA-L3 `test_logradouro_tributario_sobrepoe_a_via_mais_proxima_e_audita_a_divergencia` (via fake devolve CODLOG 958; SEFAZ 212 → `cod_log = '212'`, auditoria com 958 e 212);
  - CA-L4 `test_sem_inscricao_mantem_o_codlog_da_via_mais_proxima`;
  - `test_processo_com_poligono_nao_consulta_ponto_na_sefaz_mas_aplica_o_logradouro_tributario`.
- [ ] **Step 2: RED**
- [ ] **Step 3: Implementar** — em `ProtocolarSolicitacaoService::protocol()`, após a transação e antes do `materializar`:

```php
$this->territorioProcesso->localizarPelaInscricao($request);

if ($request->zona_codigo === null || $request->bairro_oficial === null || $request->cod_log === null) {
    $this->territorioProcesso->materializar($request);
}

$this->territorioProcesso->aplicarLogradouroTributario($request);
```

Atualizar o comentário do bloco (rede de segurança + fonte do CODLOG). Portal com polígono também passa por `aplicarLogradouroTributario` (inscrição é informada no portal).

- [ ] **Step 4: GREEN** + `php artisan test --compact tests/Feature/Geo tests/Feature/Solicitacao tests/Feature/Regin`.

---

### Task 5: Simulação REGIN sem polígono fictício

**Files:**
- Modify: `app/Services/Regin/ReginProtocoloSimulacaoService.php` (remover `POLIGONO_HOMOLOGACAO` e `poligonoDoProtocolo()`; injetar `TerritorioProcessoService`)
- Modify: testes existentes da simulação que assumem polígono (`rg -l "POLIGONO_HOMOLOGACAO|property_polygon_geojson" tests/Feature/Regin tests/Feature/Risco`)
- Create/Modify: `tests/Feature/Regin/ReginProtocoloSimulacaoLocalizacaoTest.php`

- [ ] **Step 1: Testes que falham**
  - CA-L5a `test_simulacao_sem_ponto_no_catalogo_usa_o_ponto_do_cadastro_imobiliario` (sem polígono; origem `cadastro_imobiliario`);
  - CA-L5b `test_simulacao_sem_cadastro_usa_o_ponto_do_catalogo` (origem `catalogo_simulacao`);
  - CA-L5c `test_simulacao_sem_cadastro_e_sem_ponto_fica_sem_localizacao` (sem polígono, sem ponto — nunca o quadrado fixo);
  - CA-L6 `test_reproducao_do_processo_92_indefere_pela_rt01` (inscrição 344672 com tributário 212, CNAE 4681802, `TerritorialRuleSeeder` + `DecisionTextSeeder`) → `Indeferida`, motivo com "art. 172-A".
- [ ] **Step 2: RED**
- [ ] **Step 3: Implementar** em `criarProcesso()`: criar sem `property_polygon_geojson`; antes de `protocol()`, chamar `$this->territorioProcesso->localizarPelaInscricao($solicitacao)`; se continuar sem ponto e o catálogo tiver `ponto`, gravar com `forceFill` e origem `CatalogoSimulacao`. Atualizar o docblock (a zona da decisão simulada segue do catálogo via `territorioDoSnapshot`).
- [ ] **Step 4: GREEN** + ajustar os testes antigos que assumiam o polígono (trocar a asserção para ponto/origem; não remover testes) + `php artisan test --compact tests/Feature/Regin tests/Feature/Risco`.

---

### Task 6: HU-037 RN-005 — divergência de zona entre polígono e inscrição

**Files:**
- Create: `app/Services/Geo/LocalizacaoDivergenciaService.php`
- Modify: `app/Enums/TipoGatilho.php` (`LocalizacaoDivergente = 'localizacao_divergente'`, label "Localização divergente")
- Modify: `app/Services/Decisao/DecisionTextCatalog.php` + `database/seeders/DecisionTextSeeder.php` (`localizacao.motivo.divergencia_zona`, placeholders `:zona_poligono`, `:zona_inscricao`)
- Modify: `app/Services/Expresso/FluxoExpressoService.php`
- Create: `tests/Feature/Expresso/FluxoExpressoLocalizacaoDivergenteTest.php`

**Interfaces:**
- `LocalizacaoDivergenciaService::avaliar(ViabilityRequest): ?string` — motivo quando diverge; `null` quando coincide ou não dá para verificar (auditando `event: 'localizacao-divergencia'` com `result` ∈ `coincide | diverge | nao_verificada` e o porquê).
- Só roda com `property_location_source = poligono` (ou polígono presente sem origem, para processos antigos) **e** `LocalizacaoInscricao::temPonto()`. Zona de cada ponto via `TerritoryService::identify()`; compara `zona['nome']` só quando ambas têm `status = identificado`.

Texto: `Localização divergente (HU-037 RN-005): o polígono informado está na zona :zona_poligono e a inscrição imobiliária, na zona :zona_inscricao — segue para análise técnica.`

- [ ] **Step 1: Testes que falham**
  - CA-L8 `test_poligono_em_zona_diferente_da_inscricao_encaminha_analise_com_as_duas_zonas` (+ `ExpressoQueda` com `tipo_gatilho = localizacao_divergente`);
  - CA-L9a `test_mesma_zona_segue_o_fluxo_normal`;
  - CA-L9b `test_cadastro_indisponivel_nao_bloqueia_e_audita_verificacao_nao_realizada`;
  - CA-L10 `test_rt01_tem_precedencia_sobre_a_divergencia`;
  - `test_processo_sem_poligono_nao_passa_pela_verificacao`.
- [ ] **Step 2: RED**
- [ ] **Step 3: Implementar** — em `decidirSobLock()`, após `if ($territorial->status === INDEFERIR)` e antes de `ENCAMINHAR_ANALISE`:

```php
$divergencia = $this->localizacaoDivergencia->avaliar($request);

if ($divergencia !== null) {
    return $this->encaminharAnalise($request, $divergencia, $actor, $resolved, gatilho: TipoGatilho::LocalizacaoDivergente);
}
```

Estender `encaminharAnalise()`/`capturarQueda()` com o gatilho opcional (linha de nível-processo, `dimensao = 'localizacao'`), sem mudar o comportamento dos chamadores atuais. Atualizar o docblock da classe com a nova etapa e a precedência (spec §3.7).

- [ ] **Step 4: GREEN** + `php artisan test --compact tests/Feature/Expresso tests/Feature/Decisao`.

---

### Task 7: Tela do processo mostra ponto e origem

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php` (`geo.ponto` = `pontoDoImovel()` quando não há polígono; `geo.origem` = `{value, label}` da origem ou `null`)
- Modify: `resources/js/pages/gestao/processos/show.tsx`
- Modify: teste de feature da tela do processo (`rg -l "processos/show|gestao.processos.show" tests`)

- [ ] **Step 1: Teste que falha (CA-L11)** — Inertia assert: processo sem polígono com ponto → `geo.ponto` com lat/lng e `geo.origem.value = cadastro_imobiliario`.
- [ ] **Step 2: RED**
- [ ] **Step 3: Implementar** — controller; na tela, `MapaSection` com `lat/lng` do ponto e `camadas={[]}` quando não há polígono, e legenda "Localização pelo Cadastro Imobiliário (ponto, sem o desenho do lote)" conforme a origem; sem ponto nem polígono, o `EmptyState` atual.
- [ ] **Step 4: GREEN** + `npm run build`.

---

### Task 8: Registrar a pendência SEDUR

**Files:**
- Modify: `.planning/STATE.md`

- [ ] Registrar: (1) base de lotes com inscrição imobiliária e cobertura total (cadastro multifinalitário ou `inscricoes_imobiliaria` do `ws-sigs`) — pendência SEDUR, bloqueia o polígono do lote e a HU-037 RN-004; (2) decisão: logradouro pelo tributário SEFAZ e ponto pela SEFAZ (spec 2026-09-26); (3) HU-037 RN-005 implementada por zona; via não comparada por geometria (motivo na spec §3.6).

---

### Task 9: Verificação final

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact tests/Feature/Expresso tests/Feature/Louos tests/Feature/Regin tests/Feature/Geo tests/Feature/Realty tests/Feature/Solicitacao tests/Feature/Viabilidade tests/Feature/Decisao`
- [ ] Perguntar ao usuário se roda a suíte completa (`php artisan test --compact`).
- [ ] `guardiao-entrega` com os pareceres de `especialista-seguranca` (dado pessoal da SEFAZ em auditoria: só inscrição e códigos, nunca proprietário) e `especialista-acessibilidade` (legenda do mapa).
- [ ] Pós-deploy (com pedido do usuário): refazer a simulação do #92 e confirmar o indeferimento.
