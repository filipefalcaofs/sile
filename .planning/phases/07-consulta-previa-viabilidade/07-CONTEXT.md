# Phase 7: Consulta Prévia de Viabilidade - Context

**Gathered:** 2026-06-14
**Status:** Ready for planning
**Source:** Agents analista-negocio + arquiteto-tecnico (política agentes-sile.mdc) + spec `docs/superpowers/specs/2026-06-14-consulta-previa-viabilidade-design.md`

<domain>
## Phase Boundary

Cidadão consulta a viabilidade de uma atividade por endereço ou CNAE (e, quando a base existir, por inscrição imobiliária) SEM criar processo formal. Orquestra os motores reais: território (Fase 4), enquadramento LOUOS (Fase 5) e risco (Fase 6), retornando enquadramento + risco + restrições + fundamentação legal + versões das regras. Histórico das próprias consultas (autenticado). Primeira entrega do fluxo de decisão de ponta a ponta.

Fora do escopo: processo formal de solicitação (Fase 8), fluxo expresso (Fase 9). Bloqueado pendente SEDUR: veredito locacional permitido/não permitido (Quadro 10/zona) e resolução por inscrição imobiliária (lote/Cadastro) — degradam honestamente.
</domain>

<decisions>
## Implementation Decisions

### Orquestração (arquiteto) — NÃO recomputar veredito
- `ConsultaViabilidadeService` (app/Services/Viabilidade/) só ORQUESTRA e AUDITA. Pipeline: endereço→Geocoder→lat/lng → TerritoryService::identify → LouosEnquadramentoService::enquadrar(EnquadramentoInput::paraConsulta) → RiscoClassificationService::classify(RiscoInput::paraCnae) → ConsultaViabilidadeResult readonly (snake_case toArray, versoes() de todas as regras). A degradação (zona indisponível → pendente) vem do motor LOUOS — apenas propagada (não duplicar HU-044).
- DTOs ConsultaViabilidadeInput/Result espelham RiscoResult/EnquadramentoResult/TerritoryResult (já há paraConsulta/paraCnae e ResultadoViabilidade::Pendente prontos das Fases 5/6).

### Comportamento honesto (analista) — confirmado no código
- Sem zona: risco + Quadro 7 + restrições/bairro/via REAIS; veredito locacional = Pendente (motivo "zona pendente SEDUR"). UI nunca mostra Permitido/Não permitido sem zona.

### 3 entradas
- Endereço (HU-054): real (geocode→território→motores). CNAE (HU-056): risco real + (com área) Quadro 7, sem território. Inscrição (HU-055): BLOQUEADA na resolução do ponto — contrato PropertyRegistryLookup (interface + provider indisponível, binding trocado Fase 13); degrada com aviso, sugere endereço, NUNCA inventa ponto; teste com fake provando a lógica.

### Histórico (HU-060) — autenticado
- Tabela imutável viability_queries (input + result jsonb snapshot + rules_versions jsonb + user_id), gravada no controller só quando autenticado; anônima é auditada (causer sistema, IP/origem) mas sem histórico pessoal. Usuário vê só o próprio (precedente Minhas empresas). Service reutilizável (HU-141 Fase 8).

### Pública x autenticada
- HU-054/056/057/058/059 PÚBLICAS (/portal/*) com throttle parametrizado + auditoria. HU-060 AUTENTICADA.

### UI
- Portal light; reusa MapaSection/MapImovel (Fase 4). Resultado: risco (badges) + enquadramento Quadro 7 + restrições/bairro/via + veredito locacional (pendente com motivo). Histórico autenticado.

### Parâmetros / deps
- features.consulta_viabilidade (toggle) + seguranca.throttle.consulta_viabilidade.por_minuto. ZERO dependência nova (confirmado).

### Claude's Discretion
- Nomes exatos de tabela/serviço/migrations; shape do ConsultaViabilidadeResult::toArray(); rotas (/portal/viabilidade/*); estrutura da UI.
</decisions>

<canonical_refs>
## Canonical References

### Spec + advisories
- `docs/superpowers/specs/2026-06-14-consulta-previa-viabilidade-design.md`.
- Agents (sessão 2026-06-14): analista-negocio (escopo/CAs→testes, pública×autenticada, degradação honesta, HU-055 bloqueada) e arquiteto-tecnico (ConsultaViabilidadeService, viability_queries, contrato PropertyRegistryLookup, UI).

### HUs
- `docs/SILE_HUs_Completas_MD/EP07-*/HU-054..HU-060.md`.
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` (consulta pública; throttle EP07).

### Motores a orquestrar (NÃO recriar — só consumir)
- `app/Services/Geo/TerritoryService.php` + `TerritoryResult.php` (bairro/via/restrição reais; zona/lote indisponivel) e `app/Services/Cnpj/...`/`app/Services/Geo/...` Geocoder (`Geocoder` interface + NominatimGeocoder).
- `app/Services/Louos/LouosEnquadramentoService.php` + `EnquadramentoInput::paraConsulta` + `EnquadramentoResult` + `app/Enums/ResultadoViabilidade.php` (Pendente).
- `app/Services/Risco/RiscoClassificationService.php` + `RiscoInput::paraCnae` + `RiscoResult`.

### Padrões a reusar
- Contrato + provider + binding: `app/Services/Cnpj/CnpjLookup.php`/`BrasilApiCnpjLookup.php` (padrão para `PropertyRegistryLookup`).
- Persistência/escopo por usuário: `app/Models/Company.php` + listagem "Minhas empresas" (Fase 3) — histórico só do dono.
- Throttle: `RateLimiter::for` no FortifyServiceProvider (Fase 3.1) + parâmetro.
- UI mapa: `resources/js/pages/gestao/territorio/*` (Fase 4 — MapImovel/MapaSection) e portal light (Fase 3 cadastrar empresa).
- Auditoria: `AuditService::log` (evento de negócio 'consulta-viabilidade').

### Testes
- phpunit.xml SQLite; baseline atual ~600 (--exclude-group postgis). Testes que tocam geometria/território real usam @group postgis (PostgisTestCase). Golden cases #[DataProvider] (padrão Fases 5/6). Estrutura: tests/Feature/ConsultaViabilidade/ (ou Viabilidade).
</canonical_refs>

<specifics>
## Specific Ideas
- O orquestrador NÃO decide — propaga o consolidado do motor LOUOS e o encaminhamento do risco. "Sem zona = pendente" é verdade única do motor (não duplicar).
- Consulta anônima auditada (causer sistema, IP); autenticada grava histórico.
- HU-055 é o único bloqueio formal novo da fase (contrato PropertyRegistryLookup).
</specifics>

<deferred>
## Deferred Ideas
- Veredito locacional permitido/não permitido — destrava com a base de zona (SIGIS/CA 2000, Fase 13).
- Resolução por inscrição — destrava com a base de lotes/Cadastro (Fase 13); contrato pronto.
- HU-141 (simulação dentro da solicitação) — Fase 8 reusa o ConsultaViabilidadeService.
</deferred>

---

*Phase: 07-consulta-previa-viabilidade*
*Context gathered: 2026-06-14 via agents analista-negocio + arquiteto-tecnico*
