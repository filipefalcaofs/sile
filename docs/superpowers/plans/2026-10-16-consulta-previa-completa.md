# Consulta prévia completa e confiável — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Toda consulta prévia do portal devolve um estado de confiabilidade (conclusiva, incompleta, inconclusiva) e só mostra veredito quando conclusiva, coletando os dados que o motor realmente usa.

**Architecture:** O motor continua sendo a única fonte do veredito. Um resolvedor puro (`ConfiabilidadeConsultaResolver`) lê o `ConsultaViabilidadeResult` e classifica o estado a partir do que o motor já devolve; uma informação estrutural nova (`dado_faltante`) sai do `TratamentoRamoResolver` para dispensar leitura de texto. Os endpoints públicos do portal passam a aceitar tipo de imóvel, categoria e vagas, e a tela exibe estado, pendências e aviso fixo.

**Tech Stack:** Laravel 13, PHP 8.5, Inertia v3 + React 19, PHPUnit 12, Vitest, Tailwind 4.

**Spec:** `docs/superpowers/specs/2026-10-16-consulta-previa-completa-design.md`

## Global Constraints

- Veredito é decidido só pelos motores (`LouosEnquadramentoService`, `RiscoClassificationService`); serviço, resolvedor e controller só compõem e propagam.
- Sem novas dependências; sem novas pastas base; textos novos no `DecisionTextCatalog` (+ descrição em `DecisionTextSeeder`), não hardcoded.
- A tela não orienta próximo passo (nada de "procure a SEDUR/JUCEB", nem link de protocolo). Aviso fixo: simulação informativa, não vincula a decisão oficial, que sai da análise do processo.
- Consulta anônima: só auditoria RN-002. Usuário autenticado: auditoria + snapshot em `ViabilityQuery`. Endereço não localizado / fora de Salvador / geocoder indisponível continuam erro honesto, sem histórico.
- Área obrigatória só nos endpoints de **endereço e inscrição** do portal; a aba CNAE mantém área opcional. Outros chamadores de `ConsultaViabilidadeService` (`consultarPorPontoConhecido`, `consultarComTerritorio`) não mudam de comportamento.
- CNAEs secundários ficam fora (o motor não os avalia).
- Valores de categoria: `CategoriaEmpresa::ORGAO_PUBLICO` (`orgao_publico`) e `CategoriaEmpresa::OUTRA` (`outra`). Tipo de imóvel: `PropertyType::active()` (campos `code`, `label`), convertido por `TipoImovel::fromRegin(string $raw, TipoImovelCatalog $catalog)` com `TipoImovelCatalog::vigente()`.
- PHP: chaves em todos os `if`, tipos explícitos, PHPDoc em vez de comentário inline; `vendor/bin/pint --dirty --format agent` antes de cada commit. Commits sem trailer de autoria de IA; `git add` só dos arquivos da tarefa (a árvore tem outras alterações não relacionadas).

## Review Focus

1. Área `0` (o request aceita `min:0`) em endereço/inscrição deve contar como não informada → `incompleta`, nunca veredito. Teste na Tarefa 2.
2. CNAE de formato inválido ou sem regra vigente → `inconclusiva` (nunca `conclusiva`) e sem veredito no JSON. Teste na Tarefa 2.
3. Endereço válido cuja zona não é identificada → `inconclusiva`, `veredito_locacional` nulo no JSON do portal. Teste na Tarefa 3.
4. Aba CNAE com área e todas as respostas completas continua nunca `conclusiva`. Teste na Tarefa 2.
5. Consulta anônima `incompleta`: auditada com `confiabilidade`, sem snapshot; autenticada `incompleta`: também sem snapshot. Teste na Tarefa 3.

---

### Task 1: O motor informa qual dado faltou (`dado_faltante`)

