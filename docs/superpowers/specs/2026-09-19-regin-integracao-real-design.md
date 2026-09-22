# Spec — Integração REGIN/Juceb real (entrada e saída)

Data: 2026-09-19
Origem: conversa desta data + manual da API em `~/Downloads/Manual-API-REGIN-JUCEB-SEDUR` (Guias PSCS v2.10, ofício SEDUR, OpenAPI de produção). Fonte de verdade é o contrato da Juceb e o código — não as HUs (a base documental de HUs será reconstruída depois).

## 1. Decisões fechadas

1. O SILE fala com a API `api_integracao` da PSCS/JUCEB nos dois sentidos: recebe o processo em `POST /api_integracao/recebe` (já existe) e devolve o parecer em `POST {base}/recebe` com header `JWT` (novo).
2. Autenticação de saída: `POST {base}/acesso/auth` com `username`/`password` → `token`; demais chamadas usam o header `JWT: <token>` (não `Authorization: Bearer`). Credenciais são os parâmetros `integrations.regin.*` já administráveis (senha criptografada, nunca reexibida).
3. O parecer sai no envelope `Resposta` (`codFuncao: 110`) com `dadosProcesso` (`RespostaAnaliseInstituicaoDto`): `FINALIZA_PROCESSO: 1`, `PROCESSO_INTERESSE_INSTITUICAO: 1`, `ANALISES.AREA[0]` com `STATUS_ANALISE` 2 (deferido) ou 4 (indeferido) e `JUSTIFICATIVA_ANALISE` com a fundamentação. Sucesso = corpo `RECEBIDO_SUCESSO`.
4. A troca é só o binding: `ReginParecerNotifier` passa de `UnavailableReginParecerNotifier` para o provider HTTP. O listener `ComunicarResultadoRegin` e a auditoria `sucesso`/`bloqueado` não mudam.
5. A entrada deixa de depender do catálogo do simulador: o processo é protocolado a partir do `json` (RUC) do envelope. O que faltar degrada para análise — nunca inventa dado.
6. Homologação é contra os endpoints `/teste/*` da Juceb com evidência registrada. O simulador (`/gestao/risco/simulacao-regin`) só sai depois de entrada e saída validadas em homologação real.

## 2. O que já existe (não refazer)

- `POST /api_integracao/recebe` com códigos `3` (recebido) / `5` (duplicado), persistência do envelope em `regin_recebimentos`, auditoria sem dado pessoal no log.
- Tela **API REGIN** (`/gestao/config-regin`) com URLs, usuário, senha e teste de conexão (`/acesso/auth`).
- Listener `ComunicarResultadoRegin` (ShouldQueue) no `ResultadoEmitido`: chama o notifier, audita `sucesso` ou captura `ReginUnavailableException` e audita `bloqueado`. A decisão jamais falha pela integração.
- Parâmetros `integrations.regin.em_producao`, `.url_homologacao`, `.url_producao`, `.usuario`, `.senha` (sensitive, `requires_connection_test`).

## 3. Saída — parecer ao REGIN

### 3.1 `ReginHttpClient`

Cliente fino sobre `Http` do Laravel:

- `token()`: `POST {base}/acesso/auth` com usuário/senha dos parâmetros; devolve o JWT. Sem cache além da chamada (o token é curto e o volume é baixo — um parecer por vez).
- `enviarParecer(array $resposta)`: `POST {base}/recebe` com header `JWT` e o envelope `Resposta`. Sucesso só quando o corpo é `RECEBIDO_SUCESSO`; qualquer outro corpo, HTTP não-2xx ou `ConnectionException` lança `ReginUnavailableException` com a mensagem sanitizada (nunca token nem senha).
- Timeout vem de `config('sile.integrations.regin.timeout')`.

### 3.2 `HttpReginParecerNotifier implements ReginParecerNotifier`

Monta o `Resposta` a partir da `ViabilityRequest` + `ViabilityDecision`:

| Campo | Origem |
|---|---|
| `protocolo` | `external_reference` do processo (protocolo da Junta) |
| `servico` | `WsProSol098` |
| `cnpjDestino` / `cnpjOrigem` / `cnpjEmpresa` | CNPJ da Prefeitura (parâmetro novo `integrations.regin.cnpj_prefeitura`, default `13927801000149`) |
| `codFuncao` | `110` |
| `dataGeracao` | ISO 8601 UTC |
| `dadosProcesso.PROTOCOLO` | mesmo protocolo |
| `dadosProcesso.CNPJ_INSTITUICAO` | CNPJ da Prefeitura |
| `dadosProcesso.DATA_GERACAO` | `yyyymmdd` |
| `dadosProcesso.FINALIZA_PROCESSO` | `1` |
| `dadosProcesso.PROCESSO_INTERESSE_INSTITUICAO` | `1` |
| `dadosProcesso.GERA_DOCUMENTO_PROCESSO` / `GERA_DOCUMENTOS_AREAS` | `0` (o TVL fica no backoffice) |
| `dadosProcesso.ANALISES.AREA[0].STATUS_ANALISE` | `2` deferida, `4` indeferida |
| `dadosProcesso.ANALISES.AREA[0].JUSTIFICATIVA_ANALISE` | fundamentação da decisão (texto corrido) |
| `dadosProcesso.ANALISES.AREA[0].DATA_ANALISE` | `yyyymmdd` da decisão |

