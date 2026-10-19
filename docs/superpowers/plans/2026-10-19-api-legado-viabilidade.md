# API legada de viabilidade e renovação de TVL legada no portal — plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Servir os processos de viabilidade do legado Simplifica por uma API somente leitura e fazer o portal do Viabiliza renovar TVL provisória legada consultando essa API, sem importar o legado.

**Architecture:** Parte A é um serviço Laravel novo (`legado-simplifica/api`) que lê o SQL Server legado por uma conexão `legado` e guarda clientes, tokens e auditoria num SQLite próprio. Parte B, neste repositório, adiciona um cliente HTTP, a conferência de titularidade e a elegibilidade da renovação legada, uma coluna de vínculo em `viability_requests` e a tela do portal. A Parte B só começa depois da A, porque consome o contrato da Tarefa A9.

**Tech Stack:** Laravel 13 / PHP 8.4 (imagem da API) com `pdo_sqlsrv`, SQL Server 2022, SQLite, PHPUnit 12; no portal: Laravel 13, Inertia v3, React 19, Tailwind 4.

**Spec:** `docs/superpowers/specs/2026-10-19-api-legado-viabilidade-design.md`

## Global Constraints

- O vencimento vem de `TVL_DATA_VALIDADE`, nunca de emissão + 24 meses. "Deferida" é `TVL_DEFERIDO = 1`. `TVL_STATUS_TVL_ID` não filtra.
- O identificador público da TVL é `TVL_NUM_TVL_ID`; entre as linhas do mesmo número, a vigente é a de maior `TVL_DATA_VALIDADE`, com desempate pelo maior `TVL_ID`.
- Tipo provisório é `TVL_TIP_TVL_ID = 2`. Só provisória deferida renova.
- "A vencer": janela padrão de 90 dias (`renovacao.legado.janela_a_vencer_dias`). Vencida pode renovar (`renovacao.legado.permite_vencida`, padrão ligado).
- O intervalo mínimo entre renovações reutiliza `renovacao.intervalo_meses` (padrão 3).
- A renovação é sempre de um CNPJ de empresa vinculada ao usuário efetivo (`CurrentRepresentation::grantor() ?? $request->user()`). Nunca por CPF nem por coincidência de inscrição.
- Banco legado somente leitura: nenhum `INSERT/UPDATE/DELETE/DDL` na conexão `legado`.
- API: `/v1`, JSON, Bearer por cliente com escopos `provisorias:ler` e `processos:ler`, chaves exatas, sem busca por nome.
- Endereço e atividades da TVL legada não são editáveis no assistente; atividade fora do catálogo atual torna a TVL não elegível.
- Projeto Viabiliza: testes em PHPUnit (nunca Pest), `php artisan make:` para arquivos novos, `vendor/bin/pint --dirty --format agent` ao fim, `public/build` commitado quando mudar TSX, sem autoria de IA em commit.
- O sistema se chama Viabiliza, não SILE, em texto visível.

## Review Focus

1. TVL de outra empresa nunca aparece nem libera: CNPJ legado diferente do da empresa vinculada, e CNPJ informado que não passa na conferência (Tarefas B4, B5, B6).
2. API legada fora do ar ou lenta: a lista de renovação do Viabiliza continua, com aviso nas legadas (Tarefas B1, B6).
3. Documentos com máscara, zeros à esquerda ou CPF no lugar do CNPJ: nunca viram titular CNPJ; inscrição com zeros à esquerda ainda acha a TVL (Tarefas A3, A5).
4. Número de TVL com várias linhas e renovações em cadeia: a vigente é a última e a renovação duplicada é barrada (Tarefas A4, B5).
5. Clique duplo no protocolo da renovação legada: o segundo é recusado pela regra "em aberto" (Tarefa B7).

---

# Parte A — API legada (`legado-simplifica/api`)

Todos os caminhos da Parte A são relativos a `/Users/filipefalcao/Downloads/fabrica/legado-simplifica/api`. Não há SQL Server local (falta espaço em disco): os testes rodam no PHP do Mac, com `php artisan test --compact --filter=<nome>`, contra um banco **separado** `legado_test` no SQL Server do Portainer (`144.22.212.3:8082`). Localmente o Laravel cai para o `pdo_dblib`; a imagem de produção usa `pdo_sqlsrv`. O banco de dados legado `GR_SalvadorSimplifica` nunca é alvo de teste.

### Task A1: Esqueleto Laravel, imagem com `pdo_sqlsrv` e banco de testes

