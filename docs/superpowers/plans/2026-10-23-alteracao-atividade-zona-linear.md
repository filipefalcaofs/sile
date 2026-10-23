# Alteração de atividade por comparação com a SEFAZ e zona pela centralidade linear: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o RF-02 (zona pela centralidade linear) e o RF-01 (comparação dos CNAEs com a viabilidade vinculada ao CNPJ na SEFAZ) do documento de homologação de 21/10/2026.

**Architecture:** O RF-02 estende o resolvedor de zona existente: a camada linear (ZCLMu/ZCLMe), hoje descartada, passa a ser testada por interseção com o polígono e vence a maior porção. O RF-01 entra no fluxo expresso como um serviço de comparação sem efeitos colaterais, alimentado por um gateway de consulta à SEFAZ com simulador, e o resultado filtra os CNAEs que o motor enquadra.

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12, PostGIS (grupo de testes `postgis`, porta 5444), Inertia React.

**Spec:** `docs/superpowers/specs/2026-10-23-alteracao-atividade-zona-linear-design.md`. Regra de zona: `docs/requisitos/hus/HU.182 - Definir a zona do imovel pela centralidade linear e pela maior porcao.md`.

## Global Constraints

- Código CNAE: o banco guarda dígitos (`0111301`); a exibição e a SEFAZ usam `0111-3/01`. A comparação normaliza para dígitos; a tela mostra `formatted_code`.
- A centralidade linear só vale se a linha passa por cima do polígono do lote, em qualquer parte; o percentual em outras zonas não conta (HU.182 RN-01, RN-02).
- Duas linhas (ZCLMu e ZCLMe) por cima do mesmo lote, ou empate de maior porção: o processo vai para análise técnica com os dois nomes no motivo (RN-06, RN-07).
- Sem polígono, só ponto: comportamento atual mantido.
- Taxa da alteração: a da atividade incluída mais cara; exclusão e atividade já existente não geram taxa.
- Viabilidade nova mantém o número da existente e inclui só as atividades solicitadas.
- SEFAZ indisponível: tenta a cada 30 minutos por 24 horas; esgotado o prazo, indefere por estabilidade sistêmica e orienta nova solicitação.
- Textos de motivo vão no catálogo `DecisionTextCatalog`, no mesmo lugar de `zona.motivo.empate_maior_porcao`, nunca literais no serviço.
- Sem comentários de autoria de ferramenta; comentário só onde a regra não é óbvia (convenção do projeto: PHPDoc curto em português).
- Testes PHPUnit (não Pest). Rodar `vendor/bin/pint --dirty --format agent` ao fim de cada tarefa que altera PHP.
- Não mudar dependências. Não commitar sem pedido do usuário; os passos de commit abaixo só valem quando o usuário autorizar.

## Review Focus

1. Lote cruzado por uma linha e 100% em uma única zona (Exemplo 2 da HU): zona é a linear, não a poligonal. Teste na Task 1.
2. Linha em frente ao lote sem tocá-lo (Exemplo 1): zona poligonal, como hoje. Teste na Task 1.
3. Polígono inválido ou camada linear sem resposta: resultado `indisponivel`, nunca ZCLMu por engano. Teste na Task 1.
4. CNAE repetido, com máscara diferente ou em minúsculas entre pedido e SEFAZ: comparados como iguais. Teste na Task 4.
5. Pedido sem CNAE incluído (só exclusões) com viabilidade existente: deferimento expresso sem taxa e sem consulta ao zoneamento. Teste na Task 8.

---

## Fase A: RF-02, zona pela centralidade linear

### Task 1: Resolvedor detecta linha por cima do lote

**Files:**
- Modify: `app/Services/Geo/ZonaMaiorPorcao.php`
- Modify: `app/Services/Geo/GeoServerZonaMaiorPorcaoResolver.php`
- Modify: `tests/Support/Geo/FakeZonaMaiorPorcaoResolver.php` (só se não aceitar um `ZonaMaiorPorcao` arbitrário)
- Test: `tests/Feature/Geo/ZonaMaiorPorcaoResolverPostgisTest.php`

**Interfaces:**
- Produces: `ZonaMaiorPorcao::CENTRALIDADE_LINEAR = 'centralidade_linear'` e `ZonaMaiorPorcao::CONFLITO_LINEAR = 'conflito_linear'` (constantes de status). Para `CENTRALIDADE_LINEAR`, `codigo` é `ZCLMu` ou `ZCLMe`, e `porcoes` continua trazendo a maior porção para o mapa. Para `CONFLITO_LINEAR`, `empate` é `['ZCLMu','ZCLMe']`. `identificado()` passa a valer também para `CENTRALIDADE_LINEAR`.

