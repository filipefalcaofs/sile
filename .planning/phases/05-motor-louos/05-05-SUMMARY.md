---
phase: 05-motor-louos
plan: 05
subsystem: motor
tags: [louos, motor, consolidacao, fundamentacao, vagas, restricoes-zeis, anti-fachada, parametrizacao, tdd, sqlite]

# Dependency graph
requires:
  - phase: 05-motor-louos
    plan: 04
    provides: "Dimensões territoriais quadro10/quadro11/quadro11a com shape final (permissao/condicoes/base_legal/motivo) e degradação honesta (indisponivel sem zona/atributo viário)"
  - phase: 05-motor-louos
    plan: 03
    provides: "enquadrar() + dimensão quadro7 real + auditoria 'louos'/'enquadramento' + consolidar() provisório (Pendente fixo)"
  - phase: 05-motor-louos
    plan: 01
    provides: "EnquadramentoInput.vagasDeclaradas, enum ResultadoViabilidade/Quadro10Permissao, parâmetro louos.vagas.exigencia_por_grupo (catálogo)"
  - phase: 04-georreferenciamento
    provides: "TerritoryResult.restricoes (camada ZEIS — status identificado + itens[] incidentes)"
  - phase: 06-classificacao-risco
    provides: "Padrão RiscoClassificationService::buildFundamentacao + parametrização sem deploy (Parameter::saved invalida cache)"
provides:
  - "consolidar() final: parecer único permitido/permitido_com_condicoes/nao_permitido/pendente com precedência anti-fachada"
  - "buildFundamentacao(): Lei 9.148/2016 + quadros aplicados + base_legal das regras (HU-045), honesto na pendência"
  - "avaliarVagas() (HU-042): exigência parametrizada por grupo vs vagas declaradas — condicionante, nunca bloqueio"
  - "avaliarRestricoes() (HU-043): restrições ZEIS do território viram condicionantes incidentes"
  - "Shape definitivo do consolidado {resultado, fundamentacao[], condicionantes[], motivo} — contrato do EP07/sandbox/golden"
affects: [05-08-sandbox, 05-09-golden-cases, 07-consulta-previa, 08-solicitacao]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Precedência anti-fachada do consolidado: enquadramento ausente OU zona indisponível/sem-regra => pendente (nunca veredito sem o dado)"
    - "Condicionante incidente (rebaixa para permitido_com_condicoes) vs informativa (alimenta a ficha, não rebaixa) — vagas não parametrizada/conforme é informativa; não conforme/ZEIS/via/zona condicionada incide"
    - "Fundamentação espelha RiscoClassificationService::buildFundamentacao — cita só a regra efetivamente aplicada; na pendência cita o motivo, não a regra não aplicada"
    - "Vagas parametrizadas via Settings::get('louos.vagas.exigencia_por_grupo') — efeito sem deploy (Parameter::saved invalida cache)"

key-files:
  created: []
  modified:
    - app/Services/Louos/LouosEnquadramentoService.php
    - tests/Feature/Louos/LouosConsolidacaoTest.php

key-decisions:
  - "Precedência 1 usa `!== STATUS_IDENTIFICADO` (defensivo) em vez de `=== STATUS_NAO_ENCONTRADO`: o Quadro 7 só retorna identificado/nao_encontrado, mas qualquer não-identificado vira pendente (anti-fachada estrita)"
  - "Proibido (nao_permitido) curto-circuita com condicionantes [] — condições só fazem sentido quando o uso é permitido; vagas/restrições não são coletadas nesse ramo"
  - "Condicionante informativa de vagas (conforme null, exigência não parametrizada) NÃO rebaixa o veredito — só vagas conforme=false incide (resolve a ambiguidade do plano no teste 8)"
  - "buildFundamentacao recebe motivoPendencia opcional; quando presente (pendente), anexa o motivo e NÃO cita a regra não aplicada (Quadro 10 ausente do parecer sem zona)"
  - "avaliarVagas item não declarado conta como zero (não conforme se exigência > 0); comparação numérica por item do mapa exigido"
  - "avaliarRestricoes só itera itens quando restricoes.status === identificado (incidência real); indisponivel/nao_encontrado => [] (não vira pendência — restrição não bloqueia sozinha)"