**Files:**
- Create: o projeto Laravel 13 (via `composer create-project`, inclusive `.gitignore`, `git init`), `Dockerfile` (só produção), `.env.testing` (ignorado pelo git, com host, porta, usuário e senha lidos de `../.env` e `LEGADO_DB_DATABASE=legado_test`), `config/legado.php`, `tests/Support/LegadoFixture.php`, `database/legado/schema_teste.sql`, `tests/Feature/SaudeTest.php`
- Modify: `config/database.php` (conexão `legado` tipo `sqlsrv`, `default` = `sqlite`), `routes/api.php`, `bootstrap/app.php` (rotas de API sob `/v1`, `/up`)

**Interfaces:**
- Produces: conexão `DB::connection('legado')`; `Tests\Support\LegadoFixture::limpar(): void` (apaga as linhas das tabelas de teste) e `::inserir(string $tabela, array $linha): void`; `config('legado.janela_a_vencer_dias')` = 90.

- [ ] **Step 1: Escrever o teste de saúde** `SaudeTest::test_up_responde_200_e_legado_responde_select_1` — GET `/up` é 200, e `DB::connection('legado')->selectOne('select 1 as x')->x === 1`.
- [ ] **Step 2: Rodar e ver falhar** (projeto ainda inexistente).
- [ ] **Step 3: Criar o projeto e o ambiente.** `Dockerfile` (php:8.4-cli) instala `msodbcsql18`, `unixodbc-dev` e as extensões PECL `sqlsrv`/`pdo_sqlsrv` (já escrito; não precisa buildar de novo). Remover o `docker-compose.test.yml`. `LegadoFixture` cria o banco `legado_test` pela conexão ao `master` se não existir, aplica o schema uma vez por execução e recusa qualquer nome de banco diferente de `legado_test` (guarda contra tocar em `GR_SalvadorSimplifica`). `schema_teste.sql` traz os `CREATE TABLE` das tabelas usadas (copiados de `../sql/01_estrutura.sql`): `TVL`, `PROCESSO`, `USUARIO`, `RENOVACAO_TVL`, `FICHA_TVL`, `FICHA_MATRIZ_REQUERENTE`, `CNAE`, `STATUS_HISTORICO`, `STATUS_PROCESSO`, `TIPO_TVL`. `LegadoFixture` aplica o schema uma vez por execução. Se `pdo_sqlsrv` não compilar para o PHP escolhido, cair para a imagem PHP mais próxima suportada e registrar no README.
- [ ] **Step 4: Rodar o teste e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "chore: esqueleto da API legada com SQL Server"`.

### Task A2: Documento e titular

**Files:**
- Create: `app/Legado/Documento.php`, `app/Legado/Titular.php`, `app/Legado/TitularResolver.php`, `tests/Unit/TitularResolverTest.php`

**Interfaces:**
- Produces: `Documento::normalizar(?string $valor): ?string` (só dígitos; `null` se vazio); `Documento::tipo(?string $digitos): ?string` (`'cnpj'` com 14 dígitos, `'cpf'` com 11, senão `null`); `final readonly class Titular(?string $documento, ?string $tipo, ?string $origem)` com `origem` em `tvl|requerente|outorgante|null`; `TitularResolver::resolver(object $linha): Titular` onde `$linha` tem `tvl_cnpj_empresa`, `requerente_documento`, `outorgante_documento`.

- [ ] **Step 1: Testes** (nomes e asserções):
  - `test_normaliza_mascara`: `'12.345.678/0001-90'` → `'12345678000190'`; `''` e `null` → `null`.
  - `test_cnpj_da_tvl_tem_prioridade`: tvl com 14 dígitos vence requerente CPF → `Titular('...', 'cnpj', 'tvl')`.
  - `test_requerente_com_cnpj_quando_tvl_vazia`: origem `requerente`.
  - `test_cpf_do_requerente_nao_vira_titular_cnpj`: só CPF em todos os campos → `Titular(null, null, null)`.
  - `test_tamanho_invalido_e_ignorado`: 13 dígitos nunca é titular.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** os três tipos nas assinaturas acima; o resolver tenta tvl → requerente → outorgante e só aceita 14 dígitos.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: normalização de documento e resolução do titular da TVL"`.

### Task A3: Situação da vigência

**Files:**
- Create: `app/Legado/Situacao.php` (enum `vencida|a_vencer|vigente`), `app/Legado/SituacaoCalculator.php`, `tests/Unit/SituacaoCalculatorTest.php`

