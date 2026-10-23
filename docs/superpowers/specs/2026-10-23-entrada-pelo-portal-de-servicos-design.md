# Entrada no portal do cidadão pelo Portal de Serviços da SEDUR

Data: 23/10/2026. Base de requisito: HU-142 (v2.3) e HUs 001, 002, 140, 141, 143 revistas em 22/10/2026.

## Intenção

O cidadão já se identifica no Portal de Serviços da SEDUR (servicos.sedur.salvador.ba.gov.br) com a conta GOV.BR. Três serviços da Carta de Serviços passam a abrir o portal do cidadão do Viabiliza inteiro, já autenticado, cada um numa tela de partida:

| Serviço na Carta | Tela de partida no Viabiliza |
|---|---|
| Renovação de Viabilidade (serviço 345, já existe na Carta) | `/portal/renovacao` |
| Consulta de processo (serviço novo na Carta) | `/portal/solicitacoes` |
| Responder convites (serviço novo na Carta) | `/portal/solicitacoes?status=em_pendencia` |

Depois de entrar, o cidadão navega por todo o portal. O portal do Viabiliza deixa de ter tela de login, cadastro e senha próprios para o cidadão.

Modelo, decidido pelo usuário: igual ao AD do console. O Portal de Serviços confirma quem é a pessoa; a conta fica no banco do Viabiliza (criada no primeiro acesso) e a sessão é a do próprio Laravel (guard `web`). A sessão do Viabiliza termina quando a do Portal de Serviços termina.

## Como o Portal de Serviços funciona hoje (levantado no código, 22/10/2026)

Repositório `sedur-mais/portais/portal-servicos`, branch `prd`, no GitLab roma; lib `sedur-mais/libs/laravel-libs`.

- Login: SSO da Prefeitura (`sso.salvador.ba.gov.br/govbr/authorize/?client_id=SedurMais-PortalServicos-Prd`), que repassa ao GOV.BR. No retorno, o portal chama o BFF `GET {bff}/v1/acesso/integracao/sedur-mais` e guarda na sessão o **token do BFF** (`Session::put('autenticacao', ...)`).
- BFF: `https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos` (homolog `http://ws-bff-portal-servicos.homolog`). A sessão é conferida em `GET {bff}/v1/acesso/buscar-sessao` com o header `Authorization: <token>` (o portal usa `withToken`, ou seja, `Bearer <token>`, no `buscarSessao`; o `client()` manda o token cru — testar os dois).
- Abrir sistema externo já autenticado: o portal monta a URL do sistema com `...token=<token do BFF>` (lista `servicos_sudoeste` em `libs/src/config.php`, ex.: publicidade, som, ERB).
- Sem sessão: o portal manda ao SSO com `&state=<url de volta>`. No retorno, se a URL de volta contém a palavra `PREENCHER_TOKEN`, ela é trocada pelo token; o portal ainda passa pela "Minha Área" (`sad.../login/govbr?token=...&urlVoltar=...&voltarImediatamente=true`) antes de voltar à URL.
- Saída do portal: `sso.salvador.ba.gov.br/sso/sair?client_id=...&redirect_uri=...`.

**Não sabido:** o formato da resposta de `buscar-sessao`. O portal só usa `cpf` (e `token` no `integracao/sedur-mais`). Nome, e-mail e nível GOV.BR podem vir ou não. A primeira tarefa do plano é descobrir com um token real de homologação.

## Como o Viabiliza funciona hoje (levantado no código, 23/10/2026)

