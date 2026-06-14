---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: executing
stopped_at: Completed Phase 4 (Georreferenciamento e Território) — PostGIS (geo_layers/geo_features versionados com vigência, migrations driver-aware), geocodificação Nominatim atrás de contrato (throttle/retry parametrizados), TerritoryService/SpatialRepository (ST_Contains/ST_DWithin/ST_Intersects reais), LocationValidationService (sobreposição ST_Area/ST_Intersection com limiar parametrizado), mapa Leaflet (react-leaflet v5, client-side/SSR-safe) e página de consulta territorial no console SEDUR, comando geo:importar auditado + carga REAL do GeoSalvador (bairro 171, via 800, restrição ZEIS 234), CI real com PostGIS (.github/workflows/tests.yml + POSTGIS_TESTS_REQUIRED). gsd-verifier: passed 5/5. Suíte 448/448 SQLite + 15/15 @group postgis; evidência real: Nominatim (Elevador Lacerda) + consulta espacial (Farol da Barra → bairro "Barra").
last_updated: "2026-06-13T23:55:00.000Z"
last_activity: 2026-06-13 -- Phase 4 COMPLETE (verificação passed; bairro/via/restrição reais; zona/lote bloqueados pendente SEDUR)
progress:
  total_phases: 15
  completed_phases: 5
  total_plans: 39
  completed_plans: 39
  percent: 30
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-09)

**Core value:** Responder a viabilidade locacional de atividade econômica de forma automática, correta e auditável — fluxo expresso quando a lei permite, fundamentação legal em toda decisão.
**Current focus:** Phases 3, 3.1 e 4 concluídas — próxima acionável: Phase 5 (Motor de Regras da LOUOS) ‖ Phase 6 (Classificação de Risco). ATENÇÃO: a Fase 5 consome a ZONA urbanística, hoje BLOQUEADA (sem fonte vetorial pública — pendente SEDUR). O motor nasce parametrizável e roda com seeds derivados da Lei 9.148/2016 até a base oficial chegar. Phase 3.2 (GOV.BR) implementada e desligada, aguardando credenciamento.

## Current Position

Phase: 4 (Georreferenciamento e Território) — COMPLETE (verificação passed em 2026-06-13)
Plan: 8 of 8 (todos concluídos)
Status: Fase 4 fechada; próxima é a Fase 5 (Motor LOUOS) ou 6 (Risco) — exigem brainstorming
Last activity: 2026-06-13 -- Phase 4 COMPLETE (bairro/via/restrição reais; zona/lote bloqueados pendente SEDUR)

Progress: [███░░░░░░░] 30% (5/15 fases; 39 planos executados)

Next step: `/gsd-plan-phase 5` (Motor LOUOS) ou `/gsd-plan-phase 6` (Risco) — ambos exigem brainstorming (quadros parametrizados, golden cases, condicionante-pergunta). A zona urbanística (insumo do motor) segue bloqueada até a SEDUR liberar SIGIS/CA 2000.

### Fase 2.1 (INSERTED) — Template TailAdmin (concluída 2026-06-10)

- Visual TailAdmin (React 19 + Tailwind v4) aplicado em todo o app: design system no app.css (@theme com fonte Outfit, cores brand/gray/success/error/warning, shadows), contexts de tema (dark mode com localStorage) e sidebar, 19 ícones SVG inline, componentes base em resources/js/components/{ui,form,app}/ (Button, Badge, Modal, Alert, Dropdown, Table, Input, Select, Switch, AppSidebar, AppHeader, AppShell), 4 layouts e 19 páginas redesenhados.
- Zero dependências novas (sem react-router/ApexCharts/svgr); sem features de fachada (search/notificações do template não portados).
- Padrões para novas telas: AppShell parametrizado (items/homeHref/subtitle); páginas com PageBreadcrumb + cards rounded-2xl; tabelas com componente Table; forms com Input error/hint. Novas páginas DEVEM usar esses componentes.
- Regressão: 197/197 testes, typecheck e build verdes; screenshots validados (home, login, painel, CNAEs, parâmetros, dark mode).

### Fase 2.2 (INSERTED) — Refinamento premium de UI/UX (concluída 2026-06-10)

- Landing institucional completa em home.tsx: header sticky, hero, serviços (4 cards), como funciona (4 passos), base legal (LOUOS 9.148/2016, Decreto 32.636/2020, CNAE 2.3), CTA e footer — conteúdo 100% verdadeiro, sem estatísticas/depoimentos inventados.
- Sidebar subdividida em grupos com headings (API: SidebarGroup[] no AppShell/AppSidebar). Gestão: Visão geral/Cadastros/Sistema. Portal: Início/Serviços/Minha conta.
- Auth premium: painel institucional com gradiente brand, bullets verdadeiros (LOUOS, decreto, auditoria), hierarquia forte nos forms.
- PADRÃO DE CRUD (obrigatório para novas telas): datatable em card único com ação primária no header; criar/editar em Modal (700px forms grandes, 600px simples); ações destrutivas/impacto via ui/confirm-dialog.tsx (danger/warning/info, processing); ui/pagination.tsx compartilhado com meta "Mostrando X–Y de Z" (backend envia from/to/total); ui/empty-state.tsx distinguindo busca vazia de lista vazia; edição inline apenas quando for melhor usabilidade (ex.: parâmetros por grupo).
- Regressão: 197/197 testes, typecheck e build verdes; screenshots validados (landing, login, CNAEs com modal, sidebar agrupada).