**Files:**
- Modify: `app/Services/Tratamento/TratamentoRamoResult.php`
- Modify: `app/Services/Tratamento/TratamentoRamoResolver.php` (retornos `nao_resolvido` nas ~linhas 170, 255-269 e 285-296)
- Test: `tests/Feature/Viabilidade/DadoFaltanteTratamentoTest.php`

**Interfaces:**
- Produces: `TratamentoRamoResult::$dadoFaltante` (`?string`, valores `'area' | 'tipo_imovel' | 'categoria_empresa'`, default `null`); `toEnquadramentoDimensao()` passa a incluir a chave `'dado_faltante'`. Chega ao JSON em `enquadramento.enquadramento.dado_faltante`.

- [ ] **Step 1: Write the failing test** `DadoFaltanteTratamentoTest` (usa `SeedsTratamentoPlanilha`, como `ConsultaViabilidadePublicaTest`), três métodos: `test_area_ausente_em_ramo_com_faixa_marca_area`, `test_tipo_ausente_em_familia_id_marca_tipo_imovel`, `test_categoria_ausente_em_regra_que_depende_dela_marca_categoria_empresa`. Cada um chama `TratamentoRamoResolver::resolver` (via container) com um CNAE da planilha de teste que dispara a condição e assere `status === 'nao_resolvido'` e `dadoFaltante` igual ao valor esperado; um quarto, `test_ramo_resolvido_nao_tem_dado_faltante`, assere `null`. Escolha os CNAEs/fixtures procurando em `tests/Support/SeedsTratamentoPlanilha.php` e nos testes existentes de tratamento a mesma condição que cada retorno do resolvedor trata.
- [ ] **Step 2: Run** `php artisan test --compact --filter=DadoFaltanteTratamentoTest` — Expected: FAIL (propriedade inexistente).
- [ ] **Step 3: Implement** o parâmetro `public ?string $dadoFaltante = null` no construtor de `TratamentoRamoResult` e a chave `'dado_faltante'` em `toEnquadramentoDimensao()`; passar `dadoFaltante: 'categoria_empresa'`, `'area'` e `'tipo_imovel'` nos três retornos citados. Nenhuma regra de decisão muda.
- [ ] **Step 4: Run** o mesmo filtro e `php artisan test --compact tests/Feature/Tratamento` (se existir, senão `--filter=Tratamento`) — Expected: PASS, sem regressão.
- [ ] **Step 5: Commit**

```bash
git add app/Services/Tratamento/TratamentoRamoResult.php app/Services/Tratamento/TratamentoRamoResolver.php tests/Feature/Viabilidade/DadoFaltanteTratamentoTest.php
git commit -m "feat: motor de tratamento informa qual dado faltou para resolver o ramo"
```

### Task 2: Estado de confiabilidade (enum + resolvedor + textos)

**Files:**
- Create: `app/Enums/ConfiabilidadeConsulta.php`
- Create: `app/Services/Viabilidade/ConfiabilidadeConsultaResolver.php`
- Create: `app/Services/Viabilidade/ConfiabilidadeConsultaResultado.php`
- Modify: `app/Services/Decisao/DecisionTextCatalog.php`, `database/seeders/DecisionTextSeeder.php`
- Test: `tests/Feature/Viabilidade/ConfiabilidadeConsultaTest.php`

**Interfaces:**
- Produces:
  - `enum ConfiabilidadeConsulta: string { case Conclusiva = 'conclusiva'; case Incompleta = 'incompleta'; case Inconclusiva = 'inconclusiva'; public function label(): string }`
  - `final readonly class ConfiabilidadeConsultaResultado { __construct(public ConfiabilidadeConsulta $estado, public array $pendencias = [], public ?string $motivo = null); public function toArray(): array }` — `pendencias` é `list<array{campo: string, rotulo: string, itens?: list<string>}>`; `toArray()` devolve `['estado', 'label', 'pendencias', 'motivo']`.
  - `ConfiabilidadeConsultaResolver::resolver(ConsultaViabilidadeResult $result): ConfiabilidadeConsultaResultado` (injeta `DecisionTextCatalog`).
