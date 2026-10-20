# footprint-ia

Serviço de inferência que o Viabiliza chama como último recurso para obter o
polígono de um imóvel. Ele recebe um recorte da ortofoto da Prefeitura e o
ponto do imóvel em pixels, e devolve o contorno da edificação que contém esse
ponto. O georreferenciamento e o aceite (área, compacidade, borda, ponto
contido) ficam no Laravel (`GerarPoligonoIaJob`).

## Contrato

- `GET /saude` → `{"status": "ok", "modelo": "<modelo>@<dispositivo>"}`
- `POST /segmentar` (multipart):
  - `imagem`: o PNG do recorte;
  - `x`, `y`: o ponto em pixels;
  - `largura`, `altura`: o tamanho da imagem.

  A resposta é:

  ```json
  {"status": "ok" | "sem_objeto", "anel": [[px, py], ...], "score": 0.93, "modelo": "sam2.1-hiera-small@cpu"}
  ```

  O `score` é o IoU previsto pelo SAM. Ele não é uma confiança calibrada, e o
  Viabiliza o guarda apenas como diagnóstico. Um ponto fora da imagem ou uma
  imagem inválida devolvem 422.

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
