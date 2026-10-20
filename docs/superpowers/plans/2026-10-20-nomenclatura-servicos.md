# Nomenclatura dos serviços (De x Para) — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Exibir os eventos da JUCEB com nomes parametrizáveis pela SEDUR (De x Para), com cadastro automático de eventos novos e tela de gestão.

**Architecture:** Tabela `servico_nomenclaturas` + model `ServicoNomenclatura`. Um serviço `App\Services\Servicos\ServicoNomenclaturaResolver` (singleton por requisição) aplica a regra de exibição e monta as opções do filtro. Os pontos que hoje fazem `eventos->pluck('nome')` e os `servicoOptions` passam a chamar o resolver. Tela CRUD no padrão de `ViabilityServiceTypeController`.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia v3 + React 19, Tailwind 4, PHPUnit 12, spatie/laravel-permission.

**Spec:** `docs/superpowers/specs/2026-10-20-nomenclatura-servicos-design.md`

## Global Constraints

- Só o nome exibido muda; processo, motor, REGIN e gatilhos por código (244) ficam intactos.
- Chave do mapeamento = nome normalizado: minúsculas, sem acento, espaços colapsados, trim.
- `de` é imutável depois de criado; não há exclusão de linha.
- Linhas do PDF nascem com `exibir = true`; linhas automáticas com `para = de` e `exibir = false`.
- Permissão `manter-nomenclatura-servicos`, grupo Atendimento, para os perfis que têm `manter-tipos-servico`.
- Textos de interface em português; sem comentários de autoria de ferramenta.
- PHP: `vendor/bin/pint --dirty --format agent` antes de cada commit.

## Review Focus

- Nome da JUCEB com caixa/acento/espaço diferente do PDF ("Inscrição  de primeiro estabelecimento ") casa com a mesma linha — teste no Task 1.
- Processo com dois eventos que viram o mesmo Para mostra um nome só — teste no Task 2.
- Processo só com eventos de `exibir = false` mostra os Para desses eventos, nunca lista vazia — teste no Task 2.
- Listagem com muitos processos não faz uma consulta à tabela por processo — teste de contagem de queries no Task 2.
- Valor antigo de filtro `e244` (um código) continua filtrando — teste no Task 3.

---

### Task 1: Tabela, model, carga inicial e cadastro automático

**Files:**
- Create: migration `create_servico_nomenclaturas_table` (via `php artisan make:migration`), com a carga inicial dentro do `up()`
- Create: `app/Models/ServicoNomenclatura.php`, `database/factories/ServicoNomenclaturaFactory.php`
- Modify: `app/Services/Regin/ReginEventos.php` (`gravar`)
- Test: `tests/Feature/Servicos/ServicoNomenclaturaTest.php`, `tests/Feature/Regin/ReginEventosTest.php`

**Interfaces:**
- Produces: `ServicoNomenclatura` (fillable `de`, `chave`, `para`, `exibir`; cast `exibir` bool; `HasAuditoria`, `HasFactory`); `ServicoNomenclatura::chaveDe(string $nome): string`; `ServicoNomenclatura::garantir(string $nome): self` (firstOrCreate pela chave com `para = de`, `exibir = false`). O model preenche `chave` a partir de `de` no evento `saving`.

- [ ] **Step 1: Testes que falham**
  - `test_chave_ignora_caixa_acento_e_espacos`: `chaveDe(' Inscrição  de PRIMEIRO estabelecimento ')` === `chaveDe('Inscricao de primeiro estabelecimento')` === `'inscricao de primeiro estabelecimento'`.
  - `test_garantir_cria_linha_com_de_igual_a_para_e_exibir_desligado`: `garantir('Baixa de inscrição')` → `para = 'Baixa de inscrição'`, `exibir = false`; segunda chamada com `'BAIXA DE INSCRIÇÃO'` não cria outra linha (count 1).
  - `test_carga_inicial_traz_as_linhas_do_pdf`: após migrate, existem as 6 linhas do spec com o Para inicial e `exibir = true` (ex.: `de = 'Inscrição dos demais estabelecimentos'` → `para = 'Viabilidade de Localização'`).
  - Em `ReginEventosTest`: `test_gravar_cadastra_evento_novo_na_nomenclatura` — protocolar recebimento com evento `'Alteração de nome empresarial'` cria `ServicoNomenclatura` com `de = para = 'Alteração de nome empresarial'`, `exibir = false`; evento já mapeado (`'Inscrição de primeiro estabelecimento'`) não altera a linha existente.

