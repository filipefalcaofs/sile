# Endereço do imóvel na renovação de TVL legada — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O endereço da TVL legada passa a vir do cadastro imobiliário, buscado pela inscrição imobiliária da TVL, na conferência de titularidade, no processo novo e no assistente.

**Architecture:** Um resolvedor (`ResolvedorEnderecoImovel`) transforma a inscrição em `EnderecoDoImovel` usando o `PropertyRegistryLookup` existente, com cache por requisição. A conferência de titularidade recebe o endereço do imóvel por uma função, chamada só depois que a razão social confere. O protocolo e o assistente usam o mesmo resolvedor.

**Tech Stack:** Laravel 13, PHP 8.4+, PHPUnit. Sem dependência nova.

**Spec:** `docs/superpowers/specs/2026-10-19-api-legado-viabilidade-design.md` (seção "Pontos em aberto", item "Endereço deslocado no legado", decisão de 20/10/2026).

## Global Constraints

- Worktree `/Users/filipefalcao/Downloads/fabrica/sile-wt-legado`, branch `feat/renovacao-tvl-legada`; nunca editar nem commitar em `/Users/filipefalcao/Downloads/fabrica/sile`.
- Testes em PHPUnit (nunca Pest): `vendor/bin/phpunit --filter=<nome>`; `vendor/bin/pint --dirty --format agent` ao fim; sem autoria de IA em commit; texto visível em português, sistema chamado "Viabiliza".
- A trava de titularidade falha fechada: sem prova, bloqueia. A inscrição sozinha nunca prova titularidade.
- Razão social diferente nunca consulta o cadastro.
- Mensagens exatas (copiar verbatim):
  - `M_SEM_INSCRICAO` = "A viabilidade anterior não tem inscrição imobiliária registrada para conferir o endereço. Procure a SEDUR."
  - `M_NAO_ENCONTRADA` = "A inscrição imobiliária da viabilidade anterior não foi encontrada no cadastro. Procure a SEDUR."
  - `M_INDISPONIVEL` = "Não foi possível consultar o endereço do imóvel agora. Tente novamente em instantes."
  - `M_DIVERGE` = "O endereço deste CNPJ não confere com o do imóvel da viabilidade anterior. Procure a SEDUR."
- Baseline conhecido (não é nosso): `RenovacaoDecisaoTest::test_endpoint_grava_tipo_e_validade_da_renovacao` (403) e as demais 19 falhas listadas no ledger.

## Review Focus

- Cadastro devolve `cadastro` nulo ou todos os campos de endereço vazios: tratar como não encontrado, nunca como "confere".
- Imóvel sem CEP e sem logradouro ou número utilizável: não casa com ninguém.
- TVL sem inscrição na conferência: bloqueia com `M_SEM_INSCRICAO`; no protocolo e no assistente cai para o endereço do legado (decisão minha, registrada na spec como limite desta entrega).
- Cadastro fora do ar: `consultaIndisponivel`, serviço marca `indisponivel`, a TVL não some da lista.
- Razão diferente: o cadastro não é chamado (teste com função que falha se invocada); inscrição com máscara ou zeros à esquerda chega ao cadastro só com dígitos.

---

### Task C1: Resolvedor do endereço do imóvel

