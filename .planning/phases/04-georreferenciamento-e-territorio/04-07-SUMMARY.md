---
phase: 04-georreferenciamento-e-territorio
plan: "04-07"
subsystem: ui
tags: [territorio, mapa, leaflet, react-leaflet, osm, inertia-v3, ssr-safe, useHttp, hu-030, hu-036, hu-037, sem-fachada, degradacao-comunicada]

# Dependency graph
requires:
  - phase: 04-06
    provides: "rotas gestao.territorio.index/identificar/validar-localizacao; props da página (camadas/sobreposicaoMinima/geocodingEnabled/mapa); contrato JSON do identificar (TerritoryResult) e do validar-localizacao (status/sobreposicao_percentual/limiar/motivo/alerta)"
  - phase: 04-03
    provides: "endpoint gestao.territorio.geocodificar + contrato JSON GeocodeResult (latitude/longitude/display_name/confidence/address); respostas 404/503/422 com {message}"
  - phase: 02.4-template-listagens
    provides: "PageHeader, Card/CardHeader/CardContent, Alert, Badge, Button, Input, Label; padrão de página da gestão"
  - phase: 02.4-02-console-sedur
    provides: "GestaoLayout (console SEDUR, variant console) com grupos de sidebar e item visível por permissão"
provides:
  - "MapImovel (resources/js/components/geo/map-imovel.tsx): componente Leaflet REUTILIZÁVEL (tiles OSM, marcador arrastável, clique-para-posicionar, overlay GeoJSON, recenter) — props { lat, lng, zoom?, draggable?, camadas?: {id,type,geojson}[], onMove? } — contrato para as Fases 7 e 8"
  - "MapaSection (resources/js/components/geo/mapa-section.tsx): wrapper de montagem client-side (lazy + guarda mounted) SSR-safe — única forma de montar o mapa numa página Inertia v3 com SSR"
  - "Página gestao/territorio/index: consulta territorial completa (geocodifica, identifica, valida sobreposição) sobre os endpoints reais via useHttp, comunicando zona/lote/camadas pendentes como Indisponível/Pendente SEDUR (sem valor fabricado)"
  - "MapPinIcon (icons/index.tsx) + item de navegação 'Consulta territorial' no console (visível por consultar-territorio)"
affects: [04-08, 07-consultas, 08-solicitacoes]

# Tech tracking
tech-stack:
  added:
    - "leaflet@1.9.4 (dependencies)"
    - "react-leaflet@5.0.0 (dependencies; requer React 19 — casa com o projeto)"
    - "@react-leaflet/core@3.0.0 (transitiva de react-leaflet)"
    - "@types/leaflet@1.9.21 (devDependencies; traz @types/geojson@7946.0.16)"
  patterns:
    - "Montagem client-side SSR-safe (Pitfall 9): React.lazy(import dinâmico) + guarda mounted/useEffect — o Leaflet (que acessa window) fica num chunk lazy próprio (map-imovel-*.js, 154kB) jamais alcançado no SSR do Inertia v3"
    - "Fix do ícone do marcador no Vite (Pitfall 10): import dos PNGs (marker-icon/2x/shadow) + L.Icon.Default.mergeOptions; import de leaflet/dist/leaflet.css; MapContainer com altura explícita (style height 420)"
    - "useHttp com transform(() => payload) SÍNCRONO antes do post: contorna a defasagem do dataRef (atualizado só em useEffect pós-render) e garante o envio da coordenada/polígono fresco — padrão para endpoints JSON dinâmicos no front"
    - "Erro de endpoint JSON tratado em onError (bag {message}/campo) E onHttpException (parse de response.data + return false para suprimir o modal de erro padrão do Inertia) — mensagem real do backend sempre exibida, nunca fachada"
    - "Degradação comunicada dirigida pelo dado: status indisponivel/pendente_fonte vira Badge 'Indisponível'/'Pendente SEDUR' + motivo do backend; nenhum valor é fabricado quando a base está pendente"

key-files:
  created:
    - resources/js/components/geo/map-imovel.tsx
    - resources/js/components/geo/mapa-section.tsx
    - resources/js/pages/gestao/territorio/index.tsx
  modified:
    - package.json
    - package-lock.json
    - resources/js/components/icons/index.tsx
    - resources/js/layouts/gestao-layout.tsx
    - tests/Feature/Geo/TerritoryPageTest.php

