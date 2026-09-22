# Central de Distribuição (Fase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Substituir o modal simples de distribuição por uma página dedicada (Central de Distribuição) onde o Apoio vê a carga real de cada analista do setor (número + breakdown por etapa + barra relativa), busca/ordena, e confirma a distribuição para UM analista com visão antes/depois — sem automação.

**Architecture:** Nova action GET `CaixaSetorController::central` renderiza a página `gestao/caixa-setor/central` com os processos selecionados (via `?ids=`) e a lista de analistas do(s) setor(es) desses processos, cada um com a carga calculada por um `CargaAnalistaService`. A confirmação reusa o `POST /gestao/caixa-setor/distribuir` já existente (1 analista + `request_ids[]`). O botão de distribuição da Caixa do setor passa a navegar para a Central em vez de abrir o modal.

**Tech Stack:** Laravel 13, Inertia v3 + React 19, Tailwind 4, PHPUnit 12.

## Global Constraints

- Idioma: UI, mensagens, nomes de teste (`test_...`) e PHPDoc em pt-BR; código em inglês.
- TDD estrito: teste falhando antes do código de produção. Feature tests inspecionam props Inertia (padrão do projeto).
- Decisão de destinatário 100% do Apoio — sem distribuição automática nem sugestão de "menor carga" pelo sistema (a ordenação por carga é só visualização).
- Acessibilidade (eMAG/WCAG): número da carga SEMPRE visível como texto; cor apenas complementa; barras com `role=progressbar` (usar `ProgressBar` do design system).
- "Carga ativa de análise" = processos com `status = EmAnalise` e `assigned_user_id = analista`, restritos ao(s) setor(es) dos processos selecionados; breakdown por `AnalysisStatus::grupo()` (Distribuição/Análise/Convite/Vistoria).
- Lista de analistas = apenas os vinculados ao(s) setor(es) dos processos selecionados, com permissão `analisar-processos` (mantém a regra de vínculo já existente; `DistribuicaoService` continua validando no POST).
- Permissão da Central: `distribuir-processos` (rota).
- Reusar `POST /gestao/caixa-setor/distribuir` para gravar — NÃO criar novo endpoint de distribuição nesta fase (lote multi-analista é Fase 3).
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- `git add` por-arquivo (NÃO `git add -A`): há sessão concorrente do usuário na branch. NÃO commitar `public/build`.
- Nenhum item novo de menu (a Central é filha da Caixa do setor); `npx vitest run resources/js/navigation/gestao-nav.test.ts` continua verde.

---

### Task 1: `CargaAnalistaService` — carga ativa por analista

Serviço que calcula, para um conjunto de setores, a carga ativa de análise de cada analista vinculado, com total e breakdown por grupo de etapa.

**Files:**
- Create: `app/Services/Analise/CargaAnalistaService.php`
- Test: `tests/Feature/Analise/CargaAnalistaServiceTest.php`

**Interfaces:**
- Produces:
  ```php
  CargaAnalistaService::cargaDosSetores(iterable $sectorIds): array
  // retorna, por analista vinculado a algum dos setores (com analisar-processos):
  // list<array{
  //   analista_id: int, analista: string, total: int,
  //   por_grupo: array{Distribuição:int, Análise:int, Convite:int, Vistoria:int}
  // }>
  // Analistas sem carga aparecem com total=0 e grupos zerados (o Apoio precisa vê-los).
  ```
  Consumido pela Task 2.

- [ ] **Step 1: Escrever o teste (Red)**

