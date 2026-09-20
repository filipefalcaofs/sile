# Quick — fluxo motor → caixa do setor → Apoio → analista → ficha

**Status:** executado
**Origem:** pedido do usuário em 2026-09-20 — simular de ponta a ponta o fluxo real de tramitação dos processos que o motor encaminha para análise, sem fluxo paralelo.

## Diagnóstico (mapeamento 2026-09-20)

- Motor (`FluxoExpressoService::encaminharAnalise`) grava `em_analise` + SLA, mas deixa `sector_id` nulo — processo fica órfão de caixa (roteamento automático ao setor = pendência SEDUR).
- Caixa do setor existe e é real (`/gestao/caixa-setor`, `DistribuicaoService` distribuir/assumir), mas exige `analisar-processos` para ser vista e não está no menu.
- Não existe perfil Apoio (só `cidadao`, `analista`, `gestor`, `administrador`); tramitação é privilégio do gestor.
- Ficha de análise funciona de ponta a ponta; acesso por `analisar-processos` (decisão: manter, paridade SAPS).

## Decisões do usuário (2026-09-20)

- Perfis Chefe de setor / Subcoordenadora / Coordenadora: **pendência registrada** (sem função definida = fachada de perfil).
- Gate da ficha por atribuição: **manter como está**.

## Tarefas

- [x] Parâmetro `analise.setor_triagem_id` (HU-014) + motor preenche `sector_id` no encaminhamento (fallback: nulo quando não configurado)
- [x] Perfil `apoio` (sem `analisar-processos`, com `distribuir-processos`) + usuário dev `apoio@sile.dev` no setor
- [x] Caixa do setor visível a `analisar-processos|distribuir-processos`; botão Assumir só para quem analisa
- [x] Item "Caixa do setor" no menu Operação (catálogo passa a aceitar permissão anyOf)
- [x] Feature test E2E: motor → caixa do setor → apoio distribui → analista vê na fila → abre ficha (`FluxoApoioTramitacaoTest`)
- [x] Seeds dev + verificação integral (pint, suíte filtrada, tsc/build)
- [x] STATE.md: registrar mudança + pendência dos perfis hierárquicos
