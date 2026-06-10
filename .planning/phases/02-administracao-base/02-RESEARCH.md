# Fase 2: Administração Base — Pesquisa

**Pesquisado em:** 2026-06-10
**Domínio:** Cadastros administrativos (CNAEs, usuários, perfis) + registry de parâmetros com efeito sem deploy — Laravel 13.15 / Inertia v3.1 / React 19 / spatie-permission 8.0 / spatie-activitylog 5.0 / Fortify 1.37
**Confiança:** ALTA (arquivo oficial inspecionado byte a byte; vendor lido; docs oficiais verificadas)

## Summary

A fase entrega quatro CRUDs administrativos sob `/gestao` (CNAEs, usuários, perfis, parâmetros) sobre infraestrutura que a Fase 1 já deixou pronta: auditoria transversal (`AuditService` + `RecordActivityAction` + 403 auditado global), middleware de permissão por rota, seeder idempotente de papéis e o wrapper `App\Support\Settings` cujo backend esta fase troca por banco + cache sem tocar call sites.

A pesquisa **inspecionou o xlsx oficial real** (`CNAE_Subclasses_2_3_Estrutura_Detalhada.xlsx`): 2.401 linhas em árvore (4 de cabeçalho), com exatamente **1.331 subclasses**, 673 classes, 285 grupos, 87 divisões e 21 seções, sem duplicatas e sem código fora do padrão `DDDD-D/SS`. A **divergência 1.331 × 1.332 foi resolvida com evidência**: a publicação oficial do IBGE cita 1.332 subclasses; a única ausente no arquivo é a **9900-8/00 (Organismos internacionais e outras instituições extraterritoriais)** — confirmada existente na busca oficial da CONCLA, mas omitida na aba de estrutura (a classe 99.00-8 aparece sem linha de subclasse). As outras duas classes sem subclasse no arquivo (47.90-3 — comércio ambulante; 70.10-7 — sedes de empresas) não retornam subclasses na CONCLA, e o CSV do Decreto 32.636/2020 (risco municipal) cobre exatamente as mesmas 1.331 subclasses do arquivo (cobertura 1:1). Conclusão: importar 1.331 e registrar a divergência documentada no relatório — não inventar a linha ausente.

Para o registry de parâmetros, a recomendação é tabela única `parameters` (catálogo seedado com key/group/type/value/default/validation_rules/sensitive) lida por `Settings::get` com cache por chave, invalidação na gravação e fallback resiliente para `config/sile.php` — necessário porque `Settings::get` roda no `boot()` do `AppServiceProvider` (antes de existir banco em cenários de build/CI). Histórico via `AuditService` explícito (não model events), com valores mascarados quando sensível.

**Recomendação primária:** converter o xlsx uma única vez para CSV versionado em `database/data/` (script de conversão versionado como documentação executável da proveniência) e implementar o import real em service PHP testável com relatório de contadores; registry de parâmetros em tabela `parameters` com `Settings::get` resiliente a banco ausente; inativação de usuário via `Fortify::authenticateUsing` + middleware de sessão ativa; CRUD de perfis com proteções a papéis estruturais e cache automático do spatie v8.

## Standard Stack

Nenhuma dependência nova é necessária. Tudo que a fase precisa já está instalado.

### Core (já instalado — versões verificadas via `composer show`)

| Biblioteca | Versão | Papel nesta fase |
|---|---|---|
| laravel/framework | 13.15.0 | Migrations, Eloquent, cache, `Crypt`, validação, `Route::resource` |
| spatie/laravel-permission | 8.0.0 | CRUD de roles/permissions (HU-013), middleware `permission:` |
| spatie/laravel-activitylog | 5.0.0 | Trilha de auditoria (já estendida na Fase 1 com colunas SILE) |
| laravel/fortify | 1.37.2 | Ponto de bloqueio do login de inativados (`authenticateUsing`) |
| inertiajs/inertia-laravel + @inertiajs/react | 3.1.0 / 3.3.1 | Telas administrativas (`<Form>`, `router.get`, paginação) |
| predis/predis | 3.5 | Cache Redis em dev/prod (`CACHE_STORE=redis`); testes usam `array` |

### Alternativas consideradas (e rejeitadas)

| Em vez de | Poderia usar | Por que NÃO nesta fase |
|---|---|---|
| CSV versionado + service PHP de import | maatwebsite/excel lendo o xlsx direto | Suporte a Laravel 13 existe (3.1.68+, mar/2026), mas adiciona phpoffice/phpspreadsheet inteiro para ler **uma vez** um arquivo cuja revisão oficial só está prevista para 2027. O xlsx tem estrutura em árvore (carry-forward) que exigiria mais código de parsing do que o CSV achatado. maatwebsite/excel entra quando houver **exports** (EP15) |
| Registry próprio (tabela `parameters`) | spatie/laravel-settings | spatie/laravel-settings usa classes PHP tipadas por grupo (settings como código) — incompatível com o requisito de catálogo dinâmico administrável por UI com validação/histórico/sensível por linha (RN-007/008/009) |
| Toggles no registry (`type=boolean`, `group=features`) | laravel/pennant | Pennant é para flags por escopo/usuário com resolvers em código; o requisito é toggle global administrável por interface com auditoria e histórico — um único mecanismo (o registry) atende RN-005/006/008/011 sem segundo subsistema |

## Achados sobre o arquivo oficial de CNAEs (inspeção real)

Arquivo: `docs/dados-oficiais/CNAE_Subclasses_2_3_Estrutura_Detalhada.xlsx` — 3 abas; a relevante é `Estrutura Det. CNAE Subclass2.3` (2.401 linhas × 7 colunas; coluna G sempre vazia).

**Layout (verificado):**
- Linhas 1–4: título e cabeçalhos. Dados começam na linha 5.
- Estrutura em árvore com um único nível preenchido por linha: coluna A=Seção (`A`..`U`), B=Divisão (`01`), C=Grupo (`01.1`), D=Classe (`01.11-3`), E=Subclasse (`0111-3/01`), F=Denominação.
- A hierarquia é derivada por *carry-forward* (cada subclasse pertence à última classe/grupo/divisão/seção vista acima). Verificação de coerência: o prefixo numérico de **todas** as 1.331 subclasses bate com a classe-pai do contexto — zero inconsistências.
- Contagens: 21 seções, 87 divisões, 285 grupos, 673 classes, **1.331 subclasses**. Sem duplicatas, sem subclasse sem denominação, sem linha órfã, todos os códigos no padrão `^\d{4}-\d/\d{2}$`.

