# Dashboard do Cidadão no "Meu Painel" — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transformar a página inicial do portal do cidadão (`/portal/painel`) num dashboard com dados reais — bloco de ação ("precisa da sua atenção"), KPIs, solicitações recentes e atalhos — respeitando escopo de representação (LGPD) e linguagem do cidadão.

**Architecture:** Um service de leitura route-free (`PainelCidadaoService`) agrega os dados do usuário efetivo/logado; o `Portal\DashboardController` injeta o service e passa as props para a página Inertia `portal/dashboard`. Um `SolicitacaoResumoResource` unifica o shape de solicitação reusado pela listagem e pelo painel. Cobertura por feature tests via rota.

**Tech Stack:** Laravel 13, Inertia v3, React 19, Tailwind 4, PHPUnit 12. Componentes do design system: `KpiCard`, `Card`, `Badge`, `EmptyState`, `PageHeader`.

Spec de referência: `docs/superpowers/specs/2026-06-29-painel-cidadao-portal-design.md`.

---

## Mapa de arquivos

- Criar: `app/Http/Resources/Portal/SolicitacaoResumoResource.php` — shape comum de solicitação (id, protocolo, status, tipo, empresa, data).
- Criar: `app/Services/Painel/PainelCidadaoService.php` — agregação dos dados do painel (indicadores, atenção, recentes).
- Criar: `tests/Feature/Portal/PainelCidadaoTest.php` — feature tests via rota.
- Modificar: `app/Http/Controllers/Portal/SolicitacaoController.php` — reusar o resource no `index`.
- Modificar: `app/Http/Controllers/Portal/DashboardController.php` — injetar service e passar props.
- Modificar: `config/sile.php` — `ui.painel.solicitacoes_recentes`.
- Modificar (reescrever): `resources/js/pages/portal/dashboard.tsx` — UI do dashboard.

---

## Task 1: Resource `SolicitacaoResumoResource` + refatoração do `index`

Refatoração coberta pelo teste existente `CriarSolicitacaoTest::test_minhas_solicitacoes_lista_somente_do_dono` (não muda o shape do `index`, só extrai o núcleo).

**Files:**
- Create: `app/Http/Resources/Portal/SolicitacaoResumoResource.php`
- Modify: `app/Http/Controllers/Portal/SolicitacaoController.php:94-113`
- Test (existente): `tests/Feature/Solicitacao/CriarSolicitacaoTest.php`

- [ ] **Step 1: Rodar o teste do `index` ANTES (baseline verde)**

Run: `php artisan test --compact --filter=test_minhas_solicitacoes_lista_somente_do_dono`
Expected: PASS (1 passed)

- [ ] **Step 2: Criar o Resource**

Run: `php artisan make:resource Portal/SolicitacaoResumoResource --no-interaction`
Expected: cria `app/Http/Resources/Portal/SolicitacaoResumoResource.php`

- [ ] **Step 3: Escrever o conteúdo do Resource**

Substituir todo o arquivo `app/Http/Resources/Portal/SolicitacaoResumoResource.php` por:

```php
<?php

namespace App\Http\Resources\Portal;

use App\Models\ViabilityRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Núcleo comum de uma solicitação para o portal do cidadão (listagem e painel):
 * protocolo, situação em linguagem pública (publicLabel) + cor, tipo de serviço,
 * empresa e data. Campos específicos de contexto (editable/cancelable) ficam no
 * call site, não aqui.
 *
 * @mixin ViabilityRequest
 */
class SolicitacaoResumoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'protocol_number' => $this->protocol_number,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'public_label' => $this->status->publicLabel(),
            ],
            'service_type' => $this->serviceType?->name,
            'company' => $this->company ? [
                'legal_name' => $this->company->legal_name,
                'formatted_cnpj' => $this->company->formatted_cnpj,
            ] : null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
```

- [ ] **Step 4: Refatorar o `through()` do `index` para reusar o Resource**

Em `app/Http/Controllers/Portal/SolicitacaoController.php`, adicionar o import (junto aos demais `use`):

```php
use App\Http\Resources\Portal\SolicitacaoResumoResource;
```

Substituir o bloco `->through(fn (ViabilityRequest $solicitacao) => [ ... ]);` (linhas ~94-113) por:

```php
            ->through(fn (ViabilityRequest $solicitacao) => [
                ...SolicitacaoResumoResource::make($solicitacao)->resolve(),
                // Só rascunho pode continuar a edição no wizard (policy update).
                'editable' => $solicitacao->status === ViabilityRequestStatus::Rascunho,
                // A ação de cancelar só aparece nos estados canceláveis (HU-070,
                // parâmetro solicitacao.cancelamento.estados_cancelaveis).
                'cancelable' => in_array($solicitacao->status->value, $cancelableStates, true),
            ]);
```

