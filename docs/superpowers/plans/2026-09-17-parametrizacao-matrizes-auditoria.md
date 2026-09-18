# Parametrização de Matrizes de Negócio — Backlog Priorizado

> Plano gerado a partir da auditoria somente-leitura de 2026-09-17 (varredura de hardcode, enums, matrizes e cadastros). Spot-checks das referências críticas confirmados no código antes da escrita deste plano. Cada fase vira um plano de implementação próprio (TDD) quando for executada.

**Goal:** eliminar as lacunas de parametrização que ainda exigem deploy para mudar regra de negócio, sem tocar no que é estrutural por desenho.

**Architecture:** tudo novo segue os padrões já consolidados do projeto — catálogo HU-014 (`parameters` + `Settings::get()`), versionamento por `rule_versions` com rascunho/sandbox/publicação quatro-olhos (padrão LOUOS), `HasAuditoria` nos CRUDs manuais e degradação honesta (nunca falha silenciosa).

**Tech Stack:** Laravel 13 + Inertia v3 + React 19 + PHPUnit 12.

## Global Constraints

- TDD estrito (Red-Green-Refactor); cada item nasce com feature test PHPUnit.
- `vendor/bin/pint --dirty --format agent` após alterar PHP.
- Nenhum adaptador falso; dependência externa indisponível = bloqueio registrado, não simulação.
- UI, mensagens e commits em pt-BR; código em inglês; conventional commits.
- Não parametrizar o que a auditoria classificou como estrutural (ver seção "Fora de escopo").

---

## Critério de priorização

1. **Risco jurídico/segurança** — algo que pode emitir decisão errada ou contornar o fluxo.
2. **Roteamento de processo dependendo de deploy** — dado de negócio em código PHP.
3. **Dado oficial sem interface** — tabela que só muda por seeder/artisan.
4. **Texto decisório** — o que sai no TVL/parecer embutido em constante.
5. **Consistência de vocabulário** — rótulos divergentes front × back.

---

## Fase 0 — Risco crítico: bypass `simulacao_protocolo` — **CONCLUÍDA 2026-09-18** (plano `2026-09-18-fase0-flag-simulacao-protocolo.md`; commit bae7969; flag `features.simulacao_protocolo` default OFF; suíte 1963/1994 com as 31 falhas pré-existentes de ambiente)

**Por que primeiro:** não é parametrização, é risco. `FluxoExpressoService::eSimulacaoRiscoRegin()` (`app/Services/Expresso/FluxoExpressoService.php:285-289`) converte veredito `pendente` em `permitido` e emite TVL quando `contingency_reason === 'simulacao_protocolo'` (constante em `ReginProtocoloSimulacaoService.php:32`). Em produção, qualquer processo REGIN com essa string recebe TVL sem enquadramento.

| Item | Ação | Esforço |
|---|---|---|
| 0.1 | Colocar o ramo de emissão atrás de feature flag (`features.simulacao_protocolo`, default **off**) e registrar no catálogo HU-014 com descrição explícita de "somente homologação" | P |
| 0.2 | Quando a flag estiver off, processo de simulação segue o fluxo normal (pendente → análise), sem conversão | P |
| 0.3 | Teste de regressão: com flag off, `ReginProtocoloSimulacaoTest` deve provar que nenhum TVL é emitido para `pendente` | P |
| 0.4 | Registrar no ROADMAP/STATE a remoção definitiva do bypass antes do go-live (depende de homologação REGIN real) | P |

**Critério de pronto:** com flag off (default), nenhum caminho do código converte `pendente` em `permitido` fora da consolidação real; testes verdes; flag visível na tela de parâmetros com aviso de risco.

---

## Fase 1 — Cadastro `tipos_imovel` (prioridade máxima de parametrização) — **CONCLUÍDA 2026-09-17** (plano de implementação `2026-09-17-tipos-imovel-cadastro.md`; commits cd4d2d5/8c23dd8/0ba7d9c/47a9a0b/ceabe60; suíte 1947/1978 com as 31 falhas pré-existentes de ambiente)

**Por que:** é o único dado do **roteamento** ainda em código. `TipoImovelCatalog::sedur200826()` (`app/Services/Risco/TipoImovelCatalog.php:24-37`) decide se o gatilho `dados_do_processo` derruba o processo do expresso para análise. O docblock da classe já a chama de "catálogo parametrizável" — a intenção sempre foi esta.

