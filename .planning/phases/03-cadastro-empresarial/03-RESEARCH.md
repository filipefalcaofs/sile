# Fase 3: Cadastro Empresarial — Pesquisa

**Pesquisado em:** 2026-06-11
**Domínio:** cadastro empresarial (companies/vínculos/CNAEs), consulta pública de CNPJ, importação REDESIM, Laravel 13 + Inertia v3 + React 19
**Confiança:** ALTA (provider CNPJ testado com chamadas reais; HTTP client confirmado na doc oficial 13.x; padrões internos lidos do código) / MÉDIA no payload REDESIM (estrutura de referência a validar com a SEDUR)

## Summary

A fase cria o cadastro empresarial completo do portal: empresas com CNPJ único, CNAEs principal/secundários vinculados à tabela oficial da Fase 2, vínculos usuário-empresa com papéis e ciclo de vida, consulta de dados por CNPJ para pré-preenchimento (HU-021) e importação no formato REDESIM atrás de contrato (HU-022). Nenhuma dependência nova de composer/npm é necessária: o HTTP client nativo do Laravel cobre o provider de CNPJ, e os padrões de import/auditoria/policy/telas já existem no projeto.

**Consulta CNPJ (HU-021):** três fontes públicas reais foram testadas com curl em 2026-06-11 — BrasilAPI, minhareceita.org e OpenCNPJ. Todas respondem dados oficiais da RFB sem autenticação. A recomendação é **BrasilAPI** (`https://brasilapi.com.br/api/cnpj/v1/{cnpj}`) como provider inicial: latência consistente de ~0,10s (a melhor das três), payload com código+descrição de natureza jurídica e porte, CNAEs secundários estruturados e erros claros (400/404). O payload da BrasilAPI é idêntico ao do minhareceita.org (ela o usa como fonte) — a URL parametrizada permite alternar entre os dois **sem nenhuma mudança de código**, e o minhareceita ainda oferece self-host futuro (Docker, dados abertos RFB). O contrato `CnpjLookup` (interface + DTO) isola o call site; o convênio oficial RFB (Fase 13) substitui o binding sem tocar telas/controllers.

**REDESIM (HU-022):** não existe XSD/JSON público de fácil acesso do integrador — o Manual de Integração nacional (protocolo versionado, serviços WS01/WS02/WS15/WS29) circula apenas entre órgãos conveniados, e na Bahia o integrador estadual é o **REGIN (JUCEB)**, que já se comunica com a SEDUR por webservice (TVL). O payload de referência foi definido a partir dos campos documentados da consulta prévia de viabilidade REDESIM (protocolo, evento, empresa, endereço, atividades) e está **explicitamente marcado como estrutura a validar com a SEDUR** — o contrato `RedesimImportService` + comando `redesim:importar {arquivo}` isolam o ajuste fino da Fase 13.

**Achado crítico de atualidade:** a partir de **julho/2026** (mês seguinte ao planejamento desta fase) a RFB começa a emitir **CNPJ alfanumérico** (IN RFB 2.229/2024) para novas inscrições — exatamente o público do SILE. A Rule `ValidCnpj` DEVE nascer com o algoritmo módulo 11 sobre ASCII-48, que cobre os formatos numérico e alfanumérico de uma vez. Coluna `cnpj` é string(14), nunca tipo numérico.

**Recomendação primária:** implementar em 4 blocos — (1) schema+models+factories, (2) contrato CnpjLookup com provider BrasilAPI real + toggle, (3) RedesimImportService + comando artisan, (4) telas do portal (lista + página de cadastro + página de detalhe) — com TDD estrito por HU (8 HUs × CA-01..04).

## Standard Stack

### Núcleo (tudo já instalado — zero dependência nova)

| Item | Versão | Papel na fase | Por quê |
|---|---|---|---|
| `laravel/framework` (HTTP client) | ^13.8 | Provider CNPJ (`Http::retry/timeout/throw/fake`) | Nativo, testável com `Http::fake()`, confirmado na doc 13.x |
| `spatie/laravel-activitylog` via `HasAuditoria` + `AuditService` | ^5.0 | Auditoria de models e ações explícitas | Infraestrutura travada do projeto (RN-002) |
| `spatie/laravel-permission` | ^8.0 | Sem permissão nova — portal usa auth+verified+lgpd | Cidadão acessa as próprias empresas; CA-04 é policy |
| Inertia v3 + React 19 | ^3.1 / 19 | Telas do portal | Padrões 2.1/2.2 + componentes da Fase 2.4 |
| PHPUnit | ^12.5 | Feature tests por HU | Padrão do projeto (TDD estrito) |

**Instalação:** nenhuma.

### Decisão 1 — Provider público de CNPJ (HU-021), com evidência de chamadas reais

Testes executados em 2026-06-11 com curl (CNPJs: Banco do Brasil `00000000000191`, Petrobras `33000167000101`, Magazine Luiza `47960950000121`, Itaú `60701190000104`):

| Critério | BrasilAPI | minhareceita.org | OpenCNPJ |
|---|---|---|---|
| Endpoint | `brasilapi.com.br/api/cnpj/v1/{cnpj}` | `minhareceita.org/{cnpj}` | `api.opencnpj.org/{cnpj}` |
| Latência medida (3 chamadas) | **0,10s / 0,10s / 0,10s** | 0,38s / 2,11s / 0,57s | 0,85s / 0,14s / 1,19s |
| Autenticação | nenhuma | nenhuma | nenhuma |
| Rate limit | não documentado (Cloudflare; fair-use) | não documentado | 50 req/s por IP (documentado) |
| Termos | open source comunitária, uso comercial livre, sem SLA | open source (dados abertos RFB), sem SLA, **self-host Docker** | open source, gratuita inclusive comercial, recomenda cache 24h |
| Payload | razão social, fantasia, natureza jurídica (código+texto), porte (código+texto), `cnae_fiscal`+descrição, `cnaes_secundarios[]` (código+descrição), endereço completo, situação cadastral, telefone, e-mail | **idêntico ao da BrasilAPI** (é a fonte dela) | formato próprio (campo `cnaes[]` com `is_principal`, telefones estruturados, capital social, Simples/MEI) |
| Erro CNPJ inválido | 400 `{"message","type","name"}` | 400 `{"message"}` | 400 `{"error"}` |
| CNPJ não encontrado | 404 | 404 | 404 |

Resposta real registrada (BrasilAPI, Banco do Brasil — campos relevantes):

