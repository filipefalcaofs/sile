# Serviço de inferência de edificações (SAM 2) usado pelo Viabiliza como
# último recurso para o polígono do lote. CPU por padrão; para GPU, troque o
# índice do PyTorch e rode com FOOTPRINT_IA_DEVICE=cuda.
FROM python:3.12-slim

ARG TORCH_INDEX=https://download.pytorch.org/whl/cpu
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    FOOTPRINT_IA_MODELO=facebook/sam2.1-hiera-small \
    FOOTPRINT_IA_DEVICE=cpu \
    HF_HOME=/opt/hf

RUN apt-get update \
    && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements-base.txt requirements-modelo.txt ./
RUN pip install --no-cache-dir torch torchvision --index-url "$TORCH_INDEX" \
    && pip install --no-cache-dir -r requirements-modelo.txt

# Baixa os pesos no build: o contêiner sobe sem acesso à internet.
RUN python -c "from huggingface_hub import snapshot_download; import os; snapshot_download(os.environ['FOOTPRINT_IA_MODELO'])"

COPY app.py ./
# Decodificador treinado com os lotes do CODLOG (scripts/treino-ia). O arquivo
# precisa estar em pesos/; para usar o SAM 2 original, apague a variável.
COPY pesos/ /opt/pesos/
ENV FOOTPRINT_IA_PESOS=/opt/pesos/decodificador-lote.pt

EXPOSE 8080
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8080", "--workers", "1"]
