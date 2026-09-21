# Propagação Anual da Tabela TLL — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gerar o exercício seguinte da tabela TLL propagando os valores pelo fator do decreto (IPCA), como rascunho versionado, com publicação por quatro olhos — sem cadastro manual linha a linha.

**Architecture:** O exercício TLL é uma versão de regra: novo `RuleDomain::TllValores` reusa `rule_versions` + `RuleVersionService` (quatro olhos). Um serviço `TllPropagacaoExercicio` abre o rascunho e clona as linhas ativas de `tll_valores` aplicando o fator; a linha ganha `rule_version_id`. Publicar promove o rascunho a vigente. Um comando agendado alerta gestores quando falta versão vigente. O cálculo do DAM (`TllCalculoService`) passa a exigir a versão vigente do exercício.

**Tech Stack:** Laravel 13, Eloquent, Spatie Activitylog (`HasAuditoria`), `RuleVersionService`, `NotificationDispatcher`, PHPUnit, Inertia/React (tela existente).

## Global Constraints

- TDD estrito: teste falhando antes de cada implementação (Red-Green-Refactor).
- `vendor/bin/pint --dirty --format agent` após alterar PHP.
- Auditoria RN-002 em toda gravação (HasAuditoria no model; AuditService explícito no fluxo).
- Quatro olhos em domínio sensível: publicador ≠ autor (`FourEyesViolationException`).
- Anti-fachada: sem scraping da SEFAZ, sem publicação automática, sem valor inventado.
- Nenhum item novo no menu (reuso da tela `/gestao/tll`, permissão `manter-parametros`).
- Mensagens e commits em pt-BR; código em inglês.
- Suíte roda em SQLite (migration portável, sem geometria).

---

### Task 1: Domínio `TllValores` sensível + coluna `rule_version_id` em `tll_valores`

**Files:**
- Modify: `app/Enums/RuleDomain.php`
- Modify: `app/Models/TllValor.php`
- Create: `database/migrations/2026_09_21_000000_add_rule_version_id_to_tll_valores_table.php`
- Test: `tests/Unit/Analise/TllValorTest.php`

**Interfaces:**
- Produces: `RuleDomain::TllValores` (value `'tll_valores'`, `isSensitive() === true`, label `'Tabela de valores TLL por exercício'`); `tll_valores.rule_version_id` (nullable FK → `rule_versions.id`); `TllValor::versao()` BelongsTo.

- [ ] **Step 1: Teste falhando — domínio existe e é sensível**

```php
// tests/Unit/Analise/TllValorTest.php (acrescentar)
public function test_dominio_tll_valores_existe_e_e_sensivel(): void
{
    $this->assertSame('tll_valores', RuleDomain::TllValores->value);
    $this->assertTrue(RuleDomain::TllValores->isSensitive());
    $this->assertSame('Tabela de valores TLL por exercício', RuleDomain::TllValores->label());
}
```

- [ ] **Step 2: Rodar e confirmar falha**

Run: `php artisan test --compact --filter=test_dominio_tll_valores_existe_e_e_sensivel`
Expected: FAIL — `RuleDomain::TllValores` indefinido.

- [ ] **Step 3: Implementar o domínio**

```php
// app/Enums/RuleDomain.php — acrescentar o case
    // Tabela de valores TLL por exercício (Lei 7.186/2006, Anexo IV) — dado
    // versionado que define valor de tributo (DAM). Sensível: quatro olhos.
    case TllValores = 'tll_valores';
```

Em `label()`: `self::TllValores => 'Tabela de valores TLL por exercício',`
Em `isSensitive()`: acrescentar `self::TllValores => true,` ao grupo `true`.

- [ ] **Step 4: Rodar e confirmar passagem**

Run: `php artisan test --compact --filter=test_dominio_tll_valores_existe_e_e_sensivel`
Expected: PASS

- [ ] **Step 5: Teste falhando — linha referencia a versão**

```php
public function test_linha_tll_referencia_a_versao_do_exercicio(): void
{
    $versao = RuleVersion::factory()->create(['domain' => RuleDomain::TllValores, 'version' => '2027']);
    $valor = TllValor::factory()->create(['exercicio' => 2027, 'rule_version_id' => $versao->id]);

    $this->assertTrue($valor->versao->is($versao));
}
```

- [ ] **Step 6: Rodar e confirmar falha** (coluna/relacionamento inexistente)

- [ ] **Step 7: Migration + model**

```php
// database/migrations/2026_09_21_000000_add_rule_version_id_to_tll_valores_table.php
public function up(): void
{
    Schema::table('tll_valores', function (Blueprint $table) {
        $table->foreignId('rule_version_id')->nullable()->constrained('rule_versions')->nullOnDelete();
    });
}
```

