# Escritório Virtual — constituição de sede — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o fluxo de constituição de sede de escritório virtual — validação das atividades permitidas à sede, os indeferimentos automáticos parametrizados e o encaminhamento à análise com a flag do §2º art. 6º do Decreto 35.062/2021.

**Architecture:** Estende o que existe. As três regras que o requisito descreve como "indeferir automaticamente **e impedir o prosseguimento**" viram bloqueio no protocolo, no mesmo ponto e no mesmo padrão da regra irmã do abrigado já implementada. A regra de zona e via, que o requisito descreve só como "indeferir automaticamente", é decisão dentro do fluxo expresso. O gatilho de análise já existe e ganha o texto da flag.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit via runner Pest, SQLite `:memory:` nos testes.

**Spec:** `docs/superpowers/specs/2026-08-28-escritorio-virtual-constituicao-design.md` (revisão 2) e `docs/superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md` (revisão 5)

## Global Constraints

- Comando de teste: `php artisan test --filter=<NomeDoTeste>`, sempre em **foreground**. Suíte em SQLite `:memory:`; o banco de desenvolvimento remoto é inacessível e desnecessário. O grupo `Postgis` falha neste ambiente por falta do container espacial — pré-existente, ignorar.
- Testes: classes no estilo PHPUnit em `tests/Feature/EscritorioVirtual/`, com `use LazilyRefreshDatabase;` e docblock em português explicando a regra coberta. Estilo de referência: `tests/Feature/EscritorioVirtual/AbrigadoCnaeBlockTest.php`.
- Toda mensagem ao cidadão é parâmetro administrável: `Settings::get('<chave>', config('sile.<chave>'))`, com entrada em `config/sile.php` **e** linha em `database/seeders/ParameterSeeder.php`. Nunca literal solto. Padrão de referência: `analise.escritorio_virtual.mensagem_bloqueio_abrigado`.
- Ao acrescentar parâmetro, atualizar a contagem esperada em `tests/Feature/Seeders/DatabaseSeederTest.php` e `tests/Feature/Seeders/ParameterSeederTest.php`.
- Anti-fachada: dado ausente ou integração indisponível nunca produz decisão automática — vai à análise com motivo registrado.
- Comentários e docblocks em português, densidade igual à do código ao redor.
- Mensagens de commit em português, imperativo, SEM acentuação no assunto.
- PROIBIDO qualquer marca de autoria de IA em commits, código ou comentários.
- `git add` por caminho explícito. A árvore tem arquivos modificados de trabalhos anteriores desta branch que não pertencem a este plano.

**Fora deste plano:** o fluxo de abrigado (depende do REGIN e de `[OPEN-EV-12]`/`[OPEN-EV-13]`), a alteração de endereço e a de atividade.

---

### Task 1: Atividades permitidas à sede

Duas coisas indivisíveis, porque a segunda depende da primeira.

**A primeira** é uma correção. `ViabilityRequest::virtualOfficeIntent()` exige `wants_virtual_office_tenant === false` para concluir `Sede`. Toda solicitação anterior à migration que criou essa coluna tem `tenant = null`, então uma sede legada (`hq = true`, `tenant = null`) é classificada como `Nenhum`. A SEDUR confirmou em 2026-08-31 que o que caracteriza a sede é o CNAE 8211-3/00 **mais** a resposta "Sim" à pergunta vinculada — a pergunta geral não entra nessa caracterização. E `SedeEscritorioVirtualGatilho::aplica()` já opera assim, lendo só `wants_virtual_office_hq`. Hoje as duas leituras divergem para dado legado.

**A segunda** é a regra nova: o conjunto de atividades que uma sede pode exercer é `{8211-3/00} ∪ Anexo A`. O 8211-3/00 caracteriza a sede e por isso não figura no Anexo A — precisa ser excluído da conferência, senão toda sede é indeferida pelo próprio CNAE que a define.

**Files:**
- Create: `app/Services/EscritorioVirtual/SedeAtividadesResolver.php`
- Create: `tests/Feature/EscritorioVirtual/SedeAtividadesResolverTest.php`
- Modify: `app/Models/ViabilityRequest.php`
- Modify: `tests/Feature/EscritorioVirtual/IntencaoEscritorioVirtualTest.php`

