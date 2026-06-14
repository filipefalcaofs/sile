---
phase: 07-consulta-previa-viabilidade
plan: 08
subsystem: ui
tags: [consulta-viabilidade, inertia-react, useHttp, leaflet, portal-publico, anti-fachada, veredito-honesto, acessibilidade, hu-054, hu-055, hu-056, hu-057, hu-058, hu-059]

# Dependency graph
requires:
  - phase: 07-consulta-previa-viabilidade
    plan: 06
    provides: "Página portal.viabilidade.index (prop consultaEnabled) + 3 endpoints JSON (endereco/cnae/inscricao) com contrato ConsultaViabilidadeResult::toArray()"
  - phase: 07-consulta-previa-viabilidade
    plan: 04
    provides: "Shape exato do toArray() (10 chaves: entrada/geocode/territorio/enquadramento/risco/restricoes/veredito_locacional/fundamentacao/avisos/versoes) + veredito propagado"
  - phase: 04-georreferenciamento
    provides: "MapaSection/MapImovel (Leaflet SSR-safe, lazy) reusados para exibir o ponto; padrão useHttp + mensagemDoErro/mensagemDaExcecao do gestao/territorio"
provides:
  - "Página pública portal/viabilidade/consulta.tsx (3 entradas honestas + mapa reusado + resultado real via useHttp), layout standalone público"
  - "Componente reutilizável ResultadoViabilidade (risco/enquadramento Q7/território/veredito honesto/fundamentação/avisos) + tipos TS do contrato (ResultadoConsulta)"
  - "Veredito locacional honesto na UI: pendente sempre com motivo; NUNCA Permitido/Não permitido sem zona"
  - "Landing aponta o card 'Consulta de viabilidade' para /portal/viabilidade"
  - "Asserção Inertia reativada: component('portal/viabilidade/consulta') + reflexo do toggle"
