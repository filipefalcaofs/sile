---
phase: 01-identidade-acesso-e-auditoria-transversal
plan: 04
subsystem: auth
tags: [fortify, registration, email-verification, cpf, must-verify-email, inertia, react, pt-br]

# Dependency graph
requires:
  - phase: 01-01
    provides: Fortify 1.37.2 com features registration/emailVerification, Password::defaults() parametrizado, locale pt-BR (laravel-lang)
  - phase: 01-02
    provides: Listeners RecordRegistrationActivity/RecordEmailVerifiedActivity (CA-02), auditoria transversal RN-002
  - phase: 01-03
    provides: Papel cidadao seedado (RolesAndPermissionsSeeder), UserFactory states, auth-layout.tsx, rotas portal com middleware verified
provides:
  - HU-001 completa — cadastro com nome, e-mail, CPF (validado e único), telefone opcional e senha forte; papel cidadao atribuído; auditado
  - HU-005 completa — verificação por link assinado, auditada; hash adulterado bloqueado; não verificado não acessa o portal
  - App\Rules\ValidCpf — validação de dígitos verificadores reutilizável (fase 3 usa em cadastro empresarial/procuração)
  - Colunas users.cpf (unique, 11 dígitos) e users.phone; #[Fillable] atualizado
  - UserFactory gera CPF válido e telefone — testes de qualquer fase criam usuários realistas
  - Telas auth/register e auth/verify-email pt-BR sobre auth-layout
affects: [01-05, 01-06, 01-07, 01-08, 01-09, fase-2-administracao, fase-3-cadastro-empresarial]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Hash de senha num único ponto: cast 'hashed' do User (Hash::make removido da action publicada do Fortify)"
    - "CPF normalizado para 11 dígitos antes de validar/persistir; máscara só na UI"
    - "Views do Fortify registradas como páginas Inertia no FortifyServiceProvider (registerView/verifyEmailView)"
    - "Requisitos de senha compartilhados com o React via Password::defaults()->toPasswordRulesString()"

key-files:
  created:
    - app/Rules/ValidCpf.php
    - database/migrations/2026_06_10_020811_add_cpf_and_phone_to_users_table.php
    - resources/js/pages/auth/register.tsx
    - resources/js/pages/auth/verify-email.tsx
    - tests/Unit/Rules/ValidCpfTest.php
    - tests/Feature/Auth/RegistrationTest.php
    - tests/Feature/Auth/EmailVerificationTest.php
  modified:
    - app/Actions/Fortify/CreateNewUser.php
    - app/Models/User.php
    - app/Providers/FortifyServiceProvider.php
    - database/factories/UserFactory.php
    - tests/Feature/Audit/AccessLogRecordingTest.php

key-decisions:
  - "Senha hasheada exclusivamente pelo cast 'hashed' do User — Hash::make removido da action publicada (uma única forma, conforme plano)"
  - "Asserção de pt-BR usa 'obrigatória' + 'nome': tradução real do laravel-lang é 'É obrigatória a indicação de um valor para o campo nome.'"
  - "Atributo HTML passwordrules não usado no input (inexistente nos tipos do React); requisitos exibidos como dica visível"

patterns-established:
  - "Telas de auth: Form do Inertia v3 com render props (errors, processing), labels pt-BR, erros por campo, auth-layout"
  - "Testes que fazem POST /register precisam de RolesAndPermissionsSeeder (assignRole no CreateNewUser)"

# Metrics
duration: 13min
completed: 2026-06-10
---

# Fase 1 Plano 04: Cadastro de Usuário e Confirmação de E-mail — Resumo

**HU-001 e HU-005 de ponta a ponta: cadastro pt-BR com CPF validado por dígitos verificadores (Rule própria), papel cidadao automático e auditoria de cadastro; verificação de e-mail por link assinado com auditoria, bloqueio de hash adulterado e portal fechado para não verificados — 19 testes novos**

## Performance

- **Duração:** 13 min
- **Início:** 2026-06-10T02:02:49Z
- **Término:** 2026-06-10T02:16:05Z
- **Tasks:** 2 (ambas TDD Red-Green)
- **Arquivos modificados:** 12

## Realizações

