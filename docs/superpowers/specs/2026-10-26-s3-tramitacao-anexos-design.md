# S3 — Ficha por analista e Tramitação como histórico

Origem: Lisa 22/10 19:53–19:54 e áudios de 23/10 (14:22, 14:48, 14:50).

## Já atendido (HU.183, homologação v0.127.0, e trabalho de 23/10)
- Cada analista tem a própria ficha; a ficha de outro não é sobrescrita (`FichaDeOutroAnalistaException`).
- Nova ficha da mesma pessoa vem preenchida com a última dela (`AnalysisRecordService::novaRevisao`).
- Tramitação lista cada ficha salva e finalizada com link; a ficha de vistoria concluída tem link (`vistoria/visualizar?ficha=`).
- Resultado final pela ficha usada na conclusão (`viability_decisions.analysis_record_id`).

## Falta
- Anexos visíveis para todos que consultam o processo. Hoje a tela do processo não lista documento nenhum.

## Mudança
Card "Anexos" na tela do processo (`gestao/processos/show`) com os documentos do processo (`ViabilityRequestDocument`) e os anexos das fichas de vistoria (`InspectionAttachment`), cada um com nome, origem, data e link de download. Download de documento do processo por rota nova com a permissão de consulta do processo; anexo de vistoria usa a rota que já existe, liberada também para `consultar-solicitacoes`.
