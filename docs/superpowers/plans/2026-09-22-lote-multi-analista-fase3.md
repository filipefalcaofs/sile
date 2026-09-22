# Lote multi-analista + histórico (Fase 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que o Apoio distribua os processos selecionados entre VÁRIOS analistas, definindo manualmente a quantidade por analista, com prévia de quais processos vão para cada um antes de confirmar; e exibir um histórico simples das distribuições. Sem automação — analistas e quantidades são escolha do Apoio.

**Architecture:** A Central de Distribuição ganha um modo "vários analistas": o Apoio informa uma quantidade por analista; o frontend aloca os processos selecionados por ordem de prazo (a mesma ordem em que o backend os entrega) e mostra a prévia; ao confirmar, envia o mapa explícito `alocacoes: [{analista_id, request_ids[]}]` para um novo `POST /gestao/caixa-setor/distribuir-lote`. O controller revalida tudo (escopo do ator, EmAnalise, sem responsável, sem sobreposição, união = selecionados, analistas do setor) e delega ao `DistribuicaoService::distribuirMultiplo`. Um evento de auditoria de resumo (`distribuir-lote`) por analista alimenta o histórico.

**Tech Stack:** Laravel 13, Inertia v3 + React 19, Tailwind 4, PHPUnit 12.

## Global Constraints

- Idioma: UI, mensagens, nomes de teste (`test_...`) e PHPDoc em pt-BR; código em inglês.
- TDD estrito: teste falhando antes do código de produção. Feature tests inspecionam props/efeitos (padrão do projeto).
- Decisão 100% do Apoio: nenhuma alocação automática pelo sistema. O frontend só distribui por ordem de prazo o que o Apoio pediu em quantidade; o Apoio pode ajustar.
- Segurança (mesma régua da Fase 2): o `POST distribuir-lote` revalida no backend — todos os `request_ids` devem pertencer aos setores do ATOR, estar `EmAnalise` e sem responsável; sem sobreposição de ids entre analistas; união dos ids = conjunto pedido; cada analista destino vinculado ao setor. Lote inválido é rejeitado INTEIRO (atômico), nada é gravado.
- Um único setor por operação (a Central já bloqueia seleção multi-setor na Fase 2).
- Auditoria: mantém o log por processo do `DistribuicaoService` (evento `distribuir`) e adiciona um resumo por analista (evento `distribuir-lote`: qtd, analista, setor, ator) para o histórico. Sem falha silenciosa.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- `git add` por-arquivo (NÃO `git add -A`): há sessão concorrente do usuário na branch. NÃO commitar `public/build`.
- Nenhum item novo de menu; `npx vitest run resources/js/navigation/gestao-nav.test.ts` continua verde.

---

### Task 1: `DistribuicaoService::distribuirMultiplo`

Distribui grupos {analista, processos} reusando o caminho de atribuição existente, isolando falhas e auditando por processo.

**Files:**
- Modify: `app/Services/Analise/DistribuicaoService.php`
- Test: `tests/Feature/Analise/DistribuicaoServiceTest.php`

**Interfaces:**
- Produces:
  ```php
  DistribuicaoService::distribuirMultiplo(array $grupos, User $ator): array
  // $grupos: list<array{analista: User, requests: iterable<ViabilityRequest>}>
  // retorna: array{ok: int, falhas: list<array{viability_request_id: int, motivo: string}>}
  ```
  Consumido pela Task 2.

- [ ] **Step 1: Escrever o teste (Red)**

Adicionar a `tests/Feature/Analise/DistribuicaoServiceTest.php` (usar os helpers já existentes no arquivo; se não houver, criar setor/analista/processo com factories como nos outros testes):

