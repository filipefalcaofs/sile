# HU-181 — Consultar central de notificações

## Épica
**EP11 — Pendências e Comunicação**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 20/10/2026, com o código-fonte como referência.

## Objetivo
Reunir em uma central os avisos do sistema ao usuário e permitir marcá-los como lidos.

## História de Usuário
**Como** usuário do portal ou da gestão,
**quero** ver meus avisos e marcá-los como lidos,
**para que** eu acompanhe o que o sistema me comunica e limpe o sininho.

## Atores e permissões
- Usuário autenticado no portal (`/portal/notificacoes`) ou na gestão (`/gestao/notificacoes`).
- Cada usuário vê e marca só os próprios avisos. Aviso de outro usuário responde como não encontrado.

## Fluxo evidenciado
1. O sininho no topo mostra a quantidade de avisos não lidos.
2. A central lista os avisos do usuário, dos mais recentes para os mais antigos, separados em não lidos e lidos, com título, resumo, data e link. A página tem 15 avisos por padrão (parâmetro `ui.notificacoes.per_page`).
3. «Marcar como lida» grava a data de leitura do aviso escolhido.
4. «Marcar todas como lidas» grava a leitura de todos os avisos não lidos do usuário e zera o sininho.
5. Sem avisos, a tela diz «Nenhuma notificação».

## Regras de negócio evidenciadas
- Os avisos nascem de outros eventos do sistema: resultado da solicitação, pendência, vencimento e alertas internos, como o da tabela TLL.
- A leitura vale só para o dono do aviso: um identificador de outro usuário responde 404, sem revelar que o aviso existe.
- Aviso lido continua na lista, na aba dos lidos.

## Validações
- Aviso: precisa pertencer ao usuário autenticado; caso contrário a resposta é 404.

## Critérios de aceite
### CA-01 — Lista separada por leitura
**Dado** há um usuário com avisos lidos e não lidos,
**Quando** abre a central de notificações,
**Então** vê os não lidos e os lidos em seções separadas.

### CA-02 — Marcar um aviso como lido
**Dado** há um usuário com um aviso não lido,
**Quando** clica em Marcar como lida,
**Então** o aviso passa a lido e o sininho diminui em um.

### CA-03 — Marcar todos como lidos
**Dado** há um usuário com vários avisos não lidos,
**Quando** clica em Marcar todas como lidas,
**Então** todos ficam lidos e o sininho zera.

### CA-04 — Aviso de outro usuário
**Dado** existe o identificador de um aviso de outro usuário,
**Quando** o usuário tenta marcá-lo como lido,
**Então** a resposta é 404 e o aviso não muda.

## Evidências
- `routes/portal.php` e `routes/gestao.php`: `notificacoes.index`, `notificacoes.ler` e `notificacoes.ler-todas`.
- `app/Http/Controllers/NotificationCenterController.php` e `app/Http/Resources/NotificationResource.php`.
- `resources/js/components/notificacoes/notification-center.tsx`, `resources/js/pages/portal/notificacoes/index.tsx` e `resources/js/pages/gestao/notificacoes/index.tsx`.
- `tests/Feature/Comunicacao/NotificationCenterTest.php`.

## Não evidenciado
- Exclusão de avisos.
- Preferências de quais avisos receber.
- Aviso por e-mail ou push a partir da central.

