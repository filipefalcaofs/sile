# Reprocessamento do motor por etapa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Registrar o resultado de cada etapa do motor que depende de serviço de terceiro, mostrar na ficha onde o motor parou e reprocessar a partir da etapa que falhou (botão, comando e agendamento), reavaliando o veredito.

**Architecture:** Uma tabela `viability_request_etapas` (uma linha por processo e etapa) alimentada por um `EtapaMotorRegistrador` chamado nos pontos em que o status do serviço já é conhecido. Um `ReprocessarEtapasService` refaz a localização e o território e chama `FluxoExpressoService::reavaliar`, que decide processo protocolado ou processo em análise ainda não assumido. O comando `processos:reprocessar-etapas` e o botão da ficha usam o mesmo serviço.

**Tech Stack:** PHP 8.5, Laravel 13, PHPUnit 12, Inertia v3 + React 19, Spatie Activitylog (via `AuditService`), PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-10-05-reprocessamento-por-etapa-design.md`

## Global Constraints

- Testes são classes PHPUnit em `tests/Feature/...`; criar com `php artisan make:test --phpunit NomeTest`. Nunca Pest.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`. Após cada teste novo ou alterado: rodar só aquele teste com `php artisan test --compact --filter=...`.
- Cada migration traz todos os atributos das colunas; novas tabelas com factory (`database/factories`).
- Etapa `indisponivel` é a única reprocessada sozinha. `definitiva` e `pulada` nunca.
- Intervalo: 15 minutos nas 4 primeiras tentativas, 60 minutos depois, esgotando 24 horas após a primeira falha. Valores em `config/sile.php` → `reprocessamento.*`.
- Reavaliar processo em análise só quando `status = em_analise`, `analysis_status = para_distribuir` e sem `decision`. Análise já assumida nunca é sobrescrita.
- Propriedades de auditoria e `motivo` das etapas não carregam dado pessoal (sem nome, CPF, e-mail, endereço completo).
- Rotas em português, nomeadas, sob `routes/gestao.php`; a permissão nova é `reprocessar-processos`, concedida a `analista` e `administrador`.
- Sem coautoria de IA em commit nem marca d'água no código. Mensagens de commit em português.

---

## File Structure

| Arquivo | Responsabilidade |
|---|---|
| `app/Enums/EtapaMotor.php` | Etapas, serviço de origem, ordem e rótulo |
| `app/Enums/SituacaoEtapa.php` | `ok`, `indisponivel`, `definitiva`, `pulada` |
| `database/migrations/2026_10_05_100000_create_viability_request_etapas_table.php` | Tabela das etapas |
| `app/Models/ViabilityRequestEtapa.php` + `database/factories/ViabilityRequestEtapaFactory.php` | Modelo e factory |
| `app/Services/Motor/EtapaMotorRegistrador.php` | Grava situação, tentativas, próxima tentativa e auditoria |
| `app/Services/Motor/ReprocessarEtapasService.php` + `ReprocessoResultado.php` | Reprocessa a partir da etapa que falhou |
| `app/Console/Commands/ReprocessarEtapasCommand.php` | Comando agendado e uso manual |
| `app/Http/Controllers/Gestao/ReprocessarProcessoController.php` | Botão da ficha |
| `resources/js/components/analise/etapas-motor.tsx` | Card "Etapas do motor" |
| Modificados | `ViabilityRequest`, `TerritorioProcessoService`, `PoligonoEnderecamentoService`, `TerritorialRuleService`, `FluxoExpressoService`, `AnalysisRecordController`, `show.tsx`, `routes/gestao.php`, `routes/console.php`, `config/sile.php`, `RolesAndPermissionsSeeder` |

---

### Task 1: Etapas — enums, tabela, modelo e factory

**Files:**
- Create: `app/Enums/EtapaMotor.php`, `app/Enums/SituacaoEtapa.php`, `app/Models/ViabilityRequestEtapa.php`, `database/factories/ViabilityRequestEtapaFactory.php`, `database/migrations/2026_10_05_100000_create_viability_request_etapas_table.php`
- Modify: `app/Models/ViabilityRequest.php` (relação `etapas()`), `config/sile.php` (bloco `reprocessamento`)
- Test: `tests/Feature/Motor/ViabilityRequestEtapaTest.php`

**Interfaces:**
- Produces: `EtapaMotor::{LocalizacaoInscricao,PoligonoLote,Zona,Via,Bairro,RegraTerritorial}`, `EtapaMotor::servico(): string`, `ordem(): int`, `rotulo(): string`; `SituacaoEtapa::{Ok,Indisponivel,Definitiva,Pulada}`; `ViabilityRequest::etapas(): HasMany`; `ViabilityRequestEtapa` com colunas da spec mais `primeira_falha_em`; `config('sile.reprocessamento.intervalo_inicial_min'|'tentativas_iniciais'|'intervalo_posterior_min'|'janela_horas')`.

- [ ] **Step 1: Criar o teste**

Run: `php artisan make:test --phpunit Motor/ViabilityRequestEtapaTest`

```php
<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ViabilityRequestEtapaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_etapa_pertence_ao_processo_e_converte_enums(): void
    {
        $etapa = ViabilityRequestEtapa::factory()->create([
            'etapa' => EtapaMotor::Zona,
            'situacao' => SituacaoEtapa::Indisponivel,
        ]);

        $processo = ViabilityRequest::query()->findOrFail($etapa->viability_request_id);

        $this->assertSame(EtapaMotor::Zona, $processo->etapas()->first()->etapa);
        $this->assertSame(SituacaoEtapa::Indisponivel, $processo->etapas()->first()->situacao);
    }

    public function test_processo_nao_repete_a_mesma_etapa(): void
    {
        $processo = ViabilityRequest::factory()->create();
        ViabilityRequestEtapa::factory()->create(['viability_request_id' => $processo->id, 'etapa' => EtapaMotor::Via]);

        $this->expectException(UniqueConstraintViolationException::class);

        ViabilityRequestEtapa::factory()->create(['viability_request_id' => $processo->id, 'etapa' => EtapaMotor::Via]);
    }

    public function test_cada_etapa_declara_o_servico_de_terceiro_e_a_ordem(): void
    {
        $this->assertSame('inscricao_imobiliaria', EtapaMotor::LocalizacaoInscricao->servico());
        $this->assertSame('geoserver_zona', EtapaMotor::Zona->servico());
        $this->assertLessThan(EtapaMotor::Zona->ordem(), EtapaMotor::LocalizacaoInscricao->ordem());
        $this->assertLessThan(EtapaMotor::RegraTerritorial->ordem(), EtapaMotor::Bairro->ordem());
    }

    public function test_config_traz_os_intervalos_do_reprocessamento(): void
    {
        $this->assertSame(15, config('sile.reprocessamento.intervalo_inicial_min'));
        $this->assertSame(4, config('sile.reprocessamento.tentativas_iniciais'));
        $this->assertSame(60, config('sile.reprocessamento.intervalo_posterior_min'));
        $this->assertSame(24, config('sile.reprocessamento.janela_horas'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Motor/ViabilityRequestEtapaTest.php`
Expected: FAIL (classes `EtapaMotor` e `ViabilityRequestEtapa` não existem).

- [ ] **Step 3: Implementar**

`app/Enums/EtapaMotor.php`:

```php
<?php

namespace App\Enums;

enum EtapaMotor: string
{
    case LocalizacaoInscricao = 'localizacao_inscricao';
    case PoligonoLote = 'poligono_lote';
    case Zona = 'zona';
    case Via = 'via';
    case Bairro = 'bairro';
    case RegraTerritorial = 'regra_territorial';

    public function servico(): string
    {
        return match ($this) {
            self::LocalizacaoInscricao => 'inscricao_imobiliaria',
            self::PoligonoLote => 'enderecamento_semap',
            self::Zona => 'geoserver_zona',
            self::Via => 'geoserver_via',
            self::Bairro => 'geosalvador_bairro',
            self::RegraTerritorial => 'geoserver_camadas',
        };
    }

    public function ordem(): int
    {
        return match ($this) {
            self::LocalizacaoInscricao => 1,
            self::PoligonoLote => 2,
            self::Zona => 3,
            self::Via => 4,
            self::Bairro => 5,
            self::RegraTerritorial => 6,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::LocalizacaoInscricao => 'Localização pela inscrição imobiliária',
            self::PoligonoLote => 'Polígono do lote',
            self::Zona => 'Zona urbanística',
            self::Via => 'Classe viária',
            self::Bairro => 'Bairro oficial',
            self::RegraTerritorial => 'Regras territoriais',
        };
    }
}
```

`app/Enums/SituacaoEtapa.php`:

```php
<?php

namespace App\Enums;

enum SituacaoEtapa: string
{
    case Ok = 'ok';
    case Indisponivel = 'indisponivel';
    case Definitiva = 'definitiva';
    case Pulada = 'pulada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ok => 'Concluída',
            self::Indisponivel => 'Serviço indisponível',
            self::Definitiva => 'Sem resultado',
            self::Pulada => 'Não aplicável',
        };
    }
}
```

Migration `database/migrations/2026_10_05_100000_create_viability_request_etapas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viability_request_etapas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viability_request_id')->constrained()->cascadeOnDelete();
            $table->string('etapa');
            $table->string('servico');
            $table->string('situacao');
            $table->text('motivo')->nullable();
            $table->unsignedInteger('tentativas')->default(0);
            $table->timestamp('primeira_falha_em')->nullable();
            $table->timestamp('ultima_tentativa_em')->nullable();
            $table->timestamp('proxima_tentativa_em')->nullable();
            $table->timestamp('esgotada_em')->nullable();
            $table->timestamps();

            $table->unique(['viability_request_id', 'etapa']);
            $table->index(['situacao', 'proxima_tentativa_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viability_request_etapas');
    }
};
```

`app/Models/ViabilityRequestEtapa.php`:

```php
<?php

namespace App\Models;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use Database\Factories\ViabilityRequestEtapaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado de uma etapa do motor que depende de serviço de terceiro, uma linha
 * por processo e etapa. A trilha de cada tentativa fica em activity_log.
 */
#[Fillable([
    'viability_request_id',
    'etapa',
    'servico',
    'situacao',
    'motivo',
    'tentativas',
    'primeira_falha_em',
    'ultima_tentativa_em',
    'proxima_tentativa_em',
    'esgotada_em',
])]
class ViabilityRequestEtapa extends Model
{
    /** @use HasFactory<ViabilityRequestEtapaFactory> */
    use HasFactory;

    protected $table = 'viability_request_etapas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'etapa' => EtapaMotor::class,
            'situacao' => SituacaoEtapa::class,
            'tentativas' => 'integer',
            'primeira_falha_em' => 'datetime',
            'ultima_tentativa_em' => 'datetime',
            'proxima_tentativa_em' => 'datetime',
            'esgotada_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ViabilityRequest, $this>
     */
    public function viabilityRequest(): BelongsTo
    {
        return $this->belongsTo(ViabilityRequest::class);
    }
}
```

`database/factories/ViabilityRequestEtapaFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViabilityRequestEtapa>
 */
class ViabilityRequestEtapaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $etapa = fake()->randomElement(EtapaMotor::cases());

        return [
            'viability_request_id' => ViabilityRequest::factory(),
            'etapa' => $etapa,
            'servico' => $etapa->servico(),
            'situacao' => SituacaoEtapa::Ok,
            'motivo' => null,
            'tentativas' => 1,
            'ultima_tentativa_em' => now(),
        ];
    }

    public function indisponivel(): static
    {
        return $this->state(fn (): array => [
            'situacao' => SituacaoEtapa::Indisponivel,
            'motivo' => 'Serviço sem resposta',
            'primeira_falha_em' => now(),
            'proxima_tentativa_em' => now()->subMinute(),
        ]);
    }
}
```

Em `app/Models/ViabilityRequest.php`, junto de `transitions()`:

```php
    /**
     * Etapas do motor que dependem de serviço de terceiro.
     *
     * @return HasMany<ViabilityRequestEtapa, $this>
     */
    public function etapas(): HasMany
    {
        return $this->hasMany(ViabilityRequestEtapa::class);
    }
```

Em `config/sile.php`, no array raiz (perto de `'expresso'`):

```php
    // Reprocessamento por etapa do motor (spec 2026-10-05): constantes técnicas.
    'reprocessamento' => [
        'intervalo_inicial_min' => 15,
        'tentativas_iniciais' => 4,
        'intervalo_posterior_min' => 60,
        'janela_horas' => 24,
    ],
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Motor/ViabilityRequestEtapaTest.php`
Expected: PASS (4 testes).

