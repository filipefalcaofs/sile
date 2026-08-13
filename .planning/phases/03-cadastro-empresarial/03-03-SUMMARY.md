---
phase: 03-cadastro-empresarial
plan: 03
subsystem: companies
tags: [redesim, import, upsert, console-command, audit, cnae, laravel]

requires:
  - phase: 03-cadastro-empresarial
    plan: 01
    provides: Model Company, Enum CompanySource, Rule ValidCnpj, pivot company_cnae, AuditService, Model Cnae (code/active)
provides:
  - RedesimImportService (validação por item, upsert por CNPJ, sync de CNAEs, relatório auditado)
  - Comando redesim:importar {arquivo} (relatório pt-BR, exit codes)
  - database/data/redesim-exemplo.json (payload de referência REAL para homologação)
  - tests/Fixtures/redesim/payload-valido.json (fixture de teste)
  - Contrato de import isolado para o transporte real da Fase 13 (HU-103)
affects: [03-13-integracao-redesim, fase-13-transporte]

tech-stack:
  added: []
  patterns:
    - "Import com relatório {lidos, importados, atualizados, rejeitados[], avisos[]} (padrão CnaeImportService)"
    - "Upsert por CNPJ sem reescrever source (origem de criação imutável — Pitfall 8)"
    - "Validação por item com transação atômica (item rejeitado = zero escrita)"
    - "Sincronização exata de CNAEs via sync() preservando is_primary"
    - "Auditoria explícita via AuditService com rules_version para fluxo de sistema (causer null)"

key-files:
  created:
    - database/data/redesim-exemplo.json
    - tests/Fixtures/redesim/payload-valido.json
    - app/Services/RedesimImportService.php
    - app/Console/Commands/ImportRedesimCommand.php
    - tests/Feature/Companies/RedesimImportTest.php
  modified: []

key-decisions:
  - "CNAE inativo (principal ou secundário) importa COM AVISO — dado vindo da Junta é fato consumado; restrição 'somente ativos' vale só para seleção manual (HU-025/026)"
  - "Import NÃO cria vínculo usuário-empresa — payload REDESIM não traz usuário do portal; associação chega com o transporte (Fase 13)"
  - "Estrutura de payload é REFERÊNCIA A VALIDAR COM A SEDUR (REGIN/JUCEB) — o contrato service+comando absorve o ajuste sem retrabalho de domínio"
  - "Smoke test real executado em DB sqlite ISOLADO (mktemp) para não tocar a base de DEV do projeto"

requirements-completed: [HU-022]

duration: 12min
completed: 2026-06-12
---

# Phase 3 Plan 03: Importação REDESIM (HU-022) Summary

**HU-022 com lógica REAL atrás de contrato: `RedesimImportService` com validação por item, upsert por CNPJ, sincronização de CNAEs e relatório auditado (`rules_version` redesim-import-v1), mais o comando `redesim:importar {arquivo}` operacional para homologação — sem tela fingindo transporte e sem criar vínculo usuário-empresa.**

## Performance

- **Duration:** ~12 min
- **Completed:** 2026-06-12
- **Tasks:** 3
- **Files modified:** 5 (5 criados, 0 modificados)

## Accomplishments

- `database/data/redesim-exemplo.json`: payload de referência REAL com 2 itens (Banco do Brasil `00000000000191` CNAE 6422100+6499999, Petrobras `33000167000101` CNAE 0600001) — dados públicos RFB; todos os códigos de atividade conferidos no CSV oficial `cnaes-subclasses-2-3.csv`.
- `RedesimImportService`: valida protocolo, razão social, CNPJ (via `ValidCnpj`) e existência do CNAE principal por item; item inválido é rejeitado com motivo pt-BR carregando o protocolo, **sem inserção parcial** (transação por item). Upsert por CNPJ **não reescreve `source`** (Pitfall 8); CNAEs sincronizados com `sync()` exato (principal + secundários resolvidos).
- CNAE inativo (principal ou secundário) importa **com aviso** no relatório; CNAE secundário inexistente vira aviso e é ignorado (empresa criada só com os resolvidos).
- Comando `redesim:importar {arquivo}`: relatório pt-BR (Lidos/Importados/Atualizados + seções Rejeitados/Avisos), exit 1 para arquivo inexistente, JSON inválido ou todos os itens rejeitados; exit 0 caso contrário.
- Teste garante que **não existe rota pública de import** (CA-04) — entrada é exclusivamente comando/serviço.
- 15 testes no `RedesimImportTest` (10 do service + 5 do comando), todos verdes.

## Shape do relatório (contrato de retorno)

