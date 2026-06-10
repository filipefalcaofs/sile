---
phase: 02-administracao-base
verified: 2026-06-10T17:45:00Z
status: passed
score: 41/41 must-haves verified
human_verification_done:
  - test: "Smoke E2E navegável das 4 telas administrativas (roteiro de 7 passos)"
    approved: 2026-06-10
    evidence: "02-08-SUMMARY.md (checkpoint humano aprovado pelo usuário)"
---

# Fase 2: Administração Base — Relatório de Verificação

**Objetivo da fase:** Administradores mantêm os cadastros estruturantes (CNAEs, usuários, perfis) e os parâmetros de negócio do sistema sem depender de desenvolvedor.
**Verificado em:** 2026-06-10
**Status:** passed
**Reverificação:** Não — verificação inicial

## Evidência fresca (comandos executados nesta verificação)

| Comando | Resultado |
|---|---|
| `php artisan test --compact` | 197/197 testes, 915 asserções, verde (7,3 s) |
| `php artisan route:list --path=gestao` | 16 rotas (dashboard, acessos, cnaes×4, usuarios×3, perfis×4, parametros×3) |
| `npm run typecheck` | verde |
| `npm run build` | verde (550 ms) |
| `vendor/bin/pint --dirty --format agent` | passed |
| Contagem lógica do CSV (parser csv Python) | 1.331 registros, 1.331 códigos únicos, header oficial |
| `git log -- tests/Unit/Support/SettingsTest.php` | 1 único commit (290513b, Fase 1) — intocado |
| `git status` | nenhum código de aplicação não commitado (apenas docs/rules) |

## Goal Achievement

### Critérios de sucesso do ROADMAP

| # | Critério | Status | Evidência |
|---|----------|--------|-----------|
| 1 | CNAEs com estrutura oficial 2.3 (1.331 códigos) consultável e mantível | ✓ VERIFICADO | CSV com 1.331 registros lógicos únicos; `CnaeImportService` (upsert por code, idempotente, relatório); `DatabaseSeederTest` asserta `Cnae::count() === 1331`; CRUD completo em `CnaeController` + tela com busca debounced |
| 2 | Gestão de usuários (ativação, inativação, vínculo de perfil) pela interface | ✓ VERIFICADO | `UserManagementController` (index/updateRole/toggleActivation); teste `test_inativacao_pela_interface_bloqueia_o_login_de_verdade` prova o efeito real ponta a ponta |
| 3 | Perfis com permissões granulares por funcionalidade | ✓ VERIFICADO | `RoleController` + `Roles::STRUCTURAL` + `UpdateRoleRequest` (anti-lockout); 12 testes em `ManageRolesTest` |
| 4 | Parâmetros com tipo, validação, valor padrão, histórico auditado e efeito sem deploy | ✓ VERIFICADO | Catálogo com type/default_value/validation_rules; validação dinâmica no `UpdateParameterRequest`; histórico via `ParameterController::history`; `test_alteracao_de_parametro_tem_efeito_imediato_sem_deploy` muda o parâmetro pela UI e comprova efeito em call site real da Fase 1 |

### Verdades observáveis por plano (41/41)

**02-01 — Fundação (3/3)**

| Verdade | Status | Evidência |
|---|---|---|
| CSV oficial 1.331 subclasses com proveniência documentada | ✓ | 1.331 registros lógicos únicos (contagem fresca via parser CSV); `scripts/convert-cnae-xlsx.py` documenta fonte IBGE/CONCLA, vigência e a divergência 9900-8/00 |
| 8 permissões granulares atribuídas aos papéis corretos | ✓ | `RolesAndPermissionsSeeder`: admin com todas as manter-*; analista/gestor com consultar-cnaes; testes de atribuição passando |
| Seeder aditivo (re-seed não remove ajustes da interface) | ✓ | `givePermissionTo` (nunca sync) + `test_seeder_aditivo_preserva_ajustes_feitos_pela_interface` |

**02-02 — Registry de parâmetros (6/6)**

