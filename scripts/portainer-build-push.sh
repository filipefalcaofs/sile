#!/usr/bin/env bash
# Fallback: build AMD64 + push da imagem SILE para o GHCR.
# O fluxo padrão é o Portainer clonar a main e fazer o build no host
# (docker-compose.portainer-full.yml com pull_policy: build). Use este script
# só se o build no servidor estiver indisponível.
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

APP_VERSION="$(tr -d '[:space:]' < VERSION)"
APP_REVISION="$(git rev-parse --short HEAD)"

echo "==> Build ${IMAGE} (linux/amd64) v${APP_VERSION} · ${APP_REVISION}..."
docker buildx build \
  --platform linux/amd64 \
  --build-arg "APP_VERSION=${APP_VERSION}" \
  --build-arg "APP_REVISION=${APP_REVISION}" \
  -t "${IMAGE}" \
  --push \
  .

echo "==> OK: ${IMAGE}"