**Divergência 1.331 × 1.332 (resolvida):**
- A publicação oficial IBGE/CONCLA afirma 1.332 subclasses na CNAE-Subclasses 2.3 (confirmado em ibge.gov.br, notícia oficial da versão 2.3).
- Três classes aparecem no arquivo sem nenhuma subclasse: `47.90-3`, `70.10-7` e `99.00-8`.
- Consulta à busca oficial da CONCLA (cnae.ibge.gov.br, versão 2.3): `9900-8/00` **existe** (Organismos internacionais — embaixadas, consulados, ONU etc.); `4790-3/01` e `7010-7/00` **não retornam registros**.
- O CSV do Decreto municipal 32.636/2020 (1.331 códigos) cobre exatamente os mesmos códigos do arquivo — nenhum dos três acima está classificado no decreto.
- **Conclusão:** a subclasse ausente do arquivo é a `9900-8/00`. Não é licenciável municipalmente (não consta do decreto de risco) e a fonte do seed deve permanecer fiel ao arquivo oficial. O import grava 1.331, e o relatório/auditoria registra: "publicação oficial cita 1.332; ausente no arquivo de estrutura: 9900-8/00 (confirmada na CONCLA)". Se a SEDUR precisar dela, o caminho é o próprio CRUD da HU-011 (cadastro manual auditado) — não um ajuste silencioso no seed.

**Normalizações necessárias no import (lógica real, testável em PHP):**
- Código para dígitos: `0111-3/01` → `0111301` (7 dígitos). Exibição formatada `DDDD-D/SS` via accessor (referência SIGVISA).
- `trim` em códigos e denominações (o arquivo é limpo, mas o import não deve confiar nisso).
- Carry-forward da hierarquia resolvido **na conversão** para CSV (transformação mecânica de formato); a normalização de dígitos e validações ficam no service PHP (lógica de negócio testada).

## Architecture Patterns

### Estrutura recomendada (segue padrões da Fase 1)

```
app/
├── Http/Controllers/Gestao/
│   ├── CnaeController.php             # resource (index/store/update/destroy)
│   ├── UserManagementController.php   # index/update (ativar/inativar, papel)
│   ├── RoleController.php             # resource
│   └── ParameterController.php        # index/update + histórico
├── Http/Requests/Gestao/              # FormRequests com mensagens pt-BR
├── Models/
│   ├── Cnae.php                       # HasAuditoria, accessor formatted_code
│   └── Parameter.php                  # cast por type, Crypt condicional
├── Services/
│   └── CnaeImportService.php          # import real do CSV com relatório
├── Support/Settings.php               # MESMA API; backend novo (banco+cache+fallback)
database/
├── data/cnaes-subclasses-2-3.csv      # dado oficial convertido (1.331 linhas + cabeçalho)
├── seeders/CnaeSeeder.php             # delega ao CnaeImportService
├── seeders/ParameterSeeder.php        # catálogo de parâmetros (idempotente, preserva value)
└── factories/{Cnae,Parameter}Factory.php
scripts/
└── convert-cnae-xlsx.py               # conversão one-shot xlsx→csv (proveniência documentada)
resources/js/pages/gestao/
├── cnaes/index.tsx                    # tabela + busca server-side + form modal/inline
├── usuarios/index.tsx                 # liga para gestao/acessos/{user} (fecha concern Fase 1)
├── perfis/index.tsx
└── parametros/index.tsx               # agrupado por group; histórico por parâmetro
```

### Padrão 1: Settings com banco + cache + fallback resiliente (HU-014, CA-05)

**O quê:** `Settings::get(key, default)` mantém a assinatura da Fase 1. Ordem de resolução: cache → banco (`parameters.value` ?? `default` do catálogo) → `config("sile.{$key}")` → `$default` do call site.

**Restrição crítica descoberta na Fase 1:** `Settings::get` roda no `boot()` do `AppServiceProvider` (linha 45 — `password_reset_expire`). Em build Docker (`package:discover`), CI antes de migrar e testes Unit sem `RefreshDatabase`, **a tabela pode não existir**. Sem guarda, todo `artisan` quebra.

```php
// app/Support/Settings.php — esqueleto recomendado
public static function get(string $key, mixed $default = null): mixed
{
    $fallback = config("sile.{$key}", $default);

    try {
        return Cache::remember(
            "sile.parameters.{$key}",
            (int) config('sile.parameters.cache_ttl', 300),
            function () use ($key, $fallback) {
                $parameter = Parameter::query()->where('key', $key)->first();

                return $parameter === null
                    ? $fallback
                    : ($parameter->typedValue() ?? $fallback);
            },
        );
    } catch (QueryException) {
        return $fallback; // banco ausente/não migrado: comportamento da Fase 1
    }
}
```

- **Invalidação na gravação:** no `booted()` do model `Parameter`, eventos `saved`/`deleted` fazem `Cache::forget("sile.parameters.{$parameter->key}")`. Qualquer gravação (UI, tinker, teste) invalida — efeito imediato no mesmo store (CA-05 testável de ponta a ponta).
- **TTL é constante técnica** (`config/sile.php` → `parameters.cache_ttl`), não parâmetro do registry — parametrizá-lo no próprio registry criaria recursão; a regra de parametrização exclui constantes técnicas internas.
- **Null no cache:** `Cache::remember` não re-executa para `null` armazenado em alguns drivers? Errado para Laravel ≥ 10 — `remember` armazena `null` e re-consulta. Para evitar ambiguidade, resolver o fallback **dentro** da closure (como acima), nunca retornar `null` significando "não achei".
- **Testes da Fase 1 continuam verdes:** `tests/Unit/Support/SettingsTest` (sem RefreshDatabase, tabela inexistente) cai no `QueryException → fallback config` e segue assertando `5`. Testes Feature com `RefreshDatabase` têm a tabela vazia → linha não existe → fallback config. Nenhum call site muda.

