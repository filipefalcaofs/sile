---
phase: 08-solicitacao-de-viabilidade
plan: 13
subsystem: portal-ui
tags: [solicitacao-viabilidade, ui, wizard, inertia-react, reuso-mapa, reuso-resultado-viabilidade, anti-fachada, hu-061, hu-062, hu-063, hu-064, hu-065, hu-066, hu-067, hu-068, hu-069, hu-070, hu-141]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: "05"
    provides: "rotas portal.solicitacoes.{index,store} + shape da listagem {status, company} + DuplicateRequestDetector (flash duplicateAlert)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "06"
    provides: "PUT portal.solicitacoes.imovel + flash territorio/areaAlert (RN-004) + markSimulationStale"
  - phase: 08-solicitacao-de-viabilidade
    plan: "07"
    provides: "PUT portal.solicitacoes.atividades + limite solicitacao.cnaes_complementares.max"
  - phase: 08-solicitacao-de-viabilidade
    plan: "08"
    provides: "rotas documentos.{store,download,destroy} + DocumentRequirementResolver::required()/missing()"
  - phase: 08-solicitacao-de-viabilidade
    plan: "09"
    provides: "POST portal.solicitacoes.simular + snapshot persistido (simulation_snapshot.por_cnae[].consulta)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "10"
    provides: "POST portal.solicitacoes.protocolar (proceed_despite) + bloqueio documental em flash.error"
  - phase: 08-solicitacao-de-viabilidade
    plan: "11"
    provides: "GET portal.solicitacoes.show (página de protocolo/consulta — DISTINTA do wizard)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "12"
    provides: "DELETE portal.solicitacoes.cancelar + parâmetro estados_cancelaveis"
  - phase: 04-georreferenciamento
    plan: "(geo)"
    provides: "MapaSection/MapImovel (mapa Leaflet SSR-safe, ponto + overlay GeoJSON)"
  - phase: 07-consulta-previa-viabilidade
    plan: "08"
    provides: "componente ResultadoViabilidade (veredito honesto por CNAE; pendente com motivo)"
provides:
  - "resources/js/pages/portal/solicitacoes/index.tsx — lista Minhas solicitações (status amigável, consultar, continuar, cancelar)"
  - "resources/js/pages/portal/solicitacoes/wizard.tsx — wizard multi-etapas (início → imóvel → atividades → documentos → simulação → revisão/protocolar)"
  - "resources/js/components/solicitacao/etapa-{imovel,atividades,documentos,simulacao,revisao}.tsx"
  - "rotas GET portal.solicitacoes.create (solicitacoes/nova) e portal.solicitacoes.edit (solicitacoes/{solicitacao}/editar) renderizando o wizard"
  - "SolicitacaoController@create/@edit (Inertia::render do wizard com rascunho real + dados de apoio) + index com cancelable/duplicateAlert"
affects: [08-16-fechamento-seeds]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Rota GET própria do wizard (create/edit) distinta da página de protocolo (08-11 show): 'nova' literal ANTES de solicitacoes/{solicitacao}; 'editar' autorizado pela policy update"
    - "Wizard dirigido pelo estado PERSISTIDO do rascunho: cada etapa submete ao endpoint real (back()→edit) e o controller reidrata o draft do banco; retoma na primeira etapa obrigatória incompleta"
    - "Reuso de componentes da fase anterior sem recriar: MapaSection/MapImovel (Fase 4) para o polígono e ResultadoViabilidade (Fase 7) por CNAE na simulação"
    - "Polígono de 4 pontos derivado do ponto do mapa + área declarada (footprint quadrado) — fonte enviada ao backend, que faz a geometria/área reais (08-06)"
    - "Anti-fachada na UI: faltar documento obrigatório bloqueia o botão de protocolar com a lista; simulação orienta e não bloqueia (ciência proceed_despite na tendência de indeferimento); pendente sem zona com motivo"

