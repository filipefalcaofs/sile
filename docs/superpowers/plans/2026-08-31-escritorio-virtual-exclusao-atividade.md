# Escritório Virtual — exclusão de atividade econômica — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar a exclusão de atividade econômica para sede e abrigado de escritório virtual, incluindo a cascata da exclusão do CNAE que caracteriza a sede — perda da condição, desvinculação da inscrição, notificação aos abrigados e comunicação à SEFAZ.

**Architecture:** A solicitação de alteração de atividade passa a marcar a intenção por atividade (incluir ou excluir), o que hoje não existe. A exclusão defere automaticamente, sem validação de zoneamento. A exclusão do CNAE gatilho numa sede reaproveita integralmente o `DesvincularInscricaoService` e o registro `SefazNotification` já construídos — é o terceiro gatilho do serviço compartilhado, ao lado do encerramento e da mudança de endereço.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit via runner Pest, SQLite `:memory:` nos testes.

**Spec:** `docs/superpowers/specs/2026-08-28-escritorio-virtual-alteracao-atividade-design.md` (revisão 2) e `docs/superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md` (revisão 5)

## Global Constraints

- Comando de teste: `php artisan test --filter=<NomeDoTeste>`, sempre em **foreground**. Suíte em SQLite `:memory:`; o banco de desenvolvimento remoto é inacessível e desnecessário. O grupo `Postgis` falha por falta do container espacial — pré-existente, ignorar.
- Testes: classes no estilo PHPUnit em `tests/Feature/EscritorioVirtual/`, com `use LazilyRefreshDatabase;` e docblock em português explicando a regra coberta.
- Toda mensagem ao cidadão é parâmetro administrável: `Settings::get('<chave>', config('sile.<chave>'))`, com entrada em `config/sile.php` **e** linha em `database/seeders/ParameterSeeder.php`. A contagem esperada nos testes de seeder está em **100** — atualizar ao acrescentar, incluindo o comentário que discrimina a origem.
- O CNAE gatilho é parametrizável e tem fonte única em `SedeEscritorioVirtualGatilho::cnaeGatilho()`. `8211-3/00` não deve aparecer como literal em código de produção.
- Anti-fachada: dado ausente ou operação que não pode ser concluída automaticamente vai à análise com motivo registrado — nunca decisão inventada.
- Comentários e docblocks em português, densidade igual à do código ao redor.
- Mensagens de commit em português, imperativo, SEM acentuação no assunto.
- PROIBIDO qualquer marca de autoria de IA em commits, código ou comentários.
- `git add` por caminho explícito.

**Fora deste plano, por ausência de ponto de aplicação no sistema** (ver `[OPEN-AA-3]` e §Escopo abaixo): a prevenção efetiva da cobrança de TLL (não há módulo de taxa) e a atualização do cadastro `company_cnae` como consequência do deferimento (nada o escreve a partir de uma decisão).

**Fora deste plano, por decisão de escopo:** a inclusão de atividade (RN-AA-01/RN-AA-02) e a solicitação mista (RN-AA-06), esta última dependente de `[OPEN-AA-1]`.

## Escopo e o que "excluir" significa aqui

Decisão de leitura, e ela sustenta o plano: a solicitação de alteração de atividade **é** a declaração do novo conjunto de atividades. "Excluir" significa que a nova solicitação não contém aquele CNAE e que, deferida, o TVL vigente passa a refletir o conjunto sem ele. O cadastro da empresa no portal (`company_cnae`) é outra coisa, mantida pelo próprio cidadão, e não é tocado por deferimento nenhum hoje.

O que **este** sistema possui e portanto pode efetivar é a **condição de sede** — o vínculo da inscrição, os flags da decisão, o TVL dos abrigados. É aí que a cascata da RN-AA-04 atua, e é por isso que ela é implementável mesmo com os dois pontos de aplicação ausentes.

---

### Task 1: Marcação de intenção por atividade

O requisito pressupõe que o sistema distinga atividades incluídas de excluídas (RN-AA-06 item 1). Hoje `viability_request_cnaes` guarda só os CNAEs pedidos, com `is_primary`.