- [ ] **Step 5: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/EtapaMotor.php app/Enums/SituacaoEtapa.php app/Models/ViabilityRequestEtapa.php app/Models/ViabilityRequest.php database/factories/ViabilityRequestEtapaFactory.php database/migrations/2026_10_05_100000_create_viability_request_etapas_table.php config/sile.php tests/Feature/Motor/ViabilityRequestEtapaTest.php
git commit -m "feat: registra as etapas do motor por processo"
```

---

### Task 2: EtapaMotorRegistrador

**Files:**
- Create: `app/Services/Motor/EtapaMotorRegistrador.php`
- Test: `tests/Feature/Motor/EtapaMotorRegistradorTest.php`

**Interfaces:**
- Consumes: Task 1 (enums, modelo, config).
- Produces: `EtapaMotorRegistrador::registrar(ViabilityRequest $request, EtapaMotor $etapa, SituacaoEtapa $situacao, ?string $motivo = null): ViabilityRequestEtapa`.

- [ ] **Step 1: Criar o teste**

Run: `php artisan make:test --phpunit Motor/EtapaMotorRegistradorTest`

```php
<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Motor\EtapaMotorRegistrador;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EtapaMotorRegistradorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfMinute());
    }

    public function test_falha_agenda_a_proxima_tentativa_em_quinze_minutos(): void
    {
        $processo = ViabilityRequest::factory()->create();

        $etapa = app(EtapaMotorRegistrador::class)
            ->registrar($processo, EtapaMotor::LocalizacaoInscricao, SituacaoEtapa::Indisponivel, 'sem resposta');

        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa->situacao);
        $this->assertSame('inscricao_imobiliaria', $etapa->servico);
        $this->assertSame(1, $etapa->tentativas);
        $this->assertTrue($etapa->primeira_falha_em->equalTo(now()));
        $this->assertTrue($etapa->proxima_tentativa_em->equalTo(now()->addMinutes(15)));
        $this->assertNull($etapa->esgotada_em);
    }

    public function test_depois_das_tentativas_iniciais_o_intervalo_passa_a_uma_hora(): void
    {
        $processo = ViabilityRequest::factory()->create();
        $registrador = app(EtapaMotorRegistrador::class);

        for ($i = 0; $i < 5; $i++) {
            $etapa = $registrador->registrar($processo, EtapaMotor::Zona, SituacaoEtapa::Indisponivel);
        }

        $this->assertSame(5, $etapa->tentativas);
        $this->assertTrue($etapa->proxima_tentativa_em->equalTo(now()->addMinutes(60)));
    }

    public function test_esgota_quando_a_proxima_tentativa_passaria_da_janela_de_vinte_e_quatro_horas(): void
    {
        $processo = ViabilityRequest::factory()->create();
        $registrador = app(EtapaMotorRegistrador::class);
        $registrador->registrar($processo, EtapaMotor::Via, SituacaoEtapa::Indisponivel);

        $this->travel(23 * 60 + 50)->minutes();
        $etapa = $registrador->registrar($processo, EtapaMotor::Via, SituacaoEtapa::Indisponivel);

        $this->assertNotNull($etapa->esgotada_em);
        $this->assertNull($etapa->proxima_tentativa_em);
    }

    public function test_sucesso_limpa_a_agenda_e_a_falha(): void
    {
        $processo = ViabilityRequest::factory()->create();
        $registrador = app(EtapaMotorRegistrador::class);
        $registrador->registrar($processo, EtapaMotor::Bairro, SituacaoEtapa::Indisponivel, 'fora do ar');

        $etapa = $registrador->registrar($processo, EtapaMotor::Bairro, SituacaoEtapa::Ok);

        $this->assertSame(SituacaoEtapa::Ok, $etapa->situacao);
        $this->assertSame(2, $etapa->tentativas);
        $this->assertNull($etapa->proxima_tentativa_em);
        $this->assertNull($etapa->primeira_falha_em);
        $this->assertNull($etapa->motivo);
        $this->assertSame(1, $processo->etapas()->count());
    }

    public function test_toda_tentativa_deixa_auditoria_sem_dado_pessoal(): void
    {
        $processo = ViabilityRequest::factory()->create();

        app(EtapaMotorRegistrador::class)
            ->registrar($processo, EtapaMotor::Zona, SituacaoEtapa::Indisponivel, 'GeoServer sem resposta');

        $log = Activity::query()->where('log_name', 'motor')->where('event', 'etapa')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('zona', $log->properties['etapa']);
        $this->assertSame('geoserver_zona', $log->properties['servico']);
        $this->assertSame('indisponivel', $log->properties['situacao']);
        $this->assertSame($processo->id, $log->properties['viability_request_id']);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Motor/EtapaMotorRegistradorTest.php`
Expected: FAIL (`EtapaMotorRegistrador` não existe).

- [ ] **Step 3: Implementar**

`app/Services/Motor/EtapaMotorRegistrador.php`:

```php
<?php

namespace App\Services\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use App\Support\Audit\AuditService;
use Illuminate\Support\Str;

/**
 * Grava o resultado de uma etapa do motor. Falha transitória agenda a próxima
 * tentativa (15 min nas primeiras, 60 min depois) e esgota 24 h após a primeira
 * falha; qualquer outra situação limpa a agenda.
 */
class EtapaMotorRegistrador
{
    public function __construct(private AuditService $audit) {}

    public function registrar(
        ViabilityRequest $request,
        EtapaMotor $etapa,
        SituacaoEtapa $situacao,
        ?string $motivo = null,
    ): ViabilityRequestEtapa {
        $agora = now();

        $linha = ViabilityRequestEtapa::query()->firstOrNew([
            'viability_request_id' => $request->id,
            'etapa' => $etapa->value,
        ]);

        $linha->servico = $etapa->servico();
        $linha->situacao = $situacao;
        $linha->tentativas = ((int) $linha->tentativas) + 1;
        $linha->ultima_tentativa_em = $agora;

        if ($situacao === SituacaoEtapa::Indisponivel) {
            $linha->motivo = $motivo !== null ? Str::limit($motivo, 500, '') : null;
            $linha->primeira_falha_em ??= $agora;

            $proxima = $agora->copy()->addMinutes($this->intervaloEmMinutos($linha->tentativas));
            $fimDaJanela = $linha->primeira_falha_em->copy()->addHours((int) config('sile.reprocessamento.janela_horas'));

            if ($proxima->greaterThan($fimDaJanela)) {
                $linha->esgotada_em = $agora;
                $linha->proxima_tentativa_em = null;
            } else {
                $linha->esgotada_em = null;
                $linha->proxima_tentativa_em = $proxima;
            }
        } else {
            $linha->motivo = $situacao === SituacaoEtapa::Ok ? null : ($motivo !== null ? Str::limit($motivo, 500, '') : null);
            $linha->primeira_falha_em = null;
            $linha->proxima_tentativa_em = null;
            $linha->esgotada_em = null;
        }

        $linha->save();

        $this->audit->log(
            logName: 'motor',
            event: 'etapa',
            description: "Etapa {$etapa->value} da solicitação #{$request->id}: {$situacao->value}",
            properties: [
                'viability_request_id' => $request->id,
                'etapa' => $etapa->value,
                'servico' => $etapa->servico(),
                'situacao' => $situacao->value,
                'tentativas' => $linha->tentativas,
            ],
            subject: $request,
            result: $situacao->value,
        );

        return $linha;
    }

    private function intervaloEmMinutos(int $tentativas): int
    {
        return $tentativas <= (int) config('sile.reprocessamento.tentativas_iniciais')
            ? (int) config('sile.reprocessamento.intervalo_inicial_min')
            : (int) config('sile.reprocessamento.intervalo_posterior_min');
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Motor/EtapaMotorRegistradorTest.php`
Expected: PASS (5 testes). Se o teste da janela falhar por arredondamento, ajuste o `travel` do teste (23 h 30 min + 15 min passa de 24 h) e não a regra.

- [ ] **Step 5: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Motor/EtapaMotorRegistrador.php tests/Feature/Motor/EtapaMotorRegistradorTest.php
git commit -m "feat: grava situação, tentativas e agenda de cada etapa do motor"
```

---

### Task 3: Registrar localização, território e polígono do lote

**Files:**
- Modify: `app/Services/Geo/TerritorioProcessoService.php`, `app/Services/Geo/PoligonoEnderecamentoService.php`
- Test: `tests/Feature/Motor/RegistroDasEtapasTest.php`

**Interfaces:**
- Consumes: `EtapaMotorRegistrador::registrar(...)` (Task 2); `LocalizacaoInscricao::*` (constantes `LOCALIZADA`, `INDISPONIVEL`, `NAO_ENCONTRADA`, `SEM_COORDENADA`, `SEM_INSCRICAO`); `EnderecamentoHit::*` (`ENCONTRADO`, `AMBIGUO`, `NAO_ENCONTRADO`, `INDISPONIVEL`, `DESLIGADO`); `TerritoryResult::resumoStatus()` com `status` em `identificado|nao_encontrado|indisponivel`.
- Produces: etapas `localizacao_inscricao`, `poligono_lote`, `zona`, `via`, `bairro` gravadas como efeito colateral.

- [ ] **Step 1: Criar o teste**

Run: `php artisan make:test --phpunit Motor/RegistroDasEtapasTest`

```php
<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RegistroDasEtapasTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function processoSemPonto(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'property_registration' => '7810547',
            'property_polygon_geojson' => null,
            'property_point_lat' => null,
            'property_point_lng' => null,
        ]);
    }

    private function cadastro(callable $resolver): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($resolver) implements PropertyRegistryLookup
        {
            public function __construct(private $resolver) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                return ($this->resolver)($inscricao);
            }
        });
    }

    public function test_cadastro_fora_do_ar_registra_a_localizacao_como_indisponivel(): void
    {
        $this->cadastro(fn (string $i) => throw new PropertyRegistryUnavailableException($i));
        $processo = $this->processoSemPonto();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $etapa = $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first();

        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa->situacao);
        $this->assertNotNull($etapa->proxima_tentativa_em);
    }

    public function test_inscricao_nao_encontrada_e_definitiva(): void
    {
        $this->cadastro(fn (string $i) => throw new PropertyNotFoundException($i));
        $processo = $this->processoSemPonto();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $etapa = $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first();

        $this->assertSame(SituacaoEtapa::Definitiva, $etapa->situacao);
        $this->assertNull($etapa->proxima_tentativa_em);
    }

    public function test_localizacao_bem_sucedida_registra_ok(): void
    {
        $this->cadastro(fn (string $i) => new PropertyRegistryResult(
            latitude: -12.9738,
            longitude: -38.4803,
            inscricao: $i,
            raw: ['CodigoLogradouroTributario' => '7172', 'LogradouroTributario' => 'Rua Carlos Marighella'],
        ));
        $processo = $this->processoSemPonto();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $etapa = $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first();

        $this->assertSame(SituacaoEtapa::Ok, $etapa->situacao);
    }

    public function test_processo_que_ja_tem_ponto_nao_gera_etapa_de_localizacao(): void
    {
        $processo = $this->processoSemPonto();
        $processo->forceFill(['property_point_lat' => -12.97, 'property_point_lng' => -38.48])->save();

        app(TerritorioProcessoService::class)->localizarPelaInscricao($processo);

        $this->assertSame(0, $processo->etapas()->count());
    }
}
```

O registro de `zona`, `via`, `bairro` e `poligono_lote` é coberto no Step 3 por testes adicionais que usam os fakes já existentes (`FakeSpatialRepository` em `tests/Support/Geo`, como em `tests/Feature/Geo/LocalizacaoPelaInscricaoTest.php`): copie o `setUp` dessa classe e acrescente:

```php
    public function test_materializar_registra_zona_via_e_bairro_como_ok_quando_identificados(): void
    {
        // setUp copiado de LocalizacaoPelaInscricaoTest: camadas Bairro/Zona/Via vigentes e
        // FakeSpatialRepository com bairro 'Comércio', zona 'ZCN-1' e via CODLOG 958.
        $processo = ViabilityRequest::factory()->create([
            'property_polygon_geojson' => null,
            'property_point_lat' => -12.977769,
            'property_point_lng' => -38.516404,
        ]);

        app(TerritorioProcessoService::class)->materializar($processo);

        foreach ([EtapaMotor::Zona, EtapaMotor::Via, EtapaMotor::Bairro] as $etapa) {
            $this->assertSame(
                SituacaoEtapa::Ok,
                $processo->etapas()->where('etapa', $etapa->value)->first()->situacao,
                $etapa->value,
            );
        }
    }
```

Para o polígono, em `tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php` reutilize os fakes de `GeoServerEnderecamentoClient` e `SemapLoteClient` existentes e acrescente asserções de etapa `poligono_lote` para os quatro cenários: encontrado no endereçamento → `ok`; encontrado só no SEMAP → `ok`; ambos `nao_encontrado` → `definitiva`; qualquer um `indisponivel` sem achar → `indisponivel`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Motor/RegistroDasEtapasTest.php`
Expected: FAIL (nenhuma etapa gravada: `Call to a member function` ou `null` ao ler `->situacao`).

- [ ] **Step 3: Implementar**

Em `TerritorioProcessoService`, injetar o registrador no construtor:

```php
    public function __construct(
        private readonly TerritoryService $territory,
        private readonly LocalizacaoInscricaoService $localizacao,
        private readonly AuditService $audit,
        private readonly EtapaMotorRegistrador $etapas,
    ) {}
```

Em `localizarPelaInscricao`, logo depois de obter `$localizacao` e antes do `if ($localizacao->status !== LOCALIZADA)`:

```php
        $this->etapas->registrar(
            $request,
            EtapaMotor::LocalizacaoInscricao,
            match ($localizacao->status) {
                LocalizacaoInscricao::LOCALIZADA => SituacaoEtapa::Ok,
                LocalizacaoInscricao::INDISPONIVEL => SituacaoEtapa::Indisponivel,
                LocalizacaoInscricao::SEM_INSCRICAO => SituacaoEtapa::Pulada,
                default => SituacaoEtapa::Definitiva,
            },
            $localizacao->status === LocalizacaoInscricao::LOCALIZADA
                ? null
                : "Localização pela inscrição: {$localizacao->status}",
        );
```

Em `materializar`, depois que `$territorio` está definido (após o bloco `if ($territorio === null) { ... }`):

```php
        foreach ([
            'zona' => EtapaMotor::Zona,
            'via' => EtapaMotor::Via,
            'bairro' => EtapaMotor::Bairro,
        ] as $dimensao => $etapa) {
            $dados = $territorio->{$dimensao};

            $this->etapas->registrar(
                $request,
                $etapa,
                match ((string) ($dados['status'] ?? '')) {
                    'identificado' => SituacaoEtapa::Ok,
                    'indisponivel' => SituacaoEtapa::Indisponivel,
                    default => SituacaoEtapa::Definitiva,
                },
                isset($dados['motivo']) ? (string) $dados['motivo'] : null,
            );
        }
```

Imports a acrescentar: `App\Enums\EtapaMotor`, `App\Enums\SituacaoEtapa`, `App\Services\Motor\EtapaMotorRegistrador`.

Em `PoligonoEnderecamentoService`, injetar `private EtapaMotorRegistrador $etapas` e reescrever o final de `aplicar` para registrar a situação. Substituir o corpo por:

```php
    public function aplicar(ViabilityRequest $request): bool
    {
        $codLog = trim((string) $request->cod_log);
        $numero = trim((string) $request->address_number);
        $complemento = $request->address_complement;

        $statusEnderecamento = 'sem_chave';

        if ($codLog === '' || $numero === '') {
            $this->auditar($request, 'enderecamento', 'sem_chave', $codLog, $numero, $complemento, new EnderecamentoHit('sem_chave', motivo: 'Processo sem CODLOG ou número para consultar o endereçamento.'));
        } else {
            $hit = $this->cliente->consultar($codLog, $numero, $complemento);
            $statusEnderecamento = $hit->status;

            $this->auditar($request, 'enderecamento', $hit->status, $codLog, $numero, $complemento, $hit);

            if ($hit->encontrado()) {
                $this->gravar($request, $hit);
                $this->registrarLote($request, SituacaoEtapa::Ok);

                return true;
            }
        }

        $semap = $this->semap->consultar($request->property_registration);

        $this->auditar($request, 'semap', $semap->status, $codLog, $numero, $complemento, $semap);

        if (! $semap->encontrado()) {
            $this->registrarLote($request, $this->situacaoSemLote($statusEnderecamento, $semap->status));

            return false;
        }

        $this->gravar($request, $semap, atributosDoLote: true);
        $this->registrarLote($request, SituacaoEtapa::Ok);

        return true;
    }

    private function situacaoSemLote(string $enderecamento, string $semap): SituacaoEtapa
    {
        if ($enderecamento === EnderecamentoHit::INDISPONIVEL || $semap === EnderecamentoHit::INDISPONIVEL) {
            return SituacaoEtapa::Indisponivel;
        }

        if ($enderecamento === EnderecamentoHit::DESLIGADO && $semap === EnderecamentoHit::DESLIGADO) {
            return SituacaoEtapa::Pulada;
        }

        return SituacaoEtapa::Definitiva;
    }

    private function registrarLote(ViabilityRequest $request, SituacaoEtapa $situacao): void
    {
        $this->etapas->registrar(
            $request,
            EtapaMotor::PoligonoLote,
            $situacao,
            $situacao === SituacaoEtapa::Ok ? null : 'Polígono do lote não obtido pelo endereçamento nem pelo SEMAP',
        );
    }
```

(Manter o texto original do `motivo` do `EnderecamentoHit('sem_chave', ...)` exatamente como está hoje no arquivo.)

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Motor tests/Feature/Geo`
Expected: PASS. Se algum teste existente construir esses serviços com `new`, ajuste-o para resolver pelo container.

- [ ] **Step 5: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Geo/TerritorioProcessoService.php app/Services/Geo/PoligonoEnderecamentoService.php tests/Feature/Motor/RegistroDasEtapasTest.php tests/Feature/Geo/PoligonoEnderecamentoProtocoloTest.php
git commit -m "feat: registra localização, zona, via, bairro e polígono do lote por etapa"
```

---

### Task 4: Registrar a etapa das regras territoriais

**Files:**
- Modify: `app/Services/Territorial/TerritorialRuleService.php`
- Test: `tests/Feature/Louos/TerritorialRuleServiceTest.php`

**Interfaces:**
- Consumes: `EtapaMotorRegistrador::registrar` (Task 2).
- Produces: etapa `regra_territorial` (`ok` ou `indisponivel`) gravada ao fim de `avaliar()` quando há ponto.

- [ ] **Step 1: Escrever os testes** (em `TerritorialRuleServiceTest`, que já faz `Http::fake` das camadas)

```php
    public function test_camada_indisponivel_registra_a_etapa_de_regra_territorial_como_indisponivel(): void
    {
        Http::fake(fn () => Http::response('erro', 500));

        $request = $this->solicitacao([], ['9602501']);

        $this->avaliar($request);

        $etapa = $request->etapas()->where('etapa', 'regra_territorial')->first();

        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa->situacao);
        $this->assertSame('geoserver_camadas', $etapa->servico);
    }

    public function test_camada_respondendo_registra_a_etapa_como_ok(): void
    {
        Http::fake(fn () => Http::response(['type' => 'FeatureCollection', 'features' => []]));

        $request = $this->solicitacao([], ['9602501']);

        $this->avaliar($request);

        $this->assertSame(
            SituacaoEtapa::Ok,
            $request->etapas()->where('etapa', 'regra_territorial')->first()->situacao,
        );
    }

    public function test_sem_ponto_nao_registra_a_etapa_de_regra_territorial(): void
    {
        $request = $this->solicitacao([], ['9602501']);

        app(TerritorialRuleService::class)->avaliar($request, null);

        $this->assertSame(0, $request->etapas()->where('etapa', 'regra_territorial')->count());
    }
```

Acrescentar `use App\Enums\SituacaoEtapa;` ao teste.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact --filter=etapa_de_regra_territorial tests/Feature/Louos/TerritorialRuleServiceTest.php`
Expected: FAIL (`Call to a member function on null`).

- [ ] **Step 3: Implementar**

Em `TerritorialRuleService`: injetar `private EtapaMotorRegistrador $etapas` no construtor, acrescentar a propriedade `private bool $camadaIndisponivel = false;`, renomear o método público atual `avaliar` para `private function avaliarRegras(...)` (mesma assinatura e corpo) e criar o novo `avaliar`:

```php
    public function avaliar(ViabilityRequest $request, ?array $ponto): TerritorialRuleVerdict
    {
        $this->camadaIndisponivel = false;

        $verdict = $this->avaliarRegras($request, $ponto);

        if ($ponto !== null) {
            $this->etapas->registrar(
                $request,
                EtapaMotor::RegraTerritorial,
                $this->camadaIndisponivel ? SituacaoEtapa::Indisponivel : SituacaoEtapa::Ok,
                $this->camadaIndisponivel ? 'Camada territorial indisponível no GeoServer' : null,
            );
        }

        return $verdict;
    }
```

Em `casarPoligonal` e `casarPoligonalAllowlist`, dentro de cada `if ($hit->indisponivel()) {`, antes do `return`, acrescentar `$this->camadaIndisponivel = true;`.

Manter o docblock original em `avaliar`. Imports: `App\Enums\EtapaMotor`, `App\Enums\SituacaoEtapa`, `App\Services\Motor\EtapaMotorRegistrador`.

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Louos tests/Feature/Expresso/FluxoExpressoTerritorialTest.php`
Expected: PASS.

- [ ] **Step 5: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Territorial/TerritorialRuleService.php tests/Feature/Louos/TerritorialRuleServiceTest.php
git commit -m "feat: registra a etapa das regras territoriais"
```

---

### Task 5: Reavaliar processo protocolado ou em análise não assumida

**Files:**
- Modify: `app/Services/Expresso/FluxoExpressoService.php`
- Test: `tests/Feature/Expresso/FluxoExpressoReavaliarTest.php`

**Interfaces:**
- Consumes: invariantes existentes de `decidirSobLock`, `encaminharAnalise`, `emitir`, `capturarQueda`.
- Produces: `FluxoExpressoService::reavaliar(ViabilityRequest $request, ?User $actor = null): DecisionResult`. Em processo `em_analise` + `para_distribuir` sem decisão, refaz a resolução: veredito definitivo transiciona para `deferida`/`indeferida` e zera os campos de análise; veredito ainda pendente só atualiza motivo e registra auditoria, sem nova transição e sem novo `EncaminhadoParaAnalise`.

- [ ] **Step 1: Criar o teste**

Run: `php artisan make:test --phpunit Expresso/FluxoExpressoReavaliarTest`

Reaproveite o `setUp` e os helpers de `tests/Feature/Expresso/DecidirFluxoExpressoJobTest.php` (`protocoladaComCnaes`, `fakeBairroComZona`, `classificarMunicipal`, `seedTratamento`, `seedQuadro10`, `setupDeferivel`). Casos:

```php
    public function test_processo_em_analise_nao_assumido_e_deferido_quando_os_dados_completam(): void
    {
        Event::fake([ResultadoEmitido::class, EncaminhadoParaAnalise::class]);
        $request = $this->setupDeferivel();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'analysis_stage' => AnalysisStage::Distribuicao,
        ])->save();

        $resultado = app(FluxoExpressoService::class)->reavaliar($request);

        $request->refresh();

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->status);
        $this->assertNull($request->analysis_status);
        $this->assertNull($request->analysis_stage);
        $this->assertNotNull($request->decision);
        $this->assertTrue($resultado->emitted);
    }

    public function test_analise_ja_assumida_nao_e_reavaliada(): void
    {
        $request = $this->setupDeferivel();
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::EmAnalise,
        ])->save();

        app(FluxoExpressoService::class)->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertDatabaseCount('viability_decisions', 0);
    }

    public function test_veredito_ainda_pendente_mantem_em_analise_sem_novo_encaminhamento(): void
    {
        Event::fake([EncaminhadoParaAnalise::class]);
        $request = $this->protocoladaComCnaes(['2222222']);
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
        ])->save();
        $transicoesAntes = $request->transitions()->count();

        app(FluxoExpressoService::class)->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::EmAnalise, $request->fresh()->status);
        $this->assertSame($transicoesAntes, $request->transitions()->count());
        Event::assertNotDispatched(EncaminhadoParaAnalise::class);
    }

    public function test_reavaliacao_com_o_mesmo_motivo_nao_duplica_a_queda(): void
    {
        $request = $this->protocoladaComCnaes(['2222222']);
        $request->forceFill([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
        ])->save();
        $servico = app(FluxoExpressoService::class);

        $servico->reavaliar($request);
        $servico->reavaliar($request);

        $this->assertSame(1, ExpressoQueda::query()->where('viability_request_id', $request->id)->count());
    }

    public function test_processo_protocolado_segue_o_caminho_de_decisao_normal(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $request = $this->setupDeferivel();

        app(FluxoExpressoService::class)->reavaliar($request);

        $this->assertSame(ViabilityRequestStatus::Deferida, $request->fresh()->status);
    }
```

Imports do teste: `AnalysisStage`, `AnalysisStatus`, `ViabilityRequestStatus`, `EncaminhadoParaAnalise`, `ResultadoEmitido`, `ExpressoQueda`, `FluxoExpressoService`, `Event`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Expresso/FluxoExpressoReavaliarTest.php`
Expected: FAIL (`Call to undefined method ...::reavaliar()`).

- [ ] **Step 3: Implementar**

Em `FluxoExpressoService`, acrescentar após `decide()`:

```php
    /**
     * Reavalia o veredito: processo protocolado decide como sempre; processo em
     * análise que ninguém assumiu (para_distribuir, sem decisão) refaz a resolução
     * com os dados atuais. Análise já assumida nunca é sobrescrita.
     */
    public function reavaliar(ViabilityRequest $request, ?User $actor = null): DecisionResult
    {
        $request->refresh();

        if ($request->status === ViabilityRequestStatus::Protocolada) {
            return $this->decide($request, $actor);
        }

        if (! $this->reavaliavelEmAnalise($request)) {
            return $this->resultadoIdempotente($request);
        }

        $ttl = (int) config('sile.expresso.lock.ttl_segundos', 10);

        return Cache::lock("expresso:decisao:{$request->id}", $ttl)->block(
            $ttl,
            fn (): DecisionResult => $this->decidirSobLock($request, $actor),
        );
    }

    private function reavaliavelEmAnalise(ViabilityRequest $request): bool
    {
        return $request->status === ViabilityRequestStatus::EmAnalise
            && $request->analysis_status === AnalysisStatus::ParaDistribuir
            && $request->decision()->doesntExist();
    }
```

No início de `decidirSobLock`, trocar o guard:

```php
        $request->refresh();

        if ($request->status !== ViabilityRequestStatus::Protocolada && ! $this->reavaliavelEmAnalise($request)) {
            return $this->resultadoIdempotente($request);
        }
```

No início de `encaminharAnalise`, antes de abrir a transação:

```php
        if ($request->status === ViabilityRequestStatus::EmAnalise) {
            return $this->manterEmAnalise($request, $reason, $actor, $resolved, $territorial, $gatilho);
        }
```

E o método novo:

```php
    /**
     * Reavaliação que continua pendente: não há nova transição, SLA nem evento.
     * Registra a reavaliação na auditoria e, se o motivo mudou, acrescenta a
     * queda (a anterior permanece como histórico).
     */
    private function manterEmAnalise(
        ViabilityRequest $request,
        string $reason,
        ?User $actor,
        ?ResolvedViability $resolved,
        ?TerritorialRuleVerdict $territorial,
        ?TipoGatilho $gatilho,
    ): DecisionResult {
        DB::transaction(function () use ($request, $reason, $resolved, $territorial, $gatilho): void {
            $ultimoMotivo = ExpressoQueda::query()
                ->where('viability_request_id', $request->id)
                ->latest('id')
                ->value('motivo');

            if ($ultimoMotivo !== $reason) {
                $this->capturarQueda($request, $reason, $resolved, $territorial, $gatilho);
            }

            $this->audit->log(
                logName: 'expresso',
                event: 'reavaliacao',
                description: "Reavaliação da solicitação #{$request->id}: continua em análise",
                properties: [
                    'viability_request_id' => $request->id,
                    'protocol_number' => $request->protocol_number,
                    'resultado' => 'analise',
                    'motivo' => $reason,
                ],
                subject: $request,
                result: 'analise',
            );
        });

        return DecisionResult::paraAnalise($reason);
    }
```

Em `emitir`, dentro da transação, logo depois do `$this->stateMachine->transition(...)`:

```php
                if ($request->analysis_status !== null) {
                    $request->forceFill([
                        'analysis_status' => null,
                        'analysis_stage' => null,
                        'analysis_due_at' => null,
                    ])->save();
                }
```

Imports: `App\Enums\AnalysisStatus` (os demais já existem no arquivo).

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Expresso`
Expected: PASS, sem regressão nos testes existentes de decisão.

- [ ] **Step 5: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Expresso/FluxoExpressoService.php tests/Feature/Expresso/FluxoExpressoReavaliarTest.php
git commit -m "feat: reavalia o veredito de processo em análise ainda não assumida"
```

---

### Task 6: ReprocessarEtapasService e comando agendado

**Files:**
- Create: `app/Services/Motor/ReprocessarEtapasService.php`, `app/Services/Motor/ReprocessoResultado.php`, `app/Console/Commands/ReprocessarEtapasCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Motor/ReprocessarEtapasServiceTest.php`, `tests/Feature/Motor/ReprocessarEtapasCommandTest.php`

**Interfaces:**
- Consumes: `ProtocolarSolicitacaoService::completarTerritorio`, `TerritorioProcessoService::localizarPelaInscricao`, `FluxoExpressoService::reavaliar`, etapas (Tasks 1 a 5).
- Produces: `ReprocessarEtapasService::reprocessar(ViabilityRequest $request, ?User $actor = null): ReprocessoResultado`; `ReprocessoResultado` com `bool $reprocessou`, `?EtapaMotor $paradaEm`, `string $statusDoProcesso`, `string $mensagem`; comando `processos:reprocessar-etapas {--processo=} {--limite=}`.

- [ ] **Step 1: Criar os testes**

Run: `php artisan make:test --phpunit Motor/ReprocessarEtapasServiceTest` e `php artisan make:test --phpunit Motor/ReprocessarEtapasCommandTest`

Serviço (use o `cadastro()` fake do Task 3):

```php
    public function test_sem_etapa_indisponivel_nao_faz_nada(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create();

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        $this->assertFalse($resultado->reprocessou);
    }

    public function test_para_na_localizacao_quando_o_cadastro_continua_fora_do_ar(): void
    {
        $this->cadastro(fn (string $i) => throw new PropertyRegistryUnavailableException($i));
        $processo = $this->processoEmAnaliseSemPonto();
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::LocalizacaoInscricao,
        ]);

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        $this->assertTrue($resultado->reprocessou);
        $this->assertSame(EtapaMotor::LocalizacaoInscricao, $resultado->paradaEm);
        $this->assertSame(ViabilityRequestStatus::EmAnalise, $processo->fresh()->status);
    }

    public function test_cadastro_de_volta_grava_o_ponto_e_reavalia(): void
    {
        $this->cadastro(fn (string $i) => new PropertyRegistryResult(
            latitude: -12.9738,
            longitude: -38.4803,
            inscricao: $i,
            raw: ['CodigoLogradouroTributario' => '7172', 'LogradouroTributario' => 'Rua Carlos Marighella'],
        ));
        $processo = $this->processoEmAnaliseSemPonto();
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::LocalizacaoInscricao,
        ]);

        $resultado = app(ReprocessarEtapasService::class)->reprocessar($processo);

        $processo->refresh();

        $this->assertNull($resultado->paradaEm);
        $this->assertEqualsWithDelta(-12.9738, (float) $processo->property_point_lat, 0.0000001);
        $this->assertSame(
            SituacaoEtapa::Ok,
            $processo->etapas()->where('etapa', EtapaMotor::LocalizacaoInscricao->value)->first()->situacao,
        );
    }

    public function test_registra_o_reprocesso_na_auditoria_com_o_ator(): void
    {
        $this->cadastro(fn (string $i) => throw new PropertyRegistryUnavailableException($i));
        $ator = User::factory()->analista()->create();
        $processo = $this->processoEmAnaliseSemPonto();
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::LocalizacaoInscricao,
        ]);

        app(ReprocessarEtapasService::class)->reprocessar($processo, $ator);

        $log = Activity::query()->where('log_name', 'motor')->where('event', 'reprocesso')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($processo->id, $log->properties['viability_request_id']);
        $this->assertSame($ator->id, $log->properties['ator_id']);
    }
