# HU-100 — Consultar trilha de auditoria

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Consultar a trilha global de atividades e, na mesma tela, o histórico de acessos.

## História de Usuário
**Como** auditor, **quero** filtrar a trilha de auditoria, **para** fiscalizar quem fez o quê, quando e com qual resultado.

## Atores e permissões
- Servidor do console com `consultar-auditoria`.
- A trilha por um protocolo específico é outra consulta (`RelatorioController::trilha`, permissão `consultar-relatorios`).

## Fluxo evidenciado
1. GET `/gestao/auditoria` renderiza `gestao/auditoria/index`.
2. A fonte padrão é `atividade` (`activity_log` completa). `alteracoes` restringe a diffs. `acessos` lê `access_logs` (login, logout, falha, bloqueio), com usuário, e-mail, IP, evento e canal.
3. Filtros: período, usuário, entidade, `log_name`, evento e resultado. Em acessos valem período, usuário e evento.
4. Paginação no servidor, com a query string preservada.
5. A consulta é auditada como `consulta-trilha`, marcada como dado pessoal.
6. Se a query traz `formato` csv, xlsx ou pdf, o mesmo pedido segue para a exportação.

## Regras de negócio evidenciadas
- Não há materialização nem tabela paralela: a trilha é a `activity_log`.
- Acessos não são unidos por SQL com a atividade; são uma fonte própria.
- Fonte inválida cai em atividades, sem erro fabricado.

## Validações
- Datas só entram no formato reconhecido pelo serviço de consulta.
- `per_page` fora de 10, 20, 50 e 100 usa o parâmetro `ui.auditoria.per_page`.

## Critérios de aceite
- Com permissão, a lista padrão mostra atividades recentes e a consulta fica na trilha.
- `fonte=acessos` lista `access_logs`, não atividades.
- Sem `consultar-auditoria`, o acesso é negado.

## Divergências da redação anterior
O texto pedia “trilha completa do processo”. Esta tela é a trilha global. A trilha consolidada de um protocolo está na HU de relatório por processo.

## Evidências
- `app/Http/Controllers/Gestao/AuditoriaController.php`
- `app/Services/Auditoria/AuditTrailQueryService.php` (`filtered`, `acessos`)
- `routes/gestao.php` (grupo `auditoria`)

## Não evidenciado
- União numa única linha do tempo de atividade e acesso.
- Trilha de um protocolo nesta rota (isso é o relatório `relatorios/trilha`).