```php
public function test_distribui_multiplo_atribui_cada_grupo_ao_seu_analista(): void
{
    $setor = \App\Models\Sector::factory()->create();
    $analistaA = \App\Models\User::factory()->analista()->withAcceptedLgpdTerm()->create();
    $analistaA->sectors()->attach($setor);
    $analistaB = \App\Models\User::factory()->analista()->withAcceptedLgpdTerm()->create();
    $analistaB->sectors()->attach($setor);
    $ator = \App\Models\User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    $ator->sectors()->attach($setor);

    $p1 = $this->processoNaCaixa($setor);
    $p2 = $this->processoNaCaixa($setor);
    $p3 = $this->processoNaCaixa($setor);

    $service = app(\App\Services\Analise\DistribuicaoService::class);
    $resumo = $service->distribuirMultiplo([
        ['analista' => $analistaA, 'requests' => collect([$p1, $p2])],
        ['analista' => $analistaB, 'requests' => collect([$p3])],
    ], $ator);

    $this->assertSame(3, $resumo['ok']);
    $this->assertSame([], $resumo['falhas']);
    $this->assertSame($analistaA->id, $p1->fresh()->assigned_user_id);
    $this->assertSame($analistaA->id, $p2->fresh()->assigned_user_id);
    $this->assertSame($analistaB->id, $p3->fresh()->assigned_user_id);
}

public function test_distribui_multiplo_isola_falha_de_grupo(): void
{
    $setor = \App\Models\Sector::factory()->create();
    $outroSetor = \App\Models\Sector::factory()->create();
    $analista = \App\Models\User::factory()->analista()->withAcceptedLgpdTerm()->create();
    $analista->sectors()->attach($setor);
    $foraDoSetor = \App\Models\User::factory()->analista()->withAcceptedLgpdTerm()->create();
    $foraDoSetor->sectors()->attach($outroSetor);
    $ator = \App\Models\User::factory()->gestor()->withAcceptedLgpdTerm()->create();

    $ok = $this->processoNaCaixa($setor);
    $falho = $this->processoNaCaixa($setor);

    $service = app(\App\Services\Analise\DistribuicaoService::class);
    $resumo = $service->distribuirMultiplo([
        ['analista' => $analista, 'requests' => collect([$ok])],
        ['analista' => $foraDoSetor, 'requests' => collect([$falho])], // analista de outro setor
    ], $ator);

    $this->assertSame(1, $resumo['ok']);
    $this->assertCount(1, $resumo['falhas']);
    $this->assertSame($analista->id, $ok->fresh()->assigned_user_id);
    $this->assertNull($falho->fresh()->assigned_user_id);
}
```

Se o arquivo não tiver o helper `processoNaCaixa`, copie o mesmo helper usado em `CaixaSetorTest` (cria `ViabilityRequest` com `sector_id`, `status=EmAnalise`, `analysis_stage=Distribuicao`, prazos) adaptado ao arquivo.

- [ ] **Step 2: Rodar e ver falhar (Red)**

Run: `php artisan test --compact --filter='distribui_multiplo' tests/Feature/Analise/DistribuicaoServiceTest.php`
Expected: FAIL (método `distribuirMultiplo` não existe).

- [ ] **Step 3: Implementar o método**

Adicionar ao `DistribuicaoService` (reusa `distribuirLote`, que já isola falhas e audita por processo):

```php
/**
 * Distribuição em lote entre VÁRIOS analistas (Fase 3): cada grupo leva seus
 * processos ao seu analista, reusando o caminho de atribuição (SLA + auditoria
 * por processo) e isolando falhas por item. A decisão de quem recebe o quê é
 * do Apoio — este serviço apenas executa a alocação recebida.
 *
 * @param  list<array{analista: User, requests: iterable<int, ViabilityRequest>}>  $grupos
 * @return array{ok: int, falhas: list<array{viability_request_id: int, motivo: string}>}
 */
public function distribuirMultiplo(array $grupos, User $ator): array
{
    $ok = 0;
    $falhas = [];

    foreach ($grupos as $grupo) {
        $resumo = $this->distribuirLote($grupo['requests'], $grupo['analista'], $ator);
        $ok += $resumo['ok'];
        $falhas = array_merge($falhas, $resumo['falhas']);
    }

    return ['ok' => $ok, 'falhas' => $falhas];
}
```