- [ ] **Step 1: Escrever os testes que falham** (grupo `postgis`, camadas servidas por `Http::fake` como nos testes vizinhos)
  - `test_linha_por_cima_do_lote_vence_a_zona_poligonal`: lote 100% em ZPR 3 e linha ZCLMu cruzando; esperado `status === CENTRALIDADE_LINEAR`, `codigo === 'ZCLMu'`.
  - `test_linha_em_frente_ao_lote_nao_conta`: linha a 38 m, sem tocar; esperado `IDENTIFICADO` com `codigo === 'ZPR 3'`.
  - `test_linhas_zclmu_e_zclme_por_cima_dao_conflito`: esperado `CONFLITO_LINEAR` e `empate === ['ZCLMu','ZCLMe']`.
  - `test_linha_zclme_por_cima_com_lote_em_duas_zonas`: esperado `codigo === 'ZCLMe'` mesmo com 90% em outra zona.
  - `test_camada_linear_sem_resposta_e_indisponivel`: esperado `INDISPONIVEL` (comportamento atual de camada com falha; o teste trava que a linha não vira zona).
- [ ] **Step 2: Rodar e ver falhar:** `DB_TEST_PORT=5444 php artisan test --compact tests/Feature/Geo/ZonaMaiorPorcaoResolverPostgisTest.php`. Esperado: FAIL nos quatro primeiros.
- [ ] **Step 3: Implementar.** Em `calcular`, onde hoje `$linear = true` descarta a feição, guardar `{zona: codigoZona(properties, typeName), geometria}` em `$lineares`. Em `porcoes` (renomear internamente para manter o cálculo atual), depois de montar o resultado da maior porção, testar cada linha com `ST_Intersects(ST_MakeValid(polígono), ST_SetSRID(ST_GeomFromGeoJSON(linha), 4326))`. Zonas distintas com interseção: uma vira `CENTRALIDADE_LINEAR` carregando as `porcoes` do resultado original; duas viram `CONFLITO_LINEAR`. Adicionar o status ao `camadas` já existente (`geometria: 'linear'`).
- [ ] **Step 4: Rodar e ver passar** o mesmo comando. Esperado: PASS.
- [ ] **Step 5: Commit** (só com autorização): `git add app/Services/Geo tests && git commit -m "feat: zona pela centralidade linear no resolvedor de maior porção"`

### Task 2: `ZonaProcessoService` grava a fonte e o conflito

**Files:**
- Modify: `app/Enums/ZonaFonte.php`
- Modify: `app/Services/Geo/ZonaProcessoService.php`
- Modify: catálogo de textos de `DecisionTextCatalog` (mesmo arquivo/seeder da chave `zona.motivo.empate_maior_porcao`)
- Test: `tests/Feature/Geo/ZonaProcessoServiceTest.php`

**Interfaces:**
- Consumes: Task 1 (`ZonaMaiorPorcao::CENTRALIDADE_LINEAR`, `CONFLITO_LINEAR`).
- Produces: `ZonaFonte::CentralidadeLinear = 'centralidade_linear'` com label `Centralidade linear (linha sobre o lote)`; `ZonaProcessoService::motivoConflito(ZonaMaiorPorcao): string` usando a chave `zona.motivo.conflito_centralidade_linear` (":zona_a", ":zona_b").

