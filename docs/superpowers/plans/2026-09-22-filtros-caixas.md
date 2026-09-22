# Filtros nas caixas (Fase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Disponibilizar os mesmos filtros de pesquisa (Serviço, Data Início/Fim, Status Tramitação, Nº do Processo, BAP + avançados) na Caixa do setor e na Caixa do analista (fila), reusando a lógica de filtros já existente, e paginar a fila no servidor.

**Architecture:** Reusar o `ProcessoQueryService` (já centraliza todos os filtros do SAPS, incluindo serviço, `analysis_status`, protocolo, BAP, datas sobre `protocoled_at`, bairro e categoria). Extrair um método `aplicarFiltros(Builder, array)` a partir do `filtered()` e chamá-lo no `CaixaSetorController::index` (sobre o escopo de setor/visão) e no `ProcessoController::fila` (que passa a paginar). No frontend, um único componente `processo-filtros.tsx` server-driven é usado nas duas caixas.

**Tech Stack:** Laravel 13, Inertia v3 + React 19, Tailwind 4, PHPUnit 12.

## Global Constraints

- Idioma: UI, mensagens, nomes de teste (`test_...`) e docs em pt-BR; código (métodos, variáveis) em inglês.
- TDD estrito: teste falhando antes do código de produção. Feature tests inspecionam props Inertia (padrão do projeto — a tela é 10-16).
- Auditoria de consulta (RN-002) preservada, registrando os filtros aplicados.
- Nenhum item novo de menu; `npx vitest run resources/js/navigation/gestao-nav.test.ts` deve continuar verde.
- Após alterar PHP: `vendor/bin/pint --dirty --format agent`.
- `per_page` reusa `PER_PAGE_OPTIONS = [10, 15, 25, 50]` e o padrão `ui.cnaes.per_page` (Settings), como nos controllers existentes.
- Filtros nas caixas: Serviço = `service_type_id` (param `servico`); Data Início/Fim = `data_de`/`data_ate` (whereDate sobre `protocoled_at`); Status Tramitação = `analysis_status`; Nº do Processo = `protocolo`; BAP = `bap`; Avançados = `nome`, `cnpj`, `bairro`, `categoria`. Não inventar filtro sem campo correspondente (ex.: "vencendo" não existe no service e fica fora).

---

### Task 1: Extrair `aplicarFiltros(Builder, array)` no ProcessoQueryService

Refactor DRY sem mudança de comportamento — coberto pelos testes existentes de consulta e fila. Isola os filtros de campo para reuso pela caixa do setor e pela fila.

**Files:**
- Modify: `app/Services/Analise/ProcessoQueryService.php`
- Test (existentes, regressão): `tests/Feature/Analise/ProcessoConsultaTest.php`, `tests/Feature/Analise/ProcessoFilaTest.php`

**Interfaces:**
- Produces: `ProcessoQueryService::aplicarFiltros(Builder $query, array $filtros): Builder` — aplica só os `when(...)` de filtro (sem `with`, sem ordem). Consumido pelas Tasks 2 e 3.

- [ ] **Step 1: Rodar os testes de regressão para registrar o verde atual**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoConsultaTest.php tests/Feature/Analise/ProcessoFilaTest.php`
Expected: PASS (baseline antes do refactor).

- [ ] **Step 2: Extrair o método `aplicarFiltros`**

Em `ProcessoQueryService`, criar o método público com o encadeamento de filtros hoje embutido em `filtered()` (linhas 66-85), operando sobre um `$query` recebido:

```php
/**
 * Aplica os filtros de campo (SAPS + analista + categoria) a um Builder já
 * escopado. Cada filtro só entra quando informado. Reutilizado pela consulta
 * (filtered), pela caixa do setor e pela fila do analista.
 *
 * @param  Builder<ViabilityRequest>  $query
 * @param  array<string, mixed>  $filtros
 * @return Builder<ViabilityRequest>
 */
