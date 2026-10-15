# Autenticação do console via AD (API institucional SEDUR)

Data: 2026-09-24
Status: aprovada a abordagem em discussão; aguardando revisão final da spec

## 1. Contexto e problema

O console interno (`/gestao`, guard `gestao`) autentica hoje por e-mail + senha local em `App\Http\Controllers\Gestao\LoginController`. A SEDUR disponibiliza uma API institucional de autenticação corporativa (AD) e de gestão de pessoas:

- `POST {base}/ws-autenticacao/auth` — body `{"usuario": "<conta_ad>", "senha": "<senha_ad>"}` → Bearer token
- `GET {base}/ws-autenticacao/discovery` — com Bearer
- `GET {base}/ws-gp/view/pessoaView/buscarPorCPF/<cpf>` — com Bearer → dados da pessoa

Base de produção: `https://api.sedur.salvador.ba.gov.br/k8s/prd`.

**Objetivo:** servidores com conta corporativa passam a entrar no console validando as credenciais contra o AD via essa API. Usuários sem conta AD (ex.: externos) continuam com senha local. O Laravel permanece soberano sobre sessão, autorização (Spatie), auditoria e demais mecanismos de segurança — o AD é apenas o validador de identidade dos usuários corporativos.

## 2. Decisões de escopo (aprovadas pelo usuário)

1. **Autenticação híbrida:** AD e local coexistem. O cadastro de usuários continua no Laravel, inclusive para quem não tem conta AD.
2. **Tipo de autenticação explícito no cadastro:** cada usuário tem `auth_type` (`ad` | `local`). Para `ad`, o cadastro armazena a conta corporativa em `ad_username` (ex.: `seuusuarioad`). O vínculo é determinístico — nunca inferido por e-mail ou CPF.
3. **CPF não é identificador da conta AD:** o CPF (já `unique` em `users`) é usado apenas para consultar/sincronizar dados da pessoa via `pessoaView`, no interesse do próprio usuário autenticado.
4. **Sessão sempre Laravel:** após qualquer validação (AD ou local), a sessão é a sessão Laravel do guard `gestao` (cookie `sile_gestao_session`, CSRF, logout, Spatie, auditoria). O token Bearer da API fica restrito ao backend, nunca exposto ao navegador.
5. **Sem fallback silencioso:** usuário `ad` com API indisponível recebe erro honesto de indisponibilidade — nunca cai para senha local.

## 3. Princípio de design

O AD é um **validador de identidade atrás de um contrato**; o console é uma **fronteira de autenticação fechada** que já existe e não se move. A mudança é cirúrgica: o `LoginController` delega a verificação de credencial a um `DirectoryAuthenticator`, e todo o resto do fluxo (throttle, anti-oráculo, conta ativa, permissão, sessão, auditoria) permanece.

Anti-fachada (regra do projeto): a validação AD é real, contra a API institucional. Se a API estiver fora do ar, o login AD falha com mensagem explícita — não há adaptador falso nem "modo contingência" simulado. Fakes só na suíte de testes (`Http::fake`).

## 4. Modelo de dados

Migration em `users`:

| Coluna | Tipo | Observação |
|---|---|---|
| `auth_type` | `string` com cast para enum `AuthType` (§6) | `ad` \| `local`; default `local`; indexada |
| `ad_username` | `string` nullable, `unique` | Obrigatório quando `auth_type = ad`; nulo caso contrário |

Regras de integridade (validação no cadastro/edição de usuários do console):

- `auth_type = ad` exige `ad_username` preenchido; `auth_type = local` exige `ad_username` nulo.
- Ao converter um usuário para `ad`: a senha local é substituída por um hash aleatório inutilizável e o reset de senha do console passa a ser recusado para essa conta (a senha é corporativa, gerida fora do VIABILIZA).
- Ao converter para `local`: exige definição de nova senha local (fluxo de reset), pois o hash pode ser o aleatório.

## 5. Fluxo de login (console)

