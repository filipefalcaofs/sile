# Renovação de Viabilidade de Localização — Design

**Data:** 2026-09-28
**Fonte de negócio:** Documento de Requisitos "Renovação de Viabilidade de Localização" (Acto/SEDUR, Lisa Cerqueira, 14/02/2026, v1.0)
**Status:** Design aprovado nas 4 seções; aguardando revisão da spec antes do plano de implementação.

## Contexto

A Renovação de Viabilidade prorroga o prazo de um TVL **provisório** já deferido, de empresa já constituída. É etapa prévia obrigatória à renovação do Alvará de Funcionamento na SEFAZ. O Salvador Simplifica não existe mais — **este sistema (Viabiliza) o substituiu**, então o fluxo que o documento mandava para o Simplifica passa a ser deste portal.

Regras centrais do documento que o design preserva:

- Endereço e atividades **não** são alterados na renovação.
- **Não** gera nova numeração de viabilidade — só uma nova data de validade.
- Todo processo vai para **análise semi-expressa** (crítica do analista, sem deferimento automático).
- O analista define se a viabilidade liberada continua **provisória** ou passa a **definitiva**.
- No deferimento, o sistema envia à SEFAZ o status e a nova data de validade.
- A TLL/DAM só é emitida **depois** da alteração da data de validade.
- Condicionantes do TVL provisório não atendidos → indeferimento.

## Decisões tomadas (com o usuário)

| Decisão | Escolha |
|---|---|
| Escopo | Fluxo completo: portal do cidadão + análise semi-expressa + nova validade + envio SEFAZ (gateway hoje bloqueado/auditado, Fase 13) |
| Origem do TVL | **Só a deste sistema.** O documento chama de origem Simplifica; o Viabiliza substituiu esse sistema, então a renovação acontece aqui. TVL de origem SIGS continua sendo renovado no SIGS |
| Validade/tipo | Campos na decisão: `tvl_kind` (provisória/definitiva) + `tvl_valid_until`, preenchidos na conclusão da análise |
| Identificação | A partir das empresas do cidadão: painel lista TVLs provisórios elegíveis com botão **Renovar** |
| Fluxo do cidadão | Wizard curto em 4 etapas |
| Declarações | **Parametrizáveis** pela gestão (catálogo por tipo de serviço, versionado) |
| Bloqueios | Todos os 4 do documento + só TVL provisória elegível |
| Análise | Ficha **simplificada** focada em condicionantes + tipo/validade na decisão |

## 1. Visão geral e jornada

O cidadão entra no portal (gov.br ou e-mail/senha), abre **Meu painel** e vê, nas empresas dele, os TVLs provisórios **deste sistema** com o botão **Renovar**. TVL nascido no SIGS não aparece nesta lista: a renovação dele continua no SIGS. Toca, cai num wizard de 4 passos, confirma, marca as declarações e protocola. O processo vai para análise semi-expressa; o analista confere condicionantes e, ao deferir, define tipo e nova validade. O sistema registra e tenta enviar status + nova data à SEFAZ. TLL/DAM sai depois da alteração da data.

Princípio de UX: o cidadão **não redigita o que o sistema já sabe**. Endereço e atividades vêm preenchidos e são somente leitura.

## 2. Portal do cidadão — wizard (mobile-first)

### Elegibilidade — `RenovacaoElegibilidadeService`

Avalia cada TVL do cidadão e devolve `elegivel` ou o motivo do bloqueio. Regras:

1. existe viabilidade vinculada ao CNPJ;
2. a viabilidade é do tipo **provisória** (definitiva não renova);
3. não foi renovada nos últimos **3 meses** (parâmetro `renovacao.intervalo_meses`, default 3);
4. não há outra renovação do mesmo CNPJ **em análise** — mensagem: "Para cadastrar novo processo de renovação, aguarde a conclusão da análise ou solicite desistência do processo em análise."

TVL bloqueado aparece na lista **sem** o botão, com o motivo em linguagem simples. Nunca falha silenciosa.

### Wizard — 4 passos, um por tela

1. **Confirmar TVL** — cartão somente leitura: empresa, endereço, atividades (CNAEs). Ajuda: "Confira se é esta a viabilidade que você quer renovar. Endereço e atividades não podem ser alterados na renovação."
2. **Contato** — e-mail, telefone (obrigatório), celular. Pré-preenchidos; cidadão só corrige. Máscara e teclado numérico no mobile.
3. **Declarações** — checkboxes obrigatórios do catálogo parametrizável. Todos marcados para avançar.
4. **Revisar e protocolar** — resumo + botão **Protocolar renovação**. Gera o número do processo e mostra o protocolo com próximos passos.