```

com o helper:

```php
    private function processoEmAnaliseSemPonto(): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'property_registration' => '7810547',
            'property_polygon_geojson' => null,
            'property_point_lat' => null,
            'property_point_lng' => null,
        ]);
    }
```

Comando:

```php
    public function test_elege_so_etapa_indisponivel_vencida_e_nao_esgotada(): void
    {
        $vencida = ViabilityRequestEtapa::factory()->indisponivel()->create([
            'etapa' => EtapaMotor::Zona,
            'proxima_tentativa_em' => now()->subMinute(),
        ]);
        $futura = ViabilityRequestEtapa::factory()->indisponivel()->create([
            'etapa' => EtapaMotor::Zona,
            'proxima_tentativa_em' => now()->addHour(),
        ]);
        $esgotada = ViabilityRequestEtapa::factory()->indisponivel()->create([
            'etapa' => EtapaMotor::Zona,
            'proxima_tentativa_em' => null,
            'esgotada_em' => now(),
        ]);

        $servico = Mockery::mock(ReprocessarEtapasService::class);
        $servico->shouldReceive('reprocessar')
            ->once()
            ->withArgs(fn (ViabilityRequest $p) => $p->id === $vencida->viability_request_id)
            ->andReturn(new ReprocessoResultado(true, null, 'em_analise', 'ok'));
        $this->app->instance(ReprocessarEtapasService::class, $servico);

        $this->artisan('processos:reprocessar-etapas')->assertSuccessful();
    }

    public function test_opcao_processo_reprocessa_so_aquele_processo(): void
    {
        $etapa = ViabilityRequestEtapa::factory()->indisponivel()->create(['proxima_tentativa_em' => now()->addDay()]);

        $servico = Mockery::mock(ReprocessarEtapasService::class);
        $servico->shouldReceive('reprocessar')->once()->andReturn(new ReprocessoResultado(true, null, 'em_analise', 'ok'));
        $this->app->instance(ReprocessarEtapasService::class, $servico);

        $this->artisan('processos:reprocessar-etapas', ['--processo' => $etapa->viability_request_id])->assertSuccessful();
    }

    public function test_sem_candidatos_informa_e_sai_com_sucesso(): void
    {
        $this->artisan('processos:reprocessar-etapas')
            ->expectsOutput('Nenhum processo com etapa indisponível a reprocessar.')
            ->assertSuccessful();
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Motor/ReprocessarEtapasServiceTest.php tests/Feature/Motor/ReprocessarEtapasCommandTest.php`
Expected: FAIL (classes inexistentes).

- [ ] **Step 3: Implementar**

`app/Services/Motor/ReprocessoResultado.php`:

```php
<?php

namespace App\Services\Motor;

use App\Enums\EtapaMotor;

final readonly class ReprocessoResultado
{
    public function __construct(
        public bool $reprocessou,
        public ?EtapaMotor $paradaEm,
        public string $statusDoProcesso,
        public string $mensagem,
    ) {}
}
```

`app/Services/Motor/ReprocessarEtapasService.php`:

```php
<?php

namespace App\Services\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Support\Audit\AuditService;

/**
 * Refaz as etapas do motor que falharam por serviço indisponível, na ordem, e
 * reavalia o veredito. Para na primeira etapa que continua indisponível: sem a
 * localização pela inscrição as demais não têm o que consultar.
 */
class ReprocessarEtapasService
{
    public function __construct(
        private TerritorioProcessoService $territorio,
        private ProtocolarSolicitacaoService $protocolo,
        private FluxoExpressoService $expresso,
        private AuditService $audit,
    ) {}

    public function reprocessar(ViabilityRequest $request, ?User $actor = null): ReprocessoResultado
    {
        $pendentes = $request->etapas()
            ->where('situacao', SituacaoEtapa::Indisponivel->value)
            ->get()
            ->sortBy(fn ($etapa) => $etapa->etapa->ordem());

        if ($pendentes->isEmpty()) {
            return new ReprocessoResultado(false, null, $request->status->value, 'Nenhuma etapa indisponível a reprocessar.');
        }

        $paradaEm = null;

        if ($pendentes->contains(fn ($etapa) => $etapa->etapa === EtapaMotor::LocalizacaoInscricao)
            && $this->territorio->localizarPelaInscricao($request) === LocalizacaoInscricao::INDISPONIVEL) {
            $paradaEm = EtapaMotor::LocalizacaoInscricao;
        }

        if ($paradaEm === null) {
            $this->protocolo->completarTerritorio($request);
            $this->expresso->reavaliar($request, $actor);
        }

        $request->refresh();

        $mensagem = $paradaEm === null
            ? 'Etapas reprocessadas e veredito reavaliado.'
            : "Serviço de {$paradaEm->rotulo()} continua indisponível. Nova tentativa agendada.";

        $this->audit->log(
            logName: 'motor',
            event: 'reprocesso',
            description: "Reprocesso das etapas da solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'ator_id' => $actor?->id,
                'parada_em' => $paradaEm?->value,
                'status_do_processo' => $request->status->value,
            ],
            subject: $request,
            result: $paradaEm === null ? 'sucesso' : 'parcial',
        );

        return new ReprocessoResultado(true, $paradaEm, $request->status->value, $mensagem);
    }
}
```

`app/Console/Commands/ReprocessarEtapasCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Motor\ReprocessarEtapasService;
use Illuminate\Console\Command;

