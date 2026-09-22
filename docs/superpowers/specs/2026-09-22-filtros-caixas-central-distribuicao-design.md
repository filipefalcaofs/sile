# Filtros nas caixas + Central de Distribuição — Design

Data: 2026-09-22
Status: aprovado (aguardando revisão da spec escrita)

## Contexto

O fluxo operacional **Caixa do setor → Apoio → seleciona processos → seleciona analista → Enviar → Caixa do analista** já existe e funciona de ponta a ponta:

- `CaixaSetorController` (`/gestao/caixa-setor`) com abas *Para distribuir* / *Distribuídos*, checkbox de seleção, botão *Tramitar selecionados*, ações por linha (assumir/distribuir/redistribuir).
- `DistribuicaoService`: `distribuir`, `distribuirLote`, `assumir`, `redistribuir` — com validação de vínculo de setor, recálculo de SLA (na distribuição) e auditoria síncrona por processo (RN-002/RN-006).
- Papel `apoio` (permissão `distribuir-processos`, distribui mas não analisa) e `analista` (`analisar-processos`, assume).
- Caixa do analista: `ProcessoController::fila` (`/gestao/processos/fila`) com abas *Meus processos* / *Caixa do setor* e KPIs.

Esta entrega **não cria fluxo novo** — reproduz e melhora o existente conforme os prints do sistema de referência.

## Gap a implementar

1. **Filtros ausentes**: nenhuma das duas caixas tem os filtros de pesquisa dos prints (só `per_page`).
2. **Distribuição sem visão de carga**: o modal atual é apenas um `<Select>` de nomes de analistas — sem quantidade de processos, sem indicador de carga, sem antes/depois. É insuficiente para o Apoio decidir a distribuição.

## Decisões de negócio (validadas com o usuário)

1. **Carga do analista** = apenas carga ativa de análise: processos com `assigned_user_id = analista`, `status = EmAnalise` e `analysis_status` em eixo aberto (não concluído). Detalhe com breakdown por grupo de etapa (Análise / Convite / Vistoria).
2. **Lista de analistas** na Central = apenas analistas vinculados ao(s) setor(es) dos processos selecionados (mantém a regra de vínculo de setor já existente).
3. **Lote multi-analista** (Fase 3): o Apoio define **manualmente a quantidade** por analista; o sistema aloca os processos por ordem de prazo e **exibe a prévia** de quais processos vão para cada analista antes da confirmação. O total alocado deve bater com o total selecionado. **Zero automação** — analistas e quantidades são sempre escolha do Apoio.
4. **Histórico** simples, lido do `activity_log` já existente: data/hora, Apoio que distribuiu, quantidade, analista destino. Sem tela complexa nem filtros avançados nesta entrega.
5. **Filtro "Status Tramitação"** usa o eixo operacional `analysis_status` (`AnalysisStatus::options()` — *Analisar, Em análise, Convite respondido...*), consistente nas duas caixas.
6. A decisão de quem recebe cada processo continua **100% do Apoio** em todas as fases.

## Faseamento

Cada fase tem seu próprio spec/plano/TDD e é entregável de forma independente.

- **Fase 1** — Filtros nas duas caixas.
- **Fase 2** — Central de Distribuição (carga visual, busca, ordenação, antes/depois, confirmação) para 1 analista.
- **Fase 3** — Lote multi-analista (quantidade por analista + prévia) e histórico.

---

## Fase 1 — Filtros nas duas caixas (detalhada)

### Filtros (params server-driven, iguais nas duas caixas)

| Filtro | Param | Campo/consulta |
|---|---|---|
| Serviço | `service_type_id` | `viability_requests.service_type_id` (relação `serviceType`) |
| Data Início | `data_inicio` | `protocoled_at >= data_inicio` (a "Data de Entrada" dos prints) |
| Data Fim | `data_fim` | `protocoled_at <= data_fim` (fim do dia) |
| Status Tramitação | `analysis_status` | `viability_requests.analysis_status` (via `AnalysisStatus::options()`) |
| Número do Processo | `protocolo` | `protocol_number` LIKE |
| BAP | `bap` | `external_reference` LIKE |
| Filtros Avançados (recolhido) | `bairro`, `categoria`, `vencendo` | `address_neighborhood` LIKE; `analysis_category`; `analysis_due_at` no dia |

### Backend

- **Novo escopo compartilhado** `App\Support\Filters\ProcessoFilter` (ou `scopeFiltrado` na query): recebe o `Request` e aplica os filtros acima a um `Builder<ViabilityRequest>`. Uma única fonte de verdade para as duas caixas (requisito de consistência).
- `CaixaSetorController::index`: aplica o filtro **depois** do escopo de setor/visão e **antes** da paginação. Contadores das abas passam a respeitar os filtros. Payload ganha `filtros` (valores atuais) e `opcoesFiltro` (serviços do setor + `AnalysisStatus::options()`).
- `ProcessoController::fila`: hoje entrega `processos` como array simples; passa a **paginar server-side** (igual à caixa do setor: `paginate` + `withQueryString`, `per_page` com as mesmas opções) e aplica o mesmo `ProcessoFilter` ao escopo `meus`/`setor`. Payload ganha `processos` paginado, `filtros`, `opcoesFiltro`, `perPageOptions`. Os KPIs continuam sobre o total do escopo (não sobre a página).
- Auditoria de consulta preservada, registrando os filtros aplicados.

### Frontend