### Responsividade e acessibilidade

Stepper vira barra de progresso compacta no mobile (`flex-col` → `sm:flex-row`, padrão do wizard atual). Alvos de toque ≥44px, um campo por linha, botão primário ao alcance do polegar. Reusa `accessibility-bar` (skip links, contraste, fonte), VLibras, `aria-current="step"`, erros associados ao campo. Sem tabela larga no mobile — dados do TVL em cartão empilhado. Funciona em desktop, tablet e mobile.

## 3. Gestão — análise semi-expressa

**Entrada na fila.** A renovação protocolada entra na caixa de entrada/fila existente com categoria **semi-expresso** (reusa `AnalysisCategory::semi_expresso`, gatilhos, SLA e distribuição atuais). O tipo `renovacao-tvl` identifica o processo como renovação (badge).

**Ficha simplificada** (endereço/atividades não mudam, então não reavalia zoneamento do zero):

- **Dados do TVL** (somente leitura): empresa, endereço, atividades, nº do TVL, validade atual, tipo atual.
- **Condicionantes do TVL provisório**: checklist; algum não atendido → caminho de indeferimento.
- **Decisão**: Deferir / Indeferir (reusa `AnaliseTecnicaDecisionService`).
  - **Deferir** exige **tipo liberado** (provisória/definitiva) + **nova data de validade**. O analista define — não é automático.
  - **Indeferir** exige fundamentação (padrão atual).

**Após a decisão.** Deferido: grava validade + tipo na decisão, audita (RN-002), dispara envio à SEFAZ (status + nova data) pelo gateway — hoje `UnavailableSefazViabilidadeGateway`, que audita `bloqueado` sem fingir sucesso. Emissão da TLL/DAM fica atrás da alteração da data (integração SEFAZ, Fase 13 — pendência registrada, sem fachada).

## 4. Modelo de dados, declarações, parâmetro e testes

### Modelo de dados (mínimo)

- `viability_decisions` (1:1, append-only): adicionar `tvl_kind` (`provisoria`/`definitiva`) e `tvl_valid_until` (date). Preenchidos na conclusão da análise da renovação. `tvl_product_number` já existe e **não** muda.
- `viability_requests`: adicionar `renews_viability_request_id` (nullable, FK para a solicitação de origem) — amarra a renovação ao TVL original e alimenta as regras "já em análise" e "renovada há <3 meses". A renovação é uma `ViabilityRequest` com `service_type_id` = `renovacao-tvl`, `origin` = `portal`.
- Aceite de declarações: grava qual texto, versão, data/hora e usuário (padrão `TermAcceptance`/auditoria).

### Declarações parametrizáveis

Catálogo por tipo de serviço (reuso de `LegalTerm` ou `service_declarations`): texto, versão, obrigatória, vigência, `service_type_id`. Gestão edita sem deploy (CRUD em Configuração). O wizard renderiza as declarações vigentes de `renovacao-tvl`; todas obrigatórias. Versionadas — o aceite guarda a versão exata exibida.

### Parâmetro

`renovacao.intervalo_meses` (default 3) no `ParameterSeeder` — gestor muda o intervalo mínimo sem deploy. Único parâmetro novo; o resto fica no código (conforme regra de parametrização).

### Testes (TDD, feature tests PHPUnit)

- **Elegibilidade:** cada bloqueio (sem vínculo, definitiva, <3 meses, já em análise) e o caso elegível.
- **Portal:** abrir wizard só com TVL elegível; confirmar; exigir todas as declarações; protocolar gera processo semi-expresso com `renews_viability_request_id`.
- **Gestão:** ficha exige tipo + validade no deferimento; indeferimento exige fundamentação; condicionante não atendido → indeferido.
- **Decisão:** grava `tvl_kind`/`tvl_valid_until`, audita, dispara envio SEFAZ (gateway fake só no teste).
- **Regra do documento:** endereço/atividades não alterados na renovação.

## Fora de escopo / pendências (sem fachada)

- Envio real à SEFAZ e emissão de DAM/TLL: gateway `Unavailable` hoje; integração real é Fase 13, registrada como pendência. Nada é simulado.
- TVL de origem SIGS: a renovação permanece no SIGS. Este módulo não consulta, não importa e não protocola esse acervo.

## Convenções

- TDD estrito (Red-Green-Refactor); cada regra vira feature test.
- UI, mensagens, commits e documentação em pt-BR; código em inglês.
- `vendor/bin/pint --dirty --format agent` após alterar PHP.
- Item de navegação (se novo) entra só em `gestao-nav.ts`, no grupo adequado, e `assertGestaoNavHealth()` deve continuar verde.