key-files:
  created:
    - resources/js/pages/portal/solicitacoes/index.tsx
    - resources/js/pages/portal/solicitacoes/wizard.tsx
    - resources/js/components/solicitacao/etapa-imovel.tsx
    - resources/js/components/solicitacao/etapa-atividades.tsx
    - resources/js/components/solicitacao/etapa-documentos.tsx
    - resources/js/components/solicitacao/etapa-simulacao.tsx
    - resources/js/components/solicitacao/etapa-revisao.tsx
    - tests/Feature/Solicitacao/SolicitacaoWizardPaginaTest.php
  modified:
    - app/Http/Controllers/Portal/SolicitacaoController.php
    - routes/portal.php

key-decisions:
  - "Rota GET do wizard própria (create=solicitacoes/nova, edit=solicitacoes/{solicitacao}/editar) renderizando portal/solicitacoes/wizard, DISTINTA da GET solicitacoes/{solicitacao} (08-11, protocolo/consulta). 'nova' é literal e registrada ANTES de {solicitacao} para não ser capturada como id; 'editar' é autorizado por ViabilityRequestPolicy::update (dono + rascunho → terceiro/protocolada 403)."
  - "store e protocolar NÃO foram alterados (redirect→index): mantidos exatamente como 08-05/08-10 (fora do file scope do plano; nenhum teste de 08-05/08-10 tocado). O wizard inicia em /nova (POST store → index com flash status/duplicateAlert) e o usuário continua pelo botão 'Continuar' do index (→ /editar). Após protocolar (→ index com o número), consulta pelo 'Consultar' (→ show). Navegação 100% pelos endpoints reais, sem mudar comportamento de backend."
  - "Wizard reidratado pelo estado PERSISTIDO: SolicitacaoController@edit carrega o rascunho real (imóvel/área/endereço/indicadores, CNAEs, anexos, snapshot de simulação) + requisitos (DocumentRequirementResolver required/missing) + config de anexos (HU-014). Cada etapa submete ao endpoint real e o back() recarrega /editar com props frescas; retoma na primeira etapa obrigatória incompleta (imóvel→atividades→documentos)."
  - "Território e alerta de área são TRANSIENTES (flash do PUT imóvel — 08-06): lidos de session() no edit e passados como props; a simulação vem do snapshot PERSISTIDO (08-09, RN-003), não reprocessada no front. Recompute de território a cada GET foi evitado para não gerar ruído de auditoria (TerritoryService audita)."
  - "Polígono de 4 pontos: como MapImovel demarca um ponto + overlay GeoJSON (não desenho livre), o quadrilátero é derivado do ponto do mapa + área declarada (footprint quadrado) e enviado como property_polygon_geojson — o backend (08-06) faz território/área×polígono/geometry reais. Uma única área (used_area_m2) dirige o footprint, evitando alerta espúrio no caminho do portal; o alerta RN-004 continua funcional para polígonos não-quadrados (contingência/futuro desenho livre)."
  - "Reuso real (key_links do plano): etapa-imovel importa MapaSection/MapImovel (Fase 4) para o polígono; etapa-simulacao importa ResultadoViabilidade (Fase 7) e renderiza por CNAE — pendente sem zona herda o motivo honesto do componente (nunca permitido/não permitido sem zona)."
  - "Anti-fachada na revisão: requisitos faltantes (resolver missing()) bloqueiam o botão Protocolar com a lista (aviso, nunca silencioso); a simulação com tendência nao_permitido exige checkbox de ciência (proceed_despite) para habilitar o protocolo, sem impedi-lo (direito de petição, HU-141)."
  - "index: cancelable por linha lido do parâmetro solicitacao.cancelamento.estados_cancelaveis (mesmo contrato do CancelarSolicitacaoService, 08-12), editable = rascunho (continuar no wizard), duplicateAlert lido de session() (RN-007, link ao processo anterior). Cancelar via ConfirmDialog com motivo obrigatório → DELETE cancelar."

