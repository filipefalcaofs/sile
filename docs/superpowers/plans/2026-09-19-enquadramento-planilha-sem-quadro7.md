# Enquadramento pela planilha 20.08.26 — Quadro 7 sai do sistema — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline nesta sessão). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O enquadramento passa a ser o ramo da planilha 20.08.26 (perguntas + área + tipo de imóvel); o módulo Quadro 7 (domínio, tabela, import, tela) é removido; o consolidado do 11A passa a indeferir em `Não`.

**Architecture:** Domínio versionado `risco_tratamento` (quatro olhos) com 4 tabelas tipadas. `TratamentoRamoResolver` resolve o ramo; `LouosEnquadramentoService` consome o ramo e alimenta 10/11A. `EnquadramentoResult` troca `quadro7` por `enquadramento`. Risco e enquadramento saem do mesmo ramo.

**Tech Stack:** Laravel 13, PHPUnit, Eloquent, `RuleVersion`/`RuleVersionService`, Spatie activitylog.

## Global Constraints

- Regras são dado versionado, nunca `if` por número de regra no motor.
- Publicação de domínio sensível exige quatro olhos (`RuleVersionService`).
- Sem adaptador falso de REGIN: tipo de imóvel continua stub; valor ausente/desconhecido quando a regra depende → análise.
- Área do corte 1.250 m² = `used_area_m2`.
- 1.332 CNAEs; Regra 45 não existe; `9900-8/00` ativo.
- UI, testes e commits em pt-BR; código em inglês.
- TDD: nenhum código de produção sem teste falhando primeiro.
- Não commitar sem pedido explícito do usuário.

### Files

- Create: `app/Enums/RuleDomain.php` — case `RiscoTratamento`
- Create: `app/Models/TratamentoPergunta.php`, `TratamentoRegra.php`, `TratamentoEnquadramento.php`, `TratamentoCnaeBinding.php`
- Create: migrations das 4 tabelas `treatment_*`
- Create: `app/Services/Tratamento/TratamentoRamoInput.php`, `TratamentoRamoResult.php`, `TratamentoRamoResolver.php`
- Create: `app/Services/Tratamento/TratamentoRegrasImportService.php`
- Create: `database/data/regras-20-08-26/*.csv` (extraídos do xlsx)
- Create: seeder `TratamentoRegrasSeeder`
- Modify: `app/Services/Louos/LouosEnquadramentoService.php` (sem `enquadrarQuadro7`)
- Modify: `app/Services/Louos/EnquadramentoResult.php` (`quadro7` → `enquadramento`)
- Modify: `app/Services/Louos/EnquadramentoInput.php` (ganha `respostas`, `tipoImovel`)
- Modify: `app/Services/Risco/RiscoClassificationService.php` (nível do ramo)
- Modify: `app/Http/Controllers/Gestao/CnaeController.php` (remove `LouosQuadro7Faixa` do relocate)
- Delete: `app/Models/LouosQuadro7Faixa.php`, `LouosQuadro7ImportService.php`, `LouosQuadro7EnquadramentosConverter.php`, `LouosQuadro7Seeder.php`, factory, CSVs do 7
- Delete: migrations do 7 (criar drop)
- Modify: `resources/js/pages/gestao/louos/*` (sem aba 7), `resultado-viabilidade.tsx`, `decision-explanation.tsx`
- Modify: `resources/js/navigation/gestao-nav.ts` (item da planilha em Regras)

---

### Task 1: Domínio `risco_tratamento` + tabelas

**Files:**
- Modify: `app/Enums/RuleDomain.php`
- Create: `database/migrations/*_create_treatment_perguntas_table.php` (e as outras 3)
- Create: `app/Models/TratamentoPergunta.php` etc.
- Test: `tests/Unit/Tratamento/TratamentoDomainTest.php`

**Interfaces:**
- Produces: `RuleDomain::RiscoTratamento`, models `TratamentoPergunta/Regra/Enquadramento/CnaeBinding`

- [ ] **Step 1: Write the failing test**

```php
public function test_risco_tratamento_e_dominio_sensivel(): void
{
    $this->assertTrue(RuleDomain::RiscoTratamento->isSensitive());
    $this->assertSame('risco_tratamento', RuleDomain::RiscoTratamento->value);
}
```

