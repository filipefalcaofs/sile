# HU-146 — Consultar histórico de acessos

## Épica
**EP01 — Identidade, Acesso e Segurança**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
O cidadão consulta os acessos da própria conta. Quem tem permissão no console consulta o histórico de outra conta e pode exportar o conjunto. A consulta administrativa fica auditada.

## História de Usuário
**Como** cidadão, **quero** ver meus acessos, falhas e bloqueios, **para** reconhecer tentativas na minha conta. **Como** administrador autorizado, **quero** consultar o histórico de outra conta, **para** apurar o uso sem depender do próprio usuário.

## Atores e permissões
- Portal: usuário com `auth:web`, `verified` e `lgpd.accepted` (e `ResolveRepresentation`). Menu Minha conta > Histórico de acessos (`/portal/acessos`). Não há permissão Spatie adicional. O controller usa o usuário logado; não recebe id de outra conta.
- Console: `auth:gestao`, `acessar-gestao`, `lgpd.accepted` e, na rota, `permission:consultar-acessos-de-qualquer-conta`. GET `/gestao/acessos/{user}`. Não há item em `gestao-nav.ts`. A listagem Administração > Usuários (`manter-usuarios`) tem a ação "Acessos" para essa URL. Quem não tem `consultar-acessos-de-qualquer-conta` é barrado na rota, mesmo que enxergue o link.

No seeder, `consultar-acessos-de-qualquer-conta` é concedida ao papel `administrador`.

## Fluxo evidenciado
1. Os eventos gravados em `access_logs` incluem, conforme os listeners e o login do console: `login`, `falha`, `bloqueio`, `inativada`, `ad-indisponivel` e `ad-sincronizacao-falhou`. Canal `portal` ou `gestao`. Origem `ad` ou `local` quando o login do console define `auth_origin`.
2. No portal, `AccessHistoryController` lista registros em que `user_id` é o usuário ou `email` é o e-mail dele (para falhas anteriores ao vínculo do id). Ordena por `created_at` decrescente e pagina com `ui.access_history.per_page` (padrão 15). Cada item mostra `id`, `event`, `ip_address`, `channel` e `created_at`. O user agent não vai para a tela.
3. No console, a consulta interativa faz o mesmo filtro para o `{user}` da rota e ainda envia nome e e-mail do alvo. Antes de renderizar, `AuditService::log` registra `acessos` / `consulta-acessos` com `target_user_id` e `personalData: true`.
4. Se a query `formato` for `csv`, `xlsx` ou `pdf`, o console não renderiza a tela: `ReportExporter` exporta `AcessosUsuarioReportSource` com o filtro do usuário e audita a exportação. O portal não tem esse parâmetro.

## Regras de negócio evidenciadas
- RN-001: o portal só lê a própria conta, por id ou pelo e-mail gravado no log (`Portal\AccessHistoryController`).
- RN-002: a consulta de terceiro exige `consultar-acessos-de-qualquer-conta` (`routes/gestao.php`).
- RN-003: consultar o histórico de outra conta é dado pessoal e gera auditoria explícita (`Gestao\AccessHistoryController`).
- RN-004: a página usa o parâmetro `ui.access_history.per_page`.
- Não há, nestes controllers, filtro por período, canal ou tipo de evento.

## Validações
Não há Form Request. No console, `formato` só muda o fluxo quando o valor em minúsculas é `csv`, `xlsx` ou `pdf`. Qualquer outro valor cai na listagem.

## Critérios de aceite
### CA-01 — Cidadão vê os próprios registros
**Dado** um cidadão autenticado com logs de `login` e de `falha` no e-mail dele, **quando** abre `/portal/acessos`, **então** a lista pagina esses eventos com IP, canal e data, sem escolher outro usuário.

### CA-02 — Sem permissão no console
**Dado** um servidor sem `consultar-acessos-de-qualquer-conta`, **quando** abre `/gestao/acessos/{id}`, **então** o middleware de permissão impede a consulta.

### CA-03 — Consulta administrativa auditada
**Dado** um administrador com a permissão, **quando** abre o histórico de outra conta sem `formato` de exportação, **então** vê os logs daquele usuário ou e-mail e a atividade `consulta-acessos` é registrada com `personalData`.

### CA-04 — Exportação
**Dado** a mesma permissão e `formato` igual a `csv`, `xlsx` ou `pdf`, **quando** acessa a rota do usuário, **então** a resposta vem de `ReportExporter` com `AcessosUsuarioReportSource`, e não da página Inertia.

## Divergências da redação anterior
- O texto antigo misturava a consulta com trilha de processo, GIS e REDESIM. Aqui a fonte é `access_logs`.
- O cidadão não escolhe outra conta. A exportação existe só no console e só nos três formatos acima.
- A tela administrativa não está no menu lateral; o atalho está na lista de usuários.

## Evidências
- `app/Http/Controllers/Portal/AccessHistoryController.php`.
- `app/Http/Controllers/Gestao/AccessHistoryController.php`.
- `app/Listeners/RecordSuccessfulLogin.php`, `RecordFailedLogin.php`, `RecordLockout.php`.
- `routes/portal.php` e `routes/gestao.php`.
- `resources/js/navigation/portal-nav.ts`.
- `resources/js/pages/gestao/usuarios/index.tsx` — ação "Acessos".
- `database/seeders/RolesAndPermissionsSeeder.php` — `consultar-acessos-de-qualquer-conta`.

## Não evidenciado
Não há histórico de logout nestes listeners. Não há edição nem exclusão manual de `access_logs` por estas rotas. A poda de retenção não faz parte desta consulta.