**Interfaces:**
- Consumes: `VirtualOfficeActivityCnae::permitidoNoAnexo(string $cnaeCode, string $anexo): bool` e as constantes `ANEXO_A` / `ANEXO_B`.
- Consumes: `SedeEscritorioVirtualGatilho::temCnaeGatilho(ViabilityRequest $request): bool` e o parâmetro `analise.escritorio_virtual.cnae_gatilho_sede` (default `8211-3/00`).
- Produces: `SedeAtividadesResolver::naoPermitidos(iterable $cnaeCodes): array<int, string>` — devolve os códigos que **não** podem ser exercidos por uma sede, na ordem de entrada, já normalizados como vieram. Lista vazia = todas permitidas.
- Produces: `SedeAtividadesResolver::permitida(string $cnaeCode): bool`.

- [ ] **Step 1: Escrever o teste da derivação corrigida**

Em `tests/Feature/EscritorioVirtual/IntencaoEscritorioVirtualTest.php`, substituir o caso `test_pergunta_geral_nao_respondida_nao_e_escritorio_virtual` — que asseverava o comportamento antigo — por:

```php
    /**
     * Sede legada: a coluna da pergunta geral so passou a existir na migration
     * de 2026-08-28, entao toda solicitacao anterior tem `tenant = null`. O que
     * caracteriza a sede e o CNAE 8211-3/00 mais o "Sim" na pergunta vinculada
     * (SEDUR 2026-08-31) — a pergunta geral nao entra nessa caracterizacao.
     * Sem isto, sede legada seria reclassificada como "nenhum" e divergiria do
     * SedeEscritorioVirtualGatilho, que le so a pergunta vinculada.
     */
    public function test_sede_legada_sem_resposta_na_pergunta_geral_continua_sede(): void
    {
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => null,
            'wants_virtual_office_hq' => true,
        ]);

        $this->assertSame(VirtualOfficeIntent::Sede, $request->virtualOfficeIntent());
    }

    /**
     * Sem nenhuma das duas respostas afirmativas, nao e escritorio virtual.
     */
    public function test_sem_resposta_e_sem_pergunta_vinculada_nao_e_escritorio_virtual(): void
    {
        $request = ViabilityRequest::factory()->create([
            'wants_virtual_office_tenant' => null,
            'wants_virtual_office_hq' => false,
        ]);

        $this->assertSame(VirtualOfficeIntent::Nenhum, $request->virtualOfficeIntent());
    }
```

Os outros três casos do arquivo continuam válidos e não devem ser tocados.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=IntencaoEscritorioVirtualTest`
Expected: FAIL no caso da sede legada — devolve `Nenhum` em vez de `Sede`.

- [ ] **Step 3: Corrigir a derivação**

Em `app/Models/ViabilityRequest.php`, no método `virtualOfficeIntent()`, trocar a condição de sede de `$this->wants_virtual_office_tenant === false && $this->wants_virtual_office_hq` para `$this->wants_virtual_office_tenant !== true && $this->wants_virtual_office_hq`, e ajustar o docblock explicando que `null` (dado legado, pergunta geral ainda não existia) não impede a caracterização de sede, porque quem caracteriza é a pergunta vinculada.

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test --filter=IntencaoEscritorioVirtualTest`
Expected: PASS, 6 testes.

- [ ] **Step 5: Escrever o teste do resolvedor de atividades**

Criar `tests/Feature/EscritorioVirtual/SedeAtividadesResolverTest.php`:

```php
<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Services\EscritorioVirtual\SedeAtividadesResolver;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Atividades que uma SEDE de escritorio virtual pode exercer (RN-EV-05c):
 * {8211-3/00} uniao Anexo A. O 8211-3/00 caracteriza a sede e por isso nao
 * figura no Anexo A — precisa ser excluido da conferencia, senao toda sede
 * seria indeferida pelo proprio CNAE que a define (SEDUR 2026-08-31).
 */
class SedeAtividadesResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SedeAtividadesResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->resolver = app(SedeAtividadesResolver::class);
    }

    public function test_o_cnae_que_caracteriza_a_sede_e_permitido(): void
    {
        $this->assertTrue($this->resolver->permitida('8211-3/00'));
        $this->assertSame([], $this->resolver->naoPermitidos(['8211-3/00']));
    }

    public function test_atividade_do_anexo_a_e_permitida(): void
    {
        $this->assertTrue($this->resolver->permitida('6920-6/01'));
    }

    public function test_atividade_so_do_anexo_b_nao_e_permitida_a_sede(): void
    {
        // 8630-5/99 consta do Anexo B (abrigado) e nao do Anexo A (sede).
        $this->assertFalse($this->resolver->permitida('8630-5/99'));
    }

    public function test_devolve_todos_os_cnaes_reprovados_na_ordem_de_entrada(): void
    {
        $this->assertSame(
            ['8630-5/99', '9999-9/99'],
            $this->resolver->naoPermitidos(['8211-3/00', '8630-5/99', '6920-6/01', '9999-9/99']),
        );
    }

    public function test_conjunto_valido_completo_nao_reprova_nada(): void
    {
        $this->assertSame([], $this->resolver->naoPermitidos(['8211-3/00', '6920-6/01', '8219-9/99']));
    }
}
```