```php
[
  'lidos'       => int,            // itens processados do payload
  'importados'  => int,            // novas empresas (source = redesim)
  'atualizados' => int,            // empresas existentes atualizadas (source preservado)
  'rejeitados'  => array<string>,  // "protocolo {x}: {motivo}" — item não persistido
  'avisos'      => array<string>,  // "protocolo {x}: {aviso}" — item persistido com ressalva
]
```

Auditoria ao final: `AuditService->log('empresas', 'importacao-redesim', ..., result: <sucesso|falha>, rulesVersion: 'redesim-import-v1')` — `result` é `falha` somente quando TODOS os itens lidos foram rejeitados. Causer null (ação de sistema, rodado por comando).

## Decisões de validação

- **CNAE inativo importa com aviso** (não rejeita): o dado vem da Junta Comercial e é fato consumado. A restrição "somente CNAEs ativos" pertence à seleção manual das HU-025/026, não ao import.
- **Item atômico:** cada item persiste em sua própria `DB::transaction`; rejeição = zero escrita para aquele item, os demais seguem.
- **`source` imutável no update:** empresa criada como `manual` continua `manual` após reimport REDESIM, apenas marcando `redesim_synced_at` e `redesim_protocol` (espelha "upsert não toca active" do CnaeImportService).
- **Sem vínculo usuário-empresa:** o payload não traz usuário do portal — associação é da Fase 13.

## Evidência da execução real (comando)

Executado contra `database/data/redesim-exemplo.json` em DB sqlite **isolado** (`mktemp`, fora da base de DEV):

```
=== 1a execução ===
Importação REDESIM concluída.
Lidos: 2
Importados: 2
Atualizados: 0

=== 2a execução (idempotência) ===
Importação REDESIM concluída.
Lidos: 2
Importados: 0
Atualizados: 2

=== contagem ===
companies=2
company_user=0
```

Idempotência comprovada: 1ª importa 2, 2ª atualiza 2 sem duplicar; 2 empresas no total; 0 vínculos criados. `php artisan list | grep redesim` mostra `redesim:importar`.

## Task Commits

1. **Task 1: payload de referência + fixture** - `3c1f743` (feat)
2. **Task 2: RedesimImportService (TDD, 10 testes)** - `1a70365` (feat)
3. **Task 3: comando redesim:importar (TDD, +5 testes)** - `cf51e91` (feat)

_Tasks 2 e 3 seguiram TDD estrito (RED verificado antes do GREEN, REFACTOR com pint)._

## Files Created

- `database/data/redesim-exemplo.json` - payload de referência REAL (2 itens, dados públicos RFB)
- `tests/Fixtures/redesim/payload-valido.json` - fixture com item 1 (Banco do Brasil)
- `app/Services/RedesimImportService.php` - import com validação/upsert/CNAEs/relatório auditado
- `app/Console/Commands/ImportRedesimCommand.php` - comando redesim:importar com saída pt-BR e exit codes
- `tests/Feature/Companies/RedesimImportTest.php` - 15 testes (service + comando)

## Deviations from Plan

None - plano executado exatamente como escrito.

Nota operacional (não é deviation de escopo): o smoke test real do plano sugeria `migrate:fresh --seed` na base de DEV. Para não destruir dados da base de desenvolvimento do projeto, a execução real foi feita contra um sqlite temporário isolado (`mktemp`) com as mesmas migrações/seeds — a evidência de idempotência é equivalente e mais segura.

## Pendência REGIN/SEDUR (registrada para a Fase 13)

A estrutura do payload é **referência a validar com a SEDUR** — o manual nacional REDESIM (WS01/WS02/...) não é público; na Bahia o integrador é o REGIN (JUCEB). Falta o XSD/JSON oficial e credenciais de homologação. O contrato `RedesimImportService` + comando isolam o ajuste fino do parsing na Fase 13 (HU-103/transporte) sem retrabalho de domínio. Associação empresa importada ↔ usuário do portal também é definida na Fase 13 (payload não traz o usuário do SILE).

## Known Stubs

Nenhum stub. A lógica de import é real e exercitada por testes e por execução real do comando. A pendência REGIN/SEDUR é de TRANSPORTE (Fase 13), explicitamente atrás de contrato — não é stub de funcionalidade desta fase.

## Next Phase Readiness

- HU-022 concluída com lógica real de ponta a ponta atrás de contrato.
- O service está pronto para ser chamado pelo transporte real (webservice/fila) na Fase 13 — basta adaptar o parsing de entrada; domínio (upsert, validação, CNAEs, auditoria) está consolidado.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*

## Self-Check: PASSED

- Os 5 arquivos-chave criados existem no disco.
- Os 3 commits de tarefa (`3c1f743`, `1a70365`, `cf51e91`) existem no histórico.