public function aplicarFiltros(Builder $query, array $filtros): Builder
{
    return $query
        ->when($this->valor($filtros, 'grupo'), fn (Builder $q, string $grupo) => $this->aplicarGrupo($q, $grupo))
        ->when($this->valor($filtros, 'status'), fn (Builder $q, string $status) => $q->where('status', $status))
        ->when($this->valor($filtros, 'analysis_status'), fn (Builder $q, string $s) => $q->where('analysis_status', $s))
        ->when($this->valor($filtros, 'busca'), fn (Builder $q, string $v) => $this->aplicarBusca($q, $v))
        ->when($this->valor($filtros, 'fluxo'), fn (Builder $q, string $fluxo) => $this->aplicarFluxo($q, $fluxo))
        ->when($this->valor($filtros, 'protocolo'), fn (Builder $q, string $v) => $q->whereLike('protocol_number', "%{$v}%", caseSensitive: false))
        ->when($this->valor($filtros, 'bap'), fn (Builder $q, string $v) => $q->whereLike('external_reference', "%{$v}%", caseSensitive: false))
        ->when($this->valor($filtros, 'produto_tvl'), fn (Builder $q, string $v) => $q->whereHas('decision', fn ($d) => $d->whereLike('tvl_product_number', "%{$v}%", caseSensitive: false)))
        ->when($this->inteiro($filtros, 'servico'), fn (Builder $q, int $id) => $q->where('service_type_id', $id))
        ->when($this->inteiro($filtros, 'setor'), fn (Builder $q, int $id) => $q->where('sector_id', $id))
        ->when($this->inteiro($filtros, 'analista'), fn (Builder $q, int $id) => $q->where('assigned_user_id', $id))
        ->when($this->valor($filtros, 'inscricao'), fn (Builder $q, string $v) => $q->whereLike('property_registration', "%{$v}%", caseSensitive: false))
        ->when($this->valor($filtros, 'cep'), fn (Builder $q, string $v) => $q->whereLike('address_zip', "%{$v}%", caseSensitive: false))
        ->when($this->valor($filtros, 'logradouro'), fn (Builder $q, string $v) => $q->whereLike('address_street', "%{$v}%", caseSensitive: false))
        ->when($this->valor($filtros, 'bairro'), fn (Builder $q, string $v) => $q->whereLike('address_neighborhood', "%{$v}%", caseSensitive: false))
        ->when($this->valor($filtros, 'nome'), fn (Builder $q, string $v) => $this->aplicarNome($q, $v))
        ->when($this->valor($filtros, 'cnpj'), fn (Builder $q, string $v) => $q->whereHas('company', fn ($c) => $c->whereLike('cnpj', "%{$v}%", caseSensitive: false)))
        ->when($this->data($filtros, 'data_de'), fn (Builder $q, string $d) => $q->whereDate('protocoled_at', '>=', $d))
        ->when($this->data($filtros, 'data_ate'), fn (Builder $q, string $d) => $q->whereDate('protocoled_at', '<=', $d))
        ->when($this->categoria($filtros), fn (Builder $q, string $cat) => $this->aplicarCategoria($q, $cat));
}
```

- [ ] **Step 3: Fazer `filtered()` reusar `aplicarFiltros`**

Substituir o corpo de `filtered()` por:

```php
public function filtered(array $filtros): Builder
{
    $query = ViabilityRequest::query()
        ->with(['company', 'sector:id,name', 'assignedTo:id,name', 'decision', 'encaminhamentoAnalise', 'serviceType:id,name']);

    return $this->aplicarOrdem($this->aplicarFiltros($query, $filtros), $this->valor($filtros, 'ordem'));
}
```

- [ ] **Step 4: Rodar os testes de regressão**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoConsultaTest.php tests/Feature/Analise/ProcessoFilaTest.php`
Expected: PASS (comportamento idêntico).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/ProcessoQueryService.php
git commit -m "refactor: extrai aplicarFiltros do ProcessoQueryService para reuso"
```

---

### Task 2: Filtros na Caixa do setor (backend)

Aplica os filtros ao escopo da caixa (setor + `EmAnalise` + visão) e faz os contadores das abas respeitarem os filtros. Expõe as opções de Serviço e Status Tramitação no payload.

**Files:**
- Modify: `app/Http/Controllers/Gestao/CaixaSetorController.php`
- Test: `tests/Feature/Analise/CaixaSetorTest.php`

**Interfaces:**
- Consumes: `ProcessoQueryService::aplicarFiltros` (Task 1); `AnalysisStatus::options()`; `ViabilityServiceType` (`id`, `name`).
- Produces: props `filtros` (valores atuais, incl. `analysis_status`, `servico`, `protocolo`, `bap`, `data_de`, `data_ate`, `nome`, `cnpj`, `bairro`, `categoria`), `servicoOptions`, `analysisStatusOptions`, `categoriaOptions`. Consumido pela Task 5.

- [ ] **Step 1: Escrever o teste de filtro (Red)**

Adicionar a `CaixaSetorTest`:

```php
public function test_filtra_por_analysis_status_e_ajusta_contadores(): void
{
    $setor = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setor);

    $paraDistribuir = $this->processoNaCaixa($setor);
    $paraDistribuir->forceFill(['analysis_status' => \App\Enums\AnalysisStatus::ParaDistribuir])->save();

    $encaminhado = $this->processoNaCaixa($setor);
    $encaminhado->forceFill(['analysis_status' => \App\Enums\AnalysisStatus::Encaminhado])->save();

    $response = $this->actingAs($apoio, 'gestao')
        ->get('/gestao/caixa-setor?analysis_status=encaminhado')
        ->assertOk();

    $props = $response->viewData('page')['props'];
    $ids = collect($props['processos']['data'])->pluck('id')->all();

    $this->assertContains($encaminhado->id, $ids);
    $this->assertNotContains($paraDistribuir->id, $ids);
    $this->assertSame('encaminhado', $props['filtros']['analysis_status']);
    $this->assertSame(1, $props['contadores']['para_distribuir'], 'Os contadores das abas respeitam o filtro.');
}