affects: [07-09-historico (reusa ResultadoViabilidade), 07-10-smoke (consome a página pública)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Página pública standalone (padrão home.tsx): ThemeProvider + AccessibilityBar + header próprio em Consulta.layout (layout persistente Inertia) — NÃO usa PortalLayout (autenticado)"
    - "useHttp único com transform() síncrono por aba: 1 estado de processing/erro, 1 result; corpo da aba ativa montado fresco (precedente 04-07)"
    - "Erros honestos por código: onError (422 validação/toggle via mensagemDoErro) + onHttpException (404/503/429) — 429 com mensagem pt-BR amigável"
    - "Reuso do mapa Leaflet (MapaSection draggable={false}) só para exibição — a consulta pública não reposiciona o ponto"
    - "Veredito renderizado pelo label propagado do backend; estilo por resultado com 'pendente' visualmente distinto (info), nunca herdando aparência de permitido/não permitido"

key-files:
  created:
    - resources/js/components/viabilidade/resultado-viabilidade.tsx
    - resources/js/pages/portal/viabilidade/consulta.tsx
    - tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php
  modified:
    - resources/js/pages/home.tsx

key-decisions:
  - "Layout standalone público em Consulta.layout (ThemeProvider + AccessibilityBar + header próprio), conciliando 'padrão home.tsx' (plano) com 'layout persistente Page.layout' (constraint): persistente e público, sem PortalLayout autenticado"
  - "CNAE e área compartilhados entre as 3 abas (trocar de aba preserva a atividade); endereço/inscrição são específicos da aba — menos atrito"
  - "ResultadoViabilidade recebe result não-nulo (o pai controla o estado vazio), com tipos TS exportados (ResultadoConsulta) reusados pela página e pelo histórico (07-09)"
  - "Veredito mostra o label do backend; quando pendente sem motivo (defensivo) exibe fallback 'Depende de análise técnica da SEDUR.' — nunca renderiza Permitido/Não permitido sob pendente"
  - "Avisos como Alert variant=warning; abas CNAE/inscrição com Alert variant=info explicando a limitação ANTES de consultar (degradação comunicada)"

patterns-established:
  - "ResultadoViabilidade é a VIEW única do ConsultaViabilidadeResult: usada na consulta pública (07-08) e no histórico (07-09) — uma só fonte de renderização honesta"
  - "Risco sanitário humanizado no front (baixo/medio/alto → Baixo/Médio/Alto Risco) com fallback genérico, pois o JSON carrega só o value (nivel_final), não o label"

# Metrics
duration: ~15 min
completed: 2026-06-14
---

# Phase 7 Plan 08: UI Pública da Consulta de Viabilidade Summary

**A consulta prévia ganhou sua porta PÚBLICA visual: `portal/viabilidade/consulta.tsx` (standalone, portal light) com as 3 entradas honestas (endereço com mapa/geocode, CNAE, inscrição com aviso de indisponível), consumindo os endpoints reais `/portal/viabilidade/*` via `useHttp` (nada simulado no front), reusando o mapa Leaflet da Fase 4 (`MapaSection`, só exibição) para o ponto, e renderizando o resultado real pelo novo componente `ResultadoViabilidade` — risco municipal/sanitário + encaminhamento (HU-058), enquadramento Quadro 7 (HU-057), bairro/via/zona/lote/restrições (HU-059) e o veredito locacional HONESTO (HU-054): pendente SEMPRE com o motivo (zona pendente SEDUR), NUNCA Permitido/Não permitido sem o dado oficial. Erros honestos (404/503/429/422/toggle) comunicados, toggle desligado degrada com aviso, e a landing passa a apontar o card de consulta para `/portal/viabilidade`. Provado por 2 feature tests Inertia (render do componente + reflexo do toggle) e por typecheck/build verdes.**

## Performance

- **Duration:** ~15 min
- **Completed:** 2026-06-14
- **Tasks:** 3 (Task 1 componente; Task 2 página + landing; Task 3 feature test)
- **Files:** 4 (3 criados, 1 modificado) — ZERO dependência nova (DS próprio + Leaflet já instalado)

## Accomplishments

- **Componente `ResultadoViabilidade`** (export nomeado) + tipos TS do contrato (`ResultadoConsulta` e sub-tipos), reutilizável pelo histórico (07-09).
- **Página pública** com seletor de 3 abas (Endereço | CNAE | Inscrição), forms acessíveis (Label/Input/Button do DS), mapa reusado para o ponto e o resultado real abaixo.
- **3 entradas via `useHttp`** consumindo os endpoints reais (sem mock): `/portal/viabilidade/endereco|cnae|inscricao`, com `transform()` síncrono por aba.
- **Honestidade na UI:** veredito pendente com motivo; zona/lote como "Indisponível — pendente SEDUR"; avisos de CNAE-sem-local e inscrição-indisponível; toggle off degrada com Alert.
- **Landing:** card "Consulta de viabilidade" agora linka `/portal/viabilidade` (afordância "Consultar agora →").

## Task Commits

1. **Task 1: componente ResultadoViabilidade (honesto)** — `c1550b7` (feat, HU-057/058/059)
2. **Task 2: página pública consulta.tsx (3 entradas + mapa + useHttp) + landing** — `6b7559c` (feat, HU-054/055/056)
3. **Task 3: feature test da página (render + toggle)** — `90a48b0` (test, HU-054/014)

**Plan metadata:** `docs(07-08)` (este SUMMARY).

## Contrato TS do resultado (insumo direto de 07-09)

Tipado em `resources/js/components/viabilidade/resultado-viabilidade.tsx` (exportado), espelhando `ConsultaViabilidadeResult::toArray()` (07-04):

```ts
interface ResultadoConsulta {
    entrada: { tipo: 'endereco'|'cnae'|'inscricao'; cnae: string; cnae_formatado?: string; area?: number|null; endereco?: string|null; inscricao?: string|null };
    geocode: { latitude: number; longitude: number; display_name: string; confidence: number|null; address: Record<string, unknown> } | null;
    territorio: { bairro; via; zona; lote: DimensaoTerritorial; restricoes: RestricoesTerritoriais } | null;
    enquadramento: { quadro7: { status; grupo; subgrupo; faixa: {area_min; area_max|null}|null; motivo? }; quadro10; quadro11; quadro11a; consolidado; versoes };
    risco: { municipal: {status; nivel; nivel_label}; sanitario: {status; nivel_final}; encaminhamento: {fluxo: 'expresso'|'analise'; motivo}; fundamentacao: string[]; versoes };
    restricoes: RestricoesTerritoriais | null;
    veredito_locacional: { resultado: 'permitido'|'permitido_com_condicoes'|'nao_permitido'|'pendente'; label: string; motivo: string|null };
    fundamentacao: string[];
    avisos: string[];
    versoes: Record<string, Record<string, string|null>>;
}
```

`ResultadoViabilidade` consome esse JSON e renderiza, com os componentes do DS (Card/Badge/Alert):
- **Veredito** (destaque): `label` propagado; pendente sempre com `motivo` (fallback "Depende de análise técnica da SEDUR."); estilo `info` distinto de permitido/condições/não permitido.
- **Risco (HU-058):** badge `municipal.nivel_label` (ou "Não classificado"), `sanitario.nivel_final` humanizado (ou "Não classificado"), encaminhamento `expresso`/`analise` + `motivo`.
- **Enquadramento (HU-057):** Quadro 7 grupo/subgrupo + faixa quando `identificado`; "Sem enquadramento" + motivo quando `nao_encontrado`.
- **Território (HU-059):** bairro/via/zona/lote/restrições reusando a lógica de status do `gestao/territorio` (identificado/não encontrado/indisponível com motivo).
- **Fundamentação** (lista) e **Avisos** (Alerts).

## Decisão de layout (standalone público)

A página é **standalone pública** (padrão `home.tsx`), montada via `Consulta.layout` (layout persistente do Inertia): `ThemeProvider` + `AccessibilityBar` (eMAG) + header próprio (Logo + alternância de tema + "Entrar"). **Não** usa `PortalLayout` (autenticado). Concilia a constraint "layout persistente Page.layout" com "padrão home.tsx, não PortalLayout": persistente, público e acessível, sem sessão.

## Files Created/Modified

- `resources/js/components/viabilidade/resultado-viabilidade.tsx` — componente honesto + tipos TS do contrato (reusado por 07-09).
- `resources/js/pages/portal/viabilidade/consulta.tsx` — página pública (3 abas + useHttp + MapaSection + ResultadoViabilidade + tratamento de erros/toggle), `Consulta.layout` standalone.
- `resources/js/pages/home.tsx` — `ServiceItem.href` + card "Consulta de viabilidade" → `/portal/viabilidade` (Link + afordância).
- `tests/Feature/Viabilidade/ConsultaViabilidadePaginaTest.php` — 2 testes: render do componente Inertia + reflexo do toggle.

## Deviations from Plan

Nenhum bug, correção crítica, bloqueio ou mudança arquitetural. Variações mínimas, todas dentro dos `files_modified` do 07-08 (zero scope creep, zero dependência nova):

- **[Decisão de layout]** Usado `Consulta.layout` (layout persistente) com `PublicShell` inline em vez de wrap inline no corpo como `home.tsx` faz — atende explicitamente a constraint "layout persistente Page.layout" mantendo o visual/identidade do `home.tsx` (mesmo header/tema/acessibilidade). Sem componente novo extraído (mantém os 4 `files_modified`).
- **[Aditivo de honestidade]** Abas CNAE e Inscrição exibem um `Alert variant=info` explicando a limitação ANTES de consultar (além do `avisos[]` que a resposta traz) — reforça a degradação comunicada sem inventar resultado.
- **[Humanização]** Como o JSON do risco sanitário carrega só o `nivel_final` (value), o front mapeia baixo/medio/alto → "Baixo/Médio/Alto Risco" com fallback genérico (`humanizar`) — fiel ao enum `RiscoSanitario`, sem reescrever shape do backend.

**Total:** 0 correções; 3 variações aditivas. **Impacto:** nenhum scope creep; backend (07-06/07-07) intocado.

## Issues Encountered

- **Baseline de testes maior que o citado no plano (639):** o 07-07 (histórico HU-060) já estava no HEAD quando este plano executou, elevando a suíte. Verificado que não há regressão: `--exclude-group postgis` = **648/648 verde** (639 do 07-06 + testes do 07-07 + 2 novos do 07-08).

## Verification

- `npx tsc --noEmit` → **verde** (sem erros TS; novos arquivos + edição da landing).
- `npm run build` → **verde** (`consulta-*.js` 17.39 kB gerado; `map-imovel` em chunk lazy de 154.86 kB — Leaflet SSR-safe via MapaSection).
- `php artisan test --compact --filter=ConsultaViabilidadePaginaTest` → **2/2 verde** (19 asserções).
- `php artisan test --compact --exclude-group postgis` → **648/648 verde** (3295 asserções) — **zero regressão**.
- `vendor/bin/pint --dirty --format agent` → passed.
- **Greps de aceitação:** `useHttp`, `/portal/viabilidade/endereco`, `/portal/viabilidade/inscricao`, `MapaSection`, `ResultadoViabilidade` em `consulta.tsx`; `/portal/viabilidade` em `home.tsx`; `export function ResultadoViabilidade` e `pendente` no componente; `component('portal/viabilidade/consulta')` no teste.
- **Verificação visual (composer dev):** PENDENTE DE CONFIRMAÇÃO MANUAL — o servidor `composer dev` está disponível, mas a inspeção em navegador (mapa renderizado, dark mode, mobile 375px, veredito "Pendente de análise técnica" com motivo de zona em endereço real de Salvador) não foi executada por este agente. A evidência automatizada acima (typecheck/build/feature tests com motores reais via 07-06) prova o fluxo de ponta a ponta no nível de framework.

## Next Phase Readiness

- **07-09 (histórico autenticado):** reusa `ResultadoViabilidade` e os tipos `ResultadoConsulta` (export nomeado) — uma só renderização honesta para consulta pública e histórico.
- **07-10 (smoke):** a página pública `/portal/viabilidade` está pronta para o smoke E2E (3 entradas + mapa + resultado real).
- **Bloqueios herdados (não introduzidos aqui):** veredito permitido/não permitido depende da base de zona (SIGIS/CA 2000, Fase 13); resolução por inscrição depende da base de lotes (contrato 07-02). Ambos degradam honestamente via `avisos` + veredito `pendente` — a UI já comunica.

---
*Phase: 07-consulta-previa-viabilidade*
*Completed: 2026-06-14*