- [ ] **Step 5: Rodar o teste do `index` DEPOIS (continua verde)**

Run: `php artisan test --compact --filter=test_minhas_solicitacoes_lista_somente_do_dono`
Expected: PASS (shape inalterado)

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Resources/Portal/SolicitacaoResumoResource.php app/Http/Controllers/Portal/SolicitacaoController.php
git commit -m "refactor: extrai SolicitacaoResumoResource e reusa no index do portal"
```

---

## Task 2: `config` + `PainelCidadaoService` (indicadores) + controller

Primeira fatia: indicadores reais. O service nasce com `atencao` e `solicitacoesRecentes` vazios (preenchidos nas Tasks 3 e 4).

**Files:**
- Modify: `config/sile.php:19-26`
- Create: `app/Services/Painel/PainelCidadaoService.php`
- Modify: `app/Http/Controllers/Portal/DashboardController.php`
- Create: `tests/Feature/Portal/PainelCidadaoTest.php`

- [ ] **Step 1: Adicionar a constante de itens recentes no `config/sile.php`**

No bloco `'ui' => [ ... ]` (linhas 19-26), acrescentar a linha `painel`:

```php
    'ui' => [
        'access_history' => ['per_page' => 15],
        'cnaes' => ['per_page' => 15],
        'users' => ['per_page' => 15],
        'companies' => ['per_page' => 15],
        'email_logs' => ['per_page' => 20],
        'auditoria' => ['per_page' => 20],
        // Quantidade de solicitações recentes no painel do cidadão. Constante
        // técnica/de UI (precedente [02-02]): NÃO entra no catálogo HU-014.
        'painel' => ['solicitacoes_recentes' => 5],
    ],
```

- [ ] **Step 2: Escrever o feature test dos indicadores**

Run: `php artisan make:test Portal/PainelCidadaoTest --phpunit --no-interaction`

Substituir todo o arquivo `tests/Feature/Portal/PainelCidadaoTest.php` por:

```php
<?php

namespace Tests\Feature\Portal;

use App\Enums\CompanyLinkRole;
use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityQuery;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Painel do cidadão (Meu Painel) — agregador das HUs do portal. Prova dado real
 * + escopo do usuário efetivo + linguagem pública, sem fachada.
 */
class PainelCidadaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** Cidadão habilitado a navegar no portal (papel + termo LGPD aceito). */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    /** Empresa com vínculo ATIVO do usuário. */
    private function companyLinkedTo(User $user): Company
    {
        $company = Company::factory()->create();

        $company->links()->create([
            'user_id' => $user->id,
            'role' => CompanyLinkRole::Responsavel,
            'started_at' => now(),
        ]);

        return $company;
    }

    public function test_painel_exibe_indicadores_reais(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        // Em andamento (contam): protocolada + em análise = 2.
        ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
        ]);
        ViabilityRequest::factory()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
            'status' => ViabilityRequestStatus::EmAnalise,
        ]);
        // Não contam em "em andamento": rascunho e deferida.
        ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
        ]);
        ViabilityRequest::factory()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
            'status' => ViabilityRequestStatus::Deferida,
        ]);

        ViabilityQuery::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/dashboard')
                ->where('indicadores.em_andamento', 2)
                ->where('indicadores.empresas', 1)
                ->where('indicadores.consultas', 2)
                ->where('emRepresentacao', false));
    }
}
```

- [ ] **Step 3: Rodar o teste (verificar RED)**

Run: `php artisan test --compact --filter=test_painel_exibe_indicadores_reais`
Expected: FAIL — a prop `indicadores` não existe (o controller não passa nada).

- [ ] **Step 4: Criar o service**

Run: `php artisan make:class Services/Painel/PainelCidadaoService --no-interaction`

Substituir todo o arquivo `app/Services/Painel/PainelCidadaoService.php` por:

```php
<?php

namespace App\Services\Painel;

use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityQuery;
use App\Models\ViabilityRequest;

/**
 * Leitura agregada do painel do cidadão (Meu Painel). Route-free e sem estado:
 * recebe o usuário EFETIVO (representado quando "em nome de") e o usuário LOGADO,
 * e devolve um array pronto para a página Inertia.
 *
 * Escopo (LGPD): solicitações/empresas usam o efetivo; consultas usam o logado e
 * são omitidas (null) em representação, para não somar titulares distintos.
 */
