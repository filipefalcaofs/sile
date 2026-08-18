---
phase: 08-solicitacao-de-viabilidade
plan: 14
subsystem: contingencia-solicitacao
tags: [solicitacao-viabilidade, hu-148, contingencia, backoffice, operador, mesmo-motor, protocolo, origem-auditada, duplicidade, external-reference, anexos, rn-002, rn-003, rn-004, anti-fachada, inertia-react]

# Dependency graph
requires:
  - phase: 08-solicitacao-de-viabilidade
    plan: "10"
    provides: "ProtocolarSolicitacaoService::protocol(ViabilityRequest, User, bool) — número único + transição auditada + evento de domínio (o MESMO motor, reusado sem atalho)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "05"
    provides: "DuplicateRequestDetector::detect(Company) (reincidência por CNPJ, alerta não-bloqueante, RN-007)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "06"
    provides: "PropertyGeometryWriter::write (geometry derivada driver-aware) reusada na gravação do imóvel"
  - phase: 08-solicitacao-de-viabilidade
    plan: "08"
    provides: "padrão de armazenamento de anexos (disk parametrizado nunca público + sha256 real) e DocumentRequirementResolver::missing() (gate documental do protocolo)"
  - phase: 08-solicitacao-de-viabilidade
    plan: "01"
    provides: "ViabilityRequest (origin/contingency_reason/external_reference), enum ViabilityRequestOrigin::Contingencia, factory state contingency()"
  - phase: 08-solicitacao-de-viabilidade
    plan: "02"
    provides: "permissão registrar-contingencia (admin+gestor) + parâmetros de anexos/complementares"
  - phase: 02-administracao-base
    plan: "04"
    provides: "Portal\\CnaeSearchController reusado na rota gestao.contingencia.cnaes-disponiveis (só ativos)"
provides:
  - "App\\Http\\Controllers\\Gestao\\ContingenciaController (create + store) — registra na retaguarda com origem contingencia auditada e protocola pelo MESMO motor"
  - "App\\Http\\Requests\\Gestao\\RegistrarContingenciaRequest (beneficiário por CPF + empresa por CNPJ + motivo obrigatório + imóvel/área/CNAEs + anexos)"
  - "DuplicateRequestDetector::detectByExternalReference(string, ?int) — RN-004, vínculo externo (BAP/Regin) não duplica"
  - "rotas gestao.contingencia.{create,store,cnaes-disponiveis} sob permission:registrar-contingencia"
  - "tela gestao/contingencia/create.tsx (console) + item de navegação por permissão"
affects: [08-16-fechamento-seeds, 13-integracoes-regin]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Canal de operador colapsa criação+instrução+protocolo numa transação única e protocola pelo MESMO ProtocolarSolicitacaoService — bloqueio do motor (dados mínimos/documentos) reverte TUDO (anti-fachada: nada meio-criado, número não consumido)"
    - "refresh() após ViabilityRequest::create() para carregar o status default do banco ('rascunho') antes do protocolo no mesmo request (o portal nunca expôs isso porque cria-e-redireciona)"
    - "Duplicidade dupla: external_reference (BAP/Regin) é chave dura que BLOQUEIA (RN-004); reincidência por CNPJ é ALERTA não-bloqueante (RN-007, direito de petição) — ambas no DuplicateRequestDetector"
    - "Anexos gravados DENTRO da transação com coleta de caminhos por referência e limpeza (discardStoredFiles) no rollback do protocolo — o arquivo físico não participa do rollback transacional"

key-files:
  created:
    - app/Http/Controllers/Gestao/ContingenciaController.php
    - app/Http/Requests/Gestao/RegistrarContingenciaRequest.php
    - resources/js/pages/gestao/contingencia/create.tsx
    - tests/Feature/Solicitacao/ContingenciaTest.php
  modified:
    - app/Services/Solicitacao/DuplicateRequestDetector.php
    - routes/gestao.php
    - resources/js/layouts/gestao-layout.tsx

key-decisions:
  - "MESMO motor, sem atalho (RN-002): o store cria a solicitação origin=Contingencia com todos os dados e chama ProtocolarSolicitacaoService::protocol numa transação externa. Os guards do motor (status rascunho, dados mínimos, documentos obrigatórios) valem igual; bloqueio reverte a criação inteira (anti-fachada)."
  - "requester = beneficiário (cidadão informado por CPF, resolvido para um User existente); created_by = operador autenticado. CONSEQUÊNCIA do schema (requester_user_id NOT NULL + protocolo exige company_id): beneficiário e empresa precisam pré-existir; ausência → bloqueio comunicado honesto (nunca cria usuário/empresa fantasma). Auto-provisionamento/requester nullable é evolução futura (mudança de schema, fora do escopo)."
  - "refresh() após create(): o status nasce do default do banco; sem o refresh o motor receberia status null no mesmo request (descoberto no GREEN — TypeError no guardStatus)."
  - "RN-004 reusa o detector (detectByExternalReference, aditivo — detect() intacto): mesma referência externa não cancelada BLOQUEIA com aviso e link ao processo; reincidência por CNPJ permanece ALERTA não-bloqueante."
  - "Anexos suportados (documents[{requirement_id}]=arquivo) espelhando o 08-08 (disk Settings nunca público, sha256 real, auditoria documento-anexado) para a feature funcionar de ponta a ponta quando houver requisito obrigatório (entrega-funcional) — provado por test_mesmo_motor (bloqueia) e test_anexo_satisfaz_requisito (protocola)."
  - "CNAE picker reusa Portal\\CnaeSearchController numa rota da gestão (gestao.contingencia.cnaes-disponiveis), como em requisitos-documentais (08-04) — o console (guard gestao) não acessa /portal/cnaes."