**Files:**
- Create: `database/migrations/2026_08_31_100000_add_intencao_to_viability_request_cnaes.php`
- Create: `app/Enums/IntencaoAtividade.php`
- Create: `tests/Feature/EscritorioVirtual/IntencaoAtividadeTest.php`
- Modify: `app/Models/ViabilityRequest.php`

**Interfaces:**
- Produces: `App\Enums\IntencaoAtividade` com `Incluir = 'incluir'` e `Excluir = 'excluir'`.
- Produces: coluna `viability_request_cnaes.intencao` (string, **nulável**). `null` = solicitação que não é alteração de atividade (primeiro estabelecimento, renovação): os CNAEs são simplesmente as atividades, sem intenção declarada.
- Produces: `ViabilityRequest::cnaesParaExcluir(): Collection<Cnae>` — os CNAEs do pivot marcados `Excluir`.
- Produces: `ViabilityRequest::exclusivamenteExclusao(): bool` — verdadeiro quando existe ao menos um CNAE no pivot e **todos** estão marcados `Excluir`.

Por que nulável em vez de um terceiro caso `Manter`: um CNAE que a solicitação não menciona não tem linha no pivot, então "manter" não precisa de valor. `null` distingue "esta solicitação não declara intenção por atividade" de "declara, e é incluir/excluir" — sem misturar as duas semânticas numa coluna só.

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/IntencaoAtividadeTest.php`:

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\IntencaoAtividade;
use App\Models\Cnae;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Intencao por atividade na solicitacao (RN-AA-05b): a alteracao de atividade
 * economica precisa distinguir o que esta sendo incluido do que esta sendo
 * excluido. O pivot guardava so `is_primary`. `null` na intencao significa
 * solicitacao que nao declara intencao por atividade — primeiro
 * estabelecimento, renovacao —, nao "manter".
 */
class IntencaoAtividadeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_solicitacao_sem_intencao_declarada_nao_tem_exclusoes(): void
    {
        $request = ViabilityRequest::factory()->create();
        $cnae = Cnae::factory()->create();
        $request->cnaes()->attach($cnae->id, ['is_primary' => true]);

        $this->assertTrue($request->cnaesParaExcluir()->isEmpty());
        $this->assertFalse($request->exclusivamenteExclusao());
    }

    public function test_devolve_apenas_os_cnaes_marcados_para_exclusao(): void
    {
        $request = ViabilityRequest::factory()->create();
        $manter = Cnae::factory()->create();
        $sair = Cnae::factory()->create();

        $request->cnaes()->attach($manter->id, ['is_primary' => true, 'intencao' => IntencaoAtividade::Incluir->value]);
        $request->cnaes()->attach($sair->id, ['is_primary' => false, 'intencao' => IntencaoAtividade::Excluir->value]);

        $this->assertSame([$sair->code], $request->cnaesParaExcluir()->pluck('code')->all());
    }

    public function test_exclusivamente_exclusao_quando_todos_saem(): void
    {
        $request = ViabilityRequest::factory()->create();

        foreach (Cnae::factory()->count(2)->create() as $i => $cnae) {
            $request->cnaes()->attach($cnae->id, ['is_primary' => $i === 0, 'intencao' => IntencaoAtividade::Excluir->value]);
        }

        $this->assertTrue($request->exclusivamenteExclusao());
    }

    public function test_solicitacao_mista_nao_e_exclusivamente_exclusao(): void
    {
        $request = ViabilityRequest::factory()->create();
        $entra = Cnae::factory()->create();
        $sai = Cnae::factory()->create();

        $request->cnaes()->attach($entra->id, ['is_primary' => true, 'intencao' => IntencaoAtividade::Incluir->value]);
        $request->cnaes()->attach($sai->id, ['is_primary' => false, 'intencao' => IntencaoAtividade::Excluir->value]);

        $this->assertFalse($request->exclusivamenteExclusao());
    }

    public function test_solicitacao_sem_cnae_nenhum_nao_e_exclusivamente_exclusao(): void
    {
        // Guarda contra a armadilha do "todos" sobre conjunto vazio, que em PHP
        // e verdadeiro e faria uma solicitacao vazia parecer isenta.
        $request = ViabilityRequest::factory()->create();

        $this->assertFalse($request->exclusivamenteExclusao());
    }
}
```