- [ ] **Step 6: Rodar e confirmar que falha**

Run: `php artisan test --filter=SedeAtividadesResolverTest`
Expected: FAIL — classe inexistente.

- [ ] **Step 7: Implementar o resolvedor**

Criar `app/Services/EscritorioVirtual/SedeAtividadesResolver.php`. Ele injeta `SedeEscritorioVirtualGatilho` para descobrir qual é o CNAE gatilho — o código é parametrizável (`analise.escritorio_virtual.cnae_gatilho_sede`) e não pode ser constante aqui. Como `temCnaeGatilho()` recebe uma `ViabilityRequest` e aqui só temos códigos, leia o parâmetro diretamente pelo mesmo caminho que o gatilho usa, normalizando a dígitos para comparar.

Regra: um código é permitido se for o CNAE gatilho **ou** constar do Anexo A na versão vigente. `naoPermitidos()` preserva a ordem e a grafia de entrada.

Docblock explicando a união e o porquê da exceção, citando a resposta da SEDUR de 2026-08-31.

- [ ] **Step 8: Rodar e confirmar que passa**

Run: `php artisan test --filter=SedeAtividadesResolverTest`
Expected: PASS, 5 testes.

- [ ] **Step 9: Rodar a suíte de EV**

Run: `php artisan test --filter=EscritorioVirtual`
Expected: PASS. Atenção a `GatilhoSedeTest` e `SedePerguntaPortalTest`: a mudança da derivação pode afetá-los. Se falharem, verifique se o teste asseverava o comportamento antigo para `tenant = null` — nesse caso atualize e explique no relatório.

- [ ] **Step 10: Commit**

```bash
git add app/Services/EscritorioVirtual/SedeAtividadesResolver.php app/Models/ViabilityRequest.php tests/Feature/EscritorioVirtual/SedeAtividadesResolverTest.php tests/Feature/EscritorioVirtual/IntencaoEscritorioVirtualTest.php
git commit -m "feat(ev): resolve atividades permitidas a sede de escritorio virtual"
```

---

### Task 2: Bloqueios de protocolo na constituição de sede

Três regras que o requisito descreve com o mesmo par de verbos — "indeferir automaticamente" **e** "impedir o prosseguimento" — e que por isso viram bloqueio no passo de atividades do portal, no mesmo ponto e padrão da regra do abrigado já implementada.

| Regra | Condição | Mensagem (verbatim do requisito) |
|---|---|---|
| RN-C-02 | Intenção de **abrigado** e a solicitação contém 8211-3/00 | "O CNAE 8211-3/00 não é permitido para exercício em escritório virtual e coworking, conforme as disposições do Anexo B do Decreto Municipal nº 35.062/2021." |
| RN-C-01 | Intenção de **sede** numa inscrição que já tem sede ativa | "Já existe uma sede de escritório virtual vinculada a esta inscrição imobiliária." |
| RN-C-03 | Intenção de **sede** com atividade fora de `{8211-3/00} ∪ Anexo A` | "O CNAE :cnae não é permitido para exercício em sede de escritório virtual, conforme as disposições do Anexo A do Decreto Municipal nº 35.062/2021." |

**Files:**
- Create: `tests/Feature/EscritorioVirtual/ConstituicaoSedeBloqueiosTest.php`
- Modify: `app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php`
- Modify: `config/sile.php`
- Modify: `database/seeders/ParameterSeeder.php`
- Modify: `tests/Feature/Seeders/DatabaseSeederTest.php`
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php`

**Interfaces:**
- Consumes: `SedeAtividadesResolver::naoPermitidos()` (Task 1), `ViabilityRequest::virtualOfficeIntent()`, `VirtualOfficeInscriptionLock::sedeAtiva()`.
- Produces: três parâmetros novos — `analise.escritorio_virtual.mensagem_cnae_sede_em_abrigado`, `analise.escritorio_virtual.mensagem_sede_duplicada`, `analise.escritorio_virtual.mensagem_cnae_fora_anexo_a`.

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/ConstituicaoSedeBloqueiosTest.php`. Copie de `AbrigadoCnaeBlockTest.php` o `setUp`, o helper `portalUser()` e o helper de rascunho — **repita o código, não importe**.

