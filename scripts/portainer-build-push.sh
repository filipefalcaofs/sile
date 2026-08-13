#!/usr/bin/env bash
# Build AMD64 + push da imagem SILE para o GitHub Container Registry (GHCR).
# O Portainer (stack sile-app) puxa ghcr.io/filipefalcaofs/sile:latest.
#
# Uso:
#   ./scripts/portainer-build-push.sh
#   ./scripts/portainer-build-push.sh ghcr.io/filipefalcaofs/sile:latest
#
# Pré-requisito: docker login ghcr.io -u USERNAME --password-stdin <<< "$GITHUB_TOKEN"
# (token com scope write:packages / read:packages)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE="${1:-ghcr.io/filipefalcaofs/sile:latest}"

cd "$ROOT"

echo "==> Build ${IMAGE} (linux/amd64)..."
docker buildx build \
  --platform linux/amd64 \
  -t "${IMAGE}" \
  --push \
  .

echo "==> OK: ${IMAGE}"
