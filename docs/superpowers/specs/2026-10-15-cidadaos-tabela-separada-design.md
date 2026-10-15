# Cidadão em tabela própria, separado do servidor da retaguarda

Data: 2026-10-15
Base: `softwell/main` @ `dede710f` (VERSION 0.77.2)
Branch: `feat/cidadaos-tabela-separada`

## Problema

Portal do cidadão e retaguarda da SEDUR guardam as contas na mesma tabela `users`.
`config/auth.php` define os guards `web` e `gestao` com o mesmo provider; só a sessão
é separada. O cadastro do portal valida `unique:users,cpf` e `unique:users` no e-mail
(`app/Actions/Fortify/CreateNewUser.php`). Um servidor da SEDUR não consegue criar
conta de cidadão com o próprio CPF: o cadastro responde "O valor indicado para o campo
CPF já se encontra registrado". O mesmo vale para e-mail e para a recuperação de senha.

Regra de negócio: conta de cidadão e conta de servidor são identidades diferentes, com
autenticação diferente. Nenhuma pode se misturar com a outra.

## Objetivo

- O cidadão passa a ter tabela, model, guard, provider e broker de senha próprios.
- `users` passa a conter só servidores.
- CPF e e-mail são únicos dentro de cada tabela, não entre elas.
- Uma mesma pessoa pode ter as duas contas, sem uma enxergar a outra.

Critérios de sucesso:

1. Um CPF e um e-mail já usados por um servidor podem cadastrar uma conta de cidadão.
2. Login em `/portal/login` só autentica `cidadaos`; `/gestao/login` só autentica `users`.
3. Recuperação de senha de cada ambiente só enxerga as contas do seu ambiente.
4. Todo dado de cidadão existente (empresas, procurações, solicitações, consultas,
   notificações, aceites) continua ligado à mesma pessoa depois da migração.
5. A suíte de testes passa, e a migração tem teste de ida e de volta.

## Fora de escopo

- Login Único gov.br continua com o mesmo fluxo; só muda a tabela de destino.
- Nenhuma mudança de tela, texto ou regra de negócio visível ao cidadão.
- Perfis e permissões dos servidores (Spatie) não mudam.

## Decisões

### 1. Tabela `cidadaos` e model `Cidadao`

Colunas: `id`, `name`, `cpf` (único), `email` (único), `phone`, `password` (nullable),
`email_verified_at`, `remember_token`, as colunas de dois fatores que `users` já tem,
`inactivated_at`, `created_at`, `updated_at`.

`password` é nullable porque o servidor cria o cidadão no atendimento presencial e na
contingência sem senha. Quando a pessoa cria a conta no portal com o mesmo CPF, o
cadastro **assume** o registro existente: define senha, valida o e-mail e mantém o
histórico. Cadastro com CPF que já tem senha continua recusado.

`Cidadao` usa `Notifiable`, `CausesActivity` e `HasAuditoria`, como `User` hoje. Não usa
`HasRoles`: o tipo da conta é a própria tabela, e o papel `cidadao` deixa de existir.

### 2. Guards, providers e brokers

| Guard | Provider | Model | Broker de senha |
|---|---|---|---|
| `web` (portal) | `cidadaos` | `Cidadao` | `cidadaos` |
| `gestao` | `users` | `User` | `users` |

Fortify do portal usa `CreateNewCidadao`, `UpdateCidadaoPassword`, `ResetCidadaoPassword`
e `UpdateCidadaoProfileInformation`, todos operando em `cidadaos`. O `CreateNewUser`
atual deixa de servir o portal.

### 3. Chaves que apontavam para `users`

Cada coluna é classificada por quem pode escrevê-la no código. A classificação abaixo vem
dos nomes e das migrations e é confirmada, coluna a coluna, na primeira tarefa do plano.

**Só cidadão** — passam a apontar para `cidadaos`, e a coluna é renomeada de `*_user_id`
para `*_cidadao_id` para não repetir a ambiguidade que causou o defeito:

- `company_user.user_id`
- `gov_br_accounts.user_id`
- `procurations.grantor_user_id` e `procurations.attorney_user_id`
- `viability_requests.requester_user_id`
- `assisted_attendances.citizen_user_id`
- `viability_queries.user_id` (nullable: consulta anônima)
- `service_declaration_acceptances.user_id`
- `activity_log.acting_for_user_id`

**Só servidor** — ficam como estão, apontando para `users`:

`viability_requests.assigned_user_id`, `analysis_records.analyst_user_id`,
`viability_decisions.decided_by_user_id`, `inspections.vistoriador_user_id`,
`assisted_attendances.attendant_user_id`, `tvl_documents.generated_by_user_id`,
`indeferimento_documents.generated_by_user_id`, `fine_mesh_referrals.*`,
`inspection_referrals.*`, `analysis_pendencies.*`, `analysis_status_transitions.actor_user_id`,
`access_requests.decided_by_user_id`, `sector_user`, `user_sector_assignments`,
`rule_versions.*`, `parameter_proposals.*`, `ai_suggestions.created_by_user_id`,
`abuse_alerts.resolved_by_user_id`, `predictive_anomalies.resolved_by_user_id`,
`export_files.user_id`.

**Mistas** — quem escreve pode ser cidadão ou servidor. Viram par polimórfico
`*_type` + `*_id` (sem chave estrangeira; integridade pelo model):

- `viability_requests.created_by_user_id` e `cancelled_by_user_id`
- `viability_request_documents.uploaded_by_user_id`
- `viability_request_transitions.actor_user_id`
- `access_logs.user_id`
- `legal_term_acceptances.user_id`
- `communications.recipient_user_id`
- `procurations.revoked_by_user_id`

O mapa morph usa os apelidos `cidadao` e `user` (`Relation::enforceMorphMap`), para o
banco não guardar nome de classe.

### 4. Notificações e auditoria

`notifications.notifiable_type` e `activity_log.causer_type` já são polimórficos. A
migração reescreve o tipo das linhas que pertencem a cidadãos. O `AuditService` e o
`HandleInertiaRequests` passam a resolver a identidade pelo guard do ambiente, que já
existe (`$request->is('gestao*') ? 'gestao' : 'web'`).

### 5. Migração de dados

Uma migration, em transação, na ordem:

1. Cria `cidadaos`.
2. Copia de `users` para `cidadaos` as contas que **têm o papel `cidadao` e nenhum papel
   de servidor** e não têm `ad_username`. Guarda o par `users.id → cidadaos.id` numa
   tabela temporária de mapeamento.
3. Reaponta as colunas "só cidadão" e reescreve as "mistas" usando o mapeamento.
4. Reescreve `notifications` e `activity_log` dos cidadãos.
5. Remove de `users` as linhas migradas e o papel `cidadao` do catálogo de papéis.

Conta que tem papel de servidor **e** histórico de cidadão fica em `users` e ganha uma
linha em `cidadaos` ligada por CPF, para o histórico continuar acessível no portal. A
migração tem `down()` que reverte pelo mapeamento. Ela roda antes numa cópia do banco de
homologação, com contagem de linhas por tabela antes e depois.

### 6. Código, por camada

1. Auth: `config/auth.php`, `FortifyServiceProvider`, ações do Fortify, brokers.
2. Models e factories: `Cidadao`, `CidadaoFactory`; relações de `Company`,
   `Procuration`, `ViabilityRequest`, `AssistedAttendance`, `ViabilityQuery`, etc.
3. Policies e `ResolveRepresentation` / `CurrentRepresentation` do portal.
4. Controllers e requests do portal (`app/Http/Controllers/Portal/*`).
5. O que a gestão exibe do requerente (`ProcessoResource`, ficha de análise, contingência
   e atendimento presencial, que passam a criar `Cidadao`).
6. Seeders: demonstração e desenvolvimento passam a criar cidadãos em `cidadaos`.

## Testes

- Migração: dados de antes e depois, ida e volta, conta mista, conta sem senha.
- Auth: CPF e e-mail de servidor cadastram cidadão; login e reset de senha isolados por
  ambiente; assumir registro criado no balcão.
- Os testes do portal trocam `User::factory()->cidadao()` por `Cidadao::factory()`.
- A suíte completa roda antes de qualquer publicação.

## Rollout e risco

- A migração toca dados reais na homologação e na produção do SEMIT. Publicar primeiro em
  `homologa`, validar com contagens e login real, e só então em `main`.
- Fazer em janela de baixo uso: durante a migração, o portal fica em manutenção. O
  interruptor `SILE_PORTAL_SOLICITACAO` não cobre o login; usar o modo manutenção do
  Laravel.
- Trabalho feito em worktree limpa a partir de `softwell/main`, separada do renome
  `sile` → `viabiliza` que está sem commit na árvore principal. Quando esse renome for
  integrado, esta branch recebe o merge.

## Questões que o plano resolve com evidência

- Classificação final de cada coluna dos três grupos (leitura do código que escreve nela).
- Se `revoked_by_user_id` das procurações é sempre o outorgante. Até a confirmação, a
  coluna fica no grupo das mistas; sendo sempre o outorgante, passa a "só cidadão".
- Se existem contas hoje em `users` com papel de servidor e papel `cidadao` ao mesmo
  tempo, e quantas são.