- Guards `web` (portal, cookie `sile_portal_session`, path `/portal`) e `gestao` (console). Grupo do portal: `auth:web` + `verified` + `lgpd.accepted` + `ResolveRepresentation` (`routes/portal.php:87-91`); a renovação e a consulta ainda passam por `portal.servicos` e, a renovação, por `portal.solicitacao`.
- Login por CPF e senha via Fortify (`config/fortify.php`, prefixo `portal`); cadastro, recuperação e troca de senha do portal também via Fortify.
- Login GOV.BR direto (HU antiga): `GovBrLoginController` + `GovBrAuthService` (`app/Services/GovBr/GovBrAuthService.php:33-223`): acha usuário pelo CPF, confere ativo e nível mínimo (`security.govbr.minimum_level`), cria a conta no primeiro acesso (e-mail verificado obrigatório e único, senha aleatória, `markEmailAsVerified`, papel cidadão, `GovBrAccount`, auditoria `cadastro-govbr`). Ligado por `Settings::enabled('govbr_login')`.
- Padrão AD do console: `users.auth_type` (`AuthType::Ad|Local`) e `ad_username`; conta criada no primeiro acesso em `Gestao/LoginController::registrarPrimeiroAcessoAd`.
- Retorno ao destino: o login e o termo LGPD usam `redirect()->intended(...)`.
- Não há nenhuma ligação com o BFF para autenticação, nem checagem externa da sessão.

## Objetivos

1. Entrada `GET /portal/entrar/portal-servicos?token=...&destino=renovacao|consulta|convites` que valida o token no BFF, acha ou cria a conta pelo CPF, abre a sessão do guard `web` e redireciona ao destino, sem o token no endereço final.
2. Visitante sem sessão em qualquer rota protegida do portal vai ao login do Portal de Serviços (SSO com `state` contendo a nossa entrada com `PREENCHER_TOKEN`) e volta à página que tentou abrir.
3. A sessão do Viabiliza confere, de tempos em tempos, se o token do Portal de Serviços ainda vale; se não vale, encerra a sessão do Viabiliza.
4. O portal do cidadão deixa de oferecer login por CPF e senha, cadastro, "esqueci a senha" e troca de senha (conforme as HUs revistas), quando a entrada pelo Portal de Serviços estiver ligada.
5. Especificação do que a SEDUR precisa mudar no Portal de Serviços (seção própria abaixo).

## Fora do escopo

- Mudar o código do Portal de Serviços (entregamos a especificação; quem faz é a SEDUR — decisão minha, ver "Decisões minhas").
- Console (`/gestao`): continua igual, com AD e senha local.
- Tela agregada de convites: usa-se o filtro de status da lista existente.
- Deep link por protocolo ou número de TVL (só por id, como hoje).
- Remover o código do login GOV.BR direto e do Fortify do portal: ficam desligados por chave, não apagados.

## O que não pode acontecer

- Token do Portal de Serviços gravado em log, em URL final, em banco em claro ou exposto ao front.
- Conta de cidadão criada sem CPF válido, ou duplicada para o mesmo CPF.
- Usuário do console (conta AD ou com `acessar-gestao`) virando sessão do portal por essa entrada sem as mesmas regras de hoje (o mesmo CPF pode ser cidadão no portal: as sessões já são isoladas por guard e cookie; manter isso).
- Redirecionamento aberto: o `destino` é uma lista fechada; a URL de volta (`state`) só pode apontar para o próprio Viabiliza.
- Cidadão que chega pelo Portal de Serviços ficar preso sem conseguir entrar (e-mail pendente, provisório ou em uso): com o login padrão desativado, o Portal de Serviços é o único caminho (decisão do usuário, 23/10/2026).

## Desenho

### Configuração

- Chave de recurso `portal_servicos_login` em `Settings` (padrão desligado), no mesmo padrão de `govbr_login`.
- `config/viabiliza.php` → `integrations.portal_servicos`: `bff_base_url`, `login_url` (URL do SSO do portal com `client_id`), `logout_url`, `timeout`, `intervalo_checagem_sessao` (segundos, padrão 300). Valores por `.env`; nada de chave secreta no repositório.
- Item novo no catálogo de APIs (`app/Support/ApiIntegracaoCatalog.php`) para aparecer no log de integrações, como as demais.