**Files:**
- Create: `app/Services/Renovacao/EnderecoDoImovel.php`, `app/Services/Renovacao/ResolvedorEnderecoImovel.php`, `app/Services/Renovacao/EnderecoImovelNaoEncontradoException.php`, `app/Services/Renovacao/EnderecoImovelIndisponivelException.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding `scoped` do resolvedor)
- Test: `tests/Feature/Renovacao/ResolvedorEnderecoImovelTest.php`

**Interfaces:**
- Consumes: `App\Services\Realty\PropertyRegistryLookup::resolve(string $inscricao): PropertyRegistryResult`, com `PropertyNotFoundException` e `PropertyRegistryUnavailableException`; `PropertyRegistryResult::$cadastro` (`?PropertyCadastroCampos`: `endereco`, `numero_porta`, `bairro`, `cep`).
- Produces:
  - `final readonly class EnderecoDoImovel(?string $logradouro, ?string $numero, ?string $bairro, ?string $cep)` com `texto(): string` (logradouro, número, bairro, CEP juntos por ", ", pulando vazios).
  - `ResolvedorEnderecoImovel::resolver(?string $inscricao): EnderecoDoImovel` — lança `EnderecoImovelNaoEncontradoException` (mensagem `M_SEM_INSCRICAO` para inscrição vazia; `M_NAO_ENCONTRADA` para `PropertyNotFoundException`, `cadastro` nulo ou os quatro campos vazios) e `EnderecoImovelIndisponivelException` (mensagem `M_INDISPONIVEL`, para `PropertyRegistryUnavailableException`). Guarda o resultado por inscrição durante a requisição.

- [ ] **Step 1: Testes que falham** em `ResolvedorEnderecoImovelTest` (fake de `PropertyRegistryLookup` no container): `test_mapeia_endereco_numero_porta_bairro_e_cep`; `test_inscricao_vazia_lanca_nao_encontrada_com_m_sem_inscricao_sem_consultar`; `test_inscricao_com_mascara_chega_ao_cadastro_so_com_digitos` (ex.: `"051.480-81"` → `"05148081"`); `test_property_not_found_vira_nao_encontrada`; `test_cadastro_nulo_vira_nao_encontrada`; `test_campos_todos_vazios_viram_nao_encontrada`; `test_cadastro_fora_do_ar_vira_indisponivel`; `test_mesma_inscricao_consulta_o_cadastro_uma_so_vez` (duas chamadas, `assertCount(1, $chamadas)`); `test_texto_junta_campos_preenchidos` e `test_texto_pula_campos_vazios`.
- [ ] **Step 2: Rodar e ver falhar** — `vendor/bin/phpunit --filter=ResolvedorEnderecoImovel` (classes inexistentes).
- [ ] **Step 3: Implementar** as quatro classes e o binding `scoped` no `AppServiceProvider`; a normalização da inscrição é só dígitos; o número é `numero_porta`.
- [ ] **Step 4: Rodar e ver passar**; `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 5: Commit** — `git commit -m "feat: resolvedor do endereço do imóvel pela inscrição"`.

### Task C2: Titularidade pelo endereço do imóvel

**Files:**
- Modify: `app/Services/Renovacao/ConferenciaTitularidade.php`, `app/Services/Renovacao/RenovacaoLegadaElegibilidadeService.php`
- Test: `tests/Unit/Renovacao/ConferenciaTitularidadeTest.php` (ou onde estiver hoje), `tests/Feature/Renovacao/RenovacaoLegadaElegibilidadeTest.php`, `tests/Feature/Renovacao/ProvisoriasLegadasServiceTest.php`

**Interfaces:**
- Consumes: `ResolvedorEnderecoImovel::resolver(?string): EnderecoDoImovel` e as duas exceções (C1).
- Produces:
  - `ConferenciaTitularidade::conferir(TvlLegadaDetalhe $tvl, CnpjData $empresa, callable $enderecoDoImovel): ConferenciaResultado` — `$enderecoDoImovel(): EnderecoDoImovel` só é chamado depois de a razão social igual; a comparação de endereço usa o `EnderecoDoImovel` em vez de `$tvl->endereco` (mesma regra: CEP de oito dígitos igual nos dois lados, ou logradouro normalizado e número iguais, ambos presentes). Uma `EnderecoImovelNaoEncontradoException` vira `ConferenciaResultado(false, $e->getMessage())`; a `EnderecoImovelIndisponivelException` não é capturada aqui. Divergência de endereço usa `M_DIVERGE`.
  - `RenovacaoLegadaElegibilidadeService` recebe o `ResolvedorEnderecoImovel` no construtor e, em `titularidade()`, passa `fn () => $this->resolvedor->resolver($tvl->inscricaoImobiliaria)`; captura `EnderecoImovelIndisponivelException` e devolve `ResultadoTitularidade::consultaIndisponivel(M_INDISPONIVEL)`. Todo o resto de `titularidade()` e de `avaliar()` fica igual (mesma ordem, mesmos motivos).