/**
 * Reprocessa os processos com etapa do motor indisponível cuja próxima tentativa
 * venceu. Idempotente e segura em multi-instância (agendada com onOneServer).
 */
class ReprocessarEtapasCommand extends Command
{
    protected $signature = 'processos:reprocessar-etapas
        {--processo= : Reprocessa só este processo, ignorando a agenda}
        {--limite= : Máximo de processos nesta passada}';

    protected $description = 'Reprocessa as etapas do motor que falharam por serviço indisponível';

    public function handle(ReprocessarEtapasService $servico): int
    {
        $query = ViabilityRequest::query()->whereHas('etapas', function ($etapas): void {
            $etapas->where('situacao', SituacaoEtapa::Indisponivel->value);
        });

        if ($this->option('processo') !== null) {
            $query->whereKey((int) $this->option('processo'));
        } else {
            $query->whereHas('etapas', function ($etapas): void {
                $etapas->where('situacao', SituacaoEtapa::Indisponivel->value)
                    ->whereNull('esgotada_em')
                    ->where('proxima_tentativa_em', '<=', now());
            });
        }

        $query->orderBy('id');

        if ($this->option('limite') !== null && (int) $this->option('limite') > 0) {
            $query->limit((int) $this->option('limite'));
        }

        $processos = $query->get();

        if ($processos->isEmpty()) {
            $this->info('Nenhum processo com etapa indisponível a reprocessar.');

            return self::SUCCESS;
        }

        foreach ($processos as $processo) {
            $resultado = $servico->reprocessar($processo);
            $this->line("#{$processo->id}: {$resultado->mensagem}");
        }

        return self::SUCCESS;
    }
}
```

Em `routes/console.php`, depois do bloco `expresso:reavaliar`:

```php
// Reprocessa as etapas do motor que falharam por serviço de terceiro indisponível
// (spec 2026-10-05). Respeita a agenda de cada etapa; esgota em 24 h.
Schedule::command('processos:reprocessar-etapas')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
```

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Motor`
Expected: PASS.