public function test_filtra_por_servico_bap_e_protocolo(): void
{
    $setor = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setor);

    $servico = \App\Models\ViabilityServiceType::query()->first()
        ?? \App\Models\ViabilityServiceType::factory()->create();

    $alvo = $this->processoNaCaixa($setor);
    $alvo->forceFill(['service_type_id' => $servico->id, 'external_reference' => 'BAP-XYZ-1'])->save();

    $outro = $this->processoNaCaixa($setor);
    $outro->forceFill(['external_reference' => 'BAP-OUTRO-2'])->save();

    $response = $this->actingAs($apoio, 'gestao')
        ->get('/gestao/caixa-setor?bap=XYZ')
        ->assertOk();

    $ids = collect($response->viewData('page')['props']['processos']['data'])->pluck('id')->all();
    $this->assertContains($alvo->id, $ids);
    $this->assertNotContains($outro->id, $ids);
}

public function test_index_expoe_opcoes_de_servico_e_status_de_tramitacao(): void
{
    $setor = Sector::factory()->create();
    $apoio = $this->apoioDoSetor($setor);
    $this->processoNaCaixa($setor);

    $props = $this->actingAs($apoio, 'gestao')
        ->get('/gestao/caixa-setor')
        ->assertOk()
        ->viewData('page')['props'];

    $this->assertNotEmpty($props['analysisStatusOptions']);
    $this->assertArrayHasKey('servicoOptions', $props);
    $this->assertArrayHasKey('analysis_status', $props['filtros']);
}
```

- [ ] **Step 2: Rodar e ver falhar (Red)**

Run: `php artisan test --compact --filter='test_filtra_por_analysis_status_e_ajusta_contadores|test_filtra_por_servico_bap_e_protocolo|test_index_expoe_opcoes_de_servico_e_status_de_tramitacao' tests/Feature/Analise/CaixaSetorTest.php`
Expected: FAIL (chave `filtros.analysis_status`/`servicoOptions` ausente; filtro não aplicado).

- [ ] **Step 3: Implementar os filtros no controller**

Em `CaixaSetorController`, injetar `ProcessoQueryService` no construtor:

```php
public function __construct(
    private DistribuicaoService $distribuicao,
    private AuditService $audit,
    private \App\Services\Analise\ProcessoQueryService $processos,
) {}
```

No `index`, após montar `$base` (setor + status EmAnalise) e antes dos contadores, coletar os filtros e aplicá-los:

```php
$filtros = $this->filtrosDaCaixa($request);

$base = ViabilityRequest::query()
    ->whereIn('sector_id', $sectorIds)
    ->where('status', ViabilityRequestStatus::EmAnalise->value);

$base = $this->processos->aplicarFiltros($base, $filtros);

