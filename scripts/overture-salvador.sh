#!/usr/bin/env bash
# Extrai as edificações da Overture no retângulo de Salvador (com as ilhas) em
# GeoJSONSeq, para o comando geo:edificacoes:importar-overture.
#
# Uso: scripts/overture-salvador.sh <release> <arquivo-de-saida>
#   release: a release que o CLI baixar (o CLI usa sempre a mais recente;
#            confira em https://docs.overturemaps.org/release-calendar/).
#
# Requer o CLI oficial: pipx install overturemaps
#
# Para fixar uma release antiga, use DuckDB (spatial + httpfs):
#   COPY (
#     SELECT id, sources, height, num_floors, class, subtype, geometry
#     FROM read_parquet('s3://overturemaps-us-west-2/release/<release>/theme=buildings/type=building/*', hive_partitioning=1)
#     WHERE bbox.xmin BETWEEN -38.70 AND -38.29 AND bbox.ymin BETWEEN -13.02 AND -12.73
#   ) TO '<saida>' WITH (FORMAT GDAL, DRIVER 'GeoJSONSeq');
#
# O recorte fino pelo limite oficial do município acontece na importação.
set -euo pipefail

if [ $# -ne 2 ]; then
  echo "uso: $0 <release> <arquivo-de-saida>" >&2
  exit 2
fi

release=$1
saida=$2

overturemaps download --bbox=-38.70,-13.02,-38.29,-12.73 -f geojsonseq --type=building -o "$saida"

echo "Gerado $saida. Importe com:"
echo "  php artisan geo:edificacoes:importar-overture $saida --release=$release"