```json
{
  "cnpj": "00000000000191",
  "razao_social": "BANCO DO BRASIL SA",
  "nome_fantasia": "DIRECAO GERAL",
  "natureza_juridica": "Sociedade de Economia Mista",
  "codigo_natureza_juridica": 2038,
  "porte": "DEMAIS",
  "codigo_porte": 5,
  "cnae_fiscal": 6422100,
  "cnae_fiscal_descricao": "Bancos múltiplos, com carteira comercial",
  "cnaes_secundarios": [{ "codigo": 6499999, "descricao": "Outras atividades de serviços financeiros não especificadas anteriormente" }],
  "logradouro": "SAUN QUADRA 5 BLOCO B TORRE I, II, III",
  "numero": "SN",
  "bairro": "ASA NORTE",
  "municipio": "BRASILIA",
  "uf": "DF",
  "cep": "70040912",
  "ddd_telefone_1": "6134939002",
  "descricao_situacao_cadastral": "ATIVA",
  "data_inicio_atividade": "1966-08-01"
}
```

**Recomendação: BrasilAPI como provider inicial.** Motivos: latência ~4–10x melhor (CDN/cache), payload com código+descrição prontos para o formulário, comunidade ampla. Como o formato é o mesmo do minhareceita.org, o parâmetro `integrations.cnpj_lookup.base_url` alterna entre `https://brasilapi.com.br/api/cnpj/v1` e `https://minhareceita.org` **sem deploy e sem código novo** — resiliência real (se um cair, o admin troca a URL). O minhareceita self-hosted é o plano B institucional (infra própria da SEDUR) e o convênio RFB (HU-105, Fase 13) é o substituto definitivo via novo binding da interface.

**Arquitetura do lookup (prescritivo):**

- Interface `App\Services\Cnpj\CnpjLookup` com um método: `lookup(string $cnpj): CnpjData` (lança `CnpjLookupException` em indisponibilidade; retorna `null`/exceção tipada `CnpjNotFoundException` em 404 — decidir no plano por exceções tipadas, mais expressivo nos testes).
- DTO `App\Services\Cnpj\CnpjData` (readonly, com `fromBrasilApi(array $payload)`): `cnpj`, `legalName`, `tradeName`, `legalNatureCode`, `legalNature`, `sizeCode`, `size`, `primaryCnaeCode`, `secondaryCnaeCodes[]`, `street`, `number`, `complement`, `neighborhood`, `city`, `state`, `zipCode`, `phone`, `email`, `registrationStatus`.
- Implementação `BrasilApiCnpjLookup` usando `Http::timeout(...)->retry(2, 200, throw: false)->get("{$baseUrl}/{$cnpj}")`.
- Binding no `AppServiceProvider`: `$this->app->bind(CnpjLookup::class, BrasilApiCnpjLookup::class)` — a Fase 13 troca o binding.
- **Toggle** `features.cnpj_lookup` (registry + `config/sile.php`, default `true` — provider validado): OFF ⇒ botão "Buscar CNPJ" desabilitado com aviso "consulta automática desativada — preencha manualmente" (degradação comunicada, nunca falha silenciosa).
- **Parâmetros novos no ParameterSeeder:** `features.cnpj_lookup` (boolean, grupo `features`), `integrations.cnpj_lookup.base_url` (string, grupo `integracoes`, `validation_rules: [required, url]`, `requires_connection_test: true` — botão real de teste é contrato da Fase 13), `ui.companies.per_page` (integer, grupo `ui`). **Constantes técnicas em `config/sile.php`, NUNCA no registry:** timeout (8s), retries (2), TTL do cache de consulta (86400s) — seguem o precedente `sile.parameters.cache_ttl` ([02-02]) e a regra "timeouts internos não viram parâmetro".
- **Cache de consulta:** `Cache::remember("sile.cnpj_lookup.{$cnpj}", config('sile.integrations.cnpj_lookup.cache_ttl'), ...)` no service — dados RFB atualizam mensalmente; 24h evita re-consulta no mesmo dia e protege contra rate limit/indisponibilidade. Cache apenas de SUCESSO (falha nunca é cacheada).
- **FA-03 (indisponibilidade):** `ConnectionException`/5xx ⇒ `AuditService->log('empresas', 'consulta-cnpj', ..., result: 'falha')` + resposta JSON com mensagem pt-BR; o formulário continua editável (cadastro manual segue possível). CA-02: toda consulta (sucesso e falha) auditada com CNPJ consultado e provider.
- **Endpoint:** `POST /portal/empresas/consultar-cnpj` (name `portal.empresas.consultar-cnpj`), FormRequest com `ValidCnpj`, retorna JSON do DTO. No React, chamar com o `useHttp` do Inertia v3 (requests standalone sem visita).

### Decisão 2 — Estrutura de referência REDESIM (HU-022)

Fontes verificadas: Resolução CGSIM nº 61/2020 (papéis e conteúdo informacional da pesquisa prévia — município recebe dados e responde com orientações/requisitos/motivos), Manual de Integração nacional (protocolo versionado 2.2.x, serviços WS01/WS02/WS15/WS29 — **não público**, circula entre conveniados), campos da consulta de viabilidade REDESIM documentados publicamente (protocolo, data/hora, eventos, nome empresarial, estabelecimento, tipo de unidade, forma de atuação, área, inscrição imobiliária, endereço, atividades econômicas principal/secundárias, objeto social, horário de funcionamento) e confirmação de que **na Bahia o integrador estadual é o REGIN (JUCEB)**, que já integra o Pedido de Viabilidade com o TVL da SEDUR por webservice.

**Payload JSON de referência** (⚠️ ESTRUTURA DE REFERÊNCIA A VALIDAR COM A SEDUR — o contrato isola; o ajuste fino vem com a documentação oficial REGIN/REDESIM na Fase 13):

```json
{
  "protocolo": "BAP2612345678",
  "data_solicitacao": "2026-06-10T14:30:00-03:00",
  "evento": { "codigo": "101", "descricao": "Inscrição de primeiro estabelecimento" },
  "empresa": {
    "cnpj": "00000000000191",
    "razao_social": "BANCO DO BRASIL SA",
    "nome_fantasia": "DIRECAO GERAL",
    "natureza_juridica": { "codigo": "2038", "descricao": "Sociedade de Economia Mista" },
    "porte": { "codigo": "05", "descricao": "DEMAIS" }
  },
  "endereco": {
    "cep": "40020000", "logradouro": "Rua Chile", "numero": "10", "complemento": "",
    "bairro": "Centro", "municipio": "Salvador", "uf": "BA"
  },
  "atividades": { "principal": "6422100", "secundarias": ["6499999"] },
  "contato": { "email": "empresa@example.com", "telefone": "7133330000" }
}
```