| Verdade | Status | Evidência |
|---|---|---|
| Resolução cache → banco → config → default sem tocar call sites | ✓ | `Settings::get` linhas 21-40; assinatura preservada; `SettingsTest.php` da Fase 1 com 1 único commit (intocado) |
| Fallback sem banco (QueryException → config) | ✓ | try/catch em `Settings.php:37`; suíte Unit roda sem banco |
| Alteração no banco tem efeito na leitura seguinte | ✓ | `Parameter::booted` com `Cache::forget` no saved/deleted + `test_alteracao_tem_efeito_imediato_na_leitura_seguinte` |
| Sensível criptografado no banco, claro no accessor | ✓ | `Attribute` com `Crypt::encryptString/decryptString` condicional + `test_valor_sensivel_e_criptografado_no_banco` |
| Re-seed preserva value administrado | ✓ | `updateOrCreate(['key'], $meta)` sem value no $meta + `test_seeder_preserva_valor_administrado` |
| Password::defaults reflete parâmetros do banco | ✓ | `AppServiceProvider:25-41` + `test_politica_de_senha_respeita_parametro_do_banco` |

**02-03 — Inativação no login (4/4)**

| Verdade | Status | Evidência |
|---|---|---|
| Conta inativada não autentica, mensagem pt-BR clara | ✓ | `Fortify::authenticateUsing` lança ValidationException com mensagem em pt-BR; teste dedicado |
| Tentativa em conta inativada registrada em access_logs | ✓ | `AccessLog::create` com `event => 'inativada'` (`FortifyServiceProvider:112-119`) |
| Anti-oráculo de enumeração (senha errada = mensagem padrão) | ✓ | `return null` antes do check de inatividade + `test_credencial_invalida_em_conta_inativada_nao_revela_o_estado` |
| Sessão aberta derrubada na request seguinte | ✓ | `EnsureUserIsActive` no append global do grupo web (`bootstrap/app.php:27`) com invalidate+regenerateToken |

**02-04 — HU-011 CNAEs (6/6)**

| Verdade | Status | Evidência |
|---|---|---|
| Import real, idempotente, 1.331 subclasses com relatório | ✓ | `CnaeImportService::import` (upsert por code em chunks, contadores) + `test_importa_as_1331_subclasses_oficiais` e `test_import_e_idempotente` |
| Divergência 9900-8/00 registrada na auditoria, sem inserção silenciosa | ✓ | `KNOWN_DIVERGENCE` no relatório, auditado pelo `CnaeSeeder` (event `importacao-oficial`, rules_version `cnae-subclasses-2.3`) |
| CRUD com código imutável na edição | ✓ | `UpdateCnaeRequest` valida apenas description/active + `test_edicao_atualiza_dados_mas_nunca_o_codigo` |
| Mudanças auditadas com attribute_changes | ✓ | `Cnae` usa `HasAuditoria` + `test_mudancas_sao_auditadas_com_attribute_changes` |
| Consulta separada da manutenção; cidadão 403 auditado | ✓ | Rotas com `permission:consultar-cnaes` (GET) vs `permission:manter-cnaes` (POST/PUT/DELETE); 403 auditado globalmente em `bootstrap/app.php` |
| Busca por código/denominação com paginação parametrizada | ✓ | `CnaeController::index` com branch de dígitos + `Settings::get('ui.cnaes.per_page')`; tela com debounce + preserveState |

**02-05 — HU-012 Usuários (6/6)**

| Verdade | Status | Evidência |
|---|---|---|
| Listagem/busca com paginação parametrizada | ✓ | `Settings::get('ui.users.per_page')`; busca nome/e-mail; CPF mascarado (LGPD) |
| Inativação/reativação com auditoria e efeito real no login | ✓ | `toggleActivation` com forceFill + auditoria explícita; `test_inativacao_pela_interface_bloqueia_o_login_de_verdade` |
| Vínculo de papel com auditoria anterior/novo | ✓ | `updateRole` com syncRoles + properties papel_anterior/papel_novo |
| Anti-lockout: admin não inativa a própria conta | ✓ | `ToggleUserActivationRequest::after` com mensagem pt-BR + teste |
| Sem manter-usuarios → 403 auditado | ✓ | Middleware na rota + `test_gestor_sem_permissao_nao_gerencia_usuarios` |
| Listagem linka histórico de acessos (concern da Fase 1 fechado) | ✓ | `usuarios/index.tsx:221` → `/gestao/acessos/${user.id}`; rota `gestao.acessos.show` ativa |

**02-06 — HU-013 Perfis (6/6)**