**Interfaces:**
- Produces: `SituacaoCalculator::calcular(CarbonInterface $validade, CarbonInterface $hoje, int $janelaDias): array{situacao: Situacao, dias_para_vencer: int}`; `dias_para_vencer` é negativo quando vencida; comparação por data (sem horas).

- [ ] **Step 1: Testes:** validade ontem → `vencida`, `-1`; validade hoje → `a_vencer`, `0`; hoje + 90 → `a_vencer`; hoje + 91 → `vigente`; validade às 23:59 de hoje e `hoje` às 00:01 → `a_vencer`.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** com `startOfDay()` nos dois lados.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: situação de vigência da TVL"`.

### Task A4: Repositório de TVLs (vigente por número, renovação posterior)

**Files:**
- Create: `app/Legado/TvlRepository.php`, `tests/Feature/TvlRepositoryTest.php`

**Interfaces:**
- Consumes: `LegadoFixture`, `TitularResolver`, `Documento`.
- Produces: `TvlRepository::provisoriasPor(string $chave, string $valor): Collection<int, object>` com `$chave` em `cnpj|inscricao|numero`; `TvlRepository::vigentePorNumero(int $numero): ?object`; `TvlRepository::renovacoesDoNumero(int $numero): Collection` (linhas de `RENOVACAO_TVL`). Cada linha devolvida traz `numero`, `tvl_id`, `processo_id`, `deferida`, `ativa`, `emissao`, `validade`, `razao_social`, `inscricao`, `logradouro`, `numero_endereco`, `bairro`, `cep`, `titular` (`Titular`), `renovada_depois` (bool), `ultima_renovacao_em`.

- [ ] **Step 1: Testes** (cada um monta linhas com `LegadoFixture::inserir`):
  - `test_vigente_e_a_linha_de_maior_validade_do_numero`: três linhas do número 100 → devolve a de maior validade.
  - `test_desempate_pelo_maior_tvl_id`.
  - `test_so_provisoria_deferida_entra_na_lista`: tipo 1, tipo 3 e `TVL_DEFERIDO=0` ficam de fora.
  - `test_renovada_depois_com_linha_mais_nova_ou_registro_em_renovacao_tvl`: dois casos.
  - `test_busca_por_cnpj_ignora_mascara_do_dado_legado`: TVL gravada com `'12.345.678/0001-90'` é achada por `'12345678000190'`.
  - `test_busca_por_inscricao_ignora_zeros_a_esquerda`: gravada `'5148081'`, buscada `'005148081'` e `'5148081'`.
  - `test_titular_vem_do_requerente_quando_tvl_sem_cnpj`: `PROCESSO.PRO_USU_ID` → `USUARIO.USU_CPF_CNPJ` com 14 dígitos.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** com o query builder da conexão `legado`: subconsulta que escolhe a vigente por número (`ROW_NUMBER() OVER (PARTITION BY TVL_NUM_TVL_ID ORDER BY TVL_DATA_VALIDADE DESC, TVL_ID DESC)`); comparar documento removendo `.`, `/`, `-` no SQL; comparar inscrição removendo zeros à esquerda dos dois lados; `renovada_depois` = existe linha do mesmo número com `TVL_ID` maior, ou registro em `RENOVACAO_TVL` com `REN_TVL_NUM_TVL_ID` igual; `ultima_renovacao_em` = maior `REN_TVL_DAT_SOL`.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: repositório de TVLs legadas"`.

### Task A5: Detalhe da TVL (atividades, flags, renovações)

**Files:**
- Create: `app/Legado/TvlDetalheRepository.php`, `tests/Feature/TvlDetalheRepositoryTest.php`

**Interfaces:**
- Consumes: `TvlRepository::vigentePorNumero`.
- Produces: `TvlDetalheRepository::detalhe(int $numero): ?array` com as chaves da linha de A4 mais `processo_numero`, `complemento`, `zona`, `via`, `flags` (`sede`, `mei`, `escritorio_virtual`, `culto_religioso`, `edificio_comercial`: bool), `condicionante_adicional` (string|null), `atividades` (`list<array{codigo: string, descricao: string}>`), `renovacoes` (`list<array{solicitada_em: ?string, paga_em: ?string}>`).

- [ ] **Step 1: Testes:**
  - `test_atividades_vem_da_ficha_mais_recente_sem_as_excluidas`: duas fichas da mesma TVL; só a de maior `FIC_TVL_ID` conta; linhas com `FIC_MAT_REQ_EXCLUIDA = 1` ficam de fora; código vem de `CNAE_CODIGO_CNAE`, descrição de `CNAE_NOME`.
  - `test_sem_ficha_devolve_atividades_vazias`.
  - `test_flags_e_renovacoes`.
  - `test_numero_inexistente_devolve_null`.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** juntando `TVL`→`PROCESSO`, `FICHA_TVL` (por `FIC_TVL_TVL_ID` da TVL vigente), `FICHA_MATRIZ_REQUERENTE`→`CNAE`.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: detalhe da TVL legada com atividades"`.