```php
<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\CargaAnalistaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CargaAnalistaServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analistaDoSetor(Sector $sector): User
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($sector);

        return $analista;
    }

    private function processoAtribuido(Sector $sector, User $analista, AnalysisStatus $status): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(['status' => ViabilityRequestStatus::EmAnalise]);
        $request->forceFill([
            'sector_id' => $sector->id,
            'assigned_user_id' => $analista->id,
            'analysis_status' => $status,
        ])->save();

        return $request;
    }

    public function test_carga_conta_ativos_por_analista_com_breakdown_por_grupo(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $this->processoAtribuido($setor, $analista, AnalysisStatus::EmAnalise);
        $this->processoAtribuido($setor, $analista, AnalysisStatus::Analisar);
        $this->processoAtribuido($setor, $analista, AnalysisStatus::EmConvite);

        $carga = app(CargaAnalistaService::class)->cargaDosSetores([$setor->id]);
        $linha = collect($carga)->firstWhere('analista_id', $analista->id);

        $this->assertSame(3, $linha['total']);
        $this->assertSame(2, $linha['por_grupo']['Análise']);
        $this->assertSame(1, $linha['por_grupo']['Convite']);
    }

    public function test_analista_do_setor_sem_carga_aparece_com_zero(): void
    {
        $setor = Sector::factory()->create();
        $analista = $this->analistaDoSetor($setor);

        $carga = app(CargaAnalistaService::class)->cargaDosSetores([$setor->id]);
        $linha = collect($carga)->firstWhere('analista_id', $analista->id);

        $this->assertNotNull($linha, 'Analista vinculado ao setor aparece mesmo sem carga.');
        $this->assertSame(0, $linha['total']);
    }

    public function test_carga_restrita_aos_setores_informados(): void
    {
        $setorA = Sector::factory()->create();
        $setorB = Sector::factory()->create();
        $doA = $this->analistaDoSetor($setorA);
        $doB = $this->analistaDoSetor($setorB);

        $carga = app(CargaAnalistaService::class)->cargaDosSetores([$setorA->id]);
        $ids = collect($carga)->pluck('analista_id')->all();

        $this->assertContains($doA->id, $ids);
        $this->assertNotContains($doB->id, $ids, 'Analista de outro setor não entra na carga.');
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (Red)**

Run: `php artisan test --compact tests/Feature/Analise/CargaAnalistaServiceTest.php`
Expected: FAIL (classe `CargaAnalistaService` não existe).

- [ ] **Step 3: Implementar o serviço**

```php
<?php

namespace App\Services\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use Illuminate\Support\Facades\DB;

/**
 * Carga ativa de análise por analista (Central de Distribuição — Fase 2). Para o
 * Apoio decidir a distribuição, conta os processos EM ANÁLISE atribuídos a cada
 * analista vinculado ao(s) setor(es), com breakdown por grupo de etapa
 * (AnalysisStatus::grupo()). Analistas sem carga aparecem com zero — o Apoio
 * precisa vê-los para poder escolhê-los. NÃO sugere destinatário: só mede.
 */
class CargaAnalistaService
{
    /**
     * @param  iterable<int, int>  $sectorIds
     * @return list<array{analista_id: int, analista: string, total: int, por_grupo: array<string, int>}>
     */
    public function cargaDosSetores(iterable $sectorIds): array
    {
        $ids = collect($sectorIds)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        // Grupos possíveis (todos zerados por padrão) — ordem estável para a UI.
        $gruposBase = [];
        foreach (AnalysisStatus::cases() as $status) {
            $gruposBase[$status->grupo()] = 0;
        }

        // Analistas vinculados ao(s) setor(es), com permissão de análise.
        $analistas = User::query()
            ->permission('analisar-processos')
            ->whereHas('sectors', fn ($query) => $query->whereIn('sectors.id', $ids))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Contagem ativa por analista e status (uma query), restrita aos setores.
        $contagens = ViabilityRequest::query()
            ->whereIn('sector_id', $ids)
            ->where('status', ViabilityRequestStatus::EmAnalise->value)
            ->whereNotNull('assigned_user_id')
            ->groupBy('assigned_user_id', 'analysis_status')
            ->get([
                'assigned_user_id',
                'analysis_status',
                DB::raw('count(*) as total'),
            ]);

        return $analistas->map(function (User $analista) use ($contagens, $gruposBase): array {
            $porGrupo = $gruposBase;
            $total = 0;

            foreach ($contagens->where('assigned_user_id', $analista->id) as $linha) {
                $status = $linha->analysis_status instanceof AnalysisStatus
                    ? $linha->analysis_status
                    : ($linha->analysis_status !== null ? AnalysisStatus::from($linha->analysis_status) : null);

                $grupo = $status?->grupo() ?? 'Análise';
                $porGrupo[$grupo] = ($porGrupo[$grupo] ?? 0) + (int) $linha->total;
                $total += (int) $linha->total;
            }

            return [
                'analista_id' => $analista->id,
                'analista' => $analista->name,
                'total' => $total,
                'por_grupo' => $porGrupo,
            ];
        })->all();
    }
}
```

Nota de implementação: `analysis_status` tem cast para `AnalysisStatus` no model, mas aqui a linha vem de um `get()` com `select` cru + `groupBy` — o valor pode chegar como string; o código trata os dois casos. Se o cast já resolver, o ramo `instanceof` cobre.

- [ ] **Step 4: Rodar e ver passar (Green)**

Run: `php artisan test --compact tests/Feature/Analise/CargaAnalistaServiceTest.php`
Expected: PASS (3 testes).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/CargaAnalistaService.php tests/Feature/Analise/CargaAnalistaServiceTest.php
git commit -m "feat: servico de carga ativa por analista para a central de distribuicao"
```

