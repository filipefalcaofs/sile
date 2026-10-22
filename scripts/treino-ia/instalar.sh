#!/bin/sh
# Instala o ambiente do treino dentro do volume persistente, para sobreviver
# às trocas de pod. Uso: sh scripts/treino-ia/instalar.sh
set -eu

RAIZ="${TREINO_IA_RAIZ:-/opt/app-root/src/storage/app/private/treino-ia}"
AQUI="$(cd "$(dirname "$0")" && pwd)"
PY="$RAIZ/py"
SAM2_ZIP="https://github.com/facebookresearch/sam2/archive/2b90b9f5ceec907a1c18123530e92e794ad901a4.zip"

mkdir -p "$RAIZ/logs"

if [ ! -x "$PY/bin/python" ]; then
  python3 -m venv --without-pip "$PY"
  "$PY/bin/python" -m ensurepip --upgrade
fi

"$PY/bin/python" -m pip install --no-cache-dir --upgrade pip setuptools wheel
"$PY/bin/python" -m pip install --no-cache-dir torch torchvision --index-url https://download.pytorch.org/whl/cpu
"$PY/bin/python" -m pip install --no-cache-dir -r "$AQUI/requirements.txt"
SAM2_BUILD_CUDA=0 "$PY/bin/python" -m pip install --no-cache-dir --no-build-isolation "SAM-2 @ $SAM2_ZIP"

HF_HOME="$PY/hf" "$PY/bin/python" -c "from huggingface_hub import snapshot_download; snapshot_download('facebook/sam2.1-hiera-small')"
"$PY/bin/python" -c "import torch, sam2; print('ok: torch', torch.__version__)"

echo "Ambiente pronto em $PY. Use: HF_HOME=$PY/hf $PY/bin/python -m treino_ia situacao"