### Task A6: Autenticação por token, escopos, limite e auditoria

**Files:**
- Create: `database/migrations/*_create_api_clientes_table.php`, `*_create_api_consultas_table.php`, `app/Models/ApiCliente.php`, `app/Http/Middleware/AutenticarCliente.php`, `app/Http/Middleware/RegistrarConsulta.php`, `app/Console/Commands/ClientesCriar.php`, `app/Console/Commands/ClientesRevogar.php`, `tests/Feature/AutenticacaoTest.php`
- Modify: `bootstrap/app.php` (alias `cliente`, limitador `legado` por cliente, 60/min)

**Interfaces:**
- Produces: middleware `cliente:<escopo>`; `php artisan clientes:criar {nome} {--escopo=*}` imprime o token uma única vez e grava só `hash('sha256', $token)`; `clientes:revogar {id}`; tabela `api_consultas(cliente_id, rota, chave, tipo_chave, status, criado_em)` — `chave` guarda só o tipo e os 4 últimos caracteres da chave consultada.

- [ ] **Step 1: Testes:** sem token → 401; token inválido ou revogado → 401; escopo ausente → 403; escopo certo → 200 numa rota de teste; 61ª chamada no minuto → 429; cada chamada grava uma linha em `api_consultas` sem a chave completa; o comando não grava o token em claro.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** as migrations (SQLite), o middleware (busca por hash, escopos em JSON) e os comandos.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: autenticação por token com escopos, limite e auditoria"`.

### Task A7: Endpoints de provisórias e de TVL

**Files:**
- Create: `app/Http/Controllers/V1/ProvisoriasController.php`, `app/Http/Controllers/V1/TvlController.php`, `app/Http/Resources/TvlResumoResource.php`, `app/Http/Resources/TvlDetalheResource.php`, `app/Http/Requests/ProvisoriasRequest.php`, `tests/Feature/ProvisoriasEndpointTest.php`, `tests/Feature/TvlEndpointTest.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `TvlRepository`, `TvlDetalheRepository`, `SituacaoCalculator`, middleware da A6.
- Produces: `GET /v1/provisorias?{cnpj|inscricao|numero}=…&a_vencer_dias=` (escopo `provisorias:ler`) → `{"data":[TvlResumo]}`; `GET /v1/tvls/{numero}` (mesmo escopo) → `{"data":TvlDetalhe}`. `TvlResumo`: `numero`, `tipo` (`"provisoria"`), `deferida`, `ativa`, `emissao`, `validade`, `situacao`, `dias_para_vencer`, `renovada_depois`, `ultima_renovacao_em`, `titular {documento, tipo, origem}`, `razao_social`, `inscricao_imobiliaria`, `endereco {logradouro, numero, bairro, cep}`. `TvlDetalhe` = `TvlResumo` + `processo_numero`, `complemento`, `zona`, `via`, `flags`, `condicionante_adicional`, `atividades`, `renovacoes`. Datas em ISO 8601.

- [ ] **Step 1: Testes:** exatamente uma chave, senão 422 com mensagem; CNPJ com máscara é aceito e normalizado; `numero` sem TVL → `data` vazio em `/provisorias` e 404 em `/tvls/{numero}`; `a_vencer_dias=30` muda `situacao` de uma TVL que vence em 45 dias; resposta de `/tvls/{numero}` contém as chaves de `TvlDetalhe`; escopo `processos:ler` sozinho → 403.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** os controllers finos, o FormRequest (valida chave única) e os resources.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: endpoints de provisórias e detalhe da TVL"`.

### Task A8: Endpoints de processo legado

**Files:**
- Create: `app/Legado/ProcessoRepository.php`, `app/Http/Controllers/V1/ProcessosController.php`, `app/Http/Resources/ProcessoResource.php`, `tests/Feature/ProcessosEndpointTest.php`
- Modify: `routes/api.php`

**Interfaces:**
- Produces: `GET /v1/processos/{numero}` (escopo `processos:ler`) → `{numero, criado_em, status {id, nome, fim}, tvls: [TvlResumo], historico: [{em, titulo, mensagem, status}]}` (histórico de `STATUS_HISTORICO` por `STS_HIS_DATA_CRIADO` crescente); `GET /v1/processos?{inscricao|documento|numero_tvl}=…` → `{"data":[{numero, criado_em, status}]}` limitado a 50.

