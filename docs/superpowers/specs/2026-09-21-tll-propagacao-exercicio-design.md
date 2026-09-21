# Spec — Propagação anual da tabela TLL por exercício (gerar rascunho → publicar)

Data: 2026-09-21
Origem: conversa desta data — "como automatizar a atualização anual das taxas" → opção A
(propagar pelo fator do decreto, com publicação humana).
Estende: `2026-09-20-tll-valores-exercicio-dam-sefaz-design.md` (CRUD `tll_valores` já existe).

## 1. Decisão

A tabela de valores TLL é o **Anexo IV — Tabela de Receita nº III** da Lei nº 7.186/2006,
publicada pela SEFAZ em PDF por exercício. A atualização anual é **correção monetária**:
o art. 327 da Lei 7.186/2006 autoriza, e um decreto de fim de ano aplica o **IPCA** (ex.:
Decreto nº 41.304/2025 → fator 1,0446 para 2026) a tributos e preços públicos em quantia
fixa (art. 3º do decreto). Código e descrição só mudam por **lei** (9.417/2018, 9.562/2021).

A SEFAZ **não publica API** da tabela. Logo o sistema **não descobre** o valor sozinho:
o admin informa o fator do decreto, o sistema **propõe** o exercício novo como rascunho
e um **segundo usuário publica** (quatro olhos). Nada de scraping, nada de publicação
automática (anti-fachada; o valor errado vira DAM errado).

## 2. Estado atual

- `tll_valores` (HU-071/HU-014): `(codigo_tll, exercicio)` unique; `valor`, `taxa_servico`,
  mapeamento SEFAZ (`codigo_tll_sefaz`, `codigo_servico_sefaz`, `servico_sefaz`), `active`.
  Auditoria automática via `HasAuditoria` (Spatie `logOnlyDirty` — valor anterior/novo).
- CRUD em `/gestao/tll` (`TllValorController`), permissão `manter-parametros` (reuso).
- `TllCalculoService` usa **só linha ativa** do exercício; sem valor → pendente honesto.
- O projeto já tem o padrão de **regra versionada com quatro olhos**:
  `rule_versions` (cabeçalho genérico) + `RuleVersionService::publish` rejeita
  publicador = autor em `RuleDomain::isSensitive()` (`FourEyesViolationException`).
- Alertas agendados já existem como **notificação real** (`AlertarVencimentosCommand` →
  `NotificationDispatcher`, idempotente via ledger `communications`), agendados em
  `routes/console.php`.

## 3. Modelo de dados

### 3.1 `tll_valores` (alteração)

| Campo novo | Tipo | Nota |
|---|---|---|
| `rule_version_id` | FK → `rule_versions.id`, null | liga a linha ao exercício (versão) que a gerou |

- Linhas **manuais** (CRUD atual) ficam com `rule_version_id = null` — continuam válidas.
- Linhas **propagadas** apontam para a `rule_versions` do exercício.
- **Não** adicionar coluna de status na linha: a vigência da linha continua sendo
  `active` + `exercicio`; o rascunho/publicado é da **versão** (cabeçalho), não da linha.

### 3.2 Reuso de `rule_versions` (sem tabela nova de exercício)

O exercício TLL **é** uma versão de regra. Novo domínio no enum:

```php
case TllValores = 'tll_valores'; // isSensitive() => true (define valor de tributo)
```

- `version` = o exercício como string (`"2027"`).
- `source` = o ato legal (ex.: `Decreto nº 41.304/2025`) — obrigatório na propagação.
- `status`: `rascunho` → `vigente` (publicação); a anterior do mesmo domínio vira
  `substituida` **sem apagar** (padrão `RuleVersionService`).
- `created_by` (autor do rascunho) / `published_by` (publicador) — base do quatro olhos.

O **fator** não é coluna de `rule_versions` (cabeçalho genérico). Fica na **auditoria**
da propagação (properties: origem, destino, fator, decreto) e é reexibido na tela a
partir da auditoria da versão. Não cria `tll_exercicios` — seria duplicar `rule_versions`.

## 4. Fluxo

1. Em `/gestao/tll`, ação **Gerar exercício**.
2. Campos: exercício de origem, exercício de destino, fator (ex.: `1,0446`), decreto
   (obrigatório — é a `source` da versão).
3. `TllPropagacaoExercicio` abre o rascunho (`RuleVersionService::openDraft`, domínio
   `tll_valores`, versão = destino, `created_by` = usuário) e clona as linhas **ativas**
   da origem: `valor` e `taxa_servico` × fator (2 casas, half-up); mapeamento SEFAZ
   copiado sem alterar; `rule_version_id` = rascunho; `active = true`.
