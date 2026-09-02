# Escritório Virtual — camada de tela do portal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tornar alcançáveis pelo portal as três regras de escritório virtual já implementadas e testadas — a pergunta geral, a pergunta vinculada ao CNAE gatilho, a marcação de exclusão por atividade e a confirmação de perda da condição de sede — e remover a validação que hoje impede a própria sede de compor a solicitação de exclusão.

**Architecture:** Segue o padrão das etapas do wizard já existentes. A pergunta geral entra no passo do imóvel; a vinculada e a marcação de exclusão entram no passo de atividades, porque é lá que os CNAEs são conhecidos. Nenhum texto é escrito na tela: todos vêm de parâmetro administrável pelo payload do Inertia.

**Tech Stack:** Laravel 12 + Inertia + React 19 + TypeScript, Tailwind. Testes PHPUnit via runner Pest, SQLite `:memory:`.

**Spec:** `docs/superpowers/specs/2026-07-16-escritorio-virtual-motor-design.md` (revisão 5), `docs/superpowers/specs/2026-08-28-escritorio-virtual-constituicao-design.md` (revisão 2) e `docs/superpowers/specs/2026-08-28-escritorio-virtual-alteracao-atividade-design.md` (revisão 2)

## Global Constraints

- Comando de teste: `php artisan test --filter=<NomeDoTeste>`, sempre em **foreground**. Suíte em SQLite `:memory:`; o banco de desenvolvimento remoto é inacessível e desnecessário. O grupo `Postgis` falha por falta do container espacial — pré-existente, ignorar.
- Testes: classes no estilo PHPUnit em `tests/Feature/EscritorioVirtual/`, com `use LazilyRefreshDatabase;` e docblock em português explicando a regra coberta.
- **Nenhum texto de pergunta ou aviso escrito no JSX.** Todos vêm de parâmetro administrável (`Settings::get('<chave>', config('sile.<chave>'))`), entregues à tela pelo payload do Inertia. A contagem esperada nos testes de seeder está em **101** — atualizar ao acrescentar, incluindo o comentário que discrimina a origem.
- O CNAE gatilho é parametrizável, com fonte única em `SedeEscritorioVirtualGatilho::cnaeGatilho()` (dígitos) e `cnaeGatilhoFormatado()` (`NNNN-N/NN`). **Não** escrever o código literal em código de produção nem no JSX.
- Front-end: seguir o padrão visual das etapas existentes (`resources/js/components/solicitacao/etapa-imovel.tsx` e `etapa-atividades.tsx`) — mesmas classes Tailwind, mesmo tratamento de erro, mesmo `useForm` do Inertia. Não introduzir biblioteca nem padrão visual novo.
- TypeScript: as interfaces de props do wizard e das etapas precisam refletir os campos novos. `npx tsc --noEmit` (ou o script equivalente do projeto) deve passar.
- Comentários e docblocks em português, densidade igual à do código ao redor.
- Mensagens de commit em português, imperativo, SEM acentuação no assunto.
- PROIBIDO qualquer marca de autoria de IA em commits, código ou comentários.
- `git add` por caminho explícito.
- Rebuildar os assets quando tocar `.tsx` — verifique como os commits anteriores desta branch fizeram (há commits `chore: rebuilda assets ...`) e siga o mesmo procedimento.

**Onde cada pergunta vive, e por quê.** O wizard tem a ordem `imovel → atividades → …` (confirmado em `resources/js/pages/portal/solicitacoes/wizard.tsx:118-119`). A pergunta geral ("deseja ser abrigado de escritório virtual?") é sobre o arranjo do endereço e entra no passo do **imóvel**. A pergunta vinculada ao CNAE gatilho só faz sentido quando se sabe quais CNAEs foram escolhidos, então entra no passo de **atividades** — o que coincide com o legado, onde ela aparece sob o CNAE no protocolo (`docs/artefatos/Processo - sede de virtual.pdf`).

---

### Task 1: Pergunta geral no passo do imóvel

