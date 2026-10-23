# Entrada pelo Portal de Serviços da SEDUR — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O cidadão chega ao portal do Viabiliza já autenticado a partir de três serviços da Carta de Serviços da SEDUR (renovação, consulta, convites), e o portal deixa de ter login, cadastro e senha próprios quando o recurso está ligado.

**Architecture:** Uma rota de entrada recebe o token da sessão do Portal de Serviços, valida no BFF da SEDUR (`/v1/acesso/buscar-sessao`), acha ou cria a conta pelo CPF e abre a sessão do guard `web`. Um middleware confere o token periodicamente e derruba a sessão quando ele deixa de valer. Tudo atrás da chave `features.login_portal_servicos` (desligada por padrão); desligada, o portal funciona exatamente como hoje.

**Tech Stack:** Laravel 13, Fortify, Inertia v3 + React 19, PHPUnit 12, `Http::fake`.

**Spec:** `docs/superpowers/specs/2026-10-23-entrada-pelo-portal-de-servicos-design.md`

## Global Constraints

- Chave de recurso: `features.login_portal_servicos` (NÃO `features.portal_servicos`, que já existe e liga os serviços do portal). Padrão `'0'`.
- Destinos (lista fechada): `renovacao` → `route('portal.renovacao.index')`; `consulta` → `route('portal.solicitacoes.index')`; `convites` → `route('portal.solicitacoes.index', ['status' => 'em_pendencia'])`; ausente ou inválido → `route('portal.dashboard')`.
- Rota de entrada: `GET /portal/entrar/portal-servicos`, nome `portal.entrar.portal-servicos`, parâmetros `token` e `destino`.
- E-mail provisório: `<cpf>@solicitante.viabiliza.local` (mesmo padrão do REGIN).
- Intervalo da checagem de sessão: `integrations.portal_servicos.intervalo_checagem_sessao`, padrão `300` segundos.
- Token: nunca em log, URL final, banco ou props do Inertia. Na sessão só cifrado (`Crypt::encryptString`), chave de sessão `portal_servicos.token`; marcador `portal_servicos.conferido_em` (timestamp).
- Console (`/gestao`) não muda em nada.
- Textos ao usuário em português, na linguagem do cidadão; sem "token", "BFF", "SSO" em mensagens.
- PHP: `vendor/bin/pint --dirty --format agent` antes de cada commit; testes com `php artisan test --compact --filter=...`; `DB_TEST_PORT=5444` se o grupo postgis for necessário.
- Commits sem coautoria de IA nem marca de ferramenta.

## Review Focus

1. Resposta do BFF com CPF formatado (`123.456.789-09`) ou com campo em outro nome (`documento`, `cpfCnpj`) → o cliente normaliza e aceita; corpo sem CPF reconhecível → tratado como sessão inválida, nunca como erro 500. (Teste no Task 1.)
2. Duas abas abrindo a entrada ao mesmo tempo no primeiro acesso → uma só conta para o CPF (índice único em `users.cpf` + releitura após falha de unicidade). (Teste no Task 3.)
3. Cidadão já logado no Viabiliza com outro CPF abre a entrada com o token de outra pessoa → a sessão antiga é encerrada e a nova abre para o CPF do token. (Teste no Task 4.)
4. Requisição Inertia (XHR) durante a checagem que derruba a sessão → resposta 409 com `X-Inertia-Location` para o login do Portal de Serviços, não um HTML de redirect dentro do modal. (Teste no Task 6.)
5. `intended` apontando para fora do portal (ex.: `/gestao/...` ou outro host) → ignorado, vale o destino. (Teste no Task 4.)

---

### Task 0: Descobrir o formato real da sessão (fora do código, bloqueia só a validação final)

Depende da SEDUR (mensagem enviada em 23/10/2026). Não bloqueia os Tasks 1–8, que usam `Http::fake` com o formato assumido abaixo.