- `ValidCpf` (Rule própria, sem dependência nova) valida dígitos verificadores, rejeita sequências repetidas e tamanhos errados; aceita entrada com ou sem máscara — 4 testes unitários
- Cadastro real (HU-001): colunas `cpf` (unique, 11 dígitos normalizados) e `phone` (opcional) em users; `CreateNewUser` valida tudo com mensagens e atributos pt-BR (nome/CPF/telefone/senha), cria o usuário e atribui o papel `cidadao`; activity `cadastro` gravada pelo listener do 01-02 — 8 testes cobrindo CA-01 a CA-04
- Verificação de e-mail real (HU-005): `User implements MustVerifyEmail` ativou o envio automático da notificação no cadastro, a verificação por rota assinada (`verification.verify`), o reenvio e o middleware `verified` do portal; activity `email-confirmado` gravada pelo listener do 01-02 — 7 testes cobrindo CA-01 a CA-04
- Telas `auth/register` e `auth/verify-email` pt-BR sobre o auth-layout do 01-03, com `<Form>` do Inertia v3, erros por campo, requisitos de senha parametrizados visíveis e estados de processing
- `UserFactory` gera CPF válido e telefone — nenhum teste existente quebrou com as colunas novas
- Suíte completa verde: 51 testes / 162 asserções; `npm run typecheck` e `npm run build` exit 0; pint limpo

## Rotas e nomes reais usados (HU-001/HU-005)

| Uso | Path literal | Nome real |
|---|---|---|
| Tela/POST de cadastro | `/register` | `register` / `register.store` |
| Aviso de verificação | `/email/verify` | `verification.notice` |
| Link assinado de verificação | `/email/verify/{id}/{hash}` | `verification.verify` (usado em `URL::temporarySignedRoute`) |
| Reenvio de link | `/email/verification-notification` | `verification.send` |
| Redirect pós-cadastro/verificação | `/portal` | `fortify.home` (config do 01-01) |

Flash de reenvio: `status === 'verification-link-sent'` (constante `Fortify::VERIFICATION_LINK_SENT`, conferida no vendor).

## Ajustes na action publicada do Fortify (CreateNewUser)

- `Hash::make` removido: o cast `'password' => 'hashed'` do User é a única forma de hash (plano pedia UMA forma)
- `Rule::unique(User::class)` substituído por `'unique:users'` / `'unique:users,cpf'` com normalização do CPF antes da validação
- Atributos pt-BR customizados no Validator (`nome`, `CPF`, `telefone`, `senha`) para mensagens corretas
- `assignRole('cidadao')` após criação (papel do 01-03)

## Commits por Task

Cada fase TDD foi commitada atomicamente:

1. **Task 1 RED (Rule): teste falhando da regra de CPF** - `252146f` (test)
2. **Task 1 GREEN (Rule): ValidCpf com dígitos verificadores** - `59697f9` (feat)
3. **Task 1 RED (Cadastro): teste falhando do cadastro** - `41af89b` (test)
4. **Task 1 GREEN (Cadastro): migration + action + view + factory + tela** - `a067003` (feat)
5. **Task 2 RED: teste falhando da confirmação de e-mail** - `69e84f2` (test)
6. **Task 2 GREEN: MustVerifyEmail + verifyEmailView + tela** - `415e870` (feat)

_REFACTOR não gerou commits próprios: pint sem pendências; única correção foi a remoção do atributo `passwordRules` do input no próprio ciclo GREEN (typecheck)._

## Arquivos Criados/Modificados

- `app/Rules/ValidCpf.php` - Validação de CPF com dígitos verificadores, mensagem pt-BR
- `database/migrations/2026_06_10_020811_add_cpf_and_phone_to_users_table.php` - cpf unique + phone nullable
- `app/Models/User.php` - `#[Fillable]` com cpf/phone; `implements MustVerifyEmail`
- `app/Actions/Fortify/CreateNewUser.php` - Validação completa pt-BR, normalização de CPF, papel cidadao
- `app/Providers/FortifyServiceProvider.php` - `registerView` (com passwordRules) e `verifyEmailView` (com status)
- `database/factories/UserFactory.php` - CPF válido gerado + telefone
- `resources/js/pages/auth/register.tsx` - Tela de cadastro pt-BR (Form v3, erros por campo, dica de senha)
- `resources/js/pages/auth/verify-email.tsx` - Aviso/reenvio de verificação com flash e Sair
- `tests/Unit/Rules/ValidCpfTest.php` (4), `tests/Feature/Auth/RegistrationTest.php` (8), `tests/Feature/Auth/EmailVerificationTest.php` (7)
- `tests/Feature/Audit/AccessLogRecordingTest.php` - Payload de cadastro com cpf/phone + seed de papéis (atualização declarada no plano)

