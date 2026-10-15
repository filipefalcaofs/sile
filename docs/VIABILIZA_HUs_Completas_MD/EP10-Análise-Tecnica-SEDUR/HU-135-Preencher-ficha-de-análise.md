# HU-135 — Preencher ficha de análise

> **Status: Confirmada (2026-06-11)** — mapeada a partir do SAPS legado (prints `docs/legado-saps/02`–`08`). Coração da análise humana e do fluxo semi-expresso.

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Permitir ao analista registrar análise por CNAE, condicionantes, vagas de estacionamento, parecer e revisões versionadas na ficha de análise.

## História de Usuário
**Como** analista SEDUR,  
**quero** preencher a ficha de análise do processo,  
**para** deferir, indeferir ou manter atividades em análise com fundamentação completa.

## Fluxo Principal
1. Analista abre processo na caixa do setor (HU-080/HU-081).
2. Por **CNAE**: seleciona Deferida / Indeferida / Análise; visualiza resposta à condicionante, descrição LOUOS, grupo uso, TLL/valor e **gatilhos CNAE** (motivo de análise).
3. Analista marca condicionantes que compõem o documento (checkboxes + adicionais em texto livre).
4. Seção **Vagas estacionamento**: compara dados requerente × exigido LOUOS/CNLU → veredito conforme/não conforme; recálculo e vagas vistoria quando aplicável.
5. Analista registra **parecer** (texto livre), salva rascunho ou **finaliza ficha**.
6. **Fichas de revisão** versionadas (nova revisão, histórico por data/analista, imprimir).
7. Ao finalizar: deferimento (HU-086) exige todas deferidas; uma indeferida → indeferimento do processo (HU-087).

## Regras de Negócio
- RN-001: Gatilhos CNAE parametrizados (enquadramento analista, ZEIS especial etc.) exibidos e registrados como motivo de análise.
- RN-002: Analista pode incluir atividade (CNAE) adicional quando permitido.
- RN-003: Revisões versionadas imutáveis após finalização — nova revisão cria nova ficha.
- RN-004: Dados SIGIS (zona/via) exibidos na ficha (HU-107).
- RN-005: A ficha abre **pré-preenchida pelo motor** (HU-140) quando disponível; o painel de **precedentes** do imóvel e do CNAE na zona (HU-142) é exibido junto.
- RN-006: A ficha exibe **mini-mapa permanente** (polígono + zona/via/ZEIS sobrepostos) ao lado dos dados de enquadramento — o analista vê o contexto territorial sem trocar de tela.
- RN-007: Entre revisões, o sistema oferece **comparação (diff)**: o que mudou da revisão anterior para a atual (campos, condicionantes, status por CNAE, parecer).
- RN-008: Rascunho com salvamento automático (autosave) — perda de sessão não perde trabalho da ficha.
- RN-009: Campos de texto da ficha (parecer, condicionantes adicionais) oferecem a **biblioteca de textos-padrão** (HU-085 RN-004) para inserção rápida e consistente.

## Dependências
- HU-015, HU-019, HU-038, HU-107, HU-086, HU-087, HU-132.

## Prioridade
Alta