- [ ] **Step 1: Testes que falham:** `test_materializa_centralidade_linear` (grava `zona_codigo = 'ZCLMu'` e `zona_fonte = CentralidadeLinear`); `test_conflito_linear_nao_grava_zona_vencedora` (mantém a zona do ponto, `zona_fonte = Ponto`); `test_motivo_conflito_cita_as_duas_zonas`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Geo/ZonaProcessoServiceTest.php`.
- [ ] **Step 3: Implementar** o caso de enum, o ramo em `materializar` (identificado com status linear grava `CentralidadeLinear`; conflito é "não identificado" como o empate) e `motivoConflito`. A auditoria usa `rulesVersion: 'hu-182-centralidade-linear'` quando o status é linear.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** (só com autorização).

### Task 3: Fluxo expresso encaminha o conflito e mostra o motivo

**Files:**
- Modify: `app/Services/Expresso/FluxoExpressoService.php:324-333`
- Modify: `app/Services/Geo/MapaDecisaoService.php` e `app/Http/Controllers/Gestao/AnalysisRecordController.php` (onde `zona_fonte` vira texto)
- Test: `tests/Feature/Expresso/FluxoExpressoZonaMaiorPorcaoTest.php`

**Interfaces:**
- Consumes: `ZonaProcessoService::motivoConflito` (Task 2).

- [ ] **Step 1: Testes que falham:** `test_conflito_zclmu_zclme_vai_para_analise_com_os_dois_nomes` (gatilho `ZonaEmpate`, motivo com `ZCLMu` e `ZCLMe`); `test_centralidade_linear_enquadra_com_a_zona_linear` (o motor recebe `zona_codigo = 'ZCLMu'`); `test_ficha_mostra_a_fonte_centralidade_linear` (texto do label na resposta do mapa/ficha).
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Expresso/FluxoExpressoZonaMaiorPorcaoTest.php`.
- [ ] **Step 3: Implementar.** No bloco "Empate de porções", incluir `ZonaMaiorPorcao::CONFLITO_LINEAR` e escolher `motivoConflito` ou `motivoEmpate` conforme o status; reusa `TipoGatilho::ZonaEmpate` (minha escolha: evita nova coluna/enum; o rótulo do gatilho passa a "Zona com porções iguais ou em conflito"). Mapa e ficha usam `ZonaFonte::label()`.
- [ ] **Step 4: Rodar e ver passar**, depois `php artisan test --compact tests/Feature/Geo tests/Feature/Expresso/FluxoExpressoZonaMaiorPorcaoTest.php tests/Feature/Expresso/FluxoExpressoLocalizacaoDivergenteTest.php`. Esperado: PASS sem regressão.
- [ ] **Step 5: Commit** (só com autorização).

---

## Fase B: RF-01, comparação dos CNAEs com a SEFAZ

### Task 4: Comparação de CNAEs (serviço puro)

**Files:**
- Create: `app/Services/Sefaz/ComparacaoCnaesViabilidade.php` (DTO)
- Create: `app/Services/Sefaz/ComparacaoCnaesViabilidadeService.php`
- Test: `tests/Unit/Sefaz/ComparacaoCnaesViabilidadeServiceTest.php` (usa banco, então fica em `tests/Feature/Sefaz/` se o Unit não tiver `RefreshDatabase`)

**Interfaces:**
- Produces: `ComparacaoCnaesViabilidade` (readonly) com `list<string> $incluidas`, `list<string> $excluidas`, `list<string> $mantidas`, `list<string> $invalidas` (todos em dígitos), `?string $numeroViabilidade`, `bool $semViabilidade` e `toArray(): array`/`fromArray(array)`.
- Produces: `ComparacaoCnaesViabilidadeService::comparar(list<string> $codigosDoPedido, ?list<string> $codigosDaViabilidade, ?string $numeroViabilidade): ComparacaoCnaesViabilidade`. Normaliza máscara, caixa e repetidos; `invalidas` são os do pedido ausentes da tabela `cnaes`; com `$codigosDaViabilidade === null`, `semViabilidade = true` e todos os válidos do pedido são `incluidas`.

- [ ] **Step 1: Testes que falham, um por cenário T1 a T8 do documento:** T1 idêntica (tudo em `mantidas`); T2 um novo; T3 dois novos; T4 um a menos (`excluidas`); T5 código inexistente (`invalidas`); T6 `0111-3/01` e `0111301` iguais; T7 combinação das três; T8 sem viabilidade; mais `test_mascara_e_caixa_nao_geram_falsa_inclusao`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact --filter=ComparacaoCnaesViabilidadeServiceTest`.
- [ ] **Step 3: Implementar** o DTO e o serviço (normalização por `preg_replace('/\D/', '', $codigo)`; validade por `Cnae::query()->whereIn('code', ...)`).
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** (só com autorização).

### Task 5: Gateway de consulta à SEFAZ e simulador

**Files:**
- Create: `app/Services/Sefaz/ViabilidadeVinculada.php` (DTO)
- Create: `app/Services/Sefaz/SefazViabilidadeConsultaGateway.php` (interface)
- Create: `app/Services/Sefaz/SimuladoSefazViabilidadeConsultaGateway.php`
- Create: `app/Services/Sefaz/UnavailableSefazViabilidadeConsultaGateway.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding, junto ao do `SefazViabilidadeGateway`)
- Modify: `config/viabiliza.php` (bloco `sefaz.consulta`)
- Test: `tests/Feature/Sefaz/SefazViabilidadeConsultaGatewayTest.php`

