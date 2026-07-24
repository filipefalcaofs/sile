# Ficha de análise — paridade completa com o legado (SAPS) — design

**Data:** 2026-07-24
**Status:** Implementado
**Origem:** Prints SAPS/Salvador Simplifica v5.0 (Ficha de Análise) enviados pelo usuário
**Relacionado:** HU-135 (ficha), HU-019/HU-042/HU-048 (condicionante-pergunta), HU-062 (imóvel/área pública), HU-015/HU-038 (Quadro 7 LOUOS/TLL), HU-080/081 (distribuição/caixa do setor), design anterior `2026-07-22-ficha-analise-layout-cadastro-iptu-design.md`

---

## 1. Problema

O ajuste de 22/07 alinhou o topo da ficha (Localização | Polígono + Dados do TVL + Cadastro Imobiliário). Restam divergências de conteúdo identificadas por comparação direta com os prints do legado:

1. Rótulos dos botões de ação divergem ("Salvar rascunho"/"Finalizar ficha" vs. "Salvar Ficha"/"Finalizar Ficha").
2. Não existe a seção **"Motivo de Análise"** (lista de texto) presente no rodapé da ficha legado.
3. Não existe uma tabela de **Tramitação** (Data/Setor/Usuário/Status) nem o botão "Imprimir Extrato da Tramitação" dentro da própria ficha — hoje só há uma timeline simplificada em `gestao/processos/show.tsx`.
4. No bloco "Enquadramento por atividade (CNAE)" faltam 2 elementos do legado: pergunta/resposta "desenvolvida no local?" e códigos LOUOS/TLL. Faltam também, num bloco à parte (nível de processo, antes de "Atividades do Processo"): confirmação "Endereço correto?" e leitura de "atividade em área pública?".

Consulta ao especialista de negócio (`analista-negocio`) confirmou, por item, se cada gap é dado real disponível, campo novo de baixo risco, ou bloqueio externo — evitando inventar dado (regra `entrega-funcional`).

## 2. Decisões por item (base do design)

| Item | Fonte de verdade | Ator que preenche | Bloqueio? |
|---|---|---|---|
| Motivo de Análise | Campo novo, texto livre, na própria ficha | Analista | Não |
| Tramitação (tabela) | `AnalysisStatusTransition` (já existe) + setor do processo + `actor` | Sistema (leitura) | Não |
| Imprimir Extrato | Impressão real (`window.print()`) da tabela acima | Analista (ação) | Não |
| "Desenvolvida no local?" (pergunta/resposta por CNAE) | Não existe nenhuma captura hoje (wizard nem condicionante) | Cidadão, no wizard (fora de escopo) | Não bloqueia a ficha — exibe "Pendente" |
| Código LOUOS / Código TLL / origem do valor TLL | Tabela oficial LOUOS/TTL não entregue pela SEDUR (`docs/ANALISE-HUs-REUNIAO-SEDUR.md:241`) | Admin, quando SEDUR entregar (dado versionado) | **Sim — bloqueio externo real** |
| "Atividade em área pública?" | `ViabilityRequest.is_public_area` (já existe, HU-062) | Cidadão, no wizard (já implementado) | Não — só falta exibir |
| "Endereço correto?" | Campo novo, sem HU formal — interpretação como confirmação do analista | Analista | Não |

## 3. Layout — mudanças na ficha (`gestao/ficha-analise/show.tsx`)

### 3.1 Botões de ação

Renomear (sem alterar o comportamento/rotas):
- "Salvar rascunho" → **"Salvar Ficha"**
- "Finalizar ficha" → **"Finalizar Ficha"**

### 3.2 Novo bloco "Confirmações do imóvel" — entre Dados do TVL e Enquadramento por CNAE

