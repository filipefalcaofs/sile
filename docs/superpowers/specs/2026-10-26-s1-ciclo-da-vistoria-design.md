# S1 — Ciclo da vistoria

Origem: Lisa (SEDUR), WhatsApp 22/10 19:51–20:23 e 23/10 16:23–16:25.

## Pedidos
- Processo que volta da vistoria vai para a Caixa do Setor, aba Para distribuir, com status Vistoriado e a designação do analista que mandou para a vistoria; a designação ocupa o lugar da coluna Prazo.
- Processo com ficha de vistoria concluída fica disponível para encaminhar ou redistribuir.
- Processo que está na vistoria não aparece na Caixa do Setor.
- Processo finalizado (deferido, indeferido etc.) sai da caixa de vistoria, como já sai da Caixa do Setor e da Caixa do analista.
- A chefia, com o processo em Para conclusão, consegue fazer convite e mandar para a vistoria.

## Como está
- O retorno (`EncaminharVistoriaService::encaminharAoSetor`) já devolve ao setor de origem sem responsável, com `analysis_status = vistoriado`: cai em Para distribuir.
- `CaixaSetorController::foraDaVistoria` (trabalho de 23/10) já tira da Caixa do Setor o que está em vistoria.
- A Consulta de vistorias não filtra a situação do processo: indeferidos continuam listados (caso BAP2601901235).
- Convite e vistoria já aceitam processo em Para conclusão no backend; o botão da ficha aparece para quem tem `analisar-processos`.

## Mudanças
1. Caixa do Setor: linha ganha `designacao` (analista de origem do último encaminhamento à vistoria, senão quem encaminhou) quando o processo está Vistoriado. Na aba Para distribuir a coluna Prazo vira Designação; nas outras abas o Prazo fica.
2. Consulta de vistorias: o universo só traz processos em andamento (`em_analise`, `em_pendencia`). Processo com resultado sai da lista e dos contadores.
3. Testes de regressão para convite e vistoria a partir de Para conclusão pela chefia.

## Fora
- Retorno automático ao analista de origem: a regra da SEDUR é passar pela distribuição.