### Padrão 2: catálogo de parâmetros como dado versionado (RN-007)

Tabela `parameters` (nome recomendado em vez de `settings` — o repositório já usa "Settings" para configurações pessoais do usuário em `tests/Feature/Settings` e `resources/js/pages/settings`; colidir os nomes confunde navegação e suíte):

| Coluna | Tipo | Papel |
|---|---|---|
| `key` | string, unique | `security.login.max_attempts` (espelha o path do config) |
| `group` | string, index | `seguranca`, `ui`, `features`… — agrupa a tela |
| `type` | string | `string` \| `integer` \| `boolean` \| `decimal` \| `json` \| `text` |
| `value` | text, nullable | valor administrado (string crua; cast por `type`); `null` = usa default |
| `default_value` | text, nullable | valor padrão exibido na UI (espelho documentado do config) |
| `validation_rules` | json | regras Laravel aplicadas na gravação, ex.: `["required","integer","min:1","max:20"]` |
| `sensitive` | boolean | criptografa `value` e mascara na UI e no histórico (RN-009) |
| `description` | string | texto pt-BR exibido ao administrador |
| `requires_connection_test` | boolean, default false | contrato do RN-010 — botão só nasce na Fase 13, sem teste falso |

- **Seeder idempotente que preserva o valor administrado:** `ParameterSeeder` faz upsert por `key` **apenas dos metadados** (`group`, `type`, `default_value`, `validation_rules`, `sensitive`, `description`) — NUNCA toca `value`. `updateOrCreate(['key' => …], [metadados])` sobrescreveria `value` se incluído no segundo array; deixá-lo de fora preserva o que o admin gravou.
- **Catálogo inicial:** as chaves hoje existentes em `config/sile.php` (`security.password.min_length`, `require_mixed_case`, `require_numbers`, `require_symbols`, `security.login.max_attempts`, `security.password_reset_expire`, `ui.access_history.per_page`) — todas com `validation_rules` apropriadas (ex.: `max_attempts` entre 1 e 20).
- **Validação na gravação (RN-007):** FormRequest valida `value` com as `validation_rules` do próprio registro (`Validator::make(['value' => $input], ['value' => $parameter->validation_rules])`) + coerência com `type`. Valor inválido = 422 com mensagem pt-BR, nada gravado.

### Padrão 3: criptografia condicional de sensíveis (RN-009)

A coluna `value` é compartilhada por sensíveis e não sensíveis — o cast fixo `encrypted` (doc Laravel 13, eloquent-mutators) criptografaria tudo. Recomendado: accessor/mutator condicional com `Attribute::make` + `Crypt::encryptString`/`decryptString` quando `sensitive=true`.

```php
// app/Models/Parameter.php
protected function value(): Attribute
{
    return Attribute::make(
        get: fn (?string $value) => $value !== null && $this->sensitive
            ? Crypt::decryptString($value)
            : $value,
        set: fn (?string $value) => $value !== null && $this->sensitive
            ? Crypt::encryptString($value)
            : $value,
    );
}
```

- **Pitfall real:** o mutator lê `$this->sensitive` — em `Parameter::create([...])` o `fill` processa o array **na ordem das chaves**; se `value` vier antes de `sensitive`, a flag ainda é `null` e o valor NÃO é criptografado. Mitigações obrigatórias: (1) o fluxo de produção só faz `update(['value' => …])` em registro já carregado do catálogo (sensível definido no seed); (2) `sensitive` fica **fora** do fillable da UI (imutável por interface — mudança de sensibilidade é mudança de catálogo via seeder); (3) na factory, o state `sensitive()` deve ordenar `sensitive` antes de `value`.
- Coluna `value` como `TEXT` (ciphertext é maior que o texto plano — doc oficial).
- UI nunca devolve o valor sensível: o controller envia `value_masked = '••••••'` (ou `null`) quando `sensitive`, e a gravação só altera se o campo vier preenchido.

### Padrão 4: histórico de parâmetros via auditoria explícita (RN-008, CA-07)

**Não** usar `HasAuditoria` (model events) no `Parameter` — o diff automático de `attribute_changes` gravaria o valor sensível em claro, e o hook `tapActivity` **não existe mais** no activitylog v5 (verificado no vendor: customização por instância só via `LogOptions`, que apenas omite atributos com `logExcept`, sem mascarar). Em vez disso, auditoria explícita no fluxo de gravação (padrão já usado na Fase 1 para consulta administrativa de acessos):

```php
// no service/controller de atualização
app(AuditService::class)->log(
    logName: 'parametros',
    event: 'parametro-alterado',
    description: "Parâmetro {$parameter->key} alterado",
    properties: [
        'key' => $parameter->key,
        'valor_anterior' => $parameter->sensitive ? '[criptografado]' : $old,
        'valor_novo' => $parameter->sensitive ? '[criptografado]' : $new,
    ],
    subject: $parameter,
);
```

- A tela de histórico (CA-07) consulta `Activity::where('log_name', 'parametros')` — `causer` (responsável), `created_at` (data/hora) e as properties acima cobrem exatamente o que o CA pede. `RecordActivityAction` enriquece ip/canal automaticamente.
- O mesmo padrão explícito serve para HU-012 (`log 'usuarios'`, eventos `usuario-inativado`/`usuario-reativado`/`papel-alterado`) e HU-013 (`log 'perfis'`).
- CRUD de CNAE usa `HasAuditoria` normal no model (sem dados sensíveis; `attribute_changes` automático atende CA-02).

### Padrão 5: feature toggles no registry (RN-005, RN-011, CA-06)

- Toggle = parâmetro `type=boolean`, `group=features`, chave `features.<nome>`. Helper dedicado:

```php
public static function enabled(string $feature): bool
{
    return (bool) static::get("features.{$feature}", false);
}
```

