# Integração REGIN/Juceb Real — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ligar a integração REGIN/Juceb de verdade — parecer sai pela API (`POST {base}/recebe` com JWT) e o processo entra protocolado a partir do RUC do envelope, sem o catálogo do simulador.

**Architecture:** Saída = provider HTTP novo atrás do contrato `ReginParecerNotifier` (troca só o binding; o listener e a auditoria não mudam). Entrada = `ReginProcessoProtocolador` novo que lê o `json` (RUC) e protocola; o simulador deixa de ser chamado pelo `recebe`. Homologação contra os endpoints `/teste/*` da Juceb.

**Tech Stack:** Laravel 13, `Http` client, PHPUnit, parâmetros `integrations.regin.*` existentes.

**Spec:** `docs/superpowers/specs/2026-09-19-regin-integracao-real-design.md`

## Global Constraints

- Header de autenticação é `JWT: <token>` — NUNCA `Authorization: Bearer`.
- Sucesso do envio do parecer = corpo exato `RECEBIDO_SUCESSO`. Qualquer outro corpo, HTTP não-2xx ou `ConnectionException` → `ReginUnavailableException` (o listener audita `bloqueado`).
- Token e senha NUNCA em log, auditoria ou mensagem de exceção.
- `codFuncao` do parecer = `110`. `servico` = `WsProSol098`. `STATUS_ANALISE`: 2 deferida, 4 indeferida.
- CNPJ da Prefeitura em parâmetro novo `integrations.regin.cnpj_prefeitura` (default `13927801000149`) — nunca hardcoded no service.
- Datas do `dadosProcesso` em `yyyymmdd`; `dataGeracao` do envelope em ISO 8601 UTC.
- O que o RUC não trouxer (CNAE principal, área, endereço) NÃO protocola — pendência visível, nunca processo inventado.
- TDD estrito: teste falhando antes de cada implementação. `vendor/bin/pint --dirty --format agent` ao final de cada task.
- Mensagens de commit em pt-BR, conventional commits.

---

### Task 1: Parâmetro `integrations.regin.cnpj_prefeitura`

**Files:**
- Modify: `database/seeders/ParameterSeeder.php` (bloco `integrations.regin.*`, ~linha 697)
- Modify: `config/sile.php` (bloco `integrations.regin`, ~linha 327)
- Test: `tests/Feature/Seeders/ParameterSeederTest.php`

**Interfaces:**
- Produces: `Settings::get('integrations.regin.cnpj_prefeitura')` → string de 14 dígitos; `config('sile.integrations.regin.cnpj_prefeitura')` como fallback.

- [ ] **Step 1: Teste falhando**

Em `tests/Feature/Seeders/ParameterSeederTest.php`, dentro de `test_seeder_registra_parametros_da_api_regin` (ou método novo `test_seeder_registra_cnpj_da_prefeitura_regin`):

```php
public function test_seeder_registra_cnpj_da_prefeitura_regin(): void
{
    $this->seed(ParameterSeeder::class);

    $cnpj = Parameter::query()->where('key', 'integrations.regin.cnpj_prefeitura')->first();

    $this->assertNotNull($cnpj);
    $this->assertSame('integracoes', $cnpj->group);
    $this->assertSame('string', $cnpj->type);
    $this->assertSame('13927801000149', $cnpj->default_value);
    $this->assertSame(['required', 'string', 'size:14'], $cnpj->validation_rules);
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=test_seeder_registra_cnpj_da_prefeitura_regin`
Expected: FAIL — `assertNotNull` falha (parâmetro não existe).

- [ ] **Step 3: Implementar**

Em `database/seeders/ParameterSeeder.php`, logo após o bloco `integrations.regin.senha`:

```php
'integrations.regin.cnpj_prefeitura' => [
    'group' => 'integracoes',
    'type' => 'string',
    'default_value' => '13927801000149',
    'validation_rules' => ['required', 'string', 'size:14'],
    'description' => 'CNPJ da Prefeitura (somente dígitos) usado como origem/destino nos envelopes da API REGIN',
],
```

