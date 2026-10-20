# HU-009 — Manter feriados

## Épica
**EP02 — Administração**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Manter o calendário de feriados que o cálculo de dias úteis desconta.

## História de Usuário
**Como** administrador com `manter-parametros`,
**quero** cadastrar, corrigir e inativar feriados,
**para** o prazo em dias úteis não conte essas datas.

## Atores e permissões
- A tela exige `manter-parametros`. Não há permissão própria de feriado.
- Menu Configuração, item Feriados (`/gestao/feriados`).

## Fluxo evidenciado
1. A lista busca pelo nome, filtra situação e pagina em 10, 15, 25 ou 50 (padrão 15), da data mais recente para a mais antiga.
2. Cada item traz data, nome, se repete todo ano e se está ativo.
3. Incluir e editar gravam data, nome, recorrência anual e situação. Feriado novo nasce ativo e não recorrente quando esses campos não vêm no envio.
4. A ativação inverte `active`. Não há exclusão. Feriado inativo sai do cálculo de dias úteis e o registro permanece.
5. A auditoria de criação e atualização é a do `HasAuditoria` do model.

## Regras de negócio evidenciadas
- A data é única. Não há dois feriados na mesma data.
- Inativar preserva o histórico. O controller não oferece `destroy`.
- O `HolidayController` declara que o `BusinessDeadlineCalculator` desconta o feriado ativo na duração em tempo útil.

## Validações
- `date` obrigatória, data válida, única em `holidays.date`. Na edição, ignora o próprio registro. Antes da validação a data é normalizada para o início do dia (`Y-m-d H:i:s`).
- `name` obrigatório, até 255 caracteres.
- `recurring_annually` booleano.
- `active` booleano obrigatório.

## Critérios de aceite
### CA-01 — Data única
**Dado** um feriado já gravado numa data,
**Quando** o administrador tenta cadastrar outro na mesma data,
**Então** a validação recusa a data.

### CA-02 — Recorrência
**Dado** um administrador com `manter-parametros`,
**Quando** cadastra o feriado com recorrência anual,
**Então** o registro guarda `recurring_annually` verdadeiro.

### CA-03 — Inativar sem apagar
**Dado** um feriado ativo,
**Quando** o administrador aciona a ativação,
**Então** o feriado fica inativo e continua consultável na lista filtrada por inativos.

## Divergências da redação anterior
A redação anterior pedia feriados administráveis, auditoria e integração com a contagem de prazo, em texto curto e genérico. O código confirma o CRUD com inativação no lugar da exclusão, data única e flag de recorrência anual. A regra de dias úteis versus corridos não é um campo desta tela; o que ela administra é o calendário consumido pelo calculador.

## Evidências
- `routes/gestao.php`: `feriados.index`, `store`, `update`, `ativacao.update` com `manter-parametros`.
- `app/Http/Controllers/Gestao/HolidayController.php`.
- `app/Http/Requests/Gestao/HolidayRequest.php`.
- `resources/js/navigation/gestao-nav.ts`: Feriados em Configuração.

## Não evidenciado
- Exclusão física do feriado.
- Importação de calendário nacional ou municipal por arquivo.
- Escolha, nesta tela, entre prazo em dias úteis e prazo em dias corridos.