```php
// app/Models/TllValor.php — fillable + relacionamento
//   acrescentar 'rule_version_id' ao #[Fillable([...])]
    public function versao(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class, 'rule_version_id');
    }
```

- [ ] **Step 8: Rodar e confirmar passagem**

Run: `php artisan test --compact --filter=TllValorTest`
Expected: PASS

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/RuleDomain.php app/Models/TllValor.php database/migrations tests/Unit/Analise/TllValorTest.php
git commit -m "feat: adiciona dominio TLL sensivel e versao na linha de valor"
```

---

### Task 2: Serviço `TllPropagacaoExercicio` — gerar/regerar rascunho

**Files:**
- Create: `app/Services/Analise/TllPropagacaoExercicio.php`
- Test: `tests/Unit/Analise/TllPropagacaoExercicioTest.php`

**Interfaces:**
- Consumes: `RuleVersionService::openDraft`, `RuleDomain::TllValores`, `TllValor`.
- Produces: `TllPropagacaoExercicio::propagar(int $origem, int $destino, string $fator, string $decreto, int $autorId): RuleVersion` — lança `DomainException` quando origem sem linha ativa ou destino já vigente.

- [ ] **Step 1: Teste falhando — propaga linhas ativas aplicando o fator**

```php
public function test_propaga_linhas_ativas_aplicando_o_fator(): void
{
    TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'valor' => '554.32', 'taxa_servico' => '10.00', 'active' => true]);
    TllValor::factory()->create(['codigo_tll' => '9.99', 'exercicio' => 2026, 'valor' => '100.00', 'active' => false]); // inativo não propaga

    $versao = app(TllPropagacaoExercicio::class)->propagar(2026, 2027, '1.0446', 'Decreto nº 41.304/2025', $autorId);

    $this->assertSame(RuleVersionStatus::Rascunho, $versao->status);
    $this->assertSame('Decreto nº 41.304/2025', $versao->source);
    $novo = TllValor::query()->where('codigo_tll', '2.02')->where('exercicio', 2027)->sole();
    $this->assertSame('578.99', (string) $novo->valor);       // 554.32 * 1.0446
    $this->assertSame('10.45', (string) $novo->taxa_servico); // 10.00 * 1.0446
    $this->assertTrue($novo->versao->is($versao));
    $this->assertDatabaseMissing('tll_valores', ['codigo_tll' => '9.99', 'exercicio' => 2027]);
}
```

- [ ] **Step 2: Rodar e confirmar falha** (serviço inexistente)

- [ ] **Step 3: Implementar `propagar`**

```php
// app/Services/Analise/TllPropagacaoExercicio.php
public function propagar(int $origem, int $destino, string $fator, string $decreto, int $autorId): RuleVersion
{
    $fatorFloat = (float) $fator;
    if ($fatorFloat <= 0 || $fatorFloat > 2) {
        throw new DomainException('Fator de atualização inválido.');
    }

    $ativas = TllValor::query()->active()->where('exercicio', $origem)->get();
    if ($ativas->isEmpty()) {
        throw new DomainException("Nenhum valor ativo no exercício {$origem} para propagar.");
    }

    $versao = $this->ruleVersions->openDraft(RuleDomain::TllValores, (string) $destino, $decreto, $autorId);
    if ($versao->status === RuleVersionStatus::Vigente) {
        throw new DomainException("O exercício {$destino} já está publicado e não pode ser regerado.");
    }

    DB::transaction(function () use ($ativas, $destino, $fatorFloat, $versao): void {
        foreach ($ativas as $linha) {
            TllValor::query()->updateOrCreate(
                ['codigo_tll' => $linha->codigo_tll, 'exercicio' => $destino],
                [
                    'valor' => number_format((float) $linha->valor * $fatorFloat, 2, '.', ''),
                    'taxa_servico' => number_format((float) $linha->taxa_servico * $fatorFloat, 2, '.', ''),
                    'codigo_tll_sefaz' => $linha->codigo_tll_sefaz,
                    'codigo_servico_sefaz' => $linha->codigo_servico_sefaz,
                    'servico_sefaz' => $linha->servico_sefaz,
                    'active' => true,
                    'rule_version_id' => $versao->id,
                ],
            );
        }
    });

    $this->audit->log('regras', 'tll-exercicio-gerado', "Exercício {$destino} da TLL gerado a partir de {$origem}", [
        'origem' => $origem, 'destino' => $destino, 'fator' => $fator, 'decreto' => $decreto, 'linhas' => $ativas->count(),
    ], 'sucesso', $versao);

    return $versao;
}
```

- [ ] **Step 4: Rodar e confirmar passagem**

- [ ] **Step 5: Testes de borda (origem vazia, destino vigente, fator inválido, regerar rascunho sem duplicar)** — um `test_` por caso, cada um Red→Green.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/TllPropagacaoExercicio.php tests/Unit/Analise/TllPropagacaoExercicioTest.php
git commit -m "feat: propaga exercicio da TLL pelo fator do decreto em rascunho"
```

