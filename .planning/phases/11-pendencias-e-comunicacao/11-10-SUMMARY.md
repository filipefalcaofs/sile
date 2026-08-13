---
phase: 11-pendencias-e-comunicacao
plan: 10
subsystem: seeders
tags: [hu-090, hu-091, hu-092, hu-095, hu-096, hu-147, seed-dev, fluxo-real, anti-fachada, whatsapp, pipeline-integracao, verificacao-integral, fechamento-fase]

# Dependency graph
requires:
  - phase: 11-pendencias-e-comunicacao
    provides: "11-01..11-09 (Communication ledger + enums, NotificationDispatcher, WhatsAppChannel/Gateway/Unavailable, listeners auto-descobertos NotificarPendencia/NotificarRespostaPendencia/RegistrarEnvioComunicacao, central in-app + histórico HU-096, scheduler HU-093/147)"
  - phase: 10-analise-tecnica-sedur
    provides: "PendenciaService (abrir/responder), SectorSeeder (analista@sile.dev + setor Análise Locacional), DistribuicaoService, AnaliseDevSeeder/ExpressoDevSeeder (padrão de seed dev idempotente)"
provides:
  - "ComunicacaoDevSeeder: ambiente de dev com comunicações/notificações REAIS (processo dedicado → em_analise pelo caminho legítimo → ciclo de pendência abrir/responder de verdade). Idempotente, territorial-agnóstico (roda em SQLite)"
  - "WhatsAppPipelineIntegrationTest: prova anti-fachada pela PIPELINE COMPLETA (sem Notification::fake) de que WhatsApp ON+indisponível termina bloqueado e nunca enviado — fecha o gap do caminho ON pré-Fase 13"
  - "Verificação INTEGRAL fresca da Fase 11 (2 processos) + veredito guardião-entrega APROVADO"
affects:
  - "Fase 13 (Integrações): liga o provedor real do WhatsApp (HU-095) trocando só o binding; convite/resposta Regin (HU-091 RN-004)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Seeder dev que produz comunicações REAIS pelo fluxo de verdade (PendenciaService::abrir/responder), nunca linhas fabricadas — dados fictícios, lógica real"
    - "Caminho legítimo a em_analise territorial-agnóstico: protocolo (serviço real) + transição da máquina de estados + DistribuicaoService::assumir — roda em SQLite (não exige PostGIS, diferente do AnaliseDevSeeder)"
    - "Teste de integração da pipeline completa sem Notification::fake (envio real, listeners auto-descobertos ativos) provando o invariante anti-fachada do WhatsApp ON+indisponível"

key-files:
  created:
    - "database/seeders/ComunicacaoDevSeeder.php"
    - "tests/Feature/Seeders/ComunicacaoDevSeederTest.php"
    - "tests/Feature/Comunicacao/WhatsAppPipelineIntegrationTest.php"
  modified:
    - "database/seeders/DatabaseSeeder.php"
    - "tests/Feature/Seeders/DatabaseSeederTest.php"

key-decisions:
  - "ComunicacaoDevSeeder NÃO é driver-aware (roda em SQLite, ao contrário do AnaliseDevSeeder): o ciclo de pendência é territorial-agnóstico, então a central/histórico ficam navegáveis em qualquer driver e o ComunicacaoDevSeederTest prova as comunicações reais em SQLite"
  - "Caminho legítimo a em_analise = protocolo real + ViabilityRequestStateMachine::transition (mesmo mecanismo interno do FluxoExpressoService) + DistribuicaoService::assumir; em dev pgsql o gatilho do expresso pode já ter encaminhado (sem zona → em_analise) e o seed respeita o estado (transição só se ainda protocolada). O DecidirFluxoExpressoJob é idempotente (só decide se ainda protocolada), então a fila assíncrona não conflita"
  - "Processo DEDICADO com marcador próprio (ComunicacaoDevSeeder::MARK), separado dos exemplos da Fase 8/9/10 já asseridos — só o total do cidadão muda (6→7); as demais asserções do DatabaseSeederTest ficam intactas (78 parâmetros / 24 permissões inalterados)"
  - "Idempotência por marcador + existência de pendência: re-seed com pendência já criada retorna cedo (não duplica processo, comunicações nem notificações)"
  - "WhatsAppPipelineIntegrationTest usa a Notification REAL (PendenciaSolicitadaNotification) pela pipeline real, com features.notificacao_whatsapp ON via Parameter (HU-014) e gateway default Unavailable — prova o invariante de ponta a ponta, não num elo isolado"

# Metrics
duration: ~50min
completed: 2026-06-15
---

# Phase 11 Plan 10: Fechamento — seed dev real + verificação integral (anti-fachada WhatsApp) Summary