| Item | Ação | Esforço |
|---|---|---|
| 1.1 | Migration + models `tipos_imovel` (`codigo`, `rotulo`, `dirige_regra` bool, `ativo`) e `tipos_imovel_aliases` (`tipo_imovel_id`, `alias` normalizado) | P |
| 1.2 | Seed com o conteúdo atual de `sedur200826()` (galpão, container, edificação residencial dirigindo regra; edificação comercial e sala como ramo comum) — migração sem mudança de comportamento | P |
| 1.3 | `TipoImovelCatalog` passa a ser **construído do banco** (com cache invalidado por escrita, padrão `Settings`); a assinatura pública (`codigoQueDirige`, `codigoRamoComum`) não muda — os 3 consumidores (`SolicitacaoViabilityResolver`, `ReginProtocoloSimulacaoService`, `ReginTipoImovelApplier`) não são tocados | M |
| 1.4 | CRUD administrativo com `HasAuditoria` (seguir padrão de `ViabilityServiceTypeController`); edição de `dirige_regra` exige confirmação explícita na UI (impacto de roteamento) | M |
| 1.5 | Golden tests: os mesmos valores REGIN de hoje resolvem para os mesmos códigos (prova de não-regressão da migração) | P |

**Fora do item:** `TipoImovel::normalize` permanece em código (algoritmo, não dado).

**Critério de pronto:** adicionar um tipo de imóvel novo (ex.: "galpão logístico") pela interface muda o roteamento sem deploy; golden tests verdes; auditoria registra cada alteração.

---

## Fase 2 — CRUDs de dados oficiais que já estão no banco — **CONCLUÍDA 2026-09-18** (plano `2026-09-18-fase2-cruds-pendentes.md`; commits 80ad432/d967c73/7018d99/f390beb/1b1fa69; suíte 2006/2037 com as 31 falhas pré-existentes de ambiente)

Três tabelas já existem, são versionadas/auditadas, mas só mudam por seeder ou artisan. Esforço concentrado em interface, não em modelagem.

| Item | Tabela | Ação | Esforço |
|---|---|---|---|
| 2.1 | `risk_triggers` | CRUD de gatilhos semi-expresso (liga/desliga `ativo`, motivo, título). O model já tem `scopeAtivos()` e `HasAuditoria`; o docblock promete o CRUD "06-06". **Ação sensível** (grupo G5 da auditoria): desligar gatilho manda processo ZEIS/sem-enquadramento para conclusão automática — exige confirmação + auditoria reforçada | P |
| 2.2 | `virtual_office_activity_cnaes` | Importação CSV + sandbox + publicação versionada (reusar tal e qual o padrão do rascunho LOUOS, domínio `RuleDomain::AtividadesEscritorioVirtual` já existe). **Manter a guarda de `listaDisponivel()`**: sem versão vigente, o recurso de escritório virtual degrada honesto | M |
| 2.3 | `legal_terms` | CRUD de versionamento de termos LGPD (hoje só seeder) | P |

**Critério de pronto:** atualização dos Anexos A/B do Decreto 35.062/2021 acontece pela interface com publicação quatro-olhos, sem deploy; gatilho desligado gera registro de auditoria com responsável e justificativa.

---

## Fase 3 — Território: camadas geo, GeoServer e zonas — **CONCLUÍDA 2026-09-18** (plano `2026-09-18-fase3-territorio.md`; commits 545ed49/03831ac/5f41ad9/ec2e900/f16c3ea/066f52e/2f53bbd/501d62a/f959be3/4899601; revisão final Ready to merge após fixes C1+I2; I1 escalado — ver STATE.md)

| Item | Ação | Esforço |
|---|---|---|
| 3.1 | CRUD de `geo_layers` / `geo_features`: upload de camada, status (`vigente`/`substituída`/`pendente_fonte`), publicação — hoje só `artisan geo:import` no servidor | G |
| 3.2 | Tabela `camadas_geoserver` (`workspace`, `type_name`, `tipo`, `ativo`, `ordem`) substituindo as 20 entradas fixas de `config/sile.php:275-296`; seed com a lista atual | M |
| 3.3 | Cadastro `zonas` (`codigo`, `nome`, `macrozona`, `ativo`) + FK lógica a partir do Quadro 10 — elimina zona como string livre e o `nao_encontrado` silencioso por digitação | M |
| 3.4 | Parâmetro `geo.zona.atributos_nome` no catálogo (hoje array fixo em `LouosEnquadramentoService::zonaNome()`); o próprio comentário do código diz "a confirmar com a base oficial" | P |

