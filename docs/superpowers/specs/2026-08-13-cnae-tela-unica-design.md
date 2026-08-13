# CNAE — tela única (CRUD + risco municipal + condicionantes sanitárias)

## Contexto

O cadastro de CNAE, a classificação de risco municipal (Decreto 32.636/2020)
e as condicionantes-pergunta de risco sanitário (VISA) hoje vivem em três
telas separadas no console SEDUR:

- `/gestao/cnaes` — CRUD de CNAE (modal criar/editar).
- `/gestao/risco` — consulta da tabela de risco municipal vigente + modal
  "Publicar nova versão" (versionado, quatro olhos: autor ≠ publicador).
- `/gestao/risco/condicionantes` — consulta somente leitura das
  condicionantes sanitárias (a edição foi removida em andamento nesta
  branch: SEDUR não deveria mais editar dado de outra secretaria).

O usuário pediu para seguir o padrão de um projeto de referência (VISA —
sistema `sls-sms`, outra secretaria, mesmo conceito de CNAE) onde tudo isso
é uma única tela por CNAE ("Editar CNAE"): dados do CNAE, grau de risco,
flags de responsável técnico/fator multiplicador e a lista de perguntas de
classificação de risco, tudo num único formulário com um botão Salvar.

Decisões tomadas com o usuário durante o brainstorm:

1. **Unificar a UI, não descartar o modelo de dados.** `RiskClassification`
   e `RiskCondicionante` continuam existindo e ligados a `RuleVersion`
   (histórico/domínio), mas a tela deixa de forçar o fluxo de publicação em
   lote para editar um único CNAE.
2. **Condicionantes voltam a ser editáveis pelo SEDUR** nesta tela — reverte
   a decisão em andamento de torná-las somente leitura. O usuário confirmou
   que, apesar de VISA ser outro sistema (`sls-sms`), o SEDUR passa a manter
   sua própria cópia/edição de condicionantes por CNAE aqui.
3. **Risco municipal perde a exigência de quatro olhos para edição pontual.**
   Editar o grau de risco de um CNAE nesta tela salva direto (upsert na
   linha vigente), sem pedir autor/publicador distintos nem abrir uma nova
   versão. O fluxo de quatro olhos e publicação em lote (`RiscoController`,
   `RiscoMaintenanceService`, `PublishRiscoVersionRequest`) é removido.

## Escopo

### Banco de dados

Nova migration `add_regras_risco_to_cnaes_table`, adicionando a `cnaes`:

- `exige_rt` (boolean, default false)
- `exige_rt_se_alto` (boolean, default false)
- `exige_fator_multiplicador` (boolean, default false)
- `exige_detalhamento_multiplicador` (boolean, default false)

