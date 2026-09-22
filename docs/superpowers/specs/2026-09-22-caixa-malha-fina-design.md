# Caixa de Malha Fina — Design

Data: 2026-09-22
Status: aprovado pelo usuário (aguardando revisão da spec escrita)

## Contexto

A malha fina já existe no domínio (HU-136, Fase 10): flag `in_fine_mesh` em
`viability_requests` + tabela `fine_mesh_referrals` (motivo obrigatório,
`referred_by_user_id`, `resolved_at`), com `MalhaFinaService`
(`encaminhar`, `encaminharSistema`, `encaminharLote`, `resolver`), permissão
`encaminhar-malha-fina`, filtro `categoria=malha_fina` na consulta de processos
e auditoria (`malha-fina-encaminhar` / `malha-fina-resolver`, log_name
`analise`). Encaminhamentos automáticos já existem (detecção de abuso HU-149 e
auditoria preditiva via `encaminharSistema`).

**Lacuna:** não há caixa operacional dedicada. O `resolver()` existe sem rota
nem tela. A UI diz "caixa à parte", mas ela não existe como destino navegável.

**Invariante de domínio (já garantido pelo service):**
`in_fine_mesh = true` ⇔ existe pelo menos um encaminhamento com
`resolved_at` null. A malha fina é ORTOGONAL ao status (RN-001 da HU-136):
nunca transiciona `status` nem `analysis_status`.

## Decisões do usuário (2026-09-22)

1. **Permissão:** criar `analisar-malha-fina` separada de
   `encaminhar-malha-fina`, no mesmo catálogo Spatie existente (sem mecanismo
   paralelo). `encaminhar-malha-fina` só encaminha; `analisar-malha-fina`
   acessa e opera a caixa. Atribuição somente aos responsáveis definidos,
   configurável por usuário/perfil nas telas já existentes.
2. **Baixa:** ação "Concluir/Baixar" na própria caixa, usando
   `MalhaFinaService::resolver()`. Observação **opcional** (só quando houver
   informação complementar). Não altera o status do processo. Auditoria
   registra quem e quando.
3. **Atribuição:** SEM atribuição própria de revisor. A caixa mostra o
   responsável atual do processo (`assigned_user_id`) e quem encaminhou.
   Qualquer usuário com `analisar-malha-fina` abre e dá baixa.

## Abordagem

Caixa dedicada com controller próprio, espelhando o padrão da tela de
Vistorias (`VistoriaConsultaController` — universo por critério de domínio,
abas, KPIs) e reutilizando o motor de filtros da Caixa do Setor
(`ProcessoQueryService::aplicarFiltros` + `processo-filtros.tsx`).

Alternativas descartadas: (B) extrair abstração genérica de caixa refatorando
Vistorias/Caixa do Setor — risco de regressão fora de escopo; (C) aba/filtro
dentro de caixa existente — rejeitada pelo requisito de caixa dedicada.

## Design

### 1. Backend

**Permissão**
- Nova permissão `analisar-malha-fina` em `app/Support/PermissionCatalog.php`
  e `database/seeders/RolesAndPermissionsSeeder.php`.
- Seed: atribuir ao papel **gestor** (não a analista/apoio). Atribuição fina
  aos responsáveis é operacional, via telas de Administração já existentes.

**Migration** — `fine_mesh_referrals` ganha duas colunas nullable:
- `resolved_by_user_id` (FK `users`, `nullOnDelete`) — quem baixou;
- `resolution_note` (text, nullable) — observação opcional da baixa.

**`MalhaFinaService`**
- `resolver(FineMeshReferral $referral, User $ator, ?string $observacao = null)`:
  passa a gravar `resolved_by_user_id` e `resolution_note`; observação entra
  no payload da auditoria. Comportamento atual preservado (baixa
  `resolved_at`, desliga a flag ao resolver o último aberto, audita por
  encaminhamento, não toca no status).
- Novo `resolverAbertos(ViabilityRequest $request, User $ator, ?string $observacao = null): int`
  — baixa todos os encaminhamentos abertos do processo (a caixa lista
  processos, não encaminhamentos), cada um auditado, mantendo o invariante.

**Rotas** (`routes/gestao.php`, middleware `permission:analisar-malha-fina`):
- `GET /gestao/malha-fina` → `MalhaFinaCaixaController` (invokable),
  name `gestao.malha-fina.index`;
- `POST /gestao/malha-fina/{viabilityRequest}/concluir` →
  `MalhaFinaController@concluir`, name `gestao.malha-fina.concluir`,
  body: `observacao` (string opcional, trim; vazio = null).

**Auditoria da consulta:** evento `malha-fina-consulta` (log_name `analise`),
espelhando a consulta de vistorias — registra aba, filtros e total.

### 2. A caixa (listagem)

**Universo:** `ViabilityRequest::where('in_fine_mesh', true)`.

