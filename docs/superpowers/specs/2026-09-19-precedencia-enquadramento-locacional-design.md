# Spec — Precedência do enquadramento locacional (Q10 ∧ Q11A) sobre o risco

Data: 2026-09-19
Origem: conversa desta data (o 43747 ia à análise por galpão apesar do 11A vedar).
Complementa: `2026-09-19-enquadramento-planilha-sem-quadro7-design.md` §6 (precedência do consolidado) e HU-041 RN-005 / HU-044 / HU-075 RN-009.

## 1. Decisão

O motor LOUOS já consolida `nao_permitido` quando o Quadro 10 proíbe **ou** o Quadro 11A é `Não`. Isso não muda.

O que muda é a **ordem no fluxo expresso**: o veredito locacional `nao_permitido` indefere **antes** de qualquer avaliação de risco, gatilho ou sede virtual.

```
resolve() fresco
  → temAltoRisco()                → análise (RN-041-B, ver abaixo)
  → consolidado == nao_permitido  → INDEFERE (automático, sem TVL)
  → consolidado == pendente       → análise (falta zona/via ou 11A = R)
  → ! elegivelExpresso()          → análise (médio, gatilhos)
  → gatilho sede virtual          → análise
  → permitido / permitido_com_condicoes → DEFERE
```

Risco e gatilhos (galpão, ZEIS, sede 8211, mapa do Decreto) **não são removidos**. Só passam a rodar em processo já permitido nos dois quadros.

## 1.1 RN-041-B — Alto risco nunca é decidido automaticamente

Complemento fechado na mesma conversa: **alto risco vai à análise mesmo quando o
motor já tem veredito locacional** — permitido ou não permitido. O que determina
o envio à análise é o grau de risco, vindo do CNAE (Decreto 32.636/2020 ou ramo
da planilha) ou de pergunta condicional que o eleve (ex.: P3 artesanal → ID3-11;
condicionante sanitária com `reclassifica_para: alto`).

Consequência: o indeferimento automático por veto locacional (RN-041-A) vale
para **baixo e médio** risco. No alto risco, o veto vira fundamentação para o
analista — a decisão é humana. O 43747 (baixo_a + galpão + 11A `Não`) continua
indeferido automaticamente: galpão é gatilho, não nível alto.

Implementação: o encaminhamento do motor de risco expõe `nivel` (nível
decisivo: ramo da planilha, Decreto ou nível sanitário reclassificado);
`ResolvedViability::temAltoRisco()` consolida por CNAE; o expresso checa antes
do veto. Gatilhos não contaminam o nível — `dados_do_processo` (galpão) muda o
fluxo, não o `nivel`.

## 2. Por quê

A análise humana deve receber só o que ainda pode ser deferido. Impedimento no 10 ou no 11A é decisão de lei, não de rito. Avaliar risco primeiro mandava o 43747 (galpão + 11A `Não` na VL) para a fila.

## 3. Critérios de aceite

**CA-01** — DADO Quadro 10 proibido e CNAE que o motor de risco encaminharia à análise (ex. galpão), QUANDO o expresso decide, ENTÃO status `indeferida`, sem TVL, com `ResultadoEmitido`.

**CA-02** — DADO protocolo 43747 (ZPR 3, VL, galpão) com planilha + Quadros 10 e 11A vigentes, QUANDO a simulação fecha as perguntas, ENTÃO consolidado `nao_permitido` e processo `indeferida`. Não vai à análise.

**CA-03** — DADO permitido nos dois quadros e gatilho galpão, QUANDO decide, ENTÃO segue em análise (regra de risco inalterada).

**CA-04** — DADO 11A = `R` ou zona/via ausente, QUANDO decide, ENTÃO `pendente` → análise. Falta de dado não é veto.

**CA-05** — DADO alto risco por pergunta condicional (1340-5/01 + P3 artesanal → ID3-11) e Quadro 10 proibido, QUANDO decide, ENTÃO `em_analise`, sem decisão e sem evento — e o consolidado locacional é `nao_permitido` (veto presente como fundamentação).

**CA-06** — DADO alto risco pelo CNAE (0210-1/07 no local → ID2-07) e Quadro 10 proibido, QUANDO decide, ENTÃO `em_analise`, sem decisão e sem evento.

**CA-07** — DADO baixo/médio risco com veto locacional, QUANDO decide, ENTÃO indeferimento automático (RN-041-A inalterada para não-alto).

## 4. Fora de escopo

- Alterar o consolidado do LOUOS, o mapa de risco ou o catálogo de gatilhos.
- Inventar zona/via ou matriz do 11B.
- Mudança de UI: a tela lê `status`/`tvl` gravados.