Espelha os campos equivalentes do `sls-sms` (`exige_rt`, `exige_rt_se_alto`,
`exige_fator_multiplicador`, `exige_detalhamento_multiplicador`) e cobre a
RN-010 de HU-047 ("cadastro de regras por CNAE... exige RT (condicional)...
fator multiplicador... todas parametrizáveis"). Fora do escopo: autorizado
p/ escritório virtual e autorizado p/ MEI — já existem como domínio
versionado próprio (`VirtualOfficeActivityCnae`) e não fazem parte do pedido
do usuário; não mexer.

`RiskClassification` e `RiskCondicionante` mantêm o schema atual (sem novas
colunas) — grau de risco municipal continua em `RiskClassification`, ligado
à versão vigente do domínio `RiscoMunicipal`; perguntas continuam em
`RiskCondicionante`, ligadas à versão vigente do domínio `RiscoSanitario`.

### Backend

**`Cnae` model**: adiciona os 4 novos campos ao `#[Fillable]`, cast boolean.

**`StoreCnaeRequest` / `UpdateCnaeRequest`**: validação dos 4 novos campos
(`required|boolean`) + `risco_municipal` (`required`, `Rule::enum`) — o grau
de risco entra no mesmo request do CNAE, mesmo sendo persistido em outra
tabela (o controller separa na hora de salvar).

**`CnaeController`**:
- `create()` / `edit(Cnae $cnae)` — novos métodos, renderizam a tela cheia
  (`gestao/cnaes/form`) em vez do modal. `edit()` carrega:
  - a classificação vigente (`RiskClassification::where('cnae_code', ...)
    ->where('rule_version_id', vigente municipal)->first()`);
  - as condicionantes vigentes desse CNAE (`RiskCondicionante::where(
    'cnae_code', ...)->where('rule_version_id', vigente sanitária)->get()`).
- `store()` / `update()` — passam a fazer, numa transação:
  1. salvar/atualizar o `Cnae` (campos próprios + os 4 novos flags);
  2. `RiskClassification::updateOrCreate(['rule_version_id' => vigente
     municipal, 'cnae_code' => code], ['risco_municipal' => ...])` — direto,
     sem draft, sem checagem de autor/publicador. Se não houver versão
     vigente do domínio `RiscoMunicipal`, falha com mensagem clara (não deve
     acontecer em ambiente seedado, mas é o mesmo tipo de guarda que já
     existe em `RiscoCondicionanteController::store`).
- Sub-rotas para o CRUD de perguntas dentro da ficha do CNAE (reaproveitando
  a lógica de `RiscoCondicionanteController`, já que o request/model não
  mudam):
  - `POST /gestao/cnaes/{cnae}/condicionantes`
  - `PUT /gestao/cnaes/{cnae}/condicionantes/{condicionante}`
  - `DELETE /gestao/cnaes/{cnae}/condicionantes/{condicionante}`

  Fica a critério do plano de execução decidir entre mover esses 3 métodos
  para o próprio `CnaeController` (mais simples, um controller só) ou manter
  `RiscoCondicionanteController` só com esses três métodos (reaproveita
  `StoreRiscoCondicionanteRequest`/`UpdateRiscoCondicionanteRequest` sem
  tocar). Recomendo mover para o `CnaeController` — a tela é uma só, o
  controller deveria ser um só.

**Removido**:
- `RiscoController` (index + publish), `RiscoMaintenanceService`,
  `PublishRiscoVersionRequest`.
- Rotas `GET /gestao/risco`, `PUT /gestao/risco/publicar`.
- Páginas `gestao/risco/index.tsx`, `gestao/risco/condicionantes.tsx`.
- Item de menu "Classificação de risco" e "Condicionantes" em
  `gestao-layout.tsx`.
- Permissões `consultar-risco` / `manter-risco` (seeders atualizados —
  remover das listas de cada papel; `manter-cnaes`/`consultar-cnaes` passam
  a cobrir tudo).

**Mantido sem alteração**: `RuleVersion`, `RuleVersionService`, o import
oficial (`RiscoMunicipalImportService`/`RiscoSanitarioImportService` e os
comandos/seeders associados) — a carga em massa da planilha oficial continua
gerando novas versões normalmente; só a tela manual de publicação em lote
some.

### Frontend

Substitui `resources/js/pages/gestao/cnaes/index.tsx`'s `CreateCnaeModal` /
`EditCnaeModal` por duas páginas cheias que compartilham um componente de
formulário (`CnaeForm`), no padrão "ficha" do projeto (`PageHeader` com
ações Voltar/Salvar, ver `ficha-analise/show.tsx`):

- `gestao/cnaes/criar.tsx` → `GET/POST /gestao/cnaes/criar`.
- `gestao/cnaes/editar.tsx` → `GET/PUT /gestao/cnaes/{cnae}/editar`.

Seções do formulário (mesma ordem do print de referência):

1. **Dados do CNAE** — código (readonly na edição), descrição, situação.
2. **Classificação de risco** — grau de risco (select com os 3 níveis
   municipais), exige RT, exige RT apenas se alto risco, possui fator
   multiplicador, exige detalhamento do multiplicador.
3. **Perguntas de classificação de risco** — lista repetível (texto da
   pergunta, resposta-gatilho sim/não, nível de reclassificação,
   fundamento/parecer), com adicionar/remover linha. Cada linha existente já
   salva individualmente (POST/PUT/DELETE nas sub-rotas) — não faz parte do
   `Form` de submit único do CNAE, para não perder edições de pergunta ao
   salvar o CNAE e vice-versa (mesmo padrão de "salvar aos poucos" que a UI
   antiga de condicionantes já usava).
4. **Status** — ativo/inativo (mesmo campo da seção 1 no print, mas o
   projeto já trata isso junto de "Dados do CNAE"; não duplicar).

A listagem (`gestao/cnaes/index.tsx`) troca os botões que abriam modal por
navegação (`router.visit`/`Link`) para `/gestao/cnaes/criar` e
`/gestao/cnaes/{id}/editar`; mantém busca, filtro, paginação, ativar/
desativar e excluir como estão hoje.

### Testes

Removidos (cobrem funcionalidade que deixa de existir):
- `tests/Feature/Risco/RiscoConsultaTest.php`
- `tests/Feature/Risco/RiscoCondicionanteMaintenanceTest.php`

Mantidos sem alteração: `RiscoClassificarCommandTest`, `RiscoGoldenCaseTest`,
`RiscoSanitarioSeederTest`, `RiscoSeedDistributionTest`,
`RiscoMunicipalSeederTest`, `RiscoClassificationServiceTest`,
`RiscoMunicipalImportServiceTest`, `RiscoSanitarioImportServiceTest`,
`RiscoDtoTest` (motor de classificação/import — não muda).

Novos/atualizados:
- `tests/Feature/Cnae/CnaeCrudTest.php` — cobre as páginas cheias de criar/
  editar (troca as expectativas de modal por `assertInertia` das novas
  rotas) e o upsert de `RiskClassification` embutido no save.
- Novo teste cobrindo o CRUD de condicionantes embutido na ficha do CNAE
  (equivalente ao que `RiscoCondicionanteMaintenanceTest` cobria, mas
  escopado à rota nova).

`tests/Feature/Cnae/CnaeIndexTableTest.php` e `CnaeImportTest.php`: revisar,
sem mudança esperada (não tocam nas rotas de criar/editar).

### Fora de escopo

- Autorização de CNAE para escritório virtual / MEI (domínio versionado
  próprio, feature ativa em desenvolvimento — não mexer).
- Mudar o schema de `RiskCondicionante` para os campos `grau_risco_sim`/
  `grau_risco_nao` explícitos do `sls-sms` — mantém `regra_reclassificacao`
  (json) como está.
- Qualquer alteração no motor de decisão de risco (`RiscoClassificationService`
  e afins) — só a tela de manutenção muda.