Campos como área ocupada, inscrição imobiliária e forma de atuação pertencem à **solicitação de viabilidade** (Fase 8) — se presentes no payload, são ignorados nesta fase (documentar no service).

**`RedesimImportService` (padrão `CnaeImportService`):**
- Entrada: caminho de arquivo JSON (objeto único ou array de objetos).
- Validação por item: `cnpj` válido (`ValidCnpj`), `razao_social` obrigatória, `protocolo` obrigatório, CNAE principal existente na tabela oficial. Item inválido ⇒ rejeitado com motivo no relatório (nunca inserção parcial silenciosa). CNAE **inativo** ⇒ importa com aviso no relatório (dado vindo da Junta é fato consumado; a restrição "somente ativos" vale para seleção manual nas HU-025/026).
- **Upsert por CNPJ:** existe ⇒ atualiza dados + sincroniza CNAEs + grava `redesim_protocol`/`redesim_synced_at`; não existe ⇒ cria com `source = redesim`. `source` é a origem de CRIAÇÃO (imutável); atualização via REDESIM marca `redesim_synced_at`.
- Import NÃO cria vínculo usuário-empresa (não há usuário do portal no payload) — a associação com o solicitante chega com o transporte real (Fase 13). Empresa importada sem vínculo não aparece em "Minhas empresas" de ninguém (correto: HU-027 lista empresas DO usuário).
- Relatório: `{lidos, importados, atualizados, rejeitados[], avisos[]}` + auditoria explícita `AuditService->log('empresas', 'importacao-redesim', ..., rulesVersion: 'redesim-import-v1')` (causer null = serviço/sistema; o enriquecimento de origem já é central).
- **Comando** `redesim:importar {arquivo}` (`App\Console\Commands\ImportRedesimCommand`, auto-descoberto): valida existência/JSON, chama o service, imprime relatório em pt-BR, exit code 1 se todos os itens rejeitados. Arquivo de exemplo REAL versionado em `database/data/redesim-exemplo.json` (CNPJs públicos) para homologação manual; fixtures de teste em `tests/Fixtures/redesim/`.
- UI: badge de origem na lista/detalhe ("Cadastro manual" × "REDESIM") — transparência da fonte, sem tela fingindo "receber da REDESIM".

### Decisão 3 — Schema

Produção é PostgreSQL (`.env DB_CONNECTION=pgsql`); testes são SQLite `:memory:` (phpunit.xml). Precedente do projeto ([01-07] e comentário na migration de `procurations`): unicidade condicional fica NA APLICAÇÃO, sem índice único parcial.

**`companies`**

| Coluna | Tipo | Regras |
|---|---|---|
| `id` | bigint PK | — |
| `cnpj` | string(14) **unique** | normalizado (sem máscara, uppercase) — string SEMPRE: CNPJ alfanumérico chega em julho/2026 |
| `legal_name` | string | razão social, obrigatória |
| `trade_name` | string nullable | nome fantasia |
| `legal_nature_code` | string(4) nullable | ex.: `2062` |
| `legal_nature` | string nullable | descrição |
| `size_code` | string(2) nullable | código do porte |
| `size` | string nullable | descrição do porte (ME, EPP, DEMAIS) |
| `street`, `number`, `complement`, `neighborhood`, `city`, `state`(2), `zip_code`(8) | strings nullable | endereço como texto nesta fase (geocodificação é Fase 4) — achatado na própria tabela |
| `email` | string nullable | contato |
| `phone` | string nullable | contato (dígitos) |
| `source` | string | enum PHP `CompanySource: Manual\|Redesim` (valores `manual`/`redesim`), default `manual` |
| `redesim_protocol` | string nullable | protocolo da última importação |
| `redesim_synced_at` | timestamp nullable | última atualização via REDESIM |
| `timestamps` | — | — |

**`company_user`** (vínculos — modelo Eloquent próprio `CompanyUser`, tem ciclo de vida)

| Coluna | Tipo | Regras |
|---|---|---|
| `id` | bigint PK | — |
| `company_id` | FK `constrained()->cascadeOnDelete()` | — |
| `user_id` | FK `constrained()->cascadeOnDelete()` | — |
| `role` | string | enum PHP `CompanyLinkRole: Responsavel\|Procurador` (valores `responsavel`/`procurador`) — papel NO CONTEXTO da empresa, não papel spatie |
| `started_at` | timestamp | — |
| `ended_at` | timestamp nullable | encerramento (nunca delete físico — histórico preservado, HU-028) |
| `ended_reason` | string nullable | motivo opcional |
| `timestamps` + `index(company_id, user_id)` | — | unicidade "um vínculo ativo por par" NA APLICAÇÃO (FormRequest::after / service), padrão procurations |

**`company_cnae`** (pivot simples, SEM modelo dedicado — escrita só pelo service em transação)

| Coluna | Tipo | Regras |
|---|---|---|
| `id`, `company_id` FK cascade, `cnae_id` FK | — | `cnae_id` com `constrained()->restrictOnDelete()` — banco impede excluir CNAE vinculado |
| `is_primary` | boolean default false | invariante "exatamente um principal por empresa" garantida NA APLICAÇÃO (service em transação: demove o antigo, promove o novo). Índice único parcial é viável (pgsql E sqlite suportam) mas o precedente do projeto é aplicação — manter consistência |
| `unique(company_id, cnae_id)` + timestamps | — | impede duplicar CNAE na empresa (cobre "secundário não duplica principal nem entre si" junto com a validação) |

**Pendência herdada da Fase 2 a resolver AQUI:** `CnaeController::destroy` comenta "o bloqueio por vínculo empresarial entra com o cadastro de empresas (Fase 3)" — adicionar verificação amigável (`exists` em company_cnae ⇒ erro pt-BR "CNAE vinculado a empresas não pode ser excluído") + o `restrictOnDelete` como defesa no banco. Teste novo no CnaeCrudTest.

**Factories:** `CompanyFactory` (CNPJ válido gerado pelo algoritmo, states `fromRedesim()`, `withPrimaryCnae()` via afterCreating), `CompanyUserFactory` (states `responsavel()`, `procurador()`, `ended()`). Vínculo CNAE nos testes via `attach`/state.

### Alternativas consideradas

