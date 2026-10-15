# Evidência — XML da JUCEB sem a pergunta pendente

Fonte: `regin_recebimentos.envelope` do Viabiliza (ambiente SEDUR), consulta de 14/10/2026. Contagem de ocorrências do trecho, sem diferenciar maiúsculas, no XML e no JSON do envelope.

| BAP | Gerado pela JUCEB | Recebido no Viabiliza | Bytes do XML | SHA-256 do XML | Trecho procurado | No XML | No JSON |
|---|---|---|---|---|---|---|---|
| BAP2601843931 | 2026-10-13T16:45:06 | 2026-10-14 15:27:21 | 11321 | 47a9cbe3649a3e136b993f0c8ca34edc46521fcc048eced14d58c8bed95eb3bf | PERGUNTA | 0 | 0 |
| BAP2601864396 | 2026-10-14T14:42:49 | 2026-10-14 18:43:08 | 23186 | fc782d4bc709e50fdb83e147b3e1b1bbed0f133a8788d91b04de0e5d2b4021bc | desenvolvida no local | 0 | 0 |
| BAP2601857871 | 2026-10-14T10:50:47 | 2026-10-14 15:42:28 | 24658 | 4b043d997acbdc35cd1a01d530aa442253ebf40c8f9b98bfb803cd5ed1b047a6 | produtos ser | 0 | 0 |
| BAP2601862217 | 2026-10-14T12:43:54 | 2026-10-14 16:34:46 | 28117 | cff67531fe11644827dfcb736d26abe4d79b059ccb590246ed699a612d0faba4 | tipo de associa | 0 | 0 |
| BAP2601854356 | 2026-10-14T12:43:18 | 2026-10-14 16:24:39 | 25377 | 7daa735793d9ee4a4a6b90b3f34bb45efc2ebdb3d6139c8951faa4e93864d4d8 | funcionar no local | 0 | 0 |

Observações:

- BAP2601843931: o XML não tem o grupo `GROUPPERGUNTAS`; nenhuma pergunta foi enviada.
- BAP2601864396: o XML tem 19 perguntas (`PER_PERGUNTA`); "A atividade será desenvolvida no local?" não está entre elas.
- BAP2601862217 e BAP2601854356: a JUCEB enviou "A atividade será desenvolvida no local? Sim" mas não enviou a pergunta seguinte exigida pela planilha (tipo de associação; o que irá funcionar no local).
- Os acentos do XML chegam corrompidos (`serÃ¡`); por isso os trechos procurados são ASCII.
- Para contestação: pedir à JUCEB o XML original do mesmo protocolo e comparar o SHA-256.

- Reconciliação nos 113 BAPs (14/10/2026): o número de ocorrências de `<PERGUNTAS>` contadas no texto do XML é igual ao número de perguntas lidas pelo Viabiliza em todos; divergentes = 0. O Viabiliza grava o envelope recebido sem editar o XML (`ReginRecebeService::receive`).
