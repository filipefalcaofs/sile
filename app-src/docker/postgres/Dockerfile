# PostgreSQL 16 + PostGIS 3.5 (imagem multi-arch do projeto docker-postgis,
# necessária para rodar nativo em Apple Silicon) + pgvector via repositório PGDG.
FROM imresamu/postgis:16-3.5

RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-16-pgvector \
    && rm -rf /var/lib/apt/lists/*
