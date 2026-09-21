# Relatórios SEDUR 18–21/09 — Correções fora do motor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** corrigir tudo que os relatórios de teste da SEDUR (regras 19/09, usabilidade 19/09, reteste 21/09/2026) apontaram **fora do motor de regras** — decisão do analista, nomenclatura, estrutura da ficha, menu/listagens e parametrização — para destravar o reteste da Lisa Santos.

**Origem:** auditoria consolidada em 21/09/2026 (canvas `auditoria-relatorios-sedur`), com evidência arquivo:linha por item. Relatórios-fonte em `/Users/filipefalcao/Downloads/Relatorios-Lisa-SEDUR/`.

**Fora de escopo:** lógica de classificação de risco/encaminhamento do motor (já corrigida e validada nos 5 processos OK do e-mail de 21/09); itens ambíguos 06 e 09 do relatório de usabilidade (aguardando esclarecimento da SEDUR); cadastro de processos novos para regras sem caso de teste (pendência combinada com a SEDUR — ver Fase 5, Task 5.4).

## Global Constraints

- TDD: teste falhando primeiro; RED pelo motivo certo; depois o mínimo para GREEN.
- Renomes são de **label visível**: rotas, nomes técnicos e chaves de banco (`tvl`, `protocol_number`) não mudam.
- Menu: mexer só em `resources/js/navigation/gestao-nav.ts`; rodar `npx vitest run resources/js/navigation/gestao-nav.test.ts` ao final de cada task de menu (regra `menu-navegacao.mdc`).
- Nenhum valor de negócio hardcoded novo: referência de decreto sai de dado versionado, não de string na UI (regra `parametrizacao.mdc`).
- `vendor/bin/pint --format agent` nos PHP tocados da task. `git add` só dos arquivos da task.
- Commits em pt-BR, conventional, sem ponto final.
- Auditoria (RN-002): decisão do analista e mudança de parâmetro continuam trilhadas.

## File map

| Arquivo | Papel |
|---|---|
| `app/Services/Analise/PreAnaliseService.php` | Remove pré-marcação de decisão e parecer pré-preenchido |
| `app/Services/Analise/PerguntaLocalFicha.php` | Pergunta do CNAE da ficha (sem fallback genérico) |
| `app/Services/Analise/AnaliseTecnicaDecisionService.php` | 422 com erro de validação acionável |
| `app/Http/Controllers/Gestao/AnalysisRecordController.php` | Payload da ficha sem sugestão |
| `app/Services/Risco/RiscoClassificationService.php` | Fundamentação sem texto VISA |
| `resources/js/pages/gestao/ficha-analise/show.tsx` | Ficha: decisão, parecer, blocos, ações, labels |
| `resources/js/navigation/gestao-nav.ts` | Menu e permissões |
| `resources/js/pages/gestao/processos/{fila,index}.tsx`, `caixa-setor/index.tsx` | Listagens e caixa de entrada |
| `resources/js/pages/gestao/risco/simulacao-regin.tsx`, `components/analise/processo-ui.tsx` | Selos de fluxo e "mais restritivo" |
| `app/Support/Vocabulario.php`, `app/Enums/RiscoMunicipal.php` | Labels de risco e fluxo |
| `database/seeders/RiscoMunicipalSeeder.php` | Decreto 41.758/2026 vigente |

---

## Fase 1 — Críticos (bloqueiam o reteste de 21/09) — CONCLUÍDA em 21/09/2026

> Executada com TDD; suíte completa verde (2285 passando). Commits: `0a2c971c` (1.1+1.2), `ce22a753` (1.3), `db170bee` (1.4), `22955d63` (1.5). Pendência registrada: SEDUR confirma se o conteúdo da tabela de classificação mudou com o Decreto 41.758/2026; ambiente de homologação precisa de reseed (ou ajuste do texto em Textos decisórios) para refletir a nova versão.

### Task 1.1: Decisão do analista sem sugestão e sem pré-marcação

