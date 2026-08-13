---
phase: 12-auditoria-e-compliance
plan: 06
subsystem: abuso
tags: [hu-149, abuse-detection, strategy, idempotencia, malha-fina, scheduler, settings-gated, tdd, sqlite]

# Dependency graph
requires:
  - phase: 12-auditoria-e-compliance
    provides: "12-03 — ledger abuse_alerts (índice único parcial), enums AbuseSeverity/AbuseAlertStatus, parâmetros abuso.* + toggle features.deteccao_abuso (OFF)"
  - phase: 10-analise-tecnica-sedur
    provides: "MalhaFinaService + fine_mesh_referrals (HU-136) — destino do encaminhamento (referred_by_user_id já nullable)"
  - phase: 02-parametrizacao
    provides: "Settings::get/enabled (HU-014) com fallback em config + cache por chave"
provides:
  - "Contrato Strategy AbuseDetector { key(): string; detect(DetectionWindow): iterable<AbuseFinding> }"
  - "DTOs imutáveis AbuseFinding e DetectionWindow"
  - "2 detectores determinísticos reais (volume_cnpj, volume_contador) sobre dado real, SEM IA"
  - "Tag aditiva 'abuse.detectors' + bind de AbuseDetectionService no AppServiceProvider"
  - "AbuseDetectionService — toggle-gated, upsert idempotente em abuse_alerts, malha fina acima do limiar, auditado"
  - "MalhaFinaService::encaminharSistema (aditivo, ator=sistema, referred_by null)"
  - "Comando abuso:detectar idempotente no scheduler (daily/withoutOverlapping/onOneServer)"
affects:
  - "12-08 (detectores estruturais): ACRESCENTA 3 detectores à MESMA tag 'abuse.detectors' sem tocar o serviço"
  - "12-09 (painel de alertas + efetividade): consome abuse_alerts (rule_key/severity/evidence/fine_mesh_referral_id)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Strategy + tag de container ADITIVA: iterable<AbuseDetector> resolvido por $app->tagged('abuse.detectors'); novos detectores entram sem tocar o serviço"
    - "Fingerprint ESTÁVEL por (rule_key, identidade) — NÃO inclui a janela deslizante: única forma compatível com o scheduler diário + índice único parcial (idempotência real)"
    - "A janela decide SE o finding é emitido (contagem dentro do período), não o fingerprint"
    - "Caminho de SISTEMA aditivo na malha fina (referred_by null, marcador ator=sistema) — não altera os caminhos humanos (anti-regressão Fase 10)"
    - "Comando idempotente no scheduler como no-op honesto enquanto o toggle está OFF — precedente das Fases 9/11 (ExpressoIndeferirSemBap/EscalonarSla)"

key-files:
  created:
    - "app/Services/Abuso/Contracts/AbuseDetector.php"
    - "app/Services/Abuso/AbuseFinding.php"
    - "app/Services/Abuso/DetectionWindow.php"
    - "app/Services/Abuso/Detectors/VolumeCnpjDetector.php"
    - "app/Services/Abuso/Detectors/VolumeContadorDetector.php"
    - "app/Services/Abuso/AbuseDetectionService.php"
    - "app/Console/Commands/AbusoDetectarCommand.php"
    - "tests/Feature/Abuso/AbuseDetectorsTest.php"
    - "tests/Feature/Abuso/AbuseDetectionServiceTest.php"
  modified:
    - "app/Services/Analise/MalhaFinaService.php"
    - "app/Providers/AppServiceProvider.php"
    - "routes/console.php"