- **Novo componente** `resources/js/components/analise/processo-filtros.tsx`: linha de filtros (Serviço, Data Início/Fim, Status Tramitação, Nº Processo, BAP) + botão/disclosure **"Filtros Avançados"** que revela bairro/categoria/vencendo. Reusa `TableToolbar`, `Select` e inputs existentes; navegação server-driven (`router.get` preservando `visao`/`modo` e `per_page`). Botão "Limpar".
- Usado em `caixa-setor/index.tsx` e em `processos/fila.tsx` com os mesmos props (a consulta é adaptada pelo backend conforme a caixa).
- `fila.tsx` passa a renderizar `Pagination` + `PerPageSelect` (padrão da caixa do setor), consumindo o `processos` agora paginado.

### Testes (TDD)

Feature (PHPUnit), estendendo `CaixaSetorTest` e `ProcessoFilaTest`:
1. Cada filtro isola corretamente o resultado (serviço, período por `protocoled_at`, `analysis_status`, protocolo, BAP, bairro/categoria/vencendo).
2. Filtros combinados (E lógico).
3. Contadores das abas da caixa do setor respeitam os filtros.
4. Mesmo conjunto de filtros funciona na fila (`meus` e `setor`).
5. Filtro vazio = comportamento atual (nenhuma regressão).
6. `npx vitest run resources/js/navigation/gestao-nav.test.ts` verde (nenhum item de menu novo).

---

## Fase 2 — Central de Distribuição (carga) — esboço

### Formato

Página dedicada larga: `GET /gestao/caixa-setor/distribuir?ids=...` (permissão `distribuir-processos`). O Apoio seleciona processos na Caixa do setor e clica em **Distribuir** → abre a Central. Cancelar volta para a caixa preservando a seleção.

### Conteúdo

- **Resumo no topo**: "Distribuir N processos · X analistas disponíveis · Y processos em análise no(s) setor(es)".
- **Tabela de analistas** (do setor dos processos): Nome · Carga atual (badge numérico + breakdown por grupo de etapa) · **barra de carga relativa ao maior da lista** · Selecionados agora · **Total após envio**.
- **Busca** por nome + **ordenação** (menor carga, maior carga, A–Z).
- Número sempre visível; cor apenas complementa (acessibilidade eMAG/WCAG — critério transversal do ROADMAP).
- **Confirmação** com antes/depois ("Carga atual 5 + Novos 3 = Após envio 8") antes de gravar.

### Backend

- `CaixaSetorController::distribuir` (nova action GET para a página) + reuso do `POST /gestao/caixa-setor/distribuir`.
- **Novo serviço/consulta de carga** (`CargaAnalistaService` ou método): `GROUP BY assigned_user_id` sobre processos ativos de análise do setor, com breakdown por `AnalysisStatus::grupo()`. Uma query, calculada ao abrir a Central (não no index da caixa).

### Testes (TDD)

1. Carga por analista conta apenas ativos de análise, com breakdown correto.
2. Só analistas do setor dos processos aparecem.
3. Barra relativa ao maior da lista.
4. Distribuir a 1 analista move os processos (reusa fluxo atual — manter verde).

---

## Fase 3 — Lote multi-analista + histórico — esboço

### Lote multi-analista

- Na Central, o Apoio informa **quantidade por analista** (0..restante). O sistema aloca os processos selecionados por ordem de prazo (`analysis_due_at`) e mostra a **prévia** (quais processos para cada analista). Só habilita confirmar quando a soma das quantidades = total selecionado.
- **Novo método** `DistribuicaoService::distribuirEmLoteMultiplo(array $alocacoes, User $ator)` onde `$alocacoes` = lista de `{analista_id, request_ids[]}`. Reusa `atribuir` por processo, isola falhas, audita por processo. Transação por analista.
- **Novo request** `DistribuirEmLoteRequest` validando: analistas do setor, processos em `EmAnalise`, sem sobreposição de `request_ids`, soma = total.
- **Nova rota** `POST /gestao/caixa-setor/distribuir-lote`.

### Histórico

- Painel lateral na Central: últimas distribuições lidas do `activity_log` (evento `distribuir`), exibindo data/hora, Apoio, quantidade e analista destino. Sem tela nova nem filtros avançados.

### Testes (TDD)

1. Alocação multi-analista respeita as quantidades informadas e a ordem de prazo.
2. Rejeita quando a soma ≠ total selecionado ou há `request_ids` repetidos.
3. Rejeita analista fora do setor (erro controlado, nunca silencioso).
4. Cada processo distribuído gera entrada de auditoria; histórico lê do `activity_log`.
5. Prévia server-side coincide com a gravação.

---

## Permissões e auditoria (todas as fases)

- Sem perfil novo: Apoio e gestor (`distribuir-processos`) distribuem; analista (`analisar-processos`) assume.
- Toda distribuição audita síncrono por processo (quem, quando, setor, analista destino) — RN-002/RN-006.
- Consultas (caixas e Central) auditadas, registrando filtros aplicados.

## Fora de escopo

- Distribuição automática ou sugestão de analista pelo sistema (descartada por decisão de negócio).
- Nova tabela de movimentações (activity log + timeline já cobrem).
- Alterações na ficha de análise e na caixa do analista além dos filtros.

## Critério de pronto (por fase)

- **Fase 1**: os 6 filtros + avançados operando nas duas caixas, com os mesmos params e resultados consistentes; contadores respeitam filtros; testes verdes; `vendor/bin/pint --dirty --format agent`.
- **Fase 2**: Apoio abre a Central, vê carga real (número + barra relativa + breakdown), busca/ordena, confirma com antes/depois e o processo cai na caixa do analista; testes verdes.
- **Fase 3**: Apoio distribui N processos entre vários analistas por quantidade, revisa a prévia, confirma; histórico das distribuições visível; testes verdes.
