# Escritório virtual — motor sede × abrigado — design

**Data:** 2026-07-16 · **Revisão:** 2 (OPENs SEDUR fechados + decisões nossas)  
**Origem:** Reunião SEDUR (Lisa Santos) 2026-07-16 — Meet `dhv-isxt-ibp` (~37 min).  
**Artefatos:** `docs/reunioes/2026-07-16-cliente/` (transcrição, ata, prints).  
**Status:** RASCUNHO — `[OPEN-EV-1/2/4]` fechados pela SEDUR (2026-07-16); `[OPEN-EV-3]` decidido (nossa). Pronto para plano.  
**Relacionado:**
- `2026-07-14-desfecho-analise-produto-design.md` (desvinculação em indef/cassado/revogado/desativado)
- `2026-06-14-fluxo-expresso-design.md`, `2026-06-14-analise-tecnica-design.md`
- HU-062 (imóvel/EV), HU-047 (flags CNAE EV)

> Nada de adaptador falso para SEFAZ/REDESIM. Onde a integração não estiver homologada, a feature fica **bloqueada** e visível.

---

## 1. Problema

O SILE já tem `is_virtual_office` e export de “sedes”, mas **não modela o domínio completo** que a SEDUR opera no SAPS:

1. **Sede** (empresa que cede o endereço) vs **abrigado** (empresa que usa o endereço da sede).
2. Gatilho **CNAE 8211-3/00** + pergunta “quero ser sede” → análise humana + trava de inscrição.
3. Produto do abrigado com **End. Virtual - TVL Nº** (TVL da sede) e validade alinhada à sede.
4. Lista de CNAEs permitidos para abrigados.
5. **Desvinculação da inscrição** quando a sede **muda de endereço** (bug do legado; pedido explícito).

Sem isso, o SILE não substitui o fluxo operacional de escritório virtual.

## 2. Objetivos / Não-objetivos

**Objetivos**
- Modelar sede e abrigado com regras auditáveis.
- Encaminhar sede à análise quando o gatilho dispara; permitir deferimento com flag “Sede de Escritório Virtual = Sim”.
- Travar inscrição imobiliária **somente** com CNAE 8211-3/00 **e** flag sede = Sim no deferimento.
- Vincular abrigado à TVL/viabilidade da sede; validar CNAEs permitidos; alinhar validade.
- Desvincular inscrição na mudança de endereço da sede (e nos desfechos já previstos na spec de desfecho).

**Não-objetivos**
- Telas de relatório / “enviar para análise” → specs irmãs.
- Portal SEDUR Revista Digital.
- Simular comunicação SEFAZ: se API indisponível, bloquear o passo e registrar pendência.

## 3. Conceitos

| Termo | Definição |
|---|---|
| **Sede** | Viabilidade deferida com CNAE 8211-3/00 + resposta/flag “sede de escritório virtual = Sim”; inscrição imobiliária vinculada/travada |
| **Abrigado** | Viabilidade (em geral expressa) na mesma inscrição da sede, com TVL próprio e referência ao nº TVL da sede |
| **Lista EV** | Conjunto parametrizável de CNAEs autorizados para abrigados (`autorizado_escritorio_virtual` / tabela versionada) |

Autônomo (ex.: médico em vários hospitais) **não** é escritório virtual.

## 4. Regras de negócio

### RN-EV-01 — Gatilho de sede
DADO um processo de viabilidade **padrão** que contém o CNAE **8211-3/00**  
E o requerente responde **Sim** à pergunta de regra “será sede de escritório virtual?”  
ENTÃO o processo **não** conclui no expresso e segue para **análise humana**.

### RN-EV-02 — Marcação na ficha
Na análise, o analista confirma **Sede de Escritório Virtual = Sim|Não**.  
O produto é o resultado da **última ficha**.

### RN-EV-03 — Trava de inscrição
A inscrição imobiliária só fica **travada/vinculada à sede** se, no deferimento:
- CNAE 8211-3/00 está no processo **e**
- flag sede = Sim.

