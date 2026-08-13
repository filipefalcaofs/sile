---
phase: 10-analise-tecnica-sedur
plan: 12
subsystem: services
tags: [malha-fina, fine-mesh, audit, orthogonal-flag, batch, service]

# Dependency graph
requires:
  - phase: 10-analise-tecnica-sedur/10-02
    provides: "FineMeshReferral (reason/resolved_at) + coluna in_fine_mesh (FORA do fillable) + relação ViabilityRequest::fineMeshReferrals()"
  - phase: 10-analise-tecnica-sedur/10-01
    provides: "permissão aditiva encaminhar-malha-fina (aplicada no endpoint em 10-15)"
provides:
  - "MalhaFinaService: encaminhar (single), encaminharLote (RN-004), resolver — serviço PURO (sem rota)"
  - "MalhaFinaException (motivo obrigatório — RN-002)"
  - "Encaminhamento ORTOGONAL ao status: liga in_fine_mesh + grava fine_mesh_referrals SEM transicionar o status (qualquer status, inclusive deferida — RN-001)"
affects: [10-14, 10-15, 10-16]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Flag ortogonal ao estado: in_fine_mesh ligada via forceFill (fora do fillable), NUNCA pela StateMachine — não muda o desfecho, só sinaliza revisão"
    - "Invariante in_fine_mesh = existe encaminhamento aberto: resolver baixa a flag só ao fechar o último referral aberto"
    - "Lote resiliente com auditoria por processo (espelha DistribuicaoService): motivo validado uma vez, falhas isoladas em {ok, falhas}"

key-files:
  created:
    - app/Services/Analise/MalhaFinaService.php
    - app/Services/Analise/MalhaFinaException.php
    - tests/Feature/Analise/MalhaFinaServiceTest.php
  modified: []

key-decisions:
  - "in_fine_mesh ligada por forceFill no serviço (fora do fillable, padrão 10-02); ZERO chamada à ViabilityRequestStateMachine — malha fina é flag + tabela, não estado (RN-001/RN-003)"
  - "motivo normalizado com trim() e validado uma vez (string vazia ou só espaços → MalhaFinaException); no lote a validação é precondição do lote inteiro"
  - "resolver mantém o invariante in_fine_mesh = há referral aberto: baixa a flag só quando não resta nenhum aberto (discrição do plano — documentada e testada)"
  - "encaminharLote isola falhas por processo (catch Throwable) e retorna {ok, falhas} espelhando DistribuicaoService::distribuirLote"

patterns-established:
  - "Serviço de domínio puro testado no nível de serviço (endpoint adiado para 10-15), com auditoria síncrona por processo (log_name 'analise')"

# Metrics
duration: ~12min
completed: 2026-06-14
---

# Phase 10 Plan 12: Malha Fina (serviço puro, ortogonal ao status) — HU-136

**`MalhaFinaService` real: encaminha QUALQUER processo (inclusive deferido — corrige o bug legado, RN-001) à malha fina ligando a flag `in_fine_mesh` + gravando `fine_mesh_referrals` (motivo obrigatório, RN-002), SEM transicionar o status (ortogonal — não chama a StateMachine). Repetível e em lote (RN-004) com auditoria SÍNCRONA por processo; `resolver` dá baixa e desliga a flag ao fechar o último encaminhamento aberto. TDD estrito: `MalhaFinaServiceTest` 7/7 (33 asserções), incluindo teste sobre processo DEFERIDO.**

## Performance
- **Duration:** ~12 min
- **Tasks:** 1 (TDD: commit RED + commit GREEN)
- **Files:** 3 criados (serviço, exceção, teste), 0 modificados

## Accomplishments
- Malha fina como **provocação humana** ortogonal ao status: flag + tabela, não estado — atinge até processo deferido (RN-001).
- Motivo obrigatório (RN-002) com normalização (`trim`) e exceção de domínio dedicada; nada é gravado quando o motivo é vazio.
- Lote (RN-004) com auditoria por processo e isolamento de falhas; repetível (cada encaminhamento é uma linha).
- `resolver` marca `resolved_at` sem mexer no status e mantém o invariante da flag.
- **Serviço PURO** (sem rota; ZERO dependência nova; não toca a StateMachine) — file-disjunto dos planos paralelos 10-10/10-11.

## Contrato para os planos seguintes (assinaturas EXATAS)

`App\Services\Analise\MalhaFinaService` (injeta `AuditService`):

- **`encaminhar(ViabilityRequest $request, User $ator, string $motivo): FineMeshReferral`**
  Valida o motivo (RN-002 → `MalhaFinaException`); em transação cria `fine_mesh_referrals` (`referred_by_user_id=$ator`, `reason=$motivo` normalizado, `resolved_at=null`) + `forceFill(['in_fine_mesh' => true])` (NÃO transiciona status, RN-001); audita síncrono `('analise', 'malha-fina-encaminhar')` com `motivo` e `status` atual. Repetível.
