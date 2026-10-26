# Sócio do REGIN vê o processo e consulta por CPF — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Quem tem o CPF vinculado a um processo (sócio do REGIN ou solicitante declarado) vê o processo no portal e pelo Detalhar, e o Portal de Serviços consulta os processos de viabilidade de um CPF pelo token da sessão.

**Architecture:** Tabela de vínculos CPF↔processo, sincronizada pelo protocolador do REGIN e por um comando de recuperação; a política `view` e a lista Minhas solicitações passam a considerar o vínculo; uma rota em `/api_integracao` confere o token no BFF e devolve os processos do CPF da sessão.

**Tech Stack:** Laravel 13, PHPUnit 12, Eloquent API Resource.

**Spec:** `docs/superpowers/specs/2026-10-23-socio-regin-e-consulta-por-cpf-design.md`

## Global Constraints

- Vínculo nunca vira `requester_user_id`; `update`, `protocol` e `cancel` continuam só do solicitante.
- Papéis: `socio` e `solicitante`. Item de `RUC_PROF` com chave contendo `CRC` preenchida é ignorado.
- CPF da consulta vem só da sessão do Portal (`PortalServicosSessaoClient::buscarSessao`); parâmetro `cpf` na query é ignorado.
- Consulta: 401 `{"message": "Sessão do Portal de Serviços inválida."}`; 503 `{"message": "Consulta indisponível."}` (chave desligada); 503 `{"message": "Não foi possível confirmar a sessão agora."}` (BFF fora); limite 200; sem rascunho; mais recente primeiro.
- Pint antes de cada commit; commits sem coautoria de IA.

## Review Focus

1. Sócio que também é o solicitante (conta e vínculo no mesmo processo) → o processo aparece uma vez só, na lista e na consulta. (Task 3 e Task 4.)
2. Processo do REGIN reenviado sem um sócio → o ex-sócio deixa de ver. (Task 1.)
3. Cidadão representando outra pessoa (procuração) → a lista e a política usam o CPF do representado, não o do procurador. (Task 3.)
4. Conta do Viabiliza sem CPF (contas antigas da gestão) → a política não casa vínculo com CPF vazio. (Task 3.)
5. Consulta com `?cpf=<outro>` na URL → ignorado, vale o CPF da sessão. (Task 4.)

---

### Task 1: Vínculos de CPF por processo e extrator de sócios

**Files:**
- Create: migration `create_viability_request_cpfs_table`, `app/Models/ViabilityRequestCpf.php`, `app/Services/Regin/ReginSociosExtrator.php`, `app/Services/Solicitacao/VinculosCpfProcesso.php`
- Modify: `app/Models/ViabilityRequest.php` (relação `cpfsVinculados(): HasMany`)
- Test: `tests/Feature/Regin/VinculosCpfProcessoTest.php`

**Interfaces:**
- Produces: `ReginSociosExtrator::cpfs(array $rowset): list<string>`; `VinculosCpfProcesso::sincronizar(ViabilityRequest $processo, array $socios): void`; constantes `ViabilityRequestCpf::PAPEL_SOCIO = 'socio'`, `PAPEL_SOLICITANTE = 'solicitante'`.

- [ ] Testes: `test_extrai_cpfs_de_lista_e_de_objeto_unico`, `test_ignora_cpf_invalido_cnpj_e_item_com_crc`, `test_sem_ruc_prof_devolve_lista_vazia`, `test_sincronizar_grava_socios_e_solicitante`, `test_sincronizar_remove_ex_socio_e_solicitante_apagado` (Review Focus 2), `test_sincronizar_e_idempotente`.
- [ ] FAIL → implementar → PASS → commit `feat: vínculos de CPF por processo e sócios do REGIN`.

### Task 2: Protocolador e comando de recuperação

**Files:**
- Modify: `app/Services/Regin/ReginProcessoProtocolador.php` (depois do `create` em `protocolar`, `VinculosCpfProcesso::sincronizar($solicitacao, ReginSociosExtrator::cpfs($rowset))`; método público `sociosDoRecebimento(ReginRecebimento $recebimento): list<string>` que monta o rowset como `protocolar` faz)
- Create: `app/Console/Commands/ReginVincularCpfsCommand.php` (`regin:vincular-cpfs {--dry-run}`; processos `origin = Regin`; recebimento mais recente com `protocolo = external_reference`; sem recebimento sincroniza só o solicitante; imprime contagens)
- Test: `tests/Feature/Regin/ReginVincularCpfsTest.php`