- [ ] **Step 4: Rodar e ver passar (Green)**

Run: `php artisan test --compact --filter='distribui_multiplo' tests/Feature/Analise/DistribuicaoServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Arquivo inteiro + pint + commit**

```bash
php artisan test --compact tests/Feature/Analise/DistribuicaoServiceTest.php
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/DistribuicaoService.php tests/Feature/Analise/DistribuicaoServiceTest.php
git commit -m "feat: distribuicao em lote entre varios analistas no service"
```

---

### Task 2: Request + action `distribuirLote` + rota (backend)

Endpoint que recebe as alocações, revalida tudo no escopo do ator (atômico), delega ao service e audita o resumo por analista (para o histórico).

**Files:**
- Create: `app/Http/Requests/Gestao/DistribuirEmLoteRequest.php`
- Modify: `app/Http/Controllers/Gestao/CaixaSetorController.php`
- Modify: `routes/gestao.php`
- Test: `tests/Feature/Analise/CaixaSetorTest.php`

**Interfaces:**
- Consumes: `DistribuicaoService::distribuirMultiplo` (Task 1).
- Produces: rota `POST /gestao/caixa-setor/distribuir-lote` (name `caixa-setor.distribuir-lote`). Body: `{ alocacoes: [{ analista_id: int, request_ids: int[] }] }`. Emite auditoria `distribuir-lote` por analista (properties: `analista_id`, `qtd`, `sector_id`, `ator_id`). Consumido pelas Tasks 3 e 4.

- [ ] **Step 1: Escrever o teste (Red)**

Adicionar a `CaixaSetorTest`:

```php
public function test_distribuir_lote_multiplo_distribui_por_analista(): void
{
    $setor = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setor);
    $a = $this->analistaDoSetor($setor);
    $b = $this->analistaDoSetor($setor);

    $p1 = $this->processoNaCaixa($setor);
    $p2 = $this->processoNaCaixa($setor);
    $p3 = $this->processoNaCaixa($setor);

    $this->actingAs($apoio, 'gestao')
        ->post('/gestao/caixa-setor/distribuir-lote', [
            'alocacoes' => [
                ['analista_id' => $a->id, 'request_ids' => [$p1->id, $p2->id]],
                ['analista_id' => $b->id, 'request_ids' => [$p3->id]],
            ],
        ])
        ->assertSessionHas('status');

    $this->assertSame($a->id, $p1->fresh()->assigned_user_id);
    $this->assertSame($a->id, $p2->fresh()->assigned_user_id);
    $this->assertSame($b->id, $p3->fresh()->assigned_user_id);

    // Auditoria de resumo para o histórico (um por analista).
    $this->assertDatabaseHas('activity_log', ['log_name' => 'analise', 'event' => 'distribuir-lote']);
}

public function test_distribuir_lote_rejeita_processo_de_setor_alheio_atomicamente(): void
{
    $setorA = Sector::factory()->create();
    $setorB = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setorA);
    $a = $this->analistaDoSetor($setorA);

    $doA = $this->processoNaCaixa($setorA);
    $doB = $this->processoNaCaixa($setorB); // fora da caixa do ator

    $this->actingAs($apoio, 'gestao')
        ->post('/gestao/caixa-setor/distribuir-lote', [
            'alocacoes' => [
                ['analista_id' => $a->id, 'request_ids' => [$doA->id, $doB->id]],
            ],
        ])
        ->assertSessionHas('error');

    $this->assertNull($doA->fresh()->assigned_user_id, 'Lote inválido não grava nada (atômico).');
    $this->assertNull($doB->fresh()->assigned_user_id);
}