| Verdade | Status | Evidência |
|---|---|---|
| Criação de perfis com permissões granulares, efeito imediato | ✓ | `RoleController::store` + `test_permissoes_de_perfil_novo_tem_efeito_imediato` |
| Estruturais não excluíveis/renomeáveis; permissões ajustáveis | ✓ | `Roles::STRUCTURAL` validado em FormRequest e controller; `test_ajusta_permissoes_de_papel_estrutural` |
| acessar-gestao não removível do administrador | ✓ | `UpdateRoleRequest::after:62-64` + `test_nao_remove_acessar_gestao_do_administrador` |
| Exclusão bloqueada com usuários vinculados (pt-BR) | ✓ | `RoleController::destroy:103-107` + teste |
| Auditoria com permissões antes/depois | ✓ | properties permissoes_antes/permissoes_depois no log 'perfis' |
| Sem manter-perfis → 403 auditado | ✓ | Middleware na rota + `test_analista_nao_mantem_perfis` |
| UI comunica proteções (checkbox disabled, nome readOnly) | ✓ | `perfis/index.tsx`: locked/readOnly/lockedPermission para estruturais |

**02-07 — HU-014 Parâmetros (6/6)**

| Verdade | Status | Evidência |
|---|---|---|
| Alteração com validação do catálogo; inválido rejeitado | ✓ | `UpdateParameterRequest::after` com validator dinâmico das `validation_rules` do registro + `test_valor_invalido_e_rejeitado_pelas_regras_do_catalogo` |
| Efeito sem deploy comprovado em call site real da Fase 1 | ✓ | `test_alteracao_de_parametro_tem_efeito_imediato_sem_deploy`: PUT na UI muda `ui.access_history.per_page` e a tela do portal passa a paginar 5 |
| Toggle features.procuracoes com degradação comunicada (sem fachada) | ✓ | `ProcurationController::store` bloqueia com aviso pt-BR; index acessível com aviso âmbar na tela; destroy (revogação) NUNCA bloqueado; 4 testes (bloqueia/preserva/comunica/fluxo normal) |
| Histórico com anterior/novo/responsável/data; sensível mascarado | ✓ | `ParameterController::history` + gravação com `[criptografado]`; `test_historico_de_sensivel_mascara_valores` com `assertDontSee` do token real |
| Sensível nunca devolvido em claro pela UI | ✓ | `index` envia `value => null` quando sensitive; campo password na tela; `test_parametro_sensivel_nunca_e_devolvido_em_claro` |
| Sem manter-parametros → 403 auditado | ✓ | Middleware na rota + `test_gestor_nao_mantem_parametros` |

**02-08 — Fechamento (4/4)**

| Verdade | Status | Evidência |
|---|---|---|
| migrate:fresh --seed prepara ambiente completo | ✓ | `DatabaseSeeder` chama os 5 seeders na ordem correta; `test_seed_completo_prepara_ambiente_de_desenvolvimento` asserta 4 papéis, termo LGPD, 1.331 CNAEs, 10 parâmetros, admin dev verificado |
| Navegação liga dashboard e as 4 telas por permissão | ✓ | `gestao-layout.tsx`: navItems filtrados por `auth.permissions` (CNAEs/Usuários/Perfis/Parâmetros) |
| Suíte, pint, typecheck e build verdes | ✓ | Evidência fresca desta verificação: 197/197, pint passed, tsc verde, build verde |
| Smoke E2E aprovado por humano | ✓ | Aprovado pelo usuário em 2026-06-10 (02-08-SUMMARY, checkpoint humano) |

### Artefatos obrigatórios (nível 1-3: existe, substantivo, conectado)