patterns-established:
  - "Wizard de processo no portal: página única (wizard.tsx) renderizada por create (sem rascunho → etapa de início) e edit (com rascunho → etapas), dirigida pelo estado persistido, com stepper acessível (aria-current) e retomada na primeira etapa incompleta — molde para futuros fluxos multi-etapas do cidadão"
  - "Componentes de etapa desacoplados (props estreitas por etapa, sem tipo gigante compartilhado) que consomem cada um o seu endpoint real e reusam componentes de fases anteriores (mapa, resultado de viabilidade, picker de CNAE)"

# Metrics
duration: ~30 min (com leitura de contexto); commits 0611c9e → 901ef5f
completed: 2026-06-14
---

# Phase 8 Plan 13: UI da jornada do cidadão — wizard e Minhas solicitações Summary

**A jornada do cidadão ficou navegável de ponta a ponta, consumindo APENAS os endpoints reais dos planos 08-05..08-12 (nada simulado no front). A página `portal/solicitacoes/index` lista "Minhas solicitações" (DataTable com status amigável por Badge, busca, paginação) e oferece, por linha, continuar o rascunho (→ wizard `editar`), consultar (→ protocolo 08-11) e cancelar (ConfirmDialog com motivo obrigatório → DELETE cancelar, só quando cancelável pelo parâmetro `solicitacao.cancelamento.estados_cancelaveis`); o alerta de reincidência (RN-007) aparece com link ao processo anterior. O wizard multi-etapas (`portal/solicitacoes/wizard`) tem ROTA GET própria — `GET solicitacoes/nova` (`portal.solicitacoes.create`) e `GET solicitacoes/{solicitacao}/editar` (`portal.solicitacoes.edit`, autorizada pela `ViabilityRequestPolicy::update`) — DISTINTA da `GET solicitacoes/{solicitacao}` (08-11, página de protocolo). O `SolicitacaoController@create/@edit` reidrata o rascunho REAL do banco (imóvel, área, CNAEs, anexos, snapshot de simulação) e os dados de apoio (tipos de serviço ativos, empresas do dono). As etapas — início (tipo + empresa → store), imóvel (reusa `MapaSection`/`MapImovel` da Fase 4 para o polígono de 4 pontos derivado da área + território honesto sem zona + alerta de área RN-004), atividades (reusa o `CnaePicker` que busca em `portal.cnaes.search`, principal + complementares no limite parametrizável), documentos (upload real + lista de obrigatórios; faltantes como AVISO), simulação (POST simular + `ResultadoViabilidade` por CNAE; pendente com motivo; orientativa, não bloqueia) e revisão/protocolar (resumo + bloqueio do protocolo quando faltar documento + ciência `proceed_despite` na tendência de indeferimento) — submetem cada uma ao seu endpoint real. Mobile-first e acessível (eMAG/WCAG). ZERO dependência nova. Verificação fresca: `route:list` mostra create/edit distintas da show; `npx tsc --noEmit` e `npm run build` verdes; `SolicitacaoWizardPaginaTest` 9/9; suíte completa 835/835 (4310 asserções, inclui @group postgis com o container healthy).**

## Performance

- **Duration:** ~30 min (com leitura de contexto); 3 commits TDD
- **Completed:** 2026-06-14
- **Tasks:** 3 (lista + index controller/teste; rotas + create/edit + wizard início/imóvel/atividades; etapas documentos/simulação/revisão)
- **Files:** 8 criados + 2 modificados — ZERO dependência nova

## Rotas (nomes exatos)

| Método | URI | Nome | Ação |
|---|---|---|---|
| GET | `portal/solicitacoes/nova` | `portal.solicitacoes.create` | renderiza o wizard (novo rascunho — etapa de início) |
| GET | `portal/solicitacoes/{solicitacao}/editar` | `portal.solicitacoes.edit` | renderiza o wizard (rascunho existente; policy update) |

Ambas aditivas ao grupo `auth:web` + `verified` + `lgpd.accepted` + `ResolveRepresentation`; `nova` é literal e vem ANTES de `solicitacoes/{solicitacao}` (08-11 show). As demais rotas do fluxo são consumidas como já existem (store/imovel/atividades/documentos/simular/protocolar/cancelar/show).

## Contratos de UI (shape consumido)

