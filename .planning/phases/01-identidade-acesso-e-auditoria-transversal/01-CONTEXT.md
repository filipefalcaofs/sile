# Phase 1: Identidade, Acesso e Auditoria Transversal - Context

**Gathered:** 2026-06-09
**Status:** Ready for planning
**Source:** PRD Express Path (HUs do EP01 — `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/`)

<domain>
## Phase Boundary

Usuários (cidadão, contador, procurador, analista SEDUR, gestor SEDUR, administrador) acessam o SILE com segurança: cadastro com confirmação de e-mail, autenticação, recuperação e alteração de senha, aceite de termo LGPD, gestão do próprio perfil, vínculo e revogação de procurador, e consulta do histórico de acessos. Toda ação relevante registra trilha de auditoria por um mecanismo transversal reutilizável (RN-002) que servirá todas as 15 fases.

Fora do escopo desta fase: CRUD administrativo de usuários/perfis/parâmetros (HU-012, HU-013, HU-014 — Fase 2), cadastro empresarial (Fase 3), consulta/exportação da auditoria via UI administrativa (HU-100/HU-101 — Fase 12; aqui nasce apenas o registro e a consulta do próprio histórico de acessos).
</domain>

<decisions>
## Implementation Decisions

### HU-001 — Cadastrar usuário
- Cidadão, contador, procurador ou servidor cria conta de acesso ao SILE.
- Validar dados obrigatórios antes de avançar (RN-001); informar campos pendentes e permitir nova tentativa (FA-01).
- CAs: execução com sucesso, auditoria obrigatória, bloqueio por inconsistência, segurança de acesso.

### HU-002 — Autenticar usuário
- Login seguro para os dois ambientes: SILE Cidadão (portal) e SILE Gestão (retaguarda SEDUR).
- Acesso a funcionalidades conforme perfil (RN-003); tentativa sem permissão é bloqueada e registrada (FA-04, CA-04).

### HU-003 — Recuperar senha
- Recuperação segura de acesso (link por e-mail).

### HU-004 — Alterar senha
- Usuário autenticado altera a própria senha.

### HU-005 — Confirmar e-mail
- E-mail do cadastro é validado antes de comunicação oficial; cadastro sem confirmação tem acesso restrito.

### HU-006 — Aceitar termo LGPD
- Consentimento registrado (quem, quando, qual versão do termo); exigido no primeiro acesso.
- Texto do termo não pode ser hardcoded — administrável (preparar para HU-014).

### HU-007 — Gerenciar perfil do usuário
- Consulta e atualização de dados pessoais básicos pelo próprio usuário.

### HU-008 — Vincular procurador
- Representante legal autoriza procurador a acompanhar e protocolar solicitações em seu nome.
- Ações do procurador ficam identificadas como "em nome de" na auditoria.

### HU-009 — Revogar procuração
- Representante legal interrompe o acesso do procurador; revogação registrada e efeito imediato.

### HU-010 — Consultar histórico de acessos
- Usuário consulta os próprios acessos; administrador consulta acessos de qualquer conta.
- Logins, logouts e tentativas falhas são registrados (data/hora, origem/IP).

### Auditoria transversal (RN-002 de todas as HUs — infraestrutura desta fase)
- Mecanismo único e reutilizável que registra: usuário (ou serviço), data/hora, origem, ação executada, dados de entrada, resultado, erros/exceções e versão de regras (quando aplicável).
- Reutilizável por traits/observers nos models e chamável explicitamente em serviços (referência: SIGVISA usa `HasAuditoria` + spatie/laravel-activitylog).
- Tentativas bloqueadas por falta de permissão também geram registro (CA-04).

### Perfis e permissões
- Quatro grupos de atores: Cidadão/Contador/Procurador (acesso às próprias empresas e solicitações), Analista SEDUR, Gestor SEDUR, Administrador.
- Permissões granulares por funcionalidade (referência SIGVISA: spatie/laravel-permission).

