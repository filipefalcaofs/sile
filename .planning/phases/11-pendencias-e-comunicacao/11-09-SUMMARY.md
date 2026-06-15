---
phase: 11-pendencias-e-comunicacao
plan: 09
subsystem: frontend
tags: [hu-090, hu-091, hu-096, central-in-app, sininho, badge, shared-prop, notificacoes, pendencias, historico-unificado, comunicacoes, inertia-react, frontend-only]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-08 (rotas portal/gestao.notificacoes.*, portal.solicitacoes.comunicacoes, gestao.processos.comunicacoes; shared prop notificacoes.nao_lidas; props lista/processo/comunicacoes; shapes NotificationResource/CommunicationResource)"
  - phase: 10-analise-tecnica-sedur
    provides: "PendenciaRespostaController do portal (fluxo de resposta de pendência já existente, reusado pelo link da central de pendências)"
  - phase: 08-solicitacao-viabilidade
    provides: "portal.solicitacoes.index (Minhas solicitações com status) e portal.solicitacoes.show (acompanhamento) — superfícies onde a central de pendências e o link do histórico foram embutidos"
provides:
  - "NotificationBell: sininho no cabeçalho compartilhado (portal+gestão) com badge REAL de não-lidas (shared prop notificacoes.nao_lidas) e link à central do ambiente"
  - "Páginas da central de notificações (portal/notificacoes/index, gestao/notificacoes/index) sobre o componente compartilhado NotificationCenter: lista lidas/não-lidas, marca uma e marca todas (ler/ler-todas)"
  - "Central de pendências do requerente embutida em Minhas solicitações (HU-091): ação 'Responder pendência' nas linhas em_pendencia + aviso, ligando ao fluxo PendenciaRespostaController existente (sem rota nova)"
  - "HistoricoComunicacoes + páginas portal/solicitacoes/comunicacoes e gestao/processos/comunicacoes (HU-096), com link a partir do acompanhamento (portal) e do processo (gestão)"
affects:
  - "11-10 (smoke navegável de ponta a ponta do EP11: abrir pendência → badge no sininho → central → marcar lida → histórico do processo)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Sininho como componente único no AppHeader compartilhado, derivando a rota da central do homeHref do ambiente (/portal|/gestao) — sem request extra (lê o shared prop)"
    - "Página fina por ambiente (portal/gestao notificacoes index) delegando a um componente compartilhado (NotificationCenter) que concentra a lista + marcações — DRY sem duplicar a UI"
    - "Marcação via router.post Inertia com preserveScroll: o back() do 11-08 recarrega a lista e o badge na mesma navegação"
    - "Central de pendências derivada da fonte já roteada (Minhas solicitações), sem nova rota/controller (frontend-only): a ação na linha leva ao fluxo de resposta REAL"
    - "Status HONESTO no histórico colorido por valor do enum (enviado/falhou/bloqueado/desativado/na_fila) a partir do *_label do Resource — WhatsApp nunca aparece como 'enviado'"

key-files:
  created:
    - "resources/js/components/notificacoes/NotificationBell.tsx"
    - "resources/js/components/notificacoes/notification-center.tsx"
    - "resources/js/pages/portal/notificacoes/index.tsx"
    - "resources/js/pages/gestao/notificacoes/index.tsx"
    - "resources/js/components/comunicacoes/HistoricoComunicacoes.tsx"
    - "resources/js/pages/portal/solicitacoes/comunicacoes.tsx"
    - "resources/js/pages/gestao/processos/comunicacoes.tsx"
    - "tests/Feature/Comunicacao/NotificationCenterUiSmokeTest.php"
  modified:
    - "resources/js/components/app/app-header.tsx"
    - "resources/js/components/icons/index.tsx"
    - "resources/js/types/index.d.ts"
    - "resources/js/pages/portal/solicitacoes/index.tsx"
    - "resources/js/pages/portal/solicitacoes/protocolo.tsx"
    - "resources/js/pages/gestao/processos/show.tsx"

key-decisions:
  - "Páginas REAIS por ambiente em vez do único pages/notificacoes/index.tsx do plano: o controller do 11-08 (route-owner, já shipado) renderiza portal/notificacoes/index e gestao/notificacoes/index. O contrato do código vence o caminho aspiracional do plano (precedência agentes-sile). A lógica única vive em NotificationCenter (contém ler-todas), consumido pelas duas páginas finas"
  - "HU-091 sem rota nova (frontend-only): a central de pendências foi embutida na própria 'Minhas solicitações' (fonte pinada pelo plano) — ação 'Responder pendência' nas linhas em_pendencia + aviso honesto, ligando ao PendenciaRespostaController existente. Uma página standalone portal/pendencias/index exigiria rota+controller (dono = 11-08); criá-la sem rota seria fachada (proibido)"
  - "Histórico embutido como página dedicada (a que o ComunicacaoHistoricoController serve) + LINK a partir do acompanhamento (portal) e do processo (gestão): o dado comunicacoes só existe na rota de comunicações; renderizar o componente dentro do show sem esse dado seria fachada — o link leva à página com dado REAL"
  - "Sininho derivado do homeHref (não de route()): o repo navega por caminhos string; manter o padrão evita acoplar a um helper de rotas inexistente no front"
  - "Smoke com assertInertia (não viewData): com as páginas .tsx em disco, o AssertableInertia v3 valida a EXISTÊNCIA do componente + as props — render real do contrato página↔props, como o ProcessoUiSmokeTest"