4. A lista filtra o destino; o admin confere com o PDF oficial e ajusta centavo se a
   tabela divergir do arredondamento (edição pontual pelo CRUD atual).
5. **Publicar exercício** chama `RuleVersionService::publish` (domínio sensível →
   **publicador ≠ autor**, senão `FourEyesViolationException`). A versão vira vigente;
   a anterior é fechada (substituída, sem apagar).
6. Alerta agendado (dez/jan) **notifica** os gestores se o exercício corrente ou o
   seguinte não tem versão vigente — nunca grava valor, nunca publica.

`TllCalculoService` **não muda**: continua lendo linha ativa por exercício. O rascunho
não interfere porque a linha propagada só passa a valer no exercício de destino — e o
cálculo do ano corrente usa o exercício corrente. (Se se quiser isolar o rascunho do
cálculo do próprio ano de destino antes da publicação, o filtro passa a exigir a versão
vigente — ver ponto aberto 1.)

## 5. Regras

- Origem sem linha ativa → recusa (nada a propagar).
- Destino já **vigente** → recusa (não regenera exercício publicado).
- Destino em **rascunho** → **regera**: atualiza os valores pelo novo fator e sincroniza
  o conjunto de códigos com a origem (insere os que faltam, inativa os que saíram da
  origem); não duplica (chave `(codigo_tll, exercicio)`).
- Código **inativo** na origem não vai para o destino (saiu de lei). Código **novo** de
  lei entra pelo CRUD (manual), não pelo fator.
- Fator válido: `> 0` e `≤ 2` (faixa de sanidade; IPCA anual nunca passa disso).
- Arredondamento: 2 casas, half-up, aplicado sobre o **exercício de origem** (não sobre
  2018). A prévia existe porque um centavo pode divergir do PDF.

## 6. Superfície

Mesma tela, **sem item novo no menu** (regra de navegação).

- `POST /gestao/tll/exercicios` — gerar/regerar rascunho (autor = usuário).
- `POST /gestao/tll/exercicios/{exercicio}/publicar` — publicar (publicador ≠ autor).

Serviço `App\Services\Analise\TllPropagacaoExercicio` (orquestra: openDraft + clona +
audita). Controller só valida e chama. Comando `tll:alertar-exercicio` (espelha
`AlertarVencimentosCommand`): idempotente via ledger `communications`, notifica gestores
(role de gestão) em dez/jan se faltar versão vigente; agendado em `routes/console.php`
com `withoutOverlapping`/`onOneServer`.

## 7. Auditoria (RN-002)

- `rule_versions` já audita via `HasAuditoria` (status, published_by/at).
- Propagação audita: origem, destino, fator, decreto, quantidade de linhas, autor.
- Publicação audita: versão, publicador, fechamento da anterior.
- Ajuste manual de linha (CRUD) já audita valor anterior/novo via `HasAuditoria`.

## 8. Fora de escopo

- Baixar/parsear PDF ou portal da SEFAZ (sem contrato estável).
- Aplicar IPCA do IBGE sem o decreto (a fonte é o ato, não o índice bruto).
- Publicação automática (sempre quatro olhos).
- Mudança de código/descrição (isso é lei, não fator).
- Import de PDF como fonte (opção B, futura, só como conciliação).

## 9. Critérios de aceite

- **CA-01** Origem ativa × fator gera rascunho completo: linhas clonadas, SEFAZ intacto,
  arredondado, `rule_version_id` do rascunho, versão `rascunho` com `source` = decreto.
- **CA-02** Destino vigente não regenera; destino em rascunho regenera sem duplicar.
- **CA-03** Publicar exige publicador ≠ autor (quatro olhos); ao publicar, a versão vira
  vigente e a anterior é fechada sem apagar.
- **CA-04** Sem versão vigente do exercício, o cálculo permanece pendente (nunca inventa).
- **CA-05** Gerar, regerar e publicar auditam fator, decreto, origem, destino e
  responsável; ajuste manual de linha audita valor anterior/novo.
- **CA-06** O alerta de dez/jan notifica gestores quando falta versão vigente, de forma
  idempotente, sem gravar valor nem publicar.

## 10. Pontos abertos

1. **Isolamento do rascunho no cálculo:** hoje o cálculo lê linha ativa por exercício,
   sem olhar a versão. Se o rascunho de 2027 não deve ser usado nem em simulação antes
   da publicação, o `TllCalculoService` passa a exigir a versão **vigente** do domínio
   `tll_valores` para o exercício. Confirmar se esse isolamento é desejado ou se a linha
   ativa por exercício já basta (o exercício de destino ainda não é o corrente).
2. **Quem recebe o alerta:** role/perfil de gestão a notificar (espelhar o escalonamento
   de SLA, que usa role de gestor) — confirmar o destinatário.
