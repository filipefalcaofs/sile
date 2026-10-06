# Carga completa da Planilha de regras 20.08.26 — Plano de implementação

> **Para executores:** use superpowers:executing-plans. Passos com checkbox (`- [ ]`).

**Objetivo:** completar na base a versão vigente do tratamento com o que a planilha tem e o seed não carrega, sem alterar ramos curados.

**Arquitetura:** os CSVs seguem como fonte dos ramos curados. O xlsx passa a morar em `database/data/regras-20-08-26/` e um passo `complementar()` do `PlanilhaTratamentoImporter` grava metadados e vagas por enquadramento, notas gerais, lista CNLU literal e as pendências das regras sem fluxo curado. A tela mostra as pendências da versão vigente.

**Stack:** Laravel 13, PHPUnit 12, OpenSpout, Inertia React 19.

**Spec:** `docs/superpowers/specs/2026-10-06-planilha-regras-carga-completa-design.md`

## Restrições globais
- Itens "(subgrupo 2)" do ID3 (ID3-02, 03, 13, 14, 19) não entram na lista CNLU.
- Ramos das 12 regras (29, 30, 31, 37, 38, 39, 40, 41, 42, 43, 53, 55) não são importados.
- Seed idempotente: nunca apagar dado em uso da versão vigente.
- Textos de motivo vêm do `DecisionTextCatalog`.

## Mapa de arquivos
- Criar `app/Services/Tratamento/PlanilhaCnluParser.php` — interpreta a aba CNLU.
- Criar `database/migrations/2026_10_06_180000_add_metadados_to_treatment_enquadramentos.php`.
- Modificar `app/Services/Tratamento/PlanilhaTratamentoWorkbook.php` — metadados, variantes de vagas, CNLU via parser.
- Modificar `app/Services/Tratamento/PlanilhaTratamentoImporter.php` — `complementar()`.
- Modificar `app/Models/TratamentoEnquadramento.php` — `metadados`.
- Modificar `database/seeders/TratamentoRegrasSeeder.php` — chama `complementar()`.
- Modificar `app/Services/Decisao/DecisionTextCatalog.php` — texto da pendência.
- Modificar `app/Http/Controllers/Gestao/LouosController.php` e `resources/js/pages/gestao/louos/aba-enquadramento.tsx` — pendências da vigente.
- Copiar `docs/artefatos/Planilha de regras - versão 20.08.26.xlsx` para `database/data/regras-20-08-26/planilha-regras.xlsx`.

### Task 1: Parser da lista CNLU
**Files:** criar `PlanilhaCnluParser.php`; teste `tests/Unit/Tratamento/PlanilhaCnluParserTest.php`.
**Produces:** `PlanilhaCnluParser::interpretar(array $linhas): array{itens: list<array{codigo: string, texto: string}>, aguardando: list<string>}`.

- [ ] Teste: células `['nR3 (nR3-08 e nR3-14)']`, `['nRa']`, `['nR4']` e o bloco do art. 132 (multilinha, com `ID3-02 (subgrupo 2)`) produzem itens `nR3-08, nR3-14, nRa, nR4, ID3-01, ID3-04, ID3-05, ID3-07, ID3-17, ID3-21` e `aguardando` com os cinco itens "(subgrupo 2)".
- [ ] Rodar: `php artisan test --compact tests/Unit/Tratamento/PlanilhaCnluParserTest.php` — falha (classe ausente).
- [ ] Implementar: por célula, se há `(` com códigos `n[A-Za-z0-9]+-\d+`, usar os de dentro; senão o token inicial. No bloco, uma linha por item, regex `ID\d+-\d+` e sufixo `(subgrupo N)` manda o código para `aguardando`.
- [ ] Rodar o teste — passa. Commit `feat: interpreta a lista CNLU da planilha por subgrupo`.

### Task 2: Workbook com metadados, vagas e CNLU literal
**Files:** modificar `PlanilhaTratamentoWorkbook.php`, `TratamentoEnquadramento.php`; criar a migration; teste `tests/Feature/Tratamento/PlanilhaWorkbookCompletudeTest.php`.