```
POST /gestao/login (identificador + senha)
 1. Throttle (antes de qualquer chamada externa) — hoje email|ip; estender com
    limites separados por usuário e por IP (contém password spraying contra o AD)
 2. Localiza User local por e-mail ou por ad_username, de forma determinística
 3. auth_type = local → Hash::check (fluxo atual, intacto)
    auth_type = ad   → DirectoryAuthenticator::attempt(ad_username, senha)
                       → POST /ws-autenticacao/auth
 4. Falha de credencial (local ou AD) → ValidationException genérica (auth.failed)
    + evento Failed SEM a senha nas credenciais
 5. API AD indisponível/erro → mensagem honesta de indisponibilidade + auditoria
    (evento distinto de credencial inválida)
 6. Sucesso de credencial → checagens locais, nesta ordem:
    isInactive() → can('acessar-gestao') → (demais regras já existentes)
    Qualquer negação: fail closed + auditoria (padrão atual)
 7. Sincronização (somente AD): GET pessoaView/buscarPorCPF/{user.cpf}
    → atualiza users.name; descarta todo o restante do payload na mesma request
 8. Auth::guard('gestao')->login($user, remember) + session()->regenerate()
    (inalterado)
```

Notas:

- A senha AD existe apenas na variável da request: nunca persistida (nem `users.password`, nem `parameters`, nem cache, nem sessão), nunca logada, nunca em exceção. O client relança exceção limpa (padrão `ReginHttpClient`).
- O token Bearer é usado na sequência auth → discovery/pessoaView **dentro da mesma request** e descartado ao final. Sem cache, sem sessão, sem cookie.
- `discovery` é chamado no login AD para validar a saúde/contrato da integração; se o plano de implementação constatar que é redundante com `auth`, pode ser removido do fluxo quente e usado apenas no teste de conexão da tela de parâmetros.

### 5.1 Experiência de identificação no login

O único campo de identificação da tela passa a ser **“E-mail institucional ou usuário AD”**, do tipo texto e com `autocomplete="username"`. O placeholder é `nome@salvador.ba.gov.br ou seuusuarioad`.

A orientação visível informa: “Use o usuário da rede corporativa (ex.: seuusuarioad) ou o e-mail da conta local.” Assim, a pessoa com conta AD entende que deve informar a credencial corporativa; quem usa conta local reconhece o e-mail como identificador.

Não há seletor “AD” × “local”: o Laravel identifica o mecanismo pelo cadastro da conta, reduzindo escolha errada e não expondo o tipo de autenticação de contas que não existem. As falhas de identificação e de senha permanecem em uma mensagem genérica (`auth.failed`), preservando o anti-oráculo.

O link “Esqueceu?” permanece. Para uma conta AD, o fluxo de recuperação continua sem enviar e-mail e mantém a resposta genérica; a senha corporativa é alterada fora do VIABILIZA.

## 6. Componentes

| Componente | Local | Responsabilidade |
|---|---|---|
| `SedurAuthClient` | `app/Services/Sedur/SedurAuthClient.php` | HTTP com a API (auth, discovery, pessoaView). `Http::timeout()->asJson()`, verificação TLS obrigatória, exceções limpas (`SedurAuthUnavailableException`, `SedurAuthInvalidCredentialsException`). Padrão `ReginHttpClient`. |
| `DirectoryAuthenticator` (contrato) | `app/Services/Auth/DirectoryAuthenticator.php` | `attempt(string $adUsername, string $password): DirectoryAuthResult` — interface consumida pelo controller; implementação `SedurDirectoryAuthenticator` usa o client. |
| `LoginController` (gestão) | existente | Orquestra: throttle → resolve usuário → delega credencial → checagens locais → sessão. |
| Cadastro de usuários | tela/controller existentes | Campo "Tipo de autenticação" + "Conta AD" com as validações da §4. |
| `AuthType` enum | `app/Enums/AuthType.php` | `Ad`, `Local` (TitleCase nas keys, padrão do projeto). |

## 7. Parametrização (catálogo existente — `ParameterSeeder` + tela Parâmetros)

| Chave | Tipo | Sensível | Justificativa |
|---|---|---|---|
| `integrations.sedur_auth.base_url` | string (URL) | não | Varia entre homologação e produção |
| `features.ad_login` | bool | não | Interruptor de fluxo real; desligar bloqueia login AD com aviso explícito (nunca falha silenciosa) |

