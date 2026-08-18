# Dashboard do Cidadão no "Meu Painel" (portal)

Data: 2026-06-29
Status: aprovada a abordagem em discussão; aguardando revisão final da spec

## 1. Contexto e problema

A página inicial do portal do cidadão, "Meu Painel" (`GET /portal/painel` → `portal.dashboard`), hoje não entrega valor real. O controller (`App\Http\Controllers\Portal\DashboardController::__invoke`) só faz `Inertia::render('portal/dashboard')` sem nenhuma prop, e a página (`resources/js/pages/portal/dashboard.tsx`) mostra apenas dois atalhos estáticos (Procurações, Meus acessos) e um bloco "Minha conta" com nome/e-mail.

Enquanto isso, o portal já tem dados ricos do próprio cidadão no banco: solicitações de viabilidade, pendências de análise, empresas vinculadas, histórico de consultas, notificações e procurações. Nada disso aparece no painel inicial — o cidadão precisa caçar cada informação nos submenus.

Objetivo: transformar o "Meu Painel" num dashboard útil, com dados reais (zero fachada), que responda em ordem de prioridade às duas perguntas do cidadão ao logar: **"preciso fazer algo?"** e **"cadê meu pedido?"**.

Escopo restrito ao portal do cidadão (guard `web`). O console de gestão não muda.

## 2. Decisões de escopo (aprovadas pelo usuário)

- **Abordagem A** (Ação + Acompanhamento), sem gráfico. O cidadão típico tem poucas solicitações; um gráfico de distribuição agrega pouco e custa manutenção (YAGNI).
- Os dados exibidos são exclusivamente os que já existem no banco hoje, com lógica real. Nada de TVL/alvará para download (decisão SEDUR: o documento não é entregue ao requerente — seria fachada) e nada de métrica de prazo "real" inexistente (a medição por etapa da HU-129 não está ativa).

## 3. Princípio de design (hierarquia de valor)

Ordenação por "isso depende de mim?", fundamentada nas HUs (parecer do analista de negócio):

1. **Ação necessária dele** (trava o processo): pendências de análise aguardando resposta (HU-090/091) e rascunhos não protocolados (HU-061/068).
2. **Acompanhamento** do que já foi protocolado: solicitações em andamento com status em linguagem do cidadão e prazo estimado com ressalva (HU-069).
3. **Conversão**: nova consulta de viabilidade (porta de entrada de baixo atrito — EP07).
4. **Contexto/patrimônio**: empresas vinculadas (HU-027), histórico de consultas (HU-060), notificações, procurações (HU-008).
5. **Identidade** (nome/e-mail): menor valor — vai para o rodapé.

Não existe HU dedicada a "painel do cidadão"; ele é um **agregador** das HUs acima. Enquadramento de fundo: Resolução CGSIM nº 61/2020, art. 7º (dever de resposta e transparência do andamento), operacionalizado pela HU-069.

## 4. Layout e seções (topo → base)

A página continua usando `PortalLayout` e ganha um `PageHeader` (`title="Meu painel"`), substituindo o breadcrumb manual atual.

### 4.1 "Precisa da sua atenção" (condicional)

Bloco de destaque no topo, renderizado **apenas** quando há pendência aberta ou rascunho. Tom de alerta (`warning`). Conteúdo:

- **Pendências aguardando resposta**: para cada `AnalysisPendency` aberta, mostra protocolo da solicitação, resumo curto e prazo (`due_at`, quando houver, em linguagem "até DD/MM/AAAA"), com CTA "Responder" → `/portal/solicitacoes/{id}/pendencias`.
- **Rascunhos a protocolar**: solicitações em `Rascunho`, com CTA "Retomar e protocolar" → `/portal/solicitacoes/{id}/editar`.

Quando **não há** nada pendente, o bloco é substituído por um estado calmo e explícito ("Tudo em dia — nada precisa de você agora"), reduzindo ansiedade. Esse estado calmo sempre aparece (mesmo sem nenhuma solicitação), funcionando como cabeçalho tranquilizador.

### 4.2 Indicadores (KpiCard)

Grid de `KpiCard` (componente existente, `resources/js/components/ui/kpi-card.tsx`), com números reais:

- **Em andamento** — solicitações não terminais (protocolada, em análise, em pendência, aguardando BAP). `tone="brand"`.
- **Empresas vinculadas** — `Company::countForUser` (só vínculos ativos). `tone="info"`.
- **Consultas feitas** — total de `ViabilityQuery` do usuário logado. `tone="success"`.
- **Notificações não lidas** — vem da shared prop `notificacoes.nao_lidas` (não refaz query). `tone="warning"`; vira atalho para a central.

Cada card com `note` curta de contexto. Sem `delta` (não há série histórica honesta).