- **Degradação controlada testável:** o consumidor do toggle decide o comportamento degradado e o comunica (aviso/pendência/bloqueio explícito) — nunca silencioso. Padrão: `if (! Settings::enabled('x')) { return back()->with('status', 'Funcionalidade desativada pelo administrador.'); }` ou render de tela com aviso.
- **Candidato real para nascer na fase** (CA-06 de ponta a ponta sem feature de fachada): `features.procuracoes` — com o toggle desligado, as rotas de procuração (HU-008/009, já existentes) respondem com aviso pt-BR explícito em vez de operar. É funcionalidade genuinamente acoplável (a gestão pode querer suspender o instituto da procuração) e usa só código já existente. Toggles de integrações/IA/expresso são registrados nas fases próprias (decisão do CONTEXT).
- Alternativa, se o planner preferir não tocar HU-008: provar CA-06 com toggle sintético apenas na suíte (fakes são permitidos em testes), deixando `features.procuracoes` de fora. Menos valioso: não há demonstração real na aplicação.

### Padrão 6: bloqueio de login de usuário inativado (HU-012)

Inspeção do vendor Fortify 1.37 (`AttemptToAuthenticate`): com `Fortify::authenticateUsing($callback)`, retorno falsy dispara `Failed` (→ `RecordFailedLogin` da Fase 1 grava `access_logs` evento `falha`) + incrementa o limiter + lança `ValidationException` com `auth.failed`. O callback pode lançar a própria `ValidationException` — caminho para mensagem específica de conta inativada.

```php
// FortifyServiceProvider::boot()
Fortify::authenticateUsing(function (Request $request) {
    $user = User::query()->where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return null; // mensagem padrão auth.failed + evento Failed (access_log 'falha')
    }

    if ($user->inactivated_at !== null) {
        // credencial correta + conta inativada: registrar e comunicar com clareza
        AccessLog::create([...evento 'inativada'...]);

        throw ValidationException::withMessages([
            'email' => __('Sua conta está inativa. Procure o administrador do sistema.'),
        ]);
    }

    return $user;
});
```

- **Segurança:** a mensagem "conta inativa" só aparece com credenciais corretas — não vira oráculo de enumeração.
- **Limite de coluna:** `access_logs.event` é `varchar(20)` — usar evento curto (`inativada` cabe; `bloqueio-inativacao` tem exatamente 20 chars).
- **Sessões já abertas:** inativação deve ter efeito imediato (consistente com a revalidação por request da representação na Fase 1). Middleware leve `EnsureUserIsActive` no grupo autenticado (portal + gestão): usuário com `inactivated_at` é deslogado (invalidate + regenerateToken) e redirecionado ao login com a mesma mensagem.
- **Modelagem:** `inactivated_at` timestamp nullable (migration `add`); **fora** do `#[Fillable]` (inativação só por método dedicado/`forceFill` no fluxo autorizado — nunca mass assignment); cast `datetime`; proteção contra auto-inativação no FormRequest (`$user->id !== auth()->id()`), com 422 pt-BR.
- Vínculo de papel pela UI: `$user->syncRoles([$role])` — atribuições a usuário são em memória, sem necessidade de reset de cache (doc spatie v8).

### Padrão 7: CRUD de perfis com spatie/permission 8 (HU-013)

- Criação dinâmica: `Role::create(['name' => $name, 'guard_name' => 'web'])` + `$role->syncPermissions($permissionNames)`. Doc v8 (cache): operações built-in (`create`, `delete`, `givePermissionTo`, `syncPermissions`, trait `RefreshesPermissionCache`) **resetam o cache automaticamente** — `forgetCachedPermissions` manual só é preciso ao manipular as tabelas diretamente.
- Proteções (regras do CONTEXT, implementar em FormRequest/Policy):
  - Papéis estruturais `['cidadao', 'analista', 'gestor', 'administrador']` (constante no código): não excluir, não renomear; permissões ajustáveis EXCETO remover `acessar-gestao` do `administrador` (anti-lockout).
  - Exclusão de papel custom bloqueada se `$role->users()->exists()` (CA-03), com mensagem pt-BR.
- v8 sem breaking changes relevantes aqui: o projeto já nasceu na v8 (namespace `Middleware` singular correto no `bootstrap/app.php`; migration v8). `findByName`/`findOrCreate` agora aceitam `BackedEnum|string` — irrelevante para uso com strings.
- A tela lista permissões agrupadas por prefixo (`manter-*`, `consultar-*`, `acessar-*`, `gerenciar-*`) com checkboxes — atribuição granular por funcionalidade.

### Padrão 8: import de CNAEs com relatório verificável (HU-011)

- **Conversão one-shot** (xlsx → CSV): script `scripts/convert-cnae-xlsx.py` versionado (openpyxl, disponível na máquina de dev) resolve o carry-forward da árvore e emite `database/data/cnaes-subclasses-2-3.csv` com cabeçalho `section_code,section_description,division_code,division_description,group_code,group_description,class_code,class_description,subclass_code,subclass_description` — códigos **no formato oficial** (`0111-3/01`), fidelidade à fonte. O CSV versionado é dado de seed oficial público (permitido pelas regras do projeto); o script documenta a proveniência de forma executável.
- **Lógica real em PHP testável:** `CnaeImportService` lê o CSV (`SplFileObject`/`fgetcsv`), normaliza código para dígitos, valida formato/duplicata, faz `upsert` por `code` e devolve relatório `['lidos' => n, 'importados' => n, 'atualizados' => n, 'rejeitados' => [...], 'esperado_publicacao' => 1332, 'divergencia' => ['9900800' => 'ausente do arquivo oficial de estrutura']]` (padrão de contadores do `CsvImport` do SIGVISA).
- `CnaeSeeder` delega ao service e registra o relatório na auditoria (`AuditService::log('cnaes', 'importacao-oficial', …, properties: relatório, rulesVersion: 'cnae-subclasses-2.3')` — canal `console` é preenchido automaticamente pelo `RecordActivityAction`). Entra no `DatabaseSeeder`.
- Re-execução idempotente: upsert por `code` não duplica; rodar em banco existente atualiza denominações.

### Padrão 9: telas administrativas Inertia v3 + React 19