key-decisions:
  - "Fingerprint por (rule_key, identidade) estável e SEM a janela deslizante: incluir a janela quebraria a idempotência diária (cada run mudaria o fingerprint e duplicaria o alerta aberto)"
  - "Detectores de volume usam created_at na janela; severity por faixa (>2× limite → Alta, senão Média); viability_request_id = processo mais recente do grupo (dá à malha fina um processo concreto)"
  - "abuso.* NÃO têm fallback em config/sile.php (12-03 os colocou no ParameterSeeder, não no config); Settings::get é chamado com defaults literais iguais aos seeds (30/5/20/alta). Em teste (sem seed) valem os defaults; os casos limítrofes criam Parameter rows"
  - "encaminharSistema é ADITIVO (reusa motivoObrigatorio; NÃO altera encaminhar/encaminharLote/registrar) — suíte MalhaFina segue verde"
  - "Encaminha à malha fina só quando severity >= limiar E há viability_request_id; NUNCA transiciona status (RN-001)"

patterns-established:
  - "Pipeline de detecção de abuso: contrato Strategy + DTOs + serviço Settings-gated idempotente + comando/scheduler — base extensível para a 12-08"

# Metrics
duration: ~18min
completed: 2026-06-15
---

# Phase 12 Plan 06: Motor de detecção de abuso (HU-149) — alerta + malha fina, nunca punição Summary

**Pipeline de detecção de abuso real e determinístico (SEM IA): contrato `AbuseDetector` (Strategy) + DTOs imutáveis + 2 detectores de volume + `AbuseDetectionService` toggle-gated com upsert idempotente em `abuse_alerts` e encaminhamento à malha fina (caminho de sistema, aditivo) acima do limiar, mais o comando idempotente `abuso:detectar` no scheduler — NUNCA pune nem transiciona status (RN-001), no-op honesto enquanto desligado (default).**

## Performance
- **Duration:** ~18 min
- **Completed:** 2026-06-15
- **Tasks:** 3 (TDD estrito RED→GREEN em cada)
- **Files:** 9 criados, 3 modificados
- **Par paralelo:** wave 2 com 12-04/12-05 no mesmo diretório — dono único de `routes/console.php` e `AppServiceProvider` na wave; staging seletivo por arquivo (nunca `git add -A`)

## Contrato e DTOs (insumo da 12-08)

```php
interface AbuseDetector
{
    public function key(): string;
    /** @return iterable<AbuseFinding> */
    public function detect(DetectionWindow $window): iterable;
}
```

- **`AbuseFinding`** (`final readonly`): `ruleKey` (string), `severity` (AbuseSeverity), `fingerprint` (string determinístico/estável), `evidence` (array), `viabilityRequestId` (?int), `subject` (?Model), `windowStart`/`windowEnd` (?CarbonInterface).
- **`DetectionWindow`** (`final readonly`): `start`/`end` (CarbonImmutable). Fábrica `DetectionWindow::lastDays(int $days, ?CarbonImmutable $reference = null)` — derivada de `abuso.janela_dias`.

**Fingerprint (decisão crítica de idempotência):** estável por `hash('sha256', "{rule_key}|{identidade}")` — `identidade` = CNPJ (volume_cnpj) ou `created_by_user_id` (volume_contador). NÃO inclui a janela deslizante, porque o scheduler roda diário: se o fingerprint mudasse a cada run, o índice único parcial nunca deduplicaria e cada dia criaria um alerta aberto novo. A **janela** decide apenas SE o finding é emitido (a contagem é dentro do período); o fingerprint identifica a ocorrência. Após um gestor resolver (confirmado/descartado), o índice parcial libera um novo alerta aberto futuro com o mesmo fingerprint.

## 2 detectores determinísticos (tag 'abuse.detectors')

| Detector | key | Agrupa por | Limiar (HU-014) | subject | Severidade |
|---|---|---|---|---|---|
| `VolumeCnpjDetector` | `volume_cnpj` | `company_id` (created_at na janela) | `abuso.volume_cnpj.limite` (default 5) | `Company` | >2× → Alta, senão Média |
| `VolumeContadorDetector` | `volume_contador` | `created_by_user_id` (created_at na janela) | `abuso.volume_contador.limite` (default 20) | `User` | >2× → Alta, senão Média |