key-decisions:
  - "Mapa montado só no cliente via MapaSection (lazy + mounted): o build separa o leaflet num chunk lazy próprio (não entra no app nem no SSR-reachable graph); a página SSR renderiza apenas o skeleton — SSR do Inertia v3 a salvo (Pitfall 9)"
  - "transform(() => payload) antes do post no useHttp: o submit lê transformRef.current(dataRef.current) e o dataRef só atualiza em useEffect; o transform sobrescreve a fonte de dados de forma síncrona — único jeito seguro de enviar a coordenada/polígono recém-obtido no mesmo handler"
  - "Overlay de camadas (HU-036): o componente MapImovel ACEITA camadas {id,type,geojson} e renderiza <GeoJSON>, mas a página não passa geojson porque o endpoint index (04-06) só envia metadados (type/status/feature_count) — sem geometria pública exposta nesta rota. A capacidade de overlay fica entregue no componente reutilizável (Fases 7/8 ligam o geojson); a página NÃO fabrica geometria (sem fachada). TerritoryController não está em files_modified e não foi tocado."
  - "Validação (HU-037): o polígono informado é um quadrado mínimo (~33m) ao redor do ponto/ajuste — placeholder honesto do perímetro do imóvel nesta fase; a UI rotula como 'perímetro do imóvel'. Como o lote é pendente_fonte, o backend retorna indisponivel e a UI mostra Alert info com o motivo (sem fachada). Quando a base de lotes chegar, a mesma validação alerta de verdade."
  - "Marcador arrastável + clique no mapa => moverMarcador: reposiciona o ponto, limpa a validação anterior e reidentifica automaticamente (HU-037 ajuste). A validação de sobreposição é disparada pelo botão 'Validar localização' usando o ponto ajustado."
  - "Tipagem de import de PNG via /// <reference types=\"vite/client\" /> no map-imovel.tsx (o tsconfig não inclui vite/client por padrão) — sem novo d.ts fora do escopo"

patterns-established:
  - "Componente de mapa territorial reutilizável (MapImovel) + wrapper SSR-safe (MapaSection): base para o mapa das Fases 7 (consultas) e 8 (solicitações)"
  - "Página de consulta da gestão consumindo endpoints JSON reais via useHttp com tratamento de erro completo (onError + onHttpException) e degradação comunicada por status do dado"

# Metrics
duration: ~22 min
completed: 2026-06-13
---

# Fase 4 Plano 07: Mapa Leaflet e consulta territorial Summary

**UI do território (HU-030/HU-036/HU-037): mapa Leaflet reutilizável (react-leaflet v5, montado só no cliente para o SSR do Inertia v3) com marcador arrastável e overlay GeoJSON, e a página de "Consulta territorial" no console SEDUR que geocodifica, identifica bairro/via/restrições reais e valida a sobreposição com o lote pelo limiar parametrizado — comunicando zona/lote/camadas pendentes como "Indisponível"/"Pendente SEDUR" sem fabricar valor, sobre os endpoints reais do 04-03/04-06.**

## Performance

- **Duration:** ~22 min
- **Started:** 2026-06-13T23:07:00Z
- **Completed:** 2026-06-13T23:31:00Z
- **Tasks:** 2
- **Files modified/created:** 8 (3 criados, 5 modificados)

## Accomplishments

- **Componente Leaflet reutilizável** `MapImovel` com tiles OSM (atribuição ODbL), marcador arrastável (HU-037), clique-para-posicionar, overlay de camadas GeoJSON (HU-036), recenter ao mudar o ponto, e os dois pitfalls resolvidos: ícone do marcador corrigido para o bundle Vite (`L.Icon.Default.mergeOptions` sobre os PNGs importados + `leaflet/dist/leaflet.css` + altura explícita) — Pitfall 10.
- **Wrapper SSR-safe** `MapaSection` (Pitfall 9): `React.lazy` (import dinâmico) + guarda `mounted` com `useEffect`. O build confirma que o Leaflet fica num **chunk lazy próprio** (`map-imovel-*.js`, 154kB) separado do `app` e da página — nunca alcançado no SSR do Inertia v3 (a página renderiza só o skeleton no servidor).
- **Página `gestao/territorio/index`** (console SEDUR, layout persistente `Page.layout`): card de localização (geocodifica via `useHttp`, respeita `geocodingEnabled`, degrada com aviso quando desativado/erro), mapa interativo, painel de identificação dirigido por status (bairro/via/restrições reais; zona/lote como **Indisponível** + motivo pendente SEDUR), card de camadas (badge **Pendente SEDUR** para `pendente_fonte`) e validação de localização (HU-037) com o limiar parametrizado `sobreposicaoMinima`.
- **Consumo dos endpoints reais** via `useHttp` com `transform(() => payload)` síncrono (envia a coordenada/polígono fresco) e tratamento de erro completo (`onError` + `onHttpException` com `return false` para suprimir o modal padrão) — a mensagem real do backend é sempre exibida; **nenhum resultado é simulado no front**.
- **Navegação:** `MapPinIcon` novo + item "Consulta territorial" no grupo "Visão geral" do console, visível por `consultar-territorio`.

