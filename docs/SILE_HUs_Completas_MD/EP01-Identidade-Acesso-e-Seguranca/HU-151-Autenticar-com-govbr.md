# HU-151 — Autenticar com GOV.BR

> **Status: Aprovada (2026-06-12)** — pedido direto do produto; reverte o adiamento registrado na Fase 1 ("login gov.br — não consta nas HUs"). Convive com o login local. Design: `docs/superpowers/specs/2026-06-12-autenticacao-govbr-design.md`.

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Objetivo
Permitir que o cidadão acesse o portal com a conta GOV.BR (Login Único do governo federal), criando ou vinculando a conta local automaticamente pelo CPF.

## História de Usuário
**Como** cidadão com conta GOV.BR,
**quero** entrar no portal com o Login Único,
**para** acessar os serviços sem criar mais uma senha.

## Regras de Negócio
- RN-001: Credenciais (`client_id`/`client_secret`), URLs e nível mínimo administráveis por interface (HU-014); sensíveis criptografados; efeito sem deploy.
- RN-002: Auditoria transversal — login, criação de conta e bloqueios registrados (usuário, data/hora, origem, ação, resultado).
- RN-003: Vínculo de identidade exclusivamente pelo CPF (`sub` do `id_token`); e-mail nunca vincula sozinho.
- RN-004: Nível mínimo de confiabilidade parametrizável (`auth.govbr.minimum_level`, default bronze); conta abaixo do mínimo não autentica e recebe orientação.
- RN-005: Fluxo conforme roteiro oficial — Authorization Code + PKCE S256, `state`, `nonce` e validação de assinatura do `id_token` contra o JWK do provedor.
- RN-006: Funcionalidade nasce atrás do toggle `features.govbr_login` (default desligado); sem credenciamento da SEDUR a integração permanece explicitamente bloqueada — nunca simulada. Degradação sempre comunicada.
- RN-007: Conta criada via GOV.BR recebe papel `cidadao`, senha aleatória e `email_verified_at` somente quando o GOV.BR declarar o e-mail verificado.
- RN-008: Conta local inativada não autentica via GOV.BR (paridade com o login local).
- RN-009: Logout permanece local (sem Single Logout do SSO nesta versão).

## Critérios de Aceite — BDD

### CA-01 — Login de conta existente vinculada por CPF
**Dado** um usuário local cujo CPF corresponde ao `sub` retornado pelo GOV.BR,
**Quando** concluir a autenticação no Login Único,
**Então** a sessão é criada, o vínculo GOV.BR é registrado/atualizado e ele é redirecionado ao painel do portal.

### CA-02 — Primeiro acesso cria conta real
**Dado** que não existe usuário local com o CPF retornado,
**Quando** concluir a autenticação no Login Único,
**Então** uma conta é criada com nome/e-mail/CPF do GOV.BR, papel `cidadao`, vínculo registrado, e o aceite do termo LGPD é exigido no primeiro acesso.

### CA-03 — Toggle desligado degrada comunicando
**Dado** `features.govbr_login` desligado ou credenciais não configuradas,
**Quando** o cidadão acessar a tela de login ou as rotas GOV.BR,
**Então** o botão não é exibido e as rotas redirecionam com aviso de indisponibilidade.

### CA-04 — Nível de confiabilidade insuficiente
**Dado** `auth.govbr.minimum_level` = prata e conta GOV.BR nível bronze,
**Quando** concluir a autenticação,
**Então** o acesso é negado com mensagem orientando a elevar o nível, sem criar sessão.

### CA-05 — Conta inativada bloqueada
**Dado** um usuário local inativado cujo CPF corresponde ao retorno do GOV.BR,
**Quando** concluir a autenticação,
**Então** o acesso é negado com a mesma mensagem do login local e o evento `inativada` é registrado em `access_logs`.

### CA-06 — Auditoria obrigatória
**Dado** qualquer autenticação via GOV.BR (sucesso ou bloqueio),
**Quando** o fluxo for concluído,
**Então** ficam registrados o acesso (canal portal, método govbr) e, quando houver criação de conta, a origem `govbr`.

### CA-07 — Conflito de e-mail nunca vincula automaticamente
**Dado** que o e-mail retornado pelo GOV.BR já pertence a usuário local com outro CPF,
**Quando** concluir a autenticação,
**Então** o acesso é negado com mensagem clara e nenhum vínculo é criado.

### CA-08 — Falha segura de protocolo
**Dado** `state` ou `nonce` divergente do esperado na sessão,
**Quando** o callback for processado,
**Então** nenhuma sessão é criada e o erro é comunicado sem detalhes técnicos sensíveis.

## Dependências
- HU-001/HU-002 (identidade local, CPF único), HU-014 (parâmetros), infraestrutura de auditoria (RN-002 transversal).
- Externa: credenciamento da SEDUR no Login Único (Termo de Adesão — Secretaria de Governo Digital) para validação em staging/produção.

## Prioridade
Alta
