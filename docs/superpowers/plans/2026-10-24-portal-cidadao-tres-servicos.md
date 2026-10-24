# Portal do cidadão com três serviços — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reduzir o portal do cidadão a renovar viabilidade, consultar processo e responder convite, com renovação sem vínculo de empresa e convite só do requerente.

**Architecture:** Uma chave (`features.portal_servicos_extras`) e um middleware (`portal.extras`) desligam as rotas dos serviços retirados, sem apagar código. Um escopo novo no modelo (`respondivelPor`) e a política `view` restrita definem quem responde convite. Consulta por número e lista de convites são controllers novos, em páginas Inertia no layout do portal.

**Tech Stack:** Laravel 13, Inertia v3 + React 19, Tailwind 4, PHPUnit 12, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-24-portal-cidadao-tres-servicos-design.md`

## Global Constraints

- Branch `feat/portal-cidadao-tres-servicos` no worktree `.claude/worktrees/teste-portal-renovacao`.
- Testes PostGIS: `DB_TEST_PORT=5444`. Rodar `vendor/bin/pint --dirty --format agent` antes de cada commit PHP.
- Sem migration nova. `CompanySource::Legado = 'legado'`, rótulo "Sistema anterior".
- `TestCase::setUp` liga `features.portal_servicos_extras` (como já faz com `portal_servicos`); testes novos desligam explicitamente.
- Nenhum arquivo de teste é apagado; testes que codificam a regra antiga (vínculo de empresa, sócio responde convite) são reescritos para a regra nova.
- Mensagens no tom das que já existem; sem dados pessoais na consulta.
- Commits sem coautoria de IA.

## Review Focus

1. Número digitado com máscara, minúsculas ou espaços (`2026.000123 `, `bap2600012345`) deve achar o processo.
2. Convite com prazo vencido não aparece no aviso, no badge nem na consulta como respondível.
3. TVL legada sem CNPJ gravado buscada por número não pode ser renovada; buscada pelo CNPJ certo, pode.
4. Duas renovações da mesma TVL legada para CNPJ ainda inexistente não criam duas empresas.
5. Sócio do REGIN que abre `/portal/solicitacoes/{id}/pendencias` pelo endereço direto recebe 403.

---

### Task 1: Chave e middleware dos serviços retirados

**Files:**
- Create: `app/Http/Middleware/EnsurePortalServicosExtrasEnabled.php`
- Modify: `bootstrap/app.php` (alias `portal.extras`), `config/viabiliza.php` (`features.portal_servicos_extras => env('SILE_PORTAL_SERVICOS_EXTRAS', false)`), `routes/portal.php`, `tests/TestCase.php`
- Test: `tests/Feature/Portal/PortalTresServicosTest.php`

**Interfaces:**
- Produces: alias de middleware `portal.extras` (404 quando `Settings::enabled('portal_servicos_extras')` é falso).

- [ ] **Step 1: Write the failing test** — `PortalTresServicosTest`, `setUp` desliga a chave (`config([...portal_servicos_extras => false])` + `Cache::forget`).
  - `test_servicos_retirados_respondem_404`: com cidadão logado e termo aceito, `GET` em `/portal/viabilidade`, `/portal/viabilidade/historico`, `/portal/empresas`, `/portal/empresas/cadastrar`, `/portal/procuracoes`, `/portal/solicitacoes`, `/portal/solicitacoes/nova`, `/portal/cnaes` → 404; `POST /portal/renovacao/consultar-cnpj` e `POST /portal/renovacao/legado/consultar` → 404.
  - `test_servicos_mantidos_respondem`: `/portal/painel`, `/portal/renovacao`, `/portal/notificacoes`, `/portal/conta/perfil`, `/portal/acessos` → 200.
  - `test_chave_ligada_religa_os_retirados`: liga a chave, `/portal/empresas` → 200.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Portal/PortalTresServicosTest.php` — FAIL (200 onde se espera 404).
- [ ] **Step 3: Implement** o middleware (`abort(404)` com a chave desligada) e envolver em `Route::middleware('portal.extras')` as rotas listadas na spec ("Chave dos serviços retirados"), mais `renovacao/consultar-cnpj` e `renovacao/legado/consultar`. `TestCase::setUp` liga a chave.
- [ ] **Step 4: Run** o teste novo e `php artisan test --compact tests/Feature/Portal tests/Feature/Renovacao` — PASS.
- [ ] **Step 5: Commit** `feat: desliga do portal os serviços além de renovar, consultar processo e responder convite`

### Task 2: Quem responde convite

**Files:**
- Modify: `app/Models/ViabilityRequest.php` (escopo `respondivelPor`), `app/Policies/ViabilityRequestPolicy.php` (`vinculadoPeloCpf` só papel solicitante)
- Test: `tests/Feature/Portal/ConviteSoDoRequerenteTest.php`; reescrever casos de sócio em testes existentes que esperam acesso (procurar `PAPEL_SOCIO` em `tests/`).