---

### Task 3: Publicação por quatro olhos do exercício

**Files:**
- Create: `app/Services/Analise/TllPublicacaoExercicio.php`
- Test: `tests/Unit/Analise/TllPublicacaoExercicioTest.php`

**Interfaces:**
- Consumes: `RuleVersionService::publish`, `RuleDomain::TllValores`.
- Produces: `TllPublicacaoExercicio::publicar(int $exercicio, int $publicadorId): RuleVersion` — lança `FourEyesViolationException` quando publicador = autor; `DomainException` quando não há rascunho.

- [ ] **Step 1: Teste falhando — publicador igual ao autor é rejeitado**

```php
public function test_publicador_igual_ao_autor_e_rejeitado(): void
{
    $versao = RuleVersion::factory()->create(['domain' => RuleDomain::TllValores, 'version' => '2027', 'status' => RuleVersionStatus::Rascunho, 'created_by' => $autorId]);

    $this->expectException(FourEyesViolationException::class);
    app(TllPublicacaoExercicio::class)->publicar(2027, $autorId);
}
```

- [ ] **Step 2: Rodar e confirmar falha**

- [ ] **Step 3: Implementar `publicar`**

```php
public function publicar(int $exercicio, int $publicadorId): RuleVersion
{
    $rascunho = RuleVersion::query()
        ->where('domain', RuleDomain::TllValores->value)
        ->where('version', (string) $exercicio)
        ->where('status', RuleVersionStatus::Rascunho->value)
        ->first();

    if ($rascunho === null) {
        throw new DomainException("Não há rascunho do exercício {$exercicio} para publicar.");
    }

    return $this->ruleVersions->publish($rascunho, $publicadorId);
}
```

- [ ] **Step 4: Teste — publicador distinto promove a vigente e fecha a anterior**

```php
public function test_publicador_distinto_promove_e_fecha_anterior(): void
{
    $anterior = RuleVersion::factory()->create(['domain' => RuleDomain::TllValores, 'version' => '2026', 'status' => RuleVersionStatus::Vigente, 'valid_to' => null]);
    $rascunho = RuleVersion::factory()->create(['domain' => RuleDomain::TllValores, 'version' => '2027', 'status' => RuleVersionStatus::Rascunho, 'created_by' => $autorId]);

    $vigente = app(TllPublicacaoExercicio::class)->publicar(2027, $outroId);

    $this->assertSame(RuleVersionStatus::Vigente, $vigente->status);
    $this->assertSame(RuleVersionStatus::Substituida, $anterior->fresh()->status);
}
```

- [ ] **Step 5: Rodar e confirmar passagem**

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/TllPublicacaoExercicio.php tests/Unit/Analise/TllPublicacaoExercicioTest.php
git commit -m "feat: publica exercicio da TLL por quatro olhos"
```

---

### Task 4: Cálculo exige a versão vigente do exercício

**Files:**
- Modify: `app/Services/Analise/TllCalculoService.php`
- Test: `tests/Unit/Analise/TllCalculoServiceTest.php`

**Interfaces:**
- Consumes: `RuleVersion::vigente(RuleDomain::TllValores)`, `TllValor`.
- Produces: `calcular()` só considera linha cujo `exercicio` tem versão **vigente**; sem versão vigente → `null` (pendente).

- [ ] **Step 1: Teste falhando — exercício sem versão vigente degrada para pendente**

```php
public function test_exercicio_sem_versao_vigente_degrada_para_pendente(): void
{
    TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2027, 'valor' => '554.32', 'active' => true]);
    // sem rule_versions vigente para 2027

    $resultado = app(TllCalculoService::class)->calcular([['codigo_tll' => '2.02']], 2027);

    $this->assertNull($resultado);
}
```

- [ ] **Step 2: Rodar e confirmar falha** (hoje retorna valor — rascunho vaza)

- [ ] **Step 3: Implementar o filtro de versão vigente**

```php
// em calcular(), antes do loop:
$versaoVigente = RuleVersion::query()->vigente(RuleDomain::TllValores)->where('version', (string) $exercicio)->exists();
if (! $versaoVigente) {
    return null;
}
```

- [ ] **Step 4: Ajustar os testes existentes de `calcular` para criar a versão vigente do exercício** (helper `private function publicarExercicio(int $ano): void` no teste). Rodar a suíte do service.

Run: `php artisan test --compact tests/Unit/Analise/TllCalculoServiceTest.php`
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/TllCalculoService.php tests/Unit/Analise/TllCalculoServiceTest.php
git commit -m "feat: calculo do DAM so usa exercicio com tabela vigente"
```