patterns-established:
  - "Veredito final do motor LOUOS: consolidar() combina as 4 dimensões; condicionantes carregam tipo (zona|via|vagas|restricao) para a ficha HU-135 e o discriminador de incidência"

# Metrics
duration: 7min
completed: 2026-06-14
---

# Phase 5 Plan 05: Consolidação e Fundamentação do Motor LOUOS Summary

**O motor LOUOS está fechado: `consolidar()` combina as dimensões dos Quadros 7/10/11/11A num parecer único e fundamentado (HU-044/HU-045), aplica condicionantes de vagas parametrizadas (HU-042) e restrições especiais via camada ZEIS do território (HU-043). A precedência é anti-fachada: sem enquadramento (Quadro 7) ou sem zona (Quadro 10 indisponível/sem regra) o consolidado é SEMPRE `pendente` — o motor jamais declara permitido/nao_permitido sem o dado real. Vagas e restrições alimentam condicionantes (permitido_com_condicoes) sem bloquear sozinhas.**

## Performance

- **Duration:** ~7 min (start record 06:15:03Z → 06:21:54Z)
- **Tasks:** 2 (cada uma TDD RED → GREEN → pint)
- **Files:** 2 modificados (service + teste de consolidação, criado)
- **Commits:** `f6bc878` (consolidação + fundamentação), `073989d` (vagas + restrições ZEIS) + docs deste SUMMARY

## Contrato entregue (insumo direto de 05-08 sandbox, 05-09 golden e EP07)

### Regra final de precedência do consolidado (`consolidar`)

A assinatura passou a receber o input: `consolidar(array $quadro7, array $quadro10, array $quadro11, array $quadro11a, EnquadramentoInput $input): array`.

1. **Quadro 7 não identificado** → `pendente`, motivo `'Atividade sem enquadramento no Quadro 7 — segue para análise técnica'`. (Sem grupo de uso não há permissão a verificar.)
2. **Quadro 10 `indisponivel` ou `nao_encontrado`** → `pendente`, motivo = motivo da própria dimensão (ex.: `'Permissão por zona pendente da base oficial (SEDUR)'` ou `'Combinação zona × grupo de uso sem regra no Quadro 10 vigente'`). **NUNCA permitido/nao_permitido sem a permissão real (anti-fachada central).**
3. **Quadro 10 identificado:**
   - `permissao === proibido` → `nao_permitido`, motivo `'Atividade proibida na zona pelo Quadro 10'` (RN-005). Condicionantes `[]` (curto-circuito).
   - há **condicionante incidente** (vagas não conforme, restrição ZEIS, condição pela via, ou zona `permitido_condicionado`) → `permitido_com_condicoes` (RN-006).
   - senão → `permitido`.

### Shape definitivo do `consolidado`

```
{
  resultado: <value de ResultadoViabilidade>,   // permitido | permitido_com_condicoes | nao_permitido | pendente
  fundamentacao: list<string>,                  // Lei 9.148/2016 + quadros aplicados + base_legal; ou motivo da pendência
  condicionantes: list<{tipo, ...}>,            // tipos: zona | via | vagas | restricao
  motivo: string
}
```

Tipos de condicionante:
- `{tipo: 'zona', condicionante_ref, motivo}` — Quadro 10 `permitido_condicionado` (incide).
- `{tipo: 'via', quadro: 'Quadro 11'|'Quadro 11A', condicoes, motivo}` — Quadros 11/11A identificados com condições (incide).
- `{tipo: 'vagas', conforme: bool|null, exigido, declarado, motivo}` — HU-042 (incide só quando `conforme === false`).
- `{tipo: 'restricao', nome, motivo}` — HU-043, item ZEIS do território (incide).

### Fundamentação legal (HU-045) — `buildFundamentacao`