**Interfaces:**
- Produces: `ViabilityRequest::scopeRespondivelPor(Builder $query, ?int $userId, ?string $cpf): Builder` — `requester_user_id = userId` OU `cpfsVinculados` com `cpf` e `papel = ViabilityRequestCpf::PAPEL_SOLICITANTE`; sem nenhum dos dois, vazio.

- [ ] **Step 1: Failing tests:**
  - `test_requerente_ve_e_responde_convite` (200 em `pendencias`, POST responder → redirect com status).
  - `test_solicitante_declarado_do_regin_responde_convite` (processo `origin = Regin`, `requester_user_id = null`, vínculo solicitante com o CPF do usuário).
  - `test_socio_do_regin_nao_ve_detalhe_nem_responde` (GET `pendencias` → 403; POST responder → 403; `solicitacoes/{id}` → 403).
  - `test_escopo_respondivel_por_exclui_socio` (requerente, solicitante e sócio; o escopo devolve só os dois primeiros).
- [ ] **Step 2: Run** — FAIL no caso do sócio.
- [ ] **Step 3: Implement** escopo e política.
- [ ] **Step 4: Run** o arquivo e `php artisan test --compact --filter=Socio` — PASS (testes de sócio antigos reescritos para esperar 403 no detalhe).
- [ ] **Step 5: Commit** `feat: só o requerente ou o solicitante declarado responde convite`

### Task 3: Consultar processo por número

**Files:**
- Create: `app/Http/Controllers/Portal/ConsultaProcessoController.php`, `app/Services/Solicitacao/LocalizadorProcesso.php`, `resources/js/pages/portal/consulta/index.tsx`
- Modify: `routes/portal.php` (`GET portal/consulta`, nome `portal.consulta`, dentro de `portal.servicos`, `throttle:consulta-processo`), `app/Providers/FortifyServiceProvider.php` (limiter `consulta-processo`, 30/min por usuário)
- Test: `tests/Feature/Portal/ConsultaProcessoTest.php`

**Interfaces:**
- Produces: `LocalizadorProcesso::localizar(string $numero): ?ViabilityRequest` — normaliza (trim, maiúsculas, aceita `#^[A-Z0-9][A-Z0-9./-]{0,59}$#`), procura em `protocol_number`, `external_reference` e `decision.tvl_product_number`, ignora rascunho; prefere o que casa pelo protocolo, depois o maior id. Usado também pela Task 5 (entrada `destino=processo`).
- Props da página: `numero` (string|null), `encontrado` (bool), `processo` (null ou `{protocol_number, numero_viabilidade, servico, empresa: {razao_social, cnpj}, protocolado_em, status: {value, public_label}, convite: {aberto: bool, prazo: string|null}, pode_responder: bool, solicitacao_id: int|null}`), `timeline` (`TimelineSolicitacao::build($p, publico: true)`).

- [ ] **Step 1: Failing tests:**
  - `test_acha_por_protocolo_bap_e_viabilidade` (três consultas, mesmo processo; inclui `bap2600012345` minúsculo e protocolo com espaço).
  - `test_qualquer_cidadao_consulta_sem_dados_pessoais` (outro usuário; resposta Inertia sem chaves `cpf`, `email`, `phone`, `requester`, `description`; sem endereço).
  - `test_nao_encontrado_ou_invalido_mostra_a_mesma_mensagem` (`encontrado = false` para número inexistente e para `'<script>'`).
  - `test_pode_responder_so_para_o_requerente_com_convite_no_prazo` (requerente com pendência aberta no prazo → `pode_responder = true`; prazo vencido → false; outro usuário → false).
  - `test_consulta_encontrada_e_auditada` (registro em activity log com `viability_request_id`).
  - `test_rascunho_nao_e_encontrado`.