### Entrada (`EntradaPortalServicosController`)

1. Recurso desligado → redireciona ao login antigo com aviso de entrada indisponível.
2. `token` ausente → manda ao login do Portal de Serviços (objetivo 2).
3. Chama `PortalServicosSessaoClient::buscarSessao($token)` (`GET {bff}/v1/acesso/buscar-sessao`). Erro de rede ou 5xx → mensagem de indisponibilidade, sem sessão, registro no log de integrações. 401/403 ou sem CPF → mensagem para entrar de novo pelo Portal de Serviços.
4. Normaliza o CPF (11 dígitos, dígitos verificadores) e chama `PortalServicosAuthService::autenticar($dadosSessao)`, que reaproveita as regras do `GovBrAuthService` (extrair para um serviço comum ou chamar os mesmos passos):
   - usuário existente: conta ativa, senão recusa ("procure a SEDUR"). Se o e-mail não estiver confirmado ou for provisório (`<cpf>@solicitante.viabiliza.local`, criado pelo REGIN em `ReginProcessoProtocolador.php:383-390`), a entrada confirma o e-mail e troca o provisório pelo e-mail da sessão do Portal de Serviços, se esse e-mail não for de outra conta; se for, mantém o provisório e confirma assim mesmo. Fica auditado;
   - nível mínimo GOV.BR: aplicado **se** a sessão trouxer o nível; se não trouxer, não bloqueia (registrar no log de integrações que o nível não veio);
   - primeiro acesso: cria a conta com nome, CPF e e-mail da sessão (ou do cadastro de contato da SEDUR, se a sessão não trouxer — `SedurContatoClient` por CPF). E-mail já usado por outra conta, ou ausente, não bloqueia: a conta nasce com o e-mail provisório `<cpf>@solicitante.viabiliza.local`, no mesmo padrão do REGIN, e o cidadão corrige em Meus dados. `email_verified_at` preenchido, senha aleatória inutilizável, papel cidadão, `auth_type = portal_servicos` (novo caso em `AuthType`), auditoria `cadastro-portal-servicos`;
   - sem nome em nenhuma das fontes → usa o CPF formatado como nome provisório (decisão minha), para não travar a entrada.
5. `Auth::guard('web')->login($user)`, `session()->regenerate()`, auditoria de acesso com origem "Portal de Serviços".
6. Guarda o token **cifrado na sessão** (`Crypt`), nunca em banco, para a checagem periódica.
7. Redireciona: `intended` (se veio de uma rota protegida) senão o destino da lista fechada; destino inválido → `/portal/painel`. As portas existentes (`verified`, `lgpd.accepted`) seguem valendo depois.

### Visitante sem sessão (objetivo 2)

Com o recurso ligado, o redirecionamento de convidado do portal (hoje `bootstrap/app.php:70-78` → rota `login`) passa a ir para `login_url` + `&state=` + URL da nossa entrada com `token=PREENCHER_TOKEN`, guardando antes o `intended`. A rota `/portal/login` também redireciona para lá. Console não muda.

### Checagem da sessão (objetivo 3)

Middleware `ConfereSessaoPortalServicos` no grupo do portal: se o usuário entrou por essa via e passou `intervalo_checagem_sessao` desde a última conferência, chama `buscar-sessao`. Token inválido → `Auth::guard('web')->logout()`, invalida a sessão e manda ao login do Portal de Serviços com aviso de sessão encerrada. BFF indisponível → não derruba a sessão (registra e tenta na próxima). Respostas Inertia/XHR recebem o mesmo tratamento que a sessão expirada tem hoje (`SessionExpiredTest`).

### Saída

O botão Sair do portal encerra só a sessão do Viabiliza e devolve o cidadão à Carta de Serviços (decisão minha). Não chama a saída do Portal de Serviços.

### Telas desligadas com o recurso ligado

