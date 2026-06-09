---
name: laravel-boost
description: Ferramentas Laravel Boost via MCP — search-docs, schema de banco, rotas, logs, tinker e introspecção da aplicação. Usar em tarefas de backend Laravel, debug, migrations, rotas, config e consulta à documentação versionada do ecossistema Laravel.
---

# Laravel Boost

Skill para trabalhar com o [Laravel Boost MCP](https://laravel.com/docs/13.x/boost) em projetos que têm `laravel/boost` instalado.

## Pré-requisitos

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Habilitar `laravel-boost` no MCP Settings do Cursor.

## Quando usar

- Debug de banco, schema ou queries
- Investigar erros (`last-error`, logs, browser-logs)
- Verificar rotas, config e versões de pacotes
- Buscar documentação antes de implementar (`search-docs`)
- Testar hipóteses com tinker (com aprovação — preferir testes)

## Fluxo recomendado

1. `search-docs` com queries amplas sobre o tópico
2. `database-schema` se envolver migrations/models
3. Implementar seguindo skill `laravel-best-practices`
4. Validar com `php artisan test --compact --filter=...`

## search-docs

- Múltiplas queries por tópico: `queries=["authentication", "middleware"]`
- Palavras = AND; `"frase exata"` = match adjacente
- Escopo opcional via `packages` quando souber o pacote relevante

## Anti-padrões

- Não adivinhar API de versões antigas — usar `search-docs`
- Não executar SQL de escrita via `database-query` (read-only)
- Não criar models no tinker sem aprovação — usar factories em testes
- Não ignorar `get-absolute-url` ao gerar links para o usuário
