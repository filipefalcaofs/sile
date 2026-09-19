# Spec — Enquadramento pela planilha 20.08.26; Quadro 7 sai do sistema

Data: 2026-09-19
Origem: conversa desta data (a planilha decide o enquadramento; o 7 não indefere; o módulo do 7 deixa de existir).
Substitui, no ponto de enquadramento: `2026-08-28-motor-risco-planilha-20-08-design.md` §3 MR-03 e §4.2 (a subcategoria deixa de ser saída do Quadro 7).
Abordagem: **A + remoção do módulo Quadro 7**.

## 1. Decisões fechadas

1. A planilha 20.08.26 resolve o ramo. Esse ramo **é** o enquadramento (grupo, subgrupo, código LOUOS, risco, fluxo, TLL, condicionantes).
2. O Quadro 7 **não entra** no caminho de decisão. Não há fallback para `louos_quadro7_faixas`.
3. O **módulo Quadro 7 sai do sistema**: domínio, tabela, import, conversor, seeder, tela, CSV, testes de lookup.
4. Quadros **10** e **11A** permanecem. Os dois podem indeferir: o 10 pela zona, o 11A pela via (`Não` = vedado naquela classe de via, mesmo se a zona permitir).
5. Os códigos `nR1-*`, `nR2-*`, `nRa-*`, `ID*` continuam — vocabulário da Lei nº 9.148/2016 e chave do 10 e do 11A. Não dependem de uma tabela chamada Quadro 7.
6. Fundamentação cita a LOUOS e o subgrupo obtido. Não cita “Quadro 7 consultado” nem versão `louos_quadro7`.
7. O consolidado do 11A é corrigido: `Não` veda, `R` vai à CNLU (análise), condição textual vira condicionante. Hoje qualquer texto vira condicionante — errado.

## 2. Problema

O motor atual faz `CNAE + área → louos_quadro7_faixas → grupo/subgrupo → Quadro 10`. A tabela do 7 é uma projeção achatada da planilha: um CNAE só pode ter uma faixa não sobreposta, e o conversor descarta o escritório `07.12.13` quando existe outro uso. A planilha, porém, já traz o enquadramento por ramo (perguntas + área + tipo de imóvel). Consultar o 7 depois disso relê o mesmo dado, pior.

O 7 nunca produziu `nao_permitido`. Só devolve grupo/subgrupo ou `pendente`. Quem indefere é o território: **Quadro 10** (zona) e **Quadro 11A** (via). Permitido na zona e `Não` na via = `nao_permitido`.

O consolidado atual trata qualquer texto do 11A como condicionante (`permitido_com_condicoes`). A matriz oficial é Sim / Não / R. `Não` veda. `R` vai à CNLU. Só o Sim com condição textual vira condicionante.

## 3. Pipeline

```
território (zona, via)
  → TratamentoRamoResolver (CNAE + perguntas + área utilizada + tipo de imóvel)
  → ramo: grupo, subgrupo, codigo_louos, risco, fluxo, tll, condicionantes
  → Quadro 10 (zona × grupo/subgrupo) → proibido = nao_permitido
  → Quadro 11A (via × grupo) → Não = nao_permitido; R = análise; condição = permitido_com_condicoes
```

Sem ramo resolvido (pergunta faltando, tipo ausente/desconhecido quando a regra depende dele, linha “A SER DEFINIDO PELA CNLU”, CNAE sem binding): consolidado **pendente** / encaminhamento à análise, com motivo. Sem consultar outra tabela.

Risco e enquadramento saem do **mesmo** ramo. `RiscoClassificationService` não instancia o motor LOUOS (CA-MR-08). Recebe nível/fluxo já resolvidos ou resolve o ramo por conta própria a partir dos mesmos dados versionados — um resolver, dois consumidores.

## 4. Modelo de dados (domínio `risco_tratamento`)

Domínio novo, sensível, quatro olhos. Cabeçalho `rule_versions` reusado. Quatro tabelas tipadas por versão:

| Tabela | Conteúdo | Chave natural |
|---|---|---|
| `treatment_questions` | P1–P32: número, texto, opções, CNAEs que a disparam | `rule_version_id` + `numero` |
| `treatment_rules` | R1–R50: número, texto, ramos (expresso/semiexpresso), nível de risco por ramo, referência | `rule_version_id` + `numero` |
| `treatment_enquadramentos` | Linha da aba CNAES: código LOUOS, denominação, subcategoria, faixas de área, TLL, condicionantes | `rule_version_id` + `cnae` + `codigo_louos` + `subcategoria` |
| `treatment_cnae_bindings` | Liga CNAE → regra(s) e perguntas que o CNAE usa | `rule_version_id` + `cnae` + `regra` |