| Artefato | Status | Detalhes |
|---|---|---|
| `database/data/cnaes-subclasses-2-3.csv` | ✓ VERIFICADO | 1.331 registros lógicos únicos, header oficial |
| `scripts/convert-cnae-xlsx.py` | ✓ VERIFICADO | 123 linhas, proveniência completa, validações de integridade |
| `database/seeders/RolesAndPermissionsSeeder.php` | ✓ VERIFICADO | 8 permissões, aditivo, chamado pelo DatabaseSeeder |
| `app/Models/Parameter.php` | ✓ VERIFICADO | typedValue por tipo, Crypt condicional, Cache::forget no booted |
| `app/Support/Settings.php` | ✓ VERIFICADO | backend banco+cache+fallback; 13 call sites reais em app/ |
| `database/seeders/ParameterSeeder.php` | ✓ VERIFICADO | 10 chaves (6 seguranca, 3 ui, 1 features), preserva value |
| migration `create_parameters_table` | ✓ VERIFICADO | existe como `2026_06_10_141916_*` (timestamp do artisan, todos os campos do plano) |
| migration `add_inactivated_at_to_users_table` | ✓ VERIFICADO | existe como `2026_06_10_141715_*` |
| `app/Http/Middleware/EnsureUserIsActive.php` | ✓ VERIFICADO | registrado no append global web |
| `app/Providers/FortifyServiceProvider.php` | ✓ VERIFICADO | authenticateUsing + LoginRateLimiter parametrizado |
| `app/Services/CnaeImportService.php` | ✓ VERIFICADO | 156 linhas, import real com relatório e divergência |
| `database/seeders/CnaeSeeder.php` | ✓ VERIFICADO | delega ao service + audita relatório |
| `app/Http/Controllers/Gestao/CnaeController.php` | ✓ VERIFICADO | index/store/update/destroy reais |
| `resources/js/pages/gestao/cnaes/index.tsx` | ✓ VERIFICADO | 468 linhas, busca debounced server-side, form, badge |
| `app/Http/Controllers/Gestao/UserManagementController.php` | ✓ VERIFICADO | index/updateRole/toggleActivation auditados |
| `resources/js/pages/gestao/usuarios/index.tsx` | ✓ VERIFICADO | 265 linhas, forms PUT papel/inativacao, link acessos |
| `app/Http/Requests/Gestao/ToggleUserActivationRequest.php` | ✓ VERIFICADO | anti-lockout no after() |
| `app/Support/Roles.php` | ✓ VERIFICADO | constante STRUCTURAL usada em controller e FormRequest |
| `app/Http/Controllers/Gestao/RoleController.php` | ✓ VERIFICADO | proteções estruturais + auditoria |
| `resources/js/pages/gestao/perfis/index.tsx` | ✓ VERIFICADO | 327 linhas, checkboxes com locks comunicados |
| `app/Http/Controllers/Gestao/ParameterController.php` | ✓ VERIFICADO | index agrupado, update validado, history paginado |
| `app/Http/Requests/Gestao/UpdateParameterRequest.php` | ✓ VERIFICADO | validação dinâmica via validation_rules |
| `resources/js/pages/gestao/parametros/index.tsx` + `historico.tsx` | ✓ VERIFICADO | campo por tipo, sensível como password, marcador [criptografado] |
| `app/Http/Controllers/Portal/ProcurationController.php` | ✓ VERIFICADO | toggle real guardando store, destroy livre |
| `database/seeders/DatabaseSeeder.php` | ✓ VERIFICADO | orquestra os 5 seeders |
| `resources/js/layouts/gestao-layout.tsx` | ✓ VERIFICADO | nav permissionada via auth.permissions |

### Key links (conexões críticas)

| De | Para | Via | Status |
|---|---|---|---|
| RolesAndPermissionsSeeder | papel administrador | givePermissionTo manter-* | ✓ WIRED |
| convert-cnae-xlsx.py | CSV versionado | conversão com carry-forward | ✓ WIRED |
| Settings.php | Parameter.php | Cache::remember + catch QueryException | ✓ WIRED |
| Parameter.php | cache sile.parameters.* | Cache::forget no saved/deleted | ✓ WIRED |
| AppServiceProvider | Settings | Password::defaults com security.password.* | ✓ WIRED |
| FortifyServiceProvider | AccessLog | event 'inativada' no login bloqueado | ✓ WIRED |
| bootstrap/app.php | EnsureUserIsActive | append global no grupo web | ✓ WIRED |
| CnaeSeeder | AuditService | log 'importacao-oficial' com relatório | ✓ WIRED |
| CnaeImportService | CSV oficial | database_path('data/cnaes-subclasses-2-3.csv') | ✓ WIRED |
| routes/gestao.php | permissões | middleware permission por verbo (4 grupos) | ✓ WIRED |
| cnaes/index.tsx | /gestao/cnaes | router.get com debounce + preserveState + replace | ✓ WIRED |
| usuarios/index.tsx | /gestao/acessos/{user} | Link por linha (fecha concern da Fase 1) | ✓ WIRED |
| UserManagementController | AuditService | usuario-inativado/reativado/papel-alterado | ✓ WIRED |
| UpdateRoleRequest | Roles::STRUCTURAL | validações anti-lockout no after() | ✓ WIRED |
| RoleController | AuditService | perfil-criado/atualizado/excluido | ✓ WIRED |
| UpdateParameterRequest | parameters.validation_rules | validator dinâmico do catálogo | ✓ WIRED |
| ParameterController | AuditService | parametro-alterado com [criptografado] | ✓ WIRED |
| ProcurationController | Settings | Settings::enabled('procuracoes') no store + prop na tela | ✓ WIRED |
| parametros/index.tsx | historico | link por parâmetro | ✓ WIRED |
| DatabaseSeeder | CnaeSeeder | call na ordem correta (após Roles/LegalTerm/Parameter) | ✓ WIRED |
| gestao-layout.tsx | auth.permissions | filtro de navItems por permissão | ✓ WIRED |

