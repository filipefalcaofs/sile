# Polígono do lote pelo SEMAP, em cadeia com o Endereçamento — Plano de implementação

> **Para agentes:** usar `superpowers:subagent-driven-development` ou `superpowers:executing-plans`, tarefa a tarefa. TDD estrito: teste falhando antes de qualquer código de produção.

**Spec:** `docs/superpowers/specs/2026-10-03-poligono-lote-semap-fallback-design.md`

**Objetivo:** alimentar o polígono do lote no processo por uma cadeia de fontes oficiais — Endereçamento SEDUR primeiro, SEMAP (Inscrições Geo) por inscrição como fallback — para que a zona saia da maior porção do terreno (art. 18, § 2º, da Lei 9.148/2016) em vez do ponto.

**Stack:** Laravel 13 / PHP 8.5, PostgreSQL + PostGIS (produção), SQLite na suíte, PHPUnit 12.

## Restrições globais

- Colunas novas em `viability_requests`, **fora do `fillable`**: `lote_poligono_geojson`, `lote_poligono_fonte`, `lote_poligono_consultado_em`.
- `property_polygon_geojson` (desenho do requerente) não muda de significado nem de conteúdo.
- Nenhuma camada com dado pessoal é consultada. Só a camada 18.
- Toda consulta externa fora da transação e fora do caminho da resposta.
- `vendor/bin/pint --dirty --format agent` antes de cada commit com PHP.
- Commits em português, sem coautoria nem menção a ferramenta ou IA.
- Rodar só os testes da tarefa a cada passo; a suíte inteira na última tarefa, depois de perguntar ao usuário.
- `TestCase` desliga integrações HTTP por padrão: os testes do cliente ligam o toggle e usam `Http::fake`.

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/*_add_lote_poligono_to_viability_requests_table.php` (criar) | colunas do polígono do lote |
| `app/Enums/LotePoligonoFonte.php` (criar) | `enderecamento` / `semap` |
| `app/Models/ViabilityRequest.php` (alterar) | casts das colunas novas |
| `app/Services/Geo/PoligonoLoteHit.php` (criar) | resultado de uma fonte |
| `app/Services/Geo/PoligonoLoteFonte.php` (criar) | contrato de fonte |
| `app/Services/Geo/SemapPoligonoLoteFonte.php` (criar) | ArcGIS camada 18 por inscrição |
| `app/Services/Geo/PoligonoLoteResolver.php` (criar) | percorre as fontes em ordem |
| `app/Services/Geo/PoligonoLoteService.php` (criar) | grava no processo e audita |
| `app/Services/Geo/ZonaLimitrofeService.php` (alterar) | aceita o polígono oficial |
| `app/Services/Solicitacao/SolicitacaoViabilityResolver.php` (alterar) | precedência oficial → desenho |
| `app/Services/Geo/CamadasIncidentesService.php` (alterar) | mesma precedência no inventário |
| `app/Services/Expresso/FluxoExpressoService.php` (alterar) | mesma precedência e divergência de zona |
| `app/Services/Solicitacao/ProtocolarSolicitacaoService.php` (alterar) | busca após o commit |
| `app/Jobs/ProtocolarRecebimentoReginJob.php` (alterar) | busca antes de decidir |
| `config/sile.php`, `database/seeders/ParameterSeeder.php`, `app/Support/ApiIntegracaoCatalog.php` (alterar) | interruptor e URL |
| `tests/Support/Geo/FakePoligonoLoteFonte.php` (criar) | fake de fonte para a suíte |

---

### Tarefa 1: Colunas e enum da fonte

**Arquivos:** migration, `app/Enums/LotePoligonoFonte.php`, `app/Models/ViabilityRequest.php`, teste `tests/Feature/Geo/LotePoligonoSchemaTest.php`.

- [ ] Teste que falha: criar processo, `forceFill` das três colunas, `fresh()` devolve array no GeoJSON, enum em `lote_poligono_fonte` e `CarbonInterface` na data; as colunas não são preenchíveis por `create()`.
- [ ] Migration aditiva: `json('lote_poligono_geojson')->nullable()`, `string('lote_poligono_fonte')->nullable()`, `timestamp('lote_poligono_consultado_em')->nullable()`.
- [ ] `enum LotePoligonoFonte: string { Enderecamento = 'enderecamento'; Semap = 'semap'; label(): string }`.
- [ ] Casts: `'lote_poligono_geojson' => 'array'`, `'lote_poligono_fonte' => LotePoligonoFonte::class`, `'lote_poligono_consultado_em' => 'datetime'`.
- [ ] Verificar: `php artisan test --compact --filter=LotePoligonoSchema`.

### Tarefa 2: Interruptor e URL da camada

**Arquivos:** `config/sile.php`, `database/seeders/ParameterSeeder.php`, `app/Support/ApiIntegracaoCatalog.php`, `tests/TestCase.php`, teste `tests/Feature/Geo/SemapParametrosTest.php`.

- [ ] Teste que falha: `features.arcgis_semap` existe no catálogo de parâmetros com default ligado; `config('sile.integrations.arcgis_semap.query_url')` aponta para a camada 18; a aba de integrações expõe interruptor e URL.
- [ ] `config/sile.php`: `features.arcgis_semap => true`; bloco `integrations.arcgis_semap` com `query_url` (`https://geo.salvador.ba.gov.br/arcgis/rest/services/MAPA_BASE/MAPA_BASE_SEFAZ/MapServer/18/query`), `timeout => 20`, `user_agent`.
- [ ] `ParameterSeeder`: `features.arcgis_semap` (booleano) e `integrations.arcgis_semap.query_url` (url), com descrição citando a camada e a nota técnica.
- [ ] `ApiIntegracaoCatalog`: aba ou campos do SEMAP, seguindo o padrão das abas existentes.
- [ ] `tests/TestCase.php`: desligar `sile.features.arcgis_semap` por padrão, como já é feito com `geoserver_zona`.
- [ ] Verificar: `php artisan test --compact --filter=SemapParametros`.

### Tarefa 3: Resultado e contrato de fonte

**Arquivos:** `app/Services/Geo/PoligonoLoteHit.php`, `app/Services/Geo/PoligonoLoteFonte.php`, teste `tests/Unit/Geo/PoligonoLoteHitTest.php`.

- [ ] Teste que falha: construtores nomeados `encontrado`, `naoEncontrado`, `ambiguo`, `indisponivel`; `temPoligono()` só no primeiro; `toArray()` com `fonte`, `status`, `motivo` e `candidatas`.
- [ ] `final readonly class PoligonoLoteHit` com `ENCONTRADO`, `NAO_ENCONTRADO`, `AMBIGUO`, `INDISPONIVEL`, `?array $geojson`, `LotePoligonoFonte $fonte`, `array $propriedades`, `?string $motivo`, `int $candidatas`.
- [ ] `interface PoligonoLoteFonte { public function fonte(): LotePoligonoFonte; public function buscar(ViabilityRequest $request): PoligonoLoteHit; }`.
- [ ] Verificar: `php artisan test --compact --filter=PoligonoLoteHit`.

### Tarefa 4: Fonte SEMAP (CA-SM-01 a CA-SM-08)

**Arquivos:** `app/Services/Geo/SemapPoligonoLoteFonte.php`, teste `tests/Feature/Geo/SemapPoligonoLoteFonteTest.php`.

- [ ] Testes que falham, com `Http::fake` e o toggle ligado, cobrindo um CA cada: uma feição grava o GeoJSON; zero feições é `nao_encontrado`; processo sem inscrição não chama (`Http::assertNothingSent`); HTTP 500 e `ConnectionException` são `indisponivel` com motivo; toggle desligado é `indisponivel` sem chamada; duas geometrias distintas é `ambiguo` com `candidatas = 2`; duas idênticas é `encontrado`; `LineString` não vira polígono; duas chamadas ao mesmo processo fazem uma requisição só.
- [ ] Fixture do teste: a resposta real medida para a inscrição 344672 (Polygon de 5 vértices em 4326).
- [ ] Implementação: `where=INSCRICAO='<dígitos>'`, `returnGeometry=true`, `outSR=4326`, `outFields=INSCRICAO,CODLOG,PORTA`, `f=geojson`; `timeout` do config, `connectTimeout(3)`, sem retry; memo por inscrição; aceitar só `Polygon`/`MultiPolygon`; comparar geometrias por `json_encode` normalizado para decidir ambiguidade; **nunca** ler campo fora dos três `outFields`.
- [ ] Verificar: `php artisan test --compact --filter=SemapPoligonoLoteFonte`.

### Tarefa 5: Resolvedor da cadeia (CA-CD-01 a CA-CD-04)

**Arquivos:** `app/Services/Geo/PoligonoLoteResolver.php`, `tests/Support/Geo/FakePoligonoLoteFonte.php`, teste `tests/Feature/Geo/PoligonoLoteResolverTest.php`.

- [ ] Testes que falham: a primeira fonte com polígono vence e a segunda não é chamada; `nao_encontrado` e `ambiguo` caem para a próxima; `indisponivel` também cai, e o motivo de cada fonte fica no resultado; todas sem polígono devolvem `nao_encontrado`; a ordem das fontes é a registrada.
- [ ] Implementação: recebe `iterable<PoligonoLoteFonte>` na ordem Endereçamento → SEMAP; devolve o `PoligonoLoteHit` vencedor e a lista `tentativas` (fonte + status + motivo).
- [ ] Registro no `AppServiceProvider`: por ora só o SEMAP; quando o plano de 02/10 entregar o cliente do Endereçamento, ele é registrado **antes** sem tocar no resolvedor.
- [ ] Verificar: `php artisan test --compact --filter=PoligonoLoteResolver`.

### Tarefa 6: Gravação no processo e auditoria (CA-PL-01, CA-CD-04)

**Arquivos:** `app/Services/Geo/PoligonoLoteService.php`, teste `tests/Feature/Geo/PoligonoLoteServiceTest.php`.

- [ ] Testes que falham: `encontrado` grava as três colunas e audita `geo/poligono-lote` com fonte, tentativas e contagem; `nao_encontrado` não grava polígono, grava a data da consulta e audita com o motivo; polígono já gravado não é reconsultado (idempotência); falha de auditoria não derruba a gravação.
- [ ] Implementação: `materializar(ViabilityRequest): PoligonoLoteHit`, `forceFill` das colunas, auditoria com `logName: 'geo'`, `event: 'poligono-lote'`, `rulesVersion: 'lei-9148-2016-art18'`.
- [ ] Verificar: `php artisan test --compact --filter=PoligonoLoteService`.

### Tarefa 7: Zona por área a partir do polígono oficial (CA-ZN-01 a CA-ZN-03)

**Arquivos:** `app/Services/Geo/ZonaLimitrofeService.php` (já existe), `app/Services/Solicitacao/SolicitacaoViabilityResolver.php`, `app/Services/Geo/CamadasIncidentesService.php`, `app/Services/Expresso/FluxoExpressoService.php`, testes em `tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php` e `tests/Feature/Geo/CamadasIncidentesServiceTest.php`.

- [ ] Testes que falham: processo com `lote_poligono_geojson` zoneia por ele mesmo tendo desenho diferente; sem polígono oficial usa o desenho; sem os dois segue pelo ponto e o motivo da geometria ausente aparece no encaminhamento.
- [ ] Implementação: um único método de precedência (`poligonoParaZoneamento(ViabilityRequest): ?array`) consumido pelos três chamadores, evitando repetir a regra.
- [ ] Verificar: `php artisan test --compact --filter='SolicitacaoViabilityResolver|CamadasIncidentesService'`.

### Tarefa 8: Busca no protocolo e no recebimento REGIN (CA-PL-02, CA-PL-03)

**Arquivos:** `app/Services/Solicitacao/ProtocolarSolicitacaoService.php`, `app/Jobs/ProtocolarRecebimentoReginJob.php`, testes `tests/Feature/Solicitacao/ProtocolarSolicitacaoTest.php` e `tests/Feature/Regin/RecebimentoProcessoTest.php`.

- [ ] Testes que falham: ao protocolar, o polígono é buscado **depois** do commit (fonte que lança não desfaz o protocolo nem o número gerado); no job do REGIN, o polígono é buscado antes de `expresso->decide` e a zona sai por área.
- [ ] Implementação: chamar `PoligonoLoteService::materializar` junto de `localizarPelaInscricao`, já fora da transação; no job, antes da decisão.
- [ ] Verificar: `php artisan test --compact --filter='ProtocolarSolicitacao|RecebimentoProcesso'`.

### Tarefa 9: Divergência entre polígono oficial e desenho (decisão 11)

**Arquivos:** `app/Services/Geo/LocalizacaoDivergenciaService.php`, `app/Enums/TipoGatilho.php`, `app/Services/Expresso/FluxoExpressoService.php`, teste `tests/Feature/Expresso/FluxoExpressoDivergenciaPoligonoTest.php`.

- [ ] Testes que falham: zonas diferentes entre o polígono oficial e o desenho encaminham à análise com as duas zonas no motivo e a zona gravada é a do oficial; zonas iguais seguem o fluxo com a coincidência auditada.
- [ ] Implementação espelhando a RN-005 já existente, na precedência da spec (indeferimento territorial → empate → divergência).
- [ ] Verificar: `php artisan test --compact --filter=FluxoExpressoDivergenciaPoligono`.

### Tarefa 10: Interseção real contra PostGIS (`@group postgis`)

**Arquivos:** teste `tests/Feature/Geo/PostgisZonaMaiorPorcaoTest.php` (estende `PostgisTestCase`).

- [ ] Testes que falham: lote partido entre duas zonas devolve as porções com a soma das áreas igual à área do lote (tolerância de 0,01 m²) e a vencedora é a de maior área; lote inteiro numa zona devolve 100%; polígono sem interseção devolve `sem_intersecao`.
- [ ] Usar o polígono real medido da inscrição 344672 como fixture do lote.
- [ ] Verificar: `php artisan test --compact --group postgis`.

### Tarefa 11: UI e documentação

**Arquivos:** `app/Http/Resources/ProcessoResource.php`, `resources/js/components/analise/processo-ui.tsx`, `resources/js/components/geo/camadas-imovel.tsx`, `resources/js/pages/gestao/processos/show.tsx`, `docs/integracoes/geoserver-sedur.md`, `.planning/STATE.md`.

- [ ] Teste que falha: o recurso do processo expõe `lote_poligono` com a fonte; a lista de camadas mostra a fonte da zona e o percentual de cada porção.
- [ ] Implementação, `npm run build` e commit de `public/build` se houver mudança em TSX.
- [ ] Registrar no `STATE.md`: fonte nova ligada, pendências 5, 6, 8 e 9 da nota técnica em aberto.
- [ ] Verificar: `npx vitest run` nos testes tocados e `php artisan test --compact --filter=ProcessoResource`.

### Tarefa 12: Suíte completa

- [ ] Perguntar ao usuário antes de rodar.
- [ ] `php artisan test --compact` e `php artisan test --compact --group postgis` com o container de pé.
- [ ] `vendor/bin/pint --dirty --format agent` limpo.
