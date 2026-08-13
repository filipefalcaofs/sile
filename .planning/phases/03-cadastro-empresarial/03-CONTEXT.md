# Phase 3: Cadastro Empresarial - Context

**Gathered:** 2026-06-11
**Status:** Ready for planning
**Source:** PRD Express Path (HUs do EP03 — `docs/SILE_HUs_Completas_MD/EP03-Cadastro-Empresarial/`, HU-021 a HU-028)

<domain>
## Phase Boundary

Empresas são cadastradas e mantidas com seus CNAEs (principal e secundários, da tabela oficial da Fase 2) e vínculos com usuários — prontas para sustentar as solicitações de viabilidade (Fase 8). Inclui: consulta de dados por CNPJ para pré-preenchimento, importação de dados no formato REDESIM (lógica real atrás de contrato), cadastro/atualização manual, vínculos usuário-empresa com papéis, consulta das próprias empresas e encerramento de vínculo — tudo auditado e navegável no portal do cidadão.

Fora do escopo: conexão/transporte com o integrador REDESIM e convênio oficial da Receita Federal (HU-103/HU-105 — Fase 13); solicitações de viabilidade (Fase 8); georreferenciamento do endereço (Fase 4).
</domain>

<decisions>
## Implementation Decisions

### HU-021 — Consultar dados do CNPJ
- Objetivo: preencher automaticamente os dados empresariais a partir do CNPJ.
- **Sem fachada**: a consulta SÓ existe se for contra fonte real. Decisão de arquitetura: contrato `CnpjLookup` (interface) com provider administrável. O convênio oficial RFB é a Fase 13; a pesquisa deve avaliar fonte pública oficial de dados abertos do CNPJ (ex.: BrasilAPI/minhareceita — dataset público da própria RFB) como provider inicial REAL, com feature toggle administrável (`features.cnpj_lookup`), credencial/URL parametrizadas (HU-014) e teste de conexão. Se inviável tecnicamente, a consulta automática fica explicitamente bloqueada (botão desabilitado com aviso "integração pendente") e o preenchimento é manual — nunca resultado simulado.
- Validação de CNPJ por dígitos verificadores (Rule `ValidCnpj` própria, padrão do ValidCpf da Fase 1).
- CAs padrão (execução, auditoria, bloqueio por inconsistência, segurança).

### HU-022 — Importar dados da REDESIM
- Receber dados empresariais no formato REDESIM e criar/atualizar o cadastro (a reunião SEDUR confirmou: a entrada real dos processos virá do integrador).
- **Lógica real atrás de contrato**: serviço de importação (`RedesimImportService` ou similar) que processa payload REDESIM e faz upsert de empresa+CNAEs+endereço, com relatório auditado (padrão do CnaeImportService da Fase 2). O TRANSPORTE (webservice/fila do integrador) é a HU-103 (Fase 13) — aqui o serviço é exercitado por testes e por comando artisan de import de arquivo (real, para homologação com payloads de exemplo da SEDUR quando entregues).
- NÃO criar tela fingindo "receber da REDESIM"; o serviço existe, é testado e documentado — a UI mostra a origem do cadastro (manual × redesim) quando houver registros importados.

### HU-023 — Cadastrar empresa
- Cadastro manual quando os dados não vierem integrados: razão social, nome fantasia, CNPJ (único, validado), natureza jurídica, porte, endereço (texto nesta fase; geocodificação é Fase 4), contato.
- Quem cadastra vira vínculo ativo com a empresa (responsável). Empresa duplicada (CNPJ) bloqueada com mensagem clara (CA-03).

### HU-024 — Atualizar dados empresariais
- Atualização de dados complementares pelo usuário vinculado; CNPJ imutável (padrão CPF/código CNAE); auditoria com attribute_changes.

### HU-025 / HU-026 — Vincular CNAE principal e secundários
- CNAE principal: exatamente um por empresa, escolhido da tabela oficial (somente CNAEs ativos).
- Secundários: zero ou mais, sem duplicar o principal nem entre si.
- Busca de CNAE por código/denominação (server-side, padrão da tela de CNAEs da Fase 2).
- Troca de principal e alterações de secundários auditadas (relevante para o motor de regras nas Fases 5/6).

### HU-027 — Consultar empresas vinculadas
- Lista das empresas do usuário (cards/tabela) com situação do vínculo, CNAE principal e ações; seleção de empresa é a base para "selecionar a empresa correta para solicitação" (Fase 8).
- Procurador em representação ("em nome de") enxerga as empresas do representado — integra com o mecanismo da Fase 1 (`CurrentRepresentation`).

