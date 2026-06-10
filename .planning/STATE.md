# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-09)

**Core value:** Responder a viabilidade locacional de atividade econômica de forma automática, correta e auditável — fluxo expresso quando a lei permite, fundamentação legal em toda decisão.
**Current focus:** Fase 2 — Administração Base (próxima; Fase 1 concluída)

## Current Position

Phase: 1 of 15 — CONCLUÍDA (Identidade, Acesso e Auditoria Transversal)
Plan: 9 of 9 completos
Status: Phase 1 complete — verificação passed (47/47 must-haves); smoke E2E aprovado pelo usuário
Last activity: 2026-06-10 — Fase 1 fechada: 01-09 concluído (seeds + verificação integral + checkpoint humano aprovado); VERIFICATION.md passed; HU-001 a HU-010 Complete na traceability

Progress: [█░░░░░░░░░] 7% (1/15 fases; fase 1: 9/9 planos)

Next step: `/gsd-plan-phase 2` (Administração Base — HU-011 a HU-014)

## Performance Metrics

**Velocity:**
- Total plans completed: 8
- Average duration: 11 min
- Total execution time: 1.47 h

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-identidade | 9/9 ✓ | ~96 min | 11 min |

**Recent Trend:**
- Last 5 plans: 01-04 (13 min), 01-05 (8 min), 01-06 (13 min), 01-07 (13 min), 01-08 (10 min)
- Trend: estável

*Atualizado após cada plano concluído*

## Accumulated Context

### Decisions

Registro completo na tabela Key Decisions de PROJECT.md. Mais relevantes para o trabalho atual:

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

### Pending Todos

Nenhum.

### Blockers/Concerns

- [Fase 2] Tela gestao/acessos alcançável apenas por URL direta (/gestao/acessos/{user}) — a listagem/busca de usuários da gestão é a HU-012 (Fase 2), que fechará essa navegação.

Pendências com a SEDUR (pauta: docs/ANALISE-HUs-REUNIAO-SEDUR.md seção 5). Nenhuma bloqueia as Fases 1 a 3.

- [Fase 8] HU-071/HU-072 (DAM e pagamento): escopo a confirmar — não implementar antes da confirmação.
- [Fase 13] HU-110 (SEFAZ): API confirmada; endpoint de envio do deferimento e credenciais SenhaWeb pendentes.
- [Fase 13] HU-111 (migração do legado .NET): estratégia a definir.
- [Fases 3/13] Contrato REDESIM/integrador (entrada e devolução de parecer): aguardando documentação.
- [Fases 4/13] Base GIS municipal (camadas, formato, acesso): aguardando — Fase 4 opera com camadas carregadas de dados oficiais da LOUOS até a entrega.
- [Fase 5] Quadros parametrizados vigentes e correspondência "Quadro 11" ↔ 11B: a confirmar — motor nasce parametrizável.
- [Fase 13] Acesso a ambiente de homologação (integrador/SEFAZ/GIS): solicitado.

## Session Continuity

Last session: 2026-06-10 03:50 UTC
Stopped at: Fase 1 concluída e verificada (passed). Suíte 122/122 (477 asserções), pint/typecheck/build verdes, smoke E2E aprovado. Próximo: `/gsd-plan-phase 2` (Administração Base — HU-011 a HU-014: CNAEs com seed IBGE/CONCLA, manter usuários, perfis e parâmetros do sistema com UI administrável).
Resume file: None

Nota operacional: durante o 01-09 houve uma sessão de agente concorrente no mesmo working directory (commits 79b3b81/e4010ce da Task 1 e composer run dev). Conteúdo validado e aproveitado sem duplicação. Evitar duas sessões GSD simultâneas no mesmo repositório.