---

### Task 5: Endpoints HTTP — gerar e publicar exercício

**Files:**
- Modify: `app/Http/Controllers/Gestao/TllValorController.php`
- Create: `app/Http/Requests/Gestao/PropagacaoTllRequest.php`
- Modify: `routes/gestao.php:549-554`
- Test: `tests/Feature/Analise/TllValorControllerTest.php`

**Interfaces:**
- Consumes: `TllPropagacaoExercicio::propagar`, `TllPublicacaoExercicio::publicar`.
- Produces: `POST /gestao/tll/exercicios` (gerar) e `POST /gestao/tll/exercicios/{exercicio}/publicar` (publicar). Rotas estáticas ANTES do wildcard `{tllValor}`.

- [ ] **Step 1: Teste falhando — gerar exercício cria rascunho e clona**

```php
public function test_gerar_exercicio_cria_rascunho_e_clona_linhas(): void
{
    $admin = User::factory()->create()->givePermissionTo('manter-parametros');
    TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'valor' => '554.32', 'active' => true]);

    $this->actingAs($admin)->post('/gestao/tll/exercicios', [
        'exercicio_origem' => 2026, 'exercicio_destino' => 2027, 'fator' => '1.0446', 'decreto' => 'Decreto nº 41.304/2025',
    ])->assertRedirect();

    $this->assertDatabaseHas('rule_versions', ['domain' => 'tll_valores', 'version' => '2027', 'status' => 'rascunho']);
    $this->assertDatabaseHas('tll_valores', ['codigo_tll' => '2.02', 'exercicio' => 2027]);
}
```

- [ ] **Step 2: Rodar e confirmar falha** (rota inexistente)

- [ ] **Step 3: Request + rotas + actions no controller**

```php
// app/Http/Requests/Gestao/PropagacaoTllRequest.php
public function rules(): array
{
    return [
        'exercicio_origem' => ['required', 'integer', 'min:2000', 'max:2200'],
        'exercicio_destino' => ['required', 'integer', 'min:2000', 'max:2200', 'gt:exercicio_origem'],
        'fator' => ['required', 'numeric', 'gt:0', 'lte:2'],
        'decreto' => ['required', 'string', 'max:120'],
    ];
}
```

```php
// routes/gestao.php — dentro do grupo tll., ANTES do wildcard {tllValor}
Route::post('exercicios', [TllValorController::class, 'gerarExercicio'])->name('exercicios.store');
Route::post('exercicios/{exercicio}/publicar', [TllValorController::class, 'publicarExercicio'])->name('exercicios.publicar');
```

```php
// TllValorController
public function gerarExercicio(PropagacaoTllRequest $request): RedirectResponse
{
    try {
        app(TllPropagacaoExercicio::class)->propagar(
            (int) $request->validated('exercicio_origem'),
            (int) $request->validated('exercicio_destino'),
            (string) $request->validated('fator'),
            (string) $request->validated('decreto'),
            (int) $request->user()->id,
        );
    } catch (DomainException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('status', 'Exercício gerado em rascunho. Revise e publique com um segundo usuário.');
}

public function publicarExercicio(Request $request, int $exercicio): RedirectResponse
{
    try {
        app(TllPublicacaoExercicio::class)->publicar($exercicio, (int) $request->user()->id);
    } catch (FourEyesViolationException|DomainException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('status', "Exercício {$exercicio} publicado.");
}
```

