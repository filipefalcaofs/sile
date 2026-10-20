# HU-096 — Consultar histórico de alterações

## Épica
**EP12 — Auditoria e Compliance**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar somente os registros da trilha que trazem diferença de atributos (`attribute_changes`).

## História de Usuário
**Como** auditor com permissão de consulta, **quero** ver o histórico de alterações de dados, **para** conferir o que mudou, sem misturar com acessos e eventos sem diff.

## Atores e permissões
- Servidor autenticado no console (`auth:gestao`), com `acessar-gestao`, termo LGPD aceito e permissão `consultar-auditoria`.
- Sem a permissão, a rota responde 403; o bloqueio é auditado no ponto único da aplicação.

## Fluxo evidenciado
1. O auditor abre `/gestao/auditoria` com `fonte=alteracoes`.
2. `AuditoriaController` rejeita fonte desconhecida e, nesse caso, usa a trilha de atividades. `alteracoes` é aceita.
3. `AuditTrailQueryService::apenasAlteracoes` aplica os mesmos filtros da trilha e exige `attribute_changes` preenchido.
4. A lista é paginada no servidor e mapeada por `ActivityResource`.
5. A própria consulta é auditada (`consulta-trilha`, dado pessoal).

## Regras de negócio evidenciadas
- Só entra registro que já tenha diff gravado por `HasAuditoria`. Evento sem alteração de atributo não aparece nesta fonte.
- Filtros vazios são ignorados. Datas inválidas não entram na query.
- A ordenação é da alteração mais recente para a mais antiga (`id` decrescente).

## Validações
- Filtros aceitos na query: período, usuário (id ou nome), tipo e id da entidade, `log_name`, evento e resultado.
- Página: 10, 20, 50 ou 100; fora disso vale `ui.auditoria.per_page` (padrão 20).

## Critérios de aceite
- Com `fonte=alteracoes`, a lista só contém atividades com `attribute_changes` não nulo.
- Os filtros de período, usuário e entidade restringem esse conjunto.
- A consulta gera meta-auditoria com a fonte e os filtros preenchidos.

## Divergências da redação anterior
O texto antigo prometia histórico genérico de “dados e regras” com fluxos de integração. O código é um recorte da `activity_log`, não uma tela separada de versionamento de regras.

## Evidências
- `app/Http/Controllers/Gestao/AuditoriaController.php` (`fonte`, `paginar`)
- `app/Services/Auditoria/AuditTrailQueryService.php` (`apenasAlteracoes`)
- `routes/gestao.php` (`gestao.auditoria.index`)

## Não evidenciado
- Diff visual campo a campo além do que `ActivityResource` já expõe.
- Histórico de regra da LOUOS fora do `attribute_changes` da trilha.