O último caso é o que impede o bug clássico do quantificador universal sobre conjunto vazio. Sem ele, uma solicitação sem atividade nenhuma passaria por "exclusivamente exclusão".

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=IntencaoAtividadeTest`
Expected: FAIL — enum, coluna e métodos inexistentes.

- [ ] **Step 3: Enum**

Criar `app/Enums/IntencaoAtividade.php`, backed por string, com os dois casos e docblock em português explicando a regra, no estilo dos enums existentes em `app/Enums/`.

- [ ] **Step 4: Migration**

Criar a migration acrescentando `intencao` (string, nulável) a `viability_request_cnaes`, depois de `is_primary`. Docblock explicando por que é nulável — a distinção entre "não declara intenção" e "declara".

- [ ] **Step 5: Model**

Em `app/Models/ViabilityRequest.php`, acrescentar os dois métodos. Cuidado no `exclusivamenteExclusao()`: exija ao menos uma linha antes de avaliar "todas".

Verifique se a relação `cnaes()` declara `withPivot` — se declarar, `intencao` precisa entrar na lista, senão o valor não vem carregado.

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test --filter=IntencaoAtividadeTest`
Expected: PASS, 5 testes.

- [ ] **Step 7: Rodar a suíte de EV**

Run: `php artisan test --filter=EscritorioVirtual`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_31_100000_add_intencao_to_viability_request_cnaes.php app/Enums/IntencaoAtividade.php app/Models/ViabilityRequest.php tests/Feature/EscritorioVirtual/IntencaoAtividadeTest.php
git commit -m "feat(ev): marca a intencao de incluir ou excluir por atividade"
```

---

### Task 2: Exclusão defere automaticamente, sem zoneamento

RN-AA-03 e RN-AA-05: a exclusão de atividade — em sede (CNAE diferente do gatilho) ou em abrigado — é deferida automaticamente e **não** passa por validação de zoneamento. Faz sentido: nada novo vai ser exercido no local.

**Files:**
- Create: `tests/Feature/EscritorioVirtual/ExclusaoAtividadeDeferimentoTest.php`
- Modify: `app/Services/Expresso/FluxoExpressoService.php`

**Interfaces:**
- Consumes: `ViabilityRequest::exclusivamenteExclusao()` (Task 1).
- Produces: nenhuma API nova — é um ramo de decisão no fluxo.

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/ExclusaoAtividadeDeferimentoTest.php`. Leia antes `tests/Feature/EscritorioVirtual/GatilhoSedeTest.php` e os testes de `tests/Feature/Expresso/` para ver como uma solicitação é levada ao fluxo expresso e como o desfecho é asseverado; reaproveite esse caminho.

Casos:

```php
    public function test_solicitacao_exclusivamente_de_exclusao_defere_sem_zoneamento(): void
    // solicitacao com todos os CNAEs marcados Excluir → deferida, e o
    // enquadramento LOUOS NAO e consultado (prove com um espiao no motor de
    // enquadramento, ou verificando que o resultado nao carrega veredito
    // locacional — escolha o caminho que o codigo permitir e explique no
    // relatorio)

    public function test_solicitacao_de_exclusao_defere_mesmo_sem_zona_identificada(): void
    // e o caso que prova a regra: zona pendente normalmente encaminha a
    // analise; numa exclusao, defere.

    public function test_solicitacao_comum_continua_passando_pelo_zoneamento(): void
    // guarda de escopo: sem marcacao de exclusao, nada muda.
```

O segundo caso é o que dá valor ao teste. Hoje, zona não identificada encaminha à análise (anti-fachada). Numa exclusão isso não deve acontecer, porque não há uso novo a avaliar — e é exatamente aí que a regra se distingue.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=ExclusaoAtividadeDeferimentoTest`
Expected: FAIL nos dois primeiros casos.

- [ ] **Step 3: Implementar o ramo**

Em `FluxoExpressoService`, no método que decide sob lock, acrescentar o ramo da exclusão **antes** da resolução do enquadramento — é o ponto em que a regra se distingue, e resolver o enquadramento para descartá-lo depois seria trabalho inútil e confuso de ler.

Cuidado com a ordem frente ao gatilho de sede: a exclusão do CNAE gatilho é tratada na Task 3 e **não** cai neste ramo. Neste ramo entram as exclusões que não envolvem o CNAE gatilho. Deixe a condição explícita e comentada, para que a Task 3 encaixe sem ambiguidade.

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test --filter=ExclusaoAtividadeDeferimentoTest`
Expected: PASS, 3 testes.