**Interfaces:**
- Produces: `ViabilidadeVinculada(string $numero, list<string> $cnaes)`; `SefazViabilidadeConsultaGateway::consultarPorCnpj(string $cnpj): ?ViabilidadeVinculada` (null = sem viabilidade vinculada; lança `SefazUnavailableException` se indisponível).
- Config: `viabiliza.sefaz.consulta.driver` (`simulado` | `indisponivel`, padrão `indisponivel` fora de homologação) e `viabiliza.sefaz.consulta.simulador.viabilidades` (mapa CNPJ em dígitos para `{numero, cnaes}`) e `...simulador.indisponiveis` (lista de CNPJs que simulam queda).

- [ ] **Step 1: Testes que falham:** `test_simulador_devolve_a_viabilidade_configurada`; `test_simulador_devolve_null_para_cnpj_sem_viabilidade`; `test_simulador_lanca_indisponivel_para_cnpj_marcado`; `test_driver_indisponivel_lanca_sefaz_unavailable`; `test_cnpj_com_mascara_e_aceito`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Sefaz/SefazViabilidadeConsultaGatewayTest.php`.
- [ ] **Step 3: Implementar** as classes e o binding por `config('viabiliza.sefaz.consulta.driver')`. O driver real da SEFAZ entra aqui quando o endpoint existir, trocando só o binding.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** (só com autorização).

### Task 6: Persistir a comparação no processo

**Files:**
- Create: migration `add_comparacao_cnaes_to_viability_requests_table` (via `php artisan make:migration`)
- Modify: `app/Models/ViabilityRequest.php` (cast `comparacao_cnaes` => `array`, `#[Fillable]` se aplicável)
- Create: `app/Services/Sefaz/ConsultarComparacaoCnaesService.php`
- Test: `tests/Feature/Sefaz/ConsultarComparacaoCnaesServiceTest.php`

**Interfaces:**
- Consumes: Task 4 (`comparar`) e Task 5 (`consultarPorCnpj`).
- Produces: coluna `viability_requests.comparacao_cnaes` (json, nulo). `ConsultarComparacaoCnaesService::executar(ViabilityRequest $request): ?ComparacaoCnaesViabilidade`: devolve null quando o serviço do pedido não é `alteracao-atividade` ou quando o pedido traz intenção por CNAE (fluxo do portal); caso contrário consulta, compara, grava em `comparacao_cnaes` e registra em `AuditService` (log `integracoes`, evento `sefaz-consulta-viabilidade`) o resultado da consulta e da comparação. Propaga `SefazUnavailableException`.

- [ ] **Step 1: Testes que falham:** `test_pedido_juceb_de_alteracao_grava_a_comparacao`; `test_pedido_do_portal_com_intencao_nao_consulta`; `test_outro_servico_nao_consulta`; `test_registra_auditoria_da_consulta_e_da_comparacao`; `test_idempotente_nao_consulta_de_novo_se_ja_gravada`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Sefaz/ConsultarComparacaoCnaesServiceTest.php`.
- [ ] **Step 3: Implementar** migration, cast e serviço.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** (só com autorização).

### Task 7: Motor enquadra só as atividades incluídas

**Files:**
- Modify: `app/Services/Solicitacao/SolicitacaoViabilityResolver.php` (`orderedCnaes`, linha ~195)
- Modify: `app/Services/Territorial/TerritorialRuleService.php:298` (mesmo filtro de CNAEs avaliados)
- Test: `tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php`

**Interfaces:**
- Consumes: `viability_requests.comparacao_cnaes` (Task 6).
- Produces: com `comparacao_cnaes` preenchida, `orderedCnaes` devolve só os CNAEs em `incluidas`; sem ela, o comportamento atual.

- [ ] **Step 1: Testes que falham:** `test_resolver_so_enquadra_as_incluidas_quando_ha_comparacao`; `test_resolver_sem_comparacao_enquadra_todos`; `test_regra_territorial_so_avalia_as_incluidas`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Expresso/SolicitacaoViabilityResolverTest.php`.
- [ ] **Step 3: Implementar** o filtro por `Cnae::code` dentro de `incluidas`.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** (só com autorização).

