# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-09)

**Core value:** Responder a viabilidade locacional de atividade econômica de forma automática, correta e auditável — fluxo expresso quando a lei permite, fundamentação legal em toda decisão.
**Current focus:** Fase 1 — Identidade, Acesso e Auditoria Transversal

## Current Position

Phase: 1 of 15 (Identidade, Acesso e Auditoria Transversal)
Plan: — (fase ainda não planejada)
Status: Ready to plan
Last activity: 2026-06-09 — Roadmap criado (15 fases, 131/131 HUs mapeadas)

Progress: [░░░░░░░░░░] 0%

## Performance Metrics

**Velocity:**
- Total plans completed: 0
- Average duration: —
- Total execution time: 0 h

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| — | — | — | — |

**Recent Trend:**
- Last 5 plans: —
- Trend: —

*Atualizado após cada plano concluído*

## Accumulated Context

### Decisions

Registro completo na tabela Key Decisions de PROJECT.md. Mais relevantes para o trabalho atual:

- Auditoria transversal (RN-002: usuário, data/hora, origem, ação, resultado, versão de regras) nasce como infraestrutura na Fase 1; a Fase 12 só consulta/exporta/LGPD.
- Requisitos rastreados pelo ID da HU (HU-001 a HU-131); cada CA BDD vira feature test PHPUnit — nenhuma HU concluída sem CAs passando.
- Mantenedores de quadros/condicionantes/risco (HU-015 a HU-020) entram junto com os motores (Fases 5 e 6) — fatia vertical.
- Motor de regras parametrizável: regras como dados versionados, nunca código; carga oficial (planilhas SEDUR) substitui seeds quando entregue.
- Integrações atrás de contratos; adaptador só é concluído contra homologação real — sem acesso = feature explicitamente bloqueada, nunca simulada.
- Risco municipal (Decreto 32.636/2020) e risco sanitário (planilha VISA) são dimensões separadas no modelo.
- Parametrização máxima (HU-014): nenhum valor de negócio hardcoded; funcionalidades acopláveis com feature toggle administrável.

### Pending Todos

Nenhum.

### Blockers/Concerns

Pendências com a SEDUR (pauta: docs/ANALISE-HUs-REUNIAO-SEDUR.md seção 5). Nenhuma bloqueia as Fases 1 a 3.

- [Fase 8] HU-071/HU-072 (DAM e pagamento): escopo a confirmar — não implementar antes da confirmação.
- [Fase 13] HU-110 (SEFAZ): API confirmada; endpoint de envio do deferimento e credenciais SenhaWeb pendentes.
- [Fase 13] HU-111 (migração do legado .NET): estratégia a definir.
- [Fases 3/13] Contrato REDESIM/integrador (entrada e devolução de parecer): aguardando documentação.
- [Fases 4/13] Base GIS municipal (camadas, formato, acesso): aguardando — Fase 4 opera com camadas carregadas de dados oficiais da LOUOS até a entrega.
- [Fase 5] Quadros parametrizados vigentes e correspondência "Quadro 11" ↔ 11B: a confirmar — motor nasce parametrizável.
- [Fase 13] Acesso a ambiente de homologação (integrador/SEFAZ/GIS): solicitado.

## Session Continuity

Last session: 2026-06-09
Stopped at: ROADMAP.md, STATE.md e rastreabilidade de REQUIREMENTS.md criados; aguardando /gsd-plan-phase 1
Resume file: None
