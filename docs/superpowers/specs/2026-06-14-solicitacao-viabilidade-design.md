# Spec de Design — Fase 8: Solicitação de Viabilidade (EP08)

**Data:** 2026-06-14
**Status:** Aprovado (brainstorming via agents analista-negocio + arquiteto-tecnico)
**Fontes:** [análise de negócio](#) + [arquitetura](#) (sessão 2026-06-14), ROADMAP Phase 8, HUs EP08 (HU-061..072, 139, 141, 148, 150), reunião SEDUR 2026-06-11.

## Problema

O requerente precisa **criar, instruir e protocolar** a solicitação formal de viabilidade que alimenta o fluxo expresso (Fase 9) e a análise técnica (Fase 10). É o primeiro processo formal do sistema (até aqui só houve consulta orientativa, Fase 7) e o primeiro a emitir **evento de domínio próprio**.

## Decisão de escopo (reconciliação dos dois agents)

Os dois agents convergiram no núcleo. Duas divergências pontuais resolvidas a favor da postura anti-fachada (não especular sobre contrato externo de escopo não confirmado):

| HU | Decisão | Razão |
|---|---|---|
| **HU-061..070, 141, 148, 150** | **Construir agora** — caminho real de ponta a ponta | Funciona 100% reusando Fases 1/3/4/5/6/7 |
| **HU-071/072 (DAM)** | **BLOQUEADO → Fase 13. Nenhum contrato construído agora** | A Nota SEDUR (2026-06-11) revisou o escopo (DAM é da SEFAZ; SILE só consulta/exibe) e o *escopo ainda não está confirmado* (consulta? exibição? renovação?). Construir até a interface seria especular sobre um contrato inexistente. Success criteria 4 da Fase 8 passa a depender da Fase 13 (anotado no ROADMAP). |
| **HU-139 (edifício comercial)** | **ADIADO → pendente SEDUR** | Seu valor (pré-preencher complemento por inscrição imobiliária) depende do lote/inscrição, **bloqueado** (Fase 4). Semântica pendente SEDUR. Por ora, `complemento` é **texto livre** na HU-062 (como no SAPS). |
| **Origem `regin`** | **BLOQUEADO → Fase 13** (enum extensível existe; só a origem é travada) | Contrato Regin não público. `contingencia` (HU-148) é o caminho real de operação hoje (confirmado: ROADMAP success criteria 6 + obs HU-148). |

**Núcleo a entregar:** HU-061, 062, 063, 064, 065, 066, 067, 068, 069, 070, 141, 148, 150 (13 HUs). **Zero dependência nova** (Storage local/S3, URLs assinadas e eventos são nativos; PDF é Fase 9/10).

## Comportamento honesto (degradação, não fachada)

- **Veredito locacional fica "pendente" sem a zona** (HU-141/062 herdam do motor LOUOS via Fase 7 — propagado, não recomputado). A simulação é orientativa e **NÃO bloqueia o protocolo** (HU-141 RN-002, direito de petição).
- **Área × polígono**: alerta por divergência, **não bloqueia** (HU-063 RN-004). Contra o lote oficial degrada (lote bloqueado); contra o polígono desenhado funciona.
- **Validação documental por CNAE**: nasce parametrizável; valida os obrigatórios conhecidos (foto da fachada sempre; termo de concessão se área pública) já agora; a obrigatoriedade por CNAE entra com tabela vazia e recebe carga oficial depois (planilha SEDUR hoje vazia).

## Arquitetura

### Aggregate root + satélites

- **`viability_requests`** (cabeçalho + imóvel embutido 1:1, como `companies`): `protocol_number` (unique nullable), `status`, `origin`, `service_type_id`, `company_id`, `requester_user_id` (beneficiário), `created_by_user_id` (ator real — "em nome de"), `used_area_m2`, `property_registration`, endereço (`address_*` incl. `address_complement` texto livre), `property_polygon_geojson` (jsonb, **fonte de verdade**), `property_polygon` (`geometry(Polygon,4326)` **nullable, derivada** driver-aware), indicadores (`is_virtual_office`/`is_public_area`/`has_independent_access`), `simulation_snapshot`/`simulation_rules_versions`/`simulation_resultado`/`simulated_at`, `applicant_proceeded_despite`, `contingency_reason`, `external_reference` (BAP/Regin futuro), `protocoled_at`/`cancelled_at`/`cancelled_reason`/`cancelled_by_user_id`.
- **`viability_request_cnaes`**: `request_id`, `cnae_id`, `is_primary` (espelha `company_cnae`). Limite **99** validado na aplicação (`solicitacao.cnaes_complementares.max`), `unique(request_id, cnae_id)`.
- **`viability_request_documents`**: `request_id`, `requirement_id` (nullable FK), `disk` (da época), `path`, `original_name`, `mime_type`, `size`, `sha256`, `uploaded_by_user_id`.
- **`viability_request_transitions`**: `request_id`, `from_status`, `to_status`, `reason`, `public_label`, `actor_user_id`, `created_at` — **fonte da timeline** (HU-069) e ganchos EP09/EP10.
- **`viability_service_types`**: tipos de serviço parametrizáveis (HU-061 RN-005) — `code`, `name`, `flow_hint`, `active`. CRUD admin.
- **`document_requirements`** + pivot **`cnae_document_requirement`**: modelo "Requisito" (SIGVISA) — `code`, `name`, `description`, `required`, `active`, `validation_instructions` (gancho IA EP14); N:N com CNAE.
- **`protocol_sequences`**: `year` unique, `last_number` — contador transacional.

**Polígono (decisão):** GeoJSON (jsonb) como fonte portável (suíte roda em SQLite) + `geometry` derivada nullable indexada (GiST condicional) para consultas espaciais reversas das Fases 9/10 (precedentes do imóvel — HU-142). Gravação da derivada driver-aware (`ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?),4326))`, só `pgsql`), idêntica ao `GeoJsonLayerImporter`.

### Máquina de estados

`enum ViabilityRequestStatus` (com `label()` + `publicLabel()` p/ HU-069):

```
rascunho ──protocolar(HU-068)──▶ protocolada ──cancelar(HU-070)──▶ cancelada
   └──cancelar──▶ cancelada      └┄┄(gancho EP09/EP10)┄┄▶ em_analise/deferida/indeferida
```

- **Ativos agora:** `rascunho`, `protocolada`, `cancelada`. **Ganchos (não implementar):** `aguardando_bap` (Regin/Fase 13), `em_analise`/`deferida`/`indeferida`/`em_pendencia` (EP09/10/11).
- `ViabilityRequestStateMachine` com mapa explícito de transições válidas; transição inválida → exceção (CA-03). Cada transição grava `viability_request_transitions` + `AuditService::log('solicitacoes','transicao',...)`. As fases seguintes só **adicionam entradas no mapa** e listeners — não tocam o protocolo.
- **Cancelamento:** cancelável de `rascunho` e `protocolada` (antes de decisão). Estados canceláveis em parâmetro (default: enquanto não decidido). Definição fina → pendência SEDUR.

### Número de protocolo

- **Formato parametrizável:** `{prefixo}-{AAAA}-{NNNNNN}` → `VIA-2026-000123` (`solicitacao.protocolo.prefixo`=`VIA`, `solicitacao.protocolo.padding`=6).
- **Concorrência-segura:** `ProtocolNumberGenerator` dentro da transação de protocolo: `protocol_sequences->lockForUpdate()` por ano, incrementa, persiste. `unique` em `protocol_number` é a defesa final. SQLite: `lockForUpdate` é no-op → **teste de concorrência real é `@group postgis`** (duas transações); unicidade roda em SQLite.

### Evento de domínio `SolicitacaoProtocolada` (primeiro do sistema)

- `app/Events/SolicitacaoProtocolada` (carrega o `ViabilityRequest`). **Disparado após o commit** da transação de protocolo (só protocolos efetivados geram efeitos).
- **Auditoria do protocolo NÃO depende do evento:** a transição `rascunho→protocolada` (síncrona, na transação) garante RN-002 mesmo se um listener falhar.
- **Listener real agora:** `RegistrarTrilhaProtocolo` (síncrono) — marco amigável da timeline (HU-069) + auditoria de alto nível.
- **Ganchos futuros (documentar, não implementar):** notificação cidadão (EP11), elegibilidade fluxo expresso (EP09), resposta Regin (EP13) — cada um pendura listener no mesmo evento.

### Anexos

- **Disk parametrizado (HU-014):** `Settings::get('storage.documentos.disk', config('sile.storage.documentos.disk','local'))`. Nunca disk público.
- Upload valida MIME/tamanho parametrizados (`solicitacao.anexos.mime_permitidos`, `solicitacao.anexos.max_mb`); grava `disk`/`path`/`sha256`. Substituível antes do protocolo; imutável depois.
- **Obrigatoriedade (HU-067):** `DocumentRequirementResolver` = união dos `document_requirements.required` dos CNAEs ∪ condicionais (fachada sempre; concessão se `is_public_area`). Protocolo **bloqueia** se faltar obrigatório (aviso, nunca silencioso).
- **Download por streaming** só autenticado (dono via policy / analista via permissão).
- **Consulta de protocolo sem login (HU-069):** `URL::temporarySignedRoute('portal.protocolo.publico', ...)` (TTL parametrizável) + `signed` middleware + `throttle`. Mostra status em linguagem simples + timeline + prazo estimado, **sem dados sensíveis nem anexos** (LGPD).

### Reuso (sem recomputar)

- **Imóvel (HU-062/063):** reusa mapa Leaflet (`MapaSection`/`MapImovel`, Fase 4) para o polígono de 4 pontos; `TerritoryService::identify` (centroide) → zona/via/bairro/restrições (degrada honesto); `LocationValidationService::validate($polygonGeoJson)` (já existe, `@group postgis`) para área×polígono×lote.
- **Simulação (HU-141):** `SimulacaoSolicitacaoService` itera os CNAEs e chama o `ConsultaViabilidadeService` (Fase 7), **propagando** o veredito do motor LOUOS (sem decisão paralela). Persiste snapshot; **não reprocessa no protocolo** (RN-003); mudança de área/CNAE/imóvel zera `simulated_at` (HU-063 RN-005). Toggle `features.simulacao_solicitacao`. *Nota de implementação:* `consultarPorPonto` hoje é privado e recebe `(lat, lng, ConsultaViabilidadeInput, ?GeocodeResult)` — o refactor mínimo é expor um ponto de entrada por ponto+CNAE reusando a solicitação (que já tem o ponto), evitando geocodificar de novo (discrição do executor sobre a assinatura exata).
- **"Em nome de" (HU-150):** reusa `CurrentRepresentation`/`effectiveUser`/auditoria da Fase 1; `ViabilityRequestPolicy` espelha `CompanyPolicy`. Atendimento presencial = modelo leve `AssistedAttendance` (`attendant_user_id`, `citizen_user_id`, `started_at`, `expires_at` curto parametrizável, `ended_at`) + middleware análogo ao da representação populando o **mesmo** `Context`/`CurrentRepresentation`. Permissão `atendimento-presencial`.

## Parametrização (HU-014), permissões e testes

**Parâmetros novos** (catálogo + fallback `config/sile.php`): `features.solicitacao_viabilidade`, `features.simulacao_solicitacao`, `solicitacao.cnaes_complementares.max` (99), `solicitacao.protocolo.prefixo`/`.padding`, `solicitacao.consulta_publica.assinatura_ttl_dias`, `solicitacao.anexos.max_mb`/`.mime_permitidos`, `storage.documentos.disk`, `solicitacao.area_poligono.tolerancia_percentual`, `solicitacao.prazo_estimado_dias` (ressalva honesta até HU-129/Fase 15), `solicitacao.atendimento.expiracao_minutos`, `seguranca.throttle.consulta_protocolo.por_minuto`. **Tipos de serviço, requisitos documentais = DADOS administráveis** (tabelas + CRUD), não parâmetros do registry.

**Permissões (spatie, aditivo):** cidadão opera as próprias via **policy**; nomeadas: `registrar-contingencia` (HU-148), `atendimento-presencial` (HU-150), `consultar-solicitacoes` (backoffice), `manter-tipos-servico`, `manter-requisitos-documentais`.

**Testes (TDD, feature PHPUnit):**
- SQLite (maioria): rascunho; CNAEs (limite 99); requisitos por CNAE; protocolar (número único, transição, evento via `Event::fake`); cancelar; consulta autenticada (escopo dono) + link assinado (HU-069); toggle off degrada; policy/permissão (CA-04); contingência (origem auditada); atendimento "em nome de"; simulação-snapshot (fake/real do ConsultaViabilidadeService).
- `@group postgis` (espelha os 15 da Fase 4): derivar `property_polygon`; sobreposição área×polígono×lote; território do ponto; **concorrência do protocolo** (`lockForUpdate` real).
- Factories: `ViabilityRequestFactory` states `draft`/`protocoled`/`cancelled`/`contingency`.

## Waves (para o gsd-planner)

| Wave | Conteúdo | HUs |
|---|---|---|
| **1 — Fundação** | migrations driver-aware (todas as tabelas), models+factories+enums (Status/Origin), `ProtocolNumberGenerator`, `ViabilityRequestStateMachine`, parâmetros+fallback+seeder, permissões | 061(base), 067(modelo), 014 |
| **2 — Cadastros admin** | 2a tipos de serviço (CRUD+UI) ‖ 2b requisitos documentais por CNAE (CRUD+UI) | 061 RN-005, 067 |
| **3 — Criação + imóvel + atividades** | HU-061 (criar rascunho); HU-062 (imóvel: mapa+TerritoryService+LocationValidationService+geometry, complemento texto livre); HU-063 (área+tolerância); HU-064 (atividade principal); HU-065 (CNAEs até 99) | 061, 062, 063, 064, 065 |
| **4 — Documentos + Simulação** | 4a HU-066 (anexar Storage) + HU-067 (validar obrigatórios) ‖ 4b HU-141 (`SimulacaoSolicitacaoService` reusa Fase 7) | 066, 067, 141 |
| **5 — Protocolo + evento + consulta** | HU-068 (protocolar, número único, `SolicitacaoProtocolada`+listener trilha, duplicidade RN-007); HU-069 (consulta autenticada + pública assinada); HU-070 (cancelar) | 068, 069, 070 |
| **6 — Canais de operador** | 6a HU-148 (contingência backoffice) ‖ 6b HU-150 (atendimento presencial `AssistedAttendance`) | 148, 150 |
| **7 — Fechamento** | seeds dev, golden/smoke navegável, verificação integral + `@group postgis` | todas |

## Bloqueios e pendências (registrar, não travar)

**Bloqueado externo → Fase 13 (contratos/binding indisponível ou nem construído):**
- DAM/SEFAZ (HU-071/072) — escopo não confirmado; nenhum contrato agora; success criteria 4 depende da Fase 13.
- Origem Regin (HU-061 RN-004/006, HU-103/133) — contingência (HU-148) é o caminho real hoje.

**Adiado (pendente SEDUR/dado bloqueado):**
- HU-139 (edifício comercial) — complemento como texto livre por ora.

**Pendências SEDUR (parametrizar com default honesto + ressalva):**
- Formato oficial do nº de protocolo (assume `VIA-AAAA-NNNNNN`).
- Lista oficial de tipos de serviço (tabela com seed mínimo).
- Requisitos documentais por CNAE (tabela vazia + obrigatórios-base; carga oficial depois).
- Regras de cancelamento após protocolo (estados canceláveis em parâmetro).
- Definição de duplicidade/reincidência (HU-061 RN-007 — janela em dias; por CNPJ funciona, por inscrição degrada).
- Procedimento de ciência presencial (HU-150 RN-004).
- Prazo estimado real (HU-069 RN-005) depende da medição da HU-129/Fase 15 — parâmetro com ressalva até lá.