**Files:**
- Create: `tests/Feature/EscritorioVirtual/PerguntaGeralPortalTest.php`
- Modify: `app/Http/Controllers/Portal/SolicitacaoController.php`
- Modify: `resources/js/pages/portal/solicitacoes/wizard.tsx`
- Modify: `resources/js/components/solicitacao/etapa-imovel.tsx`
- Modify: `config/sile.php`
- Modify: `database/seeders/ParameterSeeder.php`
- Modify: `tests/Feature/Seeders/DatabaseSeederTest.php`
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php`

**Interfaces:**
- Consumes: `UpdateSolicitacaoImovelRequest` já aceita `wants_virtual_office_tenant` (`sometimes|nullable|boolean`), e `SolicitacaoImovelController` já preserva o valor quando a chave está ausente. **Nada a mudar no backend de escrita.**
- Produces: parâmetro `analise.escritorio_virtual.pergunta_geral` com o texto verbatim do requisito: `Deseja ser abrigado de escritório virtual?`
- Produces: no payload do Inertia, dentro de `indicators`, o campo `wants_virtual_office_tenant` (`boolean | null`); e, no nível da solicitação, um bloco `escritorio_virtual` com `pergunta_geral` (string).

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/PerguntaGeralPortalTest.php`. Leia antes `tests/Feature/EscritorioVirtual/SedePerguntaPortalTest.php` — ele já exercita o passo do imóvel e serve de molde.

Casos:

```php
    /**
     * O payload do wizard entrega a resposta atual da pergunta geral e o texto
     * dela, que e parametrizavel (RN-EV-01, [OPEN-EV-8]). Sem isso a tela nao
     * tem o que renderizar e a regra fica inalcancavel.
     */
    public function test_payload_do_wizard_entrega_a_resposta_e_o_texto_da_pergunta(): void
    // GET da solicitação → indicators.wants_virtual_office_tenant e
    // escritorio_virtual.pergunta_geral presentes, o texto igual ao default.

    public function test_texto_da_pergunta_e_parametrizavel(): void
    // sobrescrever o Parameter muda o texto que chega ao payload.

    public function test_resposta_afirmativa_e_persistida_pelo_passo_do_imovel(): void
    // PUT com wants_virtual_office_tenant = true → persistido.

    public function test_resposta_negativa_e_persistida_e_nao_confundida_com_ausencia(): void
    // PUT com false → persiste false, e um PUT seguinte SEM a chave preserva
    // o false. E o caso que prova que ausencia nao e recusa.
```

O quarto caso é o que guarda a correção que já foi feita no controller. Sem ele, alguém que "simplifique" o `has()` para `boolean()` volta a apagar a resposta a cada gravação.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=PerguntaGeralPortalTest`
Expected: FAIL nos dois primeiros casos (campos ausentes do payload).

- [ ] **Step 3: Parâmetro do texto**

Acrescentar `analise.escritorio_virtual.pergunta_geral` em `config/sile.php` e no `ParameterSeeder`, com o texto verbatim. Atualizar a contagem 101→102 nos dois testes de seeder e o comentário que discrimina a origem.

- [ ] **Step 4: Payload do controller**

Em `SolicitacaoController`, no array que monta a solicitação para o wizard (por volta da linha 326):

- acrescentar `'wants_virtual_office_tenant' => $solicitacao->wants_virtual_office_tenant,` ao bloco `indicators` — **sem** cast para `bool`, porque `null` precisa chegar como `null` à tela: é o estado "ainda não respondido", que não é nem sim nem não;
- acrescentar um bloco novo `'escritorio_virtual' => ['pergunta_geral' => Settings::get(...)]`.

- [ ] **Step 5: Tipos e tela**

Em `wizard.tsx`, acrescentar `wants_virtual_office_tenant: boolean | null;` à interface de `indicators` e o bloco `escritorio_virtual: { pergunta_geral: string };` à interface da solicitação, repassando-o à etapa do imóvel como prop.

Em `etapa-imovel.tsx`: acrescentar o campo ao `useForm` e renderizar a pergunta **fora** do fieldset "Características do imóvel" — ela não é característica do imóvel, é declaração de intenção. Use um fieldset próprio, com o texto vindo da prop.

O controle precisa expressar **três** estados, não dois: sim, não, e ainda não respondido. Um checkbox não expressa isso. Use dois radios (Sim/Não) sem valor pré-selecionado quando o dado for `null` — assim o requerente é obrigado a responder e o sistema não presume recusa. Siga as classes Tailwind dos controles vizinhos.

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test --filter=PerguntaGeralPortalTest`
Expected: PASS, 4 testes.