$contadores = [
    'para_distribuir' => (clone $base)->whereNull('assigned_user_id')->count(),
    'distribuidos' => (clone $base)->whereNotNull('assigned_user_id')->count(),
];
```

Adicionar o helper privado e as opções:

```php
/**
 * Filtros de pesquisa da caixa (subconjunto do SAPS), crus da query string —
 * o ProcessoQueryService normaliza e ignora os vazios.
 *
 * @return array<string, string>
 */
private function filtrosDaCaixa(Request $request): array
{
    $chaves = ['analysis_status', 'servico', 'protocolo', 'bap', 'data_de', 'data_ate', 'nome', 'cnpj', 'bairro', 'categoria'];
    $filtros = [];

    foreach ($chaves as $chave) {
        $filtros[$chave] = $request->string($chave)->toString();
    }

    return $filtros;
}

/**
 * @return list<array{value: string, label: string}>
 */
private function servicoOptions(): array
{
    return \App\Models\ViabilityServiceType::query()
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn ($servico): array => ['value' => (string) $servico->id, 'label' => $servico->name])
        ->all();
}

/**
 * @return list<array{value: string, label: string}>
 */
private function categoriaOptions(): array
{
    $opcoes = [];
    foreach (\App\Services\Analise\ProcessoQueryService::CATEGORIAS as $value => $label) {
        $opcoes[] = ['value' => $value, 'label' => $label];
    }

    return $opcoes;
}
```

No `Inertia::render`, incluir as props (mesclar `filtros` de per_page com os filtros de campo) e as opções:

```php
'filtros' => $filtros + ['per_page' => $perPage],
'servicoOptions' => $this->servicoOptions(),
'analysisStatusOptions' => \App\Enums\AnalysisStatus::options(),
'categoriaOptions' => $this->categoriaOptions(),
```

Incluir os filtros preenchidos na auditoria de consulta:

```php
$this->audit->log('analise', 'consulta-caixa', 'Consulta da caixa do setor', [
    'setores' => $sectorIds->all(),
    'filtros' => array_filter($filtros, fn ($v): bool => $v !== ''),
]);
```

- [ ] **Step 4: Rodar e ver passar (Green)**

Run: `php artisan test --compact --filter='test_filtra_por_analysis_status_e_ajusta_contadores|test_filtra_por_servico_bap_e_protocolo|test_index_expoe_opcoes_de_servico_e_status_de_tramitacao' tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS.

- [ ] **Step 5: Rodar o arquivo inteiro (sem regressão)**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/CaixaSetorController.php tests/Feature/Analise/CaixaSetorTest.php
git commit -m "feat: adiciona filtros de pesquisa na caixa do setor"
```

---

### Task 3: Paginação server-side + filtros na fila (backend)

A fila passa a paginar no servidor (igual à caixa do setor) e a aceitar os mesmos filtros. KPIs continuam sobre o total do escopo.

**Files:**
- Modify: `app/Http/Controllers/Gestao/ProcessoController.php`
- Test: `tests/Feature/Analise/ProcessoFilaTest.php`

**Interfaces:**
- Consumes: `ProcessoQueryService::aplicarFiltros`, `::fila`, `::contadores`, `::visaoSetor`.
- Produces: prop `processos` agora PAGINADA (`{ data, links, from, to, total }`); props `filtros`, `perPageOptions`, `servicoOptions`, `analysisStatusOptions`, `categoriaOptions`. Consumido pela Task 6.

- [ ] **Step 1: Atualizar o helper e escrever o teste (Red)**

No `ProcessoFilaTest`, atualizar `filaProps` para o formato paginado e adicionar testes de filtro. Substituir o helper:

```php
/**
 * @return array<string, mixed>
 */
private function filaProps(User $user, string $modo, string $query = ''): array
{
    $sufixo = $query === '' ? '' : "&{$query}";

    return $this->actingAs($user, 'gestao')
        ->get("/gestao/processos/fila?modo={$modo}{$sufixo}")
        ->assertOk()
        ->viewData('page')['props'];
}
```

Ajustar os testes existentes que leem `$props['processos']` para ler `$props['processos']['data']` (em `test_fila_meus_lista_apenas_do_analista_ordenada_por_prazo`, `test_cada_item_traz_o_semaforo_on_the_fly`, `test_fila_setor_respeita_o_vinculo_do_analista`). Ex.:

```php
$ids = collect($props['processos']['data'])->pluck('id')->all();
```

E o semáforo:

```php
$porId = collect($props['processos']['data'])->keyBy('id');
```

Adicionar o teste de filtro:

```php
public function test_fila_filtra_por_bap(): void
{
    $setor = Sector::factory()->create();
    $analista = $this->analistaDoSetor($setor);

    $alvo = $this->processo(['assigned_user_id' => $analista->id]);
    $alvo->forceFill(['external_reference' => 'BAP-FILA-1'])->save();

    $outro = $this->processo(['assigned_user_id' => $analista->id]);
    $outro->forceFill(['external_reference' => 'BAP-FILA-2'])->save();

    $props = $this->filaProps($analista, 'meus', 'bap=FILA-1');
    $ids = collect($props['processos']['data'])->pluck('id')->all();

    $this->assertContains($alvo->id, $ids);
    $this->assertNotContains($outro->id, $ids);
}