**Dependência:** 3.3 depende de 3.1 (a lista de zonas deriva das camadas importadas). 3.2 e 3.4 são independentes.

**Critério de pronto:** zona nova publicada no GeoServer passa a ser enxergada pelo motor após cadastro na interface, sem deploy; Quadro 10 rejeita publicação com zona inexistente (validação, não silêncio).

---

## Fase 4 — Textos decisórios (TVL, parecer, fundamentação) — **CONCLUÍDA 2026-09-18** (plano `2026-09-18-fase4-textos-decisorios.md`; commits 761d667/f9b1b22/8b6885e/96fde1a/4f2b82c/6a2c2ea; revisão final Ready to merge; follow-up Fase 4.1 abaixo)

### Fase 4.1 — Completude do inventário (follow-up da revisão final, não iniciada)

O inventário da Fase 4 era baseado em constantes; a revisão final encontrou textos decisórios inline que ficaram de fora. Os goldens já provam os literais — migração mecânica de baixo risco:

1. `LouosEnquadramentoService`: motivos inline nunca mapeados — `'Quadro 7 sem versão vigente'` (~L107), `'CNAE sem enquadramento parametrizado no Quadro 7 vigente'` (~L125, congelado no golden sem ser administrável), motivos de condicionante/vagas/restrição (~L333-450).
2. `RiscoClassificationService`: base legal sanitária hardcoded (~L318) enquanto a municipal é administrável → `base_legal.risco_sanitario`; avaliar o motivo de ~L224.
3. `JustificativaFundamentadaComposer`: o CORPO do parecer (~15 literais em L177-363 — intro, parágrafos de território/quadros/risco/encaminhamento, prefixo 'Fundamentação: ').
4. `ConsultaViabilidadeService`: os dois avisos de inscrição (~L97/L103).

| Item | Ação | Esforço |
|---|---|---|
| 4.1 | Catálogo `motivos_parecer` (chave estável + template com placeholders `:cnae`, `:zona`, `:grupo`, `:area`) substituindo as ~14 constantes de `LouosEnquadramentoService:38-64`, os avisos de `ConsultaViabilidadeService:41-45` e o texto de `PreAnaliseService:287`. O mecanismo de placeholder `:cnae` já existe nas mensagens de escritório virtual — padrão provado no projeto | M |
| 4.2 | As 4 frases do `match()` em `JustificativaFundamentadaComposer:339-343` viram linhas do catálogo (é tabela disfarçada de código) | P |
| 4.3 | Cadastro `bases_legais` (`Lei nº 9.148/2016`, `Decreto 32.636/2020`, `Decreto 35.062/2021`) citado por referência; `risk_classifications` e Quadro 10/11A passam a referenciar em vez de concatenar literal | M |

**Critério de pronto:** alterar o texto de um motivo de indeferimento pela interface reflete no próximo TVL emitido, com histórico versionado; nenhuma citação legal permanece como literal em service.

---

## Fase 5 — Governança de parâmetros decisórios — **CONCLUÍDA 2026-09-18** (plano `2026-09-18-fase5-governanca-parametros.md`; catálogo 118→121; quatro olhos no PUT decisório; `json_fluxo_map` rejeita typo; 5.4 é pendência externa — estrutura pronta, dado oficial ausente)

**Por que:** a auditoria mostrou que parâmetros com impacto equivalente a publicação de regra (`risco.mapa_encaminhamento`, `risco.dimensao_tvl`, `analise.escritorio_virtual.cnae_gatilho_sede`, `features.fluxo_expresso`) passam hoje por um `PUT` de um clique, sem quatro olhos e **sem validação de domínio** — um typo (`"expreso"`) degrada tudo para análise silenciosamente.