| Em vez de | Poderia usar | Trade-off |
|---|---|---|
| BrasilAPI | minhareceita.org direto | mesmo payload, latência pior hoje; vence quando self-hosted pela SEDUR (troca por parâmetro, sem código) |
| BrasilAPI | OpenCNPJ | payload diferente (novo adapter); 50 req/s documentado é bom, mas exigiria segunda implementação do contrato — fazer só se a BrasilAPI degradar |
| Invariante is_primary na aplicação | índice único parcial (`CREATE UNIQUE INDEX ... WHERE is_primary`) | funciona em pgsql+sqlite, mas quebra o precedente [01-07] e complica o swap (demote→promote já resolve em transação) |
| Página dedicada para form de empresa | Modal 700px (padrão 2.2) | form tem ~12 campos + lookup + endereço; modal prejudica usabilidade — ver Architecture Patterns |

## Architecture Patterns

### Estrutura de arquivos (prescritiva)

```
app/
├── Console/Commands/ImportRedesimCommand.php      # redesim:importar {arquivo}
├── Enums/CompanySource.php                        # Manual | Redesim
├── Enums/CompanyLinkRole.php                      # Responsavel | Procurador
├── Http/Controllers/Portal/
│   ├── CompanyController.php                      # index/create/store/show/update
│   ├── CompanyCnaeController.php                  # updatePrimary / updateSecondaries (HU-025/026)
│   ├── CompanyLinkController.php                  # destroy (encerrar vínculo, HU-028)
│   └── CnpjLookupController.php                   # consulta CNPJ (HU-021, JSON)
├── Http/Requests/Portal/                          # Store/UpdateCompanyRequest, UpdatePrimaryCnaeRequest,
│   │                                              # UpdateSecondaryCnaesRequest, EndCompanyLinkRequest, LookupCnpjRequest
├── Models/{Company,CompanyUser}.php               # HasAuditoria nos dois
├── Policies/CompanyPolicy.php                     # view/update/manageCnaes/endLink
├── Rules/ValidCnpj.php                            # módulo 11 ASCII-48 (numérico + alfanumérico)
└── Services/
    ├── Cnpj/{CnpjLookup,CnpjData,BrasilApiCnpjLookup,CnpjLookupException,CnpjNotFoundException}.php
    ├── CompanyCnaeService.php                     # setPrimary/syncSecondaries em transação + auditoria explícita
    └── RedesimImportService.php

database/
├── data/redesim-exemplo.json                      # payload real de homologação manual
├── factories/{CompanyFactory,CompanyUserFactory}.php
├── migrations/...create_companies/company_user/company_cnae...
└── seeders/ParameterSeeder.php                    # +3 chaves (cnpj_lookup, base_url, ui.companies.per_page)

resources/js/pages/portal/empresas/
├── index.tsx                                      # Minhas empresas (lista)
├── cadastrar.tsx                                  # página dedicada de cadastro
└── detalhe.tsx                                    # dados + CNAEs + vínculos

routes/portal.php                                  # bloco empresas dentro do grupo lgpd+ResolveRepresentation
```

### Rotas (mapa 2.3 — prefixo `/portal`, names `portal.*`, dentro de `lgpd.accepted` + `ResolveRepresentation`)

```php
Route::get('empresas', [CompanyController::class, 'index'])->name('empresas.index');
Route::get('empresas/cadastrar', [CompanyController::class, 'create'])->name('empresas.create');
Route::post('empresas', [CompanyController::class, 'store'])->name('empresas.store');
Route::post('empresas/consultar-cnpj', CnpjLookupController::class)->name('empresas.consultar-cnpj');
Route::get('empresas/{company}', [CompanyController::class, 'show'])->name('empresas.show');
Route::put('empresas/{company}', [CompanyController::class, 'update'])->name('empresas.update');
Route::put('empresas/{company}/cnae-principal', [CompanyCnaeController::class, 'updatePrimary'])->name('empresas.cnae-principal');
Route::put('empresas/{company}/cnaes-secundarios', [CompanyCnaeController::class, 'updateSecondaries'])->name('empresas.cnaes-secundarios');
Route::delete('empresas/{company}/vinculo', [CompanyLinkController::class, 'destroy'])->name('empresas.vinculo.destroy');
```

Atenção à ordem: `empresas/cadastrar` e `empresas/consultar-cnpj` ANTES de `empresas/{company}`. Busca de CNAEs para os selects usa endpoint JSON dedicado `GET /portal/cnaes` (name `portal.cnaes.search`) retornando só ativos (`id`, `formatted_code`, `description`), com o padrão de busca do `CnaeController` (branch de dígitos só quando o termo tem dígitos — [02-04]).

### Padrão 1 — Autorização com representação (HU-027/CA-04)

O acesso é sempre do "usuário efetivo": o próprio, ou o REPRESENTADO quando em representação ativa ([01-07]). Em representação, o procurador enxerga as empresas do representado — não as próprias misturadas.

```php
// CompanyPolicy — o scoped CurrentRepresentation já foi resolvido pelo middleware
private function effectiveUser(User $user): User
{
    return app(CurrentRepresentation::class)->grantor() ?? $user;
}

public function update(User $user, Company $company): bool
{
    return $company->links()
        ->where('user_id', $this->effectiveUser($user)->id)
        ->whereNull('ended_at')
        ->exists();
}
```

- Listagem (`index`): empresas com vínculo (ativo OU encerrado — situação do vínculo é coluna) do usuário efetivo; eager load `primaryCnae` para evitar N+1.
- `Gate::authorize('update', $company)` nos controllers ⇒ 403 já auditado globalmente pelo render callback do `bootstrap/app.php` (CA-04 sem código novo).
- Registrar a policy: auto-discovery cobre `App\Models\Company` ↔ `App\Policies\CompanyPolicy`.

### Padrão 2 — Vínculo na criação e encerramento (HU-023/HU-028)

- `store`: criar empresa + vínculo `responsavel` (`started_at: now()`) **na mesma transação**, em nome do usuário efetivo (representação cria a empresa PARA o representado). CNPJ duplicado ⇒ mensagem clara via `unique` no FormRequest ("Já existe empresa cadastrada com este CNPJ.") — CA-03.
- `destroy` do vínculo: usuário encerra o PRÓPRIO vínculo (`ended_at: now()`, `ended_reason` opcional). Regra HU-028 CA-03 no `EndCompanyLinkRequest::after()` (padrão anti-lockout [02-05]): bloquear se for o último vínculo ATIVO com role `responsavel` — "A empresa não pode ficar sem responsável ativo." Nunca delete físico.
- Auditoria de vínculos: `CompanyUser` com `HasAuditoria` (created/updated entram no diff) + log explícito `AuditService` no encerramento (event `encerramento-vinculo`, properties com empresa/motivo).