### Cobertura de requisitos

| Requisito | Status | Evidência |
|---|---|---|
| HU-011 (Manter CNAEs) | ✓ SATISFEITO | Import oficial + CRUD + auditoria + consulta granular; 16 testes (CnaeImportTest, CnaeCrudTest) |
| HU-012 (Manter usuários) | ✓ SATISFEITO | Inativação real no login + papel + anti-lockout; 16 testes (InactiveUserLoginTest, ManageUsersTest) |
| HU-013 (Manter perfis) | ✓ SATISFEITO | Permissões granulares + proteções estruturais; 12 testes (ManageRolesTest) |
| HU-014 (Manter parâmetros, com CA-05/06/07) | ✓ SATISFEITO | CA-05 efeito sem deploy, CA-06 toggle real com degradação comunicada, CA-07 histórico mascarado; 27 testes (ManageParameters, ParameterEffect, ParameterHistory, ParameterRegistry, SettingsBackend) |

### Verificações adicionais solicitadas pelo orquestrador

| Verificação | Status | Evidência |
|---|---|---|
| Diretriz refinada de parametrização | ✓ CONFORME | 10 parâmetros de negócio administráveis com call sites reais; constante técnica `sile.parameters.cache_ttl` apenas em config (fora do catálogo); paginação de consulta interna (histórico) não parametrizada — conforme a regra |
| Promessa da Fase 1 preservada | ✓ CONFORME | `tests/Unit/Support/SettingsTest.php` com 1 único commit (Fase 1); assinatura `Settings::get(key, default)` intacta; 13 call sites funcionando com backend banco+cache+fallback (SettingsBackendTest verde) |
| Toggle real features.procuracoes (CA-06) | ✓ CONFORME | Sem fachada: store bloqueado com aviso pt-BR, tela comunica com aviso âmbar, revogação preservada; 4 testes end-to-end |
| Histórico de sensível mascarado (RN-009) | ✓ CONFORME | Gravação já mascara (`[criptografado]`); `assertDontSee` do valor real no teste; UI nunca recebe sensível em claro |
| Concern da Fase 1 (gestao/acessos alcançável) | ✓ RESOLVIDO | Link em cada linha de `/gestao/usuarios` → `/gestao/acessos/{user}` |
| Smoke E2E (checkpoint humano) | ✓ APROVADO | Aprovado pelo usuário em 2026-06-10 (02-08-SUMMARY) |

### Anti-padrões encontrados

| Arquivo | Padrão | Severidade | Impacto |
|---|---|---|---|
| — | Nenhum TODO/FIXME/stub/retorno vazio nos arquivos da fase | — | Os únicos matches de "placeholder" são atributos HTML `placeholder` de inputs (legítimos) e um PHPDoc descrevendo o comportamento RN-009 |

### Observações (não bloqueantes)

1. As migrations existem com timestamps diferentes dos planejados (`2026_06_10_141916` vs `120000` do plano) — efeito de `php artisan make:migration`; conteúdo confere integralmente com o plano.
2. `DatabaseSeeder` usa `WithoutModelEvents`: durante o seed, o `Cache::forget` do Parameter não dispara. Sem impacto prático — o seeder preserva `value` e leituras novas expiram pelo TTL técnico (300 s), dentro do limite "efeito limitado apenas pelo tempo de cache" da diretriz.
3. `DevAdminSeeder` cria admin dev (admin@sile.dev/password) com aviso explícito de nunca usar em produção — aceitável como seed de desenvolvimento pela regra de entrega funcional.

### Resumo

Nenhuma lacuna encontrada. As 41 verdades dos 8 planos foram verificadas contra o código real com evidência fresca: suíte 197/197 verde, 16 rotas da gestão ativas, typecheck e build verdes, CSV oficial com 1.331 registros lógicos, toggle de procurações real com degradação comunicada, histórico de sensíveis mascarado, anti-lockouts em usuários e perfis, e o concern da Fase 1 (navegação para o histórico de acessos) resolvido. O checkpoint humano (smoke E2E de 7 passos) foi aprovado pelo usuário em 2026-06-10. A fase atingiu o objetivo: administradores mantêm CNAEs, usuários, perfis e parâmetros sem depender de desenvolvedor.

---

*Verificado: 2026-06-10T17:45:00Z*
*Verificador: Claude (gsd-verifier)*