patterns-established:
  - "Canal de operador na retaguarda = MESMA máquina de estados e MESMOS motores do canal do cidadão; muda só a origem (auditada) e o ator — molde direto para origem regin (Fase 13) que só troca o enum/origem"

# Metrics
duration: ~45 min
completed: 2026-06-14
---

# Phase 8 Plan 14: Registrar solicitação em contingência (HU-148) Summary

**A contingência (HU-148) é o canal de operador na retaguarda e o caminho REAL de operação enquanto o contrato Regin não chega (Fase 13) — zero adaptador simulado. O operador autorizado (permissão `registrar-contingencia`) preenche o MESMO conjunto de dados do formulário oficial e o `ContingenciaController@store` cria a solicitação `origin = Contingencia` (auditada — RN-003), com `requester` = beneficiário informado por CPF e `created_by` = operador, e PROTOCOLA pelo MESMO `ProtocolarSolicitacaoService` do canal normal (08-10), tudo em UMA transação — nunca um atalho decisório (RN-002): o gate de dados mínimos e o gate documental do motor valem igual, e qualquer bloqueio reverte a criação inteira (anti-fachada: nada meio-criado, número de protocolo não é consumido). Motivo obrigatório (RN-001). A referência externa (BAP/Regin informada pelo requerente) é gravada e, repetida, BLOQUEIA o duplicado (RN-004 — `detectByExternalReference`, aditivo ao detector do 08-05); a reincidência por CNPJ permanece ALERTA não-bloqueante (RN-007). Anexos opcionais por requisito são gravados como no 08-08 (disk parametrizado nunca público + sha256 real + auditoria), com limpeza dos arquivos no rollback. Sem a permissão, 403 auditado (CA-04). Tela `gestao/contingencia/create.tsx` no console (aviso de origem "contingência", mapa do imóvel, picker de CNAE, anexos) + navegação por permissão. ZERO dependência nova. TDD estrito (RED→GREEN com evidência fresca): ContingenciaTest 8/8; suíte completa 826/826 (4223 asserções, inclui 19 @group postgis).**

## Performance

- **Tasks:** 2 (backend de contingência + tela do console)
- **Files:** 4 criados + 3 modificados — ZERO dependência nova
- **Commits:** `15cc505` (feat backend) · `2fe98f5` (feat tela)

## Accomplishments

- **`ContingenciaController@store`** registra `origin = Contingencia` (auditada) com `requester` = beneficiário (CPF) e `created_by` = operador, e protocola pelo MESMO `ProtocolarSolicitacaoService` numa transação externa — sem atalho (RN-002).
- **`RegistrarContingenciaRequest`** valida o conjunto oficial: CPF (ValidCpf, resolve User) + CNPJ (resolve Company) + motivo obrigatório + polígono + área + CNAE principal/complementares (limite parametrizado) + anexos (mimetypes/max dinâmicos).
- **RN-004** real: `detectByExternalReference` bloqueia a vinculação duplicada da mesma referência externa; **RN-007** alerta por CNPJ (reuso do `detect`).
- **Anexos** gravados como no 08-08 (disk Settings nunca público, sha256, auditoria) com limpeza no rollback — o gate documental do motor protocola de verdade quando o requisito é satisfeito (anti-fachada).
- **Tela do console** (`gestao/contingencia/create.tsx`) com aviso de origem, mapa do imóvel (MapaSection), picker de CNAE (rota da gestão) e anexos por requisito; **navegação** "Nova solicitação (contingência)" por permissão.

## Rotas (nomes exatos)

| Método | URI | Nome | Ação |
|---|---|---|---|
| GET | `gestao/contingencia` | `gestao.contingencia.create` | Tela do console |
| GET | `gestao/contingencia/cnaes-disponiveis` | `gestao.contingencia.cnaes-disponiveis` | Busca de CNAEs (reusa CnaeSearchController) |
| POST | `gestao/contingencia` | `gestao.contingencia.store` | Registra + protocola em contingência |

Todas sob `auth:gestao` + `permission:acessar-gestao` + `lgpd.accepted` + `permission:registrar-contingencia`.

## Task Commits

TDD estrito (RED→GREEN com evidência fresca):

1. **Task 1: backend (controller + request + detector + rotas)** — `15cc505` (feat) — RED: 7 erros (rota inexistente) → GREEN: ContingenciaTest 7/7. Correção no GREEN: `refresh()` após `create()` (status default do banco).
2. **Task 2: tela do console + navegação** — `2fe98f5` (feat) — typecheck/build verdes; +1 teste de render (ContingenciaTest 8/8).

