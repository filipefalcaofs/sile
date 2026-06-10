# Phase 2: Administração Base - Context

**Gathered:** 2026-06-10
**Status:** Ready for planning
**Source:** PRD Express Path (HUs do EP02 — `docs/SILE_HUs_Completas_MD/EP02-Administracao/`, HU-011 a HU-014)

<domain>
## Phase Boundary

Administradores mantêm os cadastros estruturantes (CNAEs com carga oficial IBGE/CONCLA, usuários, perfis com permissões granulares) e os parâmetros de negócio do sistema por interface — tipo, validação, valor padrão, histórico auditado, efeito sem deploy e feature toggles com degradação controlada. Tudo dentro do ambiente Gestão (`/gestao`), restrito ao papel administrador.

Fora do escopo desta fase: mantenedores de quadros da LOUOS, condicionantes e classificação de risco (HU-015 a HU-020 — Fases 5 e 6); cadastro empresarial (Fase 3); telas de configuração de integrações específicas (nascem com os adaptadores na Fase 13, usando o mecanismo de parâmetros desta fase).
</domain>

<decisions>
## Implementation Decisions

### HU-011 — Manter CNAEs
- CRUD administrativo da tabela de CNAEs com busca e paginação; consulta aberta a perfis internos conforme permissão, manutenção restrita ao administrador.
- **Seed oficial**: estrutura CNAE-Subclasses 2.3 do IBGE/CONCLA — `docs/dados-oficiais/CNAE_Subclasses_2_3_Estrutura_Detalhada.xlsx` (1.331 subclasses; a publicação oficial cita 1.332 — divergência de 1 a verificar e registrar na importação).
- Código normalizado para dígitos no banco; exibição formatada `DDDD-D/SS` (referência SIGVISA `Cnae`).
- Flag `ativo` para desativação lógica (CNAE desatualizado não pode ser usado em novas solicitações, mas histórico permanece íntegro).
- O modelo nasce pronto para receber as dimensões futuras sem retrabalho estrutural: grau de risco municipal (HU-020/Fase 6), condicionantes-pergunta (HU-019/Fase 6), risco sanitário (dimensão separada — decisão do projeto). Nesta fase NÃO se implementam essas dimensões; apenas não se bloqueia a evolução (ex.: não usar o código como PK natural se prejudicar FKs futuras).
- CAs padrão (CA-01 execução, CA-02 auditoria, CA-03 bloqueio por inconsistência, CA-04 segurança de acesso).

### HU-012 — Manter usuários
- Listagem/busca de usuários da gestão com ativação/inativação e vínculo de papel (perfil).
- Inativação impede login (degradação controlada com mensagem pt-BR) e é auditada; reativação idem.
- Administrador não pode inativar a própria conta (proteção contra lockout administrativo).
- **Fecha o concern da Fase 1**: a listagem liga a navegação para `gestao/acessos/{user}` (histórico de acessos de qualquer conta).

### HU-013 — Manter perfis
- CRUD de perfis (roles spatie) com atribuição granular de permissões por funcionalidade.
- Papéis estruturais do sistema (cidadao, analista, gestor, administrador) são protegidos: não podem ser excluídos nem renomeados (o código referencia seus nomes); suas permissões PODEM ser ajustadas, exceto remover `acessar-gestao` do administrador (proteção contra lockout).
- Perfis novos criados pelo administrador são totalmente editáveis/excluíveis (exclusão bloqueada se houver usuários vinculados — CA-03).

### HU-014 — Manter parâmetros do sistema (RN-004 a RN-011, CA-05 a CA-07)
- Registry de parâmetros administráveis por interface: cada parâmetro com tipo, descrição, valor padrão, regras de validação (faixa/formato/obrigatoriedade); valor inválido rejeitado na gravação (RN-007).
- **Backend do `Settings` da Fase 1 evolui**: passa a ler do banco com fallback para `config/sile.php` (valor padrão), com cache e invalidação na gravação — efeito sem deploy dentro do TTL de cache (RN-006, CA-05). Call sites existentes (`Settings::get`) não mudam — essa era a promessa da Fase 1.
- Feature toggles administráveis para funcionalidades acopláveis (RN-005); desativação degrada de forma controlada e comunicada, nunca falha silenciosa (RN-011, CA-06). Nesta fase nascem o mecanismo e os toggles já aplicáveis; toggles de integrações/IA/fluxo expresso são registrados quando as fases correspondentes chegarem.
- Parâmetros sensíveis (credenciais, tokens) armazenados criptografados (cast `encrypted`), nunca exibidos em claro após gravados — UI mostra mascarado (RN-009).
- Histórico de alterações consultável: valor anterior, valor novo, responsável, data/hora (RN-008, CA-07) — usar a trilha de auditoria transversal da Fase 1.
- Ação de teste de conexão (RN-010) é mecanismo previsto na estrutura (campo/contrato), mas só ganha implementações reais quando houver integrações (Fase 13) — sem teste falso.
- Os 4 CAs padrão + CA-05 (efeito sem deploy), CA-06 (desativação controlada), CA-07 (histórico).

### Permissões novas da fase
- Permissões granulares por funcionalidade administrativa (ex.: manter-cnaes, manter-usuarios, manter-perfis, manter-parametros) atribuídas ao papel administrador no seeder — padrão da Fase 1 (`RolesAndPermissionsSeeder` idempotente).
- Rotas sob `/gestao` com middleware de permissão específica por recurso (não apenas `acessar-gestao`) — CA-04 com 403 auditado (mecanismo da Fase 1 já cobre).

