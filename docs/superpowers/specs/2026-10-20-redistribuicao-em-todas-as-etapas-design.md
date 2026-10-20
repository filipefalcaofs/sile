# Redistribuição em todas as etapas

Data: 2026-10-20

## Problema

Um processo já distribuído só podia trocar de responsável enquanto o status de
análise era Para distribuir, Encaminhado, Analisar ou Em análise. Em vistoria
(Vistoriar/Vistoriado), Para conclusão e convite a troca era bloqueada. Se o
vistoriador ou o analista não pode continuar, o processo fica parado.

## Decisões

- A redistribuição vale em todas as situações, menos Análise concluída.
- Em vistoria, a ficha em preenchimento passa ao novo vistoriador com o rascunho
  (localização, anexos, polígono). A auditoria registra o vistoriador anterior.
- Ficha concluída não muda de dono.
- Processo em Vistoriar só vai para quem tem `preencher-ficha-vistoria`.
- Em Para conclusão, o novo responsável passa a ser também quem conclui
  (`conclusao_encaminhada_para_user_id`).
- O prazo continua o do processo (a redistribuição não recalcula o SLA).

## Telas

- Caixa do setor: o botão "Redistribuir" da aba Distribuídos aparece para as
  novas situações (flag `pode_redistribuir`).
- Consulta de vistorias: ação "Redistribuir" nas linhas Distribuído e Em campo,
  com a lista de vistoriadores, pela rota `gestao.caixa-setor.redistribuir`.

## Testes

- `AnalysisStatusTest`: só Análise concluída bloqueia.
- `VistoriaCaixaDistribuicaoTest`: transferência da ficha, recusa a quem não
  vistoria, ficha concluída preservada e flag na consulta.
- `DistribuicaoServiceTest`: redistribuição em convite e em Para conclusão.