**Files:**
- Modify: `app/Services/Analise/PreAnaliseService.php`
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`
- Test: `tests/Feature/Analise/` (teste de payload da ficha — localizar o existente do `AnalysisRecordController`)

**Comportamento-alvo (relatório 21/09, item 02):** o sistema não sugere decisão; Deferido/Indeferido nunca vêm pré-marcados.

- [ ] **Step 1: Teste que falha** — ficha de processo em análise: para cada CNAE, `status_escolhido` é `null` e o payload **não** contém `status_sugerido`.
- [ ] **Step 2: RED** — hoje `PreAnaliseService.php:214-216` grava `status_escolhido = status_sugerido`.
- [ ] **Step 3: Implementar** — `status_escolhido` nasce `null`; remover `status_sugerido` do payload da ficha (o motor pode continuar calculando internamente para os motivos de análise, mas não expõe como sugestão de decisão). No frontend: remover o bloco "Especialista sugere" (`show.tsx:1491-1514`) e o fallback `item.status_escolhido ?? item.status_sugerido` — o radio reflete só a escolha do analista.
- [ ] **Step 4: GREEN + Pint**
- [ ] **Step 5: Commit** — `fix: remove sugestão e pré-marcação da decisão do analista`

### Task 1.2: Parecer técnico em branco

**Files:**
- Modify: `app/Services/Analise/PreAnaliseService.php`
- Test: mesmo teste de payload da ficha

**Comportamento-alvo (relatório 21/09, item 04; usabilidade item 27):** parecer nasce vazio; o rascunho do motor continua disponível como **justificativa por CNAE** (não como parecer).

- [ ] **Step 1: Teste que falha** — `parecer` vem `null`/`''` no payload; justificativas por CNAE continuam presentes.
- [ ] **Step 2: RED** — `PreAnaliseService.php:122` preenche via `parecerRascunho()`.
- [ ] **Step 3: Implementar** — `'parecer' => null`; manter `parecerRascunho` alimentando apenas a justificativa. Ajustar a descrição do card no frontend (`show.tsx:1808-1810`) removendo "fundamentado pelo motor". Mover o rótulo do serviço (checkbox "Sede de Escritório Virtual", `show.tsx:1831-1837`) para o card do CNAE correspondente.
- [ ] **Step 4: GREEN + Pint**
- [ ] **Step 5: Commit** — `fix: parecer técnico nasce em branco para o analista`

### Task 1.3: Pergunta da ficha é a do CNAE

**Files:**
- Modify: `app/Services/Analise/PerguntaLocalFicha.php`
- Test: `tests/Feature/Analise/` (ou Unit do resolver)

**Comportamento-alvo (relatório 21/09, item 02):** a pergunta exibida é a cadastrada para aquele CNAE na planilha de tratamento (ex.: 8211-3/00 → P4 escritório virtual/coworking), nunca a genérica "A atividade será desenvolvida no local?" quando houver pergunta própria.

- [ ] **Step 1: Teste que falha** — CNAE 8211-3/00 com pergunta P4 cadastrada → ficha exibe P4; CNAE sem pergunta cadastrada → sem bloco de pergunta (não fallback genérico).
- [ ] **Step 2: RED** — hoje `PerguntaLocalFicha.php:20-99` prefere os números `[2, 8, 11, 13]` e cai no genérico.
- [ ] **Step 3: Implementar** — resolver a pergunta pelo vínculo do CNAE (`TratamentoCnaeBinding` → `TratamentoPergunta`); remover a preferência fixa por números e o fallback genérico.
- [ ] **Step 4: GREEN + Pint**
- [ ] **Step 5: Commit** — `fix: exibe na ficha a pergunta do próprio CNAE`

### Task 1.4: HTTP 422 ao finalizar/decidir vira erro acionável

**Files:**
- Modify: `app/Services/Analise/AnaliseTecnicaDecisionService.php`
- Modify: `app/Http/Controllers/Gestao/AnalysisRecordController.php` e `ProcessoDecisaoController.php`
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx`
- Test: feature de `concluir-processo` e `decidir`

**Causa raiz (confirmada na auditoria):** `concluir-processo` aborta 422 quando algum CNAE está sem `status_escolhido` (`AnaliseTecnicaDecisionService.php:227-236`); `decidir` aborta 422 se a ficha não está finalizada ou o processo não está `em_analise`. Com a pré-marcação removida (Task 1.1), o cenário fica mais frequente — a correção é par com ela.

