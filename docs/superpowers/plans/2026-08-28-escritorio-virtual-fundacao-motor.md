# Escritório Virtual — fundação do motor — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar a camada de domínio compartilhada de escritório virtual exigida pelo pacote normativo SEDUR 20.08.26 — duas listas de atividade, o terceiro ponto da trava de inscrição, os dois logs de integração SEFAZ e a notificação aos abrigados.

**Architecture:** Estende o que já existe. `virtual_office_activity_cnaes` ganha discriminador de anexo em vez de virar duas tabelas. Os dois sentidos da SEFAZ ficam em contratos separados com tratamentos opostos de indisponibilidade: consulta bloqueia a decisão, comunicação não desfaz deferimento. A notificação reaproveita `notifications` + `NotificationDispatcher`.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit via Pest runner (classes no estilo PHPUnit), SQLite `:memory:` nos testes, PostgreSQL em produção.

**Spec:** `docs/superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md` (revisão 4)

## Global Constraints

- Comando canônico de teste: `php artisan test --filter=<NomeDoTeste>`. A suíte roda em SQLite `:memory:`; o banco de desenvolvimento remoto **não** é necessário.
- Testes são classes PHPUnit em `tests/Feature/EscritorioVirtual/`, com `use LazilyRefreshDatabase;` e docblock explicando a RN coberta. Seguir o estilo de `AbrigadoCnaeBlockTest.php`.
- Toda parametrização é **dado versionado** por `RuleVersion` (RN-002), nunca constante em código.
- Anti-fachada: dado ausente ou integração indisponível **nunca** produz decisão automática. Vai para análise com motivo registrado.
- Sem atribuição de IA em commits, código ou comentários.
- Mensagens de commit em português, imperativo, sem acentuação no assunto (padrão do repositório).

**Fora deste plano, por decisão pendente da SEDUR:** validação do Anexo A na sede (`[OPEN-EV-7]`), campo de identificação da sede — CNPJ ou nº de TVL (`[OPEN-EV-10]`), comportamento com inscrição imobiliária ausente (`[OPEN-EV-11]`). Nenhuma tarefa aqui depende dessas respostas.

---

### Task 1: Discriminador de anexo nas listas de atividade

Hoje há uma lista só, usada para o abrigado. O pacote traz duas: Anexo A (6 CNAEs, sede) e Anexo B (319 CNAEs, abrigado). Os CSVs já estão extraídos dos PDFs de origem.

**Files:**
- Create: `database/migrations/2026_08_28_100000_add_anexo_to_virtual_office_activity_cnaes.php`
- Create: `tests/Feature/EscritorioVirtual/AnexoListaEvTest.php`
- Modify: `app/Models/VirtualOfficeActivityCnae.php`
- Modify: `app/Services/EscritorioVirtual/EscritorioVirtualCnaeImportService.php`
- Modify: `database/seeders/EscritorioVirtualCnaeSeeder.php`
- Já existem: `database/data/escritorio-virtual/anexo-a-sede.csv`, `database/data/escritorio-virtual/anexo-b-abrigado.csv`

**Interfaces:**
- Produces: `VirtualOfficeActivityCnae::permitidoNoAnexo(string $cnaeCode, string $anexo): bool` e as constantes `ANEXO_A = 'A'` / `ANEXO_B = 'B'`. O método existente `permitido(string $cnaeCode): bool` passa a delegar para o Anexo B, preservando os call sites atuais (`AbrigadoResolver`, `UpdateSolicitacaoAtividadesRequest`).
- Produces: `EscritorioVirtualCnaeImportService::import(RuleVersion $version, string $csvPath, string $anexo): array{importados: int}` — ganha o terceiro parâmetro.

- [ ] **Step 1: Escrever o teste que falha**

Criar `tests/Feature/EscritorioVirtual/AnexoListaEvTest.php`:

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\VirtualOfficeActivityCnae;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Duas listas de atividade versionadas (RN-EV-05/07, revisão 4): Anexo A vale
 * para a SEDE (6 CNAEs) e Anexo B para o ABRIGADO (319 CNAEs). O mesmo CNAE
 * pode constar dos dois; `permitido()` mantém a semântica antiga (Anexo B)
 * para não quebrar os call sites existentes.
 */
class AnexoListaEvTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EscritorioVirtualCnaeSeeder::class);
    }

    public function test_anexo_a_tem_os_seis_cnaes_da_sede(): void
    {
        $this->assertSame(6, VirtualOfficeActivityCnae::query()
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->count());
    }

    public function test_anexo_b_tem_os_cnaes_do_abrigado(): void
    {
        $this->assertSame(319, VirtualOfficeActivityCnae::query()
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_B)
            ->count());
    }

    public function test_cnae_da_sede_nao_vale_como_anexo_b_quando_ausente_dele(): void
    {
        // 6920-6/01 (contabilidade) consta do Anexo A e nao do Anexo B.
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('6920-6/01', VirtualOfficeActivityCnae::ANEXO_A));
        $this->assertFalse(VirtualOfficeActivityCnae::permitidoNoAnexo('6920-6/01', VirtualOfficeActivityCnae::ANEXO_B));
    }

    public function test_cnae_presente_nos_dois_anexos_vale_nos_dois(): void
    {
        // 8219-9/99 consta do Anexo A e do Anexo B.
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('8219-9/99', VirtualOfficeActivityCnae::ANEXO_A));
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('8219-9/99', VirtualOfficeActivityCnae::ANEXO_B));
    }

    public function test_permitido_mantem_semantica_de_abrigado(): void
    {
        $this->assertTrue(VirtualOfficeActivityCnae::permitido('8219-9/99'));
        $this->assertFalse(VirtualOfficeActivityCnae::permitido('6920-6/01'));
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --filter=AnexoListaEvTest`
Expected: FAIL — coluna `anexo` inexistente e `permitidoNoAnexo` não definido.

- [ ] **Step 3: Migration do discriminador**

Criar `database/migrations/2026_08_28_100000_add_anexo_to_virtual_office_activity_cnaes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duas listas de atividade em vez de uma (pacote normativo SEDUR 20.08.26):
 * Anexo A do Decreto 35.062/2021 vale para a SEDE, Anexo B para o ABRIGADO.
 * O mesmo CNAE pode constar dos dois, entao a unicidade passa a incluir o
 * anexo. As linhas ja carregadas sao do Anexo B (Lista EV original).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->string('anexo', 1)->default('B')->after('rule_version_id');
        });

        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->dropUnique(['rule_version_id', 'cnae_code']);
            $table->unique(['rule_version_id', 'anexo', 'cnae_code']);
        });
    }

    public function down(): void
    {
        Schema::table('virtual_office_activity_cnaes', function (Blueprint $table): void {
            $table->dropUnique(['rule_version_id', 'anexo', 'cnae_code']);
            $table->unique(['rule_version_id', 'cnae_code']);
            $table->dropColumn('anexo');
        });
    }
};
```

- [ ] **Step 4: Model com os dois anexos**

Em `app/Models/VirtualOfficeActivityCnae.php`, adicionar as constantes, incluir `anexo` no `#[Fillable]`, e substituir `permitido()` por delegação:

```php
    public const ANEXO_A = 'A';

    public const ANEXO_B = 'B';

    /**
     * O CNAE consta do anexo informado na versao VIGENTE do dominio.
     * Anexo A = atividades da SEDE; Anexo B = atividades do ABRIGADO.
     */
    public static function permitidoNoAnexo(string $cnaeCode, string $anexo): bool
    {
        $digitos = preg_replace('/\D/', '', $cnaeCode) ?? '';

        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->first();

        if ($vigente === null) {
            return false;
        }

        return static::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', $anexo)
            ->where('cnae_code', $digitos)
            ->exists();
    }

    /**
     * Semantica historica: "permitido em escritorio virtual" e a pergunta do
     * ABRIGADO (Anexo B). Preservado para os call sites existentes.
     */
    public static function permitido(string $cnaeCode): bool
    {
        return static::permitidoNoAnexo($cnaeCode, self::ANEXO_B);
    }
```