**O fechamento da Fase 11 entrega o ambiente de DESENVOLVIMENTO com comunicações e notificações REAIS (não linhas fabricadas) e fecha o gap anti-fachada do WhatsApp pela pipeline completa, com a suíte INTEGRAL fresca verde nos 2 processos e o guardião-entrega APROVADO. O `ComunicacaoDevSeeder` cria um processo de exemplo DEDICADO do `cidadao@sile.dev`, leva-o a `em_analise` pelo CAMINHO LEGÍTIMO (protocolo real → transição da máquina de estados → `DistribuicaoService::assumir` ao `analista@sile.dev`) e executa o ciclo REAL de pendência — `PendenciaService::abrir` (listener `NotificarPendencia` → `NotificationDispatcher` cria as `communications` email/in_app + a notificação in-app database do requerente) e `PendenciaService::responder` (`NotificarRespostaPendencia` avisa o analista e reabre a análise). Dados fictícios, lógica de verdade; idempotente e territorial-agnóstico (roda em SQLite, ao contrário do `AnaliseDevSeeder`). O `WhatsAppPipelineIntegrationTest` dispara a Notification de pendência REAL pela pipeline (SEM `Notification::fake`, listeners auto-descobertos ativos) com `features.notificacao_whatsapp` ON + binding default `UnavailableWhatsAppGateway`: a linha `communications` do canal whatsapp termina `bloqueado` (auditada) e NUNCA `enviado` — mesmo após o `NotificationSent('whatsapp')` disparar (o `RegistrarEnvioComunicacao` ignora whatsapp + a guarda do `markAsSent` protege o estado terminal) — enquanto e-mail e in-app na MESMA notificação terminam `enviado`. ZERO dependência nova; parâmetros 78 / permissões 24 inalterados.**

## O que o seed dev produz (LÓGICA REAL)

- 1 processo dedicado do `cidadao@sile.dev` (marcador `ComunicacaoDevSeeder::MARK`, imóvel no Rio Vermelho — sem zona oficial), levado a `em_analise` pelo caminho legítimo e atribuído ao `analista@sile.dev` (caixa do setor "Análise Locacional").
- Ciclo de pendência REAL executado: abertura (→ `communications` `pendencia_aberta` nos canais e-mail + in-app `na_fila`/`enviado` + notificação in-app database do requerente) e resposta (→ `communications` `pendencia_respondida` + notificação in-app do analista; análise reaberta em_analise).
- Resultado: central de notificações (sininho/badge) e histórico unificado por processo (HU-096) ficam NAVEGÁVEIS no dev com eventos reais. WhatsApp permanece OFF (não aparece como enviado).

## Anti-fachada do WhatsApp (pipeline completa — fecha o gap pré-Fase 13)

| Cenário | Linha `communications` (canal whatsapp) | Prova |
|---|---|---|
| Toggle OFF (default) | `desativado` + auditoria | NotificationDispatcherTest / NotificarPendenciaListenerTest |
| Toggle ON + provedor indisponível | `bloqueado` + auditoria, **nunca** `enviado` | **WhatsAppPipelineIntegrationTest (pipeline real, sem Notification::fake)** |
| `NotificationSent('whatsapp')` tardio | continua `bloqueado` | WhatsAppPipelineIntegrationTest (defesa em profundidade) |

Dupla defesa do invariante: `RegistrarEnvioComunicacao` só trata `mail`/`database` (whatsapp excluído) **e** `Communication::markAsSent` é no-op em estados terminais honestos (`bloqueado`/`desativado`). E-mail e in-app na mesma notificação terminam `enviado` — só o whatsapp degrada.

## Verificação INTEGRAL (evidência FRESCA)

- `vendor/bin/pint --test --format agent` → **passed** (codebase inteira).
- `npm run typecheck` (`tsc --noEmit`) → **passed**.
- `npm run build` → **built** (sem erros).
- `POSTGIS_TESTS_REQUIRED=true composer test` (2 processos isolados):
  - SQLite (`--exclude-group postgis`) → **1205 passed / 6154 assertions**.
  - `@group postgis` (container `sile-pgsql`) → **29 passed / 184 assertions**.
  - Baseline da fase 1199 → **1205** (+6: 4 `ComunicacaoDevSeederTest` + 2 `WhatsAppPipelineIntegrationTest`).
- Anti-fachada: NENHUM `Event::listen` para os listeners novos (auto-descoberta; só um comentário no `AppServiceProvider` explica por que não usar); WhatsApp `desativado`/`bloqueado` nunca `enviado`; binding default `Unavailable` (sem adaptador falso em runtime).
- Parâmetros **78** / permissões **24** inalterados (DatabaseSeederTest verde).