- [ ] **Step 1: Testes:** processo com histórico em ordem; processo sem TVL devolve `tvls: []`; consulta por inscrição acha o processo da TVL; sem chave ou com duas → 422; mais de 50 resultados retorna 50.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: consulta de processos legados"`.

### Task A9: Contrato OpenAPI e entrega

**Files:**
- Create: `public/openapi.yaml`, `tests/Feature/OpenApiTest.php`, `Dockerfile.prod`, `docker-compose.portainer.yml`, `README.md`
- Modify: `routes/web.php` (`GET /docs` serve o YAML)

**Interfaces:**
- Produces: `public/openapi.yaml` com todos os caminhos e esquemas das Tarefas A7 e A8 (é o contrato que a Parte B consome).

- [ ] **Step 1: Teste** `OpenApiTest::test_toda_rota_v1_esta_documentada`: cada URI de `Route::getRoutes()` que começa com `v1/` aparece em `paths` do YAML (parse com `symfony/yaml`).
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Escrever o YAML e os artefatos de entrega.** `docker-compose.portainer.yml` põe a API na rede do stack `legado-simplifica` e na `sile_net`, com `LEGADO_DB_HOST=legado-simplifica-mssql`, SQLite num volume e healthcheck em `/up`; `README.md` descreve variáveis, `clientes:criar` e o fechamento da porta 8082 na entrega. Subir o stack no Portainer fica para o usuário aprovar (ação em ambiente compartilhado).
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "docs: contrato OpenAPI e artefatos de entrega"`.

---

# Parte B — Portal Viabiliza (este repositório)

Testes: `php artisan test --compact --filter=<nome>`.

### Task B1: Cliente HTTP da API legada

**Files:**
- Create: `app/Services/Legado/LegadoViabilidadeClient.php`, `app/Services/Legado/TvlLegada.php`, `app/Services/Legado/TvlLegadaDetalhe.php`, `app/Services/Legado/LegadoIndisponivelException.php`, `tests/Feature/Legado/LegadoViabilidadeClientTest.php`
- Modify: `config/viabiliza.php` (`integrations.legado_api` = `base_url`, `token`, `timeout` 8, lidos de `LEGADO_API_URL` e `LEGADO_API_TOKEN`)

**Interfaces:**
- Produces: `LegadoViabilidadeClient::provisoriasPorCnpj(string $cnpj): list<TvlLegada>`; `::tvlsDoProcesso(string $numeroProcesso): list<TvlLegada>` (usa `GET /v1/processos?numero_processo=` e depois `GET /v1/tvls/{numero}` para cada item de `numeros_tvl`; lista vazia quando o processo não existe); os erros 422 da API trazem `mensagem` e `erros`; `::tvlPorNumero(int $numero): ?TvlLegadaDetalhe` (`null` em 404); todos lançam `LegadoIndisponivelException` em timeout, 5xx após 3 tentativas ou 401/403. `TvlLegada` e `TvlLegadaDetalhe` são `final readonly` com os campos do contrato da A7 (datas como `CarbonImmutable`).

- [ ] **Step 1: Testes** (com `Http::fake`): lista mapeada com `titular`/`endereco`; 404 em `tvlPorNumero` → `null`; `tvlsDoProcesso` mapeia as `tvls` do processo e devolve lista vazia em 404; 503 duas vezes e 200 na terceira → sucesso; timeout nas três → `LegadoIndisponivelException`; envia `Authorization: Bearer <token>`; sem `base_url` configurada → `LegadoIndisponivelException` sem chamar a rede.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** no estilo de `SedurContatoClient` (laço de tentativas, `connectTimeout(4)`).
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: cliente da API legada de viabilidade"`.

### Task B2: Parâmetros e vínculo no processo

**Files:**
- Create: `database/migrations/*_add_legacy_tvl_to_viability_requests_table.php`, `tests/Feature/Renovacao/RenovacaoLegadaVinculoTest.php`
- Modify: `database/seeders/ParameterSeeder.php` (duas chaves no grupo `solicitacao`, junto de `renovacao.intervalo_meses`, e na lista da linha ~975), `app/Models/ViabilityRequest.php` (fillable e casts), `app/Models/ViabilityRequestFactory` se existir