O `#[Fillable]` passa a `['rule_version_id', 'anexo', 'cnae_code', 'cnae_description']`.

- [ ] **Step 5: Import parametrizado por anexo**

Em `EscritorioVirtualCnaeImportService::import()`, acrescentar o parâmetro `string $anexo` após `$csvPath`, incluir `'anexo' => $anexo` no array de cada linha, e trocar a chave do upsert:

```php
            VirtualOfficeActivityCnae::query()->upsert(
                $chunk,
                ['rule_version_id', 'anexo', 'cnae_code'],
                ['cnae_description'],
            );
```

Atualizar o docblock da classe: deixa de ser "Lista EV (CNAEs do abrigado)" e passa a "listas de atividade por anexo do Decreto 35.062/2021".

- [ ] **Step 6: Seeder carrega os dois anexos**

Em `EscritorioVirtualCnaeSeeder::run()`, substituir a chamada única de import por duas, e somar os relatórios:

```php
        $relatorioB = app(EscritorioVirtualCnaeImportService::class)->import(
            $version,
            database_path('data/escritorio-virtual/anexo-b-abrigado.csv'),
            VirtualOfficeActivityCnae::ANEXO_B,
        );

        $relatorioA = app(EscritorioVirtualCnaeImportService::class)->import(
            $version,
            database_path('data/escritorio-virtual/anexo-a-sede.csv'),
            VirtualOfficeActivityCnae::ANEXO_A,
        );
```

O log de auditoria existente passa a registrar os dois contadores (`anexo_a`, `anexo_b`). O CSV antigo `atividades-permitidas.csv` deixa de ser lido; **não** apagar o arquivo nesta tarefa (é o snapshot histórico da versão anterior).

Nota para o implementador: o Anexo B foi extraído do PDF só com os códigos — a descrição vem do cadastro de CNAE, que é a fonte oficial dela. `cnae_description` vazio no CSV é esperado e o import já trata como `null`.

- [ ] **Step 7: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=AnexoListaEvTest`
Expected: PASS, 5 testes.

- [ ] **Step 8: Rodar a suíte de EV inteira, para pegar regressão nos call sites**

Run: `php artisan test --filter=EscritorioVirtual`
Expected: PASS. Atenção a `AbrigadoCnaeBlockTest` e `ListaEvImportTest`, que consomem `permitido()` e o import — se `ListaEvImportTest` chamar `import()` com dois argumentos, atualizar a chamada para passar `ANEXO_B`.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_28_100000_add_anexo_to_virtual_office_activity_cnaes.php app/Models/VirtualOfficeActivityCnae.php app/Services/EscritorioVirtual/EscritorioVirtualCnaeImportService.php database/seeders/EscritorioVirtualCnaeSeeder.php database/data/escritorio-virtual/anexo-a-sede.csv database/data/escritorio-virtual/anexo-b-abrigado.csv tests/Feature/EscritorioVirtual/AnexoListaEvTest.php
git commit -m "feat(ev): separa listas de atividade por anexo do decreto 35.062"
```

---

### Task 2: Terceiro ponto da trava de inscrição

`Constituição` §10.1: numa inscrição com sede ativa, quem responde "Não" à pergunta geral é indeferido com orientação para se abrigar. Hoje a trava só impede segunda sede e exige sede para abrigado.

**Files:**
- Create: `tests/Feature/EscritorioVirtual/RecusaAbrigoEmInscricaoTravadaTest.php`
- Modify: `app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php`

**Interfaces:**
- Consumes: `VirtualOfficeInscriptionLock::sedeAtiva(string $propertyRegistration): ?self` (já existe).
- Produces: nenhuma API nova — é regra de validação no protocolo do portal.

- [ ] **Step 1: Escrever o teste que falha**

Criar `tests/Feature/EscritorioVirtual/RecusaAbrigoEmInscricaoTravadaTest.php`. Copiar de `AbrigadoCnaeBlockTest.php` o `setUp`, o helper `portalUser()` e o helper de rascunho — **não** importar de lá; repetir, porque os testes são lidos isoladamente.