## Task Commits

Cada task foi commitada atomicamente (precedente 04-03/04-05/04-06):

1. **Task 1: Dependências Leaflet + MapImovel reutilizável + wrapper client-side + MapPinIcon** — `6b20a24` (feat)
2. **Task 2: Página de consulta territorial + navegação + teste de componente** — `4f619db` (feat)

**Plan metadata:** este SUMMARY — `docs(04-07): completa mapa leaflet e consulta territorial`.

_(STATE.md NÃO foi editado — consolidação é do orquestrador, conforme o objetivo do plano.)_

## Contrato entregue (para 04-08 / Fases 7 e 8)

### Componente reutilizável `MapImovel`

```
import { MapImovel, type MapImovelProps, type MapLayer } from '@/components/geo/map-imovel';
// SEMPRE montar via MapaSection (client-side, SSR-safe):
import { MapaSection } from '@/components/geo/mapa-section';

<MapaSection
  lat={number}            // [lat, lng] padrão Leaflet — converter p/ [lng, lat] ao chamar o backend (Pitfall 3)
  lng={number}
  zoom?={number}          // default 17
  draggable?={boolean}    // default true
  camadas?={MapLayer[]}   // { id, type, geojson: GeoJsonObject } — overlay GeoJSON (HU-036), Fases 7/8 ligam o geojson
  onMove?={(latlng: { lat: number; lng: number }) => void}  // arrastar marcador ou clicar no mapa
/>
```

- `MapaSection` é o ponto de entrada obrigatório em páginas Inertia (o `MapImovel` direto quebraria o SSR).
- O overlay (`camadas`) já funciona no componente; basta a página/endpoint fornecer o `geojson` (a rota `index` do 04-06 hoje só envia metadados, então a página do território não sobrepõe geometria — sem fachada).

### Como a página comunica pendências (sem fachada)

- **Identificação:** render dirigido por `status` de cada dimensão. `identificado` → nome (via também `distancia_m`); `nao_encontrado` → "Não encontrado neste ponto"; `indisponivel` → Badge "Indisponível" + `motivo` do backend. Hoje zona/lote vêm `indisponivel` (pendente SEDUR).
- **Camadas:** `pendente_fonte` → Badge "Pendente SEDUR" + "Sem base pública — aguardando a SEDUR"; vigentes → Badge com `status_label` + `feature_count`/versão. Nota fixa reforça que zona/lote seguem indisponíveis.
- **Validação:** `validado` (Alert success), `alerta_sobreposicao` (Alert warning com percentual e limiar), `indisponivel` (Alert info com o motivo do lote pendente).

## Files Created/Modified

- `resources/js/components/geo/map-imovel.tsx` — componente Leaflet reutilizável (OSM/ODbL, marcador arrastável, clique-para-posicionar, overlay GeoJSON, recenter, ícone/CSS/altura corrigidos)
- `resources/js/components/geo/mapa-section.tsx` — wrapper client-side SSR-safe (lazy + mounted + Suspense + skeleton 420px)
- `resources/js/pages/gestao/territorio/index.tsx` — página de consulta territorial (geocodificar/identificar/validar via useHttp; degradação comunicada)
- `package.json` / `package-lock.json` — leaflet@^1.9.4, react-leaflet@^5.0.0 (deps), @types/leaflet@^1.9.21 (dev)
- `resources/js/components/icons/index.tsx` — `MapPinIcon` (SVG inline no padrão dos demais)
- `resources/js/layouts/gestao-layout.tsx` — item "Consulta territorial" (grupo "Visão geral", visível por consultar-territorio)
- `tests/Feature/Geo/TerritoryPageTest.php` — `->component('gestao/territorio/index')` adicionado (fecha a pendência do precedente [03-04])

## Decisions Made