Se o CNAE existe mas a flag é Não → **não trava**.

### RN-EV-04 — Condicionante no produto da sede
No deferimento da sede, o produto inclui condicionante de prestação de serviços de escritório virtual (texto parametrizável).

### RN-EV-05 — Abrigado
- N abrigados por sede.
- Mesma inscrição imobiliária da sede.
- CNAEs ⊆ lista EV; fora da lista → **bloqueio no cadastro** (mensagem clara).
- Produto traz **End. Virtual - TVL Nº** = TVL da sede.
- Validade do abrigado = validade da sede (enquanto a sede estiver ativa na inscrição).
- Pode ser **expresso** se a sede já estiver deferida/ativa no local.

### RN-EV-06 — Mudança de endereço da sede
Quando a sede **sai** da inscrição A para a inscrição B (revisão de endereço / desfecho que desvincula):
1. Desvincular a viabilidade da inscrição A.
2. **Cada abrigado vinculado é DESVINCULADO da sede e NOTIFICADO** (SEDUR 2026-07-16 — `[OPEN-EV-1]` fechado). NÃO há cassação automática do abrigado: ele apenas perde o vínculo com a sede e é avisado (o abrigado deverá regularizar por conta própria).
3. Informar SEFAZ via API (mesmo canal da spec de desfecho); se indisponível → **bloqueio explícito**, sem fingir sucesso.

> **Serviço de desvinculação COMPARTILHADO.** Desvincular inscrição+abrigados+SEFAZ acontece por DOIS gatilhos: aqui (mudança de endereço/revisão) e na spec de desfecho 2026-07-14 (indeferido/cassado/revogado/desativado). Ambos DEVEM chamar **um único** serviço de desvinculação (ex.: `DesvincularInscricaoService`) — não duplicar a lógica. A notificação-ao-abrigado (item 2) é parte desse serviço.

### RN-EV-07 — Parametrização
- CNAE gatilho sede (default 8211-3/00) parametrizável.
- Pergunta Sim/Não e textos de condicionante parametrizáveis.
- Lista EV versionada (admin). **Fonte oficial (`[OPEN-EV-2]` fechado, SEDUR 2026-07-16):** endpoint SEDUR `https://api.sedur.salvador.ba.gov.br/k8s/prd/servicosonline/Web/AtividadesPermitidasEmEscritorioVirtual.php` ("Atividades Permitidas Em Escritório Virtual"). Consumo: **importar snapshot versionado** (comando/admin) para a tabela EV local, mantendo o endpoint como origem de verdade — NÃO consultar a API em tempo de cadastro (resiliência: endpoint fora ⇒ usa a última versão importada, degrada honesto). Confirmar formato de resposta do endpoint no plano.

## 5. Modelo de dados (proposta)

Estender o que já existe (`viability_requests.is_virtual_office`, decisões, CNAEs):

| Campo / entidade | Uso |
|---|---|
| `wants_virtual_office_hq` (bool, solicitação) | Resposta do requerente à pergunta sede |
| `is_virtual_office_hq` (bool, decisão/produto) | Confirmação do analista no deferimento |
| `virtual_office_hq_tvl_number` / FK sede | No abrigado: referência à sede |
| `property_registration_lock` / vínculo | Inscrição travada pela sede ativa |
| Flag CNAE `autorizado_escritorio_virtual` | Lista EV (HU-047) |

> **`[OPEN-EV-3]` DECIDIDO (nossa, 2026-07-16):** separar **sede** (`*_hq`) de **abrigado** (`is_virtual_office_tenant` / FK à sede). O `is_virtual_office` existente HOJE é a CATEGORIA do processo (dirige o checkbox "Sede de Escritório" do T01 e os relatórios/`AnalysisCategory`) — **mantê-lo como categoria DERIVADA** dos novos flags (sede OU abrigado ⇒ categoria EV), sem quebrar T01/relatórios existentes. A migração deve preencher a categoria a partir dos novos campos.