`evidence` carrega `total`, `limite`, `ids` e o identificador (`cnpj`/`created_by_user_id`); `viabilityRequestId` = processo mais recente do grupo (dá à malha fina um processo concreto para encaminhar). Queries Eloquent reais (`groupBy` + `havingRaw('count(*) > ?')`) — SEM IA.

**Consumo da tag:** `AppServiceProvider` faz `$this->app->tag([VolumeCnpjDetector::class, VolumeContadorDetector::class], 'abuse.detectors')` e binda `AbuseDetectionService` com `$app->tagged('abuse.detectors')`. A 12-08 **acrescenta** os 3 detectores estruturais à MESMA tag — o serviço não muda.

## AbuseDetectionService (API)

```php
/** @return array{executado: bool, criados: int, reaproveitados: int, encaminhados: int} */
public function detectar(): array
```

- **Toggle OFF (default):** `Settings::enabled('deteccao_abuso')` falso → retorna resumo zerado (`executado=false`), **NÃO grava nada e não audita** (no-op honesto).
- **Toggle ON:** monta a `DetectionWindow` de `abuso.janela_dias` (default 30); para cada finding faz **upsert idempotente** — se já há alerta ABERTO com `(rule_key, fingerprint)` → conta como `reaproveitado` (não duplica); senão cria (`status=aberto`, `detected_at`, `evidence`, `subject`, `viability_request_id`, janela).
- **Malha fina (acima do limiar):** se `finding.severity->isAtLeast($limiar)` (parâmetro `abuso.severidade_malha_fina`, default `alta`) **e** há `viability_request_id` → `MalhaFinaService::encaminharSistema(request, "suspeita de abuso: {rule_key}")` e grava `fine_mesh_referral_id` no alerta. **NUNCA transiciona status** (RN-001).
- **Auditoria:** loga o ciclo em `log_name='abuso'`, `event='abuso-detectar'` (criados/reaproveitados/encaminhados/janela/limiar) — RN-002/RN-003.

## MalhaFinaService::encaminharSistema (aditivo)

```php
public function encaminharSistema(ViabilityRequest $request, string $motivo): FineMeshReferral
```

Espelha o `registrar()` humano, mas com `referred_by_user_id => null` (ator=sistema) e auditoria marcando `'ator' => 'sistema'`/`'ator_id' => null`. Liga `in_fine_mesh` via `forceFill`, motivo obrigatório (reusa `motivoObrigatorio`), **sem transicionar o status** (ortogonal — RN-001, atinge até deferida). **Não altera** `encaminhar`/`encaminharLote`/`registrar` — anti-regressão Fase 10 mantida.

## Comando e scheduler

- `app/Console/Commands/AbusoDetectarCommand.php` (`abuso:detectar`): injeta `AbuseDetectionService`, chama `detectar()` e imprime o resumo — ou `"Detecção de abuso desligada (features.deteccao_abuso=0)."` no no-op. Só orquestra; nunca decide (RN-001/RN-003).
- `routes/console.php`: `Schedule::command('abuso:detectar')->daily()->withoutOverlapping()->onOneServer();` — no-op honesto enquanto o toggle está OFF (a SEDUR liga após validar limiares).

## Mapa CA → teste (HU-149)

| HU / RN | Teste | Status |
|---|---|---|
| CA-01 — alerta por padrão (volume) + evidência | `AbuseDetectorsTest`, `AbuseDetectionServiceTest::test_severity_acima_do_limiar...` | verde |
| CA-01 — malha fina acima do limiar | `AbuseDetectionServiceTest::test_severity_acima_do_limiar_encaminha...` | verde |
| CA-02 / RN-001 — NUNCA pune/transiciona status | `...encaminha_a_malha_fina_sem_mudar_status` | verde |
| Idempotência — não duplica alerta aberto | `...segunda_execucao_e_idempotente`, comando idempotente | verde |
| RN-002 — toggle OFF no-op honesto; ciclo auditado | `test_toggle_off_e_no_op_honesto...`, `test_ciclo_e_auditado_quando_ligado` | verde |
| Janela respeitada (fora não conta) | `test_volume_cnpj_respeita_a_janela...` | verde |
| ANTI-REGRESSÃO Fase 10 — encaminharSistema aditivo | suíte `MalhaFina` (11 testes) | verde |

