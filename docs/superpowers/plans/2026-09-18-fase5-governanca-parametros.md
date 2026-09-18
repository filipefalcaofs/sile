# Fase 5 — Governança de parâmetros

## Objetivo

Separar parâmetro operacional (vale no PUT) de parâmetro decisório (só vale após quatro olhos). O valor vigente permanece intacto até um segundo usuário com `manter-parametros` aprovar.

## Chaves decisórias (travadas)

- `risco.mapa_encaminhamento`
- `risco.dimensao_tvl`
- `analise.escritorio_virtual.cnae_gatilho_sede`
- `features.fluxo_expresso`
- `features.simulacao_protocolo`

## Modelo

- `parameters.governance` (`operational` | `decision`)
- `parameter_proposals` (proposta coexistente: valor proposto, autor, status, revisor)
- Uma pendente por parâmetro; nova proposta supersede a anterior (rejeita a antiga)
- Autor ≠ aprovador (`FourEyesViolationException`)
- Autor pode rejeitar (desistir)

## Validação

- `json_fluxo_map`: objeto com chaves exatas de `RiscoMunicipal` e valores de `Fluxo`

## Catálogo (+3)

- `solicitacao.duplicidade.janela_dias` (180)
- `ia.auditoria_preditiva.pesos`
- `ia.auditoria_preditiva.cortes_severidade`

Baseline: 118 → 121.

## Fora desta fase

- 5.4 vagas/capacidades: pendência SEDUR — não inventar dado
- 6.2/6.3 (Q1 baixo_c, Q2 malha-fina × AnalysisCategory): bloqueados