- [ ] **Step 1:** Com um token de homologação, rodar `curl -s -H "Authorization: Bearer $T" http://ws-bff-portal-servicos.homolog/v1/acesso/buscar-sessao` e repetir sem `Bearer`. Anotar qual funciona.
- [ ] **Step 2:** Registrar na spec (seção "Como o Portal de Serviços funciona hoje") os campos devolvidos e se o token muda ou expira antes da sessão do portal (repetir a chamada depois de 30 e 120 minutos logado).
- [ ] **Step 3:** Se os nomes dos campos diferirem de `cpf`/`nome`/`email`/`nivel`, ajustar só `PortalServicosSessao::deResposta()` (Task 1) e o fixture `tests/Fixtures/portal-servicos/buscar-sessao.json`.

Formato assumido: `{"cpf": "52998224725", "nome": "Fulana", "email": "fulana@exemplo.com", "nivelConfiabilidade": "prata"}` — qualquer campo além de `cpf` pode faltar.

---

### Task 1: Configuração, chave e cliente da sessão

**Files:**
- Modify: `config/viabiliza.php` (bloco `features` e `integrations`)
- Modify: `database/seeders/ParameterSeeder.php`
- Create: `app/Services/PortalServicos/PortalServicos.php`
- Create: `app/Services/PortalServicos/PortalServicosSessao.php`
- Create: `app/Services/PortalServicos/PortalServicosSessaoClient.php`
- Create: `app/Services/PortalServicos/SessaoPortalServicosInvalidaException.php`
- Create: `app/Services/PortalServicos/PortalServicosIndisponivelException.php`
- Modify: `app/Services/Integracao/IntegrationCallRecorder.php` (regra `portal_servicos` ANTES de `inscricao_imobiliaria`, trecho `/v1/acesso/`)
- Modify: `app/Models/IntegrationCall.php` (`ROTULOS['portal_servicos'] = 'Portal de Serviços SEDUR'`)
- Modify: `app/Support/ApiIntegracaoCatalog.php` (aba `portal_servicos`, mesmo formato da aba `sedur_contato`: interruptor da chave e URLs)
- Create: `tests/Fixtures/portal-servicos/buscar-sessao.json`
- Test: `tests/Feature/PortalServicos/PortalServicosSessaoClientTest.php`

**Interfaces:**
- Produces:
  - `PortalServicos::entradaDisponivel(): bool` — `Settings::enabled('login_portal_servicos') && filled(Settings::get('integrations.portal_servicos.bff_base_url')) && filled(Settings::get('integrations.portal_servicos.login_url'))`.
  - `PortalServicos::urlLogin(string $voltarPara): string` — `login_url` + `&state=` + `urlencode($voltarPara)`.
  - `PortalServicos::urlEntrada(string $destino = null): string` — `route('portal.entrar.portal-servicos', ['destino' => $destino, 'token' => 'PREENCHER_TOKEN'])` (o literal `PREENCHER_TOKEN` não pode ser url-encoded de outro jeito).
  - `PortalServicos::urlCarta(): string` — `Settings::get('integrations.portal_servicos.carta_url')`.
  - `final readonly class PortalServicosSessao { public function __construct(public string $cpf, public ?string $nome, public ?string $email, public ?string $nivel) {} public static function deResposta(array $corpo): self }` — `nivel` normalizado para `bronze|prata|ouro` ou `null`; aceita `silver/gold` e maiúsculas.
  - `PortalServicosSessaoClient::buscarSessao(string $token): PortalServicosSessao` — lança `SessaoPortalServicosInvalidaException` (401, 403, 404, corpo sem CPF de 11 dígitos) ou `PortalServicosIndisponivelException` (timeout, conexão, 5xx). Uma tentativa extra só para conexão/5xx.