### HU-028 — Encerrar vínculo empresarial
- Usuário encerra o próprio vínculo (campo de encerramento + motivo opcional); empresa não pode ficar sem NENHUM responsável ativo (CA-03 — bloqueio com mensagem).
- Histórico do vínculo preservado (nunca delete físico); auditado.

### Modelagem (direção; researcher detalha)
- `companies` (CNPJ único normalizado 14 dígitos, dados cadastrais, source: manual|redesim, timestamps);
- `company_user` (vínculo com papel no contexto da empresa — ex.: responsavel|procurador, started_at/ended_at, motivo) — pronto para a Fase 8 sem retrabalho;
- `company_cnae` (company_id, cnae_id, is_primary) com constraints de unicidade;
- FKs para `cnaes` da Fase 2 (PK surrogate já existente).

### Regras do projeto aplicáveis (travadas)
- TDD estrito; 4 CAs × 8 HUs como feature tests PHPUnit nomeados em pt-BR.
- Padrões de UI da fase 2.1/2.2 (OBRIGATÓRIOS — STATE.md): datatable em card com ação primária, criar/editar em Modal, ConfirmDialog para destrutivas, Pagination compartilhado, EmptyState, sidebar do portal ganha grupo "Serviços" → item "Minhas empresas".
- Parametrização: o que for valor de negócio vai para `config/sile.php`/Parameter (ex.: provider/URL do CNPJ lookup, toggle).
- Auditoria transversal (AuditService/HasAuditoria) em todas as ações.
- Rotas novas do portal sob `/portal/*` (mapa de rotas da fase 2.3); pt-BR na UI; conventional commits pt-BR.

### Claude's Discretion
- Detalhes do schema (campos exatos da empresa conforme formato REDESIM/Receita), nomes de rotas/controllers, organização das telas (lista + detalhe da empresa com abas vs página única).
- Estratégia do provider de CNPJ (qual fonte pública, resiliência, cache de consulta) — decidir na pesquisa com evidência.
- Estrutura do payload REDESIM de referência (pesquisar documentação pública do integrador nacional).
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requisitos da fase (fonte de verdade)
- `docs/SILE_HUs_Completas_MD/EP03-Cadastro-Empresarial/HU-021-Consultar-dados-do-CNPJ.md` a `HU-028-Encerrar-vinculo-empresarial.md` (8 HUs)
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` — seção 1 (entrada pelo integrador REDESIM confirmada) e 4B (módulos SIGVISA de referência: deduplicação de estabelecimentos)

### Código existente a respeitar
- `app/Models/Cnae.php` + migrations da Fase 2 (FK alvo dos vínculos)
- `app/Support/Settings.php` + `app/Models/Parameter.php` + ParameterSeeder (registro de parâmetros novos)
- `app/Services/AuditService.php`, trait `HasAuditoria` (auditoria travada)
- `app/Http/Middleware/ResolveRepresentation.php` + `CurrentRepresentation` (representação "em nome de")
- `app/Rules/ValidCpf.php` (padrão para ValidCnpj)
- `app/Services/CnaeImportService.php` (padrão de import com relatório auditado)
- `resources/js/components/{ui,form,app}/` + páginas da gestão (padrões de tela 2.1/2.2)
- `routes/portal.php` (estrutura de rotas e middlewares do portal)
- `.planning/STATE.md` — decisões acumuladas [01-xx], [02-xx] e fases 2.1/2.2/2.3

### Contexto do projeto
- `.planning/PROJECT.md`, `.planning/ROADMAP.md` (Fase 3), `AGENTS.md`

</canonical_refs>

<specifics>
## Specific Ideas

- A tela "Minhas empresas" do portal é a peça que o painel do cidadão consumirá (lacuna do painel registrada no STATE) — o controller deve expor contagens reutilizáveis.
- Import REDESIM: comando artisan `redesim:importar {arquivo}` para processar payloads reais em homologação (evidência de execução real sem depender do transporte da Fase 13).
- Origem do cadastro visível na UI (badge "Cadastro manual" × "REDESIM") — transparência da fonte.
- CNPJ exibido formatado `00.000.000/0000-00`, armazenado como 14 dígitos (padrão CPF/CNAE).
</specifics>

<deferred>
## Deferred Ideas

- Conexão real com o integrador REDESIM (transporte) — HU-103, Fase 13.
- Convênio oficial Receita Federal — HU-105, Fase 13 (o contrato CnpjLookup nasce agora; o provider oficial substitui o público depois).
- Geocodificação do endereço da empresa — Fase 4 (HU-029).
- Deduplicação/fusão de empresas migradas do legado — HU-111 (Fase 13), padrão SIGVISA.
- Seleção de empresa na criação de solicitação — Fase 8 (HU-061).
</deferred>

---

*Phase: 03-cadastro-empresarial*
*Context gathered: 2026-06-11 via PRD Express Path*
