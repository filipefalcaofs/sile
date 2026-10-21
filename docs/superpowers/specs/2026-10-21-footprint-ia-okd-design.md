# Serviço de IA de edificações no OKD

Data: 21/10/2026. Pedido em conversa ("faça isso" ao pacote para o OKD).

## Por quê

O `docker/footprint-ia` (SAM 2) existe desde 19/10, mas nunca foi construído nem
publicado. Com o Contorno por IA ligado, os processos em que a Overture não acha
o prédio esperam a IA e recebem `indisponivel`.

## O que entra

- `docker/okd/DockerFile_footprint_ia` (vai para a raiz do SEMIT como os outros):
  base `registry.salvador.ba.gov.br/library/php-oracle:8.4`, a única que sabemos
  que o build do OKD alcança; venv Python 3.12 em `/opt/footprint-ia`; PyTorch CPU
  do índice oficial; SAM 2 oficial da Meta fixado no commit `2b90b9f5` (zip do
  GitHub, sem `git`, `SAM2_BUILD_CUDA=0`); pesos `facebook/sam2.1-hiera-small`
  baixados no build (`HF_HUB_OFFLINE=1` em execução); usuário 1001; porta 8080.
  Não usamos o pacote `sam2` do PyPI: é um fork de terceiros.
- `docker/okd/footprint-ia.yaml`: Deployment + Service só interno (sem Route),
  1 réplica, CPU 1–2, memória 2–3 Gi, sondas em `/saude`.
- Passo a passo para quem administra o OKD (artefato compartilhável).
- `requirements-modelo.txt` passa a apontar para o mesmo commit fixado.

## Fora do escopo

Código do serviço e do `GerarPoligonoIaJob` (já testados), licença da ortofoto
2024 (Cartografia), GPU.

## Plano

1. Dockerfile e manifesto.
2. Validar localmente: venv Python 3.12 com as mesmas instalações, testes do
   contrato com o modelo falso e uma segmentação real com os pesos.
3. Passo a passo publicado.
4. Commit na branch e publicação no SEMIT com aprovação.