- [ ] **Step 7: Verificar tipos e suítes**

Run: o comando de checagem de tipos do projeto (veja `package.json`)
Run: `php artisan test --filter=EscritorioVirtual`
Run: `php artisan test --filter=Seeders`
Expected: PASS nas três.

- [ ] **Step 8: Rebuildar assets e commitar**

Rebuilde os assets no mesmo procedimento dos commits `chore: rebuilda assets ...` desta branch, e commite código e assets **separadamente**, como a branch já faz.

```bash
git add config/sile.php database/seeders/ParameterSeeder.php app/Http/Controllers/Portal/SolicitacaoController.php resources/js/pages/portal/solicitacoes/wizard.tsx resources/js/components/solicitacao/etapa-imovel.tsx tests/Feature/EscritorioVirtual/PerguntaGeralPortalTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Seeders/ParameterSeederTest.php
git commit -m "feat(ev): pergunta a intencao de abrigo no passo do imovel"
```

---

### Task 2: Desbloquear a sede titular na validação de atividades

Hoje a própria sede **não consegue** compor a solicitação que exclui seu CNAE gatilho. A regra de sede duplicada (RN-C-01) dispara quando a intenção é `Sede` e a inscrição tem vínculo de sede ativo — e para a sede titular esse vínculo é o **dela mesma**.

Sem esta tarefa, a Task 3 entrega uma tela que o backend rejeita.

**Files:**
- Create: `tests/Feature/EscritorioVirtual/SedeTitularNaoEBloqueadaTest.php`
- Modify: `app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php`

**Interfaces:**
- Consumes: a checagem de titularidade introduzida em `FluxoExpressoService` na onda de correção anterior (procure por `titularDaSede`) — **extraia-a** para um ponto reutilizável em vez de duplicar a comparação. Um método público em `SedeEscritorioVirtualGatilho` ou um serviço próprio; escolha o que deixar o call site mais claro e explique no relatório.
- Produces: a regra de sede duplicada passa a não disparar para o titular do vínculo.

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/SedeTitularNaoEBloqueadaTest.php`, moldado em `ConstituicaoSedeBloqueiosTest`. Casos:

```php
    public function test_sede_titular_pode_alterar_as_proprias_atividades(): void
    // solicitação da MESMA empresa que detém o vínculo, intenção Sede,
    // inscrição com sede ativa → sem erro de sede duplicada.

    public function test_empresa_de_terceiro_continua_bloqueada_por_sede_duplicada(): void
    // outra empresa, mesma inscrição, intenção Sede → 422 com o parecer de
    // sede duplicada. É a guarda que impede a correção de virar buraco.
```

O segundo caso é essencial: sem ele, a correção poderia liberar qualquer um a constituir sede numa inscrição travada.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=SedeTitularNaoEBloqueadaTest`
Expected: FAIL no primeiro caso — a sede titular recebe 422.

- [ ] **Step 3: Extrair a checagem de titularidade**

Localize `titularDaSede` em `app/Services/Expresso/FluxoExpressoService.php` (método privado, introduzido na onda de correção do plano de exclusão). Extraia a comparação para um ponto reutilizável e faça o `FluxoExpressoService` consumi-lo, sem duplicar a lógica — mesma disciplina de fonte única que se aplicou ao CNAE gatilho.

Preserve o comportamento atual: compara a empresa da solicitação com a empresa da solicitação que detém o vínculo, com guarda contra nulos.