**Abas** (padrão Vistorias):
- **Em malha fina** — flag ativa (padrão);
- **Concluídas** — `in_fine_mesh = false` com pelo menos um encaminhamento
  baixado (`whereHas('fineMeshReferrals', resolved_at not null)`); a linha
  exibe dados do último encaminhamento baixado (quem baixou, quando,
  observação).

**KPIs derivados** (sem regra de negócio nova): em malha fina (total),
entradas no mês, concluídas no mês, com prazo de análise vencido
(`analysis_due_at` estourado e ainda em malha fina).

**Filtros** — reutilização de `ProcessoQueryService::aplicarFiltros` com as
chaves de caixa (`CHAVES_FILTRO_CAIXA`): Serviço, Data inicial/final
(sobre `protocoled_at`, padrão das demais caixas), Status da tramitação
(`analysis_status`), Nº do processo, BAP; avançados: nome, CNPJ, bairro,
categoria. Frontend: componente `processo-filtros.tsx` existente.

**Colunas:** Nº processo, BAP, data de entrada (protocolo), data de entrada
na malha fina (`created_at` do encaminhamento aberto mais recente),
requerente/empresa, serviço, status, status da tramitação, responsável atual
(`assignedTo`), encaminhado por + motivo, ações (Abrir processo, Concluir).

**Paginação:** server-driven, `per_page` com as opções padrão do console
(10/15/25/50, default via `ui.cnaes.per_page`), `withQueryString()`.

### 3. Acesso ao processo

A linha abre a ficha de análise existente (`gestao.processos.ficha.show`) —
o processo original, sem cópia nem versão paralela. A ficha é gated por
`analisar-processos`: o perfil do responsável pela malha fina deve receber
**as duas permissões** (documentado; sem `analisar-processos` a ficha nega
com 403 auditado). O gate da ficha não é alterado.

### 4. Menu

Item **"Malha fina"** no grupo **Operação** (trabalho do dia — árvore de
decisão de `menu-navegacao.mdc`), após "Vistorias", ícone `shield`
(vocabulário existente), `permission: 'analisar-malha-fina'`. Apenas em
`resources/js/navigation/gestao-nav.ts` (sidebar e Cmd+K leem o mesmo
catálogo). `gestao-nav.test.ts` deve continuar verde.

### 5. Frontend

Página `resources/js/pages/gestao/malha-fina/index.tsx` no padrão visual das
caixas: KPIs no topo, abas, `ProcessoFiltros`, tabela, paginação. Título
evidente "Caixa de Malha Fina". Modal de conclusão com campo de observação
opcional e confirmação. Ação "Abrir processo" navega para a ficha.

### 6. Tramitação e auditoria (rastreabilidade)

Reutilização total do mecanismo existente (`AuditService` + `activity_log`,
consolidados na trilha do processo por `TrilhaProcessoService`):
- quando entrou: `malha-fina-encaminhar` (já existe) + coluna "data de
  entrada na malha fina" na caixa;
- quem encaminhou: `referred_by_user_id` (já existe);
- quem baixou, quando, por quê: `resolved_by_user_id`, `resolved_at`,
  `resolution_note` + evento `malha-fina-resolver` (já existia; passa a
  incluir a observação);
- consulta à caixa: `malha-fina-consulta` (novo).

### 7. Testes (TDD estrito — Red-Green-Refactor)

Feature tests PHPUnit (`tests/Feature/MalhaFina/CaixaMalhaFinaTest.php` ou
equivalente):
1. acesso à caixa negado sem `analisar-malha-fina` (403);
2. caixa lista apenas processos com `in_fine_mesh = true`;
3. filtros (serviço, datas, status tramitação, protocolo, BAP) aplicados;
4. aba Concluídas lista só processos fora da malha fina com baixa registrada;
5. concluir baixa todos os encaminhamentos abertos, desliga a flag, grava
   `resolved_by`/`resolution_note`, audita e **não muda** `status` nem
   `analysis_status`;
6. observação vazia/ausente é aceita (opcional);
7. concluir exige `analisar-malha-fina` (403 sem ela);
8. consulta à caixa é auditada (`malha-fina-consulta`).

Teste de navegação: `resources/js/navigation/gestao-nav.test.ts` verde com o
item novo.

## Fora de escopo

- Atribuição/distribuição própria de revisor dentro da malha fina (decisão do
  usuário: sem atribuição própria).
- Critérios automáticos novos de entrada na malha fina (já existem: provocação
  humana HU-136, abuso HU-149, auditoria preditiva).
- Alteração do gate da ficha de análise.
- Abstração genérica de caixas (refatoração futura, se fizer sentido).

## Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Responsável pela malha fina sem `analisar-processos` não abre a ficha | Documentar que o perfil leva as duas permissões; 403 já é auditado |
| Processo com múltiplos encaminhamentos abertos | `resolverAbertos` baixa todos, invariante da flag preservado |
| Regressão nas caixas existentes | Nenhum arquivo delas é modificado; apenas reuso de `ProcessoQueryService`/`processo-filtros.tsx` sem alteração |