Padrões já estabelecidos na Fase 1 (reusar, não recriar):
- Tabela com `<table>` estilizada + paginação por `links` do paginator Laravel (`gestao/acessos.tsx` é o template).
- Formulários com `<Form action method>` + render props `{ errors, processing, recentlySuccessful }` (`settings/password.tsx` é o template); mensagens flash via `flash.status` já renderizadas pelo `GestaoLayout`.
- URLs literais com rotas nomeadas no servidor (`route()` no PHP; sem ziggy/wayfinder no projeto).
- **Busca server-side com debounce** (novo nesta fase): estado controlado + `setTimeout` (300–400 ms) + `router.get(url, { search }, { preserveState: true, replace: true })`. `preserveState` mantém o input focado; `replace` evita poluir o histórico. Paginação preserva o filtro porque os `links` do paginator carregam a query string quando o controller usa `->withQueryString()`.
- Navegação: tela de usuários linka `<Link href={`/gestao/acessos/${user.id}`}>` — fecha o blocker registrado no STATE.md.
- `GestaoLayout` hoje não tem menu de navegação — a fase precisa adicionar navegação para as quatro telas novas (e dashboard), visível conforme `auth.permissions` (shared prop já existente no `HandleInertiaRequests`).

### Padrão 10: rotas e permissões por recurso (CA-04)

```php
// routes/gestao.php — dentro do grupo existente (auth, verified, permission:acessar-gestao, lgpd.accepted)
Route::middleware('permission:consultar-cnaes')->group(function () {
    Route::get('cnaes', [CnaeController::class, 'index'])->name('cnaes.index');
});
Route::middleware('permission:manter-cnaes')->group(function () {
    Route::post('cnaes', [CnaeController::class, 'store'])->name('cnaes.store');
    Route::put('cnaes/{cnae}', [CnaeController::class, 'update'])->name('cnaes.update');
    Route::delete('cnaes/{cnae}', [CnaeController::class, 'destroy'])->name('cnaes.destroy');
});
// usuarios, perfis, parametros: permission:manter-* em todo o grupo
```

- O 403 já é auditado globalmente (render callbacks do `bootstrap/app.php` — Fase 1). Os testes de CA-04 apenas assertam status 403 + `activity_log` com `log_name=seguranca`, `result=bloqueado` (padrão existente).
- Permissões novas no `RolesAndPermissionsSeeder`: `manter-cnaes`, `manter-usuarios`, `manter-perfis`, `manter-parametros`, `consultar-cnaes`. Sugestão de atribuição: todas as `manter-*` ao `administrador`; `consultar-cnaes` também a `analista` e `gestor` (consulta aberta a perfis internos, decisão do CONTEXT).
- **Mudança recomendada no seeder:** trocar `syncPermissions` por `givePermissionTo` (aditivo) nos papéis estruturais. Motivo: a HU-013 permite ao admin ajustar permissões de papéis estruturais pela UI; re-rodar o seeder com `syncPermissions` **reverteria silenciosamente** esses ajustes (o sync remove o que não está na lista). `firstOrCreate` + `givePermissionTo` mantém a idempotência sem regressão administrativa. O teste `test_seeder_e_idempotente` continua válido (contagens passam a 4 papéis / 8 permissões).

## Don't Hand-Roll

| Problema | Não construir | Usar | Por quê |
|---|---|---|---|
| Cache de roles/permissions | Invalidação manual espalhada | Reset automático do spatie v8 (métodos built-in + `RefreshesPermissionCache`) | Já resolve criação/edição/exclusão; manual só em manipulação direta de tabela |
| Criptografia de sensíveis | XOR/base64/algoritmo próprio | `Crypt::encryptString` (APP_KEY, AES-256-CBC + MAC) | Doc oficial; rotação de chave gerenciada pelo framework |
| Diff de auditoria de models simples (Cnae) | Comparação manual old/new | `HasAuditoria` (activitylog `logFillable+logOnlyDirty`) | Já enriquecido com origem pelo `RecordActivityAction` da Fase 1 |
| Validação dinâmica de parâmetro | Parser próprio de regras | `Validator::make` com regras Laravel armazenadas em `validation_rules` (json) | Reusa todo o ecossistema de regras e mensagens pt-BR do laravel-lang |
| Paginação + busca | Componente de tabela genérico próprio | Paginator Laravel (`->withQueryString()`) + padrão `links` da Fase 1 | Já testado e estilizado em `acessos.tsx` |
| Leitura de xlsx em runtime | Parser de planilha no request | Conversão one-shot para CSV versionado | O arquivo muda a cada ~8 anos; runtime nunca precisa de xlsx |

**Insight-chave:** a Fase 1 já entregou os mecanismos transversais (auditoria enriquecida, 403 auditado, seeds idempotentes, padrões de tela). A Fase 2 deve **consumir** esses mecanismos — qualquer reimplementação local (novo logger, novo padrão de 403, novo layout de tabela) é regressão de arquitetura.

## Common Pitfalls

### Pitfall 1: `Settings::get` no boot quebra `artisan` sem banco
**O que acontece:** o novo backend consulta a tabela `parameters` e `AppServiceProvider::boot()` chama `Settings::get('security.password_reset_expire')` em TODO boot — `php artisan package:discover` (build Docker), CI antes do migrate e testes Unit explodem com `QueryException`.
**Prevenção:** try/catch `QueryException` → fallback `config()` (Padrão 1). NUNCA usar `Schema::hasTable` por chamada (round-trip extra a cada miss de cache).
**Sinal de alerta:** suíte Unit falhando com "no such table: parameters"; build Docker quebrando no `composer install`.

### Pitfall 2: seeder do catálogo sobrescreve valor administrado
**O que acontece:** `updateOrCreate(['key' => …], ['value' => …, …])` em deploy re-zera o que o admin configurou — violação direta do propósito da HU-014.
**Prevenção:** o array de update do seeder contém SÓ metadados; `value` nunca. Teste dedicado: seed → admin grava valor → seed de novo → valor preservado.