public function test_distribuir_lote_rejeita_ids_sobrepostos(): void
{
    $setor = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setor);
    $a = $this->analistaDoSetor($setor);
    $b = $this->analistaDoSetor($setor);
    $p = $this->processoNaCaixa($setor);

    $this->actingAs($apoio, 'gestao')
        ->post('/gestao/caixa-setor/distribuir-lote', [
            'alocacoes' => [
                ['analista_id' => $a->id, 'request_ids' => [$p->id]],
                ['analista_id' => $b->id, 'request_ids' => [$p->id]], // mesmo processo em dois grupos
            ],
        ])
        ->assertSessionHas('error');

    $this->assertNull($p->fresh()->assigned_user_id);
}

public function test_distribuir_lote_exige_distribuir_processos(): void
{
    $setor = Sector::factory()->create();
    $analista = $this->analistaDoSetor($setor);
    $p = $this->processoNaCaixa($setor);

    $this->actingAs($analista, 'gestao')
        ->post('/gestao/caixa-setor/distribuir-lote', [
            'alocacoes' => [['analista_id' => $analista->id, 'request_ids' => [$p->id]]],
        ])
        ->assertForbidden();
}
```

- [ ] **Step 2: Rodar e ver falhar (Red)**

Run: `php artisan test --compact --filter='distribuir_lote' tests/Feature/Analise/CaixaSetorTest.php`
Expected: FAIL (rota inexistente → 404/405).

- [ ] **Step 3: Criar o Form Request**

`app/Http/Requests/Gestao/DistribuirEmLoteRequest.php`:

```php
<?php

namespace App\Http\Requests\Gestao;

use App\Enums\ViabilityRequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação estrutural da distribuição em lote entre vários analistas (Fase 3):
 * uma lista de alocações {analista, processos}. A autorização é o middleware
 * permission:distribuir-processos da rota. O escopo de setor do ator, a
 * ausência de sobreposição e a atomicidade são regra do controller/serviço.
 */
class DistribuirEmLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alocacoes' => ['required', 'array', 'min:1'],
            'alocacoes.*.analista_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'alocacoes.*.request_ids' => ['required', 'array', 'min:1'],
            'alocacoes.*.request_ids.*' => [
                'integer',
                Rule::exists('viability_requests', 'id')->where('status', ViabilityRequestStatus::EmAnalise->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'alocacoes' => 'alocações',
            'alocacoes.*.analista_id' => 'analista',
            'alocacoes.*.request_ids' => 'processos',
        ];
    }
}
```

- [ ] **Step 4: Adicionar a rota**

Em `routes/gestao.php`, no grupo `permission:distribuir-processos` do prefixo `caixa-setor` (junto de `distribuir`/`redistribuir`):

```php
Route::post('distribuir-lote', [CaixaSetorController::class, 'distribuirLote'])->name('distribuir-lote');
```

- [ ] **Step 5: Implementar a action**

Em `CaixaSetorController`, adicionar (usa `DistribuicaoService` e `AuditService` já injetados):

```php
/**
 * Distribuição em lote entre VÁRIOS analistas (Fase 3): recebe alocações
 * {analista, processos} e as executa numa única operação. Revalida no escopo
 * do ator (RN-004): todos os processos precisam estar na caixa do ator (setor
 * do usuário), EM ANÁLISE e sem responsável; sem sobreposição de ids entre
 * grupos; união = conjunto pedido. Lote inválido é rejeitado INTEIRO — nada é
 * gravado. Registra um resumo por analista (evento distribuir-lote) para o
 * histórico. A decisão de quem recebe o quê é do Apoio.
 */