**Interfaces:**
- Produces: colunas `renews_legacy_tvl_number` (unsignedBigInteger nullable, indexada) e `legacy_tvl_snapshot` (json nullable); parâmetros `renovacao.legado.janela_a_vencer_dias` (integer, padrão `90`, 1–365) e `renovacao.legado.permite_vencida` (boolean, padrão `1`); `ViabilityRequest::$legacy_tvl_snapshot` com cast `array`.

- [ ] **Step 1: Testes:** criar `ViabilityRequest` com número legado e snapshot e recuperar o array; `Settings::get('renovacao.legado.janela_a_vencer_dias')` = `90` depois do seeder; `Settings::get('renovacao.legado.permite_vencida')` verdadeiro.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** a migration (com `down`), o seeder e o model.
- [ ] **Step 4: Rodar e ver passar** (inclui `--filter=Renovacao` para provar que nada quebrou).
- [ ] **Step 5: Commit** — `git commit -m "feat: vínculo de renovação com TVL legada e parâmetros"`.

### Task B3: Conferência de titularidade

**Files:**
- Create: `app/Services/Renovacao/ConferenciaTitularidade.php`, `app/Services/Renovacao/ConferenciaResultado.php`, `tests/Unit/Renovacao/ConferenciaTitularidadeTest.php`

**Interfaces:**
- Consumes: `TvlLegadaDetalhe`, `App\Services\Cnpj\CnpjData` (campos `legalName`, `street`, `number`, `zipCode`).
- Produces: `ConferenciaTitularidade::conferir(TvlLegadaDetalhe $tvl, CnpjData $empresa): ConferenciaResultado` com `ConferenciaResultado(bool $confere, ?string $motivo)`; `ConferenciaTitularidade::normalizarRazao(string $texto): string` (minúsculas, sem acento, sem pontuação, sem sufixos `ltda|me|epp|eireli|sa|s a`, espaços colapsados).

- [ ] **Step 1: Testes:**
  - `test_razao_igual_e_inscricao_igual_confere`.
  - `test_razao_igual_com_sufixo_e_pontuacao_diferentes_confere`: `"BMS COMERCIO VAREJISTA DE ALIMENTOS LTDA"` × `"Bms Comércio Varejista de Alimentos Ltda."`.
  - `test_razao_diferente_nao_confere`: motivo cita a razão social.
  - `test_razao_igual_mas_endereco_e_inscricao_diferentes_nao_confere`: motivo cita endereço.
  - `test_endereco_confere_por_logradouro_e_numero_quando_nao_ha_inscricao`: `"RUA DA ENGOMADEIRA"`/`508` × `"R DA ENGOMADEIRA"`/`508` (prefixos `RUA|R|AV|AVENIDA|TV|TRAVESSA` equivalentes).
  - `test_tvl_sem_razao_social_nao_confere`.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.** Confere quando a razão normalizada é igual e (o CEP, ou o logradouro normalizado com o número, é igual). A inscrição imobiliária da TVL não consta do cadastro do CNPJ, portanto entra só se `CnpjData` a trouxer; senão vale o endereço.
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: conferência de titularidade da TVL legada"`.

### Task B4: Elegibilidade da renovação legada

**Files:**
- Create: `app/Services/Renovacao/RenovacaoLegadaElegibilidadeService.php`, `tests/Feature/Renovacao/RenovacaoLegadaElegibilidadeTest.php`

**Interfaces:**
- Consumes: `RenovacaoElegibilidade` (existente), `ConferenciaTitularidade`, `App\Services\Cnpj\CnpjLookup`, `Settings`, modelo `Cnae` (campo de código).
- Produces: `RenovacaoLegadaElegibilidadeService::avaliar(TvlLegadaDetalhe $tvl, Company $empresa): RenovacaoElegibilidade`. Ordem das regras, a primeira que falha devolve o motivo: (1) titular com CNPJ gravado diferente do CNPJ da empresa → "Esta viabilidade pertence a outra empresa."; (2) sem CNPJ gravado e conferência falha → motivo da conferência; (3) não é provisória deferida → "Somente viabilidade provisória deferida pode ser renovada."; (4) alguma atividade fora do catálogo → "A atividade X não consta mais do catálogo. Procure a SEDUR."; (5) `renovada_depois` → "Esta viabilidade já foi renovada."; (6) vencida com `renovacao.legado.permite_vencida` desligado → "Viabilidade vencida não pode ser renovada."; (7) renovação do Viabiliza deferida há menos de `renovacao.intervalo_meses` com o mesmo `renews_legacy_tvl_number` → mesma mensagem da renovação atual; (8) renovação do Viabiliza em aberto (`Protocolada`, `EmAnalise`, `EmPendencia`) → `MOTIVO_EM_ANALISE` da renovação atual.

