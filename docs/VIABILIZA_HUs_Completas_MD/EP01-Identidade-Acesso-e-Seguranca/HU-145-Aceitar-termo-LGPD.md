# HU-145 — Aceitar termo LGPD

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Antes de usar o portal ou o console, a pessoa aceita a versão vigente do termo LGPD. O aceite guarda versão, IP, navegador e data. Sem termo publicado, o bloqueio não se aplica.

## História de Usuário
**Como** usuário autenticado que ainda não aceitou o termo vigente, **quero** ler e aceitar o texto publicado, **para** seguir no ambiente em que entrei.

## Atores e permissões
Usuário autenticado no ambiente. Não há permissão Spatie específica do aceite.

- Portal: `auth:web` e `verified`, rotas `portal.termo-lgpd.show` e `portal.termo-lgpd.accept`, fora do middleware `lgpd.accepted`.
- Console: `auth:gestao`, rotas `gestao.termo-lgpd.show` e `gestao.termo-lgpd.accept`, também fora do gate, para não haver laço de redirecionamento.

O middleware `lgpd.accepted` (`EnsureLgpdTermAccepted`) protege o restante de cada ambiente e manda para o termo daquele guard. Não há item de menu: o acesso é o redirecionamento.

## Fluxo evidenciado
1. `EnsureLgpdTermAccepted` escolhe o guard `gestao` se o caminho é `gestao` ou `gestao/*`; nos demais, usa `web`.
2. `LegalTerm::current('lgpd')` busca o termo do tipo `lgpd` com `published_at` preenchido e não futuro, o de maior `version`. Se não há termo, ou se não há usuário, a request segue.
3. Se há termo vigente e `User::hasAcceptedTerm` é falso para aquele `legal_term_id`, o middleware redireciona a `gestao.termo-lgpd.show` ou `portal.termo-lgpd.show`.
4. A tela mostra `id`, `version`, `title` e `content`. Se não há termo ou o usuário já aceitou, o portal vai ao destino pretendido ou ao painel; o console vai a `gestao.dashboard`.
5. O POST exige `accepted` com a regra `accepted` (rótulo "aceite"). `LegalTermAcceptance::firstOrCreate` grava `user_id`, `legal_term_id`, `ip_address`, `user_agent` (até 500 caracteres) e `accepted_at`. Aceite repetido da mesma versão não duplica a linha.
6. A mensagem de sucesso é "Termo aceito com sucesso." O portal segue o destino pretendido ou o painel; o console segue o painel da gestão.

## Regras de negócio evidenciadas
- RN-001: o gate usa só a versão vigente (`LegalTerm::current`). Aceite de versão anterior não satisfaz `hasAcceptedTerm` de outra versão, porque a busca é por `legal_term_id`.
- RN-002: sem termo publicado, o middleware não redireciona (`EnsureLgpdTermAccepted`).
- RN-003: cada ambiente aceita no próprio guard e na própria rota (`LgpdTermController` e `TermoLgpdController`).
- RN-004: o consentimento persiste IP, user agent e data (`LegalTermAcceptance::firstOrCreate` nos dois controllers).

## Validações
Validação inline nos dois `accept`: `accepted` => `accepted`, atributo "aceite". Não há Form Request.

## Critérios de aceite
### CA-01 — Portal bloqueado até o aceite
**Dado** um cidadão autenticado e verificado e um termo `lgpd` vigente ainda não aceito por ele, **quando** abre uma rota do portal com `lgpd.accepted`, **então** é redirecionado a `/portal/termo-lgpd`.

### CA-02 — Aceite libera o console
**Dado** um servidor autenticado no guard `gestao` e o termo vigente pendente, **quando** envia o aceite marcado, **então** existe `legal_term_acceptances` daquela versão com IP e data, e o destino é `gestao.dashboard`.

### CA-03 — Sem termo publicado
**Dado** que `LegalTerm::current('lgpd')` não retorna registro, **quando** o usuário autenticado navega, **então** `EnsureLgpdTermAccepted` não redireciona ao termo.

### CA-04 — Aceite já registrado
**Dado** que o usuário já tem aceite da versão vigente, **quando** abre a rota do termo, **então** não vê o formulário de novo: o portal segue ao painel (ou destino pretendido) e o console segue ao painel da gestão.

## Divergências da redação anterior
- O texto antigo colocava o aceite no fluxo genérico de viabilidade, com GIS, REDESIM, CNAE e LOUOS. O código só lê e grava o termo legal.
- Há duas telas e dois controllers, um por guard. Não é uma rota compartilhada.
- A ausência de termo publicado desarma o gate em vez de impedir o uso.

## Evidências
- `app/Http/Middleware/EnsureLgpdTermAccepted.php` — alias `lgpd.accepted` em `bootstrap/app.php`.
- `app/Http/Controllers/Portal/LgpdTermController.php`.
- `app/Http/Controllers/Gestao/TermoLgpdController.php`.
- `app/Models/LegalTerm.php` — `current`.
- `app/Models/User.php` — `hasAcceptedTerm`.
- `routes/portal.php` e `routes/gestao.php`.

## Não evidenciado
Não há recusa explícita do termo com registro de negativa. Não há, nestes controllers, download em PDF nem assinatura digital. A publicação de novas versões do texto fica em `LegalTermController` (`/gestao/termos-legais`, permissão `manter-parametros`), fora do aceite.