### Task 8: Fluxo expresso aplica R1, R2 e R3

**Files:**
- Modify: `app/Services/Expresso/FluxoExpressoService.php` (`decidirSobLock`, antes da resolução do enquadramento)
- Modify: catálogo de textos (`expresso.motivo.cnae_invalido`, `expresso.motivo.cnae_incluido_excluido`)
- Test: `tests/Feature/Expresso/FluxoExpressoAlteracaoComparacaoTest.php`

**Interfaces:**
- Consumes: `ConsultarComparacaoCnaesService::executar` (Task 6) e o filtro da Task 7.

- [ ] **Step 1: Testes que falham, mapeando o documento:**
  - `test_so_exclusao_defere_expresso_sem_taxa_e_sem_zoneamento` (R2 pura; Review Focus 5).
  - `test_inclusao_defere_expresso_com_taxa_da_atividade_mais_cara` (R1, T3).
  - `test_inclusao_e_exclusao_prevalece_o_fluxo_da_inclusao_e_a_exclusao_nao_cobra`.
  - `test_codigo_invalido_vai_para_analise_com_o_codigo_no_motivo` (R3, T5 e T6).
  - `test_sem_viabilidade_trata_tudo_como_incluido` (T8).
  - `test_pedido_identico_a_viabilidade_nao_tem_inclusao_exclusao_nem_inconsistencia` (T1).
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Expresso/FluxoExpressoAlteracaoComparacaoTest.php`.
- [ ] **Step 3: Implementar.** Chamar o serviço antes do ramo "exclusivamente exclusão" existente. Com `invalidas` não vazio, `encaminharAnalise` com o motivo listando código e descrição (quando houver). Com só `excluidas` e nenhuma `incluida`, `deferirExclusao`. Caso contrário segue o caminho atual, que enquadra apenas as incluídas (Task 7).
- [ ] **Step 4: Rodar e ver passar**, depois `php artisan test --compact tests/Feature/Expresso`. Esperado: PASS sem regressão, em especial `AlteracaoAtividadeJucebSedeTest`.
- [ ] **Step 5: Commit** (só com autorização).

### Task 9: Analista corrige o código na ficha e o produto sai conforme a ficha

**Files:**
- Modify: controller/serviço da ficha de análise que edita CNAEs do pedido (`app/Http/Controllers/Gestao/ProcessoController.php` e o request correspondente)
- Modify: `resources/js/pages/gestao/` da ficha (campo de edição do código inconsistente)
- Modify: `app/Services/Analise/TvlPdfService.php` se o produto ler o CNAE original
- Test: `tests/Feature/Gestao/FichaCorrigirCnaeInvalidoTest.php`

**Interfaces:**
- Consumes: `comparacao_cnaes` (Task 6) e `ConsultarComparacaoCnaesService`.
- Produces: rota `PATCH` nomeada da ficha que troca um CNAE inválido por um código válido, refaz a comparação e a taxa e grava auditoria.

- [ ] **Step 1: Testes que falham:** `test_ficha_lista_incluidas_excluidas_e_mantidas_com_codigo_e_descricao` (o documento exige informar, em todos os casos, o que entra, o que sai e o que já existia); `test_analista_corrige_o_codigo_invalido_e_a_comparacao_e_refeita`; `test_codigo_corrigido_ainda_invalido_e_recusado`; `test_produto_de_viabilidade_sai_com_o_codigo_corrigido`; `test_so_analista_do_processo_corrige`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Gestao/FichaCorrigirCnaeInvalidoTest.php`.
- [ ] **Step 3: Implementar** a rota, a validação (código existente em `cnaes`), a troca no pivot `viability_request_cnaes`, a reexecução da comparação e o campo editável na ficha, seguindo o padrão das telas vizinhas da ficha.
- [ ] **Step 4: Rodar e ver passar**, depois `npm run build` e conferir a ficha no navegador com um pedido de teste.
- [ ] **Step 5: Commit** (só com autorização).

### Task 10: Viabilidade nova com o mesmo número

**Files:**
- Modify: `app/Services/Expresso/TvlNumberGenerator.php` (ou o ponto que gera o número da TVL em `emitir`)
- Modify: `app/Services/Expresso/FluxoExpressoService.php` (`emitir`)
- Test: `tests/Feature/Expresso/FluxoExpressoAlteracaoNumeroViabilidadeTest.php`