public function distribuirLote(DistribuirEmLoteRequest $request): RedirectResponse
{
    $sectorIds = $request->user()->sectors()->pluck('sectors.id');
    $alocacoes = $request->input('alocacoes');

    // Todos os ids pedidos, achatados; rejeita sobreposição entre grupos.
    $todosIds = collect($alocacoes)->flatMap(fn (array $a): array => $a['request_ids'])->map(fn ($id): int => (int) $id);
    if ($todosIds->count() !== $todosIds->unique()->count()) {
        return back()->with('error', 'Um mesmo processo foi alocado para mais de um analista.');
    }

    // Todos os processos precisam estar disponíveis na caixa do ator (atômico).
    $processos = ViabilityRequest::query()
        ->whereIn('id', $todosIds->unique())
        ->whereIn('sector_id', $sectorIds)
        ->where('status', ViabilityRequestStatus::EmAnalise->value)
        ->whereNull('assigned_user_id')
        ->get()
        ->keyBy('id');

    if ($processos->count() !== $todosIds->unique()->count()) {
        return back()->with('error', 'Alguns processos não estão disponíveis para distribuição na sua caixa.');
    }

    // Monta os grupos com os models resolvidos (analista + processos).
    $grupos = [];
    foreach ($alocacoes as $alocacao) {
        /** @var User $analista */
        $analista = User::query()->findOrFail($alocacao['analista_id']);
        $requests = collect($alocacao['request_ids'])->map(fn ($id): ViabilityRequest => $processos[(int) $id]);
        $grupos[] = ['analista' => $analista, 'requests' => $requests];
    }

    $resumo = $this->distribuicao->distribuirMultiplo($grupos, $request->user());

    // Resumo por analista para o histórico (só dos que receberam ao menos um).
    foreach ($grupos as $grupo) {
        $qtd = collect($grupo['requests'])->count();
        if ($qtd === 0) {
            continue;
        }

        $this->audit->log('analise', 'distribuir-lote', "Distribuição em lote de {$qtd} processo(s) para {$grupo['analista']->name}.", [
            'analista_id' => $grupo['analista']->id,
            'analista_nome' => $grupo['analista']->name,
            'qtd' => $qtd,
            'sector_id' => collect($grupo['requests'])->first()?->sector_id,
            'ator_id' => $request->user()->id,
        ]);
    }

    $response = back()->with('status', "{$resumo['ok']} processo(s) distribuído(s) entre os analistas.");

    if ($resumo['falhas'] !== []) {
        $motivos = collect($resumo['falhas'])->pluck('motivo')->unique()->implode(' ');
        $response->with('warning', count($resumo['falhas']).' processo(s) não distribuído(s): '.$motivos);
    }

    return $response;
}
```

Garantir o `use App\Http\Requests\Gestao\DistribuirEmLoteRequest;` no topo do controller.

- [ ] **Step 6: Rodar e ver passar (Green)**

Run: `php artisan test --compact --filter='distribuir_lote' tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS (4 testes).

- [ ] **Step 7: Arquivo inteiro + pint + commit**

```bash
php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Gestao/DistribuirEmLoteRequest.php app/Http/Controllers/Gestao/CaixaSetorController.php routes/gestao.php tests/Feature/Analise/CaixaSetorTest.php
git commit -m "feat: endpoint de distribuicao em lote entre varios analistas"
```

---

### Task 3: Histórico de distribuições (backend na Central)

A Central passa a expor as últimas distribuições em lote do(s) setor(es), lidas do `activity_log` (evento `distribuir-lote`).

**Files:**
- Modify: `app/Http/Controllers/Gestao/CaixaSetorController.php` (action `central`)
- Test: `tests/Feature/Analise/CaixaSetorTest.php`

**Interfaces:**
- Produces: prop `historico` na página `gestao/caixa-setor/central`: `list<array{em: string, ator: string, analista: string, qtd: int}>` (mais recentes primeiro, limite 15). Consumido pela Task 4.

- [ ] **Step 1: Escrever o teste (Red)**

Adicionar a `CaixaSetorTest`:

```php
public function test_central_expoe_historico_de_distribuicoes(): void
{
    $setor = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setor);
    $a = $this->analistaDoSetor($setor);
    $p1 = $this->processoNaCaixa($setor);
    $p2 = $this->processoNaCaixa($setor);
    $alvo = $this->processoNaCaixa($setor);

    // Uma distribuição em lote para gerar histórico.
    $this->actingAs($apoio, 'gestao')->post('/gestao/caixa-setor/distribuir-lote', [
        'alocacoes' => [['analista_id' => $a->id, 'request_ids' => [$p1->id, $p2->id]]],
    ])->assertSessionHas('status');

    $props = $this->actingAs($apoio, 'gestao')
        ->get("/gestao/caixa-setor/central?ids={$alvo->id}")
        ->assertOk()
        ->viewData('page')['props'];

    $this->assertNotEmpty($props['historico']);
    $this->assertSame($a->name, $props['historico'][0]['analista']);
    $this->assertSame(2, $props['historico'][0]['qtd']);
}
```

- [ ] **Step 2: Rodar e ver falhar (Red)**

Run: `php artisan test --compact --filter='test_central_expoe_historico_de_distribuicoes' tests/Feature/Analise/CaixaSetorTest.php`
Expected: FAIL (prop `historico` ausente).

- [ ] **Step 3: Implementar a leitura do histórico**

Em `central`, antes do `Inertia::render`, montar o histórico dos setores em foco (usar os setores do usuário quando não houver processos válidos, para a Central ainda mostrar histórico):

```php
$setoresHistorico = $setoresDosProcessos->isNotEmpty() ? $setoresDosProcessos : $sectorIds;

$historico = \App\Models\Activity::query()
    ->where('log_name', 'analise')
    ->where('event', 'distribuir-lote')
    ->whereIn('properties->sector_id', $setoresHistorico->map(fn ($id): int => (int) $id)->all())
    ->latest()
    ->limit(15)
    ->get()
    ->map(fn (\App\Models\Activity $a): array => [
        'em' => $a->created_at?->toIso8601String(),
        'ator' => (string) ($a->properties['ator_nome'] ?? optional($a->causer)->name ?? '—'),
        'analista' => (string) ($a->properties['analista_nome'] ?? '—'),
        'qtd' => (int) ($a->properties['qtd'] ?? 0),
    ])
    ->all();
```

Adicionar `'historico' => $historico,` às props do `Inertia::render`.

Nota: `whereIn('properties->sector_id', ...)` funciona em pgsql e sqlite (JSON). Se o driver de teste reclamar do cast, alternativa portável: buscar as últimas 60 atividades `distribuir-lote` e filtrar em PHP por `properties['sector_id']` ∈ setores, então `take(15)`. Aplique a alternativa se o teste falhar no ambiente.

- [ ] **Step 4: Rodar e ver passar (Green)**

Run: `php artisan test --compact --filter='test_central_expoe_historico_de_distribuicoes' tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS.

- [ ] **Step 5: Arquivo inteiro + pint + commit**

```bash
php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/CaixaSetorController.php tests/Feature/Analise/CaixaSetorTest.php
git commit -m "feat: historico de distribuicoes na central"
```

---

### Task 4: Central com modo "vários analistas" + histórico (frontend)

A Central ganha um alternador de modo. No modo "vários analistas", cada analista tem um campo de quantidade; o frontend aloca os processos por ordem de prazo (a ordem em que vieram), mostra a prévia (quais processos para cada analista) e habilita confirmar quando a soma = total; confirma via `POST distribuir-lote`. Um painel "Histórico" lista as últimas distribuições.

**Files:**
- Modify: `resources/js/pages/gestao/caixa-setor/central.tsx`

**Interfaces:**
- Consumes: props `processos`, `analistas`, `totalSelecionados`, `totalEmAnalise` (Fase 2) + `historico` (Task 3); `POST /gestao/caixa-setor/distribuir-lote` com `{ alocacoes: [{analista_id, request_ids}] }`.

- [ ] **Step 1: Estender props e estado de modo/quantidades**

Adicionar ao topo do componente a prop e o estado:

```tsx
interface HistoricoItem {
    em: string | null;
    ator: string;
    analista: string;
    qtd: number;
}

// em CentralProps:
    historico: HistoricoItem[];