Três casos:

```php
    public function test_recusar_abrigo_em_inscricao_com_sede_ativa_bloqueia(): void
    {
        // inscricao travada por sede ativa + resposta "Nao" a pergunta geral
        // → 422 com a mensagem de orientacao para se abrigar.
    }

    public function test_recusar_abrigo_em_inscricao_sem_sede_nao_bloqueia(): void
    {
        // mesma resposta "Nao", inscricao livre → passa.
    }

    public function test_aceitar_abrigo_em_inscricao_com_sede_ativa_nao_bloqueia(): void
    {
        // resposta "Sim" → segue para as demais validacoes (Anexo B).
    }
```

A mensagem esperada, verbatim do requisito: *"Inscrição imobiliária vinculada a uma sede de escritório virtual. Para exercer atividades nesse local, deverá ser abrigado da sede vinculada."*

Nota: o campo que carrega a resposta da pergunta geral ainda é `wants_virtual_office_hq` no schema atual. A revisão 4 prevê substituí-lo por um campo de intenção de três estados, mas **essa troca não está neste plano** — ela depende de `[OPEN-EV-10]`. Usar o campo atual e deixar comentário `// TODO revisao 4: trocar por campo de intencao quando OPEN-EV-10 fechar` **não** é aceitável; em vez disso, escrever a validação lendo o campo atual e registrar a dependência no docblock do teste.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=RecusaAbrigoEmInscricaoTravadaTest`
Expected: FAIL — a resposta é 200/302 em vez de 422 no primeiro caso.

- [ ] **Step 3: Implementar a validação**

Em `UpdateSolicitacaoAtividadesRequest::after()`, no closure que já trata a inscrição travada, acrescentar o ramo da recusa antes da checagem de CNAEs do Anexo B: com `sedeAtiva($inscricao)` não nulo e resposta negativa à pergunta geral, adicionar o erro com a mensagem parametrizada.

Ler o texto de `Settings::get('escritorio_virtual.mensagem.recusa_abrigo', <default verbatim>)`, no mesmo padrão da mensagem de bloqueio já existente.

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test --filter=RecusaAbrigoEmInscricaoTravadaTest`
Expected: PASS, 3 testes.

- [ ] **Step 5: Rodar a suíte de EV**

Run: `php artisan test --filter=EscritorioVirtual`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/EscritorioVirtual/RecusaAbrigoEmInscricaoTravadaTest.php app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php
git commit -m "feat(ev): bloqueia recusa de abrigo em inscricao com sede ativa"
```

---

### Task 3: Log e contrato da consulta SEFAZ

Sentido de entrada, novo. O contrato consulta por CNPJ e devolve a cadeia; a indisponibilidade **bloqueia** a decisão.

**Files:**
- Create: `database/migrations/2026_08_28_110000_create_sefaz_hq_queries_table.php`
- Create: `app/Models/SefazHqQuery.php`
- Create: `app/Services/Sefaz/SefazHqLookup.php` (interface)
- Create: `app/Services/Sefaz/SefazHqLookupResult.php` (DTO readonly)
- Create: `app/Services/Sefaz/UnavailableSefazHqLookup.php`
- Create: `tests/Feature/EscritorioVirtual/ConsultaSedeSefazTest.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding)

**Interfaces:**
- Produces: `SefazHqLookup::lookup(string $cnpj): SefazHqLookupResult`, lançando `SefazUnavailableException` quando a API não responde.
- Produces: `SefazHqLookupResult` readonly com `status` ∈ {`confirmada`, `cnpj_nao_localizado`, `sem_viabilidade`, `nao_e_sede`, `retorno_incompleto`}, mais `viabilidade` e `inscricaoImobiliaria` nuláveis. A divergência de inscrição **não** é status do gateway: é comparação de quem chama, porque só o chamador conhece a inscrição da solicitação.
- Produces: `SefazHqQuery` — registro de auditoria com os 9 campos do `Constituição` §11.

Detalhamento dos passos: escrever o teste dos cinco status mais o caminho de indisponibilidade; rodar e ver falhar; criar migration, model, DTO, interface e a implementação `Unavailable` que sempre lança; registrar o binding; rodar e ver passar; commit.