**Interfaces:**
- Consumes: `comparacao_cnaes.numero_viabilidade` (Task 4 e 6).
- Produces: no deferimento de alteração com viabilidade vinculada, a decisão usa o número da viabilidade existente em vez de gerar um novo; só as atividades incluídas entram, e as existentes ficam no histórico da viabilidade anterior.

- [ ] **Step 1: Testes que falham:** `test_deferimento_de_alteracao_mantem_o_numero_da_viabilidade_existente`; `test_viabilidade_nova_inclui_apenas_as_atividades_solicitadas`; `test_sem_viabilidade_vinculada_gera_numero_novo`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Expresso/FluxoExpressoAlteracaoNumeroViabilidadeTest.php`.
- [ ] **Step 3: Implementar** a reutilização do número. Antes de escrever, ler `TvlNumberGenerator` e a restrição de unicidade do número da TVL: se houver índice único, a anterior passa a `revisao` com a decisão antiga inativada, e essa escolha vai no resumo ao usuário.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** (só com autorização).

### Task 11: SEFAZ indisponível por 24 horas

**Files:**
- Create: `app/Jobs/ConsultarComparacaoCnaesJob.php`
- Modify: `app/Services/Expresso/FluxoExpressoService.php` (captura `SefazUnavailableException`, devolve `DecisionResult::aguardando`)
- Modify: catálogo de textos (`expresso.motivo.sefaz_indisponivel_indeferimento`)
- Test: `tests/Feature/Expresso/FluxoExpressoSefazIndisponivelTest.php`

**Interfaces:**
- Consumes: `SefazUnavailableException` (Task 5) e o mecanismo de espera já usado por `etapaTerritorialAguarda`.
- Produces: com a SEFAZ fora do ar e a janela aberta, `decide` devolve `aguardando` e o processo é reavaliado a cada 30 minutos pelo reprocessamento existente (`ExpressoReavaliarCommand`); passadas 24 horas do protocolo, indefere pelo caminho de indeferimento automático existente com o motivo `expresso.motivo.sefaz_indisponivel_indeferimento`, que orienta nova solicitação.

- [ ] **Step 1: Testes que falham:** `test_sefaz_fora_do_ar_dentro_da_janela_fica_aguardando`; `test_sefaz_volta_e_o_processo_segue_normalmente`; `test_apos_24_horas_indefere_por_estabilidade_sistemica_e_orienta_nova_solicitacao`; `test_nao_indefere_antes_das_24_horas`.
- [ ] **Step 2: Rodar e ver falhar:** `php artisan test --compact tests/Feature/Expresso/FluxoExpressoSefazIndisponivelTest.php`.
- [ ] **Step 3: Implementar.** Reaproveitar o prazo configurável em `config('viabiliza.expresso')` (nova chave `sefaz_consulta_janela_horas`, padrão 24) e o agendamento de reavaliação que já existe; não criar scheduler novo. O job só é necessário se o reprocessamento atual não cobrir o intervalo de 30 minutos, e nesse caso a tarefa registra a escolha.
- [ ] **Step 4: Rodar e ver passar**, depois `php artisan test --compact tests/Feature/Expresso`.
- [ ] **Step 5: Commit** (só com autorização).

### Task 12: Verificação final e roteiro de homologação

**Files:**
- Modify: `docs/requisitos/hus/` só se algum comportamento mudar a HU (a HU.182 já está atualizada)
- Test: suíte afetada

- [ ] **Step 1:** `DB_TEST_PORT=5444 php artisan test --compact tests/Feature/Geo tests/Feature/Expresso tests/Feature/Sefaz tests/Feature/Gestao/FichaCorrigirCnaeInvalidoTest.php`. Esperado: tudo verde; qualquer falha já existente em outro arquivo é registrada como anterior, não corrigida aqui.
- [ ] **Step 2:** `vendor/bin/pint --dirty --format agent`; `npm run build`.
- [ ] **Step 3:** Preencher a massa de homologação: CNPJs de teste no simulador (com viabilidade, sem viabilidade, com queda) em `config/viabiliza.php` por variável de ambiente, e polígonos de teste ZCLMu, ZCLMe e conflito, documentados no resumo para o usuário (sem arquivo novo de documentação, conforme a regra do projeto).
- [ ] **Step 4:** Resumo ao usuário com as escolhas dos passos 3 das Tasks 3, 10 e 11 e o que ficou em aberto (endpoint real da SEFAZ; imóvel só com ponto).
