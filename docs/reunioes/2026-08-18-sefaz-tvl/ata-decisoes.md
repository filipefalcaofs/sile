# Ata — Reunião SEDUR / Simplifica / Viabilidade (2026-08-18)

**Participantes:** Dayvson Reis (SEFAZ / WS SIGS) e Filipe Falcão (Sudoeste Fábrica / Viabiliza).
Lisa (SEDUR) não conseguiu entrar.
**Objetivo:** alinhar a integração de consulta de TVL que a SEFAZ consome hoje do SIGS/Simplifica
e que o Viabiliza passará a expor ao substituir o Simplifica.
**Fonte:** transcrição revisada (`transcricao.md`) + arquivo de contrato enviado por Dayvson
(`contrato-endpoint-tvl.md`).

---

## 1. Contexto

- O Viabiliza vai **substituir o Simplifica** na ponta da SEDUR. Dayvson (SEFAZ) não tem acesso ao
  Simplifica e não sabe se há questão contratual — o entendimento de trabalho é fazer tudo
  **independente do Simplifica**.
- Hoje a SEFAZ consome dados de TVL via **WS do SIGS** (`ws-sigs.pms.ba.gov.br`). Esse consumo
  sustenta a **liberação de alvará** e a **geração do DAM** — está em produção e é crítico.
- O fluxo atual é uma "ponte": a consulta bate na SEDUR (SIGS); se não encontra o TVL, vai a um
  serviço da SEFAZ. O contrato de resposta é o mesmo nos dois caminhos.
- Lacunas de conhecimento do processo: alinhar com a **Lisa (SEDUR)**; o que faltar, marcar
  reunião com a SEFAZ para levantar os serviços.

## 2. Decisões

1. **O Viabiliza expõe um endpoint de consulta de TVL** para o SIGS/SEFAZ consumir — não é a SEFAZ
   que disponibiliza; é o Viabiliza que responde.
2. **Contrato de resposta = o mesmo JSON atual** do `buscarPorNumeroTVLRegin` (ver
   `contrato-endpoint-tvl.md`). Pedido explícito do Dayvson: hoje ele faz "inúmeras
   transformações" no retorno; se o Viabiliza já responder nesse padrão, elimina esse trabalho.
   **Fechado: o Viabiliza manda nesse padrão.**
3. **404 = não encontrado.** Se o Viabiliza responder 404, a SEFAZ trata como "TVL não encontrado"
   e não busca em outro lugar.
4. **Enum de status fixo** (00 não encontrado, 01 concedido, 02 inválido/não deferido,
   03 utilizado, 99 erro de processamento) — repassado por Dayvson, documentado no contrato.
5. **Taxas são obrigatórias no retorno** — é com o bloco `taxas` (documento, codigoTLL, valor,
   dataTaxa, serviço, codigoServico) que a SEFAZ **gera o DAM**. Cada evento (viabilidade,
   renovação, inclusão de atividade) gera uma taxa.
6. **De/para de serviços:** códigos de serviço do SIGS seguem o padrão `S` + número
   (ex.: `S2253362`). O Viabiliza precisará manter de/para entre seus tipos de serviço e os códigos
   SIGS.
7. **Tipo de processo e tipo de imóvel** podem ser refeitos conforme as **tabelas domínio do
   SIGS** — Dayvson preferiu assim, deixa o de/para transparente dos dois lados.

## 3. Fatos de domínio levantados

- TVLs do **Simplifica** começam com `20` (casa dos milhões); TVLs **SEDUR/SIGS** na faixa
  ~40 mil. A numeração é a forma visual de distinguir a gestão de origem. O campo
  `tvlSimplifica` no JSON marca essa origem.
- Existe **migração de TVL SEDUR → Simplifica** com rotina própria
  (`GET /migracao-simplifica/{tvl}`), com dados específicos (o Simplifica foi feito sem olhar o
  padrão SIGS, então há de/para de serviços nesse fluxo também).
- O SIGS é o "carro-chefe": as integrações da prefeitura passam por ele e sempre existe um
  de/para a fazer.
- Cadeia de documentos citada: **IBAP** (pré-TVL) → **TVL** (pré-requerimento) → documento para
  o DAM.

## 4. Pendências

| # | Pendência | Responsável |
|---|---|---|
| 1 | Enviar o JSON de exemplo do endpoint de **migração** (`/migracao-simplifica/{tvl}`) | Dayvson |
| 2 | Enviar a lista de **status** do TVL | Dayvson — **cumprido no arquivo** (enum documentado) |
| 3 | Alinhar com a Lisa o conhecimento que a SEDUR detém do processo; lacunas → reunião com SEFAZ | Filipe |
| 4 | Definir de/para de **códigos de serviço** SILE ↔ SIGS e fonte das tabelas domínio (tipo de processo, tipo de imóvel) | Filipe + Dayvson |
| 5 | Definir autenticação/segurança do endpoint do Viabiliza (o WS SIGS atual aparenta ser aberto na rede interna) | Filipe |

## 5. Encaminhamento

- Spec de design do endpoint: `docs/superpowers/specs/2026-08-18-endpoint-consulta-tvl-sefaz-design.md`.