- [ ] **Step 1: Atualizar e acrescentar testes que falham.** Reescrever os testes de endereço de `ConferenciaTitularidade` para passar o imóvel por função (mesmos casos de CEP, logradouro e número, `S/N`, `avenida`/`av`, CEP de um lado só). Novos: `test_razao_diferente_nao_chama_o_cadastro` (função que lança `LogicException` se invocada); `test_endereco_do_imovel_igual_confere`; `test_divergencia_usa_m_diverge`; `test_imovel_nao_encontrado_devolve_o_motivo_da_excecao`; `test_cadastro_indisponivel_propaga_a_excecao`. No serviço: `test_tvl_sem_inscricao_bloqueia_com_m_sem_inscricao`; `test_inscricao_nao_encontrada_bloqueia_com_m_nao_encontrada`; `test_cadastro_fora_do_ar_e_consulta_indisponivel` (`titularidade()->consultaIndisponivel === true`, `avaliar()->motivo === M_INDISPONIVEL`); `test_cnpj_gravado_igual_nao_consulta_o_cadastro`. Em `ProvisoriasLegadasServiceTest`: `test_cadastro_fora_do_ar_marca_indisponivel_e_nao_descarta_a_tvl` (mesmo contrato da queda do CNPJ).
- [ ] **Step 2: Rodar e ver falhar** — `vendor/bin/phpunit --filter='ConferenciaTitularidade|RenovacaoLegadaElegibilidade|ProvisoriasLegadasService'`.
- [ ] **Step 3: Implementar** as duas mudanças acima; ajustar quem constrói esses objetos nos testes existentes.
- [ ] **Step 4: Rodar e ver passar**; incluir `vendor/bin/phpunit --filter=Renovacao`; `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 5: Commit** — `git commit -m "feat: titularidade da TVL legada confere o endereço do imóvel"`.

### Task C3: Endereço do imóvel no protocolo e no assistente

**Files:**
- Modify: `app/Services/Renovacao/RenovacaoProtocoloService.php`, `app/Http/Controllers/Portal/RenovacaoController.php` (método `showLegado`)
- Test: `tests/Feature/Renovacao/RenovacaoLegadaProtocoloTest.php`, `tests/Feature/Renovacao/RenovacaoLegadaPortalTest.php`

**Interfaces:**
- Consumes: `ResolvedorEnderecoImovel` e `EnderecoDoImovel::texto()` (C1).
- Produces: `protocolarLegada` grava `address_street`, `address_number`, `address_neighborhood` e `address_zip` com o endereço do imóvel quando a TVL tem inscrição (`address_complement` segue o `complemento` da TVL); `EnderecoImovelNaoEncontradoException` vira `DomainException` com a mesma mensagem e `EnderecoImovelIndisponivelException` vira `DomainException` com `M_INDISPONIVEL`, antes de criar qualquer coisa e antes de tomar o lock. TVL sem inscrição mantém o endereço do legado. `showLegado` mostra em `origem.endereco` o `texto()` do imóvel (mesma regra de fallback) e, em falha do cadastro, volta ao índice com a mensagem da exceção, no mesmo estilo dos outros bloqueios.

- [ ] **Step 1: Testes que falham.** Protocolo: `test_endereco_do_processo_vem_do_cadastro_pela_inscricao` (legado com endereço embaralhado, cadastro com o correto → processo com o do cadastro); `test_tvl_sem_inscricao_mantem_o_endereco_do_legado`; `test_cadastro_fora_do_ar_nao_cria_o_processo` (zero `ViabilityRequest`, `DomainException` com `M_INDISPONIVEL`); `test_inscricao_nao_encontrada_nao_cria_o_processo`. Portal: `test_assistente_mostra_o_endereco_do_imovel`; `test_assistente_com_cadastro_fora_do_ar_volta_ao_indice_com_a_mensagem`; `test_assistente_e_protocolo_consultam_o_cadastro_uma_vez_por_requisicao`.
- [ ] **Step 2: Rodar e ver falhar** — `vendor/bin/phpunit --filter='RenovacaoLegadaProtocolo|RenovacaoLegadaPortal'`.
- [ ] **Step 3: Implementar** no serviço de protocolo e no controller; reaproveitar o resolvedor com cache por requisição.
- [ ] **Step 4: Rodar e ver passar** (`--filter=RenovacaoLegada`, `--filter=RenovacaoProtocoloService`, `--filter=Renovacao`); `vendor/bin/pint --dirty --format agent`. Front-end: nada muda (o texto do endereço já chega pronto), então não há `npm run build`.
- [ ] **Step 5: Commit** — `git commit -m "feat: endereço do imóvel no protocolo e no assistente da renovação legada"`.

## Self-Review

- **Spec:** conferência (C2), endereço do processo e do assistente (C3), resolvedor e cache por requisição (C1), SEDUR nas mensagens (constantes globais), cadastro fora do ar (C2/C3). A lista de provisórias fica de fora por decisão registrada na spec.
- **Tipos:** `EnderecoDoImovel`, `resolver(?string)`, as duas exceções e `consultaIndisponivel(M_INDISPONIVEL)` têm o mesmo nome em todas as tarefas.
- **Proporção:** três tarefas pequenas, cada uma com seu ciclo de teste.