Espelha `RiscoClassificationService::buildFundamentacao`. Constante `FUNDAMENTO_LOUOS = 'Lei nº 9.148/2016 (LOUOS)'`.
- Quadro 7 identificado → `'Lei nº 9.148/2016 (LOUOS) — Quadro 7'`.
- Quadro 10 identificado → `base_legal` da regra (quando presente) + `'… — Quadro 10'`.
- Quadro 11/11A identificado → `base_legal` + `'… — Quadro 11'` / `'… — Quadro 11A'`.
- **Pendente por degradação** → anexa o **motivo da pendência** e NÃO cita a regra que não foi aplicada (sem zona, o parecer cita apenas o Quadro 7 e o motivo, nunca o Quadro 10 — honesto). Deduplicada com `array_unique`.

### Contrato de vagas (HU-042) — `avaliarVagas`

- Exigência lida de `Settings::get('louos.vagas.exigencia_por_grupo', [])` (mapa `grupo_uso → {item → quantidade}`), **sem hardcode** (HU-014).
- Sem exigência para o grupo → condicionante INFORMATIVA `{conforme: null, exigido: null, declarado, motivo: 'Exigência de vagas não parametrizada para o grupo'}` (degradação honesta — alimenta a ficha, **não rebaixa**).
- Com exigência → `conforme = $input->vagasDeclaradas[item] >= exigido[item]` em cada item (item não declarado = 0). Não conforme vira condicionante incidente (`permitido_com_condicoes`), **insumo da análise (HU-135), nunca `nao_permitido`**.

### Contrato de restrições (HU-043) — `avaliarRestricoes`

- Lê `$input->territory?->restricoes` (shape `{status, itens[], motivo, versao_camada}` da Fase 4).
- Só itera `itens` quando `status === 'identificado'` (incidência real). Cada item → `{tipo: 'restricao', nome, motivo: 'Restrição territorial incidente (ex.: ZEIS) — observar condicionantes especiais'}`.
- Território nulo / restrições `indisponivel`/`nao_encontrado` → `[]` (restrição não bloqueia sozinha; o roteamento à análise é do motor de risco, Fase 6).

### Auditoria (RN-002)

`enquadrar()` agora inclui `fundamentacao` nas properties do log `louos`/`enquadramento` (junto de `resultado_consolidado` e `versoes`); `rules_version` segue a versão do Quadro 7.

## Como o parecer fica `pendente` sem zona (degradação propagada)

Com a zona urbanística **bloqueada pendente SEDUR** (Fase 4), o Quadro 10 degrada para `indisponivel` (05-04, sem consultar a tabela). A precedência 2 do `consolidar()` propaga isso até o parecer: `pendente`, motivo da zona, fundamentação cita apenas o Quadro 7 + o motivo da pendência. Quando a SEDUR entregar a base de zoneamento, a MESMA lógica passa a decidir — muda a carga, não o código.

## Evidência (verificação fresca)

- `php artisan test --compact --filter=LouosConsolidacaoTest` → **11 passed** (36 assertions).
- `php artisan test --compact --filter=Louos` → **66 passed** (390 assertions) — motor inteiro (Quadro 7 + Território + Consolidação).
- `php artisan test --compact --exclude-group postgis` → **585 passed** (2.973 assertions), zero falhas. Cadeia: 574 baseline (05-04) + 11 novos (05-05) = **585**.
- `vendor/bin/pint --dirty --format agent` → passed em cada task (Task 1 fixou formatação e removeu import `Settings` ainda não usado; re-adicionado na Task 2 ao usar `Settings::get`).
- Critérios de aceite (grep) confirmados: `ResultadoViabilidade::NaoPermitido`, `ResultadoViabilidade::Pendente`, `buildFundamentacao`, `Lei nº 9.148/2016`, `Settings::get('louos.vagas.exigencia_por_grupo'`, `->restricoes`, `avaliarVagas`, `avaliarRestricoes`.

## Anti-fachada (RED confirmado)

- **`test_sem_zona_consolida_pendente` (ÂNCORA):** com uma permissão `permitido` no Quadro 10 que CASARIA, a zona `indisponivel` → consolidado `pendente`, motivo `'Base de zoneamento pendente SEDUR'`, e a fundamentação **não cita o Quadro 10**. RED original falhou com o motivo provisório do 05-03 (`'Enquadramento por área realizado; …'`); prova que o motor não decide sem a zona.
- **`test_vagas_parametrizadas_sem_deploy` (HU-042/HU-014):** mesma entrada vira de `permitido` para `permitido_com_condicoes` só ao gravar `louos.vagas.exigencia_por_grupo` — parametrização real, efeito sem deploy (cache invalidado na gravação do Parameter).

