# SILE — Sistema de Licenciamento Eletrônico

## What This Is

O SILE é o Sistema de Licenciamento Eletrônico da SEDUR (Salvador/BA): gestão da viabilidade locacional de atividades econômicas, com automação do licenciamento via motor de regras da LOUOS (Lei nº 9.148/2016), classificação de risco por CNAE (Decreto nº 32.636/2020), georreferenciamento, fluxo expresso (deferimento/indeferimento automático), análise técnica humana, integrações (REDESIM, Receita Federal, GIS, SEFAZ municipal), auditoria completa e indicadores. Atende cidadãos/requerentes (via integrador federal e portal), analistas e gestores da SEDUR.

## Core Value

Responder a viabilidade locacional de uma atividade econômica de forma automática, correta e auditável — aplicando de verdade as regras da LOUOS e a classificação de risco, sem análise humana quando a lei permite (fluxo expresso) e com fundamentação legal registrada em toda decisão.

## Requirements

### Validated

(Nenhum ainda — esqueleto sem código de domínio)

### Active

A fonte de verdade dos requisitos são as 131 Histórias de Usuário em `docs/SILE_HUs_Completas_MD/` (índice em `README-CATALOGO-HUs-SILE.md`), organizadas em 15 épicas (EP01 a EP15). Cada HU contém objetivo, fluxos, regras de negócio (RN), critérios de aceite BDD (CA), campos, permissões, exceções, auditoria, dependências e prioridade. Rastreamento detalhado em `.planning/REQUIREMENTS.md` (HU-001 a HU-131).

- [x] EP01 — Identidade, Acesso e Segurança (HU-001 a HU-010) — concluída (Fase 1, 2026-06-10)
- [ ] EP02 — Administração (HU-011 a HU-020)
- [ ] EP03 — Cadastro Empresarial (HU-021 a HU-028)
- [ ] EP04 — Georreferenciamento e Território (HU-029 a HU-037)
- [ ] EP05 — Motor de Regras da LOUOS (HU-038 a HU-046)
- [ ] EP06 — Classificação de Risco (HU-047 a HU-053)
- [ ] EP07 — Consulta Prévia de Viabilidade (HU-054 a HU-060)
- [ ] EP08 — Solicitação de Viabilidade (HU-061 a HU-072)
- [ ] EP09 — Fluxo Expresso (HU-073 a HU-078)
- [ ] EP10 — Análise Técnica SEDUR (HU-079 a HU-089)
- [ ] EP11 — Pendências e Comunicação (HU-090 a HU-096)
- [ ] EP12 — Auditoria e Compliance (HU-097 a HU-102)
- [ ] EP13 — Integrações (HU-103 a HU-111)
- [ ] EP14 — Inteligência Artificial (HU-112 a HU-121)
- [ ] EP15 — Relatórios e Indicadores (HU-122 a HU-131)

### Out of Scope

- Features de fachada (botões sem ação, resultados simulados, adaptadores falsos) — proibidas pelo contrato de execução; dependência externa indisponível = feature bloqueada e registrada, nunca simulada
- Cálculo de taxa TVS (modelo SIGVISA) — o SILE usa TLL (atividade de maior valor + taxa de serviço); a mecânica de DAM é reaproveitável, a fórmula não
- Portabilidade direta do frontend do SIGVISA — stack diferente (Vue/PrimeVue vs React/Inertia v3); apenas padrões de tela servem de referência

## Context