### Padrão 3 — CNAEs com auditoria explícita (HU-025/HU-026)

Relações: `Company::belongsToMany(Cnae::class)->withPivot('is_primary')->withTimestamps()`; accessor/relação `primaryCnae()`. Escrita SÓ pelo `CompanyCnaeService`, em transação:

```php
public function setPrimary(Company $company, Cnae $cnae): void
{
    DB::transaction(function () use ($company, $cnae) {
        $previous = $company->primaryCnae()->first();
        $company->cnaes()->wherePivot('is_primary', true)->newPivotQuery()->update(['is_primary' => false]);
        $company->cnaes()->syncWithoutDetaching([$cnae->id => ['is_primary' => true]]);
        $this->audit->log('empresas', 'cnae-principal', "CNAE principal definido", [
            'empresa_id' => $company->id,
            'cnae_anterior' => $previous?->code,
            'cnae_novo' => $cnae->code,
        ], $company);
    });
}
```

- Validações nos FormRequests: CNAE existe E está ativo (`Rule::exists('cnaes', 'id')->where('active', true)`); secundários: array distinto, sem conter o principal atual/proposto.
- Auditoria com antes/depois (padrão [02-06] `permissoes_antes/depois`) — relações NÃO entram no diff do `HasAuditoria`, por isso o log explícito. Relevante para o motor de regras (Fases 5/6).

### Padrão 4 — Telas (padrões 2.1/2.2 + Fase 2.4 em execução)

A Fase 2.4 (em execução paralela) entrega `PageHeader`, `Card`, `DataTable` tipada, `TableAction`, `use-server-table`, `KpiCard` — o padrão de listagem das Fases 3–15. As telas da Fase 3 DEVEM usá-los se já estiverem no main quando o plano executar; senão, padrões 2.1/2.2 (Table + card `rounded-2xl` + Pagination/EmptyState/ConfirmDialog) com migração trivial depois.

- **`portal/empresas/index.tsx` ("Minhas empresas"):** datatable em card único; colunas: Empresa (razão social + CNPJ formatado), CNAE principal (código formatado + descrição truncada), Origem (Badge "Cadastro manual"/"REDESIM"), Vínculo (Badge Ativo/Encerrado + papel), Ações (ver/editar). Ação primária no header: "Cadastrar empresa". EmptyState distinguindo busca vazia × lista vazia. Paginação `ui.companies.per_page`. Banner de representação já vem do `portal-layout`.
- **`cadastrar.tsx` — PÁGINA dedicada, não modal (recomendação de usabilidade):** o form tem ~12 campos + endereço + botão de lookup — modal 700px obrigaria scroll interno e o lookup re-renderiza muitos campos; página permite seções claras (Identificação → Endereço → Contato), espaço para o aviso do toggle e foco. O padrão 2.2 "criar/editar em Modal" continua valendo para forms simples (o próprio 2.2 prevê exceção por usabilidade, como a edição inline de parâmetros). Campo CNPJ com máscara `00.000.000/0000-00` + botão "Buscar CNPJ" (`useHttp` → preenche campos editáveis; erro mostra Alert e mantém preenchimento manual). Toggle OFF ⇒ botão `disabled` + hint "Consulta automática indisponível".
- **`detalhe.tsx`:** seções em cards — Dados (form de edição; CNPJ readonly — imutável, padrão CPF [01-08]), CNAEs (principal com troca via busca server-side + secundários multi com busca; ConfirmDialog `warning` na troca de principal), Vínculos (tabela com papel/início/fim + botão "Encerrar meu vínculo" via ConfirmDialog `danger` com campo de motivo opcional).
- **Sidebar:** `portal-layout.tsx`, grupo "Serviços" ganha `{ name: 'Minhas empresas', href: '/portal/empresas' }`.
- **Dashboard do cidadão:** `CompanyController` deve expor contagem reutilizável (ex.: método estático/query `Company::countForUser`) — o painel consolidado (lacuna registrada no STATE) consumirá na evolução.

### Anti-patterns (proibidos nesta fase)

- **Botão "Buscar CNPJ" com resultado simulado/fixture em runtime** — viola "sem fachada"; toggle OFF = botão desabilitado comunicado.
- **Tela "receber da REDESIM"** — o transporte é Fase 13; aqui existem service + comando + testes + badge de origem.
- **`sync()` direto de CNAEs no controller** — escreve sem auditoria e pode violar a invariante do principal; sempre via `CompanyCnaeService`.
- **Armazenar CNPJ com máscara ou como número** — 14 chars normalizados uppercase; número quebra com alfanumérico (julho/2026) e perde zeros à esquerda.
- **Validação `unique` de CNPJ sem normalizar antes** — usar `prepareForValidation()` no FormRequest (strip de máscara + uppercase) antes da rule.

## Don't Hand-Roll

| Problema | Não construir | Usar | Por quê |
|---|---|---|---|
| HTTP do provider CNPJ | Guzzle direto/cURL manual | `Http::` facade (timeout, retry, fake) | testável com `Http::fake()`/`preventStrayRequests()`, confirmado doc 13.x |
| Fonte de dados CNPJ | scraper da RFB/Sintegra | BrasilAPI/minhareceita (dados abertos RFB) | dataset oficial publicado; scraping viola termos e quebra |
| Auditoria | tabela/log próprio | `HasAuditoria` + `AuditService` | infraestrutura travada (RN-002), enriquecimento central de origem/representação |
| Invariantes de formulário | ifs no controller | FormRequest::after (padrão [02-05]/[02-06]) | precedente do projeto para regras como "último responsável" |
| Busca/paginação de listas | componente novo | padrão `CnaeController@index` + componentes 2.2/2.4 | já parametrizado e testado |
| Validação de CNPJ | regex simples / pacote externo | Rule própria `ValidCnpj` (módulo 11 ASCII-48) | padrão ValidCpf do projeto; pacotes populares ainda não cobrem alfanumérico de forma garantida — algoritmo oficial RFB é trivial |
| Estado "em nome de" | sessão lida ad hoc | `CurrentRepresentation` scoped | resolvido por middleware a cada request, revogação imediata |

## Common Pitfalls