- [ ] **Step 2: Rodar e ver falhar** — `php artisan test --compact tests/Feature/Servicos/ServicoNomenclaturaTest.php tests/Feature/Regin/ReginEventosTest.php` → FAIL (classe/tabela inexistente).

- [ ] **Step 3: Implementar**
  - Migration: colunas `id`, `de` string 255, `chave` string 255 unique, `para` string 255, `exibir` boolean default false, timestamps. No `up()`, inserir as 6 linhas do spec e depois uma linha (`para = de`, `exibir = false`) para cada `nome` distinto de `viability_request_eventos` cuja chave ainda não exista. Usar `ServicoNomenclatura::chaveDe` para a chave (normalização com `Str::ascii` + `mb_strtolower` + `preg_replace('/\s+/', ' ')` + trim).
  - `ReginEventos::gravar`: chamar `ServicoNomenclatura::garantir($evento['nome'])` para cada evento do recebimento (inclusive os já gravados no processo; `garantir` é idempotente).

- [ ] **Step 4: Rodar e ver passar** — mesmo comando → PASS.

- [ ] **Step 5: Commit** — `feat: tabela de nomenclatura dos serviços com carga do PDF e cadastro automático de eventos novos`

### Task 2: Resolver e exibição nas telas

**Files:**
- Create: `app/Services/Servicos/ServicoNomenclaturaResolver.php` (registrar como `scoped` em `AppServiceProvider`)
- Modify: `app/Http/Resources/ProcessoResource.php:66-69`, `app/Http/Controllers/Gestao/CaixaSetorController.php:126-129`, `app/Http/Controllers/Gestao/VistoriaConsultaController.php:364-366`, `app/Http/Controllers/Gestao/AnalysisRecordController.php:608-618`
- Test: `tests/Feature/Servicos/ServicoNomenclaturaResolverTest.php`, mais uma asserção no teste existente da Caixa do setor

**Interfaces:**
- Consumes: `ServicoNomenclatura::chaveDe`, colunas `para`/`exibir`.
- Produces: `ServicoNomenclaturaResolver::servicos(ViabilityRequest $processo): list<string>` (regra completa, incluindo o fallback para `serviceType->name` quando não há eventos); `ServicoNomenclaturaResolver::nomePara(string $nomeEvento): string`. Carrega a tabela inteira uma vez (mapa chave → linha) na primeira chamada.

- [ ] **Step 1: Testes que falham**
  - `test_troca_o_nome_pelo_para`: processo com evento `'Inscrição de primeiro estabelecimento'` → `['Viabilidade de Localização']`.
  - `test_dois_eventos_com_mesmo_para_aparecem_uma_vez`: primeiro + demais estabelecimentos → `['Viabilidade de Localização']`.
  - `test_esconde_eventos_com_exibir_desligado_quando_ha_algum_ligado`: eventos `'Alteração de endereço entre estados'` + `'Alteração de nome empresarial'` (linha automática) → `['Revisão de Endereço']`.
  - `test_sem_nenhum_exibir_ligado_mostra_o_para_de_todos`: só `'Alteração de nome empresarial'` com `para` renomeado para `'Nome Empresarial'` → `['Nome Empresarial']`.
  - `test_evento_sem_linha_usa_o_proprio_nome`: evento cuja linha não existe → nome original.
  - `test_sem_eventos_usa_o_tipo_interno`: processo sem eventos com serviceType `'Renovação'` → `['Renovação']`; sem serviceType → `[]`.
  - `test_carrega_a_tabela_uma_unica_vez`: com `DB::enableQueryLog()`, resolver 5 processos (eventos já carregados) gera exatamente 1 consulta em `servico_nomenclaturas`.
  - Caixa do setor (teste existente em `tests/Feature/Gestao/` que cobre `servicos`): evento `'Inscrição de primeiro estabelecimento'` → prop `servicos` igual a `['Viabilidade de Localização']`.

