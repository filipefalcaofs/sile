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
5. A suíte de testes passa, e a migração tem teste com dados do schema antigo. A volta é
   pelo backup do banco (`down()` sem efeito).

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
- `procurations.grantor_user_id`, `procurations.attorney_user_id` e
  `procurations.revoked_by_user_id` (só `ProcurationController::destroy` grava, com o
  usuário do guard `web`)
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

O `*_type` guarda o nome completo da classe (`App\Models\Cidadao` ou `App\Models\User`),
como `notifications` e `activity_log` já fazem. Não se usa `enforceMorphMap`: a tabela
`model_has_roles` do Spatie, as notificações e o log de atividade já guardam
`App\Models\User`, e apelidos quebrariam essas linhas.

### 4. Notificações e auditoria

`notifications.notifiable_type` e `activity_log.causer_type` já são polimórficos. A
migração reescreve o tipo das linhas que pertencem a cidadãos. O `AuditService` e o
`HandleInertiaRequests` passam a resolver a identidade pelo guard do ambiente, que já
existe (`$request->is('gestao*') ? 'gestao' : 'web'`).

### 5. Migração de dados

Migrations `2026_10_16_000050` a `2026_10_16_000910`, na ordem:

1. **Pré-voo (000050).** Antes de qualquer mudança, confere toda chave estrangeira para
   `users` fora das colunas que a separação converte (lista derivada de
   `Schema::getForeignKeys`, só no schema corrente). Se alguma citar uma conta de cidadão,
   a migração para com a lista `tabela.coluna (linhas)` e nada é alterado.
2. **Critério de conta de cidadão.** Não é AD, não tem `acessar-gestao` por perfil nem como
   permissão direta e não tem perfil além dos de cidadão (`cidadao` e o perfil do parâmetro
   `portal.perfil_cidadao`, enquanto existir). Conta sem perfil é cidadã. Conta ambígua
   (perfil herdado sem permissão de gestão, ou `acessar-gestao` só direta) é servidor: fica
   em `users`, não é copiada nem apagada.
3. **Cópia (000300).** As contas de cidadão vão para `cidadaos` com o mesmo `id`. A
   sequência de `cidadaos.id` passa a ficar acima dos ids das duas tabelas.
4. **Colunas mistas (000350)** viram `*_type` + `*_id`; o tipo vem do critério acima.
5. **Colunas só de cidadão (000400 a 000420)** trocam o alvo da chave para `cidadaos`.
   - Colunas que aceitam nulo (`viability_queries.user_id`, `procurations.revoked_by_user_id`)
     têm as referências a servidores anuladas, sem espelho.
   - Procurações ainda vigentes em que o outorgante ou o procurador é servidor são revogadas
     (`revoked_at` = agora, sem autor).
   - Solicitações de origem `regin` cujo requerente não é conta de cidadão passam para o
     registro técnico do REGIN (`Cidadao::CPF_TECNICO`, criado se não existir, sem senha).
     O REGIN antigo gravava o primeiro servidor como requerente de todo recebimento sem
     solicitante; sem isso o espelho desse servidor herdaria todo o histórico REGIN.
   - Nas demais colunas obrigatórias, servidor citado ganha espelho em `cidadaos` com o mesmo
     `id` e sem senha (ex.: solicitação que o próprio servidor fez no portal).
6. **Notificações e auditoria (000450).** `notifications.notifiable_type`,
   `activity_log.causer_type`/`subject_type` e `abuse_alerts.subject_type` das contas
   copiadas passam a `App\Models\Cidadao`.
7. **Remoção (000900).** Repete a checagem de conflito sobre as contas copiadas (id e CPF
   iguais nas duas tabelas e senha em `cidadaos`) e só então remove de `users` essas contas,
   seus papéis e permissões diretas, os tokens de `password_reset_tokens` dos seus e-mails e o
   papel `cidadao`. Por último, os solicitantes criados pelo REGIN antigo (e-mail
   `@solicitante.viabiliza.local`, senha aleatória) ficam sem senha.
8. **Parâmetro (000910).** Remove `portal.perfil_cidadao` do catálogo.

Depois da migração:

- O provider `cidadaos` só autentica cidadão com senha. Registro sem senha (balcão, espelho de
  servidor, solicitante do REGIN antigo) é assumido quando a pessoa se cadastra no portal com
  o mesmo CPF ou entra pelo Gov.br. Pelo Gov.br, o registro ganha senha aleatória, o e-mail
  verificado do Gov.br quando não pertence a outro cidadão, e o e-mail passa a verificado.
- O registro técnico do REGIN (CPF `00000000000`) não é conta: não aparece na gestão e não
  pode ser assumido (o CPF não passa na validação).
- `down()` de todas as migrations da separação é no-op. A reversão é a restauração do
  backup do banco feito antes da publicação.
- O ensaio da migração foi feito em SQLite com dados do schema antigo. Não ficaram provados
  nomes de constraint do PostgreSQL, `setval`, locks e tempo com volume real: ver Rollout.

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

- Migração: dados de antes e depois, conta mista, conta ambígua, conta sem senha, REGIN.
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
- No OKD as imagens web, agendador e filas rodam `migrate` ao subir, e o modo de manutenção
  `file` não vale entre pods: escalar os três a zero, rodar a migração uma vez, conferir as
  contagens e só então subir de novo.
- Trabalho feito em worktree limpa a partir de `softwell/main`, separada do renome
  `sile` → `viabiliza` que está sem commit na árvore principal. Quando esse renome for
  integrado, esta branch recebe o merge.

## Questões que o plano resolve com evidência

- Classificação final de cada coluna dos três grupos (leitura do código que escreve nela).
- (Resolvida na escrita do plano: `revoked_by_user_id` é só de cidadão.)
- Se existem contas hoje em `users` com papel de servidor e papel `cidadao` ao mesmo
  tempo, e quantas são.

## Decisões a ratificar

- **Assumir registro por CPF.** Registro sem senha (balcão ou espelho de servidor) é assumido
  por quem se cadastra no portal com o mesmo CPF, sem outra prova de identidade. Recomendação:
  manter; exigir Gov.br bloquearia a assunção quando o login Gov.br estiver desligado.
- **Espelhos de servidor assumíveis.** O servidor que já tinha pedido algo pelo portal ganha
  espelho sem senha; quem cadastrar esse CPF assume o espelho e o histórico dele (pouco,
  depois que as solicitações REGIN foram para o registro técnico).
- **Atendimento e contingência criam registro com o nome da SEDUR.** O formulário não tem
  campo de nome: CPF novo depende da consulta à SEDUR; sem a SEDUR, o atendimento é
  bloqueado sem criar registro.
- **Registro técnico compartilhado do REGIN.** Toda solicitação REGIN sem solicitante
  identificado fica no mesmo registro técnico (CPF `00000000000`), em vez de num servidor.
- **Ator das transições REGIN.** Protocolar e decidir no REGIN continuam registrados em nome
  do primeiro servidor (`FluxoExpressoService::decide` exige `User`); um ator de sistema
  próprio fica como recomendação.
- **Contas ambíguas ficam em `users`.** Servidor local com perfil sem permissão de gestão
  (ou só com `acessar-gestao` direta) continua servidor e aparece na aba da gestão com o
  indicador "sem acesso à gestão". Se for um cidadão de verdade, perde o login do portal até
  criar conta nova com o mesmo CPF; o histórico dele não se perde.