public function test_fila_pagina_no_servidor(): void
{
    $setor = Sector::factory()->create();
    $analista = $this->analistaDoSetor($setor);

    foreach (range(1, 3) as $i) {
        $this->processo(['assigned_user_id' => $analista->id]);
    }

    $props = $this->filaProps($analista, 'meus', 'per_page=10');

    $this->assertArrayHasKey('data', $props['processos']);
    $this->assertArrayHasKey('links', $props['processos']);
    $this->assertSame(3, $props['processos']['total']);
}
```

- [ ] **Step 2: Rodar e ver falhar (Red)**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoFilaTest.php`
Expected: FAIL (`processos` ainda é array simples; `data`/`total` ausentes).

- [ ] **Step 3: Implementar paginação + filtros no `fila()`**

Substituir o corpo de `ProcessoController::fila` por:

```php
public function fila(Request $request): Response
{
    $modo = $request->string('modo')->toString();
    $modo = in_array($modo, ['meus', 'setor'], true) ? $modo : 'meus';

    $user = $request->user();
    $filtros = $this->filtros($request);
    $perPage = $this->perPage($request);

    $processos = $this->processos->aplicarFiltros($this->processos->fila($user, $modo), $filtros)
        ->paginate($perPage)
        ->withQueryString()
        ->through(fn (ViabilityRequest $processo): array => (new ProcessoResource($processo))->resolve());

    $this->audit->log('analise', 'consulta-fila', 'Consulta da fila de trabalho do analista', [
        'modo' => $modo,
        'filtros' => $this->filtrosPreenchidos($filtros),
    ]);

    return Inertia::render('gestao/processos/fila', [
        'modo' => $modo,
        'processos' => $processos,
        'contadores' => $this->processos->contadores($user, $modo),
        'visaoSetor' => $user->can('distribuir-processos') ? $this->processos->visaoSetor($user) : null,
        'filtros' => $filtros + ['per_page' => $perPage],
        'perPageOptions' => self::PER_PAGE_OPTIONS,
        'servicoOptions' => $this->servicoOptions(),
        'analysisStatusOptions' => AnalysisStatus::options(),
        'categoriaOptions' => $this->categoriaOptions(),
    ]);
}
```

Adicionar `servicoOptions()` ao controller (o `categoriaOptions()` e `filtros()`/`filtrosPreenchidos()`/`perPage()` já existem):

```php
/**
 * @return list<array{value: string, label: string}>
 */
private function servicoOptions(): array
{
    return \App\Models\ViabilityServiceType::query()
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn ($servico): array => ['value' => (string) $servico->id, 'label' => $servico->name])
        ->all();
}
```

- [ ] **Step 4: Rodar e ver passar (Green)**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoFilaTest.php`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/ProcessoController.php tests/Feature/Analise/ProcessoFilaTest.php
git commit -m "feat: pagina a fila do analista no servidor e aceita filtros"
```

---

### Task 4: Componente de filtros compartilhado (frontend)

Componente server-driven reutilizado nas duas caixas: linha com Serviço, Data Início/Fim, Status Tramitação, Nº do Processo, BAP + disclosure "Filtros avançados" (Empresa/requerente, CNPJ, Bairro, Categoria) + botões Aplicar/Limpar.

**Files:**
- Create: `resources/js/components/analise/processo-filtros.tsx`

**Interfaces:**
- Produces: `export default function ProcessoFiltros(props: ProcessoFiltrosProps)` e `export interface ProcessoFiltrosValores` com as chaves `analysis_status, servico, protocolo, bap, data_de, data_ate, nome, cnpj, bairro, categoria`. Consumido pelas Tasks 5 e 6.