Casos:

```php
    public function test_cnae_de_sede_com_intencao_de_abrigado_e_bloqueado(): void
    // wants_virtual_office_tenant = true + 8211-3/00 → 422 com a mensagem do Anexo B

    public function test_sede_em_inscricao_que_ja_tem_sede_e_bloqueada(): void
    // intencao Sede + VirtualOfficeInscriptionLock ativa na inscricao → 422 com
    // a mensagem de sede duplicada

    public function test_sede_com_atividade_fora_do_anexo_a_e_bloqueada(): void
    // intencao Sede + 8211-3/00 + 8630-5/99 (so Anexo B) → 422 nomeando 8630-5/99

    public function test_sede_com_atividades_do_anexo_a_passa(): void
    // intencao Sede + 8211-3/00 + 6920-6/01 → sem erro de escritorio virtual

    public function test_sede_com_apenas_o_cnae_gatilho_passa(): void
    // intencao Sede + so 8211-3/00 → sem erro; prova a excecao do gatilho

    public function test_solicitacao_sem_intencao_de_escritorio_virtual_nao_sofre_bloqueio(): void
    // intencao Nenhum + 8630-5/99 em inscricao livre → sem erro
```

O último caso é o que impede a regra de vazar para quem não é escritório virtual — sem ele, um bug de escopo passaria despercebido.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=ConstituicaoSedeBloqueiosTest`
Expected: FAIL nos três primeiros casos.

- [ ] **Step 3: Parâmetros das três mensagens**

Acrescentar as três chaves em `config/sile.php`, dentro de `'analise' => ['escritorio_virtual' => [...]]`, com os textos verbatim da tabela acima. Acrescentar as três linhas correspondentes em `database/seeders/ParameterSeeder.php`, no mesmo formato das vizinhas.

A mensagem da RN-C-03 traz o marcador `:cnae`, substituído pelo código reprovado no momento do erro. Havendo mais de um reprovado, emita **um erro por CNAE** — o requisito manda identificar todos.

Ajustar a contagem esperada em `DatabaseSeederTest` e `ParameterSeederTest` (três a mais).

- [ ] **Step 4: Implementar os bloqueios**

Em `UpdateSolicitacaoAtividadesRequest::after()`, acrescentar um closure **novo**, separado do que já existe.

Importante: o closure atual começa com `if (blank($inscricao) || ! VirtualOfficeInscriptionLock::ativoPara($inscricao)) { return; }` — ele só roda quando a inscrição já tem sede. A constituição de **sede** acontece justamente numa inscrição **sem** sede, então as regras novas não cabem lá dentro. Precisa ser closure próprio.

Ordem dentro do closure novo, e cada ramo com `return` após adicionar o erro, para não somar pareceres de causas diferentes:

1. Resolver a intenção via `$solicitacao->virtualOfficeIntent()`. Se for `Nenhum`, retorna sem fazer nada.
2. Intenção `Abrigado` **e** a solicitação contém o CNAE gatilho → erro com a mensagem do Anexo B, retorna. (A validação do Anexo B para os demais CNAEs continua no closure existente.)
3. Intenção `Sede` e `VirtualOfficeInscriptionLock::sedeAtiva($inscricao) !== null` → erro de sede duplicada, retorna.
4. Intenção `Sede` → `SedeAtividadesResolver::naoPermitidos()` sobre os códigos da solicitação; para cada reprovado, um erro com a mensagem do Anexo A e o código no lugar de `:cnae`.

Para descobrir se a solicitação contém o CNAE gatilho no passo 2, use o mesmo parâmetro `analise.escritorio_virtual.cnae_gatilho_sede`, normalizando a dígitos — não escreva `8211-3/00` literal.

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `php artisan test --filter=ConstituicaoSedeBloqueiosTest`
Expected: PASS, 6 testes.

- [ ] **Step 6: Rodar as suítes afetadas**

Run: `php artisan test --filter=EscritorioVirtual`
Run: `php artisan test --filter=Seeders`
Expected: PASS nas duas.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php config/sile.php database/seeders/ParameterSeeder.php tests/Feature/EscritorioVirtual/ConstituicaoSedeBloqueiosTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Seeders/ParameterSeederTest.php
git commit -m "feat(ev): bloqueia constituicao de sede fora das regras do decreto"
```

---

### Task 3: Flag do §2º art. 6º no encaminhamento à análise