- [ ] **Step 5: Rodar as suítes afetadas**

Run: `php artisan test --filter=EscritorioVirtual`
Run: `php artisan test --filter=Expresso`
Expected: PASS nas duas.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Expresso/FluxoExpressoService.php tests/Feature/EscritorioVirtual/ExclusaoAtividadeDeferimentoTest.php
git commit -m "feat(ev): defere exclusao de atividade sem validar zoneamento"
```

---

### Task 3: Exclusão do CNAE gatilho — perda da condição de sede

RN-AA-04, e a peça de maior valor do plano. Antes de concluir, o sistema informa a consequência e pede confirmação:

> "A exclusão do CNAE 8211-3/00 fará com que a empresa deixe de ser caracterizada como Sede de Escritório Virtual. Deseja prosseguir com a exclusão?"

**Sim** → excluir; deferir automaticamente; retirar o enquadramento de sede; desvincular a inscrição; notificar os abrigados; comunicar à SEFAZ.
**Não** → não excluir; manter a sede; prosseguir com as demais atividades.

**Files:**
- Create: `database/migrations/2026_08_31_110000_add_confirma_perda_sede_to_viability_requests.php`
- Create: `tests/Feature/EscritorioVirtual/ExclusaoCnaeSedeTest.php`
- Modify: `app/Services/Expresso/FluxoExpressoService.php`
- Modify: `app/Services/EscritorioVirtual/DesvincularInscricaoService.php`
- Modify: `app/Enums/SefazNotificationEvent.php`
- Modify: `config/sile.php`
- Modify: `database/seeders/ParameterSeeder.php`
- Modify: `tests/Feature/Seeders/DatabaseSeederTest.php`
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php`

**Interfaces:**
- Consumes: `DesvincularInscricaoService::desvincular(VirtualOfficeInscriptionLock $lock, string $motivo, ?User $actor = null, SefazNotificationEvent $evento = SefazNotificationEvent::SedeEncerrada): array` — já existe, já notifica os abrigados e já registra a comunicação reprocessável à SEFAZ.
- Consumes: `SefazNotificationEvent::SedePerdeuCondicao` — já existe no enum.
- Produces: `viability_requests.confirma_perda_condicao_sede` (bool nulável) — `null` = ainda não perguntado, o que **não** é confirmação.

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/ExclusaoCnaeSedeTest.php`. Casos:

```php
    public function test_exclusao_do_cnae_gatilho_sem_confirmacao_nao_desvincula(): void
    // confirma_perda_condicao_sede = null → a solicitacao NAO defere a
    // exclusao do gatilho e a inscricao continua vinculada. `null` nao e "sim".

    public function test_confirmacao_negativa_mantem_a_sede(): void
    // false → o CNAE gatilho nao e excluido, o lock continua ativo.

    public function test_confirmacao_positiva_executa_a_cascata_inteira(): void
    // true → deferido; lock inativo; abrigados notificados; SefazNotification
    // criada com o evento SedePerdeuCondicao. Asseverar os quatro efeitos.

    public function test_falha_da_comunicacao_sefaz_nao_desfaz_a_perda_da_condicao(): void
    // o binding padrao do gateway lanca; a perda da condicao permanece e a
    // SefazNotification fica em Falha, reprocessavel (§4.3.3).
```

O terceiro caso é o coração. O quarto reaproveita a garantia que a onda anterior já construiu e prova que ela vale para este gatilho também — veja como `ComunicacaoSefazTest` o monta.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=ExclusaoCnaeSedeTest`
Expected: FAIL — coluna e ramo inexistentes.

- [ ] **Step 3: Migration da confirmação**

Criar a migration acrescentando `confirma_perda_condicao_sede` (bool nulável) a `viability_requests`. Docblock explicando que `null` significa "ainda não perguntado" e **nunca** equivale a confirmação — mesma razão da coluna `wants_virtual_office_tenant`.

- [ ] **Step 4: Parâmetro do texto da confirmação**