- Regra (ordem): (1) coleta pendências que o cidadão pode suprir → `Incompleta`: `area` (entrada `tipo` ≠ `cnae` e `entrada.area` nula ou ≤ 0), `pergunta` (`enquadramento.enquadramento.pergunta_sem_resposta` ≠ null, rótulo = enunciado), `tipo_imovel` / `categoria_empresa` (valor de `dado_faltante`), `vagas` (condicionante `tipo === 'vagas'` com `exigido` não vazio e `declarado` vazio; `itens` = chaves de `exigido`). (2) sem pendências e `veredito_locacional.resultado === 'pendente'` → `Inconclusiva` com `motivo` = motivo do consolidado (ou o primeiro aviso). (3) senão `Conclusiva`. A aba CNAE nunca chega ao passo 3 (sem território o consolidado é `pendente`).
- Textos novos (chaves): `consulta.confiabilidade.conclusiva`, `.incompleta`, `.inconclusiva` (rótulos curtos), `consulta.aviso.simulacao` (aviso fixo, sem menção a órgão nem orientação de próximo passo).

- [ ] **Step 1: Write the failing test** `ConfiabilidadeConsultaTest` (mesmo setup de seeds/fakes de `ConsultaViabilidadePublicaTest`), chamando `ConsultaViabilidadeService` e o resolvedor: `test_endereco_com_zona_e_dados_completos_e_conclusiva`, `test_area_zero_em_endereco_e_incompleta_com_pendencia_area` (Review Focus 1), `test_pergunta_da_planilha_sem_resposta_e_incompleta`, `test_cnae_invalido_ou_sem_regra_e_inconclusiva` (Review Focus 2), `test_cnae_com_area_e_respostas_completas_nunca_e_conclusiva` (Review Focus 4), `test_vagas_exigidas_sem_declaracao_e_incompleta_com_itens` (parametrizando `louos.vagas.exigencia_por_grupo` via `Settings`/`ParameterSeeder` no teste para o grupo do CNAE).
- [ ] **Step 2: Run** `php artisan test --compact --filter=ConfiabilidadeConsultaTest` — Expected: FAIL (classes inexistentes).
- [ ] **Step 3: Implement** enum, DTO e resolvedor conforme a regra acima; adicionar as chaves ao `DecisionTextCatalog` (padrão das chaves `consulta.aviso.*`, linhas ~145-147) e a descrição de cada uma ao `DecisionTextSeeder` (linhas ~48-50).
- [ ] **Step 4: Run** o filtro — Expected: PASS.
- [ ] **Step 5: Commit**

```bash
git add app/Enums/ConfiabilidadeConsulta.php app/Services/Viabilidade/ConfiabilidadeConsultaResolver.php app/Services/Viabilidade/ConfiabilidadeConsultaResultado.php app/Services/Decisao/DecisionTextCatalog.php database/seeders/DecisionTextSeeder.php tests/Feature/Viabilidade/ConfiabilidadeConsultaTest.php
git commit -m "feat: estado de confiabilidade da consulta prévia (conclusiva, incompleta, inconclusiva)"
```

### Task 3: Serviço, auditoria, payload e histórico

**Files:**
- Modify: `app/Services/Viabilidade/ConsultaViabilidadeInput.php`, `ConsultaViabilidadeService.php`, `ConsultaViabilidadeResult.php`
- Modify: `app/Http/Controllers/Portal/ConsultaViabilidadeController.php`
- Modify: `app/Http/Requests/Portal/ConsultaViabilidadeEnderecoRequest.php`, `ConsultaViabilidadeCnaeRequest.php`, `ConsultaViabilidadeInscricaoRequest.php`
- Test: `tests/Feature/Viabilidade/ConsultaViabilidadePublicaTest.php`, `ConsultaViabilidadeServiceTest.php`, `HistoricoConsultaTest.php`