### Fase 2.3 (INSERTED) — Segregação de rotas portal × retaguarda (concluída 2026-06-10)

- MAPA DE ROTAS VIGENTE: `/` redireciona para `/portal` (landing pública, name `home`); auth pública do cidadão sob `/portal/*` (Fortify com `prefix: portal` — `/portal/login`, `/portal/register`, `/portal/forgot-password`, `/portal/reset-password`, `/portal/email/verify`, `/portal/logout`, `/portal/user/password`); painel do cidadão em `/portal/painel` (name `portal.dashboard`, `fortify.home`); retaguarda com login interno próprio em `/gestao/login` (GET tela `auth/gestao-login` + POST no `AuthenticatedSessionController` do Fortify, names `gestao.login`/`gestao.login.store`), sem cadastro público.
- `bootstrap/app.php`: `redirectGuestsTo` por contexto (rotas `gestao*` → `gestao.login`; demais → `login`) e `redirectUsersTo` por perfil (`acessar-gestao` → `gestao.dashboard`; senão → `portal.dashboard`).
- LoginResponse por perfil (Fase 1) inalterado — usa nomes de rota. Logins separados em TELAS/rotas; autenticação única (mesmo guard `web` + permissões), padrão Laravel.
- Identidade visual: logo SILE (pin + edifício, `resources/js/components/app/logo.tsx` + `public/favicon.svg`); tela interna com aviso de acesso restrito.
- Regressão: 201/201 testes (4 novos de roteamento), pint/typecheck/build verdes.
- IDENTIDADES DISTINTAS por contexto (decisão do usuário, 2026-06-10): portal público = claro/acolhedor (AuthLayout split com painel institucional, "Bem-vindo de volta"); retaguarda = console interno escuro standalone (`auth/gestao-login.tsx` — gray-950 com grade técnica, badge "Ambiente interno", "Entrar no console", aviso de auditoria; SEM AuthLayout). Novas telas internas de auth seguem o padrão console; novas telas públicas seguem o padrão portal.
- MCP de UI/UX instalado no projeto (`.cursor/mcp.json`): `shadcn-ui` (@jpisnice/shadcn-ui-mcp-server — contexto de componentes/blocks shadcn v4 para referência de padrões). Requer reload do Cursor para carregar; usar como referência de UI/UX nas próximas telas, mantendo o design system TailAdmin próprio.

### Fase 2.4 (INSERTED) — Template SaaS de listagens e dashboard (concluída 2026-06-11)

- Biblioteca de componentes de listagem em `resources/js/components/` (spec: `.planning/phases/02.4-template-listagens/02.4-01-PLAN.md`; referências: SILE Design System kit, Horizon UI, Spruko Dashtic/Rixzo — identidade SILE mantida, zero dependências novas):
  - `ui/data-table/` — DataTable genérica tipada (`ColumnDef<T>`, ordenação controlada com aria-sort, skeleton rows, empty state integrado, densidade default/compact), `use-server-table.ts` (hook Inertia: busca com debounce 350ms, sort/filtros/per_page imediatos, preserveState+replace, volta à página 1), `table-toolbar.tsx` (busca com lupa + filtros + ações), `per-page-select.tsx` (whitelist + valor vigente).
  - `ui/` — Card/CardHeader/CardContent, KpiCard (delta opcional só com dado real), Skeleton/SkeletonText, Avatar (iniciais, cor determinística), ProgressBar (role progressbar), TableAction (tones brand/warning/success/error/neutral, href ou onClick, title); Button ganhou variantes ghost/danger e size xs.
  - `app/page-header.tsx` — título + trilha (ancestrais via prop; item final é o título) + ações; substituiu TODOS os PageBreadcrumb duplicados.
- PADRÃO DE LISTAGEM (obrigatório para novas telas): PageHeader → Card(CardHeader com ação primária) → CardContent(TableToolbar → chips de filtros ativos → DataTable → Pagination). CNAEs é a listagem-modelo (ordenação por código/denominação, filtro de situação com chip "Limpar filtro", page size 10/15/25/50 com whitelist no backend e default parametrizado).
- Backend: CnaeController@index aceita sort/direction/per_page/active com whitelists (constantes técnicas) e ecoa `filters` + `perPageOptions`; DashboardController (gestão) envia `kpis` reais condicionados à permissão (cnaes ativos/total, usuários ativos/total, perfis/permissões, logins na janela `ui.dashboard.acessos_janela_dias` — parâmetro novo no catálogo, 11 parâmetros). Sem delta inventado nos KPIs (sem série histórica ainda).
- FIX importante: busca com `LIKE` era case-sensitive no PostgreSQL (banco real) — testes SQLite não pegavam. CnaeController e UserManagementController agora usam `whereLike(..., caseSensitive: false)`. Padrão para TODAS as buscas futuras.
- Regressão: 209/209 testes (8 novos: CnaeIndexTableTest com 6, GestaoDashboardKpisTest com 2), pint/typecheck/build verdes; screenshots validados (dashboard e CNAEs claro/escuro, usuários com Avatar, perfis, parâmetros, mobile 375px com toolbar empilhada e scroll-x).
- RE-TEMATIZAÇÃO "Console SEDUR" (plano 02.4-02, pedido do usuário em 2026-06-11): a GESTÃO usa sidebar escura permanente gray-950 (`variant="console"` no AppShell/AppSidebar — estende a identidade do login interno da Fase 2.3 para dentro do app; item ativo `bg-brand-500/15 text-white`, logo branca com marca brand-400); KpiCard redesenhado em layout horizontal com well colorido 56px (`tone: brand|success|warning|error|info` — cor é diferenciação visual, não estado); DataTable com thead `bg-gray-50 dark:bg-white/[0.02]`; listagens da gestão em `density="compact"`. O PORTAL permanece `variant` light (claro/acolhedor, decisão da Fase 2.3). Novas telas da gestão DEVEM usar o console; telas do cidadão, o light.