- [ ] **Step 4: Aplicar na validação**

No closure de `UpdateSolicitacaoAtividadesRequest::after()` que hoje bloqueia por sede duplicada, acrescentar a condição de que o requerente **não** é o titular do vínculo. Comente o porquê: a sede precisa poder alterar as próprias atividades, inclusive excluir o CNAE que a caracteriza.

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `php artisan test --filter=SedeTitularNaoEBloqueadaTest`
Expected: PASS, 2 testes.

- [ ] **Step 6: Rodar as suítes afetadas**

Run: `php artisan test --filter=EscritorioVirtual`
Run: `php artisan test --filter=Expresso`
Expected: PASS. Atenção a `ConstituicaoSedeBloqueiosTest` — se algum caso dele montava a sede como titular por acidente, o comportamento muda; verifique e explique no relatório.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php app/Services/Expresso/FluxoExpressoService.php tests/Feature/EscritorioVirtual/SedeTitularNaoEBloqueadaTest.php
git commit -m "feat(ev): libera a sede titular a alterar as proprias atividades"
```

Acrescente ao `git add` o arquivo onde você colocou a checagem extraída, se for novo.

---

### Task 3: Marcação de exclusão, pergunta vinculada e confirmação de perda da condição

A tarefa que fecha a frente. Três coisas no mesmo passo do wizard, porque as três dependem de saber quais CNAEs foram escolhidos.

**Files:**
- Create: `tests/Feature/EscritorioVirtual/AtividadesPortalEscritorioVirtualTest.php`
- Modify: `app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php`
- Modify: `app/Http/Controllers/Portal/SolicitacaoAtividadeController.php`
- Modify: `app/Http/Controllers/Portal/SolicitacaoController.php`
- Modify: `resources/js/pages/portal/solicitacoes/wizard.tsx`
- Modify: `resources/js/components/solicitacao/etapa-atividades.tsx`
- Modify: `config/sile.php`
- Modify: `database/seeders/ParameterSeeder.php`
- Modify: `tests/Feature/Seeders/DatabaseSeederTest.php`
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php`

**Interfaces:**
- Consumes: `App\Enums\IntencaoAtividade` (`Incluir`/`Excluir`), `ViabilityRequest::cnaesParaExcluir()`, `SedeEscritorioVirtualGatilho::cnaeGatilho()` e `cnaeGatilhoFormatado()`.
- Produces: no payload de escrita do passo de atividades, o campo `exclusoes` — lista de ids de CNAE marcados para exclusão. **Não** reestruture `principal_cnae_id`/`complementares`: eles já têm regras de validação (`distinct`, `max`, `exists` com `active`) e mudar a forma deles alargaria o diff sem ganho.
- Produces: parâmetro `analise.escritorio_virtual.pergunta_vinculada` com o texto verbatim do legado: `Irá prestar serviço de escritório virtual, centro de negócios ou coworking?`
- Produces: no payload do wizard, dentro do bloco `escritorio_virtual`: `pergunta_vinculada`, `mensagem_confirma_perda_sede` (o parâmetro já existe), `cnae_gatilho_id` (o id do CNAE gatilho no cadastro, ou `null` se não cadastrado) e, por CNAE, a `intencao` atual.

- [ ] **Step 1: Escrever o teste**

Criar `tests/Feature/EscritorioVirtual/AtividadesPortalEscritorioVirtualTest.php`. Casos:

```php
    public function test_payload_entrega_os_textos_e_o_cnae_gatilho(): void
    // pergunta_vinculada, mensagem_confirma_perda_sede e cnae_gatilho_id no
    // payload; textos iguais aos defaults.

    public function test_marcacao_de_exclusao_e_persistida_na_intencao_do_pivot(): void
    // PUT com exclusoes: [id] → o pivot daquele CNAE fica com intencao
    // 'excluir'; os demais, 'incluir'.

    public function test_solicitacao_que_nao_e_alteracao_de_atividade_nao_recebe_intencao(): void
    // mesmo PUT em solicitação de primeiro estabelecimento → intencao null.
    // É a guarda da semântica do null.

    public function test_regravar_as_atividades_preserva_a_marcacao(): void
    // dois PUTs seguidos com o mesmo `exclusoes` → a marcação sobrevive.
    // Guarda contra o sync() que descartava a intencao.

    public function test_confirmacao_de_perda_da_condicao_de_sede_e_persistida(): void
    // PUT marcando o CNAE gatilho para exclusão e confirmando → o campo
    // confirma_perda_condicao_sede fica true.

    public function test_confirmacao_ausente_nao_vira_negativa(): void
    // PUT sem a chave de confirmação → o campo permanece null, não false.
```

O quarto e o sexto casos guardam armadilhas conhecidas: o `sync()` do controller descartava a `intencao`, e ausência de chave não pode virar negativa — o mesmo defeito já corrigido duas vezes neste projeto para outros campos.

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=AtividadesPortalEscritorioVirtualTest`
Expected: FAIL na maioria dos casos.

- [ ] **Step 3: Parâmetro da pergunta vinculada**

Acrescentar `analise.escritorio_virtual.pergunta_vinculada` em `config/sile.php` e no `ParameterSeeder`, com o texto verbatim. Atualizar a contagem (102→103 após a Task 1) nos dois testes de seeder e o comentário.

- [ ] **Step 4: Aceitar os campos novos na validação**

Em `UpdateSolicitacaoAtividadesRequest::rules()`:

```php
'exclusoes' => ['nullable', 'array'],
'exclusoes.*' => ['integer', 'distinct', Rule::exists('cnaes', 'id')],
'confirma_perda_condicao_sede' => ['sometimes', 'nullable', 'boolean'],
```

Nota deliberada: `exclusoes.*` **não** exige `active`, ao contrário de `complementares`. Um CNAE que está sendo **excluído** pode ter sido desativado no cadastro depois de a empresa passar a exercê-lo — exigir `active` impediria a empresa de se livrar de uma atividade obsoleta. Comente isso na regra.

Acrescente um `after()` validando que todo id em `exclusoes` está entre os CNAEs submetidos (principal ou complementar) — não se exclui o que não está na lista.

- [ ] **Step 5: Persistir a intenção e a confirmação**

Em `SolicitacaoAtividadeController::update()`, no `sync()` que já monta o payload do pivot: acrescentar `'intencao' => ...` a cada entrada. O valor é `IntencaoAtividade::Excluir->value` para os ids em `exclusoes`, `IntencaoAtividade::Incluir->value` para os demais — e **`null` para todos** quando o tipo de serviço da solicitação não é `alteracao-atividade`, porque aí a solicitação não declara intenção por atividade.

Descubra o tipo de serviço pela relação da solicitação; o code é `alteracao-atividade` (veja `database/seeders/ViabilityServiceTypeSeeder.php`).

Grave `confirma_perda_condicao_sede` no padrão já usado para os outros campos de resposta: `$request->has(...) ? $request->boolean(...) : <valor atual>`. Ausência preserva, nunca apaga.

- [ ] **Step 6: Payload do wizard**

Em `SolicitacaoController`, acrescentar ao bloco `escritorio_virtual` os textos novos e `cnae_gatilho_id`, e acrescentar `'intencao' => $cnae->pivot->intencao,` ao map de `cnaes`. Também `confirma_perda_condicao_sede` no nível da solicitação.

Para o `cnae_gatilho_id`: resolva o id do CNAE gatilho no cadastro a partir do código; se não existir, `null`. A tela precisa dele para saber a qual item exibir a pergunta vinculada, sem comparar códigos no front.

- [ ] **Step 7: Tela**

Em `etapa-atividades.tsx`:

- cada CNAE da lista ganha um controle de exclusão (checkbox "Excluir esta atividade"), visível **apenas** quando a solicitação é de alteração de atividade — passe essa informação pelo payload, não deduza no front;
- quando o CNAE gatilho estiver marcado para exclusão, exibir a mensagem de confirmação (`mensagem_confirma_perda_sede`) com dois radios Sim/Não, sem pré-seleção;
- a pergunta vinculada (`pergunta_vinculada`) aparece quando o CNAE gatilho está entre as atividades **e** a resposta da pergunta geral foi "Não" — a resposta dela é o `wants_virtual_office_hq`, que também precisa entrar no payload de escrita deste passo.

Atualize as interfaces de props em `wizard.tsx` e `etapa-atividades.tsx`.

Se ao implementar você concluir que a pergunta vinculada não cabe neste passo — por exemplo, porque o `wants_virtual_office_hq` é escrito pelo passo do imóvel e misturar os dois passos criaria dois donos do mesmo campo —, **PARE e me pergunte** em vez de escolher. É uma decisão de contrato entre etapas, não detalhe de implementação.

- [ ] **Step 8: Rodar e confirmar que passa**

Run: `php artisan test --filter=AtividadesPortalEscritorioVirtualTest`
Expected: PASS, 6 testes.

- [ ] **Step 9: Verificar tipos e suítes**

Run: o comando de checagem de tipos do projeto
Run: `php artisan test --filter=EscritorioVirtual`
Run: `php artisan test --filter=Expresso`
Run: `php artisan test --filter=Seeders`
Expected: PASS. Atenção a `AbrigadoCnaeBlockTest` e `ConstituicaoSedeBloqueiosTest`, que exercitam o mesmo endpoint.

- [ ] **Step 10: Rebuildar assets e commitar**

Mesmo procedimento da Task 1: código e assets em commits separados.

```bash
git add config/sile.php database/seeders/ParameterSeeder.php app/Http/Requests/Portal/UpdateSolicitacaoAtividadesRequest.php app/Http/Controllers/Portal/SolicitacaoAtividadeController.php app/Http/Controllers/Portal/SolicitacaoController.php resources/js/pages/portal/solicitacoes/wizard.tsx resources/js/components/solicitacao/etapa-atividades.tsx tests/Feature/EscritorioVirtual/AtividadesPortalEscritorioVirtualTest.php tests/Feature/Seeders/DatabaseSeederTest.php tests/Feature/Seeders/ParameterSeederTest.php
git commit -m "feat(ev): marca exclusao por atividade e confirma a perda da condicao de sede"
```

---

## Self-Review

**Cobertura.** Torna alcançáveis: a pergunta geral (RN-EV-01), a pergunta vinculada (RN-EV-01), a marcação de intenção (RN-AA-05b) e a confirmação de perda da condição (RN-AA-04). Mais a remoção do bloqueio que impedia a sede titular de compor a solicitação. Fica de fora: a tela de gestão da ficha, que já recebe a flag de análise desde o plano de constituição; e a apresentação do TVL para exclusão, que depende de decisão da SEDUR.

**Consistência.** A Task 2 é pré-requisito real da Task 3 — sem ela a tela entrega um payload que o backend rejeita. A Task 1 é independente das outras duas, mas as três tocam `config/sile.php`, o `ParameterSeeder` e a contagem nos testes de seeder, então são estritamente sequenciais.

**Risco reconhecido.** O Step 7 da Task 3 tem uma decisão de contrato entre etapas — quem é o dono de `wants_virtual_office_hq` se a pergunta vinculada vive no passo de atividades e o campo é escrito pelo passo do imóvel. Deixei explícito que o implementador deve perguntar em vez de escolher. Escrever a resposta aqui, sem ler o código dos dois passos em conjunto, seria inventar precisão — e nas nove vezes anteriores deste projeto em que um implementador parou para perguntar, o defeito era do enunciado.

**Uma armadilha que o plano trata duas vezes de propósito.** "Ausência de chave não é resposta negativa" já foi corrigido duas vezes neste projeto, em campos diferentes, depois de virar defeito. A Task 1 tem caso de teste dedicado para a pergunta geral e a Task 3 para a confirmação de perda da condição — porque a terceira vez é regressão, não descuido.