- Parâmetros novos (seeder + defaults em `config/viabiliza.php`): `features.login_portal_servicos` (boolean, `'0'`), `integrations.portal_servicos.bff_base_url` (`https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos`), `integrations.portal_servicos.login_url` (`https://sso-homo.salvador.ba.gov.br/govbr/authorize/?client_id=SedurMais-PortalServicos-Hml`), `integrations.portal_servicos.carta_url` (`https://servicos.sedur.salvador.ba.gov.br/carta-servicos/17`), `integrations.portal_servicos.timeout` (`8`, só config), `integrations.portal_servicos.intervalo_checagem_sessao` (`300`).

- [ ] **Step 1: Testes que falham**

```php
public function test_sessao_valida_devolve_cpf_nome_email_e_nivel(): void
{
    Http::fake(['*/v1/acesso/buscar-sessao' => Http::response($this->fixture())]);
    $sessao = app(PortalServicosSessaoClient::class)->buscarSessao('tok-123');
    $this->assertSame('52998224725', $sessao->cpf);
    $this->assertSame('prata', $sessao->nivel);
    Http::assertSent(fn ($r) => str_contains($r->header('Authorization')[0] ?? '', 'tok-123'));
}
public function test_cpf_formatado_ou_em_campo_documento_e_aceito(): void   // Review Focus 1
public function test_corpo_sem_cpf_e_sessao_invalida(): void                   // lança SessaoPortalServicosInvalidaException
public function test_401_e_sessao_invalida(): void
public function test_500_e_timeout_sao_indisponibilidade(): void              // lança PortalServicosIndisponivelException
public function test_chamada_fica_no_log_de_integracoes_sem_o_token(): void   // IntegrationCall integration=portal_servicos; 'tok-123' ausente de request_headers
public function test_entrada_indisponivel_com_chave_desligada_ou_sem_url(): void
```

- [ ] **Step 2:** `php artisan test --compact --filter=PortalServicosSessaoClientTest` → FAIL (classe inexistente).
- [ ] **Step 3:** Implementar as classes, parâmetros e regras acima. O cabeçalho é `Authorization: Bearer <token>` (ajustar no Task 0 se a homologação pedir o token cru).
- [ ] **Step 4:** Rodar de novo → PASS. Rodar também `--filter=IntegrationCallRecorder` e `--filter=ApiIntegracao` → PASS.
- [ ] **Step 5:** Pint e commit: `feat: cliente da sessão do Portal de Serviços da SEDUR`.

---

### Task 2: Origem da conta `portal_servicos`

**Files:**
- Modify: `app/Enums/AuthType.php` (case `PortalServicos = 'portal_servicos'`, label `'Portal de Serviços da SEDUR'`)
- Create: migration `php artisan make:migration aumenta_auth_type_em_users --no-interaction` → `string('auth_type', 20)->default('local')->change()` (manter default e nullability atuais; conferir em `2026_09_24_183700_add_auth_columns_to_users_table.php`)
- Modify: `app/Models/User.php` (scopes `usuarioPortal`/`equipeSedur` em `:94-108`: `PortalServicos` conta como usuário do portal, nunca como equipe)
- Test: `tests/Feature/Auth/AuthTypeUserTest.php` (acrescentar)

**Interfaces:**
- Produces: `AuthType::PortalServicos`.

- [ ] **Step 1:** Testes: `test_conta_portal_servicos_grava_e_le_o_tipo` (cria usuário com `auth_type = AuthType::PortalServicos`, recarrega, compara); `test_conta_portal_servicos_entra_no_scope_usuario_portal_e_nao_na_equipe`.
- [ ] **Step 2:** Rodar `--filter=AuthTypeUserTest` → FAIL.
- [ ] **Step 3:** Enum, migration e scopes.
- [ ] **Step 4:** `php artisan migrate --no-interaction` e rodar o filtro → PASS. Rodar `--filter=GestaoLoginAd` → PASS (o login AD não pode mudar).
- [ ] **Step 5:** Pint e commit: `feat: origem de conta Portal de Serviços`.

---

### Task 3: Achar ou criar a conta pelo CPF

