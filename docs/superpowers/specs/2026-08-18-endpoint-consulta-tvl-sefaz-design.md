# Endpoint de consulta de TVL para SIGS/SEFAZ — design

> Rascunho gerado da reunião de 2026-08-18 com Dayvson Reis (SEFAZ).
> Fontes: `docs/reunioes/2026-08-18-sefaz-tvl/` (ata, contrato, transcrição).

## Contexto

A SEFAZ consome hoje dados de TVL via WS do SIGS
(`GET /viabilidades/v2/buscarPorNumeroTVLRegin/{tvl}`) para duas operações críticas:
**liberação de alvará** e **geração do DAM** (a partir do bloco `taxas`). Com o Viabiliza
substituindo o Simplifica na ponta da SEDUR, **o Viabiliza passa a ser o respondedor** dessa
consulta.

Decisões fechadas na reunião:

1. O Viabiliza expõe endpoint de consulta de TVL; o SIGS/SEFAZ consome.
2. O JSON de resposta segue **exatamente o padrão atual** do WS SIGS (elimina as
   transformações que a SEFAZ faz hoje).
3. **404 = TVL não encontrado** — a SEFAZ não faz fallback.
4. Enum de status fixo: `00` não encontrado, `01` concedido, `02` inválido/não deferido,
   `03` utilizado, `99` erro de processamento.
5. `taxas` é obrigatório e alimenta o DAM; cada evento (viabilidade, renovação, inclusão de
   atividade) gera uma taxa com `codigoServico` no padrão SIGS (`S` + número).
6. Tipo de processo e tipo de imóvel seguem as **tabelas domínio do SIGS**.

## Escopo

### Endpoint

`GET /api/integracao/tvl/{numeroTvl}` (path final a definir — ver `[OPEN-TVL-1]`).

Resposta 200: JSON no contrato documentado em
`docs/reunioes/2026-08-18-sefaz-tvl/contrato-endpoint-tvl.md`:

- `resposta`: `protocolo` (= número do TVL), `status` (enum acima), `mensagem`,
  `cnpj_instituicao`.
- `empresa`: endereço completo, inscrição imobiliária, área utilizada, porte, processo,
  `tipoTVL`, `zona`, `via`, `hashDam`, `observacao`/`condicionantes` (texto legal completo do
  produto), datas de viabilidade (`yyyyMMdd HHmmss`), `nome_impresso_TVL`, `tipo_viabilidade`,
  `sede_escritorio_virtual` (`"0"`/`"1"`), `grupo_cnae` (código, tipo, categoria de risco,
  descrição), `tvlSimplifica`, `taxas`, `bap`.

Resposta 404: TVL inexistente — corpo com `resposta.status = "00"` e mensagem
"TVL não encontrado." (`[OPEN-TVL-2]`).

### Mapeamento de status (SILE → enum SIGS)

| Situação no Viabiliza | Código | Mensagem |
|---|---|---|
| TVL não encontrado | `00` | TVL não encontrado. |
| Viabilidade deferida / TVL emitido | `01` | TVL concedido. |
| Viabilidade indeferida | `02` | TVL Inválido, não deferido. |
| TVL já utilizado (alvará emitido) | `03` | TVL Utilizado. |
| Falha interna | `99` | Erro no processamento. |

O mapeamento exato dos status internos do Viabiliza para `01`/`02`/`03` depende do desenho de
status da análise já existente (ver spec `2026-07-14-status-analise-processo-design.md`) —
detalhar no plano.

### Taxas e de/para de serviços

- O Viabiliza precisa **gerar e guardar taxas por evento** (viabilidade, renovação, inclusão de
  atividade) com: documento, codigoTLL, valor, dataTaxa, descrição do serviço e
  `codigoServico` SIGS.
- `codigoServico` segue o padrão `S` + número do SIGS ⇒ tabela de **de/para** entre os tipos
  de serviço do Viabiliza e os códigos SIGS, **parametrizável pelo admin** (regra de
  parametrização máxima — valores de negócio não ficam hardcoded).
- Valores de taxa: verificar se já existe tabela de taxas no domínio ou se entra como dado
  versionado novo (`[OPEN-TVL-3]`).

### Domínios SIGS

Tipo de processo e tipo de imóvel seguem as tabelas domínio do SIGS. Obter as tabelas com o
Dayvson e cadastrar como domínio parametrizado (`[OPEN-TVL-4]`).

### Auditoria e parametrização (transversal)

- Toda consulta ao endpoint registrada na trilha de auditoria (RN-002): origem (IP/cliente),
  TVL consultado, status retornado, data/hora.
- Feature toggle da integração (`ConfigIntegracao` ou domínio equivalente): desativação
  degrada de forma controlada e comunicada (resposta `99` + log), nunca falha silenciosa.
- Credencial de acesso do consumidor criptografada, com tela de administração e teste de
  conexão.

## Fora de escopo (por ora)

- **Endpoint de migração** (`/migracao-simplifica/{tvl}`): contrato ainda não recebido —
  pendência com o Dayvson. Fica **bloqueado** até o JSON de exemplo chegar; registrar no
  ROADMAP/STATE.
- Numeração de TVL no Viabiliza (faixa própria × continuidade das faixas SEDUR/Simplifica) —
  decisão de domínio separada.

## Questões em aberto

| ID | Questão |
|---|---|
| `[OPEN-TVL-1]` | Path e versionamento do endpoint no Viabiliza (`/api/integracao/tvl/{n}`? manter `buscarPorNumeroTVLRegin` por compatibilidade?) e quem chama direto: SEFAZ ou SIGS como ponte. |
| `[OPEN-TVL-2]` | 404 com corpo JSON (`status: "00"`) ou 404 seco? Combinado em reunião foi "404 = não encontrado"; confirmar se o consumidor lê o corpo. |
| `[OPEN-TVL-3]` | Taxas: já existem no modelo do Viabiliza ou é domínio novo? Quem calcula o valor (tabela SEFAZ? parâmetro SEDUR?) e quando a taxa é gerada no fluxo. |
| `[OPEN-TVL-4]` | Obter tabelas domínio do SIGS (tipo de processo, tipo de imóvel, porte) com o Dayvson. |
| `[OPEN-TVL-5]` | Autenticação do endpoint: o WS SIGS atual aparenta ser aberto na rede interna. Viabiliza deve exigir token de integração? Envolve `especialista-seguranca` (endpoint de integração com dados pessoais — LGPD). |
| `[OPEN-TVL-6]` | Significado/preenchimento de `hashDam`, `bap`, `tipo_viabilidade`, `tipo_cnae` e `categoria` (padrão `nR1-01`) — confirmar fonte de cada campo no modelo Viabiliza. |
| `[OPEN-TVL-7]` | `tvlSimplifica` no mundo Viabiliza: vira flag de "origem" (migrado do Simplifica × nativo Viabiliza)? |

## Critérios de aceite (rascunho)

1. `GET` em TVL deferido retorna 200 com JSON no contrato, incluindo `grupo_cnae`,
   `condicionantes` e `taxas` completos.
2. TVL inexistente retorna 404 (e `status: "00"` se `[OPEN-TVL-2]` confirmar corpo).
3. TVL indeferido retorna `status: "02"`; utilizado retorna `"03"`.
4. Taxas trazem `codigoServico` resolvido via de/para parametrizável.
5. Toda chamada auditada; integração desligável por toggle com degradação controlada.
6. Golden test com o JSON real do TVL `400143` (exemplo do contrato) como fixture.