patterns-established:
  - "Componente de domínio compartilhado + páginas finas por ambiente para superfícies servidas pelo mesmo controller em portal e gestão"
  - "Afixar uma central derivada (pendências) na listagem já roteada quando a wave é frontend-only, em vez de abrir rota — degradação honesta do escopo"

# Metrics
duration: ~20min
completed: 2026-06-15
---

# Phase 11 Plan 09: Central in-app + Pendências + Histórico (UI HU-090/091/096) Summary

**A superfície navegável real do EP11 (frontend-only, sobre os endpoints e shapes que o 11-08 entregou): o sininho no cabeçalho compartilhado com o badge REAL de não-lidas (shared prop `notificacoes.nao_lidas`) levando à central; a central de notificações nos dois ambientes (portal/gestão) listando lidas/não-lidas e marcando uma/todas pelos endpoints reais; a central de pendências do requerente embutida na própria "Minhas solicitações" (ação "Responder pendência" nas linhas `em_pendencia` ligando ao fluxo existente, sem rota nova); e o histórico unificado de comunicações por processo (HU-096) com status HONESTO (WhatsApp nunca "enviado"), embutido como página dedicada e acessível por link no acompanhamento (portal) e no processo (gestão). ZERO rota/controller novo; o contrato do 11-08 foi respeitado à risca. Verificação: `npx tsc --noEmit` limpo, `npm run build` ok, smoke 5/5 e suítes vizinhas verdes (Comunicação+Analise 67/67, Solicitação 182/182).**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-06-15
- **Tasks:** 2
- **Files criados:** 8 / **modificados:** 6

## Accomplishments

- **HU-090 — sininho + central:** `NotificationBell` no `AppHeader` compartilhado mostra a contagem real de não-lidas (shared prop, null-safe) e leva à central do ambiente (`/portal/notificacoes` ou `/gestao/notificacoes`). As páginas `portal/notificacoes/index` e `gestao/notificacoes/index` usam o `NotificationCenter` compartilhado: separa não-lidas/lidas, "Marcar como lida" (POST `…/notificacoes/{id}/ler`) e "Marcar todas" (POST `…/notificacoes/ler-todas`), paginação e estado vazio honesto.
- **HU-091 — central de pendências (sem rota nova):** em "Minhas solicitações" (fonte pinada), as linhas `em_pendencia` ganham a ação "Responder pendência" (tom de alerta) que leva ao fluxo REAL do `PendenciaRespostaController` (`/portal/solicitacoes/{id}/pendencias`) e um aviso honesto destacando as pendências da página. NÃO recria a resposta; apenas agrega/leva ao fluxo existente.
- **HU-096 — histórico unificado:** `HistoricoComunicacoes` (linha do tempo: tipo, canal, status honesto, título/resumo, destinatário, data; `error_message` só quando o payload da gestão traz) consome o endpoint do 11-08 nas páginas `portal/solicitacoes/comunicacoes` e `gestao/processos/comunicacoes`. Link de acesso a partir do acompanhamento (portal, `protocolo.tsx`) e do processo (gestão, `show.tsx`).
- **Anti-fachada honesto:** estados de carregamento (botões com `loading`) e vazio reais; o status do histórico vem do ledger (bloqueado/desativado jamais viram "enviado"); nenhuma tela inventa resultado.
- **Frontend-only de verdade:** nenhuma rota/controller tocado — só `.tsx`, o tipo do shared prop e um teste. O contrato do 11-08 (nomes de componente, props `lista`/`processo`/`comunicacoes`, shapes) foi seguido exatamente.

## Task Commits

1. **Task 1 (central in-app + sininho)** — `9fd4bbd` (feat) — NotificationBell + páginas portal/gestão + NotificationCenter + BellIcon + tipo do shared prop + integração no AppHeader.
2. **Task 2 (pendências + histórico + smoke)** — `c55ab1e` (feat) — ação de pendência em Minhas solicitações + HistoricoComunicacoes + páginas de comunicações (portal/gestão) + links nas telas de processo + NotificationCenterUiSmokeTest (5/5).

## Mapa CA → teste (verde)