- **index** (`portal/solicitacoes/index`): `solicitacoes` (paginação, cada linha `{id, protocol_number, status:{value,label,public_label}, service_type, company, created_at, editable, cancelable}`), `filters`, `perPageOptions`, `solicitacaoEnabled`, `duplicateAlert`.
- **wizard** (`portal/solicitacoes/wizard`): `solicitacao` (null em /nova; em /editar = rascunho completo com `address`, `property_polygon_geojson`, `indicators`, `cnaes[]`, `documentos[]`, `simulation`), `serviceTypes`, `companies`, `requisitosObrigatorios`, `requisitosFaltantes`, `anexosConfig{max_mb,mime_permitidos}`, `cnaesComplementaresMax`, `simulacaoEnabled`, `solicitacaoEnabled`, `territorio` (flash), `areaAlert` (flash).

## Task Commits

TDD estrito (RED→GREEN com evidência fresca; render Inertia verifica que o .tsx existe em disco — anti-fachada):

1. **Task 1: lista Minhas solicitações** — `0611c9e` (feat) — RED: 4 falhas (componente index inexistente + cancelable/duplicateAlert ausentes) → GREEN: `SolicitacaoWizardPaginaTest` 4/4.
2. **Task 2: rotas create/edit + wizard (início/imóvel/atividades)** — `40041a4` (feat) — RED: 3 falhas (404 nas rotas nova/editar) → GREEN: 9/9; route:list mostra create/edit distintas da show.
3. **Task 3: etapas documentos/simulação/revisão + wiring** — `901ef5f` (feat) — typecheck + build verdes; greps ResultadoViabilidade/proceed_despite OK; 9/9.

**Plan metadata:** `docs(08-13)` (este SUMMARY + STATE).

## Decisions Made

- **Rota GET própria do wizard** (create/edit) distinta da página de protocolo (08-11), com `nova` literal antes de `{solicitacao}` e `editar` sob a policy update.
- **store/protocolar inalterados** (redirect→index): respeitado o file scope do plano (só SolicitacaoController + routes + páginas/etapas/teste); navegação do wizard via index-hub (Continuar/Consultar), sem tocar comportamento nem testes de 08-05/08-10.
- **Wizard dirigido pelo estado persistido**: edit reidrata o rascunho real; cada etapa submete ao endpoint real e o back() recarrega /editar; retoma na primeira etapa obrigatória incompleta.
- **Polígono de 4 pontos derivado** (ponto do mapa + área declarada) reusando MapImovel; o backend faz a geometria/área reais.
- **Anti-fachada**: documentos faltantes bloqueiam o protocolo com a lista; simulação orienta sem bloquear (ciência proceed_despite); pendente sem zona com motivo (ResultadoViabilidade).

## Deviations from Plan

- **Áreas unificadas (uma só `used_area_m2`)**: o plano separa "ÁREA" como etapa; como o imóvel e a área compartilham o mesmo endpoint (PUT imóvel) e o footprint do polígono é derivado da área, juntei numa etapa "Imóvel e área" (o próprio plano admite "pode estar junto da etapa imóvel"). Evita um segundo campo de área confuso e o alerta espúrio área×polígono no caminho do portal; o alerta RN-004 continua funcional para polígonos não-quadrados.
- **Polígono derivado (footprint quadrado) em vez de desenho livre**: `MapImovel` demarca ponto + overlay GeoJSON (não há ferramenta de desenho de polígono na Fase 4). O quadrilátero é construído do ponto + área e enviado de verdade ao backend (território/área/geometry reais). Desenho livre fica como evolução futura (registrado).
- **store/protocolar não alterados**: o plano descreve "iniciar → … → protocolar" como fluxo contínuo; mantive os redirects de 08-05/08-10 (→ index) para não exceder o file scope nem tocar testes de outros planos. O fluxo segue navegável pelo index-hub. (Caso a SEDUR/UX prefira o wizard contínuo, basta refinar os redirects de store→edit e protocolar→show em um plano dedicado, ajustando os respectivos testes.)
- **Arquivo `types.ts` evitado**: cada etapa define props estreitas próprias (sem tipo gigante compartilhado), exceto `SimulacaoData`/`SimulacaoPorCnae` exportados de `etapa-simulacao.tsx` e reusados pelo wizard/revisão — sem criar arquivos fora da lista do plano.