### Pitfall 1 — ValidCnpj só numérica (quebra em julho/2026)
**O que acontece:** novas inscrições RFB a partir de julho/2026 podem ter CNPJ alfanumérico (12 alfanuméricos + 2 DV numéricos); validação `\d{14}` rejeitaria empresa recém-aberta — exatamente o público do SILE.
**Prevenção:** normalizar com `preg_replace('/[^A-Z0-9]/', '', strtoupper($value))`; validar `/^[A-Z\d]{12}\d{2}$/`; DV por módulo 11 com valor `ord($char) - 48` (cobre dígitos e letras de uma vez — fórmula oficial IN RFB 2.229/2024). Rejeitar repetição uniforme (`/^(.)\1{13}$/`).
**Sinal de alerta:** teste com CNPJ alfanumérico de exemplo oficial (`12.ABC.345/01DE-35`) deve passar.

### Pitfall 2 — Form do Inertia v3 sobrescreve onSubmit ([02-04])
O handler interno do `<Form>` é definido após o spread das props: confirmações de submissão vão no `onClick` do botão `type=submit` com `preventDefault`, não no `onSubmit`.

### Pitfall 3 — Asserções de validação pt-BR
Mensagem `required` do laravel-lang é "É obrigatória a indicação..." — asserts usam `'obrigatória'` ([01-04]). Testes de rotas do portal precisam de `RolesAndPermissionsSeeder` + `User::factory()->cidadao()->withAcceptedLgpdTerm()`.

### Pitfall 4 — Teste de lookup fazendo chamada real
`Http::fake()` em TODOS os testes do lookup + `Http::preventStrayRequests()` para garantir zero rede na suíte. Cenários: sucesso (fixture JSON real da BrasilAPI), 404 (`CnpjNotFoundException` ⇒ mensagem "CNPJ não encontrado"), 400, `Http::failedConnection()` (FA-03: auditoria result falha + cadastro manual segue), toggle OFF (endpoint responde 4xx comunicado SEM request — `Http::assertNothingSent()`).

### Pitfall 5 — Representação na policy sem teste dos dois lados
Cobrir: procurador SEM representação ativa não vê empresas do outorgante; COM representação vê e edita; representação encerrada (revogação) perde acesso na request seguinte (middleware revalida — [01-07]). Lembrar: `ResolveRepresentation` reseta Context/scoped quando inválida.

### Pitfall 6 — Cache de consulta CNPJ cacheando falha
`Cache::remember` com closure que lança exceção não grava — manter a exceção fluindo (só sucesso é cacheado). Atenção também ao cache em testes: `Http::fake` + cache `array` por padrão no phpunit; limpar chave entre cenários do mesmo teste se reconsultar o mesmo CNPJ esperando novo request.

### Pitfall 7 — Swap de CNAE principal violando a invariante
Promover o novo antes de demover o antigo deixaria dois `is_primary` num instante (e quebraria índice parcial, se existisse). Ordem: demote → promote, em `DB::transaction`. Secundários: `syncWithoutDetaching` não remove — para o conjunto exato dos secundários usar `sync` calculado preservando a linha do principal (intenção do usuário = conjunto marcado, padrão [02-06] syncPermissions).

### Pitfall 8 — Import REDESIM tocando `source` no update
Upsert de empresa existente NÃO reescreve `source` (origem de criação é imutável; quem foi `manual` continua `manual` com `redesim_synced_at` marcado) — espelha o precedente "upsert não toca active" do CnaeImportService ([02-04]).

### Pitfall 9 — N+1 na listagem de empresas
Cada linha mostra CNAE principal + situação do vínculo: eager load com constraint (`with(['cnaes' => fn ($q) => $q->wherePivot('is_primary', true), 'links' => fn ($q) => $q->where('user_id', $effectiveId)])`) ou subqueries; nunca acessar relação dentro do map sem eager load.

## Code Examples

### ValidCnpj (módulo 11 ASCII-48 — numérico e alfanumérico, fórmula oficial RFB)

```php
// Fonte: IN RFB 2.229/2024 + Anexo XV IN RFB 2.119/2022 (gov.br)
class ValidCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $value));

        if (preg_match('/^[A-Z\d]{12}\d{2}$/', $cnpj) !== 1 || preg_match('/^(.)\1{13}$/', $cnpj)) {
            $fail('O campo :attribute não é um CNPJ válido.');

            return;
        }

        $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        foreach ([12, 13] as $position) {
            $sum = 0;
            $w = array_slice($weights, 12 - $position);

            for ($i = 0; $i < $position; $i++) {
                $sum += (ord($cnpj[$i]) - 48) * $w[$i];
            }

            $digit = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);

            if ((int) $cnpj[$position] !== $digit) {
                $fail('O campo :attribute não é um CNPJ válido.');

                return;
            }
        }
    }
}
// Pesos do 2º DV: prefixar 6 ao array (13 posições) — array_slice acima resolve.
// Verificar na implementação com os casos oficiais: 00000000000191 (válido) e 12ABC34501DE35 (válido, exemplo RFB).
```

### Provider BrasilAPI com resiliência (doc oficial 13.x)

```php
// Fonte: laravel.com/docs/13.x/http-client (retry/timeout/connectTimeout/throw)
class BrasilApiCnpjLookup implements CnpjLookup
{
    public function lookup(string $cnpj): CnpjData
    {
        $baseUrl = rtrim((string) Settings::get(
            'integrations.cnpj_lookup.base_url',
            config('sile.integrations.cnpj_lookup.base_url'),
        ), '/');

        return Cache::remember(
            "sile.cnpj_lookup.{$cnpj}",
            (int) config('sile.integrations.cnpj_lookup.cache_ttl', 86400),
            function () use ($baseUrl, $cnpj) {
                $response = Http::timeout((int) config('sile.integrations.cnpj_lookup.timeout', 8))
                    ->connectTimeout(3)
                    ->retry(2, 200, throw: false)
                    ->acceptJson()
                    ->get("{$baseUrl}/{$cnpj}");

                if ($response->status() === 404) {
                    throw new CnpjNotFoundException($cnpj);
                }

                if ($response->failed()) {
                    throw new CnpjLookupException($cnpj, $response->status());
                }

                return CnpjData::fromBrasilApi($response->json());
            },
        );
    }
}
```

### Testes do lookup (Http fake — doc oficial 13.x)

```php
// Fonte: laravel.com/docs/13.x/http-client#testing
Http::preventStrayRequests();
Http::fake(['*/00000000000191' => Http::response($this->brasilApiFixture(), 200)]);
// indisponibilidade (FA-03):
Http::fake(['*' => Http::failedConnection()]);
// nenhum request quando toggle off:
Http::fake();
// ... posta consultar-cnpj com features.cnpj_lookup = 0 ...
Http::assertNothingSent();
```

### Front — Buscar CNPJ com useHttp (Inertia v3)