Carga inicial: `database/data/regras-20-08-26/*.csv` extraídos do xlsx. Contagens de integridade afirmadas no teste do import: 1.332 CNAEs, 2.854 linhas de enquadramento, 32 perguntas, 59 regras, 32 condicionantes, zero R45, `9900-8/00` presente.

`RuleDomain` ganha `RiscoTratamento = 'risco_tratamento'` (`isSensitive() = true`). `LouosQuadro7` sai.

## 5. `TratamentoRamoResolver`

```php
public function resolver(TratamentoRamoInput $input): TratamentoRamoResult
```

`TratamentoRamoInput` (readonly): `cnae`, `respostas` (mapa numero → bool/opção), `areaUtilizada` (`used_area_m2`), `tipoImovel` (`TipoImovel`), `data?`, `versoesOverride?` (sandbox).

`TratamentoRamoResult` (readonly): `status` (`resolvido` | `nao_resolvido`), `grupo`, `subgrupo`, `codigoLouos`, `risco` (`baixo|medio|alto`), `fluxo` (`expresso|semiexpresso|analise`), `tll`, `condicionantes[]`, `motivo` (quando não resolvido), `versaoRegra`.

Ordem de resolução:

1. Binding do CNAE na versão vigente → regra(s).
2. Avalia a regra: pergunta respondida → ramo; pergunta faltando → `nao_resolvido` com motivo.
3. Ramo que depende de área: compara `areaUtilizada` ao corte (1.250 m²). Área ausente quando a regra depende → `nao_resolvido`.
4. Ramo que depende de tipo: `TipoImovel.dirigeRegra()` decide; `Ausente`/`Desconhecido` quando a regra depende → `nao_resolvido` (CA-MR-01/01b).
5. Subcategoria `ID*` + ramo que eleva → `alto` (CA-MR-05).
6. Linha “A SER DEFINIDO PELA CNLU” → `nao_resolvido`, motivo comissão, nunca expresso.

Sem binding → `nao_resolvido` (“CNAE sem regra de tratamento vigente”).

## 6. Motor LOUOS sem o 7

`LouosEnquadramentoService::enquadrar()` deixa de chamar `enquadrarQuadro7()`. A dimensão passa a ser `enquadramento`, preenchida pelo `TratamentoRamoResolver`:

```php
$enquadramento = $this->resolverRamo($input);        // planilha
$quadro10      = $this->enquadrarQuadro10($enquadramento, $input);
$quadro11a     = $this->enquadrarCondicoesVia(RuleDomain::LouosQuadro11a, $enquadramento, $input);
$consolidado   = $this->consolidar($enquadramento, $quadro10, $quadro11a, $input);
```

`EnquadramentoResult` troca a chave `quadro7` por `enquadramento`: `{status, grupo, subgrupo, codigo_louos, faixa?, motivo, versao_regra}`. `versoes` ganha `risco_tratamento` e perde `quadro7`. Auditoria registra `rulesVersion` da planilha.

`EnquadramentoInput` ganha `respostas` e `tipoImovel` (opcionais). `paraConsulta()` mantém a assinatura; o orquestrador (`ConsultaViabilidadeService`) passa o que tiver.

Precedência do consolidado (HU-044):

1. `enquadramento` não identificado → `pendente` (motivo do ramo).
2. `quadro10` indisponível/nao_encontrado → `pendente` (sem zona não decide).
3. `quadro10.permissao = proibido` → `nao_permitido`.
4. `quadro11a` identificado com `Não` → `nao_permitido` (HU-041 RN-005).
5. `quadro11a` identificado com `R` → `pendente` (CNLU).
6. Condicionante incidente (vagas, ZEIS, condição textual da via) → `permitido_com_condicoes`.
7. Senão → `permitido`.

## 7. O que some

| Peça | Destino |
|---|---|
| `RuleDomain::LouosQuadro7` | removido |
| Tabela `louos_quadro7_faixas` + model, factory, migrations de drop | removidos |
| `LouosQuadro7ImportService`, `LouosQuadro7EnquadramentosConverter`, `LouosQuadro7Seeder` | removidos |
| CSVs `database/data/louos/**/quadro7-faixas.csv` | removidos (a fonte passa a ser `regras-20-08-26`) |
| `enquadrarQuadro7()` | removido |
| Aba/seletor Quadro 7 em `gestao/louos` (index, rascunho, sandbox, manual, CSV modelo) | removidos |
| Testes e golden de lookup por faixa do 7 | removidos ou reescritos contra o resolver |
| Chave `quadro7` em `EnquadramentoResult`, `versoes`, UI e `decision_trace` | substituída por `enquadramento` |