### Pitfall 3: ordem de atributos no mutator condicional de `value`
**O que acontece:** `Parameter::create(['value' => 'segredo', 'sensitive' => true])` grava SEM criptografar (o mutator roda antes de `sensitive` ser setado).
**Prevenção:** `sensitive` imutável pela UI (definido só no catálogo/seed); fluxo de gravação sempre `update` em model carregado; factory state ordena `sensitive` antes de `value`; teste asserta que o valor no banco difere do texto plano (`assertDatabaseMissing`).

### Pitfall 4: histórico vaza segredo via model events
**O que acontece:** `HasAuditoria` no `Parameter` grava `attribute_changes` com valor sensível em claro (e `tapActivity` não existe no activitylog v5 para mascarar).
**Prevenção:** sem `LogsActivity` no `Parameter`; auditoria explícita com mascaramento `[criptografado]` (Padrão 4). Teste: alterar parâmetro sensível → `activity_log` não contém o valor em claro em NENHUMA coluna.

### Pitfall 5: `syncPermissions` no seeder desfaz ajustes da UI
**O que acontece:** re-seed em produção (deploy) reverte permissões que o admin adicionou a papéis estruturais pela HU-013.
**Prevenção:** seeder aditivo (`givePermissionTo`) — ver Padrão 10. Teste: seed → adicionar permissão extra via UI/model → re-seed → permissão extra permanece.

### Pitfall 6: admin tranca a si mesmo (lockout)
**O que acontece:** admin inativa a própria conta, exclui/esvazia o papel `administrador` ou remove `acessar-gestao` dele — ninguém mais administra o sistema.
**Prevenção:** três bloqueios explícitos com 422 pt-BR: auto-inativação proibida; papéis estruturais não exclusíveis/renomeáveis; `acessar-gestao` não removível do `administrador`. Cada um com teste próprio (CA-03 das HUs 012/013).

### Pitfall 7: usuário inativado continua navegando com a sessão aberta
**O que acontece:** bloquear só o login deixa sessões ativas vivas até expirarem — "inativação" sem efeito imediato.
**Prevenção:** middleware `EnsureUserIsActive` no grupo autenticado (logout forçado + redirect com mensagem). Teste: usuário logado → admin inativa → próxima request do usuário cai no login com aviso.

### Pitfall 8: evento de access_log estourando varchar(20)
**O que acontece:** `access_logs.event` é `varchar(20)`; um nome descritivo longo (`bloqueio-inativacao` = 20, ok por 0 margem) quebra em nomes maiores.
**Prevenção:** padronizar evento curto (`inativada`) e validar contra a migration existente antes de nomear.

### Pitfall 9: import re-executado duplica ou diverge silenciosamente
**O que acontece:** seeder com `insert` puro duplica em re-seed; contagem errada passa despercebida.
**Prevenção:** upsert por `code` normalizado (unique index) + relatório com contadores + teste assertando `Cnae::count() === 1331` e o registro da divergência na auditoria.

### Pitfall 10: busca server-side perde estado/foco e quebra paginação
**O que acontece:** `router.get` sem `preserveState` remonta a página e o input perde o foco a cada tecla; paginação sem `withQueryString()` descarta o filtro ao trocar de página.
**Prevenção:** debounce + `preserveState: true, replace: true` no visit; `->withQueryString()` no paginator (Padrão 9).

## Code Examples

Verificados contra vendor instalado e docs oficiais.

### Cast tipado do parâmetro (registry)

```php
// app/Models/Parameter.php — leitura tipada consumida por Settings::get
public function typedValue(): mixed
{
    $raw = $this->value ?? $this->default_value;

    if ($raw === null) {
        return null;
    }

    return match ($this->type) {
        'integer' => (int) $raw,
        'decimal' => (float) $raw,
        'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
        'json' => json_decode($raw, true),
        default => $raw,
    };
}
```

### Invalidação de cache na gravação (CA-05)

```php
// app/Models/Parameter.php
protected static function booted(): void
{
    $forget = fn (self $parameter) => Cache::forget("sile.parameters.{$parameter->key}");

    static::saved($forget);
    static::deleted($forget);
}
```

### Teste de efeito sem deploy (CA-05, ponta a ponta com mecanismo real da Fase 1)

```php
public function test_alteracao_de_parametro_tem_efeito_imediato_sem_deploy(): void
{
    $this->seed([RolesAndPermissionsSeeder::class, ParameterSeeder::class]);
    $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

    // per_page governa a paginação do histórico de acessos (call site real da Fase 1)
    $this->actingAs($admin)
        ->put(route('gestao.parametros.update', 'ui.access_history.per_page'), ['value' => 5])
        ->assertRedirect();

    AccessLog::factory()->count(6)->for($admin)->create();

    $this->actingAs($admin)
        ->get(route('portal.acessos'))
        ->assertInertia(fn (Assert $page) => $page->where('logs.data', fn ($data) => count($data) === 5));
}
```

### Accessor de código formatado (referência SIGVISA)

```php
// app/Models/Cnae.php — banco guarda dígitos ('0111301'); exibição 'DDDD-D/SS'
protected function formattedCode(): Attribute
{
    return Attribute::make(
        get: fn () => preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $this->code),
    );
}
```

### Busca server-side com debounce (Inertia v3 React)

```tsx
// resources/js/pages/gestao/cnaes/index.tsx (trecho)
const [search, setSearch] = useState(filters.search ?? '');
const isFirstRender = useRef(true);

useEffect(() => {
    if (isFirstRender.current) {
        isFirstRender.current = false;
        return;
    }
    const timeout = setTimeout(() => {
        router.get('/gestao/cnaes', { search }, { preserveState: true, replace: true });
    }, 350);
    return () => clearTimeout(timeout);
}, [search]);
```

### Controller index com filtro + paginação preservando query string

```php
public function index(Request $request): Response
{
    $cnaes = Cnae::query()
        ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
            $term = $request->string('search')->trim();
            $query->where(fn ($q) => $q
                ->where('code', 'like', preg_replace('/\D/', '', $term).'%')
                ->orWhere('description', 'like', "%{$term}%"));
        })
        ->orderBy('code')
        ->paginate((int) Settings::get('ui.cnaes.per_page', 15))
        ->withQueryString();

    return Inertia::render('gestao/cnaes/index', [
        'cnaes' => $cnaes,
        'filters' => ['search' => $request->string('search')->toString()],
    ]);
}
```