- [ ] **Step 1: Testes que falham** — (a) concluir com CNAE sem decisão → 422 **com erro de validação por campo** listando os CNAEs pendentes (não página "Oops"); (b) concluir com todas as decisões → 200; (c) decidir com ficha em rascunho → 422 com mensagem clara.
- [ ] **Step 2: RED**
- [ ] **Step 3: Implementar** — trocar `abort(422)` por `ValidationException` com mensagens por CNAE; no frontend, botão "Finalizar processo" desabilitado enquanto houver CNAE sem decisão, com tooltip/lista dos pendentes; erros de validação renderizados inline (padrão Inertia), nunca tela de erro.
- [ ] **Step 4: GREEN + Pint**
- [ ] **Step 5: Commit** — `fix: torna acionável o erro 422 ao finalizar e decidir o processo`

### Task 1.5: Fundamentação sem texto VISA e decreto 41.758/2026

**Files:**
- Modify: `app/Services/Risco/RiscoClassificationService.php`
- Modify: `database/seeders/RiscoMunicipalSeeder.php` (+ dados versionados)
- Modify: labels com "32.636" em `resources/js` (`auth-layout.tsx:17`, `home.tsx:53,89`, `cnaes/criar.tsx:214`, `cnaes/editar.tsx:536`, `indicadores.tsx:109,402`)
- Test: feature do resultado de viabilidade / unit do `RiscoClassificationService`

**Comportamento-alvo (relatório 21/09, itens 08–09; usabilidade item 10):** fundamentação cita só o decreto vigente (41.758/2026) e o risco; texto "Classificação de risco sanitário (Vigilância Sanitária)" não entra; referência de decreto na UI vem do dado versionado, não de string.

- [ ] **Step 1: Testes que falham** — (a) `fundamentacao()` não contém "Vigilância Sanitária"; (b) referência vigente é "Decreto Municipal nº 41.758/2026"; (c) risco sanitário sem classificação não exibe "não classificado" no card (exibe o risco ou nada — definir com a regra da planilha VISA unificada já seedada).
- [ ] **Step 2: RED** — `RiscoClassificationService.php:384-385` inclui a linha VISA; seed aponta `decreto-32636-2020`.
- [ ] **Step 3: Implementar** — remover a linha VISA; registrar o decreto 41.758/2026 como versão vigente do domínio de risco municipal (conteúdo da tabela: confirmar com a SEDUR se a classificação mudou ou só o instrumento legal — registrar como dependência se a planilha oficial do novo decreto não estiver disponível); UI passa a ler a referência do dado versionado.
- [ ] **Step 4: GREEN + Pint**
- [ ] **Step 5: Commit** — `fix: fundamentação cita só o decreto vigente sem texto da VISA`

---

## Fase 2 — Nomenclatura (barata; destrava os relatórios) — CONCLUÍDA em 21/09/2026

> Commit `c4b6e6cf`. Suítes Risco/Analise/EscritorioVirtual verdes (524 passando), `tsc` limpo, `gestao-nav.test.ts` e `valor-legivel.test.ts` verdes.

### Task 2.1: Renomes da ficha de análise e ações

**Files:** `resources/js/pages/gestao/ficha-analise/show.tsx`, `resources/js/pages/gestao/processos/index.tsx`

- [ ] "Dados do TVL" → "Dados da Viabilidade" (`show.tsx:1351`); "Emitir / baixar TVL" → "Visualizar Viabilidade" (`:1995`); "Criar nova revisão" → "Criar nova ficha" (`:1991`); "Abrir convite" → "Colocar em Convite" (`:2007,2069,2095`); "Encaminhar à malha fina" → "Encaminhar para a Vistoria" (`:2018,2137`; `processos/index.tsx:530`); popup de encaminhamento: "Motivo" → "Parecer" (`:2143`); remover badge "Destacado" (`:499`).
- [ ] Verificação: `npx vitest run` nos testes de UI existentes que citam os textos; grep sem ocorrências antigas.
- [ ] Commit — `style: renomeia ações e labels da ficha de análise`

### Task 2.2: Renomes de listagens e caixa de entrada

**Files:** `resources/js/navigation/gestao-nav.ts`, `resources/js/pages/gestao/processos/fila.tsx`, `index.tsx`, `caixa-setor/index.tsx`, `processos/show.tsx`