- [ ] **Step 5: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Motor app/Console/Commands/ReprocessarEtapasCommand.php routes/console.php tests/Feature/Motor
git commit -m "feat: reprocessa as etapas do motor por comando e agendamento"
```

---

### Task 7: Permissão, rota e endpoint de reprocesso manual

**Files:**
- Create: `app/Http/Controllers/Gestao/ReprocessarProcessoController.php`
- Modify: `database/seeders/RolesAndPermissionsSeeder.php`, `routes/gestao.php`
- Test: `tests/Feature/Motor/ReprocessarProcessoEndpointTest.php`

**Interfaces:**
- Consumes: `ReprocessarEtapasService::reprocessar`.
- Produces: `POST /gestao/processos/{viabilityRequest}/reprocessar`, rota `gestao.processos.reprocessar`, permissão `reprocessar-processos`; resposta `back()->with('status', <mensagem>)`.

- [ ] **Step 1: Criar o teste**

Run: `php artisan make:test --phpunit Motor/ReprocessarProcessoEndpointTest`

```php
<?php

namespace Tests\Feature\Motor;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Motor\ReprocessarEtapasService;
use App\Services\Motor\ReprocessoResultado;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReprocessarProcessoEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_analista_reprocessa_o_processo_e_ve_a_mensagem(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();

        $servico = Mockery::mock(ReprocessarEtapasService::class);
        $servico->shouldReceive('reprocessar')
            ->once()
            ->withArgs(fn (ViabilityRequest $p, ?User $u) => $p->is($processo) && $u?->is($analista))
            ->andReturn(new ReprocessoResultado(true, null, 'em_analise', 'Etapas reprocessadas e veredito reavaliado.'));
        $this->app->instance(ReprocessarEtapasService::class, $servico);

        $this->actingAs($analista)
            ->post(route('gestao.processos.reprocessar', $processo))
            ->assertRedirect()
            ->assertSessionHas('status', 'Etapas reprocessadas e veredito reavaliado.');
    }

    public function test_administrador_tambem_pode_reprocessar(): void
    {
        $this->assertTrue(User::factory()->administrador()->withAcceptedLgpdTerm()->create()->can('reprocessar-processos'));
    }

    public function test_usuario_sem_a_permissao_recebe_403(): void
    {
        $usuario = User::factory()->withAcceptedLgpdTerm()->create();
        $processo = ViabilityRequest::factory()->create();

        $this->actingAs($usuario)
            ->post(route('gestao.processos.reprocessar', $processo))
            ->assertForbidden();
    }
}
```

Se a factory não tiver o estado `administrador()`, use o padrão do `FichaCadastroImobiliarioInertiaTest`/outros testes do repositório para criar um administrador (`grep -rn "administrador()" tests | head -3`).

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Motor/ReprocessarProcessoEndpointTest.php`
Expected: FAIL (rota e permissão inexistentes).