- [ ] **Step 1: Criar o componente**

```tsx
import { type FormEvent, useState } from 'react';
import Input from '@/components/form/input';
import Label from '@/components/form/label';
import Select from '@/components/form/select';
import Button from '@/components/ui/button';

interface SelectOption {
    value: string;
    label: string;
}

export interface ProcessoFiltrosValores {
    analysis_status: string;
    servico: string;
    protocolo: string;
    bap: string;
    data_de: string;
    data_ate: string;
    nome: string;
    cnpj: string;
    bairro: string;
    categoria: string;
}

interface ProcessoFiltrosProps {
    valores: ProcessoFiltrosValores;
    servicoOptions: SelectOption[];
    analysisStatusOptions: SelectOption[];
    categoriaOptions: SelectOption[];
    onAplicar: (valores: ProcessoFiltrosValores) => void;
    onLimpar: () => void;
}

export const FILTROS_VAZIOS: ProcessoFiltrosValores = {
    analysis_status: '',
    servico: '',
    protocolo: '',
    bap: '',
    data_de: '',
    data_ate: '',
    nome: '',
    cnpj: '',
    bairro: '',
    categoria: '',
};

/**
 * Filtros de pesquisa das caixas (setor e analista). Server-driven: o pai
 * decide a rota da visita; aqui só se coleta e envia os valores.
 */
export default function ProcessoFiltros({
    valores,
    servicoOptions,
    analysisStatusOptions,
    categoriaOptions,
    onAplicar,
    onLimpar,
}: ProcessoFiltrosProps) {
    const [form, setForm] = useState<ProcessoFiltrosValores>(valores);
    const [avancadosAbertos, setAvancadosAbertos] = useState(false);

    function definir(chave: keyof ProcessoFiltrosValores, valor: string) {
        setForm((anterior) => ({ ...anterior, [chave]: valor }));
    }

    function aplicar(evento: FormEvent) {
        evento.preventDefault();
        onAplicar(form);
    }

    function limpar() {
        setForm(FILTROS_VAZIOS);
        onLimpar();
    }

    return (
        <form onSubmit={aplicar} className="space-y-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <Label htmlFor="filtro-servico">Serviço</Label>
                    <Select id="filtro-servico" value={form.servico} onChange={(v) => definir('servico', v)} placeholder="Todos" options={servicoOptions} />
                </div>
                <div>
                    <Label htmlFor="filtro-data-de">Data início</Label>
                    <Input id="filtro-data-de" type="date" value={form.data_de} onChange={(e) => definir('data_de', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-data-ate">Data fim</Label>
                    <Input id="filtro-data-ate" type="date" value={form.data_ate} onChange={(e) => definir('data_ate', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-analysis-status">Status tramitação</Label>
                    <Select id="filtro-analysis-status" value={form.analysis_status} onChange={(v) => definir('analysis_status', v)} placeholder="Todos" options={analysisStatusOptions} />
                </div>
                <div>
                    <Label htmlFor="filtro-protocolo">Número do processo</Label>
                    <Input id="filtro-protocolo" value={form.protocolo} onChange={(e) => definir('protocolo', e.target.value)} />
                </div>
                <div>
                    <Label htmlFor="filtro-bap">BAP</Label>
                    <Input id="filtro-bap" value={form.bap} onChange={(e) => definir('bap', e.target.value)} />
                </div>
            </div>

            {avancadosAbertos && (
                <div className="grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-gray-800">
                    <div>
                        <Label htmlFor="filtro-nome">Empresa / requerente</Label>
                        <Input id="filtro-nome" value={form.nome} onChange={(e) => definir('nome', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="filtro-cnpj">CNPJ</Label>
                        <Input id="filtro-cnpj" value={form.cnpj} onChange={(e) => definir('cnpj', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="filtro-bairro">Bairro</Label>
                        <Input id="filtro-bairro" value={form.bairro} onChange={(e) => definir('bairro', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="filtro-categoria">Categoria</Label>
                        <Select id="filtro-categoria" value={form.categoria} onChange={(v) => definir('categoria', v)} placeholder="Todas" options={categoriaOptions} />
                    </div>
                </div>
            )}

            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" variant="primary">Pesquisar</Button>
                <Button type="button" variant="outline" onClick={limpar}>Limpar</Button>
                <button
                    type="button"
                    onClick={() => setAvancadosAbertos((aberto) => !aberto)}
                    className="text-theme-sm font-medium text-brand-600 underline dark:text-brand-400"
                >
                    {avancadosAbertos ? 'Ocultar filtros avançados' : 'Filtros avançados'}
                </button>
            </div>
        </form>
    );
}
```

