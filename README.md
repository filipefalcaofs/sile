# Viabiliza

Sistema de Licenciamento Eletrônico da SEDUR (Salvador/BA).

O código da aplicação está em `app-src/`. A raiz reúne os arquivos para construir a imagem e subir a aplicação.

## Requisitos

- PHP 8.3 ou superior e Composer
- Node.js 20 ou superior, somente para gerar novamente os assets do frontend
- PostgreSQL 16 com PostGIS e pgvector
- Redis

Os assets já compilados estão em `app-src/public/build`.

## Configuração

```bash
cd app-src
cp .env.example .env
composer install
php artisan key:generate
```

Ajuste no `.env` os dados de banco, Redis e `APP_URL`.

Para a stack em contêiner, as variáveis de ambiente estão em `.env.portainer.example`, na raiz deste repositório.

## Execução com Docker

Na raiz do repositório:

```bash
docker build -t sile/pgsql:16-postgis-pgvector -f app-src/docker/postgres/Dockerfile app-src/docker/postgres
docker compose -f docker-compose.portainer-infra.yml up -d
docker compose -f docker-compose.portainer-full.yml up -d --build
```

A mesma imagem serve a aplicação web, a fila e o agendador.

- Fila: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
- Agendador: `php artisan schedule:work`

`DockerFile_supervisord` e `DockerFile_schedule` geram essa mesma imagem. O comando de cada contêiner é definido pelo orquestrador.

## Testes

```bash
cd app-src
composer install
php artisan test
```

## Versão

O arquivo `VERSION`, na raiz e em `app-src/VERSION`, guarda a versão do sistema.