### 4.3 Minhas solicitações recentes

Lista das últimas N solicitações (N constante, ver §7), com o mesmo shape da listagem existente: protocolo (ou "Em preenchimento"), empresa, situação via `Badge` com `public_label` (texto + cor), data. Cada item linka para o acompanhamento (`/portal/solicitacoes/{id}`). Cabeçalho com link "Ver todas" → `/portal/solicitacoes`.

`EmptyState` quando não houver nenhuma solicitação, com CTA primário "Fazer consulta de viabilidade" → `/portal/viabilidade` (conversão), pois "nova solicitação" depende de definição de canal (ver §10).

### 4.4 Ações rápidas

Cards de atalho (padrão visual atual), reordenados por valor:

- **Consulta de viabilidade** (destaque) → `/portal/viabilidade`
- **Minhas empresas** → `/portal/empresas`
- **Procurações** → `/portal/procuracoes`
- **Meus acessos** → `/portal/acessos`

### 4.5 Rodapé — Minha conta

Nome e e-mail descem para o fim da página, em `Card` discreto (deixam de ocupar espaço nobre).

## 5. Escopo de representação (LGPD)

Regra de segurança (HU-069 CA-04), não detalhe de UI. O painel usa duas referências de usuário, espelhando o código já existente:

- **Usuário efetivo** = `app(\App\Support\Representation\CurrentRepresentation::class)->grantor() ?? $request->user()` — para **solicitações, pendências e empresas** (mesma regra do `SolicitacaoController::index` e da `ViabilityRequestPolicy`).
- **Usuário logado** = `$request->user()` — para **consultas de viabilidade** (`HistoricoConsultaController` usa o logado) e **notificações** (shared prop).

Quando há representação ativa (`actingFor` setado — o banner já existe no `portal-layout`), os contadores **pessoais do procurador** (Consultas feitas, Notificações) são **omitidos** do painel, para nunca somar titulares distintos num mesmo número. Solicitações/pendências/empresas exibidas são as do titular representado.

Risco de IDOR: nulo — nenhum identificador vem do request; o único vetor seria escopo errado no código, fechado por teste (§8).

## 6. Backend

### 6.1 Service de leitura

Novo `App\Services\Painel\PainelCidadaoService` (route-free, injetável e testável — precedente dos `App\Services\Relatorios\*` usados pelo `Gestao\DashboardController`). Não inline no controller, porque há lógica de escopo de representação sensível que merece teste isolado e blindagem contra regressão.

Método principal recebe o usuário efetivo e o usuário logado (resolvidos no controller). "Em representação" é detectado quando `efetivo->id !== logado->id` (equivalente a `grantor()` não nulo / `actingFor` setado). Devolve um array com:

- `atencao`: `{ pendencias: [...], rascunhos: [...] }`
- `indicadores`: `{ em_andamento: int, empresas: int, consultas: int|null }` (consultas é `null` em representação)
- `solicitacoes_recentes`: lista (Resource, §6.2)

`notificacoes.nao_lidas` não entra aqui — já é shared prop.

### 6.2 Resource reutilizável

Não existe Eloquent API Resource para `ViabilityRequest` (a listagem usa array inline). Criar `App\Http\Resources\Portal\SolicitacaoResumoResource` com o núcleo comum (id, protocol_number, status {value,label,public_label}, service_type, company {legal_name, formatted_cnpj}, created_at, editable, cancelable) e **refatorar o `SolicitacaoController::index`** para reusá-lo — elimina a duplicação do shape em dois lugares.

### 6.3 Controller

`Portal\DashboardController::__invoke` resolve os dois usuários, injeta o service e passa as props para `Inertia::render('portal/dashboard', [...])`. Mantém-se fino (orquestra, não agrega).

### 6.4 Agregação por estado

Contagem de "em andamento" e demais counts por uma única query `groupBy('status')` sobre `viability_requests` no escopo do efetivo (padrão de `IndicadoresViabilidadeService`: agrega no banco, nunca em loop PHP), normalizada contra `ViabilityRequestStatus::cases()` para tratar estados ausentes como zero. "Em andamento" = soma dos estados não terminais.

### 6.5 Performance

~6–8 queries O(1) no total (counts agregados + últimas N com eager loading de `company`/`serviceType` + pendências abertas + rascunhos). Sem cache (dado do próprio usuário, muda com a ação dele; cache traria stale sem ganho real).

## 7. Parametrização