- [ ] **Step 2: Verificar que compila (build)**

Run: `npm run build`
Expected: build sem erros de TypeScript no novo arquivo.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/analise/processo-filtros.tsx
git commit -m "feat: componente de filtros compartilhado das caixas"
```

---

### Task 5: Integrar filtros na Caixa do setor (frontend)

**Files:**
- Modify: `resources/js/pages/gestao/caixa-setor/index.tsx`

**Interfaces:**
- Consumes: `ProcessoFiltros`, `ProcessoFiltrosValores`, `FILTROS_VAZIOS` (Task 4); props `filtros`, `servicoOptions`, `analysisStatusOptions`, `categoriaOptions` (Task 2).

- [ ] **Step 1: Estender as props e importar o componente**

No topo, importar:

```tsx
import ProcessoFiltros, { type ProcessoFiltrosValores } from '@/components/analise/processo-filtros';
```

Estender `CaixaSetorIndexProps.filtros` e adicionar as opções:

```tsx
    filtros: ProcessoFiltrosValores & { per_page: number };
    servicoOptions: { value: string; label: string }[];
    analysisStatusOptions: { value: string; label: string }[];
    categoriaOptions: { value: string; label: string }[];
```

- [ ] **Step 2: Enviar os filtros na navegação server-driven**

Ajustar `navegar` para carregar os filtros de campo além de `visao`/`per_page`:

```tsx
function navegar(params: { visao?: Visao; per_page?: number; filtros?: ProcessoFiltrosValores }) {
    setSelecionados([]);
    const filtrosAtuais = params.filtros ?? filtrosDeCampo;
    router.get(
        '/gestao/caixa-setor',
        {
            visao: params.visao ?? visao,
            per_page: params.per_page ?? filtros.per_page,
            ...Object.fromEntries(Object.entries(filtrosAtuais).filter(([, v]) => v !== '')),
        },
        { preserveScroll: true, preserveState: false },
    );
}
```

Onde `filtrosDeCampo` deriva de `filtros` (sem `per_page`):

```tsx
const { per_page: _perPage, ...filtrosDeCampo } = filtros;
```

- [ ] **Step 3: Renderizar o componente acima das abas**

Antes do bloco `role="tablist"`, inserir:

```tsx
<ProcessoFiltros
    valores={filtrosDeCampo}
    servicoOptions={servicoOptions}
    analysisStatusOptions={analysisStatusOptions}
    categoriaOptions={categoriaOptions}
    onAplicar={(valores) => navegar({ filtros: valores })}
    onLimpar={() => navegar({ filtros: { ...filtrosDeCampo, analysis_status: '', servico: '', protocolo: '', bap: '', data_de: '', data_ate: '', nome: '', cnpj: '', bairro: '', categoria: '' } })}
/>
```

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: sem erros.

- [ ] **Step 5: Verificar props no backend (sem regressão)**

Run: `php artisan test --compact tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/gestao/caixa-setor/index.tsx
git commit -m "feat: filtros de pesquisa na tela da caixa do setor"
```

---

### Task 6: Paginação + filtros na fila (frontend)

**Files:**
- Modify: `resources/js/pages/gestao/processos/fila.tsx`

**Interfaces:**
- Consumes: `ProcessoFiltros` (Task 4); props paginada `processos` + `filtros`/opções (Task 3); `Pagination`, `PerPageSelect`.

- [ ] **Step 1: Atualizar as props e imports**

Trocar `processos: ProcessoItem[]` por paginado e importar os componentes:

```tsx
import Pagination, { type PaginationLink } from '@/components/ui/pagination';
import PerPageSelect from '@/components/ui/data-table/per-page-select';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ProcessoFiltros, { type ProcessoFiltrosValores } from '@/components/analise/processo-filtros';
```

```tsx
interface Paginado<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface FilaProps {
    modo: 'meus' | 'setor';
    processos: Paginado<ProcessoItem>;
    contadores: Contadores;
    visaoSetor: VisaoSetor | null;
    filtros: ProcessoFiltrosValores & { per_page: number };
    perPageOptions: number[];
    servicoOptions: { value: string; label: string }[];
    analysisStatusOptions: { value: string; label: string }[];
    categoriaOptions: { value: string; label: string }[];
}
```

- [ ] **Step 2: Navegação server-driven com filtros e per_page**

Adicionar helper e derivar filtros de campo:

```tsx
const { per_page: _perPage, ...filtrosDeCampo } = filtros;

