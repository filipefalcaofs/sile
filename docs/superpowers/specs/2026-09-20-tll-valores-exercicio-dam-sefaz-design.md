# Spec — Tabela de valores TLL por exercício (CRUD) + cálculo do DAM + envio SEFAZ

Data: 2026-09-20
Origem: conversa desta data — "iremos enviar pra SEFAZ as taxas via nossa API; precisamos do CRUD para o cadastro das TLL conforme exercício anual".
Referências: HU-071 (DAM/TLL), HU-110 (envio SEFAZ — bloqueado, Fase 13), contrato `docs/reunioes/2026-08-18-sefaz-tvl/contrato-endpoint-tvl.md` (bloco `taxas`), planilha 20.08.26 (`codigo_tll`/`especificacao_tll` por enquadramento).

## 1. Decisões do usuário (2026-09-20)

- **Estrutura completa** do registro de TLL: código TLL + exercício + valor + código TLL SEFAZ + código de serviço SEFAZ + taxa de serviço + fator multiplicador por CNAE (RN-004).
- **O Viabiliza calcula** o valor do DAM (RN-004): atividade de maior valor + taxa de serviço + fator multiplicador quando o CNAE exigir.
- **Envio automático** à SEFAZ ao deferir o processo.

## 2. Estado atual

- O **código TLL** (1.01, 2.02) e a **especificação** já vêm da planilha por enquadramento (CNAE × área × local) — funcionam (`TratamentoEnquadramento.codigo_tll` → `PreAnaliseService` → ficha).
- O **valor monetário** não existe — a ficha mostra "Pendente (tabela de taxas/DAM)".
- O CNAE já tem `exige_fator_multiplicador` (bool) — o fator é por CNAE.
- O **envio à SEFAZ (HU-110)** está bloqueado: endpoint de envio e credenciais SenhaWeb são pendências da SEDUR; o `SefazViabilidadeGateway` é `Unavailable` (audita "bloqueado", nunca finge envio).

## 3. Modelo de dados

### `tll_valores` (tabela de valores TLL por exercício — dado versionado, administrável)

| Campo | Tipo | Nota |
|---|---|---|
| id | bigint | |
| codigo_tll | string | código da planilha (1.01, 2.02) — liga ao enquadramento |
| exercicio | smallint | ano (ex.: 2026) |
| valor | decimal(10,2) | valor da TLL em R$ |
| codigo_tll_sefaz | string, null | código da taxa na SEFAZ (ex.: T45020425) |
| codigo_servico_sefaz | string, null | código do serviço na SEFAZ (ex.: S2253362) |
| servico_sefaz | string, null | descrição do serviço na SEFAZ |
| taxa_servico | decimal(10,2) | taxa de serviço em R$ (default 0) |
| active | bool | |
| timestamps | | |

- **Unique** `(codigo_tll, exercicio)` — um valor por código por exercício.
- Versionado por exercício: o valor muda por ano sem deploy; o histórico é preservado (nunca sobrescreve o exercício anterior).

### Parâmetro administrável

- `tll.fator_multiplicador` (decimal, default 1.0) — fator aplicado quando o CNAE tem `exige_fator_multiplicador`. **Ponto aberto:** se o fator for por CNAE (valor distinto por CNAE), vira coluna em `cnaes`; por ora é um parâmetro global — confirmar com a SEDUR.

## 4. CRUD (retaguarda, HU-014)

- Tela em **Configuração** (grupo existente — regras do menu): lista os valores TLL por exercício, com criar/editar/ativar/desativar.
- Permissão nova `manter-tll` (ou reuso de uma existente de configuração — decidir no plano).
- Validação: código TLL obrigatório, exercício obrigatório (ano), valor ≥ 0, unicidade (código, exercício) com mensagem clara.
- Auditoria RN-002: criar/editar/ativar/desativar registram usuário, data/hora, valor anterior/novo.
- Sem exclusão física de exercício com valor referenciado por DAM — desativa (histórico preservado).

## 5. Cálculo do valor do processo (RN-004)

Serviço `TllCalculoService` (ou similar):

1. Para cada CNAE do processo, resolve o `codigo_tll` do enquadramento (planilha vigente) e o `valor` do exercício corrente.
2. `valor_dam = max(valores)` (atividade de maior valor) `+ taxa_servico` (do registro de maior valor).
3. Se o CNAE de maior valor tem `exige_fator_multiplicador` → `valor_dam × tll.fator_multiplicador`.
4. Sem valor parametrizado para o exercício → degradação honesta: o valor fica **pendente** (nunca inventado), a ficha mostra "Pendente — tabela do exercício não parametrizada".

A **ficha** passa a mostrar o valor real resolvido por CNAE (em vez de "Pendente (tabela de taxas/DAM)") quando há valor parametrizado para o exercício.

## 6. Envio à SEFAZ (bloqueado — HU-110)

- Ao deferir, o listener `EnviarViabilidadeSefaz` (já existe, no `ResultadoEmitido`) monta o payload **incluindo o bloco `taxas`** calculado (documento, codigoTLL SEFAZ, valor, dataTaxa, servico, codigoServico).
- O `SefazViabilidadeGateway` segue `Unavailable` → audita `bloqueado` (pendente), **nunca** finge envio.
- Quando a SEFAZ liberar o endpoint/credenciais (Fase 13), troca **só o binding** — nenhum call site muda.

## 7. Fora de escopo (registrado)

- Geração do DAM (código de barras FEBRABAN, PDF) — HU-071 escopo revisado: o DAM é emitido pela SEFAZ.
- Confirmação de pagamento (HU-072).
- Fator multiplicador por CNAE (valor distinto por CNAE) — pendente de confirmação SEDUR; por ora parâmetro global.

## 8. Critérios de aceite

- **CA-01** CRUD de valores TLL por exercício na retaguarda, com validação, unicidade (código, exercício) e auditoria.
- **CA-02** A ficha mostra o valor real da TLL por CNAE quando há valor parametrizado para o exercício corrente; sem valor, mostra pendência honesta.
- **CA-03** O cálculo do valor do processo segue a RN-004 (maior valor + taxa de serviço + fator quando o CNAE exigir), coberto por teste.
- **CA-04** Ao deferir, o bloco `taxas` é calculado e o envio à SEFAZ é tentado via gateway `Unavailable` → auditado `bloqueado`, nunca "enviado".
- **CA-05** Sem valor parametrizado para o exercício, o cálculo degrada para pendente (nunca inventa valor).

## 9. Pontos abertos (confirmar com SEDUR/SEFAZ)

1. O **fator multiplicador** é um valor global ou por CNAE?
2. A **taxa de serviço** é por código TLL ou um valor único por exercício?
3. O **endpoint de envio** da SEFAZ e as credenciais SenhaWeb (HU-110) — bloqueio externo.