- [ ] "Fila de trabalho" → "Caixa de entrada" (menu `gestao-nav.ts:55`; tela `fila.tsx:166-167`); menu "Processos" → "Consulta de processos" (`gestao-nav.ts:62`); colunas "Categoria" → "Serviço" e "Responsável" → "Analista" (`index.tsx:271,306`; `caixa-setor/index.tsx:169`); "TVL da sede" → "Viabilidade da sede" (`show.tsx:393`).
- [ ] Verificação: `npx vitest run resources/js/navigation/gestao-nav.test.ts`.
- [ ] Commit — `style: renomeia fila, consulta e colunas de processos`

### Task 2.3: "Mais restritivo", "Sistema", "Médio Risco" e selo semi-expresso

**Files:** `resources/js/pages/gestao/risco/simulacao-regin.tsx`, `resources/js/components/analise/processo-ui.tsx`, `resources/js/components/auditoria/valor-legivel.ts`, `app/Enums/RiscoMunicipal.php`, `app/Support/Vocabulario.php`, `resources/js/pages/gestao/gatilhos-risco/index.tsx`

- [ ] "CNAE mais gravoso do conjunto" → "CNAE mais restritivo da solicitação" (`simulacao-regin.tsx:371,382`); labels visíveis "motor" → "Sistema"; `baixo_b` → "Médio Risco" (`valor-legivel.ts:24`, `RiscoMunicipal.php:28`); `rotuloFluxoRisco` (`processo-ui.tsx:69-78`) e `fluxoLabel` (`simulacao-regin.tsx:213-221`) ganham ramo `semi_expresso` → "Semi-expresso" (processo com 8211-3/00 nunca exibe "Expresso" nem "Permite decisão automática" — o flag vem do motor já corrigido; aqui é só o rótulo).
- [ ] Testes: unit do `valor-legivel` e do vocabulário de fluxo.
- [ ] Commit — `style: ajusta nomenclatura de risco, fluxo e sistema`

---

## Fase 3 — Estrutura da ficha de análise — CONCLUÍDA em 21/09/2026

> Commit `6a871980`. Suítes Analise/EscritorioVirtual verdes (424 passando), `tsc` limpo. Observação: o deploy de 21/09 (push `45468347`) saiu ANTES desta fase — publicar a Fase 3 exige novo `npm run build` + push.

### Task 3.1: Resumo no topo e Motivo de Análise fora da ficha

**Files:** `resources/js/pages/gestao/ficha-analise/show.tsx`

- [ ] Card "Resumo do processo (IA — sugestão, revise)" → "Resumo" (`:2268-2270`); remover destaque âmbar dos campos do Cadastro Imobiliário (`:486-501`); "Motivo de Análise" move para o topo, logo após o Resumo, fora da ficha (`:1882-1887`); remover duplicidade endereço/área — fica só o bloco superior (`:425-474,532-541` vs `:1284-1299`).
- [ ] Commit — `refactor: reorganiza resumo e motivo de análise no topo da ficha`

### Task 3.2: Enquadramento editável e TLL

**Files:** `resources/js/pages/gestao/ficha-analise/show.tsx`, `app/Http/Controllers/Gestao/AnalysisRecordController.php`, `app/Services/Analise/`

- [ ] Campos de enquadramento (quadro, código LOUOS, código TLL) viram seleção pré-preenchida pelo motor que o analista valida ou troca (hoje somente leitura, `show.tsx:1569-1592`); troca de código TLL recalcula `valor_tll` pelo exercício vigente (correlação já existe em `AnalysisRecordResource.php:79-133`); alteração entra no autosave e na trilha de auditoria.
- [ ] Testes: feature de autosave com troca de enquadramento + recálculo do valor TLL.
- [ ] Commit — `feat: permite validar e trocar o enquadramento na ficha`

### Task 3.3: Popup de vistoria e campos REGIN

**Files:** `resources/js/pages/gestao/ficha-analise/show.tsx`, `processos/show.tsx`

- [ ] "Encaminhar para a Vistoria" abre modal com parecer e escolha do setor de tramitação (hoje é select em `processos/show.tsx:265-287`); campo REGIN na ficha somente leitura; incluir "Vagas de carga e descarga" (dado do requerente via REGIN, sem edição).
- [ ] Testes: feature do encaminhamento com parecer obrigatório e setor.
- [ ] Commit — `feat: adiciona popup de vistoria com parecer e setor`

### Task 3.4: Justificativa consolidada no início do processo

**Files:** `resources/js/pages/gestao/ficha-analise/show.tsx`