- **`encaminharLote(iterable $requests, User $ator, string $motivo): array`**
  Motivo validado uma vez; itera `encaminhar` por processo, auditando cada um (RN-004) e isolando falhas. Retorna `array{ok: int, falhas: list<array{viability_request_id: int, motivo: string}>}`.
- **`resolver(FineMeshReferral $referral, User $ator): void`**
  `forceFill(['resolved_at' => now()])`; se não restar nenhum encaminhamento aberto no processo, `forceFill(['in_fine_mesh' => false])`; audita `('analise', 'malha-fina-resolver')`. NÃO mexe no status.

`App\Services\Analise\MalhaFinaException` — `static motivoObrigatorio(): self` (RuntimeException; caller traduz em 422).

### Eventos de auditoria (log_name `analise`)
- `malha-fina-encaminhar` — properties: `viability_request_id`, `protocol_number`, `fine_mesh_referral_id`, `status` (atual, no momento), `motivo`, `ator_id`.
- `malha-fina-resolver` — properties: `viability_request_id`, `fine_mesh_referral_id`, `in_fine_mesh` (resultante), `ator_id`.

## Mapa CA → teste (verde)
| HU / RN | Teste |
|---|---|
| HU-136 RN-001 — qualquer status, inclusive deferido | `test_encaminhar_processo_deferido_funciona_e_nao_muda_o_status` |
| HU-136 RN-002 — motivo obrigatório | `test_motivo_vazio_lanca_erro_de_dominio_e_nao_cria_referral` |
| HU-136 RN-002 — auditoria síncrona por processo | `test_encaminhar_liga_a_flag_e_cria_referral_sem_mexer_no_status` |
| HU-136 RN-003 — ortogonal (flag, não estado) / repetível | `test_encaminhar_liga_a_flag...` + `test_mesmo_processo_pode_ser_encaminhado_mais_de_uma_vez` |
| HU-136 RN-004 — lote com auditoria por processo | `test_encaminhar_em_lote_cria_referral_e_audita_por_processo` |
| Resolver + invariante da flag | `test_resolver_marca_resolved_at_sem_mexer_no_status` + `test_flag_permanece_enquanto_houver_encaminhamento_aberto` |

## Task Commits
1. **RED** — teste de falha do `MalhaFinaService` — `dbed2ad` (test)
2. **GREEN** — `MalhaFinaService` + `MalhaFinaException` (malha fina ortogonal) — `af7bb66` (feat)

_RED→GREEN com evidência fresca; sem fase de refactor (código já mínimo e limpo)._

## Decisions Made
- **Exceção de domínio dedicada (`MalhaFinaException`)** — espelha `DistribuicaoException`/`AnalysisRecordImutavelException`; não estava no `files_modified` do plano, mas é a convenção dos serviços irmãos (cada serviço com sua exceção). Decisão de implementação dentro do escopo.
- **`resolver` baixa a flag ao fechar o último aberto** — escolha entre as opções da discrição do plano: mantém o invariante `in_fine_mesh = existe encaminhamento aberto`, evitando flag órfã. Testado (mantém com 2 abertos, baixa ao resolver o último).
- **`encaminharLote` valida o motivo uma vez** — o motivo é precondição do lote inteiro (RN-004 aplica motivo único); evita N falhas idênticas por motivo vazio.

## Deviations from Plan
### Auto-fixed Issues
Nenhum desvio de comportamento — plano executado como escrito. Único acréscimo: a classe `MalhaFinaException` (convenção dos serviços irmãos), documentada acima.

## Verification (evidência fresca)
- `vendor/bin/pint --dirty --format agent` → **passed**.
- `php artisan test --compact --filter=MalhaFinaServiceTest` → **7/7 (33 asserções)**.
- Critérios de aceite por grep em `app/Services/Analise/MalhaFinaService.php`: `in_fine_mesh` presente (ligada/baixada via `forceFill`); **zero chamada à StateMachine** (a palavra aparece só em comentário, documentando a ortogonalidade); `->log('analise'` presente (auditoria síncrona).
- Serviço puramente aditivo (3 arquivos novos, 0 modificados) — não toca rotas nem a StateMachine; não regride a suíte.

## Next Phase Readiness
- **10-14** (consulta HU-082): filtra `in_fine_mesh` (índice pronto) e usa `encaminharLote` no lote da consulta (seleção múltipla, RN-004).
- **10-15** (endpoint encaminhar): expõe `encaminhar`/`encaminharLote` sob a permissão `encaminhar-malha-fina` (10-01); traduz `MalhaFinaException` em 422.
- **10-16** (ação na consulta/detalhe): botão "encaminhar à malha fina" (single + lote) consumindo o endpoint de 10-15.

---
*Phase: 10-analise-tecnica-sedur*
*Completed: 2026-06-14*