Sem `LICENCAS`/`LINKS` nesta etapa (boleto/alvará são de outras áreas; `CODIGO_LINK` exige tabela da PSCS).

### 3.3 Binding

`AppServiceProvider`: `ReginParecerNotifier::class` → `HttpReginParecerNotifier::class`. O `UnavailableReginParecerNotifier` deixa de ser o binding padrão e passa a existir só como referência de teste.

## 4. Entrada — protocolar a partir do RUC

`ReginRecebeService::receive()` hoje chama `materializarDoCatalogo()`. Passa a chamar um `ReginProcessoProtocolador` novo que lê o `json` (RUC) do envelope:

| Dado do processo | Fonte no RUC |
|---|---|
| CNAEs (principal + secundários) | `rowset.GROUPRUC_ACTV_ECON.RUC_ACTV_ECON[]` (`RAE_TAE_COD_ACTVD`, `RAE_CALIF_ACTV` 1=principal) |
| Endereço (logradouro, número, complemento, bairro, CEP) | `rowset.RUC_ESTAB` (`RES_DIRECCION`, `RES_NUME`, `RES_IDENT_COMP`, `RES_URBANIZACION`, `RES_ZONA_POSTAL`) |
| Área utilizada | `rowset.RUC_ESTAB.RES_AREA` |
| Inscrição imobiliária | `rowset.GROUPRUC_GEN_PROTOCOLO.RUC_GEN_PROTOCOLO[]` com `RGP_TGE_COD_TIP_TAB = 5` |
| Empresa (CNPJ, nome) | `rowset.RUC_GENERAL` (`RGE_CGC_CPF`, `RGE_NOMB`) |

Regras:

- CNAE principal ausente, área ausente ou endereço insuficiente → o recebimento persiste (ack `3`) mas **não** protocola; fica pendência visível para análise, nunca processo inventado.
- Empresa é localizada/criada pelo CNPJ (mesma regra do import REDESIM existente).
- O processo nasce com `origin: regin`, `contingency_reason: regin_recebe`, `external_reference` = protocolo da Junta, e segue o motor normal (risco → expresso ou análise).
- O polígono não vem no RUC; o processo entra sem `property_polygon_geojson` e o território degrada para análise quando a zona for exigida (mesmo comportamento honesto de hoje).

`ReginProtocoloSimulacaoService` e o catálogo (`protocolos-sedur.json`) deixam de ser chamados pelo `recebe` e passam a existir só para a tela de homologação do motor.

## 5. Homologação com a Juceb

Comando Artisan `regin:homologar` (ou botão na tela API REGIN) que, com as credenciais configuradas:

1. Gera o token (`/acesso/auth`).
2. Valida um parecer de mentira em `/teste/validaResposta` (não grava produção).
3. Roda `/teste/testeRecebimento` com um `Resposta` real de um processo de homologação.
4. Registra cada chamada na auditoria (`integracoes`/`regin-homologacao`) com o resultado — evidência de que a integração foi validada contra o ambiente real.

## 6. Fora de escopo (pendências PSCS/Juceb)

- Anexo **Dados do Processo v2_08** (layout fino do `json`): o mapeamento da seção 4 cobre o essencial; campos não mapeados degradam para análise.
- Autenticação de **entrada** no `/recebe` do SILE (IP vs token) — sem resposta da Juceb; hoje aberto com rate limit.
- BAP no payload, tabela oficial de `CODIGO_AREA`/`CODIGO_LINK`.
- Remoção do simulador e da flag `features.simulacao_protocolo`: só após homologação real de entrada + saída.

## 7. Testes

- `HttpReginParecerNotifierTest`: `Http::fake` prova o envelope exato (codFuncao 110, STATUS_ANALISE 2/4, header `JWT`), sucesso em `RECEBIDO_SUCESSO`, e exceção em corpo inesperado/HTTP 500/timeout — sem vazar token.
- `ComunicarResultadoReginListenerTest`: com o provider HTTP fakeado, audita `sucesso`; com exceção, audita `bloqueado` (já existe — ajustar para o binding novo).
- `ReginProcessoProtocoladorTest`: RUC completo protocola com CNAEs/endereço/área/inscrição; RUC sem CNAE principal ou sem área não protocola e registra pendência; empresa reusada por CNPJ.
- `ReginRecebeEndpointTest`: o POST passa a protocolar pelo RUC (não pelo catálogo); duplicado continua `5`.
- `ReginHomologacaoTest`: o comando chama os endpoints `/teste/*` e audita.

## 8. Critério de pronto

- Parecer de um processo real chega ao REGIN de homologação com `RECEBIDO_SUCESSO` e auditoria `sucesso`.
- Um envelope com RUC real protocola o processo sem tocar o catálogo do simulador.
- Suíte verde; `vendor/bin/pint --dirty` limpo.