- [ ] **Step 3: Implementar**

Em `database/seeders/RolesAndPermissionsSeeder.php`: acrescentar `'reprocessar-processos'` à lista de permissões criadas (o mesmo array onde está `'analisar-processos'`) e a `givePermissionTo` das roles `analista` e `administrador`.

`app/Http/Controllers/Gestao/ReprocessarProcessoController.php`:

```php
<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Models\ViabilityRequest;
use App\Services\Motor\ReprocessarEtapasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReprocessarProcessoController extends Controller
{
    public function store(Request $request, ViabilityRequest $viabilityRequest, ReprocessarEtapasService $servico): RedirectResponse
    {
        $resultado = $servico->reprocessar($viabilityRequest, $request->user());

        return back()->with('status', $resultado->mensagem);
    }
}
```

Em `routes/gestao.php`, junto das rotas de processo (fora do grupo `analisar-processos`, para que a permissão seja a própria):

```php
        Route::middleware('permission:reprocessar-processos')->post(
            'processos/{viabilityRequest}/reprocessar',
            [ReprocessarProcessoController::class, 'store'],
        )->name('processos.reprocessar');
```

com `use App\Http\Controllers\Gestao\ReprocessarProcessoController;` no topo. Confirmar com `php artisan route:list --name=reprocessar` que o nome é `gestao.processos.reprocessar` (o grupo pai já prefixa `gestao.`).

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test --compact tests/Feature/Motor/ReprocessarProcessoEndpointTest.php`
Expected: PASS. Se o teste de papéis do repositório contar permissões, atualize a contagem esperada.

- [ ] **Step 5: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/ReprocessarProcessoController.php database/seeders/RolesAndPermissionsSeeder.php routes/gestao.php tests/Feature/Motor/ReprocessarProcessoEndpointTest.php
git commit -m "feat: permite reprocessar o processo pela ficha"
```

---

### Task 8: Etapas do motor na ficha de análise

**Files:**
- Create: `resources/js/components/analise/etapas-motor.tsx`
- Modify: `app/Http/Controllers/Gestao/AnalysisRecordController.php`, `resources/js/pages/gestao/ficha-analise/show.tsx`
- Test: `tests/Feature/Motor/FichaEtapasDoMotorTest.php`

**Interfaces:**
- Consumes: etapas (Task 1), rota `gestao.processos.reprocessar` (Task 7).
- Produces: props Inertia `etapasMotor: list<{etapa, rotulo, servico, situacao, situacao_rotulo, motivo, tentativas, proxima_tentativa_em, esgotada}>` e `podeReprocessar: bool` na página `gestao/ficha-analise/show`.

- [ ] **Step 1: Criar o teste** (seguir `FichaCadastroImobiliarioInertiaTest`: mesmo `setUp`, `analista()` e `processoComFicha()`)

```php
    public function test_ficha_traz_as_etapas_do_motor_em_ordem_e_a_permissao_de_reprocessar(): void
    {
        $analista = $this->analista();
        $processo = $this->processoComFicha();
        ViabilityRequestEtapa::factory()->create(['viability_request_id' => $processo->id, 'etapa' => EtapaMotor::Zona]);
        ViabilityRequestEtapa::factory()->indisponivel()->create([
            'viability_request_id' => $processo->id,
            'etapa' => EtapaMotor::LocalizacaoInscricao,
        ]);

        $this->actingAs($analista)
            ->get(route('gestao.processos.ficha.show', $processo))
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('podeReprocessar', true)
                ->has('etapasMotor', 2)
                ->where('etapasMotor.0.etapa', 'localizacao_inscricao')
                ->where('etapasMotor.0.situacao', 'indisponivel')
                ->where('etapasMotor.0.servico', 'inscricao_imobiliaria')
                ->where('etapasMotor.1.etapa', 'zona'));
    }

    public function test_ficha_sem_etapas_traz_lista_vazia(): void
    {
        $analista = $this->analista();
        $processo = $this->processoComFicha();

        $this->actingAs($analista)
            ->get(route('gestao.processos.ficha.show', $processo))
            ->assertInertia(fn (Assert $page) => $page->has('etapasMotor', 0));
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test --compact tests/Feature/Motor/FichaEtapasDoMotorTest.php`
Expected: FAIL (`Property [etapasMotor] does not exist`).

- [ ] **Step 3: Implementar o payload**

Em `AnalysisRecordController::show`, no array do `Inertia::render`, depois de `'recepcao' => ...`:

```php
            'etapasMotor' => $this->etapasDoMotor($viabilityRequest),
            'podeReprocessar' => (bool) request()->user()?->can('reprocessar-processos'),
```

E o método privado:

```php
    /**
     * @return list<array<string, mixed>>
     */
    private function etapasDoMotor(ViabilityRequest $processo): array
    {
        return $processo->etapas()
            ->get()
            ->sortBy(fn ($etapa) => $etapa->etapa->ordem())
            ->map(fn ($etapa): array => [
                'etapa' => $etapa->etapa->value,
                'rotulo' => $etapa->etapa->rotulo(),
                'servico' => $etapa->servico,
                'situacao' => $etapa->situacao->value,
                'situacao_rotulo' => $etapa->situacao->rotulo(),
                'motivo' => $etapa->motivo,
                'tentativas' => $etapa->tentativas,
                'proxima_tentativa_em' => $etapa->proxima_tentativa_em?->toIso8601String(),
                'esgotada' => $etapa->esgotada_em !== null,
            ])
            ->values()
            ->all();
    }
```