```tsx
// Inertia v3: requests standalone sem visita (AGENTS.md). Confirmar assinatura
// exata na skill inertia-react-development ao implementar.
const { post, processing } = useHttp();

async function buscarCnpj() {
    const data = await post('/portal/empresas/consultar-cnpj', { cnpj });
    preencherFormulario(data); // só campos editáveis; usuário revisa antes de salvar
}
```

## Validation Architecture

> Requisito do projeto: TDD estrito; cada CA BDD vira feature test PHPUnit nomeado em pt-BR; nenhuma HU concluída sem CAs passando.

### Grupos de teste (tests/Feature/Companies/)

| Arquivo | HU | Cobre |
|---|---|---|
| `CnpjLookupTest.php` | HU-021 | CA-01 consulta real fake com payload BrasilAPI; CA-02 auditoria de consulta (sucesso E falha); CA-03 CNPJ inválido/404 bloqueado com mensagem; CA-04 guest/sem LGPD bloqueado+auditado; FA-03 indisponibilidade registra falha e cadastro manual segue; toggle OFF desabilita sem request (`assertNothingSent`) |
| `RedesimImportTest.php` | HU-022 | CA-01 cria empresa+CNAEs+endereço de payload válido; CA-02 relatório auditado (activity `importacao-redesim` com rules_version); CA-03 item sem CNPJ válido/CNAE inexistente rejeitado com motivo (sem inserção parcial); CA-04 (n/a usuário — comando de sistema; cobrir que rota pública NÃO existe); upsert por CNPJ não duplica nem reescreve `source`; comando `redesim:importar` exit 0 + relatório, arquivo inexistente exit 1 |
| `CompanyRegistrationTest.php` | HU-023 | CA-01 cadastro cria empresa + vínculo responsável ativo (transação); CA-02 activity created da Company; CA-03 CNPJ duplicado bloqueado com mensagem clara; CA-04 não autenticado/sem verificação bloqueado; CNPJ inválido rejeitado (ValidCnpj, incl. alfanumérico válido aceito); em representação cria para o representado |
| `CompanyUpdateTest.php` | HU-024 | CA-01 vinculado ativo atualiza dados complementares; CA-02 activity updated com attribute_changes; CA-03 CNPJ imutável (valor enviado ignorado, padrão CPF [01-08]); CA-04 usuário não vinculado recebe 403 + access auditado |
| `PrimaryCnaeTest.php` | HU-025 | CA-01 define principal (exatamente um); CA-02 auditoria explícita com cnae_anterior/novo; CA-03 CNAE inativo/inexistente rejeitado; CA-04 não vinculado 403; troca de principal demove o antigo |
| `SecondaryCnaesTest.php` | HU-026 | CA-01 sincroniza conjunto de secundários; CA-02 auditoria antes/depois; CA-03 duplicado entre si ou contendo o principal rejeitado; CA-04 403; remoção total permitida (zero ou mais) |
| `MyCompaniesTest.php` | HU-027 | CA-01 lista só as empresas do usuário (com CNAE principal e situação do vínculo no payload Inertia); CA-02 (consulta às próprias empresas — sem log obrigatório além do padrão; asserts de props); CA-03 paginação parametrizada `ui.companies.per_page`; CA-04 guest redirect; procurador em representação vê empresas do representado; sem representação não vê |
| `EndCompanyLinkTest.php` | HU-028 | CA-01 encerra próprio vínculo com motivo opcional (`ended_at`, nunca delete); CA-02 auditoria de encerramento; CA-03 último responsável ativo bloqueado com mensagem; CA-04 encerrar vínculo de OUTRO usuário 403; histórico preservado na listagem |

Suplementares: teste no `CnaeCrudTest` para o bloqueio de exclusão de CNAE vinculado (pendência herdada); `ParameterRegistryTest` ganha as 3 chaves novas; teste de rota garantindo grupo `lgpd.accepted`+`ResolveRepresentation`.

### Comandos

```bash
php artisan test --compact tests/Feature/Companies                      # grupo da fase
php artisan test --compact --filter=CnpjLookupTest                      # por HU
php artisan test --compact                                              # suíte completa (regressão 201+)
vendor/bin/pint --dirty --format agent                                  # após alterar PHP
npm run types && npm run build                                          # typecheck + build
```

### Gabarito CA por HU (resumo do mapeamento)

- **CA-01 (execução):** happy path da ação com asserts de banco + resposta (redirect com status pt-BR ou props Inertia).
- **CA-02 (auditoria):** assert em `activity_log` — `log_name`, `event`, `properties` relevantes (e `result`/`rules_version` quando via AuditService).
- **CA-03 (bloqueio por inconsistência):** o invariante da HU (CNPJ duplicado/imutável, CNAE inativo, último responsável, item REDESIM inválido) bloqueado com mensagem pt-BR, sem efeito parcial no banco.
- **CA-04 (segurança):** ator sem direito (guest, não vinculado, sem representação) impedido (302/403) e evento registrado (403 já auditado globalmente — assert em activity `seguranca`/`acesso-negado`).

### Validação manual no browser (roteiro)

1. `composer run dev`; registrar cidadão A (verificar e-mail, aceitar LGPD). Sidebar > Serviços > **Minhas empresas** ⇒ EmptyState.
2. **Cadastrar empresa:** informar `00.000.000/0001-91` → "Buscar CNPJ" ⇒ campos preenchidos com dados REAIS do Banco do Brasil (evidência da integração viva). Ajustar endereço, salvar ⇒ lista mostra badge "Cadastro manual" e vínculo Ativo (Responsável).
3. **CNAEs:** no detalhe, buscar "restaurante", definir principal `5611-2/01`; adicionar 2 secundários; trocar o principal ⇒ conferir `activity_log` (eventos `cnae-principal`/`cnaes-secundarios` com antes/depois).
4. **Representação:** cidadão B outorga procuração ao cidadão A ([HU-008]); A ativa "em nome de B" ⇒ Minhas empresas mostra as empresas de B; A cadastra empresa para B; encerra representação ⇒ volta a ver só as próprias.
5. **Encerrar vínculo:** com A como único responsável ⇒ bloqueio com mensagem; vincular segundo responsável (via banco/factory nesta fase) ⇒ encerramento com motivo funciona e o histórico permanece.
6. **REDESIM:** `php artisan redesim:importar database/data/redesim-exemplo.json` ⇒ relatório no console (importados/atualizados/rejeitados); rodar de novo ⇒ atualizados (idempotente); payload com o CNPJ da empresa do passo 2 ⇒ dados atualizados + `redesim_synced_at` preenchido (badge na UI), `source` continua manual.
7. **Toggle:** na gestão (Parâmetros), desligar `features.cnpj_lookup` ⇒ botão "Buscar CNPJ" desabilitado com aviso; religar ⇒ volta (efeito sem deploy).
8. Dark mode + responsivo nas 3 telas novas; screenshots para o SUMMARY.