---

### Task 2: Action `central` + rota (backend)

GET que valida os `ids` selecionados (do(s) setor(es) do usuário, `para_distribuir`, `EmAnalise`), descobre os setores desses processos e renderiza a Central com a carga dos analistas.

**Files:**
- Modify: `app/Http/Controllers/Gestao/CaixaSetorController.php`
- Modify: `routes/gestao.php` (grupo `caixa-setor`, middleware `distribuir-processos`)
- Test: `tests/Feature/Analise/CaixaSetorTest.php`

**Interfaces:**
- Consumes: `CargaAnalistaService::cargaDosSetores` (Task 1).
- Produces: rota `GET /gestao/caixa-setor/central` (name `caixa-setor.central`); página Inertia `gestao/caixa-setor/central` com props:
  - `processos`: `list<array{id:int, bap:?string, protocol_number:?string, imovel:string, analysis_stage_label:?string}>` (os selecionados válidos)
  - `analistas`: retorno de `cargaDosSetores` (Task 1)
  - `totalSelecionados`: int
  - `totalEmAnalise`: int (soma das cargas — "processos atualmente distribuídos")
  Consumido pela Task 3.

- [ ] **Step 1: Escrever o teste (Red)**

Adicionar a `CaixaSetorTest`:

```php
public function test_central_lista_processos_selecionados_e_carga_dos_analistas(): void
{
    $setor = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setor);
    $analista = $this->analistaDoSetor($setor);

    $p1 = $this->processoNaCaixa($setor);
    $p2 = $this->processoNaCaixa($setor);

    // Carga pré-existente do analista (1 processo já atribuído no setor).
    $jaAtribuido = $this->processoNaCaixa($setor);
    $jaAtribuido->forceFill(['assigned_user_id' => $analista->id, 'analysis_status' => \App\Enums\AnalysisStatus::EmAnalise])->save();

    $props = $this->actingAs($apoio, 'gestao')
        ->get("/gestao/caixa-setor/central?ids={$p1->id},{$p2->id}")
        ->assertOk()
        ->viewData('page')['props'];

    $idsProcessos = collect($props['processos'])->pluck('id')->all();
    $this->assertContains($p1->id, $idsProcessos);
    $this->assertContains($p2->id, $idsProcessos);
    $this->assertSame(2, $props['totalSelecionados']);

    $linha = collect($props['analistas'])->firstWhere('analista_id', $analista->id);
    $this->assertSame(1, $linha['total'], 'A carga atual do analista aparece na Central.');
}

public function test_central_exige_distribuir_processos(): void
{
    $setor = Sector::factory()->create();
    $analista = $this->analistaDoSetor($setor);
    $p = $this->processoNaCaixa($setor);

    $this->actingAs($analista, 'gestao')
        ->get("/gestao/caixa-setor/central?ids={$p->id}")
        ->assertForbidden();
}

public function test_central_ignora_ids_fora_dos_setores_do_usuario(): void
{
    $setorA = Sector::factory()->create();
    $setorB = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setorA);

    $doA = $this->processoNaCaixa($setorA);
    $doB = $this->processoNaCaixa($setorB);

    $props = $this->actingAs($apoio, 'gestao')
        ->get("/gestao/caixa-setor/central?ids={$doA->id},{$doB->id}")
        ->assertOk()
        ->viewData('page')['props'];

    $ids = collect($props['processos'])->pluck('id')->all();
    $this->assertContains($doA->id, $ids);
    $this->assertNotContains($doB->id, $ids, 'Processo de setor alheio não entra na Central.');
}
```

