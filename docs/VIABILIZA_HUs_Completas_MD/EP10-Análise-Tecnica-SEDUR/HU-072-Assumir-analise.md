# HU-072 — Assumir análise

## Épica
**EP10 — Análise Técnica SEDUR**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Permitir que o analista pegue para si um processo da caixa do setor e passe a ser o responsável técnico.

## História de Usuário
**Como** analista com `analisar-processos`, **quero** assumir um processo da caixa do meu setor, **para** registrar que a análise está comigo.

## Atores e permissões
- Rota `POST caixa-setor/{processo}/assumir`: middleware `analisar-processos`.
- O perfil `apoio` tem `distribuir-processos` e não tem `analisar-processos`: vê a caixa se a rota de listagem permitir, mas não assume.
- No seeder, Analista Servidor, Analista Terceirizado e as chefias de análise têm `analisar-processos`. O administrador, no seeder, não tem essa permissão.

## Fluxo evidenciado
1. O analista aciona assumir na caixa.
2. Se o processo não está em `para_conclusao` e já tem outro responsável, o serviço lança `DistribuicaoException` e nada é gravado.
3. Em `para_conclusao`, qualquer analista do setor pode pegar o processo, mesmo que já haja responsável.
4. A atribuição usa o mesmo efeito da distribuição, com o próprio analista como ator e evento de auditoria `assumir`. O SLA da etapa é recalculado nesse caminho de atribuição.

## Regras de negócio evidenciadas
- Processo já atribuído a outra pessoa não pode ser assumido, exceto quando a situação operacional é `para_conclusao`.
- Assumir não é distribuir: não exige `distribuir-processos` e não usa a lista de destinatários do gestor.

## Validações
- Sem `analisar-processos`, a rota responde 403.
- Processo já atribuído a outro analista, fora de `para_conclusao`: exceção de domínio, sem gravação.

## Critérios de aceite
1. Analista do setor assume processo sem responsável e passa a constar como atribuído, com auditoria `assumir`.
2. Segundo analista não assume processo já atribuído, salvo em `para_conclusao`.
3. Usuário só com `distribuir-processos` não conclui a assunção.

## Divergências da redação anterior
- A redação anterior tratava assumir como capacidade genérica de qualquer analista do setor, inclusive sobre processo já distribuído. O código bloqueia a assunção quando já existe outro responsável, com a exceção explícita de `para_conclusao`.

## Evidências
- `app/Http/Controllers/Gestao/CaixaSetorController.php` (`assumir`)
- `app/Services/Analise/DistribuicaoService.php` (`assumir`)
- `routes/gestao.php`

## Não evidenciado
- Assunção por apoio ou por administrador sem a permissão `analisar-processos`.