- [ ] **Step 1: Testes:** um por regra, na ordem, mais `test_elegivel_quando_tudo_confere` e `test_cnpj_gravado_igual_dispensa_a_consulta_cadastral` (o `CnpjLookup` é fake e não pode ser chamado).
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.** Reutilizar as mensagens do serviço existente tornando-as constantes públicas, sem mudar seu comportamento (`RenovacaoElegibilidadeTest` deve continuar verde).
- [ ] **Step 4: Rodar e ver passar** (`--filter=RenovacaoLegadaElegibilidade` e `--filter=RenovacaoElegibilidade`).
- [ ] **Step 5: Commit** — `git commit -m "feat: elegibilidade da renovação de TVL legada"`.

### Task B5: Serviço de consulta do portal

**Files:**
- Create: `app/Services/Renovacao/ProvisoriasLegadasService.php`, `tests/Feature/Renovacao/ProvisoriasLegadasServiceTest.php`

**Interfaces:**
- Consumes: `LegadoViabilidadeClient`, `RenovacaoLegadaElegibilidadeService`.
- Produces: `ProvisoriasLegadasService::paraEmpresas(Collection $empresas): array{itens: list<array>, indisponivel: bool}` (para cada empresa chama `provisoriasPorCnpj`; cada item tem `numero`, `empresa_id`, `empresa`, `validade`, `situacao`, `dias_para_vencer`, `endereco`, `elegivel`, `motivo`, `origem` = `'legado'`); `::buscarPorNumero(Company $empresa, string $numeroViabilidade): array{itens: list<array>, indisponivel: bool}`, em que `$numeroViabilidade` é o número da TVL (`/^\d{7}$/`, usa `tvlPorNumero`) ou o do processo (`/^\d{10}-\d{8}\/\d{4}$/`, usa `tvlsDoProcesso`); formato fora desses dois devolve `itens` vazio sem chamar a API. Exceção do cliente vira `indisponivel: true` com `itens` vazio. Só devolve TVLs cujo `titular.documento` é nulo ou igual ao CNPJ da empresa; o resto é descartado em silêncio, para não revelar TVL de outro titular.

- [ ] **Step 1: Testes:** número da TVL e número do processo chegam à mesma TVL; número em formato inválido não chama a API; TVL com CNPJ de outra empresa é descartada; TVL sem CNPJ aparece com o veredito da conferência; falha do cliente → `indisponivel: true`; empresas sem vínculo ao usuário não são consultadas (o filtro vem do chamador, o teste prova que `paraEmpresas([])` não chama o cliente).
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: serviço de provisórias legadas do portal"`.

### Task B6: Rotas e controller do portal

**Files:**
- Create: `app/Http/Requests/Portal/ConsultarLegadoRequest.php`, `tests/Feature/Renovacao/RenovacaoLegadaPortalTest.php`
- Modify: `app/Http/Controllers/Portal/RenovacaoController.php`, `routes/portal.php`

**Interfaces:**
- Consumes: `ProvisoriasLegadasService`, `empresasDo()` e `usuarioEfetivo()` do controller.
- Produces: `RenovacaoController::index` ganha as props `legado` (lista de B5) e `legado_indisponivel` (bool); `consultarCnpj` ganha a chave `legado` na resposta; `POST renovacao/legado/consultar` (`portal.renovacao.legado.consultar`, corpo `empresa_id` + `numero_viabilidade`) → JSON `{itens, indisponivel}`; `GET renovacao/legado/{empresa}/{numero}` (`portal.renovacao.legado.show`); `POST renovacao/legado/{empresa}/{numero}` (`portal.renovacao.legado.store`). Declarar as rotas `renovacao/legado/...` antes de `renovacao/{origem}`.

- [ ] **Step 1: Testes:** `index` inclui as legadas com CNPJ gravado das empresas do usuário e `legado_indisponivel = false`; com `Http::fake` devolvendo 503, `index` ainda lista as TVLs do Viabiliza e marca `legado_indisponivel = true`; `consultar` com `empresa_id` de empresa não vinculada → 403; `consultar` sem `numero_viabilidade` ou em formato inválido → 422; `show` de TVL não elegível redireciona para a lista com o motivo; procurador atuando por outorgante vê as empresas do outorgante.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** reaproveitando `autorizarEmpresa` adaptado a `Company`, e `Inertia::render('portal/renovacao/wizard')` com `origem.legado = true` e os dados do detalhe (empresa, endereço, atividades, validade, tipo).
- [ ] **Step 4: Rodar e ver passar.**
- [ ] **Step 5: Commit** — `git commit -m "feat: rotas do portal para renovação de TVL legada"`.