## Decisions Made

- **MESMO motor, sem atalho (RN-002):** criação + protocolo numa transação externa; bloqueio do motor reverte tudo (anti-fachada). O dispatch do evento de domínio respeita o commit externo (`ShouldDispatchAfterCommit`).
- **`requester` = beneficiário (CPF) / `created_by` = operador.** Beneficiário e empresa precisam pré-existir (schema: `requester_user_id` NOT NULL; protocolo exige `company_id`) — ausência é bloqueio comunicado honesto, nunca dado fantasma. Auto-provisionamento é evolução futura (mudança de schema).
- **`refresh()` após `create()`** para o motor ver `status = rascunho` no mesmo request.
- **RN-004 (não duplica)** reusa o detector com método aditivo; **RN-007** alerta por CNPJ não-bloqueante.
- **Anexos** mirroram o 08-08; gravados na transação com limpeza no rollback.

## Deviations from Plan

- **Anexos no store (além do mínimo testado):** o plano lista "anexos" no conjunto de dados; implementei o upload por requisito (e teste `test_anexo_satisfaz_requisito_e_protocola`) para a feature funcionar de ponta a ponta quando houver requisito obrigatório — entrega-funcional, evita o beco-sem-saída pós-08-16 (foto-fachada). `test_mesmo_motor_bloqueio_documental_se_aplica` prova o gate.
- **`detectByExternalReference` adicionado ao `DuplicateRequestDetector`** (aditivo; `detect()` intacto, portal inalterado) para reusar o detector literalmente (RN-004) com bloqueio duro do identificador externo.
- **+1 teste de render** (`test_operador_acessa_tela_de_contingencia`) e **+1 teste de upload** além dos 6 nomeados — cobertura anti-fachada (a tela renderiza; o fluxo protocola com anexo).

## Issues Encountered (coordenação Wave 7)

- **`routes/gestao.php` compartilhado com 08-15 (atendimento presencial):** o 08-15 anexou o grupo `atendimento` (+ imports `AssistedAttendanceController`/`ResolveAssistedAttendance`) após minha leitura, com o controller dele ainda untracked. Para um commit self-contained (sem referência a classe não versionada), removi cirurgicamente APENAS os trechos do 08-15 do arquivo, commitei só o meu grupo `contingencia`, e RESTAUREI os trechos do 08-15 exatamente. Houve uma janela de corrida (o 08-15 reescreveu o arquivo no intervalo); reconciliei para o estado consistente. Ao final, o 08-15 commitou, e `routes/gestao.php` ficou limpo com AMBOS os grupos (zero clobber).
- **`resources/js/layouts/gestao-layout.tsx` compartilhado:** o 08-15 já havia commitado o item "Atendimento presencial" no HEAD; meu diff carregou SÓ o item "Nova solicitação (contingência)" (commit limpo).
- **`pint --dirty` tocou arquivos do 08-15** (AssistedAttendanceController/AtendimentoPresencialTest) numa rodada inicial — NÃO commitei nenhum arquivo do 08-15 (staging sempre individual por caminho).
- **Sem divergência de ParameterSeeder:** a suíte integrada (incl. 08-12) fechou verde; meu escopo não tocou catálogo/permissões.

## Verification (evidência fresca)

- **RED Task 1:** `--filter=ContingenciaTest` → 7 erros ("Route [gestao.contingencia.create] not defined").
- **GREEN Task 1:** `--filter=ContingenciaTest` → 7/7 (após `refresh()`).
- **Task 2:** `npx tsc --noEmit` → 0 erros; `npm run build` → `✓ built` (`create-*.js` gerado); `--filter=ContingenciaTest` → 8/8 (52 asserções).
- **`php artisan route:list --path=gestao/contingencia`** → 3 rotas.
- **`vendor/bin/pint --dirty --format agent`** → passed.
- **Suíte completa:** `POSTGIS_TESTS_REQUIRED=true php artisan test --compact` → **826 testes, 826 passaram, 0 falhas** (4223 asserções; container `sile-pgsql` healthy, 19 @group postgis executados de verdade).

## Next Phase Readiness

- **EP13 (Regin — HU-103/HU-133):** a vinculação posterior do BAP reusa `external_reference` (já gravado) + `detectByExternalReference` (não duplica); a origem `regin` é o gancho do enum (só troca origem/ator, mesmo motor — exatamente o molde da contingência).
- **HU-146/EP15 (monitoramento):** a dimensão `origin = contingencia` persiste no processo e nas trilhas — base do monitoramento de volume (RN-005: uso recorrente indica problema de integração).
- **08-16 (fechamento/seeds):** ao seedar `foto-fachada`/`termo-concessao`, o gate documental passa a exigir anexos também na contingência — a tela já suporta anexo por requisito; é insumo do smoke.
- **Pendência registrada (degrada honesto):** beneficiário/empresa precisam pré-existir (schema); auto-provisionamento ou `requester` nullable fica para evolução (mudança de schema, fora do escopo desta HU).

---
*Phase: 08-solicitacao-de-viabilidade*
*Completed: 2026-06-14*
