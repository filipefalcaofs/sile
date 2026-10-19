# HU-144 — Fila de trabalho do analista com SLA

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Listar os processos atribuídos ao analista, ordenados pelo prazo `analysis_due_at`, com contadores, e oferecer visão agregada do setor a quem distribui.

## História de Usuário
**Como** usuário com `consultar-solicitacoes` e processos atribuídos a mim, **quero** ver minha fila pelo prazo, **para** trabalhar primeiro o que vence antes.

## Atores e permissões
- `GET processos/fila`: `consultar-solicitacoes`. Não exige `analisar-processos`.
- A lista é só `assigned_user_id` do usuário logado, status `em_analise` ou `em_pendencia`, e exclui situação operacional `para_conclusao`.
- Quem tem `distribuir-processos` recebe, na mesma tela, `visaoSetor` (carga por analista e processos no vermelho). Os demais recebem `visaoSetor` nulo.
- O parâmetro de modo antigo é ignorado: esta rota não reabre a fila do setor. A caixa do setor é a HU-080.

## Fluxo evidenciado
1. A fila aplica os filtros da caixa, pagina e ordena por `analysis_due_at` e id.
2. Contadores no escopo do usuário: em análise com responsável, em pendência e vencendo hoje (`analysis_due_at` até o fim do dia). O contador "aguardando análise" usa o mesmo escopo já filtrado pelo responsável, então processo sem responsável não entra nesta fila.
3. O acesso audita `consulta-fila` com modo `meus`.
4. O prazo exibido é a coluna materializada no encaminhamento e recalculada na distribuição ou na assunção. A redistribuição não recalcula.

## Regras de negócio evidenciadas
- Processo deferido, indeferido ou em outro status terminal não aparece: a fila só tem os dois status de trabalho.
- `para_conclusao` sai desta fila e aparece na aba correspondente da caixa do setor.
- O semáforo de SLA da fila e do escalonamento leem a mesma origem (`analysis_due_at` e início da etapa). Esta tela não dispara notificação.

## Validações
- Paginação 10, 15, 25 ou 50; senão, `ui.cnaes.per_page`.
- Filtros vazios não restringem.

## Critérios de aceite
1. A rota exige `consultar-solicitacoes`.
2. O analista vê somente processos atribuídos a ele, em análise ou em pendência, fora de `para_conclusao`, ordenados pelo prazo.
3. Usuário com `distribuir-processos` vê a visão do setor; os outros não.
4. A consulta é auditada. Nenhum status é alterado por abrir a fila.

## Divergências da redação anterior
- A redação anterior misturava a fila do analista com a caixa do setor e com alertas de vencimento. A fila é somente leitura dos processos do usuário. Alertar vencimento é a HU-093 e não muda esta lista.

## Evidências
- `app/Http/Controllers/Gestao/ProcessoController.php` (`fila`)
- `app/Services/Analise/ProcessoQueryService.php` (`fila`, `escopo`, `STATUS_FILA`, `visaoSetor`)
- `routes/gestao.php`

## Não evidenciado
- Fila do setor inteiro nesta rota.
- Cálculo de SLA diferente do `analysis_due_at` já gravado.
