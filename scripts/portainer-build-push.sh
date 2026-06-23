#!/usr/bin/env bash
# Build AMD64 + push da imagem SILE para Docker Hub (servidor Portainer).
# Uso: ./scripts/portainer-build-push.sh [tag]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE="${1:-filipefalcaofs97/sile:latest}"

cd "$ROOT"

echo "==> Build ${IMAGE} (linux/amd64)..."
docker buildx build \
  --platform linux/amd64 \
  -t "${IMAGE}" \
  --push \
  .

echo "==> OK: ${IMAGE}"
