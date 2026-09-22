# Distribuição pelo Apoio na caixa do setor — Design

Data: 2026-09-22
Status: aprovado (aguardando revisão da spec escrita)

## Contexto

O fluxo **Motor → Caixa do setor → Apoio → Caixa da analista** já existe no backend:

- O fluxo expresso (`FluxoExpressoService::encaminharAnalise`) e o envio manual (`EnviarParaAnaliseService`) depositam o processo na caixa do setor de triagem (`sector_id` preenchido, `assigned_user_id` nulo, `analysis_stage = distribuicao`, `analysis_status = para_distribuir`). **Não há distribuição automática para analista** — decisão de negócio mantida.
- A caixa do setor (`/gestao/caixa-setor`, `CaixaSetorController`) é acessível ao perfil Apoio (permissão `distribuir-processos`) e ao gestor.
- `DistribuicaoService::distribuirLote` já aceita lote (`request_ids[]`), isola falhas e audita por processo.
- A caixa da analista (fila `modo=meus`) e a ficha de análise já funcionam.
- O E2E `tests/Feature/Analise/FluxoApoioTramitacaoTest.php` cobre o fluxo completo.

## Gap a implementar

1. A tela da caixa do setor distribui **um processo por vez** — sem checkboxes e sem ação em lote (o backend já aceita lote).
2. Não há separação visual entre "o que precisa ser distribuído" e "o que já foi distribuído".
3. Não há **redistribuição** (troca de analista responsável) com regras claras.

## Decisões de negócio (validadas com o usuário)

1. **Abas na caixa do setor**: `Para distribuir` (padrão — só processos sem responsável) e `Distribuídos` (acompanhamento, com o nome da analista).
2. **Redistribuição permitida** na aba `Distribuídos`, com auditoria da troca.
3. **Redistribuição bloqueada após a conclusão da análise** (`analysis_status = analise_concluida` ou etapa posterior — convite/vistoria).
4. **SLA na redistribuição: manter o prazo original** (`analysis_due_at` não é recalculado). O prazo é do processo, não da pessoa — trocar de analista não pode zerar atraso.

## Backend

### `CaixaSetorController@index`

- Aceita `visao=para_distribuir|distribuidos` (padrão `para_distribuir`), via query string.
- `para_distribuir`: adiciona `whereNull('assigned_user_id')` à consulta atual.
- `distribuidos`: adiciona `whereNotNull('assigned_user_id')`.
- Cada item expõe `pode_redistribuir` (boolean): falso quando `analysis_status` está em `analise_concluida` ou etapas posteriores (`em_convite`, `convite_*`, `vistoriar`, `vistoriado`).
- Contadores das duas visões no payload para os badges das abas.
- Auditoria de consulta já existente é mantida.

### `DistribuicaoService::redistribuir(ViabilityRequest $request, User $novaAnalista, User $ator)`

- Reusa `garantirVinculoDeSetor`.
- Bloqueia com `DistribuicaoException` quando a análise já foi concluída (mesma regra do `pode_redistribuir`).
- Grava `assigned_user_id`/`assigned_at` da nova analista **sem recalcular SLA** (mantém `analysis_due_at` e `analysis_stage_started_at`).
- Audita síncrono com evento `redistribuir`, registrando analista anterior e nova, ator, setor e processo.

### Rota

- `POST /gestao/caixa-setor/redistribuir` → `CaixaSetorController@redistribuir`, permissão `distribuir-processos`, reutilizando `DistribuirProcessoRequest` (single: `request_ids` com um item).
- Resposta: flash de sucesso ou erro controlado (nunca falha silenciosa).

## Frontend — `resources/js/pages/gestao/caixa-setor/index.tsx`

- Abas no topo do card: **Para distribuir** (com contador) e **Distribuídos** (com contador); navegação server-driven via query string (`visao`), preservando `per_page`.
- Aba **Para distribuir**:
  - Coluna de checkbox por linha + checkbox "selecionar todos" da página corrente.
  - Barra de ação contextual: botão **"Tramitar selecionados (n)"**, habilitado só com seleção não vazia e `podeDistribuir`.
  - Abre o modal de analistas já existente, enviando `request_ids[]` para `POST /gestao/caixa-setor/distribuir` (rota e lote já existentes).
  - Ações por linha (Distribuir individual, Assumir, Abrir) permanecem.
- Aba **Distribuídos**:
  - Coluna "Analista" em destaque.
  - Ação **Redistribuir** por linha (mesmo modal de analistas), desabilitada com motivo visível quando `pode_redistribuir` é falso.
- Feedback: flashes `status`/`warning`/`error` já emitidos pelo controller.

## Permissões e auditoria

- Sem perfil novo: Apoio e gestor (`distribuir-processos`) tramitam e redistribuem; analista (`analisar-processos`) assume.
- Cada distribuição/redistribuição audita síncrono por processo (RN-002/RN-006): quem, quando, setor, analista anterior → nova.

## Testes (TDD — Red-Green-Refactor)

Feature tests (PHPUnit), novos ou estendendo `CaixaSetorTest`/`DistribuicaoServiceTest`:

1. Aba padrão (`para_distribuir`) lista apenas processos sem responsável.
2. Aba `distribuidos` lista apenas processos atribuídos, com `pode_redistribuir` correto.
3. Lote via `request_ids[]` move os processos para a fila da analista (já coberto — manter verde).
4. Redistribuição troca a responsável **mantendo `analysis_due_at`** e audita `redistribuir` com analista anterior/nova.
5. Redistribuição bloqueada quando `analysis_status = analise_concluida` (403/erro controlado auditado).
6. Apoio não assume (já coberto — manter verde).
7. `FluxoApoioTramitacaoTest` (E2E) continua verde.
8. `npx vitest run resources/js/navigation/gestao-nav.test.ts` verde (nenhum item novo de menu — mesma tela).

## Fora de escopo

- Recepção REGIN/JUCEB (já alimenta a caixa do setor).
- Distribuição automática pelo motor (descartada por decisão de negócio).
- Caixa da analista e ficha de análise (já existem).
- Tabela de movimentações dedicada (timeline + activity log já cobrem).

## Critério de pronto

- Fluxo ponta a ponta demonstrável: processo recepcionado → caixa do setor (aba Para distribuir) → Apoio seleciona via checkbox → tramita para analista → processo some da aba Para distribuir, aparece em Distribuídos e na fila da analista → analista abre a ficha e analisa.
- Testes acima passando; `vendor/bin/pint --dirty --format agent` executado.