Timeouts ficam em `config/viabiliza.php` (regra de parametrização: throttle/timeout não são parâmetro de gestor). Nenhuma credencial sensível é necessária: a autenticação usa a senha do próprio usuário, transitória.

## 8. Auditoria

- `access_logs` passa a distinguir a origem da autenticação: login AD × login local; e a natureza da falha: credencial inválida × API indisponível × conta sem permissão × conta inativa. Implementação via listeners existentes (`RecordSuccessfulLogin`, `RecordFailedLogin`) + registros diretos no controller, mantendo o fillable fechado do `AccessLog` (estruturalmente impossível logar senha/token).
- Evento `Failed` disparado **sem** a senha no array de credenciais.
- `session()->regenerate()` no sucesso e `regenerateToken()` no logout permanecem.

## 9. Segurança e LGPD (obrigatórios)

1. Senha AD nunca persiste, nunca loga, fora de Telescope/Debugbar em produção; exceção limpa em falha da API.
2. HTTPS com verificação de certificado (sem `withoutVerifying`); timeout curto.
3. Throttle por usuário e por IP **antes** de chamar a API AD; anti-oráculo: usuário AD inexistente, senha errada e conta AD bloqueada retornam a mesma mensagem genérica.
4. Fail closed: `isInactive()` + `can('acessar-gestao')` antes de `Auth::guard('gestao')->login()`; negação auditada.
5. `pessoaView` somente com o CPF do **próprio usuário autenticado**, extraindo apenas o necessário (nome) e descartando o resto. Consulta a CPF de terceiros pelo console é feature separada — **bloqueada** até definição de escopo do token e base legal (ver §11).
6. Provisionamento é manual-prévio (cadastro no console com `auth_type`/`ad_username`): servidor AD válido sem cadastro no VIABILIZA não entra. Não há JIT provisioning nesta fase.
7. Desprovisionamento: a validação a cada login já barra quem foi desligado no AD; `inactivated_at` local segue soberano e `EnsureUserIsActive` derruba sessões de inativados.

## 10. Testes (TDD)

Fakes: `Http::fake()` para a API SEDUR e fake do contrato via `app->instance(DirectoryAuthenticator::class, ...)`. Nenhum teste chama a API real.

Feature tests (caminhos felizes, falhas e bordas):

- Login AD com sucesso → sessão `gestao`, `access_logs` marcado como AD.
- Login AD com credencial inválida → mensagem genérica idêntica à de senha local errada; `Failed` sem senha.
- API AD indisponível (timeout/500) → erro de indisponibilidade, sem fallback local, auditoria de indisponibilidade.
- Usuário `local` → fluxo atual intacto (regressão).
- Usuário AD sem `acessar-gestao` ou inativado → negado + auditado.
- Throttle: após N tentativas, a tentativa bloqueada **não** chama a API AD.
- Senha AD ausente de `access_logs`, de `storage/logs` e das props Inertia.
- Sincronização: `pessoaView` retorna payload rico → `users.name` atualizado, nenhum campo extra persistido.
- Cadastro: validações de `auth_type`/`ad_username` (obrigatoriedade, unicidade, exclusividade).
- Conversão local→ad: senha local inutilizada; reset de senha recusado para conta AD.

## 11. Escalonamentos (pendências externas — registrar em STATE.md/ROADMAP.md)

1. **TI-SEDUR (dona da API):** escopo real do token do `/auth` (lê `pessoaView` de qualquer CPF? TTL?), ambiente de **homologação** da API e credenciais de teste. A conclusão da integração exige validação contra homologação real com evidência registrada (regra do projeto).
2. **DPO:** base legal do tratamento de nome/CPF de servidores para gestão de acesso; janela de retenção de `access_logs`.
3. **SEDUR:** política de desprovisionamento — sincronização proativa (job consultando status funcional) depende de a API oferecer essa consulta; sem ela, vale validação a cada login + inativação manual.
4. **Bloqueio explícito:** consulta a `pessoaView` de terceiros pelo console permanece bloqueada até (1) e (2) serem respondidos.