**Correção de posicionamento** (releitura cuidadosa dos prints): "Atividade em área pública?" e "Endereço correto?" NÃO são por-CNAE nem ficam no card Polígono — no legado formam um bloco único, de nível de processo, posicionado **depois da faixa "Dados do TVL" e antes de "Atividades do Processo"**. Também corrige a citação incorreta ao campo "Confirma polígono diferente do requerente?", que **não existe hoje** no SILE (verificado em `show.tsx` — nenhuma referência).

Novo `Card` "Confirmações do imóvel", entre o card "Dados do TVL" (3.1) e o card "Enquadramento por atividade (CNAE)":

1. **"Atividade está estabelecida em área pública?"** — `DescItem` somente leitura, valor = `is_public_area` do processo (Sim/Não/"—" se nunca respondido), nota "informado pelo requerente na solicitação".
2. **"Endereço correto?"** — radio Sim/Não, **editável pelo analista**, mesmo padrão de persistência (autosave). Sem seleção default (`null` = não respondido).

### 3.3 Enquadramento por atividade (CNAE) — card existente, 2 acréscimos por item

Dentro de cada `<li>` de CNAE (após o bloco atual de Grupo de uso/gatilhos/fundamentação), adicionar:

1. **Pergunta/Resposta** (texto fixo, sem input): "Pergunta: A atividade será desenvolvida no local?" / "Resposta: Pendente — requerente ainda não respondeu esta pergunta no formulário de solicitação." Sempre neste estado até o wizard capturar (fora de escopo desta spec).
2. **Código LOUOS** e **Código TLL**, ao lado do "Valor TLL" já existente, com o mesmo padrão visual de pendência: "Pendente — tabela oficial SEDUR não entregue" (idêntico ao tratamento atual do `valor_tll` nulo).

Esses 2 acréscimos são exibidos uma vez por item de CNAE (replicando o padrão do print), não exigem nenhuma migration nova de dados de negócio — apenas os dois campos de contrato explícito da seção 4.2 para deixar a pendência auditável e preparada para quando os dados chegarem.

### 3.4 Nova seção "Motivo de Análise"

Nova `Card` com:
- Lista de itens de texto livre (chips ou lista com botão remover, mesmo componente/padrão usado em "Condicionantes Adicionais").
- Campo de texto + botão "Adicionar motivo".
- Sem sugestão automática nem IA — é anotação manual do analista.
- Posição: após "Parecer técnico", antes da seção de Tramitação (nova, 3.5).

### 3.5 Nova seção "Tramitação"

Nova `Card` com tabela:

| Data | Setor | Usuário | Status |
|---|---|---|---|

- Fonte: `AnalysisStatusTransition` do processo (`viabilityRequest->analysisStatusTransitions`, ordenado por `id`), com `to_status->label()`, `actor->name` (— quando null, ex.: transição automática `para_distribuir`), e o setor **atual** do processo (`viabilityRequest->sector->name`) — ressalva: o modelo não versiona o setor por transição; se o processo mudar de setor, as linhas antigas mostrarão o setor atual, não o histórico. Documentado como limitação conhecida, não como bug.
- Cada linha expansível (chevron) revela o campo `reason` da transição, quando presente.
- Botão **"Imprimir Extrato da Tramitação"**: abre uma janela de impressão do navegador (`window.open` + `print()`) com o extrato montado a partir dos mesmos dados já renderizados na tabela — sem novo endpoint, sem PDF novo, sem chamada ao servidor.
- Posição: após "Motivo de Análise", como última seção antes das ações de rodapé já existentes (Encaminhar à malha fina / Salvar / Finalizar).

## 4. Dados e contrato

### 4.1 `analysis_records` — 2 colunas novas

Migration nova, seguindo o padrão de `per_cnae`/`conditions`/`parking` (nullable, cast array/boolean):

- `analysis_reasons` (`json`, nullable) — lista de strings (Motivo de Análise). Cast `array`, default `[]`.
- `address_confirmed` (`boolean`, nullable) — "Endereço correto?" (null = não respondido ainda).

`AnalysisRecord::casts()`: adicionar `'analysis_reasons' => 'array'`, `'address_confirmed' => 'boolean'`. `#[Fillable]`: incluir as duas colunas.