## Performance Metrics

**Velocity:**

- Total plans completed: 22
- Average duration: 11 min
- Total execution time: ~4.21 h

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-identidade | 9/9 ✓ | ~96 min | 11 min |
| 02-administracao-base | 8/8 ✓ | ~100 min | 12 min |
| 03-cadastro-empresarial | 6/9 | ~85 min | 14 min |

**Recent Trend:**

- Last 5 plans: 03-02 (18 min), 03-03 (12 min), 03-04 (14 min), 03-05 (9 min), 03-06 (10 min)
- Trend: 03-06 sem deviations (plano executado como escrito); HU-025/HU-026 backend em 2 tasks TDD; 82 testes do grupo Companies verdes (17 novos)

*Atualizado após cada plano concluído*

## Accumulated Context

### Decisions

Registro completo na tabela Key Decisions de PROJECT.md. Mais relevantes para o trabalho atual:

- [2026-06-12, decisão do usuário] AUTENTICAÇÕES SEPARADAS por ambiente: portal (guard web) e gestão (guard gestao) têm sessões independentes — estar logado num ambiente não significa nada no outro. Revoga a interpretação "login único com destino por perfil" da Fase 2.3 (a HU-002 não exigia sessão única). Login do console em `Gestao\LoginController` (POST /gestao/login, guard gestao) com paridade de segurança: throttle parametrizado + Lockout auditado, anti-oráculo, conta inativada, CA-04 (sem permissão = bloqueado+auditado NO LOGIN) e e-mail verificado como pré-condição de login (rotas internas SEM middleware verified — o aviso/reenvio de verificação é do fluxo do portal). Login do portal leva sempre ao portal (admin no portal é cidadão comum; console só via /gestao/login). `User::$guard_name = 'web'` fixo (papéis/permissões são conceito único, independente do guard de sessão). Settings e termo LGPD com `auth:web,gestao`; troca de senha via PUT /settings/password próprio (rota Fortify exige guard web). Logout da gestão derruba só o guard gestao (cookie de sessão é único por domínio; logout do portal via Fortify invalida a sessão inteira — limitação conhecida). Testes de rotas /gestao usam `actingAs($user, 'gestao')`.