// dentro do componente:
const [modo, setModo] = useState<'um' | 'varios'>('um');
const [quantidades, setQuantidades] = useState<Record<number, number>>({});
const [historicoAberto, setHistoricoAberto] = useState(false);

const loteForm = useForm<{ alocacoes: { analista_id: number; request_ids: number[] }[] }>({ alocacoes: [] });
```

- [ ] **Step 2: Alocação por ordem de prazo + prévia (memo)**

```tsx
const totalAlocado = useMemo(
    () => Object.values(quantidades).reduce((soma, q) => soma + (q || 0), 0),
    [quantidades],
);

// Aloca os processos (já ordenados por prazo pelo backend) na ordem dos
// analistas que têm quantidade > 0. Determinístico e visível na prévia.
const alocacaoPrevia = useMemo(() => {
    const ids = processos.map((p) => p.id);
    let cursor = 0;
    const grupos: { analista_id: number; analista: string; request_ids: number[] }[] = [];

    for (const a of analistas) {
        const q = quantidades[a.analista_id] || 0;
        if (q <= 0) {
            continue;
        }
        const fatia = ids.slice(cursor, cursor + q);
        cursor += q;
        grupos.push({ analista_id: a.analista_id, analista: a.analista, request_ids: fatia });
    }

    return grupos;
}, [analistas, quantidades, processos]);

const processoPorId = useMemo(() => new Map(processos.map((p) => [p.id, p])), [processos]);

function definirQuantidade(analistaId: number, valor: number) {
    const restante = totalSelecionados - (totalAlocado - (quantidades[analistaId] || 0));
    const limitado = Math.max(0, Math.min(valor, restante));
    setQuantidades((atual) => ({ ...atual, [analistaId]: limitado }));
}

function confirmarLote() {
    if (totalAlocado !== totalSelecionados) {
        return;
    }
    loteForm.transform(() => ({
        alocacoes: alocacaoPrevia.map((g) => ({ analista_id: g.analista_id, request_ids: g.request_ids })),
    }));
    loteForm.post('/gestao/caixa-setor/distribuir-lote', {
        preserveScroll: true,
        onSuccess: () => router.visit('/gestao/caixa-setor'),
    });
}
```

- [ ] **Step 3: Alternador de modo + UI do modo "vários"**

Adicionar, acima da lista de analistas, um alternador:

```tsx
<div className="mb-4 inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 dark:border-gray-800 dark:bg-white/[0.03]">
    {([['um', 'Um analista'], ['varios', 'Vários analistas']] as const).map(([valor, rotulo]) => (
        <button
            key={valor}
            type="button"
            onClick={() => setModo(valor)}
            className={`rounded-md px-4 py-2 text-theme-sm font-medium ${
                modo === valor ? 'bg-white text-brand-500 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400' : 'text-gray-500'
            }`}
        >
            {rotulo}
        </button>
    ))}
