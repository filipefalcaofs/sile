# Polígono complementar nos processos em análise

Data: 21/10/2026. Aprovado em conversa.

## Por quê

A Overture foi ligada em 21/10 com 591.601 prédios. Processos que entraram antes
ficaram sem polígono do lote (ex.: 2795, ponto dentro de um prédio). Eles foram
para análise por regra, não por falta de polígono, então a decisão fica; o que
falta é o polígono e a zona pela maior porção.

## Comando

`php artisan geo:poligono:reprocessar-abertos [--dry-run]`, rodado à mão no pod.

- **Processos:** status `em_analise` ou `em_pendencia`, sem polígono do lote
  (`address_polygon_geojson` nulo) e sem ficha de análise finalizada. Decididos
  e encerrados (deferida, indeferida, cancelada, desativada, cassada, revogada)
  nunca entram.
- **Fontes:** só as edificações complementares pelo ponto, na ordem de sempre
  (Overture, Microsoft), as que estiverem ligadas. IA sem espera (não há
  serviço). Endereçamento SEDUR e SEMAP não rodam de novo: já foram tentados na
  entrada e a busca pelo ponto no endereçamento leva ~30 s por processo.
- **Achou:** grava polígono e procedência como num processo novo
  (`PoligonoComplementarService`) e recalcula a zona pela maior porção
  (`ZonaProcessoService::materializar`, auditado). Empate mantém a zona do ponto.
- **Não muda:** decisão, encaminhamento, analista, setor, etapas do motor.
- **Sem ponto ou ponto inelegível:** contado e deixado como está.
- **Sem fonte complementar ligada:** o comando para com erro.
- **`--dry-run`:** consulta a base e calcula a zona sem gravar nada.
- **Saída:** contadores (vistos, com polígono, sem ponto, ponto inelegível, sem
  prédio) e a lista dos processos que mudaram de zona (id, protocolo, de, para,
  fonte), para avisar os analistas. Execução real fica na auditoria.

## Plano

1. Teste de feature do comando com fakes da base e da maior porção: grava e
   recalcula; empate mantém zona; deferida e ficha finalizada ficam de fora; sem
   ponto conta e não mexe; dry-run não grava e lista; sem fonte ligada falha.
2. Comando `ReprocessarPoligonoAbertosCommand`.
3. Suíte de Geo, Pint, commit na branch `feat/poligono-complementar-edificacoes`.
4. Publicar no SEMIT com aprovação; rodar primeiro com `--dry-run` no pod.