- [ ] **Step 4: Rodar e confirmar passagem; teste de quatro olhos no HTTP** (autor ≠ publicador publica; autor = publicador recebe erro).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/TllValorController.php app/Http/Requests/Gestao/PropagacaoTllRequest.php routes/gestao.php tests/Feature/Analise/TllValorControllerTest.php
git commit -m "feat: endpoints de gerar e publicar exercicio da TLL"
```

---

### Task 6: Alerta agendado de exercício faltante

**Files:**
- Create: `app/Console/Commands/AlertarExercicioTllCommand.php`
- Create: `app/Notifications/TllExercicioFaltanteNotification.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Analise/AlertarExercicioTllCommandTest.php`

**Interfaces:**
- Consumes: `RuleVersion::vigente(RuleDomain::TllValores)`, `NotificationDispatcher`, role `notificacoes.escalonamento.gestor_role` (default `gestor`).
- Produces: comando `tll:alertar-exercicio` — notifica gestores em dez/jan quando o exercício corrente ou o seguinte não tem versão vigente; idempotente via ledger `communications`; nunca grava valor nem publica.

- [ ] **Step 1: Teste falhando — notifica gestor quando falta versão vigente do ano seguinte**

```php
public function test_notifica_gestor_quando_falta_versao_vigente(): void
{
    Carbon::setTestNow('2026-12-15');
    $gestor = User::factory()->create()->assignRole('gestor');
    Notification::fake();

    $this->artisan('tll:alertar-exercicio')->assertSuccessful();

    Notification::assertSentTo($gestor, TllExercicioFaltanteNotification::class);
}
```

- [ ] **Step 2: Rodar e confirmar falha** (comando inexistente)

- [ ] **Step 3: Implementar comando + notificação** (espelha `AlertarVencimentosCommand`: checa ledger `communications` antes de notificar para não duplicar; só roda a regra em dez/jan — fora dessa janela, no-op).

- [ ] **Step 4: Agendar em `routes/console.php`**

```php
// Tabela TLL (HU-071): alerta gestores em dez/jan quando o exercício corrente
// ou o seguinte não tem versão vigente. SÓ notifica (nunca grava/publica).
Schedule::command('tll:alertar-exercicio')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->onOneServer();
```

- [ ] **Step 5: Rodar e confirmar passagem; teste de idempotência** (2ª execução não reenvia).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/AlertarExercicioTllCommand.php app/Notifications/TllExercicioFaltanteNotification.php routes/console.php tests/Feature/Analise/AlertarExercicioTllCommandTest.php
git commit -m "feat: alerta gestores quando falta tabela TLL vigente do exercicio"
```

---

### Task 7: Tela — ação Gerar exercício e botão Publicar

**Files:**
- Modify: `resources/js/pages/gestao/tll/index.tsx`
- Modify: `app/Http/Controllers/Gestao/TllValorController.php` (index passa `rascunhos`/`vigentes` por exercício)

**Interfaces:**
- Consumes: rotas `tll.exercicios.store` / `tll.exercicios.publicar`; props `exercicios` (status por ano, autor, se o usuário pode publicar).
- Produces: modal "Gerar exercício" (origem, destino, fator, decreto) e botão "Publicar" no rascunho, desabilitado para o autor (quatro olhos).

- [ ] **Step 1: Index passa os exercícios com status e permissão de publicar**

```php
'exercicios' => RuleVersion::query()->where('domain', RuleDomain::TllValores->value)
    ->orderByDesc('version')->get()->map(fn (RuleVersion $v) => [
        'exercicio' => (int) $v->version,
        'status' => $v->status->value,
        'autor_id' => $v->created_by,
        'pode_publicar' => $v->status === RuleVersionStatus::Rascunho && $v->created_by !== $request->user()?->id,
    ]),
```

- [ ] **Step 2: Modal Gerar exercício + botão Publicar na lista** (componentes existentes: `Modal`, `Input`, `Button`, `ConfirmDialog`; `router.post` para as rotas novas).

- [ ] **Step 3: Build + typecheck**

Run: `npm run build && npx tsc --noEmit` (ou o typecheck do projeto)
Expected: sem erros; `public/build` atualizado.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/gestao/tll/index.tsx app/Http/Controllers/Gestao/TllValorController.php public/build
git commit -m "feat: tela de gerar e publicar exercicio da TLL"
```

---

## Self-Review

- **Cobertura da spec:** §3.1 coluna (Task 1), §3.2 domínio/quatro olhos (Tasks 1, 3), §4 fluxo (Tasks 2, 3, 5, 7), §5 regras (Task 2 bordas), §6 superfície (Tasks 5, 6, 7), §7 auditoria (Tasks 2, 3, 5), §9 CAs 01–06 (Tasks 2–6). Ponto aberto 10.1 (isolar rascunho do cálculo) → **resolvido**: Task 4 exige versão vigente. Ponto aberto 10.2 (destinatário) → **resolvido**: role `gestor` (padrão do escalonamento SLA).
- **Placeholders:** nenhum; código real em cada step.
- **Consistência de tipos:** `propagar(origem, destino, fator, decreto, autorId)` e `publicar(exercicio, publicadorId)` iguais em serviço, controller e testes; `RuleDomain::TllValores` único.