**Files:**
- Create: `app/Support/EmailProvisorio.php`
- Modify: `app/Services/Regin/ReginProcessoProtocolador.php:388` (usar `EmailProvisorio::para($cpf)`)
- Create: `app/Services/PortalServicos/PortalServicosAuthService.php`
- Create: `app/Services/PortalServicos/EntradaPortalServicosRecusadaException.php` (`__construct(string $motivoInterno, public readonly string $mensagemUsuario)`)
- Test: `tests/Feature/PortalServicos/PortalServicosAuthServiceTest.php`

**Interfaces:**
- Consumes: `PortalServicosSessao` (Task 1), `AuthType::PortalServicos` (Task 2), `SedurContatoClient::consultarCpf(string): SedurPessoaFisica` (existente, só `nome`).
- Produces:
  - `EmailProvisorio::para(string $cpf): string`, `EmailProvisorio::eh(?string $email): bool`.
  - `PortalServicosAuthService::autenticar(PortalServicosSessao $sessao): User` — lança `EntradaPortalServicosRecusadaException`.

Regras (copiadas da spec):
- CPF com dígito verificador inválido → recusa (`ValidCpf`).
- Nível: aplica `security.govbr.minimum_level` só se `$sessao->nivel !== null`; abaixo → recusa com a mesma mensagem do GOV.BR e `logBlocked` (reaproveitar o texto de `GovBrAuthService::ensureMinimumLevel`).
- Conta existente inativa → recusa `"Sua conta está inativa. Procure a SEDUR."` + `AccessLog` `inativada`, canal `portal`.
- Conta existente com e-mail não confirmado ou provisório: se o e-mail da sessão existe e não é de outra conta, troca; confirma (`markEmailAsVerified`) em qualquer caso; audita `email-confirmado-portal-servicos`. `auth_type` existente nunca muda.
- Primeiro acesso: nome = sessão → `SedurContatoClient::consultarCpf` (falha ignorada) → CPF formatado `000.000.000-00`; e-mail = sessão se livre, senão `EmailProvisorio::para`; senha `Hash::make(Str::random(40))`; `auth_type = PortalServicos`; `markEmailAsVerified`; papel `PerfilCidadao::nome()`; audita `cadastro-portal-servicos` com CPF mascarado; tudo em `DB::transaction`; em `UniqueConstraintViolationException` relê por CPF e devolve.

- [ ] **Step 1:** Testes que falham:
  - `test_conta_existente_ativa_e_devolvida_sem_mudar_auth_type` (inclusive conta `ad`)
  - `test_conta_do_regin_com_email_pendente_fica_confirmada`
  - `test_email_provisorio_e_trocado_pelo_da_sessao`
  - `test_email_provisorio_fica_quando_o_da_sessao_e_de_outra_conta` (mas confirmado)
  - `test_primeiro_acesso_cria_cidadao_confirmado_com_auth_type_portal_servicos`
  - `test_primeiro_acesso_com_email_em_uso_nasce_com_email_provisorio`
  - `test_primeiro_acesso_sem_email_nasce_com_email_provisorio`
  - `test_primeiro_acesso_sem_nome_usa_cadastro_da_sedur_e_depois_o_cpf_formatado`
  - `test_conta_inativa_e_recusada`
  - `test_nivel_abaixo_do_minimo_e_recusado_e_sem_nivel_passa`
  - `test_cpf_invalido_e_recusado`
  - `test_corrida_no_primeiro_acesso_nao_duplica_conta` (Review Focus 2: simular criando a conta entre a busca e o insert, via evento `creating` no teste)
  - `test_regin_continua_usando_o_mesmo_email_provisorio`
- [ ] **Step 2:** `--filter=PortalServicosAuthServiceTest` → FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** Rodar o filtro e `--filter=Regin` → PASS.
- [ ] **Step 5:** Pint e commit: `feat: conta do cidadão a partir da sessão do Portal de Serviços`.

---

### Task 4: Rota de entrada

**Files:**
- Create: `app/Http/Controllers/Portal/EntradaPortalServicosController.php`
- Modify: `routes/portal.php` (rota fora de `guest` e fora de `auth`, com `throttle:30,1` por IP — cidadãos atrás da mesma rede de órgão público)
- Test: `tests/Feature/PortalServicos/EntradaPortalServicosTest.php`