## State of the Art

| Antes | Agora | Quando mudou | Impacto |
|---|---|---|---|
| CNPJ exclusivamente numérico | **CNPJ alfanumérico** (raiz+ordem alfanuméricas, 2 DV numéricos, módulo 11 ASCII-48) | produção a partir de **julho/2026** (IN RFB 2.229/2024) | `ValidCnpj` nasce compatível; coluna string; máscara aceita letras; monitorar providers públicos quanto a aceitar letras na URL |
| ReceitaWS como API gratuita padrão | BrasilAPI/minhareceita/OpenCNPJ (dados abertos RFB, sem token) | consolidado 2023+ | sem limite de 3 req/min da ReceitaWS; minhareceita self-hostável |
| `Inertia::lazy()` | `Inertia::optional()`; `useHttp` para requests standalone | Inertia v3 | lookup CNPJ sem visita Inertia completa |
| Guzzle direto | `Http::` com `retry(..., throw: false)`, `failedConnection()`, `preventStrayRequests()` | Laravel 10+ → 13 confirmado | testes de integração herméticos |

**Obsoleto/evitar:** ReceitaWS no plano gratuito (3 req/min); validação de CNPJ por regex numérica; pacotes de validação sem suporte alfanumérico.

## Open Questions

1. **Contrato real REGIN/SEDUR (transporte e payload exatos)**
   - Sabemos: na Bahia o integrador é o REGIN (JUCEB), que já troca viabilidade/TVL com a SEDUR via webservice; o manual nacional (WS01/WS02/...) não é público.
   - Falta: XSD/JSON oficial e credenciais de homologação (pendência já registrada no STATE para a Fase 13).
   - Tratamento: payload de referência marcado "a validar com a SEDUR"; o contrato (service + comando) absorve o ajuste sem retrabalho de domínio.
2. **Viabilidade de CONSTITUIÇÃO sem CNPJ** — na abertura de empresa nova a consulta prévia ocorre antes de existir CNPJ. O upsert por CNPJ (decisão de contexto) cobre alteração/regularização; o caso "sem CNPJ ainda" pertence à solicitação (Fase 8) e ao transporte (Fase 13). Confirmar com a SEDUR se o cadastro empresarial deve aceitar registro provisório sem CNPJ (hoje: não).
3. **Providers públicos × CNPJ alfanumérico** — nenhum CNPJ alfanumérico existe ainda (emissão começa em julho/2026); não foi possível testar se BrasilAPI/minhareceita aceitarão letras no path. Risco baixo (dataset RFB os incluirá), monitorar no primeiro mês; a validação local já estará pronta.
4. **Associação empresa importada ↔ usuário do portal** — payload REDESIM não traz usuário do SILE; empresa importada fica sem vínculo até a Fase 13 definir a associação (CPF do responsável? reivindicação?). Registrar na pauta SEDUR.

## Sources

### Primárias (confiança ALTA)
- Chamadas reais via curl em 2026-06-11: `brasilapi.com.br/api/cnpj/v1/{cnpj}`, `minhareceita.org/{cnpj}`, `api.opencnpj.org/{cnpj}` — payloads, latências e erros registrados neste documento.
- Laravel 13 HTTP Client — `laravel.com/docs/13.x/http-client` (timeout, connectTimeout, retry com `throw: false`, throw, fake, failedConnection, preventStrayRequests, assertSent/assertNothingSent).
- RFB — CNPJ Alfanumérico: `gov.br/receitafederal/.../cnpj-alfanumerico` + PDF de perguntas e respostas + Anexo XV IN RFB 2.119/2022 (algoritmo DV módulo 11 ASCII-48; cronograma julho/2026; IN RFB 2.229/2024).
- Resolução CGSIM nº 61/2020 (DOU/gov.br) — modelos de integração REDESIM, papéis do município na pesquisa prévia de viabilidade.
- Código do projeto: `CnaeImportService`, `ValidCpf`, `Settings`/`Parameter`/`ParameterSeeder`, `AuditService`/`HasAuditoria`, `ResolveRepresentation`/`CurrentRepresentation`, `ProcurationController`/`ProcurationPolicy`, `CnaeController`, migrations, `portal-layout`, `bootstrap/app.php`, testes da Fase 1/2; `.planning/STATE.md` (decisões [01-xx]/[02-xx], fases 2.1–2.4).

### Secundárias (confiança MÉDIA)
- JUCEB/REGIN: páginas oficiais `ba.gov.br/juceb/redesim/*` e `regin.juceb.ba.gov.br` + termo de convênio municipal (diário oficial) descrevendo integração REGIN ↔ SEDUR (TVL via webservice).
- Campos da consulta de viabilidade REDESIM: documentação pública de consulta de protocolo (estabelecimento, atividades, endereço, eventos, área, inscrição imobiliária, forma de atuação) — base do payload de referência.
- OpenCNPJ: documentação oficial `opencnpj.org` (50 req/s, atualização mensal, uso comercial livre).

### Terciárias (confiança BAIXA — marcadas para validação)
- Manual de Integração REDESIM 2.2.x (WS01/WS02/WS15/WS29): existência confirmada por documentos públicos de terceiros; conteúdo integral não acessível — NÃO usado como base normativa do payload.
- Rate limit efetivo da BrasilAPI: não documentado oficialmente (fair-use via Cloudflare).

## Metadata

**Confiança por área:**
- Provider CNPJ: ALTA — chamadas reais executadas e registradas; termos verificados em fontes públicas.
- HTTP client / padrões Laravel: ALTA — doc oficial 13.x + código do projeto.
- Schema/autorização/telas: ALTA — derivados de decisões travadas (CONTEXT/STATE) e código existente.
- Payload REDESIM: MÉDIA — estrutura de referência fundamentada em norma CGSIM + campos públicos da consulta prévia; pendente validação SEDUR (por desenho, atrás de contrato).
- CNPJ alfanumérico: ALTA — fonte oficial RFB; impacto direto por entrar em vigor em julho/2026.

**Pesquisado em:** 2026-06-11
**Válido até:** ~2026-07-11 (reavaliar: entrada em produção do CNPJ alfanumérico e comportamento dos providers públicos; estável no restante)