HU-038 (enquadrar pelo Quadro 7) deixa de ter implementação. O critério de aceite vira: enquadrar pelo ramo vigente da planilha.

## 8. O que nasce

- Domínio versionado `risco_tratamento` (quatro olhos), carga da planilha 20.08.26.
- `TratamentoRamoResolver` + `TratamentoRamoInput`/`Result`.
- Manutenção da planilha no grupo **Regras** (tela nova ou extensão do console de regras). Não reusa a UI de faixas do 7.
- `EnquadramentoResult.enquadramento` com `versao_regra` da planilha.

## 9. O que não muda

- Quadro 10: permissão por zona (`proibido` → `nao_permitido`).
- Tipo de imóvel do REGIN (`TipoImovel` / catálogo já existentes).
- Dimensão sanitária (VISA).
- Anti-fachada: sem zona → 10 indisponível → pendente; sem ramo → pendente.

## 10. UI

- Consulta de viabilidade: card “Enquadramento de uso”, sem menção a Quadro 7. Fonte = planilha + versão.
- Console LOUOS: só 10 e 11A. “Quadros LOUOS” permanece com os dois.
- Item novo em Regras para a planilha de tratamento (perguntas/ramos), no fim do grupo (simulação já está no fim).

## 11. Critérios de aceite

**CA-01** — DADO um CNAE com ramo na planilha vigente e insumos suficientes, QUANDO o motor enquadra, ENTÃO grupo/subgrupo vêm do ramo; `louos_quadro7_faixas` não é lida (tabela inexistente).

**CA-02** — DADO o mesmo CNAE com dois usos na planilha (ex. `0111-3/01` escritório vs agropecuária), QUANDO a pergunta escolhe o ramo, ENTÃO o subgrupo é o da linha escolhida, não uma faixa única achatada.

**CA-03** — DADO ramo não resolvido, QUANDO o motor consolida, ENTÃO resultado `pendente` e encaminhamento à análise; nunca permitido/não permitido.

**CA-04** — DADO zona que o Quadro 10 marca proibido para o grupo do ramo, QUANDO há território, ENTÃO `nao_permitido`.

**CA-04b** — DADO Quadro 10 permitido e Quadro 11A `Não` para o mesmo grupo na classe da via, QUANDO a via está identificada, ENTÃO `nao_permitido` (HU-041 RN-005). Não vira condicionante.

**CA-05** — Console de Quadros LOUOS não lista Quadro 7. Import/rascunho/sandbox do 7 não existem.

**CA-06** — Fundamentação e `decision_trace` não têm dimensão `quadro7`. Versão registrada é a da planilha (`risco_tratamento`).

**CA-07** — Golden da planilha (R5/R1 área ≤ 1250; área > 1250; galpão+ID; CNLU) passam no resolver. CA-MR-01..08 da spec de 28/08 continuam válidos, com subcategoria vindo do ramo, não do 7.

**CA-08** — Import da planilha é idempotente e afirma as contagens (1.332 CNAEs, 2.854 enquadramentos, 32 perguntas, 59 regras, 32 condicionantes, zero R45, `9900-8/00`).

**CA-09** — Publicação de nova versão da planilha exige quatro olhos (`RuleVersionService`).

## 12. Fora de escopo

- Trocar a carga oficial dos Quadros 10 e 11A (o CSV do 11A já tem Sim/Não/R).
- Inventar zona ou via.
- Adaptador falso de REGIN (tipo de imóvel continua stub até a integração).
- Apagar menções históricas em `.planning/phases/05-*` (arquivo morto; não é runtime).
- Quadro 11B (evolução futura, se a SEDUR pedir).

## 13. Ordem de implementação

1. Domínio `risco_tratamento` + tabelas + import da planilha (dado versionado, quatro olhos).
2. `TratamentoRamoResolver` + golden (TDD).
3. Motor LOUOS consome o ramo; drop da tabela e do domínio do 7; UI e traces sem `quadro7`.
4. Correção do consolidado do 11A (Não/R/condição).
5. Risco/fluxo/TLL do mesmo ramo.
6. Tela de manutenção da planilha no grupo Regras.