**Interfaces:**
- Consumes: `PortalServicos` (Task 1), `PortalServicosSessaoClient` (Task 1), `PortalServicosAuthService` (Task 3).
- Produces: rota `portal.entrar.portal-servicos`; constante `EntradaPortalServicosController::DESTINOS` com os três destinos da Global Constraints; sessão `portal_servicos.token` (cifrado) e `portal_servicos.conferido_em`.

Fluxo: chave desligada → `redirect()->route('login')` com `status` "Entrada pelo Portal de Serviços indisponível no momento.". Sem `token` ou `token === 'PREENCHER_TOKEN'` → `redirect()->away(PortalServicos::urlLogin(PortalServicos::urlEntrada($destino)))`. Sessão inválida, indisponibilidade ou recusa → `Inertia::render('auth/entrada-portal-servicos', ['mensagem' => ..., 'cartaUrl' => PortalServicos::urlCarta()])` com status 200 (página simples, sem form). Mensagens: inválida → "Sua entrada pelo Portal de Serviços expirou. Entre de novo pelo Portal de Serviços da SEDUR."; indisponível → "Não foi possível confirmar sua entrada agora. Tente de novo em alguns minutos."; recusa → `mensagemUsuario`. Sucesso → se já houver usuário `web` logado: `Auth::guard('web')->logout()` e `session()->invalidate()` antes; depois `Auth::guard('web')->login($user)`, `session()->regenerate()`, grava token cifrado e `conferido_em = now()->timestamp`, `AccessLog` `login` com canal `portal` e origem `portal_servicos`; redireciona para `intended` se for do próprio host e começar com `/portal/` (senão descarta), senão o destino.

- [ ] **Step 1:** Página `resources/js/pages/auth/entrada-portal-servicos.tsx` (título, mensagem, botão "Voltar ao Portal de Serviços" para `cartaUrl`), no padrão visual de `auth/verify-email.tsx`.
- [ ] **Step 2:** Testes que falham:
  - `test_token_valido_abre_sessao_e_vai_para_a_renovacao` (assertRedirect `/portal/renovacao`, `assertAuthenticatedAs($user, 'web')`, `assertGuest('gestao')`)
  - `test_destinos_consulta_e_convites` (convites → `/portal/solicitacoes?status=em_pendencia`)
  - `test_destino_invalido_vai_para_o_painel`
  - `test_token_nao_aparece_no_redirect_nem_na_sessao_em_claro` (Location sem `tok-`; `session('portal_servicos.token')` ≠ token e `Crypt::decryptString` = token)
  - `test_sem_token_vai_ao_login_do_portal_com_preencher_token` (Location começa com `login_url`, contém `state=` e `PREENCHER_TOKEN`)
  - `test_sessao_invalida_mostra_mensagem_e_nao_autentica`
  - `test_bff_fora_mostra_indisponibilidade_e_nao_autentica`
  - `test_chave_desligada_volta_ao_login_antigo`
  - `test_outro_cidadao_logado_e_substituido` (Review Focus 3)
  - `test_intended_de_fora_do_portal_e_ignorado` (Review Focus 5)
  - `test_intended_do_portal_e_respeitado` (ex.: `/portal/solicitacoes/15`)
  - `test_conta_nova_passa_pelo_termo_lgpd` (seguir o redirect: chega em `portal.termo-lgpd.show`)
- [ ] **Step 3:** `--filter=EntradaPortalServicosTest` → FAIL.
- [ ] **Step 4:** Implementar controller, rota e página.
- [ ] **Step 5:** Rodar o filtro → PASS; `npm run build` sem erro de tipo.
- [ ] **Step 6:** Pint e commit: `feat: entrada do cidadão pelo Portal de Serviços da SEDUR`.

---

### Task 5: Desativar a entrada local do portal com a chave ligada