## Veredito do guardião-entrega: APROVADO

Verificação independente com evidência fresca (reexecutou pint + os 3 testes alvo: **14/14 / 137 asserções**, e leu o código). Conformidade: Entrega funcional (anti-fachada) [ok], TDD [ok], Auditoria RN-002 [ok], Parametrização HU-014 [ok], Convenções/consistência [ok]. Itens a corrigir: nenhum. Bloqueio WhatsApp provedor real (HU-095) → Fase 13 confirmado REGISTRADO (ROADMAP + 11-03-PLAN + binding documentado).

## Bloqueios / pendências SEDUR (registrados — nunca simulados)

- **WhatsApp provedor real (HU-095)** → **Fase 13**: toggle off + contrato `UnavailableWhatsAppGateway`; liga trocando SÓ o binding (credenciais `integrations.whatsapp.*` criptografadas + teste de conexão já previstos). Hoje degrada honesto (`desativado`/`bloqueado`).
- **Convite/resposta via Simplifica/Regin (HU-091 RN-004)** → **Fase 13**: a resposta pelo portal SILE já funciona desde a Fase 10; o convite via integrador federal entra com o Regin real.
- **Destinatário "gestor do setor" do escalonamento (HU-147)** → roteamento automático ao setor é pendência **SEDUR**; default `role gestor` parametrizável (`notificacoes.escalonamento.tratamento`).
- **Rito de não-resposta de pendência (indeferir por prazo)** → **SEDUR** (sem rito definido): hoje `pendencias:expirar` expira + notifica + mantém o estado, SEM decisão automática (anti-fachada).
- **Templates oficiais (texto/identidade visual/base legal das comunicações)** → **SEDUR**: pt-BR default parametrizável (`notificacoes.pendencia.*`) sem deploy.
- **Preferência de canal por usuário** → gancho no `NotificationDispatcher` (hoje default por toggle + mapa_canais).

## Smoke navegável de ponta a ponta — CHECKPOINT HUMANO (roteiro registrado)

Gate de UI da fase (único pendente). Rodar com o ambiente dev de pé (`composer run dev`) e o seed aplicado (`php artisan migrate:fresh --seed`):

1. **Gestão** (`analista@sile.dev` / `password`): abrir uma pendência num processo `em_analise` (ex.: usar o processo dedicado do seed ou qualquer um da caixa) — informar a descrição e confirmar.
2. **Portal** (`cidadao@sile.dev` / `password`): conferir o **sininho com badge** de não-lidas, a **notificação in-app** da pendência e o **e-mail real** (Pail/log/`MAIL_MAILER`); abrir a central de pendências e **RESPONDER** pelo portal.
3. Confirmar que a análise **REABRIU** (`em_pendencia`→`em_analise`) e que o **analista** recebeu a notificação in-app da resposta.
4. Abrir o **histórico de comunicações do processo (HU-096)** e ver os eventos unificados (canal/tipo/status honesto), incl. o e-mail e o in-app.
5. (Opcional) Rodar `php artisan notificacoes:alertar-vencimentos`, `notificacoes:escalonar-sla` e `pendencias:expirar`: conferir que SÓ notificam (sem mudar estado, exceto a pendência expirar) e são idempotentes (2ª execução não duplica).
6. Confirmar que o **WhatsApp está OFF** (histórico mostra `desativado`, nunca `enviado`).

Sinal de retomada: "aprovado" ou a lista de problemas encontrados (cada um vira gap a corrigir antes de concluir a fase).

## Task Commits

1. **Task 1 (seed dev real + integração anti-fachada WhatsApp)** — `8e3c6ae` (feat) — TDD: RED (`ComunicacaoDevSeeder` inexistente → classe não encontrada) → GREEN (ComunicacaoDevSeederTest 4/4) + WhatsAppPipelineIntegrationTest 2/2 + DatabaseSeederTest ajustado (6→7) verde.
2. **Task 2 (verificação integral + guardião)** — sem código (verificação/relatório); evidência fresca colada acima.

## Mapa CA → teste (verde)
| HU / RN | Evidência |
|---|---|
| HU-090..096/147 — fluxo navegável ponta a ponta | ComunicacaoDevSeederTest + checkpoint humano (smoke) |
| Entrega-funcional — lógica real sem fachada | `composer test` 2 processos + evidência anti-fachada + guardião APROVADO |
| HU-095 — WhatsApp nunca finge (ON+indisponível → bloqueado) | WhatsAppPipelineIntegrationTest (pipeline real) |
| Suíte integral fresca (SQLite + postgis) | `composer test` 1205 + 29 |

---
*Phase: 11-pendencias-e-comunicacao — FECHAMENTO (10/10 planos)*
*Completed: 2026-06-15*