### Regras do projeto aplicáveis (travadas)
- TDD estrito: cada CA vira feature test PHPUnit nomeado em pt-BR antes do código de produção.
- UI pt-BR; código em inglês; conventional commits pt-BR.
- Nenhum valor de negócio hardcoded — a própria fase entrega o mecanismo definitivo.
- Laravel way: form requests, policies/middleware de permissão, Eloquent, factories.
- `vendor/bin/pint --dirty --format agent` após alterar PHP.
- Sem features de fachada: import oficial processa o arquivo real; histórico exibe dados reais da trilha.

### Claude's Discretion
- Importação do xlsx oficial: converter para CSV versionado + seeder/command de import (com normalização e relatório de divergência), ou leitura direta de xlsx via pacote (maatwebsite/excel é aplicável por decisão do projeto) — decidir na pesquisa considerando reprodutibilidade do seed e a divergência 1.331 × 1.332.
- Modelagem da tabela `cnaes`: colunas de hierarquia (seção/divisão/grupo/classe) desnormalizadas vs apenas subclasse; PK surrogate vs código.
- Modelagem do registry de parâmetros (tabela única `settings` com type/value/default/rules/sensitive/group vs tabelas por domínio) — observar exemplos do SIGVISA (configs por domínio) e a necessidade do SILE de registry genérico + grupos.
- Estratégia de cache do Settings (TTL parametrizado, invalidação por chave na gravação).
- Componentes de UI das telas administrativas (tabela, busca, formulários) seguindo os padrões Inertia/React/Tailwind da Fase 1.
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requisitos da fase (fonte de verdade)
- `docs/SILE_HUs_Completas_MD/EP02-Administracao/HU-011-Manter-CNAEs.md` — CNAEs (observações citam seed oficial e modelo SIGVISA)
- `docs/SILE_HUs_Completas_MD/EP02-Administracao/HU-012-Manter-usuarios.md` — usuários
- `docs/SILE_HUs_Completas_MD/EP02-Administracao/HU-013-Manter-perfis.md` — perfis
- `docs/SILE_HUs_Completas_MD/EP02-Administracao/HU-014-Manter-parâmetros-do-sistema.md` — parâmetros (RN-004 a RN-011, CA-05 a CA-07, domínios parametrizáveis)

### Dados oficiais
- `docs/dados-oficiais/CNAE_Subclasses_2_3_Estrutura_Detalhada.xlsx` — estrutura oficial IBGE/CONCLA para o seed
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` (seções 4A e 4C) — volumetria CNAE, normalizações necessárias, risco municipal × sanitário

### Código existente a respeitar (Fase 1)
- `app/Support/Settings.php` — wrapper atual (lê config/sile.php); a fase troca o backend SEM tocar call sites
- `config/sile.php` — parâmetros atuais (security, ui) que viram valores padrão do registry
- `app/Services/AuditService.php` + `app/Actions/RecordActivityAction.php` — auditoria transversal (assinaturas travadas)
- `database/seeders/RolesAndPermissionsSeeder.php` — padrão de seed idempotente de papéis/permissões
- `routes/gestao.php` + `resources/js/layouts/gestao-layout.tsx` — ambiente Gestão onde as telas entram
- `resources/js/pages/gestao/acessos.tsx` — tela órfã de navegação que a HU-012 conecta
- `.planning/phases/01-identidade-acesso-e-auditoria-transversal/01-VERIFICATION.md` — estado verificado da Fase 1

### Contexto do projeto
- `.planning/PROJECT.md`, `.planning/ROADMAP.md` (Fase 2), `.planning/STATE.md` (decisões acumuladas [01-xx])
- `AGENTS.md` — convenções do repositório

</canonical_refs>

<specifics>
## Specific Ideas

- A tela de usuários (HU-012) deve linkar o histórico de acessos por usuário (`gestao.acessos.show`) — fecha o blocker registrado no STATE.md.
- O import de CNAEs deve produzir relatório verificável (contagem importada × esperada; divergência 1.331/1.332 registrada) — padrão de import com contadores do SIGVISA (`CsvImport`).
- Settings com banco + cache: a chave `security.login.max_attempts` (usada no rebinding do LoginRateLimiter da Fase 1) precisa continuar funcionando — testes da Fase 1 não podem quebrar.
- Auditoria de parâmetro sensível: o histórico NUNCA grava o valor em claro (nem anterior nem novo) — gravar marcador (ex.: "[criptografado]").
- Inativação de usuário: campo dedicado (ex.: `inactivated_at`) em vez de excluir; listener/gate no login com mensagem pt-BR clara.
</specifics>

<deferred>
## Deferred Ideas

- Mantenedores de quadros LOUOS, condicionantes e classificação de risco (HU-015 a HU-020) — Fases 5 e 6.
- Dimensões de risco no CNAE (municipal, sanitário, condicionante-pergunta) — Fase 6; aqui só não se bloqueia a evolução.
- Telas de configuração de integrações com teste de conexão real (SEFAZ, REDESIM, GIS) — Fase 13, sobre o mecanismo desta fase.
- Toggles de IA, fluxo expresso e canais de notificação — registrados quando as fases correspondentes chegarem (9, 11, 14).
- Convite/criação de usuário interno por e-mail — não consta nas HUs; cadastro continua pelo fluxo público (HU-001) + vínculo de papel pelo admin (HU-012).
</deferred>

---

*Phase: 02-administracao-base*
*Context gathered: 2026-06-10 via PRD Express Path*