**Files:**
- Create: `app/Http/Middleware/BloqueiaEntradaLocalDoPortal.php` (anexado ao grupo `web` em `bootstrap/app.php`)
- Modify: `bootstrap/app.php:70-78` (`redirectGuestsTo`: portal com a chave ligada → `PortalServicos::urlLogin(PortalServicos::urlEntrada())`, guardando `url.intended` antes)
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (prop compartilhada `entradaPortalServicos: bool`)
- Modify: `resources/js/layouts/settings-layout.tsx` (esconde a aba Senha) e o componente do botão GOV.BR (não renderiza) quando `entradaPortalServicos`
- Test: `tests/Feature/PortalServicos/EntradaLocalDesativadaTest.php`

**Interfaces:**
- Consumes: `PortalServicos::entradaDisponivel()`, `urlLogin()`, `urlEntrada()` (Task 1).
- Bloqueia (só com a chave ligada) as rotas de nome: `login`, `register`, `password.request`, `password.email`, `password.reset`, `password.update`, `portal.conta.senha.show`, `portal.conta.senha.update`, `portal.govbr.redirect`, `portal.govbr.callback` → redirect para o login do Portal de Serviços (GET) ou 404 (POST/PUT). `verification.*` e `logout` continuam.

- [ ] **Step 1:** Testes que falham:
  - `test_com_a_chave_ligada_login_cadastro_e_senha_vao_ao_portal_de_servicos` (data provider com as rotas GET)
  - `test_post_de_login_com_cpf_e_senha_e_recusado`
  - `test_visitante_em_rota_protegida_vai_ao_portal_e_guarda_o_destino` (GET `/portal/solicitacoes/15` → Location do login do portal; `session('url.intended')` termina em `/portal/solicitacoes/15`)
  - `test_console_nao_muda` (`/gestao/login` responde 200 com a chave ligada)
  - `test_com_a_chave_desligada_tudo_segue_como_hoje` (GET `/portal/login` 200)
  - `test_prop_entrada_portal_servicos_no_inertia`
- [ ] **Step 2:** `--filter=EntradaLocalDesativadaTest` → FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** Rodar o filtro, `--filter=AuthenticationTest`, `--filter=RegistrationTest`, `--filter=GovBrLoginTest`, `--filter=SessionIsolationTest` → PASS (os antigos com a chave desligada).
- [ ] **Step 5:** Pint, `npm run build`, commit: `feat: portal sem login próprio quando a entrada é pelo Portal de Serviços`.

---

### Task 6: Checagem periódica da sessão

**Files:**
- Create: `app/Http/Middleware/ConfereSessaoPortalServicos.php` (no grupo `auth:web` do portal em `routes/portal.php`, incluindo o grupo do termo LGPD)
- Test: `tests/Feature/PortalServicos/ConfereSessaoPortalServicosTest.php`

**Interfaces:**
- Consumes: `PortalServicosSessaoClient::buscarSessao` (Task 1), chaves de sessão do Task 4.
- Regras: só age se existir `portal_servicos.token` na sessão. Se `now()->timestamp - conferido_em < intervalo`, passa sem chamar. Senão chama: válido → atualiza `conferido_em`; `SessaoPortalServicosInvalidaException` → `Auth::guard('web')->logout()`, `session()->invalidate()`, `regenerateToken()`, redireciona ao login do Portal de Serviços (`Inertia::location(...)` quando `X-Inertia`); `PortalServicosIndisponivelException` → passa e NÃO atualiza `conferido_em` (tenta de novo na próxima requisição, no máximo uma chamada por minuto — guardar `portal_servicos.tentado_em`). CPF da sessão diferente do usuário logado → trata como inválida.