- Auditoria transversal (RN-002: usuário, data/hora, origem, ação, resultado, versão de regras) nasce como infraestrutura na Fase 1; a Fase 12 só consulta/exporta/LGPD.
- Requisitos rastreados pelo ID da HU (HU-001 a HU-131); cada CA BDD vira feature test PHPUnit — nenhuma HU concluída sem CAs passando.
- Mantenedores de quadros/condicionantes/risco (HU-015 a HU-020) entram junto com os motores (Fases 5 e 6) — fatia vertical.
- Motor de regras parametrizável: regras como dados versionados, nunca código; carga oficial (planilhas SEDUR) substitui seeds quando entregue.
- Integrações atrás de contratos; adaptador só é concluído contra homologação real — sem acesso = feature explicitamente bloqueada, nunca simulada.
- Risco municipal (Decreto 32.636/2020) e risco sanitário (planilha VISA) são dimensões separadas no modelo.
- Parametrização máxima (HU-014): nenhum valor de negócio hardcoded; funcionalidades acopláveis com feature toggle administrável.
- [01-01] Fortify com exatamente 4 features (registration, resetPasswords, emailVerification, updatePasswords); 2FA/passkeys fora — migrations publicadas mantidas, inofensivas com features off.
- [01-01] Parâmetros de segurança em config/sile.php lidos via App\Support\Settings::get(); Password::defaults() parametrizado — HU-014 troca o backend para banco sem tocar call sites.
- [01-01] Permissions-Policy com geolocation=(self) — mapa da fase 4 pode pedir localização no próprio domínio.
- [01-01] Rotas Fortify confirmadas (login.store, register.store, password.*, verification.*, user-password.update) — registradas no 01-01-SUMMARY.md.
- [01-02] 403 auditado via render callback em AccessDeniedHttpException (exceção PREPARADA pelo Handler — callback em AuthorizationException nunca dispararia) + UnauthorizedException do spatie.
- [01-02] fortify.limiters.login = null: pipeline usa EnsureLoginIsNotThrottled e dispara Lockout (auditado em access_logs); limiter nomeado daria 429 sem evento. 01-06 deve parametrizar respeitando isso (limite atual: 5 fixo do LoginRateLimiter).
- [01-02] AuditService::log(logName, event, description, properties, subject, result, rulesVersion) e logBlocked() — assinaturas travadas para 01-06/01-07/01-08.
- [01-02] access_logs é tabela dedicada imutável (sem updated_at); AccessLogFactory pronta para HU-010 (01-08).
- [01-03] Papéis travados: cidadao/analista/gestor/administrador num único guard web; contador/procurador NÃO são papéis (poder vem de dados). Factory states por papel exigem seed prévio de RolesAndPermissionsSeeder.
- [01-03] Ambientes segregados por middleware permission:acessar-gestao (não por guard); nomes portal.*/gestao.* alimentam o channel da auditoria. Portal usa auth+verified; lgpd.accepted entra no 01-05; MustVerifyEmail no 01-04.
- [01-03] Props Inertia compartilhadas (auth.user/roles/permissions, flash.status) tipadas em SharedProps (resources/js/types/index.d.ts); auth-layout.tsx pronto para as telas de auth dos planos 01-04/01-05/01-06.
- [01-04] Cadastro coleta cpf (obrigatório, único, 11 dígitos sem máscara, Rule ValidCpf própria) e phone (opcional); UserFactory gera CPF válido. Senha hasheada SÓ pelo cast hashed do User (Hash::make removido da action publicada).
- [01-04] User implements MustVerifyEmail — portal bloqueia não verificados; testes que postam /register precisam seedar RolesAndPermissionsSeeder (assignRole no CreateNewUser).
- [01-04] Mensagem required do laravel-lang pt-BR é "É obrigatória a indicação..." — asserções de validação devem usar 'obrigatória', não 'obrigatório'.
- [01-05] Termo LGPD é dado versionado: seed publica v1; gate lgpd.accepted desarma sem termo publicado; rotas do termo ficam fora do subgrupo protegido; na gestão a permissão é avaliada ANTES do termo (403 prevalece).
- [01-05] Testes que acessam rotas protegidas com termo seedado usam User::factory()->...->withAcceptedLgpdTerm(); aceite é firstOrCreate (idempotente sob unique user_id+legal_term_id).
- [01-06] Limite de login parametrizado nos DOIS pontos: RateLimiter::for('login') E rebinding do LoginRateLimiter (com limiters.login=null o limiter nomeado não é consultado; sem o rebinding o 5 do vendor governaria).
- [01-06] Bloqueio temporário em request web responde redirect 302 com erro auth.throttle em 'email' (não 429 — status só é honrado em JSON); testes assertam redirect+erro+access_logs bloqueio.
- [01-06] Form do Inertia v3 aceita errorBag nativamente (errorBag="updatePassword" em settings/password); rotas de recuperação guest-only redirecionam autenticados para route('home').
- [01-07] Representação vive na sessão (acting_procuration_id) e é revalidada em TODA request do portal por ResolveRepresentation — revogação tem efeito imediato; Context acting_for_user_id + scoped CurrentRepresentation alimentam auditoria e share actingFor do Inertia.
- [01-07] ResolveRepresentation RESETA Context e scoped service quando não há representação válida (estado sobrevive entre requests em testes/queue); unicidade "uma procuração ativa por par" na aplicação (FormRequest::after), sem índice único parcial.
- [01-08] CPF imutável por omissão nas regras do ProfileUpdateRequest (valor enviado é ignorado); troca de e-mail zera email_verified_at e reenvia VerifyEmail.
- [01-08] Histórico de acessos filtra user_id OR email — titular vê falhas/bloqueios pré-login gravados sem user_id; page size parametrizado em sile.ui.access_history.per_page via Settings::get.
- [01-08] Consulta administrativa de acessos auditada explicitamente (log 'acessos', event 'consulta-acessos', target_user_id nas properties) — consulta relevante a dados de terceiro (CA-02).
- [02-01] CSV oficial CNAE fiel à fonte: 1.331 subclasses versionadas em database/data/; 9900-8/00 ausente do arquivo NÃO inventada (proveniência em scripts/convert-cnae-xlsx.py; relatório formal da divergência no import 02-04). Códigos no formato oficial; normalização para dígitos é do CnaeImportService.
- [02-01] RolesAndPermissionsSeeder aditivo (givePermissionTo, nunca sync): re-seed não desfaz ajustes de permissão feitos pelo admin via HU-013. 8 permissões; administrador com todas as manter-*; analista/gestor com consultar-cnaes.
- [02-03] Inativação de conta: users.inactivated_at (fora do fillable — só forceFill em fluxo autorizado); Fortify::authenticateUsing bloqueia com mensagem pt-BR + access_log evento 'inativada' (curto — varchar(20)); anti-oráculo: senha errada retorna null (fluxo padrão 'falha').
- [02-03] EnsureUserIsActive no append GLOBAL do grupo web (bootstrap/app.php): sessão aberta de inativado derrubada na request seguinte (invalidate+regenerateToken); cobre portal/gestão/settings sem tocar arquivos de rota. 02-05 só grava/limpa inactivated_at.
- [02-02] Settings::get com backend banco+cache+fallback (cache → parameters.value ?? default do catálogo → config/sile.php → default do call site; QueryException → config) mantendo assinatura — call sites e tests/Unit/Support/SettingsTest.php intactos. Settings::enabled(feature) para toggles.
- [02-02] Parameter: sensitive FORA do fillable e ordenado antes de value na factory (mutator condicional lê a flag — Pitfall 3); SEM HasAuditoria (vazaria sensível) — histórico explícito com mascaramento entra no 02-07; requires_connection_test é contrato do RN-010 (implementação real na Fase 13).
- [02-02] ParameterSeeder upsert SÓ de metadados (value administrado preservado em re-seed); 10 chaves nos grupos seguranca/ui/features; NÃO registrado no DatabaseSeeder (responsabilidade do 02-08). TTL do cache é constante técnica (sile.parameters.cache_ttl), não parâmetro do registry.
- [02-04] Import oficial de CNAEs: upsert por code NÃO toca active (desativação administrada sobrevive a re-import); divergência 9900-8/00 (publicação cita 1.332, arquivo traz 1.331) registrada no relatório auditado (rules_version cnae-subclasses-2.3), nunca inserida silenciosamente — caminho é o CRUD manual (testado com esse caso real).
- [02-04] Cnae: PK surrogate id (code unique em dígitos, não PK), hierarquia desnormalizada, accessor formatted_code — pronto para dimensões de risco da Fase 6 sem retrabalho. UpdateCnaeRequest valida só description/active (código imutável na edição, padrão CPF). CnaeSeeder fora do DatabaseSeeder (02-08).
- [02-04] Form do Inertia v3 SOBRESCREVE onSubmit custom (handler interno definido após spread das props): confirmações de submit vão no onClick do botão type=submit com preventDefault. Busca por código só aplica branch de dígitos quando o termo contém dígitos (termo textual não vira LIKE '%').
- [02-05] Inativação pela UI é toggle único (PUT usuarios/{user}/inativacao alterna pelo estado) com anti-lockout no FormRequest::after(); auditoria de usuários é EXPLÍCITA (log 'usuarios', target_user_id nas properties) — inactivated_at fora do fillable e papéis (relação) não entram no diff do HasAuditoria.
- [02-05] Listagem de usuários nunca expõe CPF em claro (LGPD): transform por item com cpf_masked ***.***.***-DD; teste asserta missing('cpf') no payload Inertia.
- [02-06] Papéis estruturais protegidos por Roles::STRUCTURAL (constante única) validada em FormRequest::after(): rename/exclusão bloqueados, acessar-gestao não removível do administrador — três proteções anti-lockout com teste nomeado cada. Regra real no backend; UI só comunica (checkbox disabled + hidden, nome readOnly).
- [02-06] Update de perfil pela UI usa syncPermissions (conjunto exato marcado — intenção do admin) em contraste com o seeder aditivo givePermissionTo do 02-01; sem forgetCachedPermissions manual (spatie v8 reseta nos métodos built-in). Roles do spatie sem HasAuditoria — auditoria explícita no log 'perfis' com permissoes_antes/depois.
- [02-07] Toggle real features.procuracoes (CA-06): store bloqueado com aviso pt-BR, index acessível, destroy NUNCA bloqueado — segurança do outorgante prevalece sobre o toggle. Toggles futuros (Fases 9/11/13/14) só registram chaves features.* no catálogo e usam Settings::enabled — mecanismo fechado.
- [02-07] Validação de parâmetro é dinâmica via validation_rules do próprio registro (FormRequest::after); sensível com campo vazio = manter valor (sem gravação, sem activity); UI nunca recebe valor sensível (prop null + password vazio). Histórico via AuditService explícito com [criptografado] e latest('id') para ordem estável no mesmo segundo.
- [03-01] ValidCnpj nasce compatível com CNPJ alfanumérico (IN RFB 2.229/2024, produção julho/2026): normaliza `[^A-Z0-9]` + uppercase, valida `/^[A-Z\d]{12}\d{2}$/`, DV por módulo 11 sobre `ord(char)-48`. Pesos `[6,5,4,3,2,9,8,7,6,5,4,3,2]` com `array_slice($weights, 13 - $position)` para posições 12 e 13 (NÃO usar o snippet de 12 pesos do RESEARCH). Coluna cnpj é string(14) SEMPRE, nunca numérico.
- [03-01] Schema empresarial: companies (cnpj unique), company_user (modelo próprio CompanyUser com started_at/ended_at — encerramento nunca apaga, histórico preservado), company_cnae (pivot SEM modelo). Unicidade "um vínculo ativo por par" e "exatamente um principal" ficam NA APLICAÇÃO (precedente procurations/[01-07]); company_cnae.cnae_id com restrictOnDelete como defesa no banco + unique(company_id, cnae_id).
- [03-01] Pivot company_cnae exige nome EXPLÍCITO no belongsToMany em Company::cnaes() e Cnae::companies() — Eloquent inferiria cnae_company (ordem alfabética). Accessor formatted_cnpj é posicional (regex por posição), funciona com alfanumérico.
- [03-01] Parâmetros do lookup de CNPJ: features.cnpj_lookup (default true), integrations.cnpj_lookup.base_url (requires_connection_test, contrato RN-010 — teste real na Fase 13), ui.companies.per_page — catálogo passa a 14 chaves. Constantes técnicas (timeout 8s, retries 2, cache_ttl 86400) ficam SÓ em config/sile.php, nunca no registry (precedente [02-02]).
- [03-01] Pendência da Fase 2 resolvida: CnaeController::destroy bloqueia exclusão de CNAE vinculado (companies()->exists()) com mensagem pt-BR via flash.error. Canal flash.error agora compartilhado no HandleInertiaRequests e exibido via Alert variant=error na gestao-layout — padrão para bloqueios comunicados (nunca silenciosos).
- [03-02] Consulta de CNPJ (HU-021) atrás de contrato: interface App\Services\Cnpj\CnpjLookup (lookup(string): CnpjData) + DTO readonly CnpjData (fromBrasilApi/toArray snake_case) + provider real BrasilApiCnpjLookup. Binding no AppServiceProvider — a Fase 13 (HU-105) troca SÓ o binding pelo provider conveniado RFB, sem tocar call sites. base_url lido via Settings::get (banco→cache→config); trocar BrasilAPI↔minhareceita é mudança de parâmetro, sem deploy (provado em teste).
- [03-02] Cache de consulta CNPJ (Cache::remember key sile.cnpj_lookup.{cnpj}, ttl config 86400) grava SÓ sucesso: exceção dentro do closure impede a gravação (falha nunca cacheada). Constantes técnicas (timeout 8s, connectTimeout 3s, retries 2) em config/sile.php. ConnectionException convertida em CnpjLookupException no provider; 404 → CnpjNotFoundException.
- [03-02] Endpoint POST /portal/empresas/consultar-cnpj (name portal.empresas.consultar-cnpj) auditado em TODAS as saídas via AuditService (event consulta-cnpj, result sucesso/falha/bloqueado, properties com cnpj e provider). Toggle features.cnpj_lookup desligado bloqueia ANTES de qualquer request HTTP (degradação comunicada 422). Shape JSON = CnpjData::toArray (snake_case) — contrato do formulário React no 03-06.
- [03-02] bootstrap/app.php: shouldRenderJsonWhen estendido para portal/empresas/consultar-cnpj quando expectsJson() — o projeto restringia render JSON de exceções a api/*, fazendo o portal redirecionar (302) em vez de 401/422/404 JSON. Padrão para futuros endpoints JSON do portal (useHttp): adicionar a rota na condição.
- [03-03] Importação REDESIM (HU-022) com lógica REAL atrás de contrato: RedesimImportService->import(jsonPath) retorna {lidos, importados, atualizados, rejeitados[], avisos[]} e audita via AuditService (log 'empresas', event 'importacao-redesim', rules_version 'redesim-import-v1', result falha SÓ quando todos rejeitados; causer null = sistema). Validação por item (protocolo/razão social/CNPJ via ValidCnpj/CNAE principal existente); item inválido rejeitado com motivo pt-BR carregando o protocolo, sem inserção parcial (DB::transaction por item). Estrutura do payload é REFERÊNCIA A VALIDAR COM A SEDUR (REGIN/JUCEB) — o transporte real é a Fase 13 (HU-103) e ajusta só o parsing.
- [03-03] Upsert por CNPJ NÃO reescreve source (origem de criação imutável — Pitfall 8): empresa criada manual continua manual após reimport, marcando redesim_synced_at + redesim_protocol. CNAEs sincronizados com sync() exato (principal is_primary=true + secundários resolvidos). CNAE inativo (principal/secundário) IMPORTA com aviso (dado da Junta é fato consumado; "somente ativos" vale só para seleção manual HU-025/026); secundário inexistente vira aviso e é ignorado. Import NUNCA cria company_user (payload não traz usuário do portal — associação é da Fase 13).
- [03-03] Comando redesim:importar {arquivo} (App\Console\Commands\ImportRedesimCommand, auto-descoberto): relatório pt-BR (Lidos/Importados/Atualizados + seções Rejeitados/Avisos), exit 1 para arquivo inexistente / JSON inválido (JsonException) / todos os itens rejeitados; exit 0 caso contrário. NÃO existe rota pública de import (testado — entrada é exclusivamente comando/serviço, CA-04). Payload de referência REAL versionado em database/data/redesim-exemplo.json (dados públicos RFB) para homologação; fixture em tests/Fixtures/redesim/.
- [03-04] CompanyPolicy integrada à representação [01-07]: effectiveUser() = CurrentRepresentation::grantor() ?? $user, replicado na policy E no CompanyController. view() aceita vínculo ativo OU encerrado (histórico visível); update/manageCnaes/endLink exigem vínculo ATIVO (whereNull ended_at). Gate manageCnaes pronto para o 03-05; endLink para o 03-06 (HU-028).
- [03-04] store de empresa (HU-023) cria Company (source=manual) + CompanyUser (responsavel, started_at=now) na MESMA DB::transaction, em nome do usuário efetivo — em representação o vínculo nasce para o REPRESENTADO (user_id=grantor), nunca para o procurador; auditoria created enriquecida com acting_for_user_id=grantor. CNPJ duplicado bloqueado no StoreCompanyRequest com unique + mensagem "Já existe empresa cadastrada com este CNPJ." (CA-03); prepareForValidation normaliza CNPJ (uppercase/sem máscara), telefone e CEP (só dígitos).
- [03-04] Listagem Minhas empresas (HU-027) server-driven (padrão CnaeController@index): escopo whereHas('links', user efetivo), busca por razão social/fantasia (whereLike caseSensitive:false) ou prefixo de CNPJ, ordenação whitelistada (SORTABLE_COLUMNS=['legal_name']), paginação parametrizada ui.companies.per_page; eager loading anti-N+1 (wherePivot is_primary + vínculo do usuário). Company::countForUser exposto em totalCompanies (contagem reutilizável p/ painel do cidadão). Rotas portal.empresas.index/create/store ANTES de empresas/{company} (03-05).
- [03-05] HU-024 atualizar empresa: CompanyController::show (Gate view, aceita vínculo ativo OU encerrado) com props completas (company, cnaes primary/secondaries, links com is_current_user, abilities update/manageCnaes/endLink) — contrato direto da tela de detalhe 03-08. update (Gate update, vínculo ATIVO) com CNPJ IMUTÁVEL POR OMISSÃO: o UpdateCompanyRequest não inclui cnpj nas rules, logo validated() nunca o contém e o valor enviado é ignorado (padrão CPF [01-08]); auditoria 'updated' com attribute_changes automática (HasAuditoria). Rotas empresas/{company} (show/update) DEPOIS das literais.
- [03-05] HU-028 encerrar vínculo: CompanyLinkController::destroy encerra o PRÓPRIO vínculo ativo do usuário efetivo via ended_at/ended_reason — NUNCA delete físico (histórico preservado). Proteção do último responsável ativo em EndCompanyLinkRequest::after() (precedente anti-lockout [02-05]): bloqueia com "A empresa não pode ficar sem responsável ativo." contando APENAS o papel responsavel (procurador único encerra normalmente). Auditoria de negócio explícita event 'encerramento-vinculo' (AuditService, log_name 'empresas', props empresa_id/vinculo_id/motivo) coexiste com o 'updated' técnico do HasAuditoria. Rota DELETE empresas/{company}/vinculo.
- [03-04] Testes Inertia da Fase 3 usam assertInertia has()/where() SEM ->component() — as páginas React (portal/empresas/index, cadastrar) só nascem no 03-07 (wave 6) e a checagem de componente exige o arquivo em disco. PENDÊNCIA p/ 03-07: adicionar a verificação visual/componente quando as telas existirem. Shape das props do index documentado no 03-04-SUMMARY (contrato da DataTable).
- [03-06] Escrita do pivot company_cnae EXCLUSIVAMENTE via CompanyCnaeService em DB::transaction: setPrimary demove→promove (o antigo principal VIRA secundário — linha do pivot preservada, nunca removida); syncSecondaries com sync() calculado incluindo o principal no payload (conjunto exato dos secundários, padrão syncPermissions [02-06]). Auditoria explícita dentro da transação: 'cnae-principal' (cnae_anterior/cnae_novo) e 'cnaes-secundarios' (antes/depois) — insumo das Fases 5/6. Seleção manual aceita SÓ CNAEs ativos (Rule::exists where active nos FormRequests; principal bloqueado entre os secundários via after()); GET /portal/cnaes (portal.cnaes.search) retorna só ativos, máx 20 (constante técnica MAX_RESULTS), shape id/formatted_code/description — contrato do picker da tela 03-08.

### Roadmap Evolution

- Phase 3.1 inserida após a Phase 3: Fundação assíncrona — scheduler, jobs e retenção (URGENT). Origem: levantamento "Laravel 13 — recursos prontos não usados" (2026-06-12). Escopo: scheduler ativo com primeira rotina real (padrão para HU-134/HU-147), importações REDESIM/CNAE como jobs em fila com retry e relatório, `throttle` parametrizado nas rotas públicas, pruning de access_logs com retenção parametrizada (LGPD; trilha de auditoria RN-002 fica fora) e retry/backoff no HTTP client. Demais recursos do levantamento anotados nas fases consumidoras como "Nota (recursos do framework)": Storage/URLs assinadas (Fases 8 e 10), atomic locks e eventos de domínio (Fases 8 e 9), canal database de Notifications (Fase 11), `Concurrency`/`Http::pool`/`Queue::route()`/Horizon (Fase 13).

### Pending Todos

Nenhum.

### Blockers/Concerns

- [Fase 3.2 / HU-151] Credenciamento no Login Único GOV.BR: a SEDUR ainda não possui client_id/client_secret (Termo de Adesão junto à Secretaria de Governo Digital). A autenticação GOV.BR do portal está IMPLEMENTADA e DESLIGADA (`features.govbr_login` default false); a validação contra `sso.staging.acesso.gov.br` real é critério de conclusão da integração — bloqueada até a credencial. Spec: `docs/superpowers/specs/2026-06-12-autenticacao-govbr-design.md`.
  - Implementação concluída em 2026-06-12 (commits 48194e6/a55369c): GovBrProvider Socialite (PKCE S256, nonce, token via Basic auth, id_token validado contra JWK com firebase/php-jwt), GovBrAuthService (vínculo por CPF, criação de conta cidadao, nível mínimo parametrizável, inativada/conflito de e-mail bloqueados), govbr_accounts 1:1, 6 parâmetros novos (credenciais sensíveis criptografadas), botão oficial nas telas de login/cadastro. 24 testes novos (7 unit JWK + 2 schema + 15 feature); suíte 317/317.
  - Evidência E2E real (browser, 2026-06-12): com credencial de exemplo, o clique no botão redirecionou ao staging real e o gov.br respondeu `invalid_client "cliente-exemplo-dev was not found"` — fluxo OAuth correto de ponta a ponta; falta apenas a credencial verdadeira. Toggle do dev restaurado para desligado.
  - DECISÕES da fase: níveis de confiabilidade lidos do `reliability_info` do id_token (API /confiabilidades é obsoleta no roteiro — parâmetro api_base_url removido); nível ausente = bronze; primeiro acesso sem e-mail verificado no gov.br é bloqueado com orientação (users.email é NOT NULL); e-mail do gov.br NUNCA sobrescreve o local nem vincula sozinho (CPF é a única chave); logout local apenas (sem Single Logout); seeder de parâmetros ganhou suporte a `sensitive` via forceFill (fora do fillable, decisão 02-02).
- [Produto] Painel consolidado do cidadão (padrão SIGVISA: situação atual ao logar — empresas, solicitações, DAMs, TVLs): NÃO há HU no catálogo (HU-122 é dashboard do GESTOR). Lacuna identificada em 2026-06-10; decisão pendente (criar HU-132 proposta ou evoluir o painel dentro das fases). Pauta para a SEDUR. As peças surgem nas Fases 3 (empresas), 7 (consultas), 8 (solicitações) e 9 (TVL) — o painel atual de atalhos evolui junto.
- [Identidade] Marca institucional oficial a confirmar: o usuário indicou a página de marcas da SEDUR **estadual** (https://www.ba.gov.br/sedur/institucional/marcas — manual Secom, marca Sedur, marca do Governo, brasão do Estado; arquivos em SharePoint com login, download direto bloqueado). A documentação do projeto referencia a SEDUR **municipal de Salvador** (LOUOS municipal, Decreto 32.636/2020, Prefeitura de Salvador). Confirmar qual ente é o dono do SILE antes de aplicar marca institucional/co-branding; até lá, o sistema usa a logo própria do SILE (components/app/logo.tsx).

Pendências com a SEDUR (pauta: docs/ANALISE-HUs-REUNIAO-SEDUR.md seção 5). Nenhuma bloqueia as Fases 1 a 3.

- [Fase 8] HU-071/HU-072 (DAM e pagamento): escopo a confirmar — não implementar antes da confirmação.
- [Fase 13] HU-110 (SEFAZ): API confirmada; endpoint de envio do deferimento e credenciais SenhaWeb pendentes.
- [Fase 13] HU-111 (migração do legado .NET): estratégia a definir.
- [Fases 3/13] Contrato REDESIM/integrador (entrada e devolução de parecer): aguardando documentação.
- [Fases 4/13] Base GIS municipal (camadas, formato, acesso): aguardando — Fase 4 opera com camadas carregadas de dados oficiais da LOUOS até a entrega.
- [Fase 4 — RESOLVIDO PARCIAL/BLOQUEADO, 2026-06-13] EP04 concluído com escopo honesto. ENTREGUE com dado público real do GeoSalvador (ArcGIS REST, f=geojson&outSR=4326): bairro (171, Dec. 38.776/2024), eixo viário (800, extrato central) e restrições ambientais ZEIS (234, PDDU 2016); geocodificação Nominatim real atrás de contrato. BLOQUEADO pendente SEDUR (sem fonte vetorial pública — comunicado na UI como "pendente SEDUR", nunca inventado): **zona urbanística LOUOS** (HU-031 — Quadro 10 só em PDF) e **lote cadastral** (HU-033 — Cadastro Multifinalitário/SEFAZ restrito); por consequência HU-037 RN-005 (divergência por inscrição imobiliária). A classificação viária LOUOS (Quadros 11/11A) entra como geometria, mas o ATRIBUTO operacional pende de confirmação SEDUR (PDDU 2016 ≟ Mapa 04 da LOUOS). IMPACTO NA FASE 5: o motor de enquadramento depende da zona urbanística — manter o bloqueio explícito; o motor nasce parametrizável e roda com seeds derivados da Lei 9.148/2016 até a base oficial chegar. A infraestrutura (camadas versionadas, TerritoryService, sobreposição polígono×lote) já está pronta e testada — quando a SEDUR entregar SIGIS/CA 2000, muda a carga, não a lógica.
- [Fase 5] Quadros parametrizados vigentes e correspondência "Quadro 11" ↔ 11B: a confirmar — motor nasce parametrizável.
- [Fase 13] Acesso a ambiente de homologação (integrador/SEFAZ/GIS): solicitado.

## Session Continuity

Last session: 2026-06-12 19:50 UTC
Stopped at: Completed 03-06-PLAN.md (Fase 3, wave 5 — vínculo de CNAEs da empresa HU-025/HU-026: CompanyCnaeService transacional único ponto de escrita do pivot, endpoints PUT cnae-principal/cnaes-secundarios via manageCnaes, busca GET /portal/cnaes só ativos; 17 testes do plano verdes, grupo Companies 82 verdes, pint limpo; MCP Laravel Boost indisponível na sessão — padrões do RESEARCH validados por teste)
Resume file: None

Nota operacional: durante o 01-09 houve uma sessão de agente concorrente no mesmo working directory (commits 79b3b81/e4010ce da Task 1 e composer run dev). Conteúdo validado e aproveitado sem duplicação. RECORRÊNCIA no 02-05: TRÊS sessões executoras despachadas para o mesmo plano; a segunda e a terceira detectaram a colisão no início (SUMMARY/commits já no HEAD), não editaram código e validaram o trabalho da primeira com evidência fresca (Users 15/15, suíte 168/168, typecheck/build/pint verdes, 3 rotas usuarios.*). Corrigir o despacho: um único executor por wave/plano — nunca sessões GSD simultâneas ou repetidas no mesmo plano sem checar SUMMARY antes.
