# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-09)

**Core value:** Responder a viabilidade locacional de atividade econômica de forma automática, correta e auditável — fluxo expresso quando a lei permite, fundamentação legal em toda decisão.
**Current focus:** Fase 2 — Administração Base (em execução)

## Current Position

Phase: 2 of 15 — Administração Base (HU-011 a HU-014)
Plan: 5 of 8 completos (02-01, 02-02, 02-03, 02-04, 02-05)
Status: In progress — wave 3 completa (02-05 concluído; suíte 168/168 verde no fechamento)
Last activity: 2026-06-10 — Completed 02-05-PLAN.md (HU-012: gestão de usuários com inativação real, papel auditado e link para acessos)

Progress: [█░░░░░░░░░] 11% (1/15 fases; fase 2: 5/8 planos)

Next step: orquestrador despacha a wave 4 (02-06 — HU-013 perfis; 02-07 — HU-014 tela de parâmetros)

## Performance Metrics

**Velocity:**
- Total plans completed: 13
- Average duration: 12 min
- Total execution time: 2.57 h

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-identidade | 9/9 ✓ | ~96 min | 11 min |
| 02-administracao-base | 5/8 | ~66 min | 13 min |

**Recent Trend:**
- Last 5 plans: 02-01 (9 min), 02-03 (10 min), 02-02 (14 min), 02-04 (22 min), 02-05 (11 min)
- Trend: estável (02-04 maior por cobrir import + CRUD + tela em 3 tasks)

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

### Pending Todos

Nenhum.

### Blockers/Concerns

Pendências com a SEDUR (pauta: docs/ANALISE-HUs-REUNIAO-SEDUR.md seção 5). Nenhuma bloqueia as Fases 1 a 3.

- [Fase 8] HU-071/HU-072 (DAM e pagamento): escopo a confirmar — não implementar antes da confirmação.
- [Fase 13] HU-110 (SEFAZ): API confirmada; endpoint de envio do deferimento e credenciais SenhaWeb pendentes.
- [Fase 13] HU-111 (migração do legado .NET): estratégia a definir.
- [Fases 3/13] Contrato REDESIM/integrador (entrada e devolução de parecer): aguardando documentação.
- [Fases 4/13] Base GIS municipal (camadas, formato, acesso): aguardando — Fase 4 opera com camadas carregadas de dados oficiais da LOUOS até a entrega.
- [Fase 5] Quadros parametrizados vigentes e correspondência "Quadro 11" ↔ 11B: a confirmar — motor nasce parametrizável.
- [Fase 13] Acesso a ambiente de homologação (integrador/SEFAZ/GIS): solicitado.

## Session Continuity

Last session: 2026-06-10 15:17 UTC
Stopped at: Completed 02-05-PLAN.md (Fase 2, wave 3 completa — suíte 168/168, typecheck/build verdes; concern da tela gestao/acessos resolvido pela navegação da HU-012)
Resume file: None

Nota operacional: durante o 01-09 houve uma sessão de agente concorrente no mesmo working directory (commits 79b3b81/e4010ce da Task 1 e composer run dev). Conteúdo validado e aproveitado sem duplicação. RECORRÊNCIA no 02-05: TRÊS sessões executoras despachadas para o mesmo plano; a segunda e a terceira detectaram a colisão no início (SUMMARY/commits já no HEAD), não editaram código e validaram o trabalho da primeira com evidência fresca (Users 15/15, suíte 168/168, typecheck/build/pint verdes, 3 rotas usuarios.*). Corrigir o despacho: um único executor por wave/plano — nunca sessões GSD simultâneas ou repetidas no mesmo plano sem checar SUMMARY antes.