## State of the Art

| Abordagem antiga | Abordagem atual | Quando mudou | Impacto na fase |
|---|---|---|---|
| `Settings::get` lendo só `config/sile.php` | Banco + cache + fallback config | Esta fase (era a promessa da Fase 1) | Call sites intactos; testes da Fase 1 verdes via fallback |
| `tapActivity` para customizar activity (activitylog v4) | v5: sem `tapActivity`; customização via `LogOptions`/pipeline ou log explícito | activitylog v5 (2025) | Mascaramento de sensível exige auditoria explícita, não model event |
| `Spatie\Permission\Middlewares\` (plural) | `Spatie\Permission\Middleware\` (singular) | v6+ (projeto já nasceu certo na v8) | Nenhum — `bootstrap/app.php` já correto |
| `forgetCachedPermissions` manual após toda mudança | Reset automático nos métodos built-in (v8) | Documentado na doc v8 de cache | CRUD de perfis não precisa de reset manual; manter o do seeder é inócuo |
| `useForm` para tudo | `<Form>` component (recomendado na v3) com render props | Inertia v2.1+/v3 | Padrão da Fase 1 (`settings/password.tsx`) — seguir |
| Planilha lida em runtime | Dado oficial convertido e versionado + import auditado | Padrão SIGVISA (`CsvImport` com contadores) | Reprodutibilidade do seed e relatório verificável |

**Deprecado/indisponível:**
- `tapActivity` (activitylog v5) — substituído por auditoria explícita nesta arquitetura.
- `Inertia::lazy()` — removido no Inertia v3; usar `Inertia::optional()` (irrelevante para as telas desta fase, que são paginadas server-side).

## Open Questions

1. **Toggle real da fase (`features.procuracoes`)**
   - O que se sabe: CA-06 exige desativação controlada demonstrável; procurações são o único candidato acoplável já implementado.
   - O que falta: confirmação de que a gestão deseja esse toggle (não está nas HUs da fase).
   - Recomendação: implementar `features.procuracoes` como primeiro toggle real (baixo risco, reusa HU-008/009); se o planner rejeitar, provar CA-06 com toggle de teste na suíte e registrar que toggles reais nascem nas fases 9/11/13/14.

2. **Inserir ou não a subclasse 9900-8/00 ausente do arquivo**
   - O que se sabe: existe na publicação oficial (CONCLA confirma), falta no xlsx de estrutura, não consta do decreto municipal de risco.
   - Recomendação: NÃO inserir no seed (fidelidade ao arquivo); registrar divergência no relatório/auditoria. Caminho administrativo (CRUD HU-011) cobre a necessidade se surgir. Levar à SEDUR como nota informativa.

3. **Escopo da edição de CNAE pela UI**
   - O que se sabe: HU-011 fala "manter CNAEs"; a tabela é oficial (IBGE) — editar denominação oficial localmente cria divergência com a fonte.
   - Recomendação: CRUD completo (CA-01 pede), mas com `code` imutável na edição (padrão CPF da Fase 1: regra omite o campo) e flag `ativo` como mecanismo principal de gestão (desativação lógica preserva histórico). Exclusão física bloqueada quando houver vínculos (futuro — Fase 3+); nesta fase, exclusão permitida apenas para CNAEs sem vínculo (hoje, todos) ou simplesmente desencorajada em favor de `ativo=false` — decisão fina para o planner.

4. **Paginação da tela de parâmetros**
   - O que se sabe: o catálogo inicial tem ~8 chaves em 3 grupos — paginação é desnecessária; agrupamento por `group` com seções na tela é mais útil.
   - Recomendação: tela agrupada por `group` sem paginação; busca client-side simples (filtro em memória) é aceitável aqui por ser dataset pequeno e estático — sem custo de servidor.

## Validation Architecture

Organização proposta dos testes (PHPUnit, nomes pt-BR, `RefreshDatabase`, factories + seeds explícitos — padrão Fase 1):

| Grupo | Pasta | O que prova |
|---|---|---|
| CNAEs | `tests/Feature/Cnae/` | HU-011 CA-01 (CRUD executa e persiste), CA-02 (activity_log de criação/edição/desativação via HasAuditoria), CA-03 (validação bloqueia código malformado/duplicado — 422), CA-04 (403 auditado sem permissão); `CnaeImportTest`: contagem 1.331, normalização de dígitos, idempotência do upsert, relatório com divergência 9900-8/00 registrada na auditoria |
| Usuários | `tests/Feature/Users/` | HU-012 CA-01 (listagem/busca, inativação/reativação, vínculo de papel), CA-02 (auditoria explícita log 'usuarios'), CA-03 (auto-inativação bloqueada 422), CA-04 (403 auditado); login de inativado bloqueado com mensagem pt-BR + access_log; middleware desloga sessão ativa de inativado; listagem linka `gestao/acessos/{user}` |
| Perfis | `tests/Feature/Roles/` | HU-013 CA-01 (criar/editar role + syncPermissions com efeito imediato), CA-02 (auditoria), CA-03 (exclusão bloqueada com usuários vinculados; papéis estruturais não excluíveis/renomeáveis; acessar-gestao não removível do administrador), CA-04 (403 auditado) |
| Parâmetros | `tests/Feature/Parameters/` | HU-014 CA-01 (gravação válida), CA-02 (auditoria), CA-03/RN-007 (validation_rules rejeitam valor inválido 422), CA-04 (403), **CA-05** (alteração reflete em request seguinte sem deploy — ex.: per_page do histórico), **CA-06** (toggle desligado degrada com aviso explícito), **CA-07** (histórico exibe valor anterior/novo/responsável/data; sensível mascarado '[criptografado]'); RN-009 (valor sensível cifrado no banco, nunca devolvido em claro pela UI) |
| Seeder/regressão | `tests/Feature/Authorization/` (existente) + `tests/Feature/Seeders/` | Permissões novas criadas e atribuídas; seeder aditivo preserva ajustes; idempotência (contagens); `ParameterSeeder` preserva `value` administrado |
| Unit | `tests/Unit/Support/SettingsTest.php` (existente, **não tocar asserções**) | Fallback config segue valendo sem tabela — prova que call sites da Fase 1 não quebram |

**Comandos por grupo (rodar o mínimo, com filtro):**

```bash
php artisan test --compact tests/Feature/Cnae
php artisan test --compact tests/Feature/Users
php artisan test --compact tests/Feature/Roles
php artisan test --compact tests/Feature/Parameters
php artisan test --compact tests/Unit/Support/SettingsTest.php   # regressão da promessa da Fase 1
php artisan test --compact                                        # suíte completa antes de fechar a fase (122 testes da Fase 1 + novos)
vendor/bin/pint --dirty --format agent                            # após alterar PHP
npm run typecheck && npm run build                                # frontend
```

**Validação manual no browser (admin dev `admin@sile.dev` / `password`):**

1. `php artisan migrate:fresh --seed` (roda RolesAndPermissions + LegalTerm + DevAdmin + Cnae + Parameter seeders).
2. Login em `/login` → redireciona para `/gestao` (admin).
3. `/gestao/cnaes` — tabela carregada com 1.331 registros; buscar "restaurante" (deve achar 5611-2/01); buscar "0111" por código; editar denominação; desativar um CNAE e ver badge inativo.
4. `/gestao/usuarios` — buscar usuário; inativar um cidadão de teste; abrir aba anônima e tentar logar com ele → mensagem "conta inativa"; reativar; clicar no link de histórico de acessos → `/gestao/acessos/{id}`.
5. `/gestao/perfis` — criar perfil "fiscal" com `consultar-cnaes`; tentar excluir `administrador` (bloqueado); tentar excluir perfil com usuário vinculado (bloqueado).
6. `/gestao/parametros` — alterar `ui.access_history.per_page` para 5 → abrir `/portal/acessos` e confirmar 5 itens por página (CA-05 sem deploy); gravar valor inválido (ex.: 0) → erro de validação pt-BR; consultar histórico do parâmetro (valor anterior/novo/responsável); desligar o toggle de features e verificar a degradação comunicada (CA-06).
7. Logar com usuário sem permissão (cidadão) e acessar `/gestao/cnaes` direto → 403; conferir `activity_log` com `result=bloqueado`.

**Verificação do seed CNAE (evidência fresca, não suposição):**

```bash
php artisan db:seed --class=CnaeSeeder
php artisan tinker --execute 'echo App\Models\Cnae::count();'            # esperado: 1331
php artisan tinker --execute 'echo App\Models\Cnae::where("code","0111301")->value("description");'  # Cultivo de arroz
php artisan tinker --execute 'echo App\Models\Activity::where("log_name","cnaes")->latest()->value("properties");'  # relatório com divergência 9900-8/00
```

## Sources

### Primárias (confiança ALTA)
- Inspeção direta do arquivo oficial `docs/dados-oficiais/CNAE_Subclasses_2_3_Estrutura_Detalhada.xlsx` via openpyxl (contagens, formatos, classes sem subclasse, coerência hierárquica) — evidência reproduzível nos comandos desta pesquisa.
- Busca oficial CONCLA/IBGE (cnae.ibge.gov.br, versão 2.3): existência da `9900-8/00`; ausência de subclasses em `4790-3` e `7010-7`.
- IBGE — notícia oficial da CNAE-Subclasses 2.3 (ibge.gov.br): 1.332 subclasses, 21/87/285/673 nos demais níveis, vigência 01/01/2019, Resolução CONCLA 02/2018.
- Vendor instalado (leitura direta): `laravel/fortify` 1.37.2 (`AttemptToAuthenticate` — comportamento exato de `authenticateUsing`, eventos `Failed`); `spatie/laravel-activitylog` 5.0.0 (coluna `attribute_changes`, ausência de `tapActivity`, `LogOptions::logExcept`); migrations e código da Fase 1 do próprio repositório.
- Docs oficiais Laravel 13 (laravel.com/docs/13.x/eloquent-mutators): cast `encrypted`, `Attribute::make`, exigência de coluna TEXT, impossibilidade de busca em valores cifrados.
- Doc oficial spatie/laravel-permission v8 (spatie.be/docs/laravel-permission/v8): cache automático nos métodos built-in, reset manual, atribuições a usuário em memória; guia de upgrade v7→v8 (assinaturas `BackedEnum|string`, namespace `Middleware`).
- Skill do projeto `.cursor/skills/inertia-react-development/SKILL.md` (Inertia v3 React: `<Form>`, router, pitfalls v3).

### Secundárias (confiança MÉDIA)
- maatwebsite/excel: suporte a Laravel 13 desde 3.1.68 (PR #4348 mesclado em 17/03/2026; release notes Packagist) — usado apenas para a decisão de NÃO adotar agora.
- `docs/ANALISE-HUs-REUNIAO-SEDUR.md` (análise interna do projeto, seções 4A/4B/4C): cruzamento decreto × IBGE 1:1; padrões SIGVISA (`Cnae`, `CsvImport`, configs por domínio).

### Terciárias (confiança BAIXA)
- Nenhuma afirmação desta pesquisa depende de fonte não verificada.

## Metadata

**Confiança por área:**
- Dados oficiais CNAE e divergência: ALTA — arquivo real inspecionado + confirmação na CONCLA.
- Registry de parâmetros (modelagem, cache, criptografia, histórico): ALTA — padrões verificados contra docs Laravel 13 e vendor activitylog/permission instalados; pontos de boot do próprio repositório lidos.
- Bloqueio de login de inativados: ALTA — vendor Fortify lido linha a linha (eventos e exceções).
- Padrões de UI Inertia v3: ALTA para os padrões já existentes na Fase 1 (código do repo); MÉDIA para o detalhe fino do debounce (padrão estável da comunidade + skill do projeto; sem armadilha conhecida).
- Recomendação `givePermissionTo` vs `syncPermissions` no seeder: ALTA (comportamento documentado do sync) — mas é decisão de produto registrada como recomendação, não fato.

**Data da pesquisa:** 2026-06-10
**Válida até:** ~2026-07-10 (stack estável; revisão CNAE oficial prevista só para 2027)