Em `config/sile.php`, dentro de `integrations.regin`:

```php
'cnpj_prefeitura' => '13927801000149',
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact --filter=test_seeder_registra_cnpj_da_prefeitura_regin`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders/ParameterSeeder.php config/sile.php tests/Feature/Seeders/ParameterSeederTest.php
git commit -m "feat: parametriza CNPJ da prefeitura para o envelope REGIN"
```

---

### Task 2: `ReginHttpClient` — token e envio do parecer

**Files:**
- Create: `app/Services/Regin/ReginHttpClient.php`
- Test: `tests/Feature/Regin/ReginHttpClientTest.php`

**Interfaces:**
- Consumes: `ReginIntegrationSettings` (`baseUrl()`, `username()`, `password()`), `config('sile.integrations.regin.timeout')`.
- Produces:
  - `ReginHttpClient::token(): string` — JWT cru.
  - `ReginHttpClient::enviarParecer(array $resposta): void` — lança `ReginUnavailableException` em qualquer falha.

- [ ] **Step 1: Teste falhando**

`tests/Feature/Regin/ReginHttpClientTest.php`:

```php
<?php

namespace Tests\Feature\Regin;

use App\Services\Regin\ReginHttpClient;
use App\Services\Regin\ReginUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReginHttpClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function credenciais(): void
    {
        config()->set('sile.integrations.regin.url_homologacao', 'http://10.57.247.9:8080/api_integracao');
        config()->set('sile.integrations.regin.usuario', 'sedur_integracao');
        config()->set('sile.integrations.regin.senha', 'segredo-forte');
    }

    public function test_token_autentica_em_acesso_auth(): void
    {
        $this->credenciais();
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123', 'username' => 'sedur_integracao']),
        ]);

        $token = app(ReginHttpClient::class)->token();

        $this->assertSame('jwt-123', $token);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/acesso/auth')
            && $request['username'] === 'sedur_integracao'
            && $request['password'] === 'segredo-forte');
    }

    public function test_enviar_parecer_usa_header_jwt_e_aceita_recebido_sucesso(): void
    {
        $this->credenciais();
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123']),
            '*/recebe' => Http::response('RECEBIDO_SUCESSO'),
        ]);

        app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/recebe')
            && $request->hasHeader('JWT', 'jwt-123')
            && ! $request->hasHeader('Authorization'));
    }

    public function test_enviar_parecer_lanca_quando_corpo_nao_e_recebido_sucesso(): void
    {
        $this->credenciais();
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123']),
            '*/recebe' => Http::response('ERRO_QUALQUER'),
        ]);

        $this->expectException(ReginUnavailableException::class);

        app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);
    }

    public function test_enviar_parecer_lanca_em_falha_http_e_timeout_sem_vazar_token(): void
    {
        $this->credenciais();
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-123']),
            '*/recebe' => Http::response('erro', 500),
        ]);

        try {
            app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);
            $this->fail('Deveria lançar ReginUnavailableException');
        } catch (ReginUnavailableException $e) {
            $this->assertStringNotContainsString('jwt-123', $e->getMessage());
            $this->assertStringNotContainsString('segredo-forte', $e->getMessage());
        }

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->expectException(ReginUnavailableException::class);
        app(ReginHttpClient::class)->enviarParecer(['protocolo' => '43747']);
    }

    public function test_token_sem_credencial_lanca(): void
    {
        config()->set('sile.integrations.regin.usuario', '');
        config()->set('sile.integrations.regin.senha', '');

        $this->expectException(ReginUnavailableException::class);

        app(ReginHttpClient::class)->token();
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Regin/ReginHttpClientTest.php`
Expected: FAIL — classe `ReginHttpClient` não existe.

- [ ] **Step 3: Implementar**

`app/Services/Regin/ReginHttpClient.php`:

```php
<?php

namespace App\Services\Regin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP da API REGIN/JUCEB (api_integracao). Autentica em /acesso/auth
 * e usa o header JWT (NÃO Authorization: Bearer) nas demais chamadas. Token e
 * senha nunca aparecem em exceção ou log. Sucesso do envio do parecer é o corpo
 * exato RECEBIDO_SUCESSO; qualquer outro resultado é indisponibilidade honesta.
 */
class ReginHttpClient
{
    public function __construct(private ReginIntegrationSettings $settings) {}

    public function token(): string
    {
        if ($this->settings->username() === '' || $this->settings->password() === '') {
            throw new ReginUnavailableException(motivo: 'Credenciais da API REGIN não configuradas.');
        }

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->post($this->settings->baseUrl().'/acesso/auth', [
                    'username' => $this->settings->username(),
                    'password' => $this->settings->password(),
                ]);
        } catch (ConnectionException) {
            throw new ReginUnavailableException(motivo: 'REGIN indisponível ao autenticar.');
        }

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new ReginUnavailableException(motivo: 'Falha ao autenticar no REGIN.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $resposta
     */
    public function enviarParecer(array $resposta): void
    {
        $token = $this->token();

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['JWT' => $token])
                ->post($this->settings->baseUrl().'/recebe', $resposta);
        } catch (ConnectionException) {
            throw new ReginUnavailableException(motivo: 'REGIN indisponível ao enviar o parecer.');
        }

        if (! $response->successful() || trim($response->body()) !== 'RECEBIDO_SUCESSO') {
            throw new ReginUnavailableException(motivo: 'REGIN não confirmou o recebimento do parecer.');
        }
    }

    private function timeout(): int
    {
        return (int) config('sile.integrations.regin.timeout', 8);
    }
}
```

Ajustar `ReginUnavailableException` para aceitar o motivo na mensagem (mantendo o protocolo):

```php
public function __construct(
    public readonly ?string $protocolNumber = null,
    public readonly ?string $motivo = null,
) {
    parent::__construct(
        $motivo ?? 'Comunicação do parecer ao Regin/Junta indisponível: contrato/homologação pendente (Fase 13).',
    );
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Regin/ReginHttpClientTest.php`
Expected: PASS (5 testes)

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Regin/ReginHttpClient.php app/Services/Regin/ReginUnavailableException.php tests/Feature/Regin/ReginHttpClientTest.php
git commit -m "feat: adiciona cliente HTTP da API REGIN com JWT"
```

---

### Task 3: `HttpReginParecerNotifier` — monta o `Resposta` e envia

**Files:**
- Create: `app/Services/Regin/HttpReginParecerNotifier.php`
- Test: `tests/Feature/Regin/HttpReginParecerNotifierTest.php`

**Interfaces:**
- Consumes: `ReginHttpClient::enviarParecer(array)`, `Settings::get('integrations.regin.cnpj_prefeitura')`, `ViabilityRequest` (`external_reference`), `ViabilityDecision` (`outcome`, `fundamentacao`, `decided_at`).
- Produces: `HttpReginParecerNotifier implements ReginParecerNotifier` — `notifyParecer(ViabilityRequest, ViabilityDecision): void`.

- [ ] **Step 1: Teste falhando**

`tests/Feature/Regin/HttpReginParecerNotifierTest.php`:

```php
<?php

namespace Tests\Feature\Regin;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Regin\HttpReginParecerNotifier;
use App\Services\Regin\ReginHttpClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HttpReginParecerNotifierTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function clienteEspião(): object
    {
        return new class
        {
            /** @var list<array<string, mixed>> */
            public array $envios = [];

            public function enviarParecer(array $resposta): void
            {
                $this->envios[] = $resposta;
            }
        };
    }

    public function test_monta_envelope_deferido_com_status_2(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '43747']);
        $decision = ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'outcome' => DecisionOutcome::Deferida,
            'fundamentacao' => ['texto' => 'Viabilidade deferida pelo motor'],
        ]);

        $espiao = $this->clienteEspião();
        $notifier = new HttpReginParecerNotifier($espiao); // type mismatch proposital até o bind

        $notifier->notifyParecer($request, $decision);

        $this->assertCount(1, $espiao->envios);
        $envio = $espiao->envios[0];

        $this->assertSame('43747', $envio['protocolo']);
        $this->assertSame('WsProSol098', $envio['servico']);
        $this->assertSame(110, $envio['codFuncao']);
        $this->assertSame('13927801000149', $envio['cnpjDestino']);
        $this->assertSame('13927801000149', $envio['cnpjOrigem']);

        $dados = $envio['dadosProcesso'];
        $this->assertSame('43747', $dados['PROTOCOLO']);
        $this->assertSame(1, $dados['FINALIZA_PROCESSO']);
        $this->assertSame(1, $dados['PROCESSO_INTERESSE_INSTITUICAO']);
        $this->assertSame(0, $dados['GERA_DOCUMENTO_PROCESSO']);
        $this->assertSame(2, $dados['ANALISES']['AREA'][0]['STATUS_ANALISE']);
        $this->assertSame($decision->decided_at->format('Ymd'), $dados['ANALISES']['AREA'][0]['DATA_ANALISE']);
    }

    public function test_monta_envelope_indeferido_com_status_4(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create(['external_reference' => '53514']);
        $decision = ViabilityDecision::factory()->indeferida()->create(['viability_request_id' => $request->id]);

        $espiao = $this->clienteEspião();
        (new HttpReginParecerNotifier($espiao))->notifyParecer($request, $decision);

        $this->assertSame(4, $espiao->envios[0]['dadosProcesso']['ANALISES']['AREA'][0]['STATUS_ANALISE']);
    }
}
```

Nota para o implementador: o espião é um double do `ReginHttpClient`. Injetar via container (`app()->instance(ReginHttpClient::class, $espiao)`) e resolver o notifier pelo container, não `new` — ajustar o teste para `$this->app->make(HttpReginParecerNotifier::class)` após o bind.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Regin/HttpReginParecerNotifierTest.php`
Expected: FAIL — classe não existe.

- [ ] **Step 3: Implementar**

`app/Services/Regin/HttpReginParecerNotifier.php`:

```php
<?php

namespace App\Services\Regin;

use App\Enums\DecisionOutcome;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Support\Settings;

/**
 * Provider REAL do parecer ao REGIN/JUCEB: monta o envelope Resposta
 * (codFuncao 110) a partir da decisão imutável e envia pelo ReginHttpClient.
 * Sucesso é RECEBIDO_SUCESSO; qualquer falha propaga ReginUnavailableException
 * e o listener audita a pendência — nunca sucesso fictício.
 */
class HttpReginParecerNotifier implements ReginParecerNotifier
{
    public function __construct(private ReginHttpClient $client) {}

    public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void
    {
        $this->client->enviarParecer($this->envelope($request, $decision));
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(ViabilityRequest $request, ViabilityDecision $decision): array
    {
        $cnpj = (string) Settings::get('integrations.regin.cnpj_prefeitura', config('sile.integrations.regin.cnpj_prefeitura'));
        $protocolo = (string) $request->external_reference;

        return [
            'protocolo' => $protocolo,
            'servico' => 'WsProSol098',
            'cnpjDestino' => $cnpj,
            'cnpjOrigem' => $cnpj,
            'cnpjEmpresa' => $cnpj,
            'codFuncao' => 110,
            'dataGeracao' => now()->toIso8601String(),
            'dadosProcesso' => [
                'PROTOCOLO' => $protocolo,
                'CNPJ_INSTITUICAO' => $cnpj,
                'DATA_GERACAO' => now()->format('Ymd'),
                'FINALIZA_PROCESSO' => 1,
                'PROCESSO_INTERESSE_INSTITUICAO' => 1,
                'GERA_DOCUMENTO_PROCESSO' => 0,
                'GERA_DOCUMENTOS_AREAS' => 0,
                'ANALISES' => [
                    'AREA' => [
                        [
                            'STATUS_ANALISE' => $decision->outcome === DecisionOutcome::Deferida ? 2 : 4,
                            'JUSTIFICATIVA_ANALISE' => $this->justificativa($decision),
                            'DATA_ANALISE' => $decision->decided_at?->format('Ymd') ?? now()->format('Ymd'),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function justificativa(ViabilityDecision $decision): string
    {
        $texto = $decision->fundamentacao['texto'] ?? null;

        return is_string($texto) && $texto !== ''
            ? $texto
            : "Viabilidade {$decision->outcome->value} pelo motor de regras.";
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Regin/HttpReginParecerNotifierTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Regin/HttpReginParecerNotifier.php tests/Feature/Regin/HttpReginParecerNotifierTest.php
git commit -m "feat: envia parecer ao REGIN com envelope Resposta"
```

---

### Task 4: Trocar o binding e ajustar o listener

**Files:**
- Modify: `app/Providers/AppServiceProvider.php:105`
- Test: `tests/Feature/Expresso/ComunicarResultadoReginListenerTest.php`

**Interfaces:**
- Consumes: `HttpReginParecerNotifier` (Task 3).
- Produces: binding `ReginParecerNotifier::class → HttpReginParecerNotifier::class`.

- [ ] **Step 1: Teste falhando**

Em `ComunicarResultadoReginListenerTest`, trocar o caminho "bloqueado" pelo caminho real com `Http::fake`. Novo teste:

```php
public function test_binding_real_envia_parecer_e_audita_sucesso(): void
{
    config()->set('sile.integrations.regin.url_homologacao', 'http://10.57.247.9:8080/api_integracao');
    config()->set('sile.integrations.regin.usuario', 'sedur_integracao');
    config()->set('sile.integrations.regin.senha', 'segredo');

    Http::fake([
        '*/acesso/auth' => Http::response(['token' => 'jwt-1']),
        '*/recebe' => Http::response('RECEBIDO_SUCESSO'),
    ]);

    [$request, $decision] = $this->requestComDecisao();
    $request->forceFill(['external_reference' => '43747'])->save();

    event(new ResultadoEmitido($request, $decision));

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/recebe') && $r->hasHeader('JWT', 'jwt-1'));

    $sucesso = Activity::query()
        ->where('log_name', 'integracoes')
        ->where('event', 'regin-parecer')
        ->where('properties->result', 'sucesso')
        ->count();

    $this->assertSame(1, $sucesso);
}
```

O teste antigo `test_canal_bloqueado_audita_pendencia_uma_unica_vez_sem_quebrar_o_fluxo` passa a usar `Http::fake` com `/recebe` retornando erro 500 — prova que a falha real audita `bloqueado` (não mais o provider Unavailable).

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Expresso/ComunicarResultadoReginListenerTest.php`
Expected: FAIL — binding ainda é o Unavailable (nenhuma chamada HTTP sai).

- [ ] **Step 3: Implementar**

Em `app/Providers/AppServiceProvider.php`, trocar a linha do binding:

```php
$this->app->bind(ReginParecerNotifier::class, HttpReginParecerNotifier::class);
```

Atualizar o comentário do bloco: o parecer ao Regin agora é REAL (HTTP); SEFAZ e BAP continuam bloqueados. Adicionar o import `App\Services\Regin\HttpReginParecerNotifier` e remover o de `UnavailableReginParecerNotifier` se ficar sem uso.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Expresso/ComunicarResultadoReginListenerTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Providers/AppServiceProvider.php tests/Feature/Expresso/ComunicarResultadoReginListenerTest.php
git commit -m "feat: liga o envio real do parecer ao REGIN"
```

---

### Task 5: `ReginProcessoProtocolador` — protocola a partir do RUC

**Files:**
- Create: `app/Services/Regin/ReginProcessoProtocolador.php`
- Modify: `app/Services/Regin/ReginRecebeService.php` (troca `materializarDoCatalogo` pelo protocolador)
- Test: `tests/Feature/Regin/ReginProcessoProtocoladorTest.php`

**Interfaces:**
- Consumes: `ReginRecebimento` (`corpo` = o `json`/RUC, `protocolo`), `ProtocolarSolicitacaoService::protocol`, `FluxoExpressoService::decide`.
- Produces: `ReginProcessoProtocolador::protocolar(ReginRecebimento $recebimento): ?ViabilityRequest` — `null` quando faltar dado mínimo (CNAE principal, área, endereço).

- [ ] **Step 1: Teste falhando**

`tests/Feature/Regin/ReginProcessoProtocoladorTest.php` — casos:

```php
public function test_ruc_completo_protocola_com_cnaes_endereco_area_e_inscricao(): void
{
    // RUC com GROUPRUC_ACTV_ECON (1 principal + 1 secundário), RUC_ESTAB com
    // RES_AREA=834 e endereço, RUC_GEN_PROTOCOLO tipo 5 com a inscrição.
    // Afirmar: processo criado com origin=regin, contingency_reason=regin_recebe,
    // external_reference=protocolo, 2 CNAEs no pivot (1 primary), used_area_m2=834,
    // address_* preenchidos, property_registration preenchido.
}

public function test_ruc_sem_cnae_principal_nao_protocola(): void
{
    // RUC sem GROUPRUC_ACTV_ECON → protocolar() retorna null, nenhum processo criado.
}

public function test_ruc_sem_area_nao_protocola(): void
{
    // RUC sem RES_AREA → null, sem processo.
}

public function test_empresa_e_reusada_pelo_cnpj(): void
{
    // Company existente com o CNPJ do RUC_GENERAL → o processo usa o id dela,
    // nenhuma empresa nova é criada.
}
```

(Implementador: montar o array RUC completo nos testes seguindo a OpenAPI — `rowset.RUC_GENERAL.RGE_CGC_CPF`, `rowset.GROUPRUC_ACTV_ECON.RUC_ACTV_ECON[].RAE_TAE_COD_ACTVD`/`RAE_CALIF_ACTV`, `rowset.RUC_ESTAB.RES_*`, `rowset.GROUPRUC_GEN_PROTOCOLO.RUC_GEN_PROTOCOLO[].RGP_TGE_COD_TIP_TAB=5`/`RGP_VALOR`.)

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Regin/ReginProcessoProtocoladorTest.php`
Expected: FAIL — classe não existe.

- [ ] **Step 3: Implementar**

`app/Services/Regin/ReginProcessoProtocolador.php`:

```php
<?php

namespace App\Services\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;

/**
 * Protocola o processo a partir do RUC do envelope REGIN — sem o catálogo do
 * simulador. O que o RUC não trouxer (CNAE principal, área, endereço) NÃO
 * protocola: retorna null e a pendência fica visível, nunca processo inventado.
 */
class ReginProcessoProtocolador
{
    public function __construct(
        private ProtocolarSolicitacaoService $protocolar,
        private FluxoExpressoService $expresso,
    ) {}

    public function protocolar(ReginRecebimento $recebimento): ?ViabilityRequest
    {
        $ruc = $recebimento->corpo ?? [];
        $rowset = $ruc['rowset'] ?? [];

        $cnaes = $this->cnaes($rowset);
        $area = $this->area($rowset);
        $endereco = $this->endereco($rowset);

        if ($cnaes === [] || $area === null || $endereco === null) {
            return null;
        }

        $ator = $this->ator();

        $solicitacao = ViabilityRequest::query()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'service_type_id' => $this->tipoServico()->id,
            'company_id' => $this->empresa($rowset)->id,
            'requester_user_id' => $ator->id,
            'created_by_user_id' => $ator->id,
            'used_area_m2' => $area,
            'address_street' => $endereco['street'],
            'address_number' => $endereco['number'],
            'address_complement' => $endereco['complement'],
            'address_neighborhood' => $endereco['neighborhood'],
            'address_zip' => $endereco['zip'],
            'property_registration' => $this->inscricao($rowset),
            'contingency_reason' => ReginProtocoloSimulacaoService::CONTINGENCIA_RECEBE,
            'external_reference' => $recebimento->protocolo,
        ]);

        foreach (array_values($cnaes) as $indice => $cnae) {
            $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        $this->protocolar->protocol($solicitacao->fresh() ?? $solicitacao, $ator);
        $this->expresso->decide($solicitacao->fresh() ?? $solicitacao, $ator);

        return $solicitacao->fresh() ?? $solicitacao;
    }

    // ... helpers: cnaes(), area(), endereco(), inscricao(), empresa(), tipoServico(), ator()
}
```

Em `ReginRecebeService::receive()`, trocar:

```php
$processo = $this->processos->materializarDoCatalogo($protocolo);
```

por:

```php
$processo = $this->protocolador->protocolar($recebimento);
```

(injetar `ReginProcessoProtocolador` no construtor no lugar de `ReginProtocoloSimulacaoService`.)

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Regin/ReginProcessoProtocoladorTest.php tests/Feature/Regin/ReginRecebeEndpointTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Regin/ReginProcessoProtocolador.php app/Services/Regin/ReginRecebeService.php tests/Feature/Regin/ReginProcessoProtocoladorTest.php tests/Feature/Regin/ReginRecebeEndpointTest.php
git commit -m "feat: protocola o processo REGIN a partir do RUC"
```

---

### Task 6: Homologação contra a Juceb

**Files:**
- Create: `app/Console/Commands/ReginHomologarCommand.php`
- Test: `tests/Feature/Regin/ReginHomologarCommandTest.php`

**Interfaces:**
- Consumes: `ReginHttpClient`, `AuditService`.
- Produces: comando `regin:homologar {--protocolo=}` que valida o parecer em `/teste/validaResposta` e roda `/teste/testeRecebimento`, auditando cada chamada (`integracoes`/`regin-homologacao`).

- [ ] **Step 1: Teste falhando** — comando chama os endpoints `/teste/*` com JWT e audita sucesso/falha.

- [ ] **Step 2: Rodar e ver falhar** — comando não existe.

- [ ] **Step 3: Implementar** o comando com `Http` + header `JWT`, auditando cada etapa.

- [ ] **Step 4: Rodar e ver passar** — `php artisan test --compact tests/Feature/Regin/ReginHomologarCommandTest.php`.

- [ ] **Step 5: Commit** — `feat: adiciona comando de homologação da API REGIN`.

---

## Self-Review

- **Cobertura da spec:** Task 1 (CNPJ parâmetro) → spec §3.2; Tasks 2–4 (cliente, notifier, binding) → spec §3; Task 5 (protocolador) → spec §4; Task 6 (homologação) → spec §5. Fora de escopo (§6) corretamente sem task.
- **Placeholders:** Task 5 tem helpers privados não detalhados linha a linha — o implementador precisa do mapeamento exato do RUC, que está na spec §4 e na OpenAPI. Aceitável para um plano deste tamanho; o teste trava o comportamento.
- **Tipos:** `ReginHttpClient::enviarParecer(array): void`, `ReginProcessoProtocolador::protocolar(ReginRecebimento): ?ViabilityRequest`, `HttpReginParecerNotifier` implementa `ReginParecerNotifier` — consistentes entre tasks.