O `UnavailableSefazHqLookup` é o binding **padrão** até a SEDUR homologar o endpoint — mesmo padrão de `UnavailableSefazViabilidadeGateway`. Isso é deliberado: sem endpoint, o fluxo de abrigado fica bloqueado e visível, nunca fingindo sucesso.

---

### Task 4: Log da comunicação SEFAZ com reprocessamento

Sentido de saída. Falha **não** desfaz deferimento.

**Files:**
- Create: `database/migrations/2026_08_28_120000_create_sefaz_notifications_table.php`
- Create: `app/Models/SefazNotification.php`
- Create: `tests/Feature/EscritorioVirtual/ComunicacaoSefazTest.php`
- Modify: `app/Services/Sefaz/SefazViabilidadeGateway.php` (novo método de evento)
- Modify: `app/Services/Sefaz/UnavailableSefazViabilidadeGateway.php`
- Modify: `app/Services/EscritorioVirtual/DesvincularInscricaoService.php`

**Interfaces:**
- Consumes: `DesvincularInscricaoService::desvincular(VirtualOfficeInscriptionLock $lock, string $motivo, ?User $actor = null): array` (já existe).
- Produces: `SefazNotification` com os campos do `Alteração de Endereço` §4.3.2 mais `status` ∈ {`pendente`, `enviada`, `falha`} e `tentativas`.
- Produces: `SefazViabilidadeGateway::notifyEvent(SefazNotification $notification): void`.

O teste central é o CA-12 do motor: deferimento concluído + comunicação que falha ⇒ o deferimento **permanece**, a `SefazNotification` fica em `falha` com o código de erro, e o reprocessamento é possível. Escrever com o gateway forjado para lançar.

---

### Task 5: Abrigados ativos de uma sede e notificação

**Files:**
- Create: `tests/Feature/EscritorioVirtual/NotificacaoAbrigadosTest.php`
- Modify: `app/Services/EscritorioVirtual/AbrigadoResolver.php` (consulta inversa)
- Modify: `app/Services/EscritorioVirtual/DesvincularInscricaoService.php`

**Interfaces:**
- Consumes: `NotificationDispatcher` (já existe).
- Produces: `AbrigadoResolver::abrigadosAtivos(VirtualOfficeInscriptionLock $lock): Collection<ViabilityRequest>`.

O teste cobre o CA-06 do motor: sede sai da inscrição ⇒ todos os abrigados ativos recebem notificação individual com o texto do `Alteração de Endereço` §3.3.1, e **nenhum** é transferido ou cassado (RN-AE-04).

---

## Self-Review

**Cobertura da spec.** Este plano cobre RN-EV-03 (ponto 3), RN-EV-05 (duas listas), RN-EV-07 (versionamento), RN-EV-08 (contrato e log de consulta), RN-EV-09 (comunicação e reprocessamento), RN-EV-10 (rastreabilidade) e a parte de notificação da RN-EV-06. Ficam de fora, por dependerem de `[OPEN-EV-7]`, `[OPEN-EV-10]` e `[OPEN-EV-11]`: o campo de intenção de três estados (RN-EV-01), a validação do Anexo A na sede e a tela de identificação da sede.

**Consistência de tipos.** `permitidoNoAnexo` e as constantes `ANEXO_A`/`ANEXO_B` da Task 1 são consumidas pela Task 2. `SefazHqLookupResult` da Task 3 e `SefazNotification` da Task 4 não se cruzam — são sentidos opostos, deliberadamente separados. `AbrigadoResolver::abrigadosAtivos` da Task 5 consome o `VirtualOfficeInscriptionLock` que a Task 2 já usa.

**Granularidade.** As tarefas 1 e 2 estão detalhadas passo a passo com código. As tarefas 3 a 5 estão em nível de interface e critério, não de código linha a linha — elas dependem de decisões de shape que o implementador da tarefa anterior pode informar melhor. Detalhar antes de a Task 1 rodar seria inventar precisão.
