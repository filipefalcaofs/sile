# HU-147 — Vincular procurador

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O cidadão autoriza outra conta já cadastrada, localizada pelo e-mail, a atuar em seu nome. O procurador inicia essa atuação a partir de uma procuração ativa. A lista mostra o que ele outorgou e o que recebeu.

## História de Usuário
**Como** cidadão autenticado no portal, **quero** vincular um procurador pelo e-mail da conta dele e, quando eu for o procurador, **quero** passar a atuar em nome de quem me outorgou, **para** acompanhar os serviços do portal nessa condição.

## Atores e permissões
Cidadão autenticado no portal: `auth:web`, `verified`, `lgpd.accepted` e `ResolveRepresentation`.

Rotas: GET/POST `/portal/procuracoes` e POST `/portal/representacao`. Menu Serviços > Procurações (`/portal/procuracoes`). O card "Procurações" também está em `PORTAL_ACOES`.

A permissão `gerenciar-procuracoes-proprias` é dada ao papel `cidadao` no `RolesAndPermissionsSeeder`, mas estas rotas não consultam essa permissão. A revogação, na história HU-148, usa `ProcurationPolicy::delete`.

## Fluxo evidenciado
1. `ProcurationController::index` lista procurações em que o usuário é outorgante (`granted`) e em que é procurador (`received`), com nome e e-mail da outra parte, início, validade, revogação e `isActive()`. A prop `procuracoesEnabled` é enviada como `true`.
2. `store` localiza o procurador pelo e-mail validado e cria a procuração com `grantor_user_id` do usuário logado, `starts_at` agora e `expires_at` opcional. A mensagem é "Procurador vinculado com sucesso."
3. O `store` não lê o parâmetro `features.procuracoes`. A migration `2026_09_22_224506_remove_retired_parameters_from_catalog` remove essa chave do catálogo. O comentário do método que fala em bloqueio pelo toggle não corresponde ao corpo do método.
4. Para atuar em nome de alguém, `RepresentationController::store` exige `procuration_id`. A procuração precisa estar em `scopeActive`, pertencer ao procurador logado. A sessão guarda `acting_procuration_id`. O contexto recebe `acting_for_user_id`. A auditoria registra `procuracao` / `representacao-iniciada`. O destino é o painel, com a mensagem "Você está atuando em nome de {nome}."
5. `ResolveRepresentation`, em toda request desse grupo, confere se a procuração da sessão ainda existe, se o usuário ainda é o procurador e se `isActive()` continua verdadeiro. Se não, remove a sessão, avisa que a representação foi encerrada e limpa o contexto.
6. Procuração ativa: não revogada, `starts_at` já alcançado e `expires_at` nulo ou futuro (`Procuration::isActive` / `scopeActive`).

## Regras de negócio evidenciadas
- RN-001: o procurador precisa ser uma conta existente, encontrada por e-mail (`StoreProcurationRequest`).
- RN-002: não é permitido outorgar para si mesmo (`StoreProcurationRequest::after`).
- RN-003: não pode haver outra procuração ativa do mesmo outorgante para o mesmo procurador.
- RN-004: a validade, quando informada, tem de ser uma data futura. Sem validade, a procuração não expira por data.
- RN-005: só procuração ativa recebida pelo usuário logado inicia a representação (`RepresentationController::store`).
- RN-006: o model `Procuration` usa `HasAuditoria` nos atributos fillable. O início da atuação gera log explícito em `AuditService`.

## Validações
`StoreProcurationRequest`:

- `attorney_email`: obrigatório, e-mail, `exists:users,email`. Mensagem de inexistência: "Não encontramos uma conta com este e-mail. Oriente o procurador a se cadastrar no Viabiliza primeiro."
- `expires_at`: opcional, data, `after:now`. Mensagem: "A validade deve ser uma data futura."
- Depois das regras: auto-outorga ("Você não pode outorgar procuração para si mesmo.") e duplicidade ("Já existe uma procuração ativa para este procurador.").

Representação: `procuration_id` obrigatório, inteiro. Se a procuração não está ativa para aquele procurador: "Esta procuração não está ativa."

## Critérios de aceite
### CA-01 — Vínculo por e-mail
**Dado** um cidadão autenticado e outra conta com e-mail cadastrado, **quando** envia POST `/portal/procuracoes` sem validade ou com data futura, **então** a procuração é criada com início agora e a mensagem é "Procurador vinculado com sucesso."

### CA-02 — E-mail sem conta
**Dado** um e-mail que não existe em `users`, **quando** tenta vincular, **então** a validação recusa com a orientação para o procurador se cadastrar antes.

### CA-03 — Auto-outorga e duplicidade
**Dado** o próprio e-mail, ou um procurador que já tem procuração ativa daquele outorgante, **quando** envia o formulário, **então** o vínculo não é criado e a mensagem correspondente de `after()` é exibida.

### CA-04 — Atuar em nome do outorgante
**Dado** uma procuração ativa recebida pelo usuário, **quando** envia POST `/portal/representacao` com o id, **então** a sessão guarda `acting_procuration_id`, a atividade `representacao-iniciada` é registrada e o painel informa em nome de quem ele está.

## Divergências da redação anterior
- O ator da redação antiga era "representante legal" num fluxo de LOUOS, GIS e REDESIM. O código liga duas contas do portal por e-mail, sem documento, sem empresa e sem cartório.
- O interruptor `features.procuracoes` não é consultado no `store` atual; a tela recebe `procuracoesEnabled: true` fixo.
- A permissão `gerenciar-procuracoes-proprias` não protege a rota.

## Evidências
- `routes/portal.php` — `procuracoes` e `representacao`.
- `app/Http/Controllers/Portal/ProcurationController.php` — `index` e `store`.
- `app/Http/Controllers/Portal/RepresentationController.php` — `store`.
- `app/Http/Requests/StoreProcurationRequest.php`.
- `app/Models/Procuration.php` — `scopeActive` e `isActive`.
- `app/Http/Middleware/ResolveRepresentation.php`.
- `resources/js/navigation/portal-nav.ts`.

## Não evidenciado
Não há upload de procuração, consulta à Receita ou vínculo por CPF/CNPJ. Não há aprovação do procurador antes de a outorga valer. Não há tela de procurações no console.
