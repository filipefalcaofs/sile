---
name: pest-testing
description: Testes Pest PHP em projetos Laravel — test()/it(), datasets, mocks, browser tests, smoke tests e arch(). Usar ao escrever, corrigir ou refatorar testes em tests/Feature, tests/Unit ou tests/Browser, ou quando o usuário mencionar Pest, TDD ou php artisan test.
---

# Pest Testing

Baseado nas guidelines do [Laravel Boost](https://laravel.com/docs/13.x/boost). Para sintaxe exata, usar `search-docs` quando o MCP Boost estiver disponível.

## Criar testes

```bash
php artisan make:test --pest NomeControllerTest        # tests/Feature/
php artisan make:test --pest --unit NomeServiceTest    # tests/Unit/
```

Não prefixar `Feature/` ou `Unit/` no nome — evita path duplicado.

## Organização

- Feature: `tests/Feature/`
- Unit: `tests/Unit/`
- Browser: `tests/Browser/`
- Não remover testes sem aprovação

## Convenção test() vs it()

Verificar arquivos irmãos no mesmo diretório e seguir o padrão existente.

```php
it('is true', function () {
    expect(true)->toBeTrue();
});
```

## Executar

```bash
php artisan test --compact --filter=nomeDoTeste
php artisan test --compact
php artisan test --compact tests/Feature/ExampleTest.php
```

## Assertions HTTP

| Preferir | Evitar |
|---|---|
| `assertSuccessful()` | `assertStatus(200)` |
| `assertNotFound()` | `assertStatus(404)` |
| `assertForbidden()` | `assertStatus(403)` |

## Mocking

```php
use function Pest\Laravel\mock;
```

## Datasets

```php
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
]);
```

## Browser tests (Pest 4)

- Diretório `tests/Browser/`
- `RefreshDatabase`, factories, `Event::fake()`
- `visit()`, `click()`, `fill()`, `assertNoJavaScriptErrors()`

## Armadilhas comuns

- Esquecer import de `mock`
- Usar `assertStatus(200)` em vez de `assertSuccessful()`
- Prefixar suite no `make:test`
- Browser test sem `assertNoJavaScriptErrors()`