**Interfaces:**
- Consumes: `ConfiabilidadeConsultaResolver::resolver(...)` (Task 2); `dado_faltante` (Task 1).
- Produces:
  - `ConsultaViabilidadeInput` ganha `public array $vagas = []` (`array<string, float>`); `paraEndereco`, `paraCnae`, `paraInscricao` aceitam `?TipoImovel $tipoImovel = null, ?string $categoriaEmpresa = null, array $vagas = []` ao fim.
  - `ConsultaViabilidadeService::consultarPorEndereco(string $endereco, string $cnae, ?float $area = null, array $respostas = [], ?TipoImovel $tipoImovel = null, ?string $categoriaEmpresa = null, array $vagas = [])`; `consultarPorInscricao` idem; `consultarPorCnae` passa a aceitar `array $vagas = []` ao fim. `consultarPorPonto`/`consultarPorCnaeComEntrada`/`consultarComTerritorio` repassam `tipoImovel`, `categoriaEmpresa` e `vagasDeclaradas: $input->vagas` ao `EnquadramentoInput`.
  - `ConsultaViabilidadeResult::confiabilidade(): ConfiabilidadeConsultaResultado` e `toArray()` com a chave `'confiabilidade'`.
  - Payload do portal (`jsonComPerguntas`): `veredito_locacional` = `null` quando o estado não é `conclusiva`; nova chave `aviso_simulacao` (string, texto de `consulta.aviso.simulacao`) em todo resultado.
  - Requests: `tipo_imovel` (`nullable|string|max:120`), `categoria_empresa` (`nullable|in:orgao_publico,outra`), `vagas` (`nullable|array`, `vagas.*` `numeric|min:0`); `area` passa a `required|numeric|gt:0|max:9999999` em endereço e inscrição (CNAE mantém `nullable`), com mensagem em português.
- Controller: converte `tipo_imovel` (label) em `TipoImovel::fromRegin($label, TipoImovelCatalog::vigente())`; `registrarHistorico` não grava quando o estado é `incompleta`.
- Auditoria (`comporEResultar`): `properties` ganha `confiabilidade` e `pendencias`.