- [ ] **Step 4: Componente e uso em `show.tsx`**

`resources/js/components/analise/etapas-motor.tsx`:

```tsx
import { router } from '@inertiajs/react';
import { useState } from 'react';
import Badge from '@/components/ui/badge';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

export interface EtapaMotor {
    etapa: string;
    rotulo: string;
    servico: string;
    situacao: 'ok' | 'indisponivel' | 'definitiva' | 'pulada';
    situacao_rotulo: string;
    motivo: string | null;
    tentativas: number;
    proxima_tentativa_em: string | null;
    esgotada: boolean;
}

interface Props {
    processoId: number;
    etapas: EtapaMotor[];
    podeReprocessar: boolean;
}

const marca: Record<EtapaMotor['situacao'], string> = {
    ok: '✓',
    indisponivel: '✗',
    definitiva: '–',
    pulada: '–',
};

export function temEtapaIndisponivel(etapas: EtapaMotor[]): boolean {
    return etapas.some((etapa) => etapa.situacao === 'indisponivel');
}

export function EtapasMotorCard({ processoId, etapas, podeReprocessar }: Props) {
    const [processando, setProcessando] = useState(false);

    if (etapas.length === 0) {
        return null;
    }

    function reprocessar() {
        router.post(
            `/gestao/processos/${processoId}/reprocessar`,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessando(true),
                onFinish: () => setProcessando(false),
            },
        );
    }

    return (
        <Card>
            <CardHeader
                title="Etapas do motor"
                description="Onde o motor parou por serviço de terceiro indisponível. O reprocesso refaz só o que falhou."
            />
            <CardContent>
                <ul className="divide-y divide-border">
                    {etapas.map((etapa) => (
                        <li key={etapa.etapa} className="flex flex-wrap items-center gap-3 py-2">
                            <span aria-hidden="true" className="w-4 text-center font-medium">
                                {marca[etapa.situacao]}
                            </span>
                            <span className="min-w-48 font-medium">{etapa.rotulo}</span>
                            <Badge>{etapa.situacao_rotulo}</Badge>
                            {etapa.motivo && <span className="text-sm text-muted-foreground">{etapa.motivo}</span>}
                            {etapa.situacao === 'indisponivel' && (
                                <span className="text-sm text-muted-foreground">
                                    {etapa.esgotada
                                        ? 'Tentativas automáticas esgotadas'
                                        : `Tentativas: ${etapa.tentativas}`}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
                {podeReprocessar && temEtapaIndisponivel(etapas) && (
                    <div className="mt-4">
                        <Button type="button" onClick={reprocessar} disabled={processando}>
                            {processando ? 'Reprocessando…' : 'Reprocessar'}
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
```

Em `show.tsx`: importar `import { EtapasMotorCard, type EtapaMotor } from '@/components/analise/etapas-motor';`, acrescentar `etapasMotor: EtapaMotor[]; podeReprocessar: boolean;` à interface de props (junto de `recepcao`), desestruturar os dois no componente e renderizar logo antes do bloco `{recepcao && (`:

```tsx
                <EtapasMotorCard
                    processoId={processo.id}
                    etapas={etapasMotor ?? []}
                    podeReprocessar={podeReprocessar ?? false}
                />
```

Teste de unidade do front (`resources/js/components/analise/etapas-motor.test.ts`, no estilo de `processo-ui.test.ts`):

```ts
import { describe, expect, it } from 'vitest';
import { temEtapaIndisponivel, type EtapaMotor } from './etapas-motor';

const etapa = (situacao: EtapaMotor['situacao']): EtapaMotor => ({
    etapa: 'zona',
    rotulo: 'Zona urbanística',
    servico: 'geoserver_zona',
    situacao,
    situacao_rotulo: situacao,
    motivo: null,
    tentativas: 1,
    proxima_tentativa_em: null,
    esgotada: false,
});

describe('temEtapaIndisponivel', () => {
    it('só aceita reprocessar quando alguma etapa está indisponível', () => {
        expect(temEtapaIndisponivel([etapa('ok'), etapa('indisponivel')])).toBe(true);
        expect(temEtapaIndisponivel([etapa('ok'), etapa('definitiva')])).toBe(false);
    });
});
```

- [ ] **Step 5: Verificar e commitar**

Run:

```bash
php artisan test --compact tests/Feature/Motor/FichaEtapasDoMotorTest.php
npx vitest run resources/js/components/analise/etapas-motor.test.ts
npx tsc --noEmit
npm run build
vendor/bin/pint --dirty --format agent
```

Expected: PHP e Vitest passam. Se o `tsc` apontar erro, confirme que são só os anteriores em `malha-fina`, `distribuicao-inteligente` e `modal.test` (existentes antes desta mudança). O `public/build` entra no commit, porque o Dockerfile não roda `npm run build`.

```bash
git add resources/js/components/analise/etapas-motor.tsx resources/js/components/analise/etapas-motor.test.ts resources/js/pages/gestao/ficha-analise/show.tsx app/Http/Controllers/Gestao/AnalysisRecordController.php tests/Feature/Motor/FichaEtapasDoMotorTest.php public/build
git commit -m "feat: mostra as etapas do motor e o botão de reprocessar na ficha"
```

---

### Task 9: Verificação final

**Files:** nenhum novo.

- [ ] **Step 1: Suíte completa**

Run: `php artisan test --compact`
Expected: sem falhas novas. As únicas falhas aceitáveis são as que já existiam sem esta mudança: `DecisionTextCrudTest::test_lista_agrupa_os_textos_por_prefixo_da_chave`, `DecisionTextCrudTest::test_edicao_do_template_persiste_e_o_motor_emite_o_novo_texto` e `EnvironmentAccessTest::test_servidor_autentica_pelo_login_interno`.

- [ ] **Step 2: Conferir o agendamento e a rota**

```bash
php artisan schedule:list | grep reprocessar
php artisan route:list --name=reprocessar
php artisan processos:reprocessar-etapas --help
```

Expected: o comando aparece a cada 15 minutos, a rota é `gestao.processos.reprocessar` e o `--help` lista `--processo` e `--limite`.

- [ ] **Step 3: Rodar a migration em banco limpo**

Run: `php artisan migrate:fresh --seed --env=testing` (ou o equivalente do ambiente de teste do repositório).
Expected: sem erro.

- [ ] **Step 4: Preparar a publicação**

Atualizar `VERSION` (próxima versão MINOR) e seguir o fluxo `publicar-softwell-semit` somente com pedido explícito.

---

## Self-Review

**Spec coverage**

| Requisito da spec | Task |
|---|---|
| Registrar o resultado de cada etapa que depende de terceiro | 1, 2, 3, 4 |
| Mostrar na ficha onde parou, qual serviço e se é transitória | 8 |
| Reprocessar da etapa que falhou (botão, comando, agendamento) | 6, 7, 8 |
| Reavaliar o veredito, inclusive processo em análise não assumido | 5 |
| Só `indisponivel` reprocessa; `definitiva` nunca | 2, 3, 6 |
| Parar na primeira etapa indisponível | 6 |
| Intervalo 15 min / 60 min e esgotamento em 24 h | 2 |
| Análise assumida nunca é sobrescrita | 5 |
| Veredito antigo permanece como histórico | 5 (`manterEmAnalise` acrescenta, não apaga) |
| Permissão `reprocessar-processos` para analista e administrador | 7 |
| Auditoria de cada tentativa sem dado pessoal | 2, 6 |
| Vale para todos os processos | 3 a 6 (nenhuma condição de origem) |

**Desvios da spec, declarados:** a tabela ganhou a coluna `primeira_falha_em` (a janela de 24 horas conta desde a primeira falha e a spec não a listava); a permissão ficou com o nome `reprocessar-processos`, no padrão kebab-case do repositório, em vez de `processos.reprocessar`.

**Limitação documentada:** o agendamento reprocessa o processo enquanto houver etapa `indisponivel` dentro da janela, mesmo se o processo também estiver em análise por outro motivo (por exemplo, alto risco). O custo é limitado pela janela de 24 horas e pela regra de parar na primeira etapa que falha.

**Consistência de tipos:** `EtapaMotor`, `SituacaoEtapa`, `EtapaMotorRegistrador::registrar`, `ReprocessarEtapasService::reprocessar`, `ReprocessoResultado(reprocessou, paradaEm, statusDoProcesso, mensagem)` e `FluxoExpressoService::reavaliar` usam os mesmos nomes e assinaturas em todas as tasks. A etapa da ficha usa as chaves `etapa`, `rotulo`, `servico`, `situacao`, `situacao_rotulo`, `motivo`, `tentativas`, `proxima_tentativa_em`, `esgotada` no PHP e no TSX.
