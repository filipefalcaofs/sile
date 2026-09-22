# Fluxo por ramo da planilha como dado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** o fluxo (expresso/semiexpresso) de cada ramo da planilha de tratamento vira **dado versionado curado dos textos oficiais das regras** — não mais heurística de nível + tipo de imóvel. Decisões registradas 22/09/2026: correção estrutural (a planilha prevalece sobre a RN-041-B — "Fluxo Expresso (ALTO RISCO)" da regra 51 é honrado) e, sem resposta que decida a linha, o sistema **não enquadra** (análise), nunca assume a primeira linha ID.

**Origem:** relatório SEDUR 21/09/2026 (itens 18, 20, 21, 25) + e-mail (item 5). Verificação com simulação real: `dupla-r49-r50`, `regra-1-nr`, `regra-1-id`, `regra-51`.

**Fora de escopo:** regras sem seção de fluxo no texto oficial (7 de 60) ficam na heurística atual, marcadas como pendentes de curadoria.

## Design

A planilha oficial (`regras.csv`) tem, por regra, seções "Fluxo Expresso" e "Fluxo Semiexpresso" com bullets "Se a resposta for SIM/NÃO na pergunta X [+ faixa de área] [+ tipo de espaço] → código LOUOS". Isso é uma tabela de decisão. O import ganha uma tabela curada `treatment_regra_ramos`:

| coluna | origem |
|---|---|
| `regra` | número da regra |
| `pergunta` | pergunta decisiva do ramo (11, 2, 3…) |
| `resposta` | bool — o valor que seleciona o ramo (null = qualquer) |
| `faixa` | `ate_1250` / `acima_1250` / null (qualquer) |
| `tipo_dirige` | bool — ramo vale para galpão/container/edificação residencial (null = qualquer) |
| `codigo_louos` | enquadramento do ramo (null = "segundo enquadramento disponível" — o resolver escolhe a linha não-escritório) |
| `fluxo` | `expresso` / `semiexpresso` |

A curadoria é **extração programática dos textos + revisão** — o parser lê as seções e bullets; o que não parseia vira pendência explícita, nunca inferência silenciosa. O `TratamentoRamoResolver` consulta a tabela: ramo casado → fluxo do dado; sem ramo → heurística atual (degradação honesta, logada). `FluxoExpressoService`: o gate `temAltoRisco()` cede quando o ramo curado diz expresso.

## File map

| Arquivo | Papel |
|---|---|
| `database/data/regras-20-08-26/regras-fluxo.csv` | Ramo curado por regra (extraído do texto oficial + revisão) |
| `scripts/`extrai-fluxo-regras.py | Extrator seções/bullets → CSV + relatório de não parseados |
| `database/migrations/…_create_treatment_regra_ramos_table.php` | Tabela versionada |
| `app/Models/TratamentoRegraRamo.php` | Model |
| `app/Services/Tratamento/TratamentoRegrasImportService.php` | Import do CSV no domínio RiscoTratamento |
| `app/Services/Tratamento/TratamentoRamoResolver.php` | Fluxo do ramo curado + não-enquadrar sem resposta decisiva |
| `app/Services/Expresso/FluxoExpressoService.php` | Gate alto risco cede ao ramo expresso |
| `tests/Feature/Tratamento/TratamentoRegraRamoTest.php` | Ramo curado |
| `tests/Unit/Tratamento/TratamentoRamoResolverTest.php` | Resolver |
| `tests/Feature/Risco/ReginProtocoloSimulacaoTest.php` | Itens 18/20/21/25 |

## Tasks

### Task 1: Extrator + CSV curado

- [ ] Parser `scripts/extrai-fluxo-regras.py`: por regra, seções → bullets → (pergunta, resposta, faixa, tipo, codigo_louos, fluxo); bullets não parseados listados no relatório
- [ ] Revisar manualmente as regras do relatório (1, 5, 24, 25, 49, 50, 51, 52, 2, 3, 6, 7, 18, 19, 56, 57, 58, 59) contra o texto oficial
- [ ] `regras-fluxo.csv` commitado com header documentando a curadoria

### Task 2: Tabela + import

- [ ] Migration `treatment_regra_ramos` (rule_version_id, regra, pergunta, resposta nullable, faixa nullable, tipo_dirige nullable, codigo_louos nullable, fluxo) + índice (rule_version_id, regra)
- [ ] Model + import no `TratamentoRegrasImportService` (lê `regras-fluxo.csv` quando presente)
- [ ] Teste: import carrega os ramos; idempotente

### Task 3: Resolver lê o ramo curado

- [ ] RED: item 20 — 4511-1/01 P11=SIM → semiexpresso (hoje expresso); item 25 — 1032-5/01 P2=NÃO → expresso mesmo ALTO; item 18 — 8630-5/02 P11=SIM → expresso + 07.05.03
- [ ] Resolver: casa o ramo curado (pergunta decisiva × resposta × faixa × tipo) → fluxo + codigo_louos do dado; sem ramo → heurística atual
- [ ] Item 21: múltiplas linhas sem resposta decisiva → `nao_resolvido` (análise), nunca a primeira linha ID
- [ ] GREEN + Pint

### Task 4: RN-041-B cede ao ramo expresso

- [ ] RED: regra-51 (alto, ramo expresso) → deferida expresso com TVL
- [ ] `FluxoExpressoService`: gate alto risco só quando o ramo NÃO marcou expresso explícito
- [ ] GREEN + Pint

### Task 5: Regressão dos itens do relatório

- [ ] Simulação real dos 4 códigos com os resultados esperados
- [ ] Suíte completa verde
- [ ] Commit(s) em pt-BR