`AnalysisRecordResource`: expor `analysis_reasons` (default `[]`) e `address_confirmed`.

`AnalysisRecordRequest` (autosave, `sometimes`): `analysis_reasons` (`array`, `analysis_reasons.*` string), `address_confirmed` (`nullable boolean`).

### 4.2 `PreAnaliseService::perCnae()` — 2 chaves explícitas de contrato pendente

Ao montar cada item de `per_cnae` na pré-análise (revisão 1), incluir explicitamente, sempre `null` até a fonte oficial existir:

```php
'codigo_louos' => null,
'codigo_tll' => null,
```

Isso documenta o contrato (mesmo padrão já usado em `AnaliseTecnicaDecisionService` com `'reason' => null`) e evita qualquer ambiguidade de "campo esquecido" vs. "pendência conhecida". A UI usa a ausência (`null`) para renderizar o estado de pendência da seção 3.2-2.

Não há alteração no motor/`EnquadramentoResult` — `grupo`/`subgrupo` (Grupo de Uso) continuam vindo de lá, sem mudança.

### 4.3 `AnalysisRecordController@show` — novas props Inertia

- `localizacao` (já existe): adicionar `is_public_area: boolean|null` (lido de `viabilityRequest->is_public_area`).
- `tramitacao`: novo array, construído a partir de `viabilityRequest->analysisStatusTransitions()->with('actor')->orderBy('id')->get()`, mapeado para `{ data: ISO8601, setor: string|null, usuario: string|null, status: string (label), reason: string|null }`.

### 4.4 Auditoria (RN-002)

- Autosave de `analysis_reasons`/`address_confirmed` já cai no evento existente `ficha-autosave` (nenhum evento novo necessário — o payload já é auditado por completo).
- Consulta da aba Tramitação não gera evento de auditoria adicional (é parte do `ficha-consulta` já existente, sem nova chamada externa).

## 5. Degradação (anti-fachada)

| Situação | UI |
|---|---|
| `analysis_reasons` vazio | Lista vazia + placeholder "Nenhum motivo registrado" (sem bloquear a ficha) |
| `codigo_louos`/`codigo_tll` nulos (sempre, hoje) | "Pendente — tabela oficial SEDUR não entregue" |
| Pergunta "desenvolvida no local" | Sempre "Pendente — requerente ainda não respondeu..." (não há fonte hoje) |
| `is_public_area` null (processo antigo sem o campo) | "—" |
| `address_confirmed` null | Radio sem seleção (nem Sim nem Não marcado) |
| `tramitacao` vazia (processo ainda não passou pelo eixo operacional) | `EmptyState` "Sem tramitação registrada" |

Nenhum desses estados bloqueia abrir, salvar ou finalizar a ficha.

## 6. Escopo

### Inclui
- Renomeação dos botões.
- Seção Motivo de Análise (campo novo, editável, autosave).
- Seção Tramitação (tabela + impressão) com dado real já existente.
- Novo bloco "Confirmações do imóvel" (área pública leitura + endereço correto editável), entre Dados do TVL e Enquadramento por CNAE.
- 2 acréscimos no card de Enquadramento por CNAE (pergunta pendente, código LOUOS/TLL pendente).
- Migration em `analysis_records`, ajuste em `PreAnaliseService`, `AnalysisRecordResource`, `AnalysisRecordRequest`, `AnalysisRecordController`.

### Fora deste ciclo (registrar como pendência em `STATE.md`/`ROADMAP.md`)
- Captura da resposta "desenvolvida no local?" no wizard do cidadão (HU-064/065) — requer nova pergunta no formulário, fora do escopo desta ficha.
- Tabela oficial de código LOUOS + código/valor TLL — bloqueio externo (SEDUR não entregou).
- Barra de abas externa do processo (SemiExpresso/MalhaFina/Anexos/DAM/Vistoria) — fora de escopo, decidido em 2026-07-24 (conversa anterior) como "só a ficha".
- Refino de obrigatoriedade/regra de concessão de uso para área pública (Observações HU-062) — já é pendência conhecida da própria HU.