- [ ] **Step 2: Rodar e ver falhar** — `php artisan test --compact tests/Feature/Servicos/ServicoNomenclaturaResolverTest.php` → FAIL.

- [ ] **Step 3: Implementar** o resolver conforme a regra do spec (passos 1–5) e trocar os quatro pontos para `app(ServicoNomenclaturaResolver::class)->servicos($processo)`.

- [ ] **Step 4: Rodar e ver passar** — o arquivo novo e `php artisan test --compact --filter='CaixaSetor|VistoriaConsulta|AnalysisRecord|ProcessoResource'` → PASS.

- [ ] **Step 5: Commit** — `feat: telas da gestão exibem o serviço pelo nome parametrizado (De x Para)`

### Task 3: Filtro "Serviço" pelo nome parametrizado

**Files:**
- Modify: `app/Services/Analise/ProcessoQueryService.php:105,503-514`; `servicoOptions` em `CaixaSetorController.php:757-772`, `VistoriaConsultaController.php:289-304`, `ResultadoExpressoController.php:195-210`
- Test: `tests/Feature/Servicos/FiltroServicoNomenclaturaTest.php`

**Interfaces:**
- Consumes: `ServicoNomenclaturaResolver::nomePara`.
- Produces: `ServicoNomenclaturaResolver::opcoesDeEvento(): list<array{value: string, label: string}>` — uma opção por `para` distinto entre os `(codigo, nome)` existentes em `viability_request_eventos`, ordenada por label, `value = ProcessoQueryService::PREFIXO_EVENTO . implode(',', códigos ordenados)`. `ProcessoQueryService::eventoDoServico` passa a devolver `?list<string>`; o filtro usa `whereIn('codigo', $codigos)`.

- [ ] **Step 1: Testes que falham**
  - `test_opcoes_agrupam_eventos_com_o_mesmo_para`: eventos com códigos `'101'` (primeiro) e `'102'` (demais) → uma opção `['value' => 'e101,102', 'label' => 'Viabilidade de Localização']`.
  - `test_filtro_com_varios_codigos_traz_processos_de_qualquer_um`: na Caixa do setor, `servico=e101,102` lista o processo do 101 e o do 102, e não o do 244.
  - `test_valor_antigo_com_um_codigo_continua_filtrando`: `servico=e244` lista só o processo do 244.
  - `test_opcoes_de_tipo_interno_continuam_depois`: a prop `servicoOptions` da Caixa termina com as opções de `ViabilityServiceType` (value = id).

- [ ] **Step 2: Rodar e ver falhar** — `php artisan test --compact tests/Feature/Servicos/FiltroServicoNomenclaturaTest.php` → FAIL.

- [ ] **Step 3: Implementar** — `eventoDoServico` separa por vírgula, faz trim e descarta vazios (lista vazia → `null`); os três `servicoOptions` trocam a consulta de eventos por `opcoesDeEvento()` e mantêm o `concat` dos tipos internos.

- [ ] **Step 4: Rodar e ver passar** — o arquivo novo e `php artisan test --compact --filter='CaixaSetor|VistoriaConsulta|ResultadoExpresso|ProcessoQuery'` → PASS.

- [ ] **Step 5: Commit** — `feat: filtro de serviço agrupa os eventos pelo nome parametrizado`

### Task 4: Permissão e tela de gestão

**Files:**
- Create: `app/Http/Controllers/Gestao/ServicoNomenclaturaController.php`, `app/Http/Requests/Gestao/StoreServicoNomenclaturaRequest.php`, `app/Http/Requests/Gestao/UpdateServicoNomenclaturaRequest.php`, migration `cria_permissao_manter_nomenclatura_servicos` (padrão de `2026_10_15_130000_cria_permissao_distribuir_qualquer_setor.php`, mas buscando os perfis por `Role::permission('manter-tipos-servico')`), `resources/js/pages/gestao/servicos-nomenclatura/index.tsx`
- Modify: `routes/gestao.php` (perto da linha 468), `app/Support/PermissionCatalog.php` (após `manter-tipos-servico`), `database/seeders/RolesAndPermissionsSeeder.php:84,160`, `tests/Feature/Authorization/RolesAndPermissionsSeederTest.php:38,151`, `resources/js/navigation/gestao-nav.ts:138`
- Test: `tests/Feature/Servicos/ServicoNomenclaturaCrudTest.php`