- **Prazo estimado**: reusa o parâmetro existente `solicitacao.prazo_estimado_dias` (default 30) com ressalva "estimado e não vinculante" (`Settings::get`). Sem novo parâmetro.
- **Quantidade de itens recentes (N)**: constante **técnica** em `config/sile.php`, no bloco `ui` (ex.: `ui.painel.solicitacoes_recentes`, default 5). É recorte de leitura/UI, não valor de negócio — segue o precedente [02-02] e o uso de `ui.*.per_page` já presente. **Não** entra no catálogo HU-014/`ParameterSeeder` (o `ParameterSeederTest` trava a contagem do catálogo; adicionar parâmetro quebraria o teste sem ganho de negócio).

## 8. Acessibilidade e linguagem (eMAG/WCAG 2.1 AA)

- Status sempre via `public_label` (nunca o `label()` técnico). Os rótulos públicos já estão revisados no enum (ex.: Protocolada → "Recebida — em processamento"; EmPendencia → "Pendência — ação necessária do requerente").
- Situação comunicada por **texto além de cor** (o `Badge` já exibe o rótulo textual; cor é reforço, não único canal).
- Sem jargão (BAP, TVL, SLA, malha fina). Onde inevitável, traduzir — o `public_label` já troca BAP por "Junta Comercial".
- Prazo sempre com ressalva de não vinculação; nunca expor SLA interno, ator/analista ou motivo interno das transições (minimização — HU-102).
- Bloco "Precisa da sua atenção" com heading semântico; CTAs como links/botões com rótulo descritivo; ícones decorativos com `aria-hidden`.

## 9. Estados

- **Sem solicitações**: bloco calmo "Tudo em dia" + KPIs zerados honestos + `EmptyState` na lista com CTA de consulta de viabilidade.
- **Com pendência/rascunho**: bloco de atenção em destaque.
- **Em representação**: dados do titular representado; contadores pessoais (consultas/notificações) omitidos.

## 10. Fora de escopo / bloqueios

- Gráfico de distribuição por status (abordagem B, descartada).
- "Nova solicitação" como CTA primário: a entrada formal de viabilidade depende de canal (integrador federal/Regin) — Fase 13 bloqueada externamente. O CTA de conversão é a **consulta prévia** (claramente do cidadão); "nova solicitação" segue disponível pelo fluxo atual, sem destaque novo.
- Download de TVL/alvará no portal (decisão SEDUR — não é entregue ao requerente).
- Medição de prazo real por etapa (HU-129, não ativa) — só o prazo parametrizado com ressalva.
- Unificar a inconsistência conhecida de escopo da `ViabilityQuery` (logado vs. efetivo) — depende de decisão de produto; o painel respeita o comportamento atual e o torna explícito.
- Auditoria nova só para renderizar a home (a trilha já ocorre no acesso ao processo específico — HU-069).
- Painel público de transparência sem login (decisão política SEDUR, distinta deste painel logado).

## 11. Testes (TDD)

Feature tests PHPUnit via rota (`get('/portal/painel')` + `assertInertia`), espelhando os CAs:

- **Happy path**: usuário com solicitações em vários estados → indicadores corretos, "em andamento" soma só os não terminais, lista recente com `public_label`.
- **Vazio**: sem solicitações → KPIs zerados, estado calmo, `EmptyState`.
- **Estados zerados/normalização**: status sem ocorrência contam como zero (sem erro).
- **Atenção**: pendência `aberta` aparece no bloco; rascunho aparece; pendência `respondida`/`expirada` não aparece.
- **Escopo de representação não vaza**: atuando em nome de outro, vê solicitações/empresas/pendências do representado e **não** as próprias; contadores pessoais omitidos. Sub-casos: sem representação, com representação, dados de terceiro não aparecem.
- **Pendências**: conta só as `aberta` do usuário efetivo (não de terceiros).
- **Refatoração do `index`**: o `SolicitacaoController::index` continua passando o mesmo shape após adotar o `SolicitacaoResumoResource` (teste existente deve permanecer verde).

Verificação: `php artisan test --compact` (filtrando os testes do painel), `vendor/bin/pint --dirty --format agent`, `npm run typecheck` e `npm run build`.

## 12. Referências

- HUs: HU-069 (acompanhamento), HU-090/091 (pendências), HU-060 (consultas), HU-027 (empresas), HU-008/009 (procurações), HU-076/077/119 (resultado/explicação), HU-102 (minimização), HU-014 (parametrização).
- Código: `app/Http/Controllers/Portal/DashboardController.php`, `resources/js/pages/portal/dashboard.tsx`, `app/Http/Controllers/Portal/SolicitacaoController.php`, `app/Enums/ViabilityRequestStatus.php`, `app/Models/{ViabilityRequest,Company,ViabilityQuery,AnalysisPendency}.php`, `app/Http/Controllers/Gestao/DashboardController.php`, `app/Services/Relatorios/*`, `resources/js/components/ui/{kpi-card,badge,card,empty-state}.tsx`, `config/sile.php`.