- [ ] **Step 2: Rodar e ver falhar (Red)**

Run: `php artisan test --compact --filter='central' tests/Feature/Analise/CaixaSetorTest.php`
Expected: FAIL (rota `central` inexistente → 404/erro).

- [ ] **Step 3: Adicionar a rota**

Em `routes/gestao.php`, no grupo `Route::middleware('permission:distribuir-processos')` do prefixo `caixa-setor`, adicionar ANTES das POST:

```php
Route::get('central', [CaixaSetorController::class, 'central'])->name('central');
```

- [ ] **Step 4: Implementar a action**

Injetar `CargaAnalistaService` no construtor do `CaixaSetorController` (junto de `DistribuicaoService`, `AuditService`, `ProcessoQueryService`):

```php
private \App\Services\Analise\CargaAnalistaService $carga,
```

Adicionar o método:

```php
/**
 * Central de Distribuição (Fase 2): a partir dos processos selecionados na
 * caixa (ids), mostra a carga ativa de cada analista do(s) setor(es) desses
 * processos para o Apoio decidir a distribuição. Só processos EM ANÁLISE, sem
 * responsável, do(s) setor(es) do usuário (RN-004). A gravação é o POST
 * distribuir já existente. A decisão do destinatário é do Apoio — aqui só se
 * mede a carga (nunca se sugere). A consulta é auditada.
 */
public function central(Request $request): Response
{
    $sectorIds = $request->user()->sectors()->pluck('sectors.id');

    $ids = collect(explode(',', (string) $request->string('ids')))
        ->map(fn ($id): int => (int) trim($id))
        ->filter()
        ->unique()
        ->values();

    $processos = ViabilityRequest::query()
        ->whereIn('id', $ids)
        ->whereIn('sector_id', $sectorIds)
        ->where('status', ViabilityRequestStatus::EmAnalise->value)
        ->whereNull('assigned_user_id')
        ->with(['sector:id,name'])
        ->orderBy('analysis_due_at')
        ->orderBy('id')
        ->get()
        ->map(fn (ViabilityRequest $processo): array => [
            'id' => $processo->id,
            'bap' => $processo->external_reference,
            'protocol_number' => $processo->protocol_number,
            'imovel' => implode(' - ', array_filter([
                trim(implode(', ', array_filter([$processo->address_street, $processo->address_number]))),
                $processo->address_neighborhood,
            ])),
            'analysis_stage_label' => $processo->analysis_stage?->label(),
        ])
        ->all();

    // Setores efetivos dos processos válidos (interseção com os do usuário).
    $setoresDosProcessos = ViabilityRequest::query()
        ->whereIn('id', collect($processos)->pluck('id'))
        ->pluck('sector_id')
        ->unique()
        ->values();

    $analistas = $this->carga->cargaDosSetores($setoresDosProcessos);
    $totalEmAnalise = collect($analistas)->sum('total');

    $this->audit->log('analise', 'consulta-central', 'Consulta da central de distribuição', [
        'setores' => $setoresDosProcessos->all(),
        'processos' => collect($processos)->pluck('id')->all(),
    ]);

    return Inertia::render('gestao/caixa-setor/central', [
        'processos' => $processos,
        'analistas' => $analistas,
        'totalSelecionados' => count($processos),
        'totalEmAnalise' => $totalEmAnalise,
    ]);
}
```

- [ ] **Step 5: Rodar e ver passar (Green)**

