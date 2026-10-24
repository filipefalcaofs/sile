# footprint-ia

Serviço de inferência que o Viabiliza chama como último recurso para obter o
polígono de um imóvel. Ele recebe um recorte da ortofoto da Prefeitura e o
ponto do imóvel em pixels, e devolve o contorno que contém esse ponto e os
candidatos das três máscaras do SAM 2.1. Com `FOOTPRINT_IA_PESOS`, o
decodificador treinado com os lotes do CODLOG (`scripts/treino-ia`) substitui o
original e o contorno passa a ser o do lote inteiro, com estacionamento e
recuos. O georreferenciamento, o recorte crescente (60, 120 e 240 m) e o aceite
(área, compacidade, borda, ponto contido, score mínimo) ficam no Laravel
(`GerarPoligonoIaJob`, `AceiteMascaraIa`).

## Contrato

- `GET /saude` → `{"status": "ok", "modelo": "<modelo>@<dispositivo>"}`
- `POST /segmentar` (multipart):
  - `imagem`: o PNG do recorte;
  - `x`, `y`: o ponto em pixels;
  - `largura`, `altura`: o tamanho da imagem.

  A resposta é:

  ```json
  {
    "status": "ok" | "sem_objeto",
    "anel": [[px, py], ...],
    "score": 0.93,
    "candidatos": [{"anel": [[px, py], ...], "score": 0.93, "encosta": false}, ...],
    "modelo": "sam2.1-hiera-small+decodificador-lote@cpu"
  }
  ```

  `anel` e `score` são os do candidato de maior score. Cada candidato é o
  contorno de uma das três máscaras que contém o ponto; `encosta` diz se o
  contorno chega a 2 px da borda (lote maior que o recorte). O `score` é o IoU
  previsto pelo modelo. Um ponto fora da imagem ou uma imagem inválida devolvem
  422.

## Pesos treinados

`FOOTPRINT_IA_PESOS` aponta para o arquivo de pesos do decodificador, carregado
com `torch.load(weights_only=True)`. Se a variável aponta para um arquivo que
não existe, o serviço falha na primeira segmentação com mensagem clara, em vez
de usar o modelo original sem avisar. O `Dockerfile` copia `pesos/` para
`/opt/pesos/` e usa `decodificador-lote.pt`.

## Build e execução

```bash
docker build -t viabiliza/footprint-ia docker/footprint-ia
docker run -p 8080:8080 viabiliza/footprint-ia
```

Depois, configure a URL (`http://<host>:8080`) em Configuração de APIs, na aba
"Edificações complementares", e ligue a IA.

Para usar GPU, faça o build com
`--build-arg TORCH_INDEX=https://download.pytorch.org/whl/cu124` e rode com
`--gpus all -e FOOTPRINT_IA_DEVICE=cuda`. Em CPU, cada recorte de 800 × 800 px
leva de segundos a dezenas de segundos. Isso é suficiente para as dezenas de
casos por dia que chegam a este passo.

## Implantação no OKD (homologação e depois produção)

A imagem do OKD é gerada do `DockerFile_footprint_ia` da raiz do repositório no
GitLab do SEMIT, que copia o código de `app-src/docker/footprint-ia/`. Para
levar o modelo treinado:

1. Publicar no SEMIT, pelo fluxo incremental de sempre, os arquivos do serviço
   (`app.py`, `pesos/decodificador-lote.pt`) e os do Viabiliza
   (`GerarPoligonoIaJob`, `InferenciaEdificacaoClient`, `MascaraIa`,
   `OrtofotoClient`, `AceiteMascaraIa`, `config/viabiliza.php` e os testes).
2. No `DockerFile_footprint_ia`, depois do `COPY ... app.py`, acrescentar:

   ```dockerfile
   COPY app-src/docker/footprint-ia/pesos/decodificador-lote.pt /opt/footprint-ia/pesos/
   ENV FOOTPRINT_IA_PESOS=/opt/footprint-ia/pesos/decodificador-lote.pt
   ```

   O `chown`/`chmod` que já vem depois cobre a pasta nova. O `torch==2.5.1` do
   arquivo carrega os pesos (verificado com `torch.load(weights_only=True)`).
3. Aplicar `okd-deployment.yaml` no namespace de homologação, com a imagem
   gerada. O serviço é interno (sem Route).
4. No Viabiliza de homologação, em Configuração de APIs, aba "Edificações
   complementares": URL do serviço `http://viabiliza-footprint-ia:8080` e a IA
   ligada (`features.edificacoes_ia`).
5. Conferir: `GET /saude` responde com o modelo
   `sam2.1-hiera-small+decodificador-lote@cpu`; um processo sem polígono do
   CODLOG, Overture ou Microsoft gera o contorno pela IA e a resolução fica
   registrada em `lot_polygon_resolutions` (fonte `ia`), com o motivo de
   recusa quando não aceito.
6. Produção: o mesmo procedimento, depois de conferir em homologação os
   contornos aceitos de alguns processos reais.

Voltar atrás: definir `FOOTPRINT_IA_PESOS` vazio no deployment (`env`) devolve
o SAM 2 original sem novo build; desligar `features.edificacoes_ia` tira a IA do
fluxo.

## Testes

Os testes usam um modelo falso (`FOOTPRINT_IA_STUB=1`), sem baixar os pesos:

```bash
cd docker/footprint-ia
docker run --rm -v "$PWD":/app -w /app python:3.12-slim \
  sh -c "pip install -q -r requirements-teste.txt && FOOTPRINT_IA_STUB=1 python -m pytest -q"
```

## Licenças

- **SAM 2** (Meta): código e pesos `sam2.1-hiera-*` sob Apache 2.0.
- **FastAPI**: MIT. **OpenCV**: Apache 2.0. **Pillow**: HPND.