- [ ] Bloco único no início: todas as atividades para validação; endereço/imóvel (logradouro, número, bairro, área, zona, via); LOUOS/TLL/valor por atividade; classificação de risco do estabelecimento com o CNAE que elevou; só fundamentação legal vigente + decreto do risco (usabilidade item 24).
- [ ] Commit — `feat: consolida justificativa do processo no início da análise`

---

## Fase 4 — Menu, home e listagens — CONCLUÍDA em 21/09/2026

> Commit `99115ff7`. Testes de autorização, dashboard, roteamento e listagens verdes; `gestao-nav.test.ts` verde; `tsc` limpo. Interpretação registrada: "Auditoria e compliance: eliminar" foi lido como "fora do menu do gestor/analista" (restrito ao administrador) — a trilha é RN-002 e não pode ser eliminada do sistema. Coordenador/subcoordenador/chefe de setor não existem como perfis; mapeados para gestor. A revogação do gestor vai na migration `2026_09_21_194557` (seeder é aditivo).

### Task 4.1: Caixa de entrada como home do analista e limpeza da fila

**Files:** `routes/gestao.php`, `resources/js/pages/gestao/processos/fila.tsx`, `resources/js/navigation/gestao-nav.ts`

- [ ] Tela inicial do analista = Caixa de entrada (hoje `/gestao` abre dashboard para todos); remover cards "Visão do setor", coluna "Prazo" e texto de priorização da fila (`fila.tsx:136,185,234-235`); processo da SEDUR entra na caixa do setor e o analista assume a partir dela (verificar regra de distribuição atual).
- [ ] Testes: feature de redirect/home por perfil.
- [ ] Commit — `feat: torna a caixa de entrada a home do analista`

### Task 4.2: Permissões de menu (Setores, Minutas, Auditoria, Indicadores)

**Files:** `resources/js/navigation/gestao-nav.ts`, `database/seeders/RolesAndPermissionsSeeder.php`

- [ ] Setores e Minutas/textos padrão só administrador (`manter-setores` hoje também vai para gestor — `RolesAndPermissionsSeeder.php:118`); eliminar grupo Auditoria do menu (trilha, alertas, preditiva) e restringir Indicadores/Relatórios a coordenador/subcoordenador/chefe de setor — confirmar com a SEDUR se a eliminação é do menu ou do módulo (item 06 do relatório está parcialmente em branco; registrar como dúvida se necessário).
- [ ] Verificação: `npx vitest run resources/js/navigation/gestao-nav.test.ts` + testes de permissão.
- [ ] Commit — `feat: restringe menus de administração e indicadores por perfil`

### Task 4.3: Colunas da consulta de processos

**Files:** `resources/js/pages/gestao/processos/index.tsx`, `app/Services/Analise/ProcessoQueryService.php`

- [ ] Colunas: Processo SEDUR (não VIA), protocolo JUCEB/REGIN (hoje "Empresa"), logradouro + bairro (novo), Serviço, Analista; manter Status e Situação de análise; replicar na Caixa do setor e na Caixa do usuário; consulta com Endereço / Zona / Via separadas.
- [ ] Testes: feature do payload da listagem com as novas colunas.
- [ ] Commit — `feat: ajusta colunas da consulta de processos ao padrão SEDUR`

---

## Fase 5 — Parametrização e dados — CONCLUÍDA em 21/09/2026 (com bloqueios registrados)

> Commit `2a88e65f` (+ build `660cc883`). Suíte completa verde: 2336/2336, incluindo o grupo postgis com o container de pé. Dois bugs de infra de teste corrigidos no caminho: isolamento do RefreshDatabase no `PostgisTestCase` e o seed do expresso sem a resposta da planilha no snapshot.
>
> - **5.1 Vias**: implementado — CRUD `gestao/louos/vias` espelhando Zonas, seed das 7 classes do Quadro 11A vigente e guarda de publicação do rascunho 11A contra o cadastro ativo.
> - **5.2 Tipo de imóvel da inscrição**: BLOQUEADO — a certidão SEFAZ/SEDUR mapeada em `SedurSefazPropertyRegistryLookup` não traz campo de tipo de imóvel; depende da TI da SEDUR indicar o campo/endpoint. Registrado como pendência externa.
> - **5.3 Visualizar Viabilidade**: resolvido na Fase 2 (renome) — a viabilidade já é associada ao processo deferido via `TvlDocument` → decisão.
> - **5.4 Seeds de processos SEDUR**: BLOQUEADO parcialmente — os 7 processos dos relatórios já existem no ambiente de análise; os processos NOVOS para regras sem caso dependem da SEDUR definir as combinações CNAE × zona × via (pré-condição da Lisa para fechar a validação da planilha).