- **Esqueleto pronto, zero domínio**: Laravel 13 + Inertia v3 + React 19 + Tailwind 4 + PHPUnit. Apenas `User` e `Welcome.tsx` existem.
- **Análise de requisitos consolidada** em `docs/ANALISE-HUs-REUNIAO-SEDUR.md`: confronto das HUs com a reunião SEDUR (2026-06-09) e fontes oficiais (LOUOS Lei 9.148/2016, Decreto 32.636/2020, Portal Simplifica, CNAE 2.3 IBGE/CONCLA).
- **Dados oficiais já obtidos** em `docs/dados-oficiais/`: estrutura CNAE-Subclasses 2.3 (1.331 códigos, IBGE/CONCLA), classificação de risco municipal unificada (Decreto 32.636/2020 — 767 Baixo A / 328 Baixo B / 236 Alto, 1.045 com condicionantes), Planilha Unificada CNAE da VISA (285 linhas, dimensão sanitária — separada do risco municipal).
- **Implementação de referência**: o SIGVISA (`sls-sms`, mesma prefeitura) tem módulos operacionais reaproveitáveis — DAM/SEFAZ (JWT SenhaWeb), CNAE com condicionante-pergunta reclassificadora, auditoria via spatie/activitylog, assinatura digital gov.br/ICP, QR de verificação pública, geocodificação Nominatim/Leaflet, IA multi-provider, framework de importação CSV, permissões spatie. Backend é portável; frontend não.
- **Fluxo central do negócio**: entrada via integrador federal (REDESIM) → enquadramento Quadro 7 (área informada) → verificação zona/via (Quadros 10/11A/11B) → classificação de risco → fluxo expresso (baixo risco: obrigação legal) ou análise humana (alto risco; médio depende de condicionante) → resposta ao integrador com parecer.
- **Pendências de confirmação com a SEDUR** (pauta em `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 5): correspondência "Quadro 11" ↔ 11B oficial, escopo DAM (HU-071/072), estratégia de migração do legado .NET (HU-111), endpoint SEFAZ de envio de deferimento (HU-110), contrato REDESIM, base GIS municipal, acesso a homologação.
- **Condicionante como pergunta**: o modelo de dados precisa suportar condicionante operacionalizada como pergunta dirigida ao requerente cuja resposta reclassifica o risco (padrão do decreto "DI" e do SIGVISA `CnaePergunta`).
- **Risco em duas dimensões**: risco municipal unificado (decreto) ≠ risco sanitário (planilha VISA) — manter separados no modelo.

## Constraints

- **Tech stack**: Laravel 13 + Inertia v3 + React 19 + Tailwind 4 + PHPUnit — esqueleto existente; não trocar. Libs de backend do SIGVISA aplicáveis (dompdf, picqer/barcode, simple-qrcode, maatwebsite/excel, spatie/permission, spatie/activitylog).
- **Qualidade**: TDD estrito (Red-Green-Refactor); cada CA BDD das HUs vira feature test PHPUnit; nenhuma HU concluída sem CAs cobertos por testes passando.
- **Auditoria transversal**: trilha de auditoria (usuário, data/hora, origem, ação, resultado, versão de regras) é RN-002 de todas as HUs — implementar como infraestrutura desde o EP01.
- **Sem features de fachada**: toda feature executa lógica real de ponta a ponta; integração indisponível = feature bloqueada no ROADMAP/STATE, não simulada. Fakes/stubs só na suíte de testes.
- **Integrações reais**: integração externa só é concluída após validação contra homologação real (SEFAZ, REDESIM, GIS) com evidência de chamada real registrada.
- **Parametrização máxima (HU-014)**: nenhum valor de negócio hardcoded (prazos, limiares, taxas, textos, e-mails, termos); tudo administrável por interface com validação, histórico auditado e efeito sem deploy. Funcionalidades acopláveis nascem com feature toggle; desativação degrada de forma controlada. Credenciais criptografadas com teste de conexão.
- **Dados de seed**: fictícios aceitáveis em dev; preferir dados oficiais públicos (CNAE IBGE/CONCLA, Decreto 32.636/2020, quadros LOUOS) e substituir pelas planilhas oficiais da SEDUR quando entregues. O motor processa dados reais em qualquer caso — muda a carga, nunca a lógica.
- **Idioma**: UI, mensagens, commits e documentação em pt-BR; código (variáveis, classes, métodos) em inglês. Conventional commits em português.
- **Convenções**: AGENTS.md e rules do repositório — Laravel way (`php artisan make:`), Eloquent API Resources, factories em testes, `vendor/bin/pint --dirty --format agent` após alterar PHP, `search-docs` do Boost antes de mudanças.

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Requisitos rastreados pelo ID da HU (HU-001 a HU-131), não por REQ-IDs sintéticos | HUs são a fonte de verdade contratual; rastreabilidade direta documento ↔ código ↔ teste | — Pending |
| Roadmap estruturado por épicas na ordem de dependência (EP01 → EP15, com EP05/EP06 antes de EP07) | Consulta prévia consome motor de regras + risco + território; auth e cadastros base sustentam tudo | — Pending |
| Auditoria como infraestrutura no EP01 (não só no EP12) | RN-002 é transversal a todas as HUs; EP12 cobre consulta/exportação/LGPD sobre dados já registrados | — Pending |
| Mantenedores de quadros/condicionantes/risco (HU-015 a HU-020) entram junto com EP05/EP06 | Mantenedor sem motor que consuma os dados é feature morta; juntos formam fatia vertical | — Pending |
| HU-071, HU-072 (DAM) e HU-111 (migração legado) pendentes de confirmação de escopo com a SEDUR | Origem em fontes secundárias (Carta de Serviços, reunião); aguardando confirmação formal | — Pending |
| Motor de regras parametrizável: regras como dados versionados, não como código | Planilhas e quadros oficiais alimentam o sistema quando entregues; volumetria divergente na reunião exige flexibilidade de carga | — Pending |
| Integrações isoladas atrás de contratos (interfaces), adaptadores implementados contra API real em homologação | Sem documentação/credencial = feature bloqueada explícita; nunca adaptador falso | — Pending |
| `.planning/` versionado no git | Fonte de verdade da gestão; rastreabilidade exigida pelo fluxo (bloqueios de integração registrados em ROADMAP/STATE) | — Pending |
| Risco municipal e risco sanitário como dimensões separadas no modelo | 52 dos 260 CNAEs da planilha VISA divergem do decreto; são classificações de naturezas diferentes | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-06-09 after initialization*