Run: `php artisan test --compact --filter='central' tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS (3 testes).

- [ ] **Step 6: Arquivo inteiro + pint + commit**

```bash
php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/CaixaSetorController.php routes/gestao.php tests/Feature/Analise/CaixaSetorTest.php
git commit -m "feat: pagina da central de distribuicao com carga dos analistas"
```

---

### Task 3: Página da Central de Distribuição (frontend)

Página larga com resumo no topo, tabela de analistas (nome, carga badge + breakdown + barra relativa ao maior, após envio), busca por nome, ordenação (menor/maior carga, A–Z), seleção de 1 analista com antes/depois, e confirmação que faz `POST /gestao/caixa-setor/distribuir`.

**Files:**
- Create: `resources/js/pages/gestao/caixa-setor/central.tsx`

**Interfaces:**
- Consumes: props da Task 2 (`processos`, `analistas`, `totalSelecionados`, `totalEmAnalise`); `POST /gestao/caixa-setor/distribuir` com `{ request_ids: number[], analista_id: number }`; componentes `ProgressBar` (`@/components/ui/progress-bar`), `Button`, `Card`/`CardHeader`/`CardContent`, `PageHeader`, `Input`, `Select`, `GestaoLayout`, `router`/`useForm` do Inertia.

- [ ] **Step 1: Criar a página**

```tsx
import { Head, router, useForm } from '@inertiajs/react';
import { type ReactNode, useMemo, useState } from 'react';
import PageHeader from '@/components/app/page-header';
import Input from '@/components/form/input';
import Button from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ProgressBar from '@/components/ui/progress-bar';
import GestaoLayout from '@/layouts/gestao-layout';

interface ProcessoSelecionado {
    id: number;
    bap: string | null;
    protocol_number: string | null;
    imovel: string;
    analysis_stage_label: string | null;
}

interface AnalistaCarga {
    analista_id: number;
    analista: string;
    total: number;
    por_grupo: Record<string, number>;
}

interface CentralProps {
    processos: ProcessoSelecionado[];
    analistas: AnalistaCarga[];
    totalSelecionados: number;
    totalEmAnalise: number;
}

type Ordenacao = 'menor' | 'maior' | 'nome';