## 6. Critérios de aceite (BDD prioritários)

**CA-01 — Gatilho sede**  
DADO CNAE 8211-3/00 + pergunta Sim  
QUANDO o motor classifica o fluxo  
ENTÃO cai em análise humana (não deferimento expresso automático).

**CA-02 — Trava só com flag**  
DADO CNAE 8211-3/00 e analista marca sede=Não  
QUANDO defere  
ENTÃO inscrição **não** fica travada.

**CA-03 — Trava com sede=Sim**  
DADO CNAE 8211-3/00 e sede=Sim  
QUANDO defere  
ENTÃO inscrição fica vinculada à sede e condicionante EV no produto.

**CA-04 — Abrigado fora da lista**  
DADO inscrição com sede ativa  
QUANDO abrigado tenta CNAE não autorizado EV  
ENTÃO cadastro/protocolo é bloqueado com mensagem parametrizada.

**CA-05 — Produto abrigado**  
DADO abrigado deferido  
ENTÃO produto contém End. Virtual = TVL da sede e validade ≤ validade da sede.

**CA-06 — Mudança de endereço**  
DADO sede muda inscrição A→B  
QUANDO a transição conclui com sucesso (incl. SEFAZ se exigida)  
ENTÃO inscrição A fica livre de vínculo de sede; auditoria registra desvinculação.

**CA-07 — Auditoria**  
Toda marcação sede, trava, vínculo abrigado e desvinculação gera trilha RN-002.

## 7. Integrações e bloqueios

| Dependência | Impacto | Tratamento |
|---|---|---|
| Lista CNAEs EV (SEDUR) | RN-EV-05 | Importar do endpoint SEDUR `AtividadesPermitidasEmEscritorioVirtual.php` (snapshot versionado); validação do abrigado usa a última versão importada. Enforcement no protocolo do portal (`StoreSolicitacaoRequest`). |
| SEFAZ API | RN-EV-06 + desfecho | Bloqueio explícito se gateway off; via o serviço de desvinculação compartilhado |
| REDESIM (revisões) | origem da mudança de endereço | Spec telas; motor consome evento quando existir |

## 8. Fontes

- Ata: `docs/reunioes/2026-07-16-cliente/ata-itens-para-specs.md` §1 e §3.A  
- Prints: `02b`, `03`, `03b`, `04`, `05`  
- Transcrição: ~03:46–12:00 (conceito), ~13:37–16:40 (ficha), ~18:00–25:00 (produto)  
- Código: `ViabilityRequest.is_virtual_office`, `EscritorioVirtualReportSource`, `SefazViabilidadeGateway`, ficha/análise  

## 9. Questões abertas

- ~~`[OPEN-EV-1]`~~ **FECHADO (SEDUR 2026-07-16):** abrigado é **desvinculado da sede e notificado** na mudança de endereço (sem cassação automática). Ver RN-EV-06.  
- ~~`[OPEN-EV-2]`~~ **FECHADO (SEDUR 2026-07-16):** lista oficial no endpoint `api.sedur.salvador.ba.gov.br/.../AtividadesPermitidasEmEscritorioVirtual.php` — importar snapshot versionado. Ver RN-EV-07.  
- ~~`[OPEN-EV-3]`~~ **DECIDIDO (nossa):** separar sede (`*_hq`) de abrigado (`*_tenant`); `is_virtual_office` vira categoria derivada. Ver §5.  
- ~~`[OPEN-EV-4]`~~ **CONFIRMADO (SEDUR):** viabilidade da sede só interna + SEFAZ via API — alinhado à spec de desfecho 2026-07-14.  

Nenhuma questão aberta remanescente nesta spec. (Formato do payload do endpoint EV a confirmar no plano — detalhe de implementação, não bloqueia o design.)