- [ ] **Step 1:** Testes que falham:
  - `test_dentro_do_intervalo_nao_chama_o_bff` (`Http::assertNothingSent`)
  - `test_token_ainda_valido_mantem_a_sessao`
  - `test_token_invalido_encerra_a_sessao_e_manda_ao_portal`
  - `test_requisicao_inertia_recebe_409_com_location` (Review Focus 4)
  - `test_bff_fora_nao_derruba_e_tenta_no_maximo_uma_vez_por_minuto`
  - `test_cpf_diferente_encerra_a_sessao`
  - `test_quem_nao_entrou_pelo_portal_de_servicos_nao_e_conferido`
- [ ] **Step 2:** `--filter=ConfereSessaoPortalServicosTest` → FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** Rodar o filtro e `--filter=SessionExpiredTest` → PASS.
- [ ] **Step 5:** Pint e commit: `feat: sessão do portal acompanha a do Portal de Serviços`.

---

### Task 7: Sair volta à Carta de Serviços

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php` (binding de `Laravel\Fortify\Contracts\LogoutResponse`, ao lado do `LoginResponse` de `:44`)
- Test: `tests/Feature/PortalServicos/SaidaPortalServicosTest.php`

**Interfaces:**
- Regra: se a requisição de logout é do portal e a chave está ligada → `redirect()->away(PortalServicos::urlCarta())` (`Inertia::location` para XHR); senão o comportamento atual do Fortify (`/`). Ler a chave ANTES do `invalidate` não é necessário: a decisão usa só a chave, não a sessão.

- [ ] **Step 1:** Testes: `test_sair_com_a_chave_ligada_vai_a_carta_de_servicos`, `test_sair_com_a_chave_desligada_segue_como_hoje`, `test_sair_do_console_nao_muda`.
- [ ] **Step 2:** FAIL → implementar → PASS (`--filter=SaidaPortalServicosTest`).
- [ ] **Step 3:** Pint e commit: `feat: sair do portal volta à Carta de Serviços`.

---

### Task 8: HUs alinhadas às regras de e-mail provisório

**Files:**
- Modify: `docs/requisitos/hus/HU.140 - Cadastrar usuario.md` (cenários 3 e 4: e-mail em uso ou ausente → conta nasce com e-mail provisório, corrigível em Meus dados; sem nome → nome do cadastro da SEDUR ou CPF)
- Modify: `docs/requisitos/hus/HU.142 - Autenticar pelo portal de servicos da SEDUR.md` (cenário 5 e Visão Geral no mesmo sentido; conta do REGIN com e-mail pendente é confirmada na entrada; botão Sair volta à Carta de Serviços)
- Regenerar DOCX/PDF das duas e os blocos nos consolidados `HUs-VIABILIZA-PORTAL` e `HUs-VIABILIZA-CONSOLIDADO` (mesmo processo de 22/10/2026: DOCX individual a partir do anterior; troca de bloco nos consolidados; PDF via `soffice --headless --convert-to pdf`).

- [ ] **Step 1:** Editar os dois `.md`, em linguagem do cidadão (sem token/BFF/SSO), acrescentando a linha 23/10/2026 no histórico com versão `2.4`.
- [ ] **Step 2:** Gerar DOCX/PDF e trocar os blocos; conferir com `pdftotext` que os consolidados têm 42 e 181 HUs e que "e-mail provisório" aparece nas duas HUs.
- [ ] **Step 3:** Commit: `docs: HUs 140 e 142 com e-mail provisório e saída para a Carta de Serviços`.

---

### Task 9: Verificação final

- [ ] **Step 1:** `php artisan test --compact tests/Feature/PortalServicos tests/Feature/Auth tests/Feature/Renovacao tests/Feature/Solicitacao tests/Feature/Lgpd` → tudo PASS; colar o resumo.
- [ ] **Step 2:** Perguntar ao usuário se roda a suíte inteira (`php artisan test --compact`).
- [ ] **Step 3:** Com o Task 0 concluído: em homologação, ligar `features.login_portal_servicos`, abrir `/portal/entrar/portal-servicos?destino=renovacao&token=<token real>` e conferir que cai em `/portal/renovacao` autenticado; registrar o resultado. Sem o Task 0, declarar explicitamente que a integração real não foi verificada.