## Testes (11, TDD)

**Task 1 — consolidação + fundamentação (`f6bc878`):**
1. `test_proibido_consolida_nao_permitido` — zona `proibido` → `nao_permitido` (RN-005).
2. `test_permitido_sem_condicoes_consolida_permitido` — zona `permitido`, sem via/restrições → `permitido`.
3. `test_permitido_condicionado_consolida_com_condicoes` — zona `permitido_condicionado` → `permitido_com_condicoes` (RN-006).
4. `test_sem_zona_consolida_pendente` — âncora anti-fachada.
5. `test_sem_enquadramento_consolida_pendente` — Quadro 7 `nao_encontrado` → `pendente`.
6. `test_fundamentacao_cita_louos_e_quadros_aplicados` — HU-045: cita Lei 9.148/2016 + Quadro 7 + Quadro 10, versões em `versoes()`.
7. `test_resultado_e_auditado_com_fundamentacao_e_versao` — CA-02/RN-002.

**Task 2 — vagas + restrições ZEIS (`073989d`):**
8. `test_vagas_nao_parametrizadas_geram_condicionante_informativa_sem_bloquear` — condicionante `vagas` `conforme: null`, resultado permanece `permitido`.
9. `test_vagas_nao_conformes_geram_permitido_com_condicoes` — exigência > declarado → `permitido_com_condicoes`, `conforme: false`.
10. `test_restricao_zeis_incidente_gera_permitido_com_condicoes` — item ZEIS → `permitido_com_condicoes`, condicionante `restricao`.
11. `test_vagas_parametrizadas_sem_deploy` — HU-042/HU-014.

## Deviations from Plan

### Decisões de precisão (não-deviation)
- **Precedência 1 com `!== STATUS_IDENTIFICADO`** em vez do literal `=== STATUS_NAO_ENCONTRADO` do plano: equivalente no domínio (o Quadro 7 só retorna identificado/nao_encontrado), porém estritamente anti-fachada (qualquer não-identificado → pendente). Mantido o motivo do plano.
- **Proibido com condicionantes `[]`:** o ramo `nao_permitido` curto-circuita antes de coletar vagas/restrições — condições só se aplicam a uso permitido. O plano pede "sempre incluir as condicionantes"; interpretei como "no parecer permitido/condicionado", já que listar exigência de vagas num uso proibido seria ruído. Sem impacto nos testes do plano.

**Total deviations:** nenhuma de código; plano executado como escrito (testes 1-11 conforme especificados).

## Issues Encountered

- **Pint removeu `use App\Support\Settings;` na Task 1** (import ainda não usado) — comportamento correto; re-adicionado na Task 2 ao introduzir `Settings::get`. Nenhuma execução paralela colidiu nesta sessão (commits `f6bc878`/`073989d` saíram limpos com 2 files cada, `git add` por caminho explícito).

## Next Plan Readiness

- **Motor LOUOS completo:** o `consolidar()` final é o veredito fundamentado consumido pelo EP07 (consulta prévia) e pela solicitação (EP08). O shape `{resultado, fundamentacao[], condicionantes[], motivo}` é estável.
- **05-08 (sandbox HU-143):** pode exercitar o motor com `versoesOverride` e `louos.vagas.exigencia_por_grupo` para simular vereditos sem afetar a vigente.
- **05-09 (golden cases):** casos `requer_zona` esperam `pendente` enquanto a zona é indisponível; casos com TerritoryResult sintético (zona identificada) cobrem proibido/condicionado/permitido e os rebaixamentos por vagas/ZEIS.
- **Pendência herdada (SEDUR):** zona urbanística e atributo viário LOUOS seguem bloqueados — o motor degrada honestamente; quando a base chegar, muda a carga, não a lógica.

---
*Phase: 05-motor-louos*
*Completed: 2026-06-14*
