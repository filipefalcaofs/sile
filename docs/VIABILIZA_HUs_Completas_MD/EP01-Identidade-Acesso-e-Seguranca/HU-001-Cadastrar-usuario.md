# HU-001 — Cadastrar usuário

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O cidadão cria a própria conta no portal, com CPF, e-mail, telefone e senha. A conta nasce com o perfil de cidadão e precisa confirmar o e-mail antes de usar as telas autenticadas.

## História de Usuário
**Como** visitante do portal, **quero** me cadastrar com nome, e-mail, CPF, telefone e senha, **para** acessar o portal do Viabiliza com uma conta local.

## Atores e permissões
Visitante não autenticado. O Fortify publica o cadastro em `/portal/register` (prefixo `portal`, guard `web`, `Features::registration()`). Não há permissão Spatie nessa rota. O perfil atribuído é o nome devolvido por `PerfilCidadao::nome()` (parâmetro `portal.perfil_cidadao`, padrão `cidadao`).

O menu do portal não inclui o cadastro: a tela é a view de registro do Fortify (`auth/register`).

## Fluxo evidenciado
1. O visitante abre `/portal/register`. A view recebe a descrição da política de senha (`PasswordPolicy::description()`) e se o botão GOV.BR pode aparecer (`GovBr::loginAvailable()`).
2. O envio chama `CreateNewUser::create`. O CPF é reduzido a dígitos antes da validação.
3. Com os dados válidos, o sistema grava `name`, `email`, `cpf`, `phone` e `password` em `users` e chama `assignRole` com o perfil de cidadão.
4. `User` implementa `MustVerifyEmail`. As rotas autenticadas do portal exigem o middleware `verified`, então a conta só segue para o painel depois da confirmação do e-mail (HU-005).
5. Conta de servidor no console não nasce neste formulário. O primeiro acesso com identificador ainda inexistente, quando a autenticação institucional está ligada, é criado em `LoginController::registrarPrimeiroAcessoAd` (HU-002).

## Regras de negócio evidenciadas
- RN-001: o cadastro local é autocadastro do portal e atribui um único papel, o de cidadão (`CreateNewUser::create`).
- RN-002: CPF, e-mail e telefone são obrigatórios; o CPF é único e passa por `ValidCpf`; o telefone passa por `TelefoneComDdd`.
- RN-003: a senha segue `PasswordValidationRules::passwordRules()` (`Password::default()` + confirmação). Os requisitos vêm de `PasswordPolicy::buildRule()`: tamanho mínimo `security.password.min_length` (padrão 8), maiúsculas e minúsculas se `security.password.require_mixed_case`, números se `security.password.require_numbers`, símbolos se `security.password.require_symbols`.
- Não há regra, nesta ação, de consulta a GIS, REDESIM, CNAE ou motor da LOUOS.

## Validações
`CreateNewUser::create`, com `Validator::make`:

- `name`: obrigatório, texto, máximo 255 (rótulo "nome").
- `email`: obrigatório, e-mail, máximo 255, único em `users`.
- `cpf`: obrigatório, `ValidCpf` (11 dígitos, dígitos verificadores, rejeita sequência repetida), único em `users.cpf`.
- `phone`: obrigatório, máximo 20, `TelefoneComDdd` (DDD + 8 ou 9 dígitos; a máscara não entra na contagem).
- `password`: obrigatório, política padrão e `confirmed`.

## Critérios de aceite
### CA-01 — Cadastro válido cria conta de cidadão
**Dado** um visitante em `/portal/register` com nome, e-mail inédito, CPF válido, telefone com DDD e senha que cumpre a política, **quando** envia o formulário, **então** `CreateNewUser` grava o usuário e atribui o papel de `PerfilCidadao::nome()`.

### CA-02 — CPF ou e-mail repetido
**Dado** um CPF ou e-mail já existente em `users`, **quando** o visitante tenta se cadastrar, **então** a validação recusa o envio e nenhuma conta nova é gravada.

### CA-03 — CPF inválido
**Dado** um CPF com quantidade de dígitos diferente de 11, sequência repetida ou dígitos verificadores incorretos, **quando** o visitante envia o cadastro, **então** `ValidCpf` recusa com a mensagem de CPF inválido.

### CA-04 — Conta ainda não entra no portal autenticado
**Dado** um cadastro concluído sem `email_verified_at`, **quando** o usuário tenta abrir uma rota do portal protegida por `auth:web` e `verified`, **então** o middleware de verificação impede o uso até a confirmação do e-mail.

## Divergências da redação anterior
- O texto antigo tratava cidadão, contador, procurador e servidor como o mesmo cadastro, e incluía consulta a GIS, REDESIM, CNAE e LOUOS. O código só cadastra conta local do portal.
- Não há pré-condição de usuário já autenticado nem de permissão Spatie.
- A criação de conta AD no primeiro login do console e a criação de conta no GOV.BR não passam por este formulário.

## Evidências
- `config/fortify.php` — `Features::registration()`, prefixo `portal`, username `cpf`.
- `app/Providers/FortifyServiceProvider.php` — `Fortify::registerView` e `Fortify::createUsersUsing(CreateNewUser::class)`.
- `app/Actions/Fortify/CreateNewUser.php` — validação e `assignRole`.
- `app/Rules/ValidCpf.php` e `app/Rules/TelefoneComDdd.php`.
- `app/Support/PasswordPolicy.php` e `app/Support/PerfilCidadao.php`.
- `routes/portal.php` — grupo `auth:web` + `verified`.

## Não evidenciado
Não há tela em que um administrador crie usuário digitando os dados. `UserManagementController` lista, troca papel, inativa e altera o tipo de autenticação; não tem `store` de usuário. Não há integração com base externa neste cadastro.