- **Montagem client-side via MapaSection (lazy + mounted):** o Inertia v3 tem SSR ligado (visto no dev server); o Leaflet acessa `window` na importação. O import dinâmico isola o Leaflet num chunk lazy (confirmado no build) e a guarda `mounted` renderiza só o skeleton no servidor — SSR a salvo sem desligar o SSR do projeto.
- **`transform(() => payload)` antes do `post`:** o `submit` do useHttp lê `transformRef.current(dataRef.current)`, e o `dataRef` só é atualizado num `useEffect` pós-render; chamar `setData` e `post` no mesmo handler enviaria dado defasado. `transform` reescreve a fonte de forma síncrona — envia a coordenada/polígono recém-obtido com segurança.
- **`onError` + `onHttpException`:** os erros do geocodificar (404/503/422 com `{message}`) e validações (422 com bag) são tratados nos dois callbacks; `onHttpException` retorna `false` para suprimir o modal de erro padrão do Inertia. A mensagem real do backend é sempre exibida (degradação comunicada, não fachada).
- **Overlay sem geojson na página:** a rota `index` (04-06) não expõe geometria; a página não fabrica geojson. A capacidade de overlay fica no componente reutilizável para as Fases 7/8 ligarem quando houver fonte (TerritoryController fora do escopo de files_modified, não tocado).
- **Polígono de validação = quadrado mínimo ao redor do ponto:** placeholder honesto do perímetro do imóvel nesta fase (o perímetro real vem da base cadastral); a UI rotula como "perímetro do imóvel" e o resultado é `indisponivel` enquanto o lote estiver pendente.
- **`/// <reference types="vite/client" />`:** habilita a tipagem dos imports de PNG do Leaflet sem criar d.ts fora do escopo.

## Deviations from Plan

None - plan executed exactly as written.

Observações de escopo (sem desvio): (1) o overlay de camadas é entregue como capacidade do componente reutilizável, mas a página não sobrepõe geometria porque o endpoint `index` do 04-06 não expõe geojson (sem fachada; TerritoryController não está em files_modified); (2) `app-sidebar.tsx` consta em files_modified mas não precisou de alteração — o `SidebarItem` já suporta `visible` e o item é adicionado no `gestao-layout.tsx`.

## Issues Encountered

- **Defasagem do `dataRef` no useHttp:** investigado na implementação compilada (`submit` lê `transformRef.current(dataRef.current)`, `dataRef` atualizado só em `useEffect`). Resolvido com `transform(() => payload)` síncrono antes do `post`.
- **Tipagem de import de PNG:** o tsconfig não inclui `vite/client`; resolvido com a diretiva `/// <reference types="vite/client" />` no `map-imovel.tsx`.

## User Setup Required

None - nenhuma configuração de serviço externo. A geocodificação usa o Nominatim público por padrão (parametrizável); o overlay de zona/lote "liga" quando a SEDUR entregar as camadas.

## Next Phase Readiness

- **Componente de mapa pronto e reutilizável** para as Fases 7 (consultas) e 8 (solicitações) — `MapImovel` + `MapaSection` (SSR-safe), com overlay GeoJSON disponível para ligar quando houver geometria.
- **Bloqueio mantido e visível:** zona urbanística (HU-031) e lote cadastral (HU-033/HU-037 RN-004/RN-005) aparecem como Indisponível/Pendente SEDUR na UI — nunca simulados. O orquestrador deve manter o bloqueio no STATE/ROADMAP.
- **Pendente para o fechamento (04-08):** validação visual (claro/escuro + mobile 375px) e o **smoke navegável** da página (incluindo o SSR runtime da rota autenticada e uma chamada REAL ao Nominatim em dev) — a regra "sem fachada" exige a evidência de integração real no fechamento da fase.

### Evidência fresca (sem fachada)

- **typecheck:** `npm run typecheck` (tsc --noEmit) — sem erros.
- **build:** `npm run build` — verde; `map-imovel-*.js` (154kB, leaflet) é um **chunk lazy próprio**, separado do `app` (320kB, sem variação relevante) e da página `territorio-*.js` — prova do isolamento client-side (Pitfall 9).
- **teste do plano:** `php artisan test --compact tests/Feature/Geo/TerritoryPageTest.php` → **7/7 (39 asserções)**, agora com `->component('gestao/territorio/index')`.
- **sem regressão no backend:** suíte completa **448/448 (2176 asserções)** fora do grupo postgis + **13/13 (61 asserções)** `@group postgis` (container `sile-pgsql` healthy) = 461 testes verdes; nenhum arquivo de backend foi alterado.
- **SSR do dev server saudável:** o dev server (Inertia v3 com SSR) renderiza todas as páginas sem erro; a rota do território renderiza o skeleton no servidor (leaflet nunca importado no SSR).

---
*Phase: 04-georreferenciamento-e-territorio*
*Completed: 2026-06-13*