### Task B7: Protocolo da renovação legada

**Files:**
- Modify: `app/Services/Renovacao/RenovacaoProtocoloService.php`
- Test: `tests/Feature/Renovacao/RenovacaoLegadaProtocoloTest.php`

**Interfaces:**
- Consumes: `RenovacaoLegadaElegibilidadeService`, `LegadoViabilidadeClient::tvlPorNumero`.
- Produces: `RenovacaoProtocoloService::protocolarLegada(TvlLegadaDetalhe $tvl, Company $empresa, User $ator, array $contato, array $declaracaoIds): ViabilityRequest`. Cria o processo com `renews_legacy_tvl_number`, `legacy_tvl_snapshot` (o detalhe usado), `company_id`, endereço e inscrição da TVL e as atividades achadas no catálogo; passa pelo mesmo caminho de protocolo e análise semi-expressa da renovação atual.

- [ ] **Step 1: Refatorar com rede de segurança.** Rodar `--filter=RenovacaoProtocoloService` (verde). Extrair do método `protocolar` a parte comum (declarações, protocolo, transições, auditoria, território, setor, envio à análise) para um método privado que recebe os atributos do novo processo e os ids de CNAE. Rodar de novo (continua verde).
- [ ] **Step 2: Testes de `protocolarLegada`:** cria processo com número legado, snapshot, empresa e atividades; status final `EmAnalise` com categoria `SemiExpresso`; declaração obrigatória não marcada → `DomainException`; TVL não elegível → `DomainException` com o motivo; `test_segundo_protocolo_da_mesma_tvl_e_recusado` (chamar duas vezes seguidas; a segunda lança a exceção da regra "em aberto"); `used_area_m2` ausente do legado não impede o protocolo.
- [ ] **Step 3: Rodar e ver falhar.**
- [ ] **Step 4: Implementar `protocolarLegada`** e ligar `storeLegado` do controller (B6) a ele, tratando `DomainException` como o `store` atual.
- [ ] **Step 5: Rodar e ver passar** (`--filter=RenovacaoLegada` e `--filter=RenovacaoProtocoloService`).
- [ ] **Step 6: Commit** — `git commit -m "feat: protocolo da renovação de TVL legada"`.

### Task B8: Tela do portal

**Files:**
- Modify: `resources/js/pages/portal/renovacao/index.tsx`, `resources/js/pages/portal/renovacao/wizard.tsx`, `public/build` (via `npm run build`)
- Test: `tests/Feature/Renovacao/RenovacaoLegadaPortalTest.php` (acrescentar asserções Inertia)

**Interfaces:**
- Consumes: props `legado`, `legado_indisponivel` e a rota `portal.renovacao.legado.consultar` (B6); `origem.legado` no wizard.
- Produces: na lista, linhas legadas com selo "Sistema anterior", situação (vencida, a vencer em N dias, vigente) e botão Renovar ou o motivo; aviso quando `legado_indisponivel`; ação "Renovar viabilidade do sistema anterior" abrindo uma caixa com seletor de empresa vinculada e um campo "Número da viabilidade" (aceita o número da TVL ou o do processo), que chama `consultar` e lista o resultado; o wizard envia ao `store` legado quando `origem.legado`.

- [ ] **Step 1: Testes Inertia:** a página `portal/renovacao/index` recebe `legado` e `legado_indisponivel`; a página `portal/renovacao/wizard` recebe `origem.legado = true` para TVL legada elegível.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** a UI seguindo o padrão visual das duas páginas; textos em português, com "Viabiliza".
- [ ] **Step 4: Rodar os testes e ver passar; `npm run build`; conferir no navegador** com `preview_start` e a API legada simulada por `Http::fake` ou por uma instância local, sem erros de console.
- [ ] **Step 5: Commit** — `git commit -m "feat: tela de renovação de TVL legada no portal"`.

### Task B9: Verificação final

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 2:** `php artisan test --compact --filter=Renovacao` e `--filter=Legado`; todos verdes.
- [ ] **Step 3:** Perguntar ao usuário se roda a suíte completa (`php artisan test --compact`).
- [ ] **Step 4:** Rodar a verificação ponta a ponta contra a API real: `legado-simplifica/api` apontando para o SQL Server do Portainer (porta 8082), uma consulta por inscrição conhecida e a conferência de um CNPJ real da amostra, mostrando a saída.