export default function CentralDistribuicao({ processos, analistas, totalSelecionados, totalEmAnalise }: CentralProps) {
    const [busca, setBusca] = useState('');
    const [ordenacao, setOrdenacao] = useState<Ordenacao>('menor');
    const [selecionado, setSelecionado] = useState<number | null>(null);

    const form = useForm<{ request_ids: number[]; analista_id: string }>({
        request_ids: processos.map((p) => p.id),
        analista_id: '',
    });

    const maiorCarga = useMemo(
        () => analistas.reduce((max, a) => Math.max(max, a.total), 0),
        [analistas],
    );

    const listaVisivel = useMemo(() => {
        const termo = busca.trim().toLowerCase();
        const filtrados = termo === ''
            ? analistas
            : analistas.filter((a) => a.analista.toLowerCase().includes(termo));

        const ordenados = [...filtrados];
        ordenados.sort((a, b) => {
            if (ordenacao === 'nome') {
                return a.analista.localeCompare(b.analista, 'pt-BR');
            }
            if (ordenacao === 'maior') {
                return b.total - a.total;
            }
            return a.total - b.total;
        });

        return ordenados;
    }, [analistas, busca, ordenacao]);

    const escolhido = analistas.find((a) => a.analista_id === selecionado) ?? null;

    function confirmar() {
        if (selecionado === null) {
            return;
        }

        form.transform((data) => ({ ...data, analista_id: String(selecionado) }));
        form.post('/gestao/caixa-setor/distribuir', {
            preserveScroll: true,
            onSuccess: () => router.visit('/gestao/caixa-setor'),
        });
    }

    return (
        <>
            <Head title="Central de distribuição" />
            <PageHeader
                title="Central de distribuição"
                breadcrumbs={[
                    { label: 'Painel', href: '/gestao' },
                    { label: 'Caixa do setor', href: '/gestao/caixa-setor' },
                ]}
            />

            <div className="space-y-6">
                <Card>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <ResumoItem rotulo="Processos selecionados" valor={totalSelecionados} />
                            <ResumoItem rotulo="Analistas disponíveis" valor={analistas.length} />
                            <ResumoItem rotulo="Processos em análise no setor" valor={totalEmAnalise} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        title="Escolha o analista"
                        description="A carga atual apoia a decisão. A escolha do destinatário é sua — o sistema não distribui automaticamente."
                    />
                    <CardContent>
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="w-full sm:max-w-xs">
                                <Input
                                    id="busca-analista"
                                    value={busca}
                                    onChange={(e) => setBusca(e.target.value)}
                                    placeholder="Pesquisar analista..."
                                    aria-label="Pesquisar analista"
                                />
                            </div>
                            <label className="flex items-center gap-2 text-theme-sm text-gray-500 dark:text-gray-400">
                                Ordenar
                                <select
                                    value={ordenacao}
                                    onChange={(e) => setOrdenacao(e.target.value as Ordenacao)}
                                    className="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                                >
                                    <option value="menor">Menor carga</option>
                                    <option value="maior">Maior carga</option>
                                    <option value="nome">Ordem alfabética</option>
                                </select>
                            </label>
                        </div>

                        <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                            {listaVisivel.map((a) => {
                                const ativo = a.analista_id === selecionado;
                                const percentual = maiorCarga > 0 ? (a.total / maiorCarga) * 100 : 0;
                                const grupos = Object.entries(a.por_grupo).filter(([, n]) => n > 0);

                                return (
                                    <li key={a.analista_id}>
                                        <button
                                            type="button"
                                            onClick={() => setSelecionado(a.analista_id)}
                                            aria-pressed={ativo}
                                            className={`flex w-full flex-col gap-2 px-3 py-3 text-left transition-colors sm:flex-row sm:items-center sm:gap-4 ${
                                                ativo ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[0.03]'
                                            }`}
                                        >
                                            <span className="min-w-[200px] flex-1 font-medium text-gray-800 dark:text-white/90">
                                                {a.analista}
                                            </span>
                                            <span className="flex min-w-[240px] flex-1 items-center gap-3">
                                                <span className="inline-flex min-w-[3.5rem] justify-center rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-sm font-semibold tabular-nums text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                                    {a.total}
                                                </span>
                                                <span className="w-full max-w-[220px]">
                                                    <ProgressBar
                                                        value={percentual}
                                                        tone={a.total >= maiorCarga && maiorCarga > 0 ? 'warning' : 'brand'}
                                                        label={`Carga de ${a.analista}: ${a.total} processos`}
                                                    />
                                                </span>
                                            </span>
                                            <span className="min-w-[160px] text-theme-xs text-gray-500 dark:text-gray-400">
                                                {grupos.length > 0
                                                    ? grupos.map(([g, n]) => `${n} ${g}`).join(' · ')
                                                    : 'sem carga ativa'}
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    </CardContent>
                </Card>

                {escolhido && (
                    <Card>
                        <CardContent>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                                        Você está encaminhando <strong>{totalSelecionados}</strong> processo(s) para
                                    </p>
                                    <p className="text-lg font-semibold text-gray-800 dark:text-white/90">{escolhido.analista}</p>
                                    <p className="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">
                                        Carga atual: <strong className="tabular-nums">{escolhido.total}</strong>
                                        {'  +  '}
                                        Novos: <strong className="tabular-nums">{totalSelecionados}</strong>
                                        {'  =  '}
                                        Após envio: <strong className="tabular-nums">{escolhido.total + totalSelecionados}</strong>
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Button variant="outline" onClick={() => router.visit('/gestao/caixa-setor')} disabled={form.processing}>
                                        Cancelar
                                    </Button>
                                    <Button variant="primary" onClick={confirmar} loading={form.processing}>
                                        Confirmar envio
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function ResumoItem({ rotulo, valor }: { rotulo: string; valor: number }) {
    return (
        <div className="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
            <p className="text-theme-xs tracking-wide text-gray-400 uppercase">{rotulo}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{valor}</p>
        </div>
    );
}

CentralDistribuicao.layout = (page: ReactNode) => <GestaoLayout>{page}</GestaoLayout>;
```

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: sem erros de TypeScript.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/gestao/caixa-setor/central.tsx
git commit -m "feat: tela da central de distribuicao com visao de carga"
```

---

### Task 4: Caixa do setor abre a Central (frontend)

O botão "Tramitar/Distribuir selecionados" e a ação de distribuir por linha passam a navegar para a Central (`/gestao/caixa-setor/central?ids=...`) em vez de abrir o modal. Redistribuir e o fluxo de vistoria continuam no modal atual.

**Files:**
- Modify: `resources/js/pages/gestao/caixa-setor/index.tsx`

**Interfaces:**
- Consumes: rota `caixa-setor.central` (Task 2). Não altera props do backend.

- [ ] **Step 1: Navegar para a Central na distribuição**

Adicionar um helper e trocar os `onClick` que hoje chamam `abrirModal({ tipo: 'distribuir', ... })`:

```tsx
function irParaCentral(ids: number[]) {
    if (ids.length === 0) {
        return;
    }
    router.get('/gestao/caixa-setor/central', { ids: ids.join(',') }, { preserveScroll: true });
}
```

- Botão de lote (hoje "Tramitar selecionados (n)"): `onClick={() => irParaCentral(selecionados)}`; manter o rótulo "Distribuir selecionados ({selecionados.length})".
- Ação de distribuir por linha (o `TableAction` com `tipo: 'distribuir'`, quando `assigned_user_id === null` e NÃO em vistoria): `onClick={() => irParaCentral([item.id])}`.
- MANTER o modal para: redistribuir (`tipo: 'redistribuir'`) e para linhas em vistoria (`emVistoria(item)`), que exigem o seletor de vistoriador. Ou seja: se a linha estiver em vistoria, continuar abrindo o modal (o seletor de vistoriadores é específico e fora do escopo da Central da Fase 2).

Regra concreta para a ação por linha:
```tsx
onClick={() => (emVistoria(item) ? abrirModal({ tipo: 'distribuir', ids: [item.id], descricao: `Processo ${item.bap ?? item.protocol_number ?? item.id}` }) : irParaCentral([item.id]))}
```
E o botão de lote só aparece na aba `para_distribuir`; se a seleção tiver alguma linha em vistoria, manter o modal (o Apoio raramente mistura, mas a Central da Fase 2 é para analistas; vistoria é caso do modal). Implementação simples: se `selecionados` contém alguma linha em vistoria, o botão de lote abre o modal; senão, vai para a Central.

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: sem erros.

- [ ] **Step 3: Backend sem regressão**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/gestao/caixa-setor/index.tsx
git commit -m "feat: caixa do setor abre a central de distribuicao"
```

---

### Task 5: Verificação final da fase

**Files:** nenhum (verificação).

- [ ] **Step 1: Navegação íntegra**

Run: `npx vitest run resources/js/navigation/gestao-nav.test.ts`
Expected: PASS (nenhum item de menu novo — a Central é filha da caixa).

- [ ] **Step 2: Suíte de análise dos arquivos tocados**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php tests/Feature/Analise/CargaAnalistaServiceTest.php tests/Feature/Analise/ProcessoFilaTest.php`
Expected: PASS.

- [ ] **Step 3: Build final**

Run: `npm run build`
Expected: sem erros.

- [ ] **Step 4: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: limpo (ou aplica e commita).

---

## Self-Review

**Spec coverage (Fase 2):**
- Página dedicada da Central → Tasks 2 (rota+action) e 3 (tela).
- Carga real por analista (número + breakdown + barra relativa) → Task 1 (serviço) + Task 3 (barra relativa ao maior).
- Resumo no topo (selecionados, analistas, em análise) → Tasks 2 (props) e 3 (UI).
- Busca + ordenação (menor/maior/A–Z) → Task 3.
- Antes/depois + confirmação → Task 3.
- Acessibilidade (número sempre visível, `ProgressBar` com role) → Task 3 + constraint.
- Reuso do POST distribuir (sem endpoint novo) → Tasks 3/4.
- Decisão 100% do Apoio, sem automação → constraint + textos da UI.

**Placeholder scan:** sem TBD/TODO; código completo em cada passo.

**Type consistency:** `cargaDosSetores(iterable): list<{analista_id,analista,total,por_grupo}>` (Task 1) = shape `AnalistaCarga` (Task 3) e prop `analistas` (Task 2). `processos` shape idêntico entre Task 2 (backend) e `ProcessoSelecionado` (Task 3). `POST distribuir` recebe `{request_ids, analista_id}` (contrato já existente, validado por `DistribuirProcessoRequest`).

**Notas de risco:**
- `analysis_status` em `groupBy` cru pode vir como string ou enum — o serviço trata os dois (Task 1). Confirmar na execução (Green do teste de breakdown).
- Sessão concorrente do usuário na branch: `git add` por-arquivo; não commitar `public/build`.
- Fase 3 (lote multi-analista + histórico) NÃO entra aqui — o botão da Central distribui todos os selecionados para 1 analista.