A tela padrão de login do portal é desativada: com o recurso ligado, o único caminho de entrada do cidadão é o Portal de Serviços (decisão do usuário). Login por CPF e senha, cadastro, esqueci a senha e aba Senha em Minha conta não aparecem nem respondem no portal (redirecionam à entrada). Botão "Entrar com GOV.BR" direto também sai. O reenvio de confirmação de e-mail continua (troca de e-mail no perfil).

## Mudança necessária no Portal de Serviços (para a SEDUR)

1. Serviço 345 (Renovação de Viabilidade): abrir `https://<viabiliza>/portal/entrar/portal-servicos?destino=renovacao&token=<token>` em vez do caminho atual (`isSiteSedur && isSimplifica`). Mesmo padrão de `servicos_sudoeste`, mas numa lista própria do Viabiliza (o Viabiliza é da SEDUR, não da Sudoeste).
2. Cadastrar na Carta os serviços "Consulta de processo de viabilidade" e "Responder convites da viabilidade", com `destino=consulta` e `destino=convites`.
3. Abrir em nova aba ou na mesma aba, **sem iframe** (cookies de sessão do Viabiliza são de primeira parte).
4. Liberar o Viabiliza para chamar `buscar-sessao` no BFF (rede/OKD e, se houver, chave de API).
5. Confirmar que URLs de volta do Viabiliza são aceitas no `state` do SSO.

## Testes

Feature tests com `Http::fake` para o BFF: token válido com conta existente, conta do REGIN com e-mail pendente e com e-mail provisório, primeiro acesso, primeiro acesso com e-mail em uso (nasce com provisório), conta inativa, nível insuficiente (com e sem nível na resposta), token inválido, BFF fora, destino inválido, token não aparece no redirect final nem no log, recurso desligado mantém o login antigo, convidado vai ao SSO com `PREENCHER_TOKEN`, checagem periódica derruba a sessão com token inválido e não derruba com BFF fora, isolamento com a sessão do console. Os testes atuais de GOV.BR, Fortify e renovação continuam passando com o recurso desligado.

## Decisões minhas (o usuário pode trocar)

- A SEDUR muda o Portal de Serviços a partir da seção acima; não abrimos merge request lá.
- Responder convites cai na lista filtrada `status=em_pendencia`, não numa tela nova.
- Novo `AuthType::PortalServicos` para marcar a origem da conta, no lugar de reaproveitar `GovBrAccount`.
- Nível mínimo só é aplicado se a sessão do BFF trouxer o nível.
- Botão Sair encerra só o Viabiliza e volta à Carta de Serviços.
- Sem nome na sessão e no cadastro de contato: nome provisório = CPF formatado.
- Token guardado cifrado só na sessão; checagem a cada 5 minutos.
- Tudo atrás da chave `portal_servicos_login`, desligada por padrão; o login antigo só some com a chave ligada.
- `auth_type = portal_servicos` só para conta criada por essa entrada; contas `local` existentes continuam `local` e contas `ad` nunca mudam. A coluna `users.auth_type` é `string(10)`: migration para aumentar o tamanho (ou usar o valor `portal`, decisão do plano). A checagem periódica se apoia num marcador na sessão (entrou por essa via), não no `auth_type`.
- Servidor com conta AD ou `acessar-gestao` pode entrar no portal como cidadão por essa via, como já acontece no GOV.BR direto; as sessões seguem isoladas por guard e cookie. Teste próprio para isso.
- Validade do token: a primeira tarefa do plano, além do formato de `buscar-sessao`, verifica se o token muda ou expira antes da sessão do Portal de Serviços. Se expirar antes, a checagem periódica vira "só derruba em 401 confirmado duas vezes seguidas" e o plano revê o objetivo 3.

## Pendências que dependem de fora

- Formato real da resposta de `buscar-sessao` (precisa de token de homologação).
- Endereço do Viabiliza em homologação/produção para a SEDUR cadastrar.