| Item | Ação | Esforço |
|---|---|---|
| 5.1 | Classificação de parâmetros em `operacional` × `decisório` (metadado no catálogo); decisórios exigem quatro olhos (mesmo rito da publicação LOUOS) | M |
| 5.2 | Validação de domínio em parâmetros JSON: `risco.mapa_encaminhamento` só aceita valores do enum `Fluxo`; rejeitar typo na borda | P |
| 5.3 | Migrar para o catálogo: `solicitacao.duplicidade.janela_dias` (hoje só em `config/sile.php:127`), `ia.auditoria_preditiva.pesos` e `cortes_severidade` (hoje `private const` em `PredictiveAuditService:39-43,171-176`) | P |
| 5.4 | Cobrar da SEDUR os dados ausentes: `louos.vagas.exigencia_por_grupo` e `relatorios.saturacao.capacidades` (estrutura pronta, parâmetro vazio) — **pendência externa registrada; NÃO inventar dado** | P |

**Critério de pronto:** alteração de parâmetro decisório exige segundo aprovador e fica registrada com os dois responsáveis; typo em mapa JSON é rejeitado com mensagem clara. **Cumprido** (exceto 5.4, que depende da SEDUR).

---

## Fase 6 — Vocabulário único front × back

| Item | Ação | Esforço |
|---|---|---|
| 6.1 | Endpoint de metadados (rótulos de `RiscoMunicipal`, `RiscoSanitario`, `AnalysisCategory`, `ResultadoViabilidade`, `Quadro10Permissao`) servido pelo backend — fonte única; eliminar as 5+ cópias manuais em `resultado-viabilidade.tsx`, `dashboard.tsx`, `indicadores.tsx`, `saturacao.tsx`, `sandbox.tsx` | M |
| 6.2 | Resolver divergência `baixo_b` = "Médio" (só no front) e o `baixo_c` fantasma em `JustificativaFundamentadaComposer:431` (não existe no enum) — decisão de vocabulário com a SEDUR antes de codificar | P |
| 6.3 | `CATEGORIA_OPTIONS` do relatório de saturação (4 valores) × `AnalysisCategory` (2 casos): confirmar se "malha fina" e "sede de escritório" são categorias ou dimensões cruzadas; ajustar um dos lados | P |
| 6.4 | `export-menu.tsx` passa a respeitar `relatorios.export.formatos_habilitados` (parâmetro já existe, o componente ignora) | P |

---

## Questões abertas (resolver com SEDUR antes das fases indicadas)

1. `baixo_c` é resíduo ou nível futuro? (bloqueia 6.2)
2. "Malha fina" e "sede de escritório" são categorias de relatório ou dimensões? (bloqueia 6.3)
3. Quadro 11 (não-A) foi descontinuado em definitivo pela migration `2026_09_17_170000`? (afeta modelagem futura de condições por via)
4. A home pública (`home.tsx`: `services`, `steps`, `legalBasis`) precisa ser editável pela SEDUR? Se sim, vira CMS leve (fase nova); se não, permanece em código.
5. Dados de `vagas.exigencia_por_grupo` e `saturacao.capacidades` (pendência externa — 5.4; estrutura pronta, motor degrada honesto; **não inventar**).

## Fora de escopo (decisão registrada — NÃO parametrizar)

- Precedência da consolidação (`consolidar()`): garantia jurídica anti-fachada ("sem zona → pendente").
- Máquinas de estado (`AnalysisStatusStateMachine`, `ViabilityRequestStatus`): workflow, não cadastro.
- Sequenciais `protocol_sequences` / `tvl_sequences`: integridade transacional.
- `ai.allowed_hosts`: controle anti-SSRF, deploy-controlado por segurança.
- `PROVIDER_OPTIONS` de IA: provedor novo exige adaptador em código.
- Normalizadores (`TipoImovel::normalize`, formatação CNAE): algoritmo.
- Constantes técnicas (timeout/tries/backoff, TTLs, dimensão de embeddings): precedente [02-02] já consolidado.

## Sequenciamento sugerido e esforço total

```text
Fase 0 (P)  ── risco crítico, independente
Fase 1 (M)  ── independente
Fase 2 (M)  ── independente; 2.2 reusa padrão LOUOS
Fase 3 (G)  ── 3.3 depende de 3.1
Fase 4 (M)  ── independente
Fase 5 (M)  ── independente; 5.4 é pendência externa
Fase 6 (M)  ── depende das respostas SEDUR (questões 1-2)
```

Esforço bruto estimado: ~2 semanas de engenharia para Fases 0–2 (as de maior valor), ~3–4 semanas para o backlog completo, podendo paralelizar Fases 1, 2 e 4 (domínios disjuntos).
