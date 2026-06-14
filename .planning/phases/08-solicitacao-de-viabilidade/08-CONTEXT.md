# Phase 8: Solicitação de Viabilidade - Context

**Gathered:** 2026-06-14
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc) + spec `docs/superpowers/specs/2026-06-14-solicitacao-viabilidade-design.md`

<domain>
## Phase Boundary

Requerente CRIA, INSTRUI e PROTOCOLA a solicitação formal de viabilidade (primeiro processo formal + primeiro evento de domínio do sistema). Caminho real: criar (origem + tipo de serviço parametrizável) → informar imóvel (polígono/fachada/indicadores), área, atividade principal e CNAEs complementares (até 99) → anexar documentos e validar obrigatoriedade → simular (orientativo, não bloqueia) → protocolar (número único rastreável) → consultar protocolo (timeline, linguagem simples). Reusa Fases 1 (representação), 3 (cadastro/CNAEs), 4 (território/mapa/validação), 5/6 (motores) e 7 (ConsultaViabilidadeService p/ simulação). Contingência (HU-148) e atendimento presencial "em nome de" (HU-150) são os canais de operador — e a contingência é o caminho de operação real enquanto o Regin não chega.

Fora do escopo / bloqueado: fluxo expresso (Fase 9), análise técnica (Fase 10). BLOQUEADO externo → Fase 13: DAM/SEFAZ (HU-071/072 — escopo não confirmado; NENHUM contrato agora) e origem Regin. ADIADO pendente SEDUR: HU-139 (edifício comercial — complemento entra como texto livre). Degrada honesto: veredito locacional "pendente" sem zona; área×polígono alerta sem bloquear; validação documental por CNAE parametrizável com carga oficial pendente.
</domain>

<decisions>
## Implementation Decisions

### Reconciliação dos agents (anti-fachada)
- **HU-071/072 (DAM):** BLOQUEADO → Fase 13, sem nenhum contrato agora. Escopo revisado SEDUR (DAM é da SEFAZ; SILE só consulta/exibe) e ainda NÃO confirmado — construir interface seria especular. Success criteria 4 da Fase 8 depende da Fase 13 (anotado no ROADMAP).
- **HU-139 (edifício comercial):** ADIADO → pendente SEDUR (valor depende de inscrição/lote bloqueado + semântica pendente). `address_complement` é texto livre por ora.
- **Origem `regin`:** BLOQUEADO → Fase 13. Enum `origin` extensível existe (`portal`/`contingencia`/`regin`); só `regin` travado. `contingencia` (HU-148) é o caminho real hoje.

### Modelo de dados (arquiteto)
- Aggregate root `viability_requests` (imóvel embutido 1:1 como `companies`): protocol_number (unique nullable), status, origin, service_type_id, company_id, requester_user_id (beneficiário), created_by_user_id (ator real "em nome de"), used_area_m2, property_registration, address_* (incl. address_complement texto livre), property_polygon_geojson (jsonb FONTE) + property_polygon (geometry(Polygon,4326) nullable DERIVADA driver-aware, GiST condicional), indicadores (is_virtual_office/is_public_area/has_independent_access), simulation_snapshot/_rules_versions/_resultado/simulated_at, applicant_proceeded_despite, contingency_reason, external_reference (BAP/Regin futuro), protocoled_at/cancelled_*.
- Satélites: viability_request_cnaes (is_primary; unique(request,cnae); limite 99 na app), viability_request_documents (requirement_id nullable, disk/path/sha256/uploaded_by), viability_request_transitions (from/to/reason/public_label/actor — FONTE da timeline HU-069), viability_service_types (parametrizável CRUD), document_requirements + pivot cnae_document_requirement (modelo "Requisito" SIGVISA), protocol_sequences (year unique, last_number).
- Polígono: GeoJSON jsonb = fonte portável (suíte SQLite); geometry derivada via ST_MakeValid(ST_SetSRID(ST_GeomFromGeoJSON(?),4326)) só pgsql (idêntico ao GeoJsonLayerImporter) — serve consultas espaciais reversas das Fases 9/10.

