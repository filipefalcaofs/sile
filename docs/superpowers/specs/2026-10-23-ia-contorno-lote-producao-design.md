# IA de contorno de lote: modelo treinado em produção

Data: 23/10/2026. Continuação de `2026-10-21-treino-ia-contorno-lote-design.md`.

## Por quê

O treino local do decodificador do SAM 2.1 com lotes do CODLOG mostrou, em lotes
de teste de bairros fora do treino, que o modelo treinado com recorte crescente
(variante E) contorna o lote inteiro muito melhor que o serviço atual (IoU
mediano 0,45–0,49 contra 0,06). A LLM como juíza dos candidatos não superou o
modelo treinado (melhor caso 0,41 com gpt-5.5/gpt-6.1-sol; 0,23 com o
gpt-4o-mini cadastrado no Viabiliza) e fica de fora.

Dois defeitos de produção apareceram na investigação:

1. A ortofoto 2024 tem buracos: o exportImage devolve HTTP 200 com uma imagem de
   cor única. O `OrtofotoClient` aceita a imagem e nunca cai na ortofoto
   2016–2017; o serviço contorna um quadrado vazio.
2. O recorte é sempre de 60 m e o aceite recusa contorno que encosta na borda:
   lote maior que o recorte nunca é aceito.

## Decisões

### Serviço `footprint-ia`

- `FOOTPRINT_IA_PESOS`: caminho de um arquivo de pesos do decodificador. Quando
  definido, o serviço carrega esses pesos sobre o `sam_mask_decoder` do modelo
  base. Sem a variável, comportamento atual. O nome do modelo na resposta passa a
  identificar os pesos.
- A resposta de `/segmentar` ganha `candidatos`: as três máscaras do SAM, cada uma
  com `anel`, `score` e `encosta` (anel a até 2 px da borda). Os campos `anel` e
  `score` continuam iguais (máscara de maior score), para compatibilidade.
- Os pesos vão na imagem (`docker/footprint-ia/pesos/`), como o modelo base, que
  já é baixado no build porque o contêiner sobe sem internet.

### Viabiliza

- `OrtofotoClient`: imagem em que metade ou mais dos pixels tem a mesma cor é
  tratada como indisponível; o cliente tenta o próximo serviço (2016–2017). Sem a
  extensão GD, a checagem não roda (comportamento atual).
- `GerarPoligonoIaJob`: recorte crescente, a mesma regra da variante E avaliada.
  Para cada lado em `viabiliza.edificacoes.ia.lados_recorte_m` (60, 120, 240):
  recorta, segmenta e separa os candidatos que não encostam na borda. Se nenhum
  encosta, ou se é o último lado, fica o de maior score entre os que não
  encostam; senão, passa ao lado seguinte.
- `AceiteMascaraIa`: limite de área do lote alinhado ao treino (até 20.000 m²,
  antes 5.000 m², que era limite de edificação). Score mínimo configurável
  (`aceite.score_min`), com o valor tirado da validação.

### Treino e escolha do modelo

- Validação: 10% dos lotes de treino, por bairro, separados antes do treino. O
  teste (bairros sorteados no conjunto) só é usado uma vez, para o resultado
  final.
- O treino grava os pesos de cada época; a época e a taxa de aprendizado são
  escolhidas pela validação.

## Fora do escopo

- LLM como juíza no Viabiliza.
- GPU no OKD.
- Publicar no SEMIT: a entrega fica pronta na branch, com o procedimento; a
  publicação depende do usuário.
