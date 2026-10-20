# HU-121 — Solicitações por CNAE

## Épica
**EP15 — Relatórios e Indicadores**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar os CNAEs com mais solicitações protocoladas no recorte, contando processos distintos.

## História de Usuário
**Como** gestor, **quero** ver o ranking de CNAE, **para** entender o perfil das atividades pedidas.

## Atores e permissões
- `consultar-relatorios` em `/gestao/relatorios/indicadores`.

## Fluxo evidenciado
1. `porCnae` junta a pivô de CNAEs do processo e a tabela `cnaes`.
2. Conta processos distintos por código, do maior total para o menor, com limite padrão de 10.
3. Empate de total desempata pelo código.
4. A exportação `?formato=` é a lista de solicitações do recorte, não só o ranking.

## Regras de negócio evidenciadas
- Um processo com mais de um CNAE conta uma vez em cada código, não várias vezes no mesmo código.
- Só entra solicitação protocolada que passe nos filtros.

## Validações
- Filtro `cnae` é texto opcional no request de relatório.
- Período invertido zera a série.

## Critérios de aceite
- A resposta traz código e total, no máximo o limite, em ordem decrescente de volume.
- Processo sem CNAE não gera código vazio.
- A consulta é auditada.

## Divergências da redação anterior
O texto não limitava o ranking nem dizia que a contagem é de processos distintos.

## Evidências
- `app/Services/Relatorios/IndicadoresViabilidadeService.php` (`porCnae`)
- `app/Http/Controllers/Gestao/RelatorioController.php` (`indicadores`)

## Não evidenciado
- Ranking completo sem corte, nesta chamada padrão.