### Regras do projeto aplicáveis (travadas)
- TDD estrito: cada CA BDD vira feature test PHPUnit; nenhum código de produção sem teste falhando antes.
- Sem features de fachada: tudo funciona de ponta a ponta de verdade.
- UI, mensagens e textos em pt-BR; código em inglês.
- Nenhum valor de negócio hardcoded (ex.: política de senha, tentativas de login, validade de link de recuperação, texto do termo LGPD) — usar config/parâmetros com defaults sensatos, prontos para administração via HU-014 (Fase 2).
- Laravel way: `php artisan make:`, form requests, policies, factories, Eloquent.
- `vendor/bin/pint --dirty --format agent` após alterar PHP.

### Claude's Discretion
- Escolha do mecanismo de autenticação (starter kit oficial Laravel/Fortify vs implementação própria com guards de sessão Inertia) — decidir na pesquisa considerando Laravel 13 + Inertia v3 + React 19.
- Modelagem de procuração (tabela própria com vigência/escopo) e do histórico de acessos (tabela dedicada vs evento de auditoria filtrado).
- Estrutura de rotas segregadas portal × gestão (referência SIGVISA: `portal.php`/`visa.php` + middleware SecurityHeaders).
- Layout/identidade visual das telas (não há UI-SPEC; telas de auth são convencionais — seguir padrões do ecossistema Inertia/React e Tailwind 4, textos pt-BR).
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requisitos da fase (fonte de verdade)
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-001-Cadastrar-usuario.md` — cadastro
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-002-Autenticar-usuario.md` — autenticação (SILE Cidadão × SILE Gestão)
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-003-Recuperar-senha.md` — recuperação de senha
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-004-Alterar-senha.md` — alteração de senha
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-005-Confirmar-e-mail.md` — confirmação de e-mail
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-006-Aceitar-termo-LGPD.md` — termo LGPD
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-007-Gerenciar-perfil-do-usuario.md` — perfil
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-008-Vincular-procurador.md` — procuração (vincular)
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-009-Revogar-procuracao.md` — procuração (revogar)
- `docs/SILE_HUs_Completas_MD/EP01-Identidade-Acesso-e-Seguranca/HU-010-Consultar-historico-de-acessos.md` — histórico de acessos

### Contexto do projeto
- `.planning/PROJECT.md` — restrições e decisões-chave (auditoria transversal, parametrização, sem fachada)
- `.planning/ROADMAP.md` — Fase 1: goal, critérios de sucesso, critério de pronto transversal
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` (seção 4B) — módulos do SIGVISA reaproveitáveis: auditoria (spatie/activitylog + trait), permissões (spatie/permission), rotas segregadas, SecurityHeaders
- `AGENTS.md` — convenções Laravel/Inertia/React do repositório

</canonical_refs>

<specifics>
## Specific Ideas

- Auditoria deve capturar também a "versão de regras" — na Fase 1 não há motor de regras, mas o campo nasce no schema para as fases 5/6 (nullable).
- Procurador atuando "em nome de" precisa aparecer na auditoria desde já (HU-008/HU-009 dependem disso para rastreabilidade).
- Dois contextos de acesso (Cidadão × Gestão) com navegação e permissões distintas — base para todas as fases de retaguarda (10, 11, 12).
- Histórico de acessos (HU-010) inclui tentativas falhas de login — o listener de auth registra sucesso e falha.
</specifics>

<deferred>
## Deferred Ideas

- CRUD administrativo de usuários e perfis (HU-012/HU-013) — Fase 2.
- Parâmetros administráveis por interface (HU-014) — Fase 2; nesta fase os valores ficam em config/parâmetros com default, sem UI de administração.
- Consulta e exportação da trilha de auditoria completa (HU-100/HU-101) e LGPD operacional (HU-102) — Fase 12.
- Login gov.br/SSO — não consta nas HUs; não implementar.
</deferred>

---

*Phase: 01-identidade-acesso-e-auditoria-transversal*
*Context gathered: 2026-06-09 via PRD Express Path*
