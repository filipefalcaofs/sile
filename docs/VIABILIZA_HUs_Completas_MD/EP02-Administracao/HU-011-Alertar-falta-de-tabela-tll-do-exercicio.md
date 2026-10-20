# HU-011 — Alertar falta de tabela TLL do exercício

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 20/10/2026, com o código-fonte como referência.

## Objetivo
Avisar os gestores, em dezembro e janeiro, quando o exercício corrente ou o seguinte não tem tabela de valores da TLL vigente.

## História de Usuário
**Como** gestor da SEDUR que recebe escalonamentos,
**quero** ser avisado no sistema quando falta a tabela TLL do exercício,
**para que** eu publique os valores da TLL a tempo.

## Atores e permissões
- Rotina agendada `tll:alertar-exercicio`, todo dia às 07:30, sem sobreposição e em um só servidor.
- Recebe o aviso todo usuário cujo perfil tem a permissão `receber-escalonamento-sla`; quem define o perfil é o administrador (HU-006).

## Fluxo evidenciado
1. A rotina roda todo dia às 07:30. Fora de dezembro e janeiro, termina sem avisar.
2. Em dezembro e janeiro, confere se o exercício do ano corrente e o do ano seguinte têm versão vigente da tabela TLL (HU-010).
3. Para cada gestor com a permissão, o sistema cria na central de notificações o aviso «Tabela TLL sem publicação», com os exercícios sem tabela e o link para Valores TLL (`/gestao/tll`).
4. O aviso é só no sistema (sininho). Desde 16/10/2026 não há e-mail.
5. Cada aviso é registrado na trilha de auditoria (`tll-exercicio-faltante`), por gestor e por exercício. O gestor já avisado sobre um exercício não recebe de novo.
6. A rotina só avisa: não grava valor nem publica o rascunho da tabela.

## Regras de negócio evidenciadas
- A janela de aviso é dezembro e janeiro, definida no código.
- O exercício conta como coberto quando existe versão vigente da TLL com o ano como número da versão.
- Se nenhum perfil tem a permissão `receber-escalonamento-sla`, ninguém é avisado e o sistema registra o fato no log.
- A rotina é idempotente: o mesmo gestor não é avisado duas vezes sobre o mesmo exercício.

## Critérios de aceite
### CA-01 — Aviso de exercício sem tabela
**Dado** que é dezembro ou janeiro e não há tabela TLL vigente para o exercício corrente ou o seguinte,
**Quando** a rotina diária roda,
**Então** cada gestor com `receber-escalonamento-sla` recebe na central de notificações o aviso com os exercícios sem tabela e o link para Valores TLL, e o aviso fica na trilha de auditoria.

### CA-02 — Sem repetição
**Dado** que o gestor já foi avisado sobre o exercício,
**Quando** a rotina roda de novo,
**Então** nenhum aviso novo é criado para esse gestor e esse exercício.

### CA-03 — Fora da janela ou com tabela vigente
**Dado** que o mês não é dezembro nem janeiro, ou que os dois exercícios têm tabela vigente,
**Quando** a rotina roda,
**Então** nenhum aviso é criado.

## Evidências
- `app/Console/Commands/AlertarExercicioTllCommand.php` (`tll:alertar-exercicio`) e `routes/console.php` (agendamento diário às 07:30).
- `app/Notifications/TllExercicioFaltanteNotification.php` (canal `database`) e `app/Services/Comunicacao/DestinatariosEscalonamento.php`.
- `tests/Feature/Analise/AlertarExercicioTllCommandTest.php` e `tests/Feature/Comunicacao/AvisosInternosSomenteSininhoTest.php`.

## Não evidenciado
- Alteração da janela de aviso por parâmetro.
- Envio do aviso por e-mail.
- Disparo manual do aviso pela tela.