## 7. Critérios de aceite — BDD

**CA-01** DADO uma ficha aberta QUANDO o analista visualizar os botões de ação ENTÃO os rótulos exibidos são "Salvar Ficha" e "Finalizar Ficha".

**CA-02** DADO uma ficha em edição QUANDO o analista adicionar um item em "Motivo de Análise" e a ficha autosave ENTÃO o item persiste e reaparece ao recarregar a página.

**CA-03** DADO um processo com transições registradas no eixo operacional QUANDO abrir a ficha ENTÃO a seção Tramitação exibe uma linha por transição com Data, Setor, Usuário e Status corretos.

**CA-04** DADO um processo sem nenhuma transição operacional QUANDO abrir a ficha ENTÃO a seção Tramitação exibe o estado vazio, sem erro.

**CA-05** DADO qualquer CNAE da ficha QUANDO exibido ENTÃO aparecem "Pergunta: A atividade será desenvolvida no local?" com resposta "Pendente...", e "Código LOUOS"/"Código TLL" com "Pendente — tabela oficial SEDUR não entregue" — nunca um valor inventado.

**CA-06** DADO um processo com `is_public_area = true` QUANDO abrir a ficha ENTÃO o bloco "Confirmações do imóvel" exibe "Atividade está estabelecida em área pública?: Sim".

**CA-07** DADO uma ficha em edição QUANDO o analista marcar "Endereço correto? = Não" e a ficha autosave ENTÃO o valor persiste como `false` e é lido corretamente ao reabrir.

**CA-08** DADO a seção Tramitação renderizada QUANDO o analista clicar em "Imprimir Extrato da Tramitação" ENTÃO o diálogo de impressão do navegador abre, restrito ao conteúdo da tabela (CSS `@media print`).

**CA-09** DADO uma revisão finalizada (imutável) QUANDO o analista tentar editar Motivo de Análise ou Endereço correto ENTÃO a ficha bloqueia a edição (mesma regra RN-003 já aplicada aos demais campos).

## 8. Testes

| Teste | Cobertura |
|---|---|
| Feature autosave `analysis_reasons`/`address_confirmed` (novo ou estender `AnalysisRecordAutosaveTest`) | CA-02, CA-07, CA-09 |
| Feature payload ficha + `tramitacao` (novo) | CA-03, CA-04 |
| Smoke UI `FichaUiSmokeTest` (estender) | CA-01, CA-05, CA-06, presença das novas seções |
| Unit `PreAnaliseServiceTest` (ou equivalente) — `codigo_louos`/`codigo_tll` sempre `null` no contrato | CA-05 |

CA-08 (impressão) é comportamento de browser — cobertura por smoke UI verificando que o botão existe e a marcação `@media print` está presente; sem teste de navegador real para o diálogo nativo de impressão.

## 9. Decisões registradas

| # | Decisão |
|---|---|
| D1 | Escopo = só o conteúdo da ficha (confirmado com o usuário); barra de abas externa do processo fica fora |
| D2 | Motivo de Análise é campo novo de texto livre do analista, não derivado do motor |
| D3 | Tramitação usa o eixo operacional (`AnalysisStatusTransition`) já existente; setor exibido é o atual do processo (limitação documentada, não bug) |
| D4 | Pergunta "desenvolvida no local" e tabela LOUOS/TLL ficam com contrato explícito `null` — pendência auditável, nunca fachada |
| D5 | "Área pública" é leitura do campo já existente (`is_public_area`, HU-062); não é campo novo |
| D6 | "Endereço correto?" é interpretação (sem HU formal) modelada como confirmação simples do analista, de baixo risco |
| D7 | Impressão da Tramitação é `window.print()` com CSS de impressão — sem PDF novo, sem endpoint novo |