O gatilho de sede já encaminha à análise, com o motivo `'gatilho: sede de escritório virtual'`. O requisito exige que a solicitação chegue à análise com a flag:

> "Verificar se atende ao §2º do artigo 6º do Decreto Municipal nº 35.062, de 29 de dezembro de 2021."

**Files:**
- Create: `tests/Feature/EscritorioVirtual/FlagAnaliseSedeTest.php`
- Modify: `app/Services/Expresso/FluxoExpressoService.php`
- Modify: `config/sile.php`
- Modify: `database/seeders/ParameterSeeder.php`
- Modify: `tests/Feature/Seeders/DatabaseSeederTest.php`
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php`

**Interfaces:**
- Consumes: `SedeEscritorioVirtualGatilho::aplica()` e `FluxoExpressoService::encaminharAnalise()`.
- Produces: parâmetro `analise.escritorio_virtual.flag_analise_sede`.

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/FlagAnaliseSedeTest.php`. Um caso principal: solicitação com o CNAE gatilho e resposta "Sim" à pergunta vinculada, submetida ao fluxo expresso, termina em análise com o motivo contendo o texto da flag. Um segundo caso provando que o texto é parametrizável — sobrescrever o parâmetro muda o motivo registrado.

Verifique como `GatilhoSedeTest` já monta esse cenário e onde ele lê o motivo registrado (auditoria e/ou timeline); reaproveite o mesmo caminho de asserção.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=FlagAnaliseSedeTest`
Expected: FAIL — o motivo é `'gatilho: sede de escritório virtual'`.

- [ ] **Step 3: Parâmetro da flag**

Acrescentar `analise.escritorio_virtual.flag_analise_sede` em `config/sile.php` e no `ParameterSeeder`, com o texto verbatim acima. Ajustar as contagens nos dois testes de seeder.

- [ ] **Step 4: Usar a flag como motivo**

Em `FluxoExpressoService`, no ramo `if ($this->gatilhoSede->aplica($request))`, trocar o motivo literal pela leitura do parâmetro.

Decisão de forma, e explique-a no comentário: o motivo passa a ser o texto da flag, não a concatenação dos dois. O motivo é o que a operação lê na fila de análise, e o requisito define exatamente o que ela deve ler. A informação de que o gatilho foi o de sede já está na trilha de auditoria por outros campos.

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `php artisan test --filter=FlagAnaliseSedeTest`
Expected: PASS.

- [ ] **Step 6: Rodar as suítes afetadas**

Run: `php artisan test --filter=EscritorioVirtual`
Run: `php artisan test --filter=Expresso`
Run: `php artisan test --filter=Seeders`
Expected: PASS nas três. `GatilhoSedeTest` provavelmente assevera o motivo antigo — atualize e explique no relatório.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Expresso/FluxoExpressoService.php config/sile.php database/seeders/ParameterSeeder.php tests/Feature/EscritorioVirtual/FlagAnaliseSedeTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Seeders/ParameterSeederTest.php
git commit -m "feat(ev): encaminha sede a analise com a flag do decreto 35.062"
```

---

## Self-Review

**Cobertura da spec.** Cobre RN-C-01, RN-C-02, RN-C-03 e a parte de encaminhamento da RN-C-04, mais a correção da derivação de intenção para dado legado (achado I5 da revisão final, agora resolvível pela resposta da SEDUR de 2026-08-31). RN-C-08 já foi entregue. Fica de fora o indeferimento por zona e via da RN-C-04: o motor LOUOS já produz esse veredito e o fluxo expresso já indefere sobre ele — o que falta é só o texto de indeferimento parametrizado, que depende da pergunta 11 do e-mail à SEDUR (lista oficial) e não bloqueia o resto.

**Consistência de tipos.** `SedeAtividadesResolver::naoPermitidos()` da Task 1 é consumido pela Task 2. A correção de `virtualOfficeIntent()` na Task 1 é pré-requisito das três condições da Task 2, que chaveiam por `VirtualOfficeIntent::Sede`. A Task 3 não depende das outras duas e poderia rodar em paralelo, mas mantenho sequencial porque as três mexem em `config/sile.php` e no `ParameterSeeder`, e a contagem de parâmetros nos testes de seeder colidiria.

**Granularidade.** As três tarefas trazem o código dos testes principais. Nas Tasks 2 e 3, alguns cenários estão descritos em vez de escritos — são montagens de rascunho de solicitação que dependem de helpers existentes que o implementador precisa ler de qualquer forma; transcrevê-los aqui de memória arriscaria divergir do que o repositório tem.