## Issues Encountered

- **`assertInertia()->component()` exige o .tsx em disco**: a primeira RED acusou "Inertia page component file does not exist" — confirmando o requisito anti-fachada de a página existir de fato. Resolvido criando as páginas reais.
- **Pint removeu imports do teste entre tasks** (`no_unused_imports`): `ViabilityServiceType`/`Cnae` foram retirados após a Task 1 (sem uso) e re-adicionados na Task 2 quando passaram a ser usados. Sem impacto em produção.
- **Erro de tipagem do polígono**: `property_polygon_geojson` não é campo do `useForm` (é derivado), então o erro de validação dele é lido do bag compartilhado (`usePage().props.errors`) e injetado no envio via `transform`.
- **Execução concorrente (Wave 7)**: 08-11/08-14/08-15 já commitados; toquei só os arquivos do 08-13 (rotas aditivas append-only; staging individual). Suíte completa 835/835 valida todos juntos.

## Verification (evidência fresca)

- **`php artisan route:list --path=portal/solicitacoes`** → `portal.solicitacoes.create` (GET solicitacoes/nova) e `portal.solicitacoes.edit` (GET solicitacoes/{solicitacao}/editar), DISTINTAS de `portal.solicitacoes.show` (GET solicitacoes/{solicitacao}); `nova` listada antes de `{solicitacao}`.
- **`npx tsc --noEmit`** → sem erros.
- **`npm run build`** → built ok (`wizard` 28,6 kB; `map-imovel` 154,9 kB lazy).
- **`php artisan test --compact --filter=SolicitacaoWizardPaginaTest`** → **9/9** (87 asserções).
- **Critérios de aceite (greps)**: `component('portal/solicitacoes/index')` + `ConfirmDialog` na index; `MapaSection` em etapa-imovel; `cnaes.search` em etapa-atividades; `ResultadoViabilidade` em etapa-simulacao; `proceed_despite` em etapa-revisao — todos OK.
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **835 testes, 835 passaram, 0 falhas** (4310 asserções; inclui @group postgis com o container `sile-pgsql` healthy). Zero regressão.

## Verificação visual (CHECKPOINT HUMANO PENDENTE)

Não executada por mim (sem abrir o browser). Sugerida com `composer dev`, percorrendo o wizard com um endereço de Salvador:
1. `/portal/solicitacoes` → "Nova solicitação" → escolher tipo + empresa → criar (volta ao index com sucesso; alerta de duplicidade quando houver) → "Continuar".
2. Etapa imóvel: arrastar o marcador no mapa, informar área/referência → salvar; conferir o território (zona "indisponível" com motivo — pendente SEDUR) e o alerta de área quando divergir.
3. Atividades: principal + complementares (picker de CNAEs ativos) → salvar.
4. Documentos: anexar; conferir os obrigatórios e o aviso de faltantes.
5. Simulação: "Simular" → conferir a tendência por CNAE (pendente sem zona com motivo).
6. Revisão: faltar documento bloqueia o botão Protocolar com a lista; com tendência de indeferimento, marcar a ciência; protocolar gera número e leva ao index com o número (consulta pelo "Consultar").
7. Conferir mobile (375px) e dark mode.

## Next Phase Readiness

- **08-16 (fechamento/seeds)**: este wizard é o insumo do smoke navegável de ponta a ponta. Seedar os requisitos-base (`foto-fachada`, `termo-concessao`) faz a etapa de documentos exibir obrigatórios reais e o bloqueio documental do protocolo.
- **Pendências herdadas (degradam honesto, registradas)**: zona urbanística pendente SEDUR (veredito locacional permanece pendente na simulação, com motivo); desenho livre de polígono (hoje footprint derivado da área); escopo DAM (Fase 13). Caso se queira o wizard contínuo (sem o index-hub), refinar os redirects de store/protocolar em plano dedicado.

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