### Máquina de estados
- enum ViabilityRequestStatus (label()/publicLabel()). Ativos: rascunho, protocolada, cancelada. Ganchos (NÃO implementar): aguardando_bap (Regin), em_analise/deferida/indeferida/em_pendencia (EP09/10/11). ViabilityRequestStateMachine com mapa explícito; transição inválida → exceção (CA-03); cada transição grava viability_request_transitions + AuditService::log('solicitacoes','transicao'). Fases seguintes só ADICIONAM ao mapa + listeners.
- Cancelamento: de rascunho e protocolada (antes de decisão). Estados canceláveis em parâmetro (default: enquanto não decidido).

### Número de protocolo
- Formato parametrizável {prefixo}-{AAAA}-{NNNNNN} (VIA, padding 6). Concorrência-segura: ProtocolNumberGenerator com protocol_sequences->lockForUpdate() na transação; unique(protocol_number) defesa final. Teste de concorrência = @group postgis (lockForUpdate no-op em SQLite); unicidade em SQLite.

### Evento de domínio (primeiro do sistema)
- app/Events/SolicitacaoProtocolada (carrega ViabilityRequest), disparado APÓS commit da transação. Auditoria do protocolo NÃO depende do evento (transição síncrona garante RN-002). Listener real agora: RegistrarTrilhaProtocolo (marco amigável timeline + auditoria alto nível). Ganchos (documentar, não implementar): notificação EP11, elegibilidade EP09, resposta Regin EP13.

### Anexos
- Disk parametrizado Settings::get('storage.documentos.disk', config(...,'local')); nunca público. MIME/tamanho parametrizados. Grava disk/path/sha256. DocumentRequirementResolver = união dos document_requirements.required dos CNAEs ∪ condicionais (fachada sempre; concessão se is_public_area); protocolo BLOQUEIA se faltar (aviso). Download streaming só autenticado. Consulta pública sem login HU-069 = URL::temporarySignedRoute (TTL parametrizável) + signed + throttle; status simples + timeline + prazo, sem dados sensíveis/anexos (LGPD).

### Reuso (sem recomputar)
- Imóvel: mapa Leaflet (MapaSection/MapImovel Fase 4) p/ polígono 4 pontos; TerritoryService::identify (centroide) degrada honesto; LocationValidationService::validate(array $polygonGeoJson) (existe, @group postgis) p/ área×polígono×lote.
- Simulação HU-141: SimulacaoSolicitacaoService itera CNAEs e chama ConsultaViabilidadeService (Fase 7) PROPAGANDO veredito (sem decisão paralela); persiste snapshot; não reprocessa no protocolo; mudança de área/CNAE/imóvel zera simulated_at. Toggle features.simulacao_solicitacao. NOTA: consultarPorPonto é private(lat,lng,ConsultaViabilidadeInput,?GeocodeResult) — expor ponto de entrada por ponto+CNAE (solicitação já tem o ponto), evitar geocodificar de novo (discrição do executor).
- "Em nome de" HU-150: reusa CurrentRepresentation/effectiveUser/auditoria (Fase 1); ViabilityRequestPolicy espelha CompanyPolicy. Presencial = modelo leve AssistedAttendance (attendant/citizen/started_at/expires_at curto parametrizável/ended_at) + middleware análogo populando o MESMO Context/CurrentRepresentation. Permissão atendimento-presencial.

### Parâmetros / permissões
- Parâmetros: features.solicitacao_viabilidade, features.simulacao_solicitacao, solicitacao.cnaes_complementares.max (99), solicitacao.protocolo.prefixo/.padding, solicitacao.consulta_publica.assinatura_ttl_dias, solicitacao.anexos.max_mb/.mime_permitidos, storage.documentos.disk, solicitacao.area_poligono.tolerancia_percentual, solicitacao.prazo_estimado_dias (ressalva HU-129/Fase 15), solicitacao.atendimento.expiracao_minutos, seguranca.throttle.consulta_protocolo.por_minuto. Tipos de serviço + requisitos documentais = DADOS administráveis (tabelas+CRUD), não registry.
- Permissões (spatie, aditivo): cidadão via policy; nomeadas registrar-contingencia, atendimento-presencial, consultar-solicitacoes, manter-tipos-servico, manter-requisitos-documentais.