</div>
```

No modo `varios`, cada linha de analista mostra, além da carga, um campo de quantidade (`type="number"`, `min=0`, `max=restante`) ligado a `definirQuantidade`. Substituir o `onClick` de seleção da linha por um input quando `modo === 'varios'`. Adicionar uma barra fixa de resumo: `Alocados {totalAlocado} de {totalSelecionados}` e o botão **Confirmar distribuição** (habilitado só quando `totalAlocado === totalSelecionados`, `loading={loteForm.processing}`) que chama `confirmarLote`.

Prévia (quando há alocação): abaixo, listar por analista os processos alocados:

```tsx
{modo === 'varios' && alocacaoPrevia.length > 0 && (
    <div className="mt-4 space-y-3">
        {alocacaoPrevia.map((g) => (
            <div key={g.analista_id} className="rounded-xl border border-gray-100 p-3 dark:border-gray-800">
                <p className="mb-1 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                    {g.analista} — {g.request_ids.length} processo(s)
                </p>
                <ul className="text-theme-xs text-gray-500 dark:text-gray-400">
                    {g.request_ids.map((id) => {
                        const p = processoPorId.get(id);
                        return <li key={id}>{p?.bap ?? p?.protocol_number ?? `#${id}`}</li>;
                    })}
                </ul>
            </div>
        ))}
    </div>
)}
```

Manter o modo `um` exatamente como na Fase 2 (seleção de 1 analista + antes/depois + `POST distribuir`).

- [ ] **Step 4: Painel de histórico**

Adicionar um botão "Ver histórico" (ex.: no cabeçalho do card) que alterna `historicoAberto`, e um bloco:

```tsx
{historicoAberto && (
    <Card>
        <CardHeader title="Histórico de distribuições" description="Últimas distribuições em lote do setor." />
        <CardContent>
            {historico.length === 0 ? (
                <p className="text-theme-sm text-gray-500 dark:text-gray-400">Sem distribuições registradas.</p>
            ) : (
                <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                    {historico.map((h, i) => (
                        <li key={i} className="flex flex-wrap items-center justify-between gap-2 py-2 text-theme-sm">
                            <span className="text-gray-700 dark:text-gray-300">
                                {h.em ? new Date(h.em).toLocaleString('pt-BR') : '—'}
                            </span>
                            <span className="text-gray-600 dark:text-gray-400">{h.ator}</span>
                            <span className="font-medium text-gray-800 dark:text-white/90">{h.qtd} → {h.analista}</span>
                        </li>
                    ))}
                </ul>
            )}
        </CardContent>
    </Card>
)}
```

- [ ] **Step 5: Build**

Run: `npm run build`
Expected: sem erros de TypeScript.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/gestao/caixa-setor/central.tsx
git commit -m "feat: central com lote multi-analista, previa e historico"
```

---

### Task 5: Verificação final da fase

**Files:** nenhum (verificação).

- [ ] **Step 1: Navegação íntegra**

Run: `npx vitest run resources/js/navigation/gestao-nav.test.ts`
Expected: PASS.

- [ ] **Step 2: Suíte de análise dos arquivos tocados**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php tests/Feature/Analise/DistribuicaoServiceTest.php tests/Feature/Analise/CargaAnalistaServiceTest.php`
Expected: PASS.

- [ ] **Step 3: Build final**

Run: `npm run build`
Expected: sem erros.

- [ ] **Step 4: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: limpo.

---

## Self-Review

**Spec coverage (Fase 3):**
- Quantidade por analista (manual) + alocação por ordem de prazo + prévia → Task 4 (frontend) sobre o `distribuirMultiplo` (Task 1) e o endpoint (Task 2).
- Soma = total antes de confirmar → Task 4 (`totalAlocado === totalSelecionados`) + backend atômico (Task 2).
- Segurança (escopo do ator, EmAnalise, sem responsável, sem sobreposição, atômico) → Task 2.
- Histórico simples (data/hora, ator, analista, qtd) via activity_log → Tasks 2 (emissão `distribuir-lote`) + 3 (leitura) + 4 (UI).
- Sem automação (Apoio define analistas e quantidades) → constraints + UI.

**Placeholder scan:** sem TBD/TODO; código completo em cada passo.

**Type consistency:** `distribuirMultiplo(array $grupos, User): {ok,falhas}` (Task 1) usado na Task 2; `alocacoes: [{analista_id:int, request_ids:int[]}]` idêntico entre Request (Task 2), action (Task 2) e frontend `confirmarLote` (Task 4); `historico: [{em,ator,analista,qtd}]` entre Task 3 (backend) e `HistoricoItem` (Task 4).

**Notas de risco:**
- `whereIn('properties->sector_id', ...)` pode variar por driver; a Task 3 traz a alternativa portável (filtrar em PHP) caso o teste falhe.
- Sessão concorrente do usuário na branch: `git add` por-arquivo; não commitar `public/build`.
- A Central já bloqueia multi-setor (Fase 2), então o lote multi-analista opera sempre dentro de um setor — os analistas listados são todos do mesmo setor.