- [ ] Testes: `test_protocolar_regin_grava_os_socios`, `test_comando_vincula_processos_antigos`, `test_comando_dry_run_nao_grava`, `test_comando_sem_recebimento_vincula_so_o_solicitante`.
- [ ] FAIL → implementar → PASS (+ `--filter=Regin` sem regressão) → commit `feat: protocolador e comando vinculam os CPFs dos processos do REGIN`.

### Task 3: Política e Minhas solicitações

**Files:**
- Modify: `app/Policies/ViabilityRequestPolicy.php` (`view` = `owns` ou vínculo com `effectiveUser($user)->cpf` não vazio)
- Modify: `app/Http/Controllers/Portal/SolicitacaoController.php` (`index`: `requester_user_id` = efetivo **ou** `whereHas('cpfsVinculados', cpf = efetivo->cpf)`; `editable`/`cancelable` também exigem `Gate::allows('update'|'cancel')`)
- Test: `tests/Feature/Solicitacao/VinculoCpfAcessoTest.php`

- [ ] Testes: `test_socio_ve_o_processo_e_responde_convite`, `test_socio_nao_edita_nem_cancela`, `test_cpf_sem_vinculo_nao_ve`, `test_conta_sem_cpf_nao_casa_vinculo` (RF 4), `test_lista_inclui_processo_do_socio_sem_acoes` , `test_processo_do_socio_que_tambem_e_solicitante_aparece_uma_vez` (RF 1), `test_representado_usa_o_cpf_do_outorgante` (RF 3), `test_detalhar_abre_processo_regin_para_o_socio`.
- [ ] FAIL → implementar → PASS (+ `tests/Feature/Solicitacao tests/Feature/Portal tests/Feature/PortalServicos`) → commit `feat: sócio e solicitante declarado veem o processo do REGIN`.

### Task 4: Consulta de processos por CPF

**Files:**
- Create: `app/Http/Controllers/Integracao/ProcessosPortalServicosController.php`, `app/Http/Resources/ProcessoPortalServicosResource.php`
- Modify: `routes/web.php` (dentro do grupo `LogInboundIntegration`: `GET /api_integracao/portal-servicos/processos`, nome `portal-servicos.processos`, `throttle:portal-servicos-processos`), `app/Providers/AppServiceProvider.php` (rate limiter 60/min por IP), `app/Services/Integracao/IntegrationCallRecorder.php` (rota de entrada na regra `portal_servicos`)
- Test: `tests/Feature/PortalServicos/ConsultaProcessosPortalServicosTest.php`

Campos do item (spec): `numero_processo`, `numero_viabilidade`, `bap`, `servico`, `empresa`, `cnpj`, `situacao`, `situacao_descricao`, `convite_pendente`, `protocolado_em`, `atualizado_em`, `vinculo`, `detalhar_url`.

- [ ] Testes: `test_devolve_os_processos_do_cpf_da_sessao`, `test_formato_do_item`, `test_sem_rascunho_e_sem_processo_de_outro_cpf`, `test_parametro_cpf_e_ignorado` (RF 5), `test_processo_em_dois_vinculos_aparece_uma_vez` (RF 1), `test_sem_token_e_token_recusado_dao_401`, `test_chave_desligada_e_bff_fora_dao_503`, `test_token_nao_aparece_no_log_de_integracoes`.
- [ ] FAIL → implementar → PASS → commit `feat: consulta de processos por CPF para o Portal de Serviços`.

### Task 5: Verificação e revisão

- [ ] `php artisan test --compact tests/Feature/PortalServicos tests/Feature/Solicitacao tests/Feature/Regin tests/Feature/Portal tests/Feature/Auth` (falhas só as já existentes na base).
- [ ] Revisão da branch por revisor independente; corrigir críticos e importantes com teste.
- [ ] Atualizar a mensagem ao Ricardo Luz (consulta por CPF) e a memória.