## Decisões Tomadas

- Hash de senha exclusivamente via cast `hashed` (action publicada usava `Hash::make` — removido conforme instrução do plano de manter uma única forma)
- Requisitos de senha exibidos como texto da string `toPasswordRulesString()` (formato Apple passwordrules) com rótulo pt-BR "Requisitos da senha:" — o atributo HTML `passwordrules` não existe nos tipos do React e foi descartado
- Seed de papéis adicionado pontualmente no teste de cadastro do `AccessLogRecordingTest` (não em setUp) — menor mudança que mantém os demais testes da classe independentes de papéis

## Desvios do Plano

### Correções automáticas

**1. [Rule 1 - Bug no plano] Asserção de mensagem pt-BR não casaria com a tradução real**

- **Encontrado em:** Task 1 (RED 2 → GREEN 2)
- **Problema:** O plano mandava assertar a substring `'obrigatório'` na mensagem de `name`, mas a tradução do laravel-lang é "É obrigatória a indicação de um valor para o campo nome." — `'obrigatório'` (masculino) não é substring de `'obrigatória'`; o teste falharia para sempre
- **Correção:** Asserção trocada para `'obrigatória'` + `'nome'` (prova a mensagem E o atributo traduzidos — intenção do CA preservada e fortalecida)
- **Arquivos:** tests/Feature/Auth/RegistrationTest.php
- **Verificação:** `test_cadastro_bloqueado_com_dados_incompletos` verde
- **Commit:** a067003

**2. [Rule 3 - Bloqueio] Teste de cadastro do 01-02 sem papéis seedados quebraria com o assignRole**

- **Encontrado em:** Task 1 (GREEN 2)
- **Problema:** O plano declarou a atualização do payload (cpf/phone) em `AccessLogRecordingTest::test_cadastro_gera_activity_de_cadastro`, mas com `CreateNewUser` chamando `assignRole('cidadao')` o teste passaria a estourar `RoleDoesNotExist` (classe não seeda papéis)
- **Correção:** `$this->seed(RolesAndPermissionsSeeder::class)` adicionado no início do próprio teste
- **Arquivos:** tests/Feature/Audit/AccessLogRecordingTest.php
- **Verificação:** filtro `AccessLogRecordingTest` verde (8/8)
- **Commit:** a067003

---

**Total de desvios:** 2 correções automáticas (1 bug de especificação de teste, 1 bloqueio)
**Impacto no plano:** Nenhum scope creep — ambas necessárias para os testes provarem o comportamento real. Intenção do plano preservada integralmente.

## Problemas Encontrados

- `passwordRules` como atributo do `<input>` falhou no typecheck (propriedade inexistente nos tipos do React) — removido no ciclo GREEN; a dica visível abaixo do campo cumpre o requisito do plano.

## Portões de Autenticação

Nenhum — execução 100% local (artisan, phpunit, npm, git).

## Configuração Manual Necessária

Nenhuma — sem serviços externos neste plano.

## Prontidão para o Próximo Plano

- 01-05 (termo LGPD): fluxo cadastro → verificação pronto; o middleware `lgpd.accepted` entra após `verified` nas rotas do portal
- 01-06 (login/senhas): telas de auth seguem o padrão estabelecido em `auth/register`; `Notification::fake()` + rotas literais validados na prática
- Atenção transversal: qualquer teste novo que poste em `/register` precisa seedar `RolesAndPermissionsSeeder` (assignRole no CreateNewUser)
- Usuários de factory são verificados por padrão (`email_verified_at => now()`); usar `->unverified()` para cenários de verificação
- Migrations continuam não executadas em dev (apenas SQLite de teste) — `php artisan migrate` em Postgres quando o ambiente subir

---
*Fase: 01-identidade-acesso-e-auditoria-transversal*
*Concluído em: 2026-06-10*