## Task Commits

1. **Task 1: contrato + 2 detectores + tag** — `931d385` (feat, TDD)
2. **Task 2: AbuseDetectionService + encaminharSistema** — `ca9e603` (feat, TDD)
3. **Task 3: comando abuso:detectar + scheduler** — `8f5c5a9` (feat, TDD)

## Deviations from Plan

None — plano executado como escrito. Ajuste de autonomia (não desvio): como `abuso.*` não tem espelho em `config/sile.php` (12-03 os colocou no ParameterSeeder) e `config/sile.php` está FORA do escopo desta wave, `Settings::get` é chamado com defaults literais iguais aos seeds (30/5/20/alta) — comportamento idêntico ao espelho de config, sem tocar arquivo de outro escopo.

## Issues Encountered

- **Import errado de CarbonImmutable no teste (RED de transição).** A primeira versão do `AbuseDetectionServiceTest` importou `Illuminate\Support\CarbonImmutable` (inexistente), mascarando o RED real. Corrigido para `Carbon\CarbonImmutable` (mesma classe usada em `DetectionWindow`); RED reconfirmado pelo motivo certo (classe/método ausentes) antes do GREEN.

## Evidência de verificação (output real, fresco)

- RED Task 1: `AbuseDetectorsTest` → 6 errors (classes ausentes).
- GREEN Task 1: `AbuseDetectorsTest` → **6 passed, 22 assertions**.
- RED Task 2: `AbuseDetectionServiceTest` → 6 errors (service ausente + `encaminharSistema` indefinido).
- GREEN Task 2: `"AbuseDetectionServiceTest|MalhaFina"` → **17 passed, 83 assertions** (MalhaFina verde — anti-regressão).
- RED Task 3: 3 novos testes → 2 errors (comando ausente) + 1 failure (scheduler não registrado).
- GREEN Task 3: `"AbuseDetectorsTest|AbuseDetectionServiceTest"` → **15 passed, 67 assertions**.
- `php artisan abuso:detectar` (dev real) → `Detecção de abuso desligada (features.deteccao_abuso=0).` exit 0 (no-op honesto com toggle OFF).
- Verificação combinada do plano: `"AbuseDetectorsTest|AbuseDetectionServiceTest|MalhaFina"` → **26 passed, 116 assertions**.
- Suíte completa: `php artisan test --compact --exclude-group=postgis` → **1253 passed, 6487 assertions** (sem regressão; baseline 12-03 era 1219).
- `vendor/bin/pint --test` nos arquivos do plano → passed (sem pendências). `ReadLints` limpo.

## Next Phase Readiness

- **12-08** (detectores estruturais): basta ACRESCENTAR `PoligonoRepetidoDetector`/`EscritorioVirtualDetector`/etc. à tag `'abuse.detectors'` (parâmetro `abuso.escritorio_virtual.limite` já existe na 12-03) — `AbuseDetectionService` os consome sem alteração. Manter o fingerprint estável por (regra, identidade).
- **12-09** (painel + efetividade): consome `abuse_alerts` (rule_key, severity, status, evidence, viability_request_id, fine_mesh_referral_id) e a relação `fineMeshReferral`; a efetividade (RN-005) = confirmados ÷ gerados.
- **Pendência SEDUR (registrada):** limiares/janela são defaults honestos (5/20/30 dias, severidade alta) até a SEDUR calibrar com casos reais (HU-149 Observações); o toggle nasce OFF até validação. A simulação de impacto (HU-143/RN-002) antes de ativar é trabalho futuro.

---
*Phase: 12-auditoria-e-compliance*
*Completed: 2026-06-15*