### Claude's Discretion
- Nomes exatos de migrations/colunas/classes; assinatura exata do ponto de entrada da simulação por ponto; shape dos Resources/JSON; rotas (/portal/solicitacoes/* públicas vs autenticadas); estrutura da UI multi-etapas; formato do seed dev de tipos de serviço/requisitos.
</decisions>

<canonical_refs>
## Canonical References

### Spec + agents
- `docs/superpowers/specs/2026-06-14-solicitacao-viabilidade-design.md`.
- Agents (2026-06-14): analista-negocio (escopo/sequenciamento, núcleo 13 HUs, bloqueios) e arquiteto-tecnico (modelo de dados, máquina de estados, evento, anexos, contratos).

### HUs
- `docs/SILE_HUs_Completas_MD/EP08-Solicitacao-de-Viabilidade/HU-061..HU-072.md`, `HU-141`, `HU-148`, `HU-150`.
- `docs/SILE_HUs_Completas_MD/EP02-Administracao/HU-139` (edifício comercial — adiado).
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` (revisão DAM HU-071/072; contingência como caminho real).

### Reuso (NÃO recriar — consumir)
- Simulação: `app/Services/Viabilidade/ConsultaViabilidadeService.php` (consultarPorEndereco/PorCnae/PorInscricao públicos; consultarPorPonto private) + DTOs ConsultaViabilidadeInput/Result (Fase 7).
- Território/mapa: `app/Services/Geo/TerritoryService.php`, `app/Services/Geo/LocationValidationService.php` (validate(array $polygonGeoJson) @group postgis), `app/Services/Geo/GeoJsonLayerImporter.php` (gravação geometry driver-aware), `app/Services/Geo/PostgisSpatialRepository.php`; mapa em `resources/js/pages/gestao/territorio/*` (MapImovel/MapaSection).
- Representação: `app/Support/Representation/CurrentRepresentation.php`, `app/Http/Controllers/Portal/RepresentationController.php`, `app/Models/Procuration.php`, `app/Policies/CompanyPolicy.php`/`ProcurationPolicy.php`.
- Cadastro/CNAEs: `app/Models/Company.php` + pivot `company_cnae` + listagem "Minhas empresas" (escopo do dono) + picker de CNAEs (Fase 3).
- Auditoria/parametrização: `app/Support/Audit/AuditService.php` + HasAuditoria; Settings::get + `config/sile.php`; throttle via RateLimiter::for (FortifyServiceProvider, Fase 3.1) + parâmetro.

### Testes
- phpunit.xml SQLite; baseline atual 657 (--exclude-group postgis) + 15 @group postgis. Geometria/concorrência usam @group postgis (PostgisTestCase + geo:preparar-banco-de-testes). Golden/smoke #[DataProvider] (padrão Fases 5/6/7). Estrutura: tests/Feature/Solicitacao/ (ou Viabilidade).
</canonical_refs>

<specifics>
## Specific Ideas
- portal direto e contingência = MESMA máquina de estados e MESMOS motores; mudam só origem (auditada) e ator. HU-150 = portal direto "em nome de" pelo atendente.
- Simulação propaga o veredito do motor LOUOS (não decide), herda "pendente" sem zona, e NÃO bloqueia o protocolo (direito de petição, HU-141 RN-002).
- SolicitacaoProtocolada é o primeiro evento próprio — base desacoplada para EP09/11/13 (só listeners depois).
- Validação documental valida os obrigatórios conhecidos já agora (fachada sempre; concessão se área pública); por-CNAE entra vazio e recebe carga oficial.
</specifics>

<deferred>
## Deferred Ideas
- HU-071/072 (DAM/SEFAZ) — Fase 13 (escopo a confirmar SEDUR; nenhum contrato agora).
- Origem Regin (embed/webservice) — Fase 13 (HU-103/133); contingência é o caminho real hoje.
- HU-139 (edifício comercial/complemento) — pendente SEDUR + inscrição/lote bloqueado; complemento texto livre por ora.
- Reaproveitamento/precedentes do imóvel (HU-142) — Fase 10 (a geometry derivada já deixa o gancho espacial pronto).
- Prazo estimado real (HU-069 RN-005) — depende da medição HU-129/Fase 15; parâmetro com ressalva até lá.
</deferred>

---

*Phase: 08-solicitacao-de-viabilidade*
*Context gathered: 2026-06-14 via agents analista-negocio + arquiteto-tecnico*