- [ ] **Step 2: Run** `php artisan test --compact tests/Unit/Tratamento/TratamentoDomainTest.php` — FAIL (case não existe).
- [ ] **Step 3: Add case + label + isSensitive; criar migrations e models** (campos por tabela conforme spec §4; `rule_version_id` FK; índice único na chave natural).
- [ ] **Step 4: Run** — PASS. Rodar `php artisan migrate` em SQLite de teste.
- [ ] **Step 5: Do not commit.**

---

### Task 2: Extração dos CSVs da planilha

**Files:**
- Create: `database/data/regras-20-08-26/perguntas.csv`
- Create: `database/data/regras-20-08-26/regras.csv`
- Create: `database/data/regras-20-08-26/enquadramentos.csv`
- Create: `database/data/regras-20-08-26/cnae-bindings.csv`
- Create: `scripts/extrair-planilha-20-08-26.php` (one-shot, não runtime)

**Interfaces:**
- Consumes: `docs/artefatos/Planilha de regras - versão 20.08.26.xlsx`
- Produces: 4 CSVs no layout das tabelas da Task 1

- [ ] **Step 1: Script de extração** lê o xlsx (abas CNAES, Perguntas, Regras, Condicionantes) e grava os 4 CSVs.
- [ ] **Step 2: Rodar** e conferir contagens: 1.332 CNAEs, 2.854 enquadramentos, 32 perguntas, 59 regras, 32 condicionantes, zero R45, `9900-8/00` presente.
- [ ] **Step 3: Do not commit.**

---

### Task 3: Import versionado da planilha

**Files:**
- Create: `app/Services/Tratamento/TratamentoRegrasImportService.php`
- Create: `database/seeders/TratamentoRegrasSeeder.php`
- Test: `tests/Feature/Tratamento/TratamentoRegrasImportTest.php`

**Interfaces:**
- Consumes: CSVs da Task 2, `RuleVersion`
- Produces: `TratamentoRegrasImportService::import(RuleVersion $version, string $dir): array{lidos,importados,rejeitados}`

- [ ] **Step 1: Write the failing test** — import idempotente + contagens (CA-08).
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implementar** no padrão de `RiscoMunicipalImportService` (upsert por chave natural; relatório de rejeitados).
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Seeder** publica rascunho + (em dev) versão vigente `planilha-20-08-26`.
- [ ] **Step 6: Do not commit.**

---

### Task 4: `TratamentoRamoResolver`

**Files:**
- Create: `app/Services/Tratamento/TratamentoRamoInput.php`
- Create: `app/Services/Tratamento/TratamentoRamoResult.php`
- Create: `app/Services/Tratamento/TratamentoRamoResolver.php`
- Test: `tests/Unit/Tratamento/TratamentoRamoResolverTest.php`

**Interfaces:**
- Consumes: models da Task 1, `TipoImovel`
- Produces: `TratamentoRamoResolver::resolver(TratamentoRamoInput): TratamentoRamoResult`

- [ ] **Step 1: Write the failing test** — golden cases (CA-07): R5/R1 área ≤ 1250 → baixo/expresso/`07.12.13`; área > 1250 → médio; galpão+ID → alto; CNLU → `nao_resolvido`.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implementar** a ordem de resolução da spec §5.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Do not commit.**

---

### Task 5: Motor LOUOS consome o ramo; drop do 7

**Files:**
- Modify: `app/Services/Louos/LouosEnquadramentoService.php`
- Modify: `app/Services/Louos/EnquadramentoResult.php`
- Modify: `app/Services/Louos/EnquadramentoInput.php`
- Delete: `app/Models/LouosQuadro7Faixa.php`, services/import/converter/seeder do 7
- Create: migration drop `louos_quadro7_faixas`
- Test: `tests/Feature/Louos/LouosEnquadramentoTest.php` (reescrito)

**Interfaces:**
- Consumes: `TratamentoRamoResolver`
- Produces: `EnquadramentoResult.enquadramento` (sem `quadro7`)