### Task 5.1: Aba de Vias na parametrização LOUOS

**Files:** `app/Http/Controllers/Gestao/LouosController.php`, `resources/js/pages/gestao/louos/`, `routes/gestao.php`

- [ ] Cadastro de Vias (classe VA-1, nR1 etc.) como entidade própria, análogo ao de Zonas, alimentando as linhas do Quadro 11A (hoje `classe_via` é texto livre na linha — `quadro-fields.ts:67-68`).
- [ ] Testes: CRUD de vias + integridade referencial com o Quadro 11A.
- [ ] Commit — `feat: adiciona cadastro de vias na parametrização LOUOS`

### Task 5.2: Tipo de imóvel a partir da inscrição

**Files:** `app/Services/Realty/SedurSefazPropertyRegistryLookup.php`, `app/Services/Risco/TipoImovel.php`

- [ ] Lookup da inscrição passa a preencher `tipo_imovel` (hoje `null`, `:148`) com os valores Residencial Vertical / Residencial horizontal / Não residencial e comercial; ficha consome o valor da inscrição.
- [ ] Testes: unit do lookup com retorno fake da SEFAZ.
- [ ] Commit — `feat: preenche tipo de imóvel a partir da inscrição`

### Task 5.3: "Visualizar Viabilidade" a partir do processo

**Files:** `resources/js/pages/gestao/processos/show.tsx`, `ficha-analise/show.tsx`, `routes/gestao.php`

- [ ] Processo deferido exibe a viabilidade associada (documento já ligado via `TvlDocument` → decisão); ação "Visualizar Viabilidade" abre o documento sem emitir novo.
- [ ] Commit — `feat: vincula visualização da viabilidade ao processo deferido`

### Task 5.4: Seeds de processos de teste SEDUR

**Files:** `database/seeders/`

- [ ] Seeder dedicado com os 7 processos citados nos relatórios (68994/2024, 43747/2026, 53514/2026, 53528/2026, 33072/2026, 54772/2026, 54560/2026) como processos reais (hoje só existem no catálogo JSON da simulação); **dependência externa:** a SEDUR define as combinações CNAE × zona × via dos processos novos para cobrir regras sem caso — registrar no STATE.md como bloqueio até a planilha chegar.
- [ ] Commit — `test: semeia processos SEDUR dos relatórios de homologação`

---

## Spec coverage (self-review)

| Relatório | Tasks |
|---|---|
| Regras 21/09 itens 01–02 (semi-expresso, sugestão, pré-marca, pergunta) | 1.1, 1.3, 2.3 |
| Regras 21/09 item 04 (parecer, rótulo serviço) | 1.2 |
| Regras 21/09 item 05 (ações, viabilidade) | 2.1, 5.3 |
| Regras 21/09 item 07 (422) | 1.4 |
| Regras 21/09 itens 08–09 (mais restritivo, decreto, VISA) | 1.5, 2.3 |
| Usabilidade 01–06 (menu, caixas, perfis) | 2.2, 4.1, 4.2, 4.3 |
| Usabilidade 07–08 (vias, quadros) | 5.1 (quadros 10/11A já implementados) |
| Usabilidade 10–13 (tipo imóvel, conjunto, mais restritivo) | 1.5, 2.3, 5.2 |
| Usabilidade 14–21 (ficha, mapa, SAPS, precedente) | 3.1 (mapa GeoServer e "Solicitar consulta" ficam para fase de Território — registrar) |
| Usabilidade 22 (ações, convite, vistoria) | 2.1, 3.3 |
| Usabilidade 23–24 (enquadramento, TLL, justificativa) | 3.2, 3.4 |
| Usabilidade 25–29 (Sistema, REGIN, 8211, resumo) | 2.3, 3.1, 3.3 |
| Usabilidade 30–32 (consulta, TVL, CNAE) | 4.3, 2.1 (CNAE com descrição já implementado) |
| E-mail 21/09 (processos novos) | 5.4 |