Acrescentar `analise.escritorio_virtual.mensagem_confirma_perda_sede` em `config/sile.php` e no `ParameterSeeder`, com o texto verbatim acima. O texto cita o CNAE — use o marcador `:cnae`, substituído pelo gatilho vigente, no mesmo padrão das mensagens irmãs. Atualizar a contagem nos dois testes de seeder.

- [ ] **Step 5: Implementar a cascata**

Em `FluxoExpressoService`, no ramo da exclusão que a Task 2 deixou preparado: quando a solicitação exclui o CNAE gatilho **e** a inscrição tem sede ativa:

- `confirma_perda_condicao_sede !== true` → **não** defere. Encaminha à análise com o motivo da confirmação pendente. É o caminho anti-fachada: sem a confirmação explícita do requerente, o sistema não retira condição cadastral por conta própria.
- `=== true` → defere e chama `DesvincularInscricaoService::desvincular()` com o motivo da exclusão do CNAE e o evento `SefazNotificationEvent::SedePerdeuCondicao`.

O serviço já cuida de desativar o lock, notificar cada abrigado e registrar a comunicação reprocessável. **Não reimplemente nada disso** — é o terceiro gatilho do serviço compartilhado, e duplicar a lógica é exatamente o que a RN-EV-06 do motor proíbe.

Verifique se `desvincular()` precisa de ajuste para receber o evento — a assinatura já o aceita como último parâmetro com default.

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test --filter=ExclusaoCnaeSedeTest`
Expected: PASS, 4 testes.

- [ ] **Step 7: Rodar as suítes afetadas**

Run: `php artisan test --filter=EscritorioVirtual`
Run: `php artisan test --filter=Expresso`
Run: `php artisan test --filter=Seeders`
Expected: PASS nas três.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_31_110000_add_confirma_perda_sede_to_viability_requests.php app/Services/Expresso/FluxoExpressoService.php app/Services/EscritorioVirtual/DesvincularInscricaoService.php app/Enums/SefazNotificationEvent.php config/sile.php database/seeders/ParameterSeeder.php tests/Feature/EscritorioVirtual/ExclusaoCnaeSedeTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Seeders/ParameterSeederTest.php
git commit -m "feat(ev): retira condicao de sede na exclusao do cnae gatilho"
```

---

## Self-Review

**Cobertura da spec.** Cobre RN-AA-03, RN-AA-04, RN-AA-05, RN-AA-05b e a parte de comunicação da RN-AA-09. Fica de fora, com motivo declarado: RN-AA-08 (isenção de TLL — a derivação `exclusivamenteExclusao()` entra na Task 1, mas não há ponto de aplicação, `[OPEN-AA-3]`); a atualização de `company_cnae` da RN-AA-09 (nada a escreve a partir de uma decisão); RN-AA-01 e RN-AA-02 (inclusão, agora desbloqueada por `[OPEN-EV-7]`, mas fora deste recorte); RN-AA-06 e RN-AA-07 (solicitação mista, dependem de `[OPEN-AA-1]`).

**Consistência de tipos.** `exclusivamenteExclusao()` e `cnaesParaExcluir()` da Task 1 são consumidos pelas Tasks 2 e 3. O ramo que a Task 2 cria em `FluxoExpressoService` é onde a Task 3 encaixa — as duas mexem no mesmo método, então são estritamente sequenciais. `SefazNotificationEvent::SedePerdeuCondicao` e a assinatura de `desvincular()` com parâmetro de evento já existem, entregues no plano anterior.

**Risco conhecido.** A Task 3 depende de a exclusão do CNAE gatilho ser detectável antes da decisão, o que exige que a marcação da Task 1 esteja preenchida. Nenhuma tela do portal preenche `intencao` ainda — como acontece com a pergunta geral de escritório virtual, a regra fica correta no dado e inalcançável pela interface até a tela existir. Isso é consequência de `[OPEN-EV-8]` e do desenho da tela de alteração de atividade, não deste plano; os testes exercitam o comportamento pela factory. Registrar no relatório final para não parecer entrega completa.

**Uma armadilha que o plano trata de propósito.** `exclusivamenteExclusao()` sobre conjunto vazio: "todos os elementos satisfazem P" é verdadeiro para conjunto vazio em qualquer linguagem, e aqui isso faria uma solicitação sem atividade nenhuma parecer isenta de TLL. A Task 1 tem caso de teste dedicado.
