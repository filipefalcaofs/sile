# HU-148 — Revogar procuração

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O outorgante revoga a própria procuração na hora. A representação aberta na sessão do procurador cai na próxima request do portal. O procurador também pode encerrar a atuação sem revogar o vínculo.

## História de Usuário
**Como** outorgante, **quero** revogar uma procuração que concedi, **para** o procurador deixar de atuar em meu nome imediatamente. **Como** procurador em atuação, **quero** encerrar essa atuação na minha sessão, **para** voltar a agir só em meu nome.

## Atores e permissões
Cidadão autenticado no portal (`auth:web`, `verified`, `lgpd.accepted`, `ResolveRepresentation`).

- Revogação: DELETE `/portal/procuracoes/{procuration}`. `ProcurationPolicy::delete` permite só se `user.id` é `grantor_user_id`.
- Encerrar atuação: DELETE `/portal/representacao`. Não há policy; remove a chave da sessão de quem está logado.

Menu: Serviços > Procurações (`/portal/procuracoes`), o mesmo da HU-147. Não há item separado de revogação.

## Fluxo evidenciado
1. `ProcurationController::destroy` autoriza `delete` na procuração. Quem não é o outorgante recebe a negação do Gate.
2. A revogação preenche `revoked_at` com agora e `revoked_by_user_id` com o usuário logado. Não apaga a linha. A mensagem é "Procuração revogada."
3. `isActive()` passa a ser falso porque `revoked_at` deixou de ser nulo. `scopeActive` também exige `revoked_at` nulo.
4. Na request seguinte do portal, `ResolveRepresentation` vê a procuração inativa, esquece `acting_procuration_id`, limpa `acting_for_user_id` e avisa: "A representação foi encerrada porque a procuração não está mais ativa."
5. `RepresentationController::destroy` apenas esquece `acting_procuration_id`, registra `procuracao` / `representacao-encerrada` e volta com "Representação encerrada." A procuração continua ativa para um novo início de atuação.
6. A lista da HU-147 continua exibindo o registro, com `revoked_at` e `is_active` falso.

## Regras de negócio evidenciadas
- RN-001: somente o outorgante revoga (`ProcurationPolicy::delete`).
- RN-002: revogar não exclui o registro; grava data e autor (`ProcurationController::destroy`).
- RN-003: procuração revogada ou vencida não sustenta representação. O middleware revalida em toda request do grupo (`ResolveRepresentation`).
- RN-004: encerrar a atuação na sessão não revoga a procuração (`RepresentationController::destroy`).
- RN-005: `Procuration` audita atributos fillable via `HasAuditoria`. O fim da atuação tem log explícito no `AuditService`.

## Validações
Não há Form Request no DELETE da procuração nem no DELETE da representação. A autorização da revogação é a policy. O vínculo `{procuration}` é o model binding do Laravel.

## Critérios de aceite
### CA-01 — Outorgante revoga
**Dado** uma procuração cujo `grantor_user_id` é o usuário logado, **quando** envia DELETE `/portal/procuracoes/{id}`, **então** `revoked_at` e `revoked_by_user_id` são preenchidos e a mensagem é "Procuração revogada."

### CA-02 — Procurador não revoga
**Dado** o procurador (ou outro usuário) autenticado, **quando** tenta o DELETE da procuração de que não é outorgante, **então** `ProcurationPolicy::delete` nega e `revoked_at` não é alterado por esse pedido.

### CA-03 — Efeito na sessão
**Dado** o procurador com `acting_procuration_id` apontando para a procuração revogada, **quando** faz uma nova request no grupo do portal, **então** `ResolveRepresentation` remove a atuação e informa que a representação foi encerrada.

### CA-04 — Encerrar sem revogar
**Dado** o procurador em atuação e a procuração ainda ativa, **quando** envia DELETE `/portal/representacao`, **então** a sessão perde `acting_procuration_id`, a atividade `representacao-encerrada` é registrada e `revoked_at` permanece nulo.

## Divergências da redação anterior
- A redação antiga falava em representante legal, análise de viabilidade, GIS e REDESIM. A revogação é um update na procuração entre usuários do portal.
- Não há aprovação de um servidor nem prazo de carência: `revoked_at` vale na hora para `isActive()`.
- Encerrar a representação e revogar a procuração são operações diferentes.

## Evidências
- `app/Http/Controllers/Portal/ProcurationController.php` — `destroy`.
- `app/Policies/ProcurationPolicy.php`.
- `app/Http/Controllers/Portal/RepresentationController.php` — `destroy`.
- `app/Http/Middleware/ResolveRepresentation.php`.
- `app/Models/Procuration.php` — `isActive` e `scopeActive`.
- `routes/portal.php`.

## Não evidenciado
Não há revogação pelo console, revogação em lote nem restauração de uma procuração revogada. Não há notificação por e-mail ao procurador nestes métodos.