- [ ] **Step 1: Write the failing tests**:
  - `ConsultaViabilidadePublicaTest`: `test_endereco_sem_area_responde_422_com_mensagem`; `test_zona_nao_identificada_devolve_inconclusiva_sem_veredito` (Review Focus 3: JSON tem `confiabilidade.estado === 'inconclusiva'` e `veredito_locacional === null`); `test_resultado_conclusivo_mantem_veredito_e_confiabilidade`; `test_tipo_imovel_categoria_e_vagas_chegam_ao_motor` (vagas parametrizadas para o grupo, declaradas no request, sem pendência de vagas na resposta); `test_incompleta_anonima_audita_confiabilidade_e_nao_grava_historico` e `test_incompleta_autenticada_nao_grava_snapshot` (Review Focus 5); `test_aba_cnae_continua_com_area_opcional`.
  - `HistoricoConsultaTest`: snapshot de consulta conclusiva e inconclusiva contém `result.confiabilidade`.
  - `ConsultaViabilidadeServiceTest`: auditoria `viabilidade/consulta` contém `confiabilidade` e `pendencias`; chamadores antigos (`consultarPorPontoConhecido`, `consultarComTerritorio`) seguem passando sem os parâmetros novos.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/Viabilidade` — Expected: FAIL nos testes novos.
- [ ] **Step 3: Implement** conforme as interfaces acima, nos arquivos listados; `index()` do controller passa as props `tiposImovel` (`list<array{value: string, label: string}>` de `PropertyType::active()`, `value` = `label`) e `categoriasEmpresa` (`[{value:'orgao_publico', label:'Órgão público'}, {value:'outra', label:'Outra'}]`).
- [ ] **Step 4: Run** `php artisan test --compact tests/Feature/Viabilidade tests/Unit/Viabilidade` — Expected: PASS (inclusive os testes antigos).
- [ ] **Step 5: Commit**

```bash
git add app/Services/Viabilidade app/Http/Controllers/Portal/ConsultaViabilidadeController.php app/Http/Requests/Portal tests/Feature/Viabilidade tests/Unit/Viabilidade
git commit -m "feat: consulta prévia devolve confiabilidade e aceita tipo de imóvel, categoria e vagas"
```

### Task 4: Tela do portal

**Files:**
- Modify: `resources/js/pages/portal/viabilidade/consulta.tsx`
- Modify: `resources/js/components/viabilidade/resultado-viabilidade.tsx` (tipo `ResultadoConsulta` + renderização)
- Test: `resources/js/pages/portal/viabilidade/consulta-layout.test.tsx` (existente) e `resources/js/components/viabilidade/resultado-viabilidade.test.tsx` (novo)

**Interfaces:**
- Consumes: props `tiposImovel`, `categoriasEmpresa` (Task 3); `ResultadoConsulta.aviso_simulacao: string` e `ResultadoConsulta.confiabilidade: { estado: 'conclusiva' | 'incompleta' | 'inconclusiva'; label: string; pendencias: Array<{ campo: string; rotulo: string; itens?: string[] }>; motivo: string | null }`; `veredito_locacional` passa a `VereditoLocacional | null`.

- [ ] **Step 1: Write the failing tests** (`resultado-viabilidade.test.tsx`): `conclusiva` exibe o veredito e o aviso fixo; `incompleta` lista as pendências e não exibe veredito; `inconclusiva` exibe o `motivo`, sem veredito e sem qualquer link/texto de próximo passo; o aviso fixo aparece nos três estados. Em `consulta-layout.test.tsx`: o campo de área é obrigatório nas abas Endereço e Inscrição e opcional na aba CNAE; a aba CNAE exibe o rótulo "sem análise do local".
- [ ] **Step 2: Run** `npx vitest run resources/js/components/viabilidade resources/js/pages/portal/viabilidade` — Expected: FAIL.
- [ ] **Step 3: Implement**: bloco "Refinar consulta" (recolhido por padrão) com selects de tipo de imóvel e categoria da empresa (das props) e, quando houver pendência `vagas`, campos numéricos para cada `itens`; envio no payload (`tipo_imovel`, `categoria_empresa`, `vagas`); destaque do estado de confiabilidade antes do veredito; pendências apontadas ao lado dos campos correspondentes (`area`, `tipo_imovel`, `categoria_empresa`, `vagas`, e as perguntas já renderizadas por `PerguntaPlanilhaCampo`); veredito só quando `conclusiva`; aviso fixo (texto de `aviso_simulacao` do payload); desabilitar o botão sem área nas abas Endereço/Inscrição; seguir o padrão visual e de componentes atual (`Input`, `Label`, `Alert`, `Card`).
- [ ] **Step 4: Run** o filtro do Step 2 e `npx tsc --noEmit` — Expected: PASS, sem erro de tipo.
- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/portal/viabilidade resources/js/components/viabilidade
git commit -m "feat: tela da consulta prévia mostra confiabilidade, pendências e aviso de simulação"
```

### Task 5: Verificação final

- [ ] **Step 1:** `vendor/bin/pint --dirty --format agent`, depois `php artisan test --compact tests/Feature/Viabilidade tests/Unit/Viabilidade tests/Feature/Tratamento` — Expected: tudo verde.
- [ ] **Step 2:** `npx vitest run` e `npx tsc --noEmit` — Expected: verde.
- [ ] **Step 3:** Verificar no navegador (Browser pane, `preview_start`) os três estados na página `/portal/viabilidade` e a aba CNAE; registrar o que não foi possível exercitar (ex.: zona real depende da camada SEMAP).
- [ ] **Step 4:** Perguntar ao usuário se rodamos a suíte completa (`php artisan test --compact`).