**Interfaces:**
- Consumes: `ServicoNomenclatura`.
- Produces: rotas `gestao.servicos-nomenclatura.index` (GET `/gestao/servicos-nomenclatura`), `.store` (POST), `.update` (PUT `{servicoNomenclatura}`), `.toggle-exibir` (PATCH `{servicoNomenclatura}/exibir`), todas sob `permission:manter-nomenclatura-servicos`. Página Inertia `gestao/servicos-nomenclatura/index` com props `nomenclaturas` (paginado: `id, de, para, exibir`), `filters` (`search, sort, direction, per_page, exibir`), `perPageOptions` e `sugestoesDe: list<string>` com os nomes distintos de `viability_request_eventos` para o datalist do campo De.

- [ ] **Step 1: Testes que falham**
  - `test_lista_exige_permissao`: analista → 403.
  - `test_administrador_lista_e_busca_por_de_ou_para`: `search=localiza` acha as linhas com Para "Viabilidade de Localização"; `exibir=0` filtra só as desligadas.
  - `test_cria_linha`: POST `de='Baixa de inscrição', para='Encerramento', exibir=1` → linha com `chave = 'baixa de inscricao'`.
  - `test_nao_cria_de_repetido_por_caixa_ou_acento`: POST `de='INSCRICAO DE PRIMEIRO ESTABELECIMENTO'` → erro de validação em `de`.
  - `test_atualiza_para_e_exibir_sem_mudar_de`: PUT com `de` diferente → `de` inalterado, `para` e `exibir` alterados.
  - `test_alterna_exibir`: PATCH inverte `exibir`.
  - `test_migration_da_permissao_concede_a_quem_tem_tipos_servico`: perfil customizado com `manter-tipos-servico` recebe `manter-nomenclatura-servicos` ao rodar o `up()` da migration (incluir via `require` do arquivo, como nos testes de migration existentes, se houver; senão testar pelo seeder).
  - `RolesAndPermissionsSeederTest`: incluir `manter-nomenclatura-servicos` nas listas dos dois perfis.

- [ ] **Step 2: Rodar e ver falhar** — `php artisan test --compact tests/Feature/Servicos/ServicoNomenclaturaCrudTest.php tests/Feature/Authorization/RolesAndPermissionsSeederTest.php` → FAIL.

- [ ] **Step 3: Implementar** o controller espelhando `ViabilityServiceTypeController` (ordenáveis `de`, `para`; padrão `de asc`). Store valida `de` required/max:255 e unicidade por `chaveDe` (closure rule, mensagem "Já existe uma linha para este serviço."), `para` required/max:255, `exibir` boolean default true. Update aceita só `para` e `exibir`. Página React espelha `gestao/tipos-servico/index.tsx`: colunas De, Para, Exibir (Badge "Exibe"/"Oculto"), ações editar e alternar; texto de ajuda: "Num processo com mais de um serviço, aparecem só os marcados como Exibir. Se nenhum estiver marcado, aparecem todos." Item de menu "Nomenclatura dos serviços", ícone `briefcase`, logo após "Tipos de serviço".

- [ ] **Step 4: Rodar e ver passar** — mesmo comando → PASS; `npm run build` sem erro de tipo.

- [ ] **Step 5: Verificar no navegador** — abrir `/gestao/servicos-nomenclatura` no preview, criar/editar/alternar uma linha, conferir console sem erro; abrir a Caixa do setor e ver o nome novo.

- [ ] **Step 6: Commit** — `feat: tela de nomenclatura dos serviços e permissão para parametrizar`

### Task 5: Verificação final

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact tests/Feature/Servicos tests/Feature/Regin tests/Feature/Gestao tests/Feature/Authorization tests/Feature/Vistoria` → sem falha nova (comparar com `main` se houver falha preexistente).