- [ ] **Step 2: Run** — FAIL (rota inexistente).
- [ ] **Step 3: Implement** localizador, controller (auditoria via `AuditService::log('solicitacoes', 'consulta-processo', ...)`) e página: campo único com `Label`/`Input` do portal, exemplos dos três formatos, resultado em `Card` com `dl`, `Badge` da situação, tramitação no mesmo componente/markup de `protocolo-publico.tsx`, botão "Responder convite" para `/portal/solicitacoes/{id}/pendencias` quando `pode_responder`.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat: consulta de processo por número do processo, da viabilidade ou do BAP`

### Task 4: Convites no painel, na lista e no menu

**Files:**
- Create: `app/Http/Controllers/Portal/ConviteController.php`, `app/Services/Painel/ConvitesDoCidadao.php`, `resources/js/pages/portal/convites/index.tsx`
- Modify: `routes/portal.php` (`GET portal/convites`, `portal.convites.index`, dentro de `portal.servicos`), `app/Services/Painel/PainelCidadaoService.php`, `app/Http/Middleware/HandleInertiaRequests.php` (prop `convitesPendentes`), `tests/Feature/Portal/PainelCidadaoTest.php`
- Test: `tests/Feature/Portal/ConvitesTest.php`

**Interfaces:**
- Consumes: `respondivelPor` (Task 2).
- Produces: `ConvitesDoCidadao::abertos(User $efetivo): Collection` — `AnalysisPendency` aberta, `due_at` nulo ou no futuro, de processo `respondivelPor($efetivo->id, $efetivo->cpf)`, ordenada por `due_at`; `::total(User): int`. Item: `{id, solicitacao_id, protocol_number, empresa, descricao, due_at}`.

- [ ] **Step 1: Failing tests:**
  - `ConvitesTest::test_lista_so_convites_respondiveis_no_prazo` (requerente vê o seu; sócio não; vencido some).
  - `ConvitesTest::test_badge_compartilhado_conta_os_convites` (prop `convitesPendentes` = 1 no painel).
  - `PainelCidadaoTest::test_atencao_lista_so_convites_do_requerente_sem_rascunhos` (substitui o caso de rascunho do painel).
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement**: `atencao` passa a ter só `pendencias` vindas de `ConvitesDoCidadao::abertos`; indicadores e recentes continuam com `visivelPara`.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat: convites para responder no painel, em lista própria e no menu`

### Task 5: Entrada pelo Portal de Serviços

**Files:**
- Modify: `app/Http/Controllers/Portal/EntradaPortalServicosController.php`, `tests/Feature/PortalServicos/EntradaPortalServicosTest.php`

- [ ] **Step 1: Failing tests:** `test_destino_processo_abre_a_consulta_com_o_numero` (redirect `/portal/consulta?numero=2026.000123`, também para quem não é dono); `test_destinos_nova_e_autonomo_abrem_o_painel_com_aviso` (redirect `portal.dashboard` com flash `info` = "Os pedidos de viabilidade começam na JUCEB. Pelo portal você renova viabilidade, consulta processo e responde convite."). Reescrever os casos antigos de `detalhar` que esperavam `solicitacoes.show`.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** (o `detalhar` antigo some; número inválido vai à consulta sem número).
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Commit** `feat: Detalhar do Portal abre a consulta; pedido novo orienta a JUCEB`

### Task 6: Renovação sem vínculo de empresa

**Files:**
- Create: `app/Services/Renovacao/BuscaRenovacao.php`
- Modify: `app/Enums/CompanySource.php`, `app/Http/Controllers/Portal/RenovacaoController.php`, `app/Services/Renovacao/ProvisoriasLegadasService.php`, `app/Services/Renovacao/RenovacaoProtocoloService.php`, `routes/portal.php`, `resources/js/pages/portal/renovacao/index.tsx`, `resources/js/components/renovacao/legado-item.tsx`
- Test: `tests/Feature/Renovacao/RenovacaoBuscaTest.php`; reescrever `RenovacaoVinculoTest`, `RenovacaoLegadaVinculoTest`, `RenovacaoLegadaPortalTest`, `RenovacaoPortalTest` onde esperam 403 por falta de vínculo ou usam `legado/{empresa}`.

**Interfaces:**
- Produces:
  - `BuscaRenovacao::buscar(string $termo): array{itens: list<array>, indisponivel: bool, termo_invalido: bool}` — 14 dígitos = CNPJ; 7 dígitos ou `\d{10}-\d{8}/\d{4}` = número. Item: `{origem: 'viabiliza'|'legado', id: ?int, numero, empresa, cnpj: ?string, endereco, validade, situacao: ?string, dias_para_vencer: ?int, elegivel, motivo, url: ?string}`; `url` = `/portal/renovacao/{id}` ou `/portal/renovacao/legado/{cnpj}/{numero}`.
  - `ProvisoriasLegadasService::porCnpj(string $cnpj): array{itens, indisponivel}` e `::porNumero(string $numero, ?string $cnpj): array{itens, indisponivel}` — titularidade com `new Company(['cnpj' => ...])` em memória. TVL sem CNPJ gravado e sem CNPJ na busca → item com `elegivel = false`, `motivo = 'Busque pelo CNPJ da empresa para renovar esta viabilidade.'`.
  - Rotas `renovacao/legado/{cnpj}/{numero}` (GET/POST, `whereNumber`), `cnpj` com 14 dígitos.
  - `RenovacaoProtocoloService::protocolarLegada(TvlLegadaDetalhe $tvl, string $cnpj, User $ator, array $contato, array $declaracaoIds): ViabilityRequest` — resolve a empresa dentro do lock e da transação: menor id com o CNPJ, senão cria (`source = Legado`, `legal_name` = `$tvl->razaoSocial` ou consulta de CNPJ, endereço do imóvel).