- [ ] **Step 1: Write the failing test** — enquadramento pelo ramo (CA-01/02/03); `quadro7` ausente no resultado.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implementar** — `enquadrar()` chama o resolver; `EnquadramentoResult` troca a chave; `EnquadramentoInput` ganha `respostas`/`tipoImovel`; drop da tabela e do domínio.
- [ ] **Step 4: Run** — PASS. Rodar regressão das suítes Louos/Viabilidade/Expresso.
- [ ] **Step 5: Do not commit.**

---

### Task 6: Correção do consolidado do 11A

**Files:**
- Modify: `app/Services/Louos/LouosEnquadramentoService.php` (`consolidar`, `coletarCondicionantes`)
- Test: `tests/Feature/Louos/LouosConsolidacaoTest.php`

**Interfaces:**
- Consumes: `quadro11a` com `condicoes`
- Produces: `Não` → `nao_permitido`; `R` → `pendente`; condição textual → `permitido_com_condicoes`

- [ ] **Step 1: Write the failing test** — CA-04b (11A `Não` indeferre mesmo com 10 permitido).
- [ ] **Step 2: Run** — FAIL (hoje vira condicionante).
- [ ] **Step 3: Implementar** a precedência da spec §6.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Do not commit.**

---

### Task 7: Risco/fluxo/TLL do mesmo ramo

**Files:**
- Modify: `app/Services/Risco/RiscoClassificationService.php`
- Modify: `app/Services/Viabilidade/ConsultaViabilidadeService.php`
- Test: `tests/Feature/Risco/RiscoClassificationServiceTest.php`

**Interfaces:**
- Consumes: `TratamentoRamoResolver`
- Produces: nível/fluxo/TLL do ramo; CA-MR-08 (risco não instancia o motor LOUOS)

- [ ] **Step 1: Write the failing test** — risco do ramo, não do CNAE.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implementar** — o orquestrador passa o ramo resolvido; o risco lê nível/fluxo/TLL do ramo.
- [ ] **Step 4: Run** — PASS.
- [ ] **Step 5: Do not commit.**

---

### Task 8: UI sem Quadro 7 + item da planilha em Regras

**Files:**
- Modify: `resources/js/pages/gestao/louos/index.tsx`, `rascunho.tsx`, `manual.tsx`, `quadro-fields.ts`, `quadro-columns.tsx`
- Modify: `resources/js/components/viabilidade/resultado-viabilidade.tsx`
- Modify: `resources/js/components/auditoria/decision-explanation.tsx`
- Modify: `resources/js/navigation/gestao-nav.ts`
- Test: `resources/js/navigation/gestao-nav.test.ts`

**Interfaces:**
- Produces: console LOUOS só com 10/11A; card “Enquadramento de uso”; item “Planilha de regras” em Regras

- [ ] **Step 1: Remover** a aba/seletor do 7 e as menções na UI.
- [ ] **Step 2: Adicionar** o item da planilha no grupo Regras (fim do grupo).
- [ ] **Step 3: Rodar** `npx vitest run resources/js/navigation/gestao-nav.test.ts` — PASS.
- [ ] **Step 4: Rodar** `npx tsc --noEmit` e `npm run build` — verdes.
- [ ] **Step 5: Do not commit.**

---

### Task 9: Limpeza de referências

**Files:**
- Modify: `app/Http/Controllers/Gestao/CnaeController.php` (remove `LouosQuadro7Faixa` do relocate)
- Modify: `database/seeders/DatabaseSeeder.php`, `DemonstracaoClienteSeeder.php` (sem `LouosQuadro7Seeder`)
- Modify: testes que seedam/factorizam o 7 (Expresso, Analise, EscritorioVirtual, Decisao)
- Delete: `tests/Fixtures/golden/louos/quadro7-*.json`

- [ ] **Step 1: Remover** todas as referências a `LouosQuadro7`/`louos_quadro7`.
- [ ] **Step 2: Rodar** `php artisan test --compact` — verde.
- [ ] **Step 3: Rodar** `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 4: Do not commit.**

---

## Self-Review

- Spec §4 (tabelas) → Task 1. §5 (resolver) → Task 4. §6 (motor/consolidado) → Tasks 5/6. §7 (o que some) → Tasks 5/8/9. CA-08/09 → Task 3. CA-04b → Task 6.
- Tipos: `TratamentoRamoResolver::resolver` usado nas Tasks 4, 5 e 7 com a mesma assinatura.
- Sem placeholders.