class PainelCidadaoService
{
    /**
     * Estados não terminais que contam como "em andamento" (rascunho fica de
     * fora — aparece no bloco de atenção, não é processo em curso).
     *
     * @var list<ViabilityRequestStatus>
     */
    private const EM_ANDAMENTO = [
        ViabilityRequestStatus::Protocolada,
        ViabilityRequestStatus::EmAnalise,
        ViabilityRequestStatus::EmPendencia,
        ViabilityRequestStatus::AguardandoBap,
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(User $efetivo, User $logado): array
    {
        $emRepresentacao = $efetivo->id !== $logado->id;

        return [
            'indicadores' => $this->indicadores($efetivo, $logado, $emRepresentacao),
            'atencao' => ['pendencias' => [], 'rascunhos' => []],
            'solicitacoesRecentes' => [],
            'emRepresentacao' => $emRepresentacao,
        ];
    }

    /**
     * @return array{em_andamento: int, empresas: int, consultas: int|null}
     */
    private function indicadores(User $efetivo, User $logado, bool $emRepresentacao): array
    {
        $porStatus = ViabilityRequest::query()
            ->where('requester_user_id', $efetivo->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $emAndamento = collect(self::EM_ANDAMENTO)
            ->sum(fn (ViabilityRequestStatus $status): int => (int) ($porStatus[$status->value] ?? 0));

        return [
            'em_andamento' => (int) $emAndamento,
            'empresas' => Company::countForUser($efetivo),
            'consultas' => $emRepresentacao ? null : ViabilityQuery::forUser($logado)->count(),
        ];
    }
}
```

- [ ] **Step 5: Reescrever o controller para injetar o service**

Substituir todo o arquivo `app/Http/Controllers/Portal/DashboardController.php` por:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Painel\PainelCidadaoService;
use App\Support\Representation\CurrentRepresentation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meu Painel (portal do cidadão): agrega ação necessária, indicadores e
 * solicitações recentes do usuário. O usuário efetivo (representado quando "em
 * nome de") escopa solicitações/empresas; o logado escopa consultas/notificações.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, PainelCidadaoService $painel): Response
    {
        $logado = $request->user();
        $efetivo = app(CurrentRepresentation::class)->grantor() ?? $logado;

        return Inertia::render('portal/dashboard', $painel->build($efetivo, $logado));
    }
}
```

- [ ] **Step 6: Rodar o teste (verificar GREEN)**

Run: `php artisan test --compact --filter=test_painel_exibe_indicadores_reais`
Expected: PASS

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/sile.php app/Services/Painel/PainelCidadaoService.php app/Http/Controllers/Portal/DashboardController.php tests/Feature/Portal/PainelCidadaoTest.php
git commit -m "feat: adiciona indicadores reais ao painel do cidadão"
```

---

## Task 3: Solicitações recentes

**Files:**
- Modify: `app/Services/Painel/PainelCidadaoService.php`
- Modify: `tests/Feature/Portal/PainelCidadaoTest.php`

- [ ] **Step 1: Escrever o teste das solicitações recentes**

Acrescentar ao final da classe `PainelCidadaoTest` (antes da chave de fechamento `}`):

```php
    public function test_painel_lista_solicitacoes_recentes_limitadas_e_publicas(): void
    {
        $user = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        // 6 solicitações com datas decrescentes (a mais recente primeiro).
        foreach (range(0, 5) as $i) {
            ViabilityRequest::factory()->protocoled()->create([
                'requester_user_id' => $user->id,
                'company_id' => $company->id,
                'protocol_number' => sprintf('VIA-2026-%06d', $i),
                'created_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Limite default (config sile.ui.painel.solicitacoes_recentes = 5).
                ->has('solicitacoesRecentes', 5)
                // Mais recente primeiro (i = 0).
                ->where('solicitacoesRecentes.0.protocol_number', 'VIA-2026-000000')
                // Linguagem pública do status (publicLabel), nunca o label técnico.
                ->where('solicitacoesRecentes.0.status.public_label', 'Recebida — em processamento'));
    }
```

- [ ] **Step 2: Rodar o teste (verificar RED)**

Run: `php artisan test --compact --filter=test_painel_lista_solicitacoes_recentes_limitadas_e_publicas`
Expected: FAIL — `solicitacoesRecentes` vem vazio (`has(...,5)` falha).

- [ ] **Step 3: Implementar `solicitacoesRecentes` no service**

Em `app/Services/Painel/PainelCidadaoService.php`, adicionar o import (junto aos demais `use`):

```php
use App\Http\Resources\Portal\SolicitacaoResumoResource;
```

No método `build`, trocar a linha `'solicitacoesRecentes' => [],` por:

```php
            'solicitacoesRecentes' => $this->solicitacoesRecentes($efetivo),
```

E adicionar o método (após `indicadores`):

```php
    /**
     * Últimas N solicitações do efetivo (N = config técnica, fora do catálogo
     * HU-014), no shape comum do SolicitacaoResumoResource.
     *
     * @return list<array<string, mixed>>
     */
    private function solicitacoesRecentes(User $efetivo): array
    {
        $limit = (int) config('sile.ui.painel.solicitacoes_recentes', 5);

        return SolicitacaoResumoResource::collection(
            ViabilityRequest::query()
                ->where('requester_user_id', $efetivo->id)
                ->with(['company', 'serviceType'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
        )->resolve();
    }
```

- [ ] **Step 4: Rodar o teste (verificar GREEN)**

Run: `php artisan test --compact --filter=test_painel_lista_solicitacoes_recentes_limitadas_e_publicas`
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Painel/PainelCidadaoService.php tests/Feature/Portal/PainelCidadaoTest.php
git commit -m "feat: lista solicitações recentes no painel do cidadão"
```

---

## Task 4: Bloco "Precisa da sua atenção" (pendências + rascunhos)

**Files:**
- Modify: `app/Services/Painel/PainelCidadaoService.php`
- Modify: `tests/Feature/Portal/PainelCidadaoTest.php`

- [ ] **Step 1: Escrever o teste do bloco de atenção**

Acrescentar ao topo dos `use` do arquivo de teste:

```php
use App\Models\AnalysisPendency;
```

Acrescentar ao final da classe `PainelCidadaoTest`:

```php
    public function test_painel_atencao_traz_pendencias_abertas_e_rascunhos_do_efetivo(): void
    {
        $user = $this->portalUser();
        $other = $this->portalUser();
        $company = $this->companyLinkedTo($user);

        // Pendência ABERTA do usuário (aparece).
        $req = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
            'protocol_number' => 'VIA-2026-000100',
        ]);
        AnalysisPendency::factory()->create(['viability_request_id' => $req->id]);

        // Pendência RESPONDIDA do usuário (NÃO aparece).
        AnalysisPendency::factory()->respondida()->create(['viability_request_id' => $req->id]);

        // Pendência aberta de OUTRO usuário (NÃO aparece — escopo).
        $otherReq = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $other->id,
            'company_id' => $this->companyLinkedTo($other)->id,
        ]);
        AnalysisPendency::factory()->create(['viability_request_id' => $otherReq->id]);

        // Rascunho do usuário (aparece).
        ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('atencao.pendencias', 1)
                ->where('atencao.pendencias.0.solicitacao_id', $req->id)
                ->where('atencao.pendencias.0.protocol_number', 'VIA-2026-000100')
                ->has('atencao.rascunhos', 1));
    }
```

- [ ] **Step 2: Rodar o teste (verificar RED)**

Run: `php artisan test --compact --filter=test_painel_atencao_traz_pendencias_abertas_e_rascunhos_do_efetivo`
Expected: FAIL — `atencao.pendencias`/`atencao.rascunhos` vêm vazios.

- [ ] **Step 3: Implementar `atencao` no service**

Em `app/Services/Painel/PainelCidadaoService.php`, adicionar os imports (junto aos demais `use`):

```php
use App\Enums\AnalysisPendencyStatus;
use App\Models\AnalysisPendency;
```

No método `build`, trocar a linha `'atencao' => ['pendencias' => [], 'rascunhos' => []],` por:

```php
            'atencao' => $this->atencao($efetivo),
```

E adicionar o método (após `solicitacoesRecentes`):

```php
    /**
     * Itens que dependem de ação do efetivo: pendências ABERTAS (HU-090/091) e
     * rascunhos a protocolar (HU-061/068).
     *
     * @return array{pendencias: list<array<string, mixed>>, rascunhos: list<array<string, mixed>>}
     */
    private function atencao(User $efetivo): array
    {
        $pendencias = AnalysisPendency::query()
            ->where('status', AnalysisPendencyStatus::Aberta)
            ->whereHas('viabilityRequest', fn ($query) => $query->where('requester_user_id', $efetivo->id))
            ->with('viabilityRequest:id,protocol_number')
            ->latest()
            ->get()
            ->map(fn (AnalysisPendency $pendencia): array => [
                'id' => $pendencia->id,
                'solicitacao_id' => $pendencia->viability_request_id,
                'protocol_number' => $pendencia->viabilityRequest?->protocol_number,
                'descricao' => $pendencia->description,
                'due_at' => $pendencia->due_at?->toDateTimeString(),
            ])
            ->all();

        $rascunhos = ViabilityRequest::query()
            ->where('requester_user_id', $efetivo->id)
            ->where('status', ViabilityRequestStatus::Rascunho)
            ->with(['company:id,legal_name', 'serviceType:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ViabilityRequest $rascunho): array => [
                'id' => $rascunho->id,
                'service_type' => $rascunho->serviceType?->name,
                'company_legal_name' => $rascunho->company?->legal_name,
                'created_at' => $rascunho->created_at?->toDateTimeString(),
            ])
            ->all();

        return ['pendencias' => $pendencias, 'rascunhos' => $rascunhos];
    }
```

- [ ] **Step 4: Rodar o teste (verificar GREEN)**

Run: `php artisan test --compact --filter=test_painel_atencao_traz_pendencias_abertas_e_rascunhos_do_efetivo`
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Painel/PainelCidadaoService.php tests/Feature/Portal/PainelCidadaoTest.php
git commit -m "feat: adiciona bloco de pendências e rascunhos ao painel do cidadão"
```

---

## Task 5: Casos de borda — vazio e representação não vaza

**Files:**
- Modify: `tests/Feature/Portal/PainelCidadaoTest.php`

- [ ] **Step 1: Escrever os testes de vazio e de representação**

Acrescentar aos `use` do arquivo de teste:

```php
use App\Models\Procuration;
```

Acrescentar ao final da classe `PainelCidadaoTest`:

```php
    public function test_painel_vazio_zera_indicadores_e_listas(): void
    {
        $user = $this->portalUser();

        $this->actingAs($user)
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('indicadores.em_andamento', 0)
                ->where('indicadores.empresas', 0)
                ->where('indicadores.consultas', 0)
                ->has('atencao.pendencias', 0)
                ->has('atencao.rascunhos', 0)
                ->has('solicitacoesRecentes', 0)
                ->where('emRepresentacao', false));
    }

    public function test_painel_em_representacao_mostra_do_representado_sem_vazar(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $proc = Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        // Dados do REPRESENTADO (devem aparecer).
        $grantorCompany = $this->companyLinkedTo($grantor);
        $grantorReq = ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $grantor->id,
            'company_id' => $grantorCompany->id,
            'protocol_number' => 'VIA-2026-000200',
        ]);

        // Dados do PROCURADOR (NÃO devem aparecer no painel do representado).
        $attorneyCompany = $this->companyLinkedTo($attorney);
        ViabilityRequest::factory()->protocoled()->create([
            'requester_user_id' => $attorney->id,
            'company_id' => $attorneyCompany->id,
            'protocol_number' => 'VIA-2026-000999',
        ]);
        ViabilityQuery::factory()->count(3)->create(['user_id' => $attorney->id]);

        $this->actingAs($attorney)
            ->withSession(['acting_procuration_id' => $proc->id])
            ->get('/portal/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('emRepresentacao', true)
                // Solicitações/empresas do representado.
                ->where('indicadores.em_andamento', 1)
                ->where('indicadores.empresas', 1)
                ->has('solicitacoesRecentes', 1)
                ->where('solicitacoesRecentes.0.id', $grantorReq->id)
                // Consultas (pessoais do procurador) omitidas em representação.
                ->where('indicadores.consultas', null));
    }
```

- [ ] **Step 2: Rodar os testes (verificar comportamento)**

Run: `php artisan test --compact --filter=PainelCidadaoTest`
Expected: PASS em todos. Se `test_painel_em_representacao_*` falhar, o escopo do controller/service está incorreto — revisar o uso de `CurrentRepresentation::grantor()` no controller e o `requester_user_id => $efetivo->id` no service (não deve usar o logado para solicitações/empresas).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Portal/PainelCidadaoTest.php
git commit -m "test: cobre painel vazio e escopo de representação do cidadão"
```

---

## Task 6: Frontend — `portal/dashboard.tsx`

Reescrita da página consumindo as props. Sem teste de componente automatizado (consistente com o repo); verificação por `typecheck` e `build`.

**Files:**
- Modify (reescrever): `resources/js/pages/portal/dashboard.tsx`

- [ ] **Step 1: Reescrever a página**

Substituir todo o arquivo `resources/js/pages/portal/dashboard.tsx` por:

```tsx
import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/components/app/page-header';
import {
    AlertIcon,
    ArrowRightIcon,
    BellIcon,
    CheckCircleIcon,
    FileIcon,
    GroupIcon,
    ListIcon,
    PencilIcon,
    SearchIcon,
} from '@/components/icons';
import Badge from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import EmptyState from '@/components/ui/empty-state';
import KpiCard, { type KpiTone } from '@/components/ui/kpi-card';
import PortalLayout from '@/layouts/portal-layout';
import type { SharedProps } from '@/types';

type BadgeColor = 'primary' | 'success' | 'error' | 'warning' | 'info' | 'light';

interface StatusInfo {
    value: string;
    label: string;
    public_label: string;
}

interface SolicitacaoResumo {
    id: number;
    protocol_number: string | null;
    status: StatusInfo;
    service_type: string | null;
    company: { legal_name: string; formatted_cnpj: string } | null;
    created_at: string | null;
}

interface PendenciaResumo {
    id: number;
    solicitacao_id: number;
    protocol_number: string | null;
    descricao: string;
    due_at: string | null;
}

interface RascunhoResumo {
    id: number;
    service_type: string | null;
    company_legal_name: string | null;
    created_at: string | null;
}

interface DashboardProps {
    indicadores: {
        em_andamento: number;
        empresas: number;
        consultas: number | null;
    };
    atencao: {
        pendencias: PendenciaResumo[];
        rascunhos: RascunhoResumo[];
    };
    solicitacoesRecentes: SolicitacaoResumo[];
    emRepresentacao: boolean;
}

interface Indicator {
    key: string;
    label: string;
    value: string;
    note: string;
    icon: ReactNode;
    tone: KpiTone;
}

interface QuickAction {
    name: string;
    label: string;
    href: string;
    icon: ReactNode;
}

const numberFormat = new Intl.NumberFormat('pt-BR');

/** Cor do selo conforme o estado do processo (reforço visual, nunca único canal). */
function statusColor(value: string): BadgeColor {
    switch (value) {
        case 'protocolada':
            return 'info';
        case 'deferida':
            return 'success';
        case 'indeferida':
        case 'cancelada':
            return 'error';
        case 'em_pendencia':
        case 'aguardando_bap':
            return 'warning';
        default:
            return 'light';
    }
}

/** 'YYYY-MM-DD HH:MM:SS' como 'DD/MM/YYYY' sem depender do fuso do navegador. */
function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;

    return `${day}/${month}/${year}`;
}

const quickActions: QuickAction[] = [
    {
        name: 'Consulta de viabilidade',
        label: 'Verifique se a atividade é permitida',
        href: '/portal/viabilidade',
        icon: <SearchIcon className="size-6 text-gray-800 dark:text-white/90" />,
    },
    {
        name: 'Minhas empresas',
        label: 'Empresas vinculadas a você',
        href: '/portal/empresas',
        icon: <ListIcon className="size-6 text-gray-800 dark:text-white/90" />,
    },
    {
        name: 'Procurações',
        label: 'Vínculos de representação',
        href: '/portal/procuracoes',
        icon: <FileIcon className="size-6 text-gray-800 dark:text-white/90" />,
    },
    {
        name: 'Meus acessos',
        label: 'Histórico da sua conta',
        href: '/portal/acessos',
        icon: <GroupIcon className="size-6 text-gray-800 dark:text-white/90" />,
    },
];

export default function Dashboard({ indicadores, atencao, solicitacoesRecentes, emRepresentacao }: DashboardProps) {
    const { auth, notificacoes } = usePage<SharedProps>().props;

    const temAtencao = atencao.pendencias.length > 0 || atencao.rascunhos.length > 0;

    const possibleIndicators: (Indicator | null)[] = [
        {
            key: 'andamento',
            label: 'Em andamento',
            value: numberFormat.format(indicadores.em_andamento),
            note: 'solicitações em processamento',
            icon: <FileIcon className="size-6" />,
            tone: 'brand',
        },
        {
            key: 'empresas',
            label: 'Empresas vinculadas',
            value: numberFormat.format(indicadores.empresas),
            note: 'com vínculo ativo',
            icon: <ListIcon className="size-6" />,
            tone: 'info',
        },
        indicadores.consultas !== null
            ? {
                  key: 'consultas',
                  label: 'Consultas feitas',
                  value: numberFormat.format(indicadores.consultas),
                  note: 'consultas de viabilidade',
                  icon: <SearchIcon className="size-6" />,
                  tone: 'success',
              }
            : null,
        !emRepresentacao
            ? {
                  key: 'notificacoes',
                  label: 'Notificações não lidas',
                  value: numberFormat.format(notificacoes.nao_lidas),
                  note: 'avisos do seu processo',
                  icon: <BellIcon className="size-6" />,
                  tone: 'warning',
              }
            : null,
    ];

    const indicators = possibleIndicators.filter((indicator): indicator is Indicator => indicator !== null);

    return (
        <>
            <Head title="Meu painel" />
            <PageHeader title="Meu painel" />

            <div className="grid grid-cols-12 gap-4 md:gap-6">
                <div className="col-span-12">
                    {temAtencao ? (
                        <div className="rounded-2xl border border-warning-300 bg-warning-50 p-5 dark:border-warning-500/30 dark:bg-warning-500/10 md:p-6">
                            <div className="flex items-start gap-3">
                                <span className="text-warning-500">
                                    <AlertIcon className="size-6 fill-current" />
                                </span>
                                <div className="w-full">
                                    <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">
                                        Precisa da sua atenção
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        Estes itens dependem de você para o processo seguir.
                                    </p>

                                    <ul className="mt-4 flex flex-col gap-3">
                                        {atencao.pendencias.map((pendencia) => (
                                            <li
                                                key={`pendencia-${pendencia.id}`}
                                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning-200 bg-white px-4 py-3 dark:border-warning-500/20 dark:bg-white/[0.03]"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                        Pendência — {pendencia.protocol_number ?? 'solicitação'}
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {pendencia.descricao}
                                                        {pendencia.due_at ? ` · responda até ${formatDate(pendencia.due_at)}` : ''}
                                                    </p>
                                                </div>
                                                <Link
                                                    href={`/portal/solicitacoes/${pendencia.solicitacao_id}/pendencias`}
                                                    className="inline-flex items-center gap-1.5 rounded-lg bg-warning-500 px-3 py-2 text-theme-sm font-medium text-white transition hover:bg-warning-600"
                                                >
                                                    Responder
                                                    <ArrowRightIcon className="size-4" />
                                                </Link>
                                            </li>
                                        ))}

                                        {atencao.rascunhos.map((rascunho) => (
                                            <li
                                                key={`rascunho-${rascunho.id}`}
                                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                                        Rascunho não protocolado
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {rascunho.company_legal_name ?? rascunho.service_type ?? 'Solicitação em preenchimento'}
                                                    </p>
                                                </div>
                                                <Link
                                                    href={`/portal/solicitacoes/${rascunho.id}/editar`}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                                                >
                                                    <PencilIcon className="size-4" />
                                                    Retomar e protocolar
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center gap-3 rounded-2xl border border-success-200 bg-success-50 p-5 dark:border-success-500/30 dark:bg-success-500/10 md:p-6">
                            <span className="text-success-600 dark:text-success-500">
                                <CheckCircleIcon className="size-6" />
                            </span>
                            <div>
                                <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">Tudo em dia</h3>
                                <p className="mt-0.5 text-sm text-gray-600 dark:text-gray-300">
                                    Nada precisa de você agora. Acompanhe abaixo o andamento dos seus pedidos.
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                <div className="col-span-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                        {indicators.map((indicator) => (
                            <KpiCard
                                key={indicator.key}
                                label={indicator.label}
                                value={indicator.value}
                                note={indicator.note}
                                icon={indicator.icon}
                                tone={indicator.tone}
                            />
                        ))}
                    </div>
                </div>

                <div className="col-span-12">
                    <Card>
                        <CardHeader
                            title="Minhas solicitações recentes"
                            description="Acompanhe o andamento dos seus pedidos de viabilidade."
                            actions={
                                <Link
                                    href="/portal/solicitacoes"
                                    className="inline-flex items-center gap-1.5 text-theme-sm font-medium text-brand-500 transition hover:text-brand-600 dark:text-brand-400"
                                >
                                    Ver todas
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            }
                        />
                        <CardContent flush>
                            {solicitacoesRecentes.length === 0 ? (
                                <EmptyState
                                    icon={<FileIcon className="size-6" />}
                                    title="Você ainda não tem solicitações"
                                    description="Comece verificando se a atividade é permitida no endereço desejado."
                                    action={
                                        <Link
                                            href="/portal/viabilidade"
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white transition hover:bg-brand-600"
                                        >
                                            Fazer consulta de viabilidade
                                            <ArrowRightIcon className="size-4" />
                                        </Link>
                                    }
                                />
                            ) : (
                                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {solicitacoesRecentes.map((solicitacao) => (
                                        <li key={solicitacao.id}>
                                            <Link
                                                href={`/portal/solicitacoes/${solicitacao.id}`}
                                                className="flex flex-wrap items-center justify-between gap-3 px-4 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.02] sm:px-6"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-medium text-gray-800 dark:text-white/90">
                                                        {solicitacao.protocol_number ?? 'Em preenchimento'}
                                                    </p>
                                                    <p className="truncate text-theme-xs text-gray-500 dark:text-gray-400">
                                                        {solicitacao.company?.legal_name ?? solicitacao.service_type ?? '—'}
                                                        {solicitacao.created_at ? ` · ${formatDate(solicitacao.created_at)}` : ''}
                                                    </p>
                                                </div>
                                                <Badge size="sm" color={statusColor(solicitacao.status.value)}>
                                                    {solicitacao.status.public_label}
                                                </Badge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="col-span-12">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
                        {quickActions.map((action) => (
                            <Link
                                key={action.href}
                                href={action.href}
                                className="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6"
                            >
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800">
                                    {action.icon}
                                </div>
                                <div className="mt-5 flex items-end justify-between">
                                    <div>
                                        <span className="text-sm text-gray-500 dark:text-gray-400">{action.label}</span>
                                        <h4 className="mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90">
                                            {action.name}
                                        </h4>
                                    </div>
                                    <ArrowRightIcon className="mb-1.5 size-5 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500 dark:text-gray-500 dark:group-hover:text-brand-400" />
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                <div className="col-span-12">
                    <Card>
                        <CardHeader title="Minha conta" description="Bem-vindo(a) ao Simplifica." />
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">Nome</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.name ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-theme-xs text-gray-500 dark:text-gray-400">E-mail</dt>
                                    <dd className="mt-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {auth.user?.email ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <PortalLayout>{page}</PortalLayout>;
```

- [ ] **Step 2: Typecheck**

Run: `npm run typecheck`
Expected: sem erros em `resources/js/pages/portal/dashboard.tsx`.

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: build conclui sem erro de Vite/manifest.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/portal/dashboard.tsx
git commit -m "feat: reescreve Meu Painel do cidadão com dados reais"
```

---

## Task 7: Verificação final

**Files:** nenhum (verificação).

- [ ] **Step 1: Suíte de testes do portal e da solicitação (regressão)**

Run: `php artisan test --compact --filter=PainelCidadaoTest`
Expected: PASS (4 testes).

Run: `php artisan test --compact tests/Feature/Solicitacao/CriarSolicitacaoTest.php tests/Feature/Routing/EnvironmentAccessTest.php`
Expected: PASS (o refactor do resource e o novo controller não quebram a listagem nem o roteamento que faz `GET /portal/painel`).

- [ ] **Step 2: Suíte completa (confirmar nada quebrado)**

Run: `php artisan test --compact`
Expected: PASS na suíte inteira.

- [ ] **Step 3: Lint final**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sem alterações pendentes (ou aplica e segue).

---

## Self-Review

**Cobertura da spec:**
- §4.1 atenção (pendências + rascunhos) → Task 4 + front Task 6. Estado calmo → Task 6.
- §4.2 KPIs (em andamento, empresas, consultas, notificações) → Task 2 (backend) + Task 6 (front; notificações via shared prop).
- §4.3 solicitações recentes + EmptyState → Task 3 + Task 6.
- §4.4 ações rápidas / §4.5 minha conta → Task 6.
- §5 representação (efetivo vs logado, omitir contadores pessoais) → Task 2 (consultas null) + Task 5 (teste) + Task 6 (omite notificações).
- §6 service + resource + controller → Tasks 1, 2, 3, 4.
- §7 parametrização (config, sem catálogo) → Task 2 Step 1.
- §8 linguagem/acessibilidade (publicLabel, texto+cor) → Resource (Task 1) + front (Task 6).
- §11 testes (happy, vazio, zerados, representação, pendências, refactor index) → Tasks 1–5.

**Placeholders:** nenhum — todo passo tem código/comando completo.

**Consistência de tipos:** `build(User $efetivo, User $logado)` usado igual no controller e service; props `indicadores`/`atencao`/`solicitacoesRecentes`/`emRepresentacao` batem entre service (PHP) e `DashboardProps` (TS); `SolicitacaoResumoResource` shape bate com `SolicitacaoResumo` (TS) e com o `index` (que adiciona `editable`/`cancelable`).

## Refinamentos durante a execução (review)

Ajustes que surgiram nas revisões de cada task e foram incorporados ao código final:

1. **Task 1:** docblock do `SolicitacaoResumoResource` corrigido (não há campo de cor; a cor é derivada no front a partir de `status.value`).
2. **Task 4:** teste reforçado com um rascunho de terceiro, para isolar o corte de escopo dos rascunhos.
3. **Task 6 (tipagem):** o array de indicadores foi extraído para uma variável intermediária `possibleIndicators: (Indicator | null)[]` antes do `.filter()` — o tipo contextual `Indicator[]` não se propaga através do `.filter()` para um array literal, então a anotação na variável é necessária para o type guard compilar (o bloco de código acima já reflete a versão corrigida).
4. **Task 6 (design §4.2):** o KPI "Notificações não lidas" virou atalho para `/portal/notificacoes` (campo `href?` em `Indicator` + wrapper `Link` no map, sem alterar o `KpiCard` compartilhado).
5. **Task 6 (acessibilidade §8):** `aria-hidden="true"` aplicado aos 16 ícones decorativos da página (todos acompanhados de texto).

Follow-ups registrados (não bloqueantes): `aria-hidden` como padrão no componente base de ícone (débito transversal do projeto); extrair `statusColor`/`formatDate` para um módulo compartilhado; unificar a resolução do usuário efetivo (hoje repetida entre controllers do portal).