- [ ] **Step 1: Failing tests (`RenovacaoBuscaTest`):**
  - `test_busca_por_cnpj_junta_viabiliza_e_sistema_anterior` (TVL deferida local de empresa sem vínculo com o usuário + provisória do legado falsa; dois itens com as origens).
  - `test_busca_por_numero_acha_tvl_do_viabiliza_e_do_legado`.
  - `test_tvl_legada_sem_cnpj_buscada_por_numero_pede_o_cnpj`.
  - `test_qualquer_cidadao_abre_o_assistente_e_contato_e_o_do_usuario` (props `contato.email` = e-mail do usuário).
  - `test_protocolo_legado_cria_a_empresa_uma_vez` (dois protocolos seguidos de TVLs diferentes do mesmo CNPJ novo → 1 `Company` com `source = legado`; requerente = usuário).
  - `test_protocolo_legado_reaproveita_a_empresa_de_menor_id`.
  - `test_termo_invalido_avisa_os_formatos`.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** serviço, controller (`index` lê `?busca=`; `show`/`store` sem `autorizarEmpresa`; `showLegado`/`storeLegado` por CNPJ), enum e página (campo único com máscara livre, cartões unificados com selo "Viabiliza"/"Sistema anterior", aviso de sistema anterior indisponível; sai o bloco da SEDUR e o modal).
- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/Renovacao` — PASS.
- [ ] **Step 5: Commit** `feat: renovação por CNPJ ou número, sem vínculo de empresa, no Viabiliza e no sistema anterior`

### Task 7: Menu, painel e landing

**Files:**
- Modify: `resources/js/navigation/portal-nav.ts`, `resources/js/navigation/portal-nav.test.ts`, `resources/js/components/app/sidebar-active.test.ts`, `resources/js/pages/portal/dashboard.tsx`, `resources/js/navigation/portal-nav-icons.ts` (se faltar ícone)
- Test: os dois testes Vitest acima.

- [ ] **Step 1: Failing test (Vitest):** o grupo Serviços tem exatamente `['/portal/renovacao', '/portal/consulta', '/portal/convites']`; `PORTAL_ACOES` tem as mesmas três rotas; `assertPortalNavHealth()` vazio.
- [ ] **Step 2: Run** `npx vitest run resources/js/navigation` — FAIL.
- [ ] **Step 3: Implement**: catálogo novo (Renovar viabilidade `check-circle`, Consultar processo `search`, Responder convite `bell` → trocar Notificações para outro ícone para não repetir); badge do item Responder convite com `convitesPendentes`; painel: aviso no topo ("Você tem N convite(s) para responder"), três cartões, indicadores sem link para listas retiradas (convites → `/portal/convites`), recentes levando a `/portal/consulta?numero=`, estado vazio sem link para consulta prévia, sem rascunhos e sem "Minhas consultas de viabilidade".
- [ ] **Step 4: Run** Vitest e `npm run build` — PASS sem erro de tipo (`npx tsc --noEmit` se o projeto usar).
- [ ] **Step 5: Commit** `feat: menu, painel e landing do portal com os três serviços`

### Task 8: Verificação

- [ ] `DB_TEST_PORT=5444 php artisan test --compact` inteiro; comparar falhas com as 4 já conhecidas na base (`ConsultarProtocoloTest` de prazo e 3 do `SolicitacaoSmokeTest`).
- [ ] Imagem nova da stack `viabiliza-portal-teste` e conferência no navegador (painel, consulta, convites, renovação da TVL 2104040 até o assistente).

### Task 9: Consulta também no Simplifica (revisão de 24/10)

**Files:** `app/Services/Legado/LegadoViabilidadeClient.php` (`processo(string $numero): ?array` → `GET /v1/processos/{numero}`), `app/Services/Solicitacao/ConsultaProcessoLegado.php` (`localizar(string $numero): ?array{processo, timeline}`), `app/Http/Controllers/Portal/ConsultaProcessoController.php`, `resources/js/pages/portal/consulta/index.tsx`, `tests/Feature/Portal/ConsultaProcessoLegadoTest.php`.

- [ ] Testes: `test_numero_do_processo_do_simplifica_mostra_o_processo_com_a_tramitacao` (sem `mensagem` no payload), `test_numero_de_tvl_do_simplifica_leva_ao_processo`, `test_processo_do_viabiliza_tem_prioridade`, `test_simplifica_fora_do_ar_avisa`, `test_consulta_do_simplifica_e_auditada`. Ver falhar, implementar, ver passar, commit.