function navegar(params: { modo?: 'meus' | 'setor'; per_page?: number; filtros?: ProcessoFiltrosValores }) {
    const filtrosAtuais = params.filtros ?? filtrosDeCampo;
    router.get(
        '/gestao/processos/fila',
        {
            modo: params.modo ?? modo,
            per_page: params.per_page ?? filtros.per_page,
            ...Object.fromEntries(Object.entries(filtrosAtuais).filter(([, v]) => v !== '')),
        },
        { preserveScroll: true, preserveState: false },
    );
}
```

Trocar `trocarModo` para usar `navegar({ modo: proximo })`.

- [ ] **Step 3: Renderizar filtros, iterar `processos.data` e paginação**

Inserir `<ProcessoFiltros .../>` acima das abas (mesmos props da Task 5, com `onAplicar={(v) => navegar({ filtros: v })}`). Trocar `rows={processos}` por `rows={processos.data}`. Após o `DataTable`, adicionar:

```tsx
<div className="flex items-center justify-end">
    <PerPageSelect value={filtros.per_page} options={perPageOptions} onChange={(pp) => navegar({ per_page: pp })} />
</div>
<Pagination
    links={processos.links}
    meta={{ from: processos.from, to: processos.to, total: processos.total }}
/>
```

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: sem erros.

- [ ] **Step 5: Verificar props no backend**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoFilaTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/gestao/processos/fila.tsx
git commit -m "feat: filtros e paginacao na caixa do analista"
```

---

### Task 7: Verificação final da fase

**Files:** nenhum (verificação).

- [ ] **Step 1: Navegação continua íntegra**

Run: `npx vitest run resources/js/navigation/gestao-nav.test.ts`
Expected: PASS.

- [ ] **Step 2: Suíte de análise dos arquivos tocados**

Run: `php artisan test --compact tests/Feature/Analise/ProcessoConsultaTest.php tests/Feature/Analise/ProcessoFilaTest.php tests/Feature/Analise/CaixaSetorTest.php`
Expected: PASS.

- [ ] **Step 3: Build final**

Run: `npm run build`
Expected: sem erros.

- [ ] **Step 4: Pint (garantia)**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sem alterações pendentes (ou aplica e commita).

---

## Self-Review

**Spec coverage (Fase 1):**
- Filtros Serviço/Data/Status Tramitação/Nº Processo/BAP/Avançados nas duas caixas → Tasks 2, 5 (setor) e 3, 6 (fila), com componente compartilhado na Task 4.
- Consistência entre as caixas → mesmo `aplicarFiltros` (Task 1) e mesmo componente (Task 4).
- Paginação server-side da fila → Task 3 (backend) e 6 (frontend).
- Auditoria com filtros → Tasks 2 e 3.
- Sem item de menu novo → Task 7.

**Placeholder scan:** sem TBD/TODO; todo passo de código traz o código.

**Type consistency:** `aplicarFiltros(Builder, array): Builder` usado igual nas Tasks 2 e 3; `ProcessoFiltrosValores` (10 chaves) idêntico nas Tasks 4/5/6; props `servicoOptions`/`analysisStatusOptions`/`categoriaOptions` iguais no backend (Tasks 2/3) e frontend (Tasks 5/6); `processos` paginado (`data/links/from/to/total`) coerente entre Task 3 e Task 6.

**Notas de risco:**
- `ViabilityServiceType::factory()` é usada num teste (Task 2) — se a factory não existir, usar `ViabilityServiceType::query()->firstOrFail()` a partir do seed, ou criar via `ViabilityServiceType::create(['name' => 'Serviço teste'])`. Confirmar na execução da Task 2.
- Ajuste dos testes existentes da fila (Task 3) para o formato paginado é obrigatório e faz parte do Red da própria task.