| HU / RN | Teste |
|---|---|
| HU-090 — central do portal renderiza + badge real (shared prop) | NotificationCenterUiSmokeTest::test_central_de_notificacoes_do_portal_renderiza_componente_e_badge |
| HU-090 — central da gestão renderiza + badge real | NotificationCenterUiSmokeTest::test_central_de_notificacoes_da_gestao_renderiza_componente_e_badge |
| HU-091 — pendências do requerente em Minhas solicitações (lista + link ao fluxo real) | NotificationCenterUiSmokeTest::test_central_de_pendencias_do_requerente_lista_em_minhas_solicitacoes |
| HU-096 — histórico unificado renderiza no portal | NotificationCenterUiSmokeTest::test_historico_de_comunicacoes_do_portal_renderiza_componente |
| HU-096 — histórico unificado renderiza na gestão | NotificationCenterUiSmokeTest::test_historico_de_comunicacoes_da_gestao_renderiza_componente |
| HU-096 — payload (canais/tipos/status/ordenação/LGPD/auditoria) | ComunicacaoHistoricoTest (11-08, mantido verde) |

## Verificação (evidência fresca)

- `npx tsc --noEmit` → **sem erros** (exit 0).
- `npm run build` → **✓ built** (manifest Vite válido; páginas novas compiladas).
- `vendor/bin/pint --dirty --format agent` → **passed**.
- `php artisan test --compact --filter=NotificationCenterUiSmokeTest` → **5 passed / 61 assertions**.
- Anti-regressão (telas/contratos tocados): `php artisan test --compact tests/Feature/Comunicacao tests/Feature/Analise/ProcessoUiSmokeTest.php` → **67 passed / 295 assertions**; `php artisan test --compact tests/Feature/Solicitacao` → **182 passed / 962 assertions**.
- **Checkpoint humano (verificação visual pendente):** o smoke (assertInertia) prova o contrato página↔props e a EXISTÊNCIA dos componentes em disco; `tsc`/`build` provam compilação/tipos. A renderização visual no navegador (sininho com badge, marcar lida/todas atualizando o badge, ação "Responder pendência", linha do tempo do histórico, responsividade/acessibilidade eMAG/WCAG no portal) NÃO é coberta por estes comandos e deve ser conferida por um humano antes do "pronto" final.

## Deviations from Plan

- **Caminho das páginas da central:** o plano listou `resources/js/pages/notificacoes/index.tsx` (página única), mas o `NotificationCenterController` (11-08, route-owner já shipado) renderiza `portal/notificacoes/index` e `gestao/notificacoes/index`. Criei as DUAS páginas reais (o que o controller de fato renderiza) sobre o componente compartilhado `notification-center.tsx` (que contém `ler-todas`). O contrato do código vence o caminho aspiracional do plano; criar a página única seria um arquivo morto (nenhuma rota a renderiza) — fachada proibida.
- **HU-091 sem página standalone `portal/pendencias/index.tsx`:** a wave é frontend-only e o 11-08 NÃO expôs um índice de pendências; uma página própria exigiria rota+controller (dono = 11-08). Em vez de criar um arquivo morto, a central de pendências foi entregue de forma REAL e derivada na própria "Minhas solicitações" (fonte pinada pelo plano): ação "Responder pendência" nas linhas `em_pendencia` + aviso, ligando ao `PendenciaRespostaController` existente. **Pendência registrada (exceção que pertence ao 11-08, route-owner):** se for desejada uma central de pendências standalone (página/rota própria), o índice de pendências do requerente deve ser exposto no 11-08 — NÃO foi criado aqui (regra frontend-only).
- **Histórico por link, não embutido no show:** o componente `HistoricoComunicacoes` renderiza com dado REAL nas páginas de comunicações (as que o `ComunicacaoHistoricoController` serve). As telas de processo (acompanhamento/portal e show/gestão) recebem um LINK para essas páginas, porque o payload `comunicacoes` só existe na rota de comunicações — embutir no show sem esse dado seria fachada.
- **Smoke com `assertInertia` (não `viewData`):** agora que as `.tsx` existem, o AssertableInertia v3 valida a existência do componente + props (render real do contrato), padrão do `ProcessoUiSmokeTest`. O smoke começou VERMELHO ("Inertia page component file does not exist" para as 4 páginas novas) e ficou VERDE após a implementação.

## Issues Encountered

- **`assertInertia` exige o componente em disco:** a primeira execução do smoke falhou em 4 métodos por "Inertia page component file [..] does not exist" — confirmando que o teste prova a existência das páginas. Resolvido ao criar as páginas com os nomes EXATOS que o controller renderiza. Nenhum bug de produção.

## Next Phase Readiness

- **11-10 (smoke navegável de ponta a ponta):** o ciclo de UI está fechado — sininho (badge) → central (lista/marcar) → histórico do processo; e a central de pendências leva ao fluxo de resposta. As páginas e o componente de histórico estão prontos para o passo navegável.
- **Pendência aberta (não bloqueante desta wave):** central de pendências standalone (página/rota própria) depende de um índice de pendências exposto pelo 11-08 (route-owner). Hoje a central vive, honesta e funcional, dentro de "Minhas solicitações".
- **Verificação visual** registrada como checkpoint humano (acima) — recomendada antes do aceite final da fase.

---
*Phase: 11-pendencias-e-comunicacao*
*Completed: 2026-06-15*