- [ ] Migration: `$table->json('metadados')->nullable()->after('vagas')`; model: `metadados` no `Fillable` e cast `array`.
- [ ] Teste (planilha real em `database/data/regras-20-08-26/planilha-regras.xlsx`): `ler()` devolve 2850 enquadramentos; o primeiro tem `metadados.atualizado_em === '2024-04-12'`, `metadados.atualizacao_recente === 'Regra 5'` e `vagas` com as chaves `carga_1_aplica`, `patio_1_analise`, `embarque_1_aplica`; `cnlu` não contém `ID3-02` e contém `nR3-08`; `notas` tem a chave `cnlu_aguardando_sedur`.
- [ ] Rodar — falha.
- [ ] Implementar: no mapa de cabeçalhos, registrar também o título com espaços colapsados (`preg_replace('/\s+/u', ' ', $titulo)`); `vagas` ganha as variantes `{carga|patio|embarque}_{1..3}_{aplica|analise|quantidade}` (embarque sem quantidade); `metadados` com `subdivisao`, `atualizado_em`, `atualizacao_recente`; `cnlu()` delega ao parser e `notas()` recebe `aguardando`.
- [ ] Rodar o teste — passa. Commit `feat: lê metadados, vagas e CNLU literal da planilha`.

### Task 3: complementar() e seed
**Files:** modificar `PlanilhaTratamentoImporter.php`, `TratamentoRegrasSeeder.php`, `DecisionTextCatalog.php`; copiar o xlsx; teste `tests/Feature/Tratamento/TratamentoComplementoTest.php`.
**Produces:** `PlanilhaTratamentoImporter::complementar(RuleVersion $versao, string $path): array{atualizados: int, sem_correspondencia: int, cnlu: int, pendencias: int}`.

- [ ] Texto de fábrica `tratamento.pendencia.sem_fluxo_curado` = `Aguardando confirmação da SEDUR: o texto da regra não define o fluxo de forma inequívoca. O motor usa a heurística desta versão.`
- [ ] Teste: rodar `TratamentoRegrasSeeder` duas vezes; verificar: `sem_correspondencia === 0`; todo enquadramento com `metadados` não nulo; `treatment_cnlu_itens` com 12 códigos e sem `ID3-02`; `treatment_notas` com `cnlu_aguardando_sedur`; `treatment_pendencias` com as 12 regras; contagens iguais após o segundo seed; `treatment_regra_ramos` inalterado.
- [ ] Rodar — falha.
- [ ] Implementar `complementar()` em transação: atualizar `vagas` e `metadados` por `(cnae, codigo_louos, subcategoria)`; substituir os itens CNLU da versão; upsert das notas; recriar as pendências das regras sem ramo em `treatment_regra_ramos`.
- [ ] Seeder: depois do import dos CSVs, chamar `complementar()` quando faltar `metadados` em algum enquadramento ou não houver item CNLU; o retorno antecipado de "planilha completa" passa a exigir também isso.
- [ ] Rodar o teste — passa. Commit `feat: seed completa a versão com a planilha xlsx`.

### Task 4: Pendências da versão vigente na tela
**Files:** modificar `LouosController.php`, `aba-enquadramento.tsx`; teste na suíte de UI do Motor de regras.

- [ ] Teste: a resposta Inertia da aba Enquadramento traz `enquadramento.pendencias` com as pendências da versão vigente.
- [ ] Controller: `pendencias` da vigente, ordenadas por regra.
- [ ] TSX: cartão "Regras aguardando a SEDUR" com `Regra N. motivo`, visível quando houver itens.
- [ ] Rodar o teste PHP e `npx vitest run resources/js/pages/gestao/louos`. Commit `feat: mostra as regras aguardando a SEDUR na versão vigente`.

### Task 5: Verificação
- [ ] Suítes de Tratamento, Louos, Decisao e Expresso; pint; build do frontend. Resultado real antes de afirmar pronto.
