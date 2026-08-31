# Escritório virtual — motor sede × abrigado — design

**Data:** 2026-07-16 · **Revisão:** 5 (respostas SEDUR 2026-08-31)
**Origem:** Reunião SEDUR (Lisa Santos) 2026-07-16 — Meet `dhv-isxt-ibp` (~37 min); pacote normativo SEDUR recebido em 2026-08-28.
**Artefatos:** `docs/reunioes/2026-07-16-cliente/` (transcrição, ata, prints) e `docs/artefatos/` (requisitos, listas de atividade, planilha de regras 20.08.26, 11 protocolos de teste).
**Status:** RASCUNHO — a revisão 5 **fecha** `[OPEN-EV-7]`, `[OPEN-EV-9]` e `[OPEN-EV-10]` com as respostas da SEDUR de 2026-08-31, e abre `[OPEN-EV-12]` a `[OPEN-EV-14]`. **`[OPEN-EV-12]` precisa de confirmação por escrito antes de qualquer implementação: ele retira do nosso escopo um subsistema inteiro que o requisito descreve como nosso.**
**Relacionado:**
- `2026-08-28-escritorio-virtual-constituicao-design.md`
- `2026-08-28-escritorio-virtual-alteracao-endereco-design.md`
- `2026-08-28-escritorio-virtual-alteracao-atividade-design.md`
- `2026-07-14-desfecho-analise-produto-design.md` (desvinculação em indef/cassado/revogado/desativado)
- `2026-06-14-fluxo-expresso-design.md`, `2026-06-14-analise-tecnica-design.md`
- HU-062 (imóvel/EV), HU-047 (flags CNAE EV)

> Nada de adaptador falso para SEFAZ/REDESIM. Onde a integração não estiver homologada, a feature fica **bloqueada** e visível.

---

## 1. Problema

O Viabiliza já tem `is_virtual_office` e export de "sedes", mas **não modela o domínio completo** que a SEDUR opera no SAPS:

1. **Sede** (empresa que cede o endereço) vs **abrigado** (empresa que usa o endereço da sede).
2. Gatilho **CNAE 8211-3/00** → análise humana + trava de inscrição.
3. Produto do abrigado com **End. Virtual - TVL Nº** (TVL da sede) e validade alinhada à sede.
4. Listas de CNAEs permitidos — **duas**, não uma (Anexo A para sede, Anexo B para abrigado).
5. **Desvinculação da inscrição** quando a sede muda de endereço ou deixa de ser sede.

O pacote normativo de 2026-08-28 acrescenta um sexto item, ausente das revisões anteriores: a SEFAZ é consultada **antes** do enquadramento do abrigado, e não apenas notificada depois.

## 2. Escopo desta spec

Esta spec é a **camada de domínio compartilhada**. Ela define o que os três serviços de EV consomem em comum:

- modelo sede/abrigado e o vínculo entre eles;
- as duas listas de atividade e sua versionagem;
- a trava por inscrição imobiliária e seus três pontos de consulta;
- o contrato SEFAZ nos dois sentidos, com a rastreabilidade exigida;
- o gatilho de sede.

**Fora do escopo:** os fluxos de serviço (constituição, alteração de endereço, alteração de atividade) têm spec própria; telas e relatórios idem.

## 3. Conceitos

| Termo | Definição |
|---|---|
| **Sede** | Viabilidade deferida com CNAE 8211-3/00 e confirmação de que prestará serviço de escritório virtual; inscrição imobiliária vinculada/travada |
| **Abrigado** | Viabilidade na mesma inscrição da sede, com TVL próprio e referência ao nº TVL da sede |
| **Anexo A** | Lista de atividades permitidas **para a sede** — 6 CNAEs (Decreto 35.062/2021) |
| **Anexo B** | Lista de atividades permitidas **em escritório virtual**, para o abrigado — 319 CNAEs. O Anexo A é subconjunto próprio dele: as 6 atividades da sede também são permitidas ao abrigado, e 313 são exclusivas do abrigado |
| **Pergunta geral** | "Deseja ser abrigado de escritório virtual?" — determina o fluxo |
| **Pergunta vinculada** | Desmembramento do CNAE 8211-3/00, exibido quando a pergunta geral é respondida "Não" |

Autônomo (ex.: médico em vários hospitais) **não** é escritório virtual.

## 4. Regras de negócio

### RN-EV-01 — Gatilho de sede (revisto)

O gatilho é uma sequência de **duas** perguntas, não uma:

1. **Pergunta geral** — "Deseja ser abrigado de escritório virtual?"
   - **Sim** → fluxo de abrigado (RN-EV-05).
   - **Não** → segue para o passo 2.
2. **Pergunta vinculada**, exibida apenas quando a solicitação contém o CNAE **8211-3/00** — "Irá prestar serviço de escritório virtual, centro de negócios ou coworking?"
   - **Sim** → intenção de **sede**.
   - **Não** → desmembramentos comuns do CNAE; não é escritório virtual.

DADO CNAE 8211-3/00 e pergunta vinculada respondida **Sim**, o processo **não** conclui no expresso e segue para análise humana com a flag de análise do §2º art. 6º do Decreto 35.062/2021.

> **`[OPEN-EV-5]` FECHADO (pacote SEDUR 2026-08-28).** Não é "CNAE sozinho". `Constituição - Virtual.pdf` §4.1.2 condiciona o fluxo de sede à resposta da pergunta vinculada, e o protocolo real `docs/artefatos/Processo - sede de virtual.pdf` mostra a pergunta com a redação acima e resposta "Sim". A ambiguidade da transcrição vinha de o exemplo demonstrado ser um processo já em análise.

> **`[OPEN-EV-8]` ABERTO.** Três redações da pergunta geral circulam nos artefatos: `Constituição` §2 ("Deseja ser abrigado de escritório virtual?"), planilha de regras aba *Perguntas_ regra geral do Sistema* ("A atividade vai ser estabelecida em escritório virtual?") e o legado ("Irá prestar serviço de escritório virtual, centro de negócios ou coworking?" — que é a **vinculada**). Confirmar o enunciado oficial de cada uma antes de parametrizar. O texto é dado parametrizável (RN-EV-07), então isso não trava o modelo.

### RN-EV-02 — Marcação na ficha

Na análise, o analista confirma **Sede de Escritório Virtual = Sim|Não**. O produto é o resultado da **última ficha**.

### RN-EV-03 — Trava de inscrição (ampliada)

A inscrição imobiliária fica **travada/vinculada à sede** quando, no deferimento, o CNAE 8211-3/00 está no processo **e** a flag sede = Sim. CNAE presente com flag Não → não trava.

A revisão 4 acrescenta que a trava é consultada em **três** pontos, não um:

| # | Momento | Efeito |
|---|---|---|
| 1 | Constituição de nova sede na inscrição | Indefere: já existe sede vinculada |
| 2 | Constituição de abrigado na inscrição | Exige sede **existente**; sem sede, indefere |
| 3 | Solicitação que responde "Não" à pergunta geral numa inscrição travada | Indefere com orientação para se abrigar |

O terceiro é novo (`Constituição` §10.1) e não está implementado.

### RN-EV-04 — Campo estruturado no produto da sede

No deferimento da sede, o produto **deve** expor um campo estruturado **"Sede de escritório virtual? Sim/Não"** (`is_virtual_office_hq`), **fora** do bloco de texto de condicionantes.

Pedido explícito da Lisa (~00:17:45–00:18:34): *"Que isso não seja dentro desse campo texto de condicionante. Teria como ele ser um campo separado de sim ou não? […] Porque aí a gente consegue encaminhar essa informação pra SEFAZ de forma mais assertiva."*

O texto de condicionante pode continuar no PDF como cláusula legal, mas não substitui o campo estruturado. O payload SEFAZ envia o bool, não um parse de texto.

### RN-EV-05 — Abrigado

- N abrigados por sede; mesma inscrição imobiliária da sede.
- CNAEs ⊆ **Anexo B**; fora da lista → indeferimento automático com o CNAE identificado na mensagem.
- O 8211-3/00 **não** consta do Anexo B, e isso é deliberado: impede que uma sede se estabeleça dentro de outro escritório virtual (SEDUR 2026-08-31).
- Produto traz **End. Virtual - TVL Nº** = TVL da sede.
- Validade do abrigado = validade da sede, enquanto a sede estiver ativa na inscrição.
- Pode ser expresso se a sede já estiver deferida/ativa no local.

A identificação da sede pelo requerente passa por consulta SEFAZ (RN-EV-08), não por digitação livre.

### RN-EV-05c — Atividades permitidas à sede — NOVO (SEDUR 2026-08-31)

O conjunto de atividades que uma sede pode exercer é **{8211-3/00} ∪ Anexo A**, não o Anexo A sozinho.

O 8211-3/00 é o CNAE que **caracteriza** a sede e por isso não figura no Anexo A — ele não é uma atividade que a sede exerce entre outras, é a declaração de que o estabelecimento presta o serviço de escritório virtual. O Anexo A lista as atividades que um estabelecimento **que já é sede** pode acumular, seja no próprio processo de constituição, seja depois por alteração de atividade econômica.

Consequência direta para a validação: ao conferir as atividades de uma solicitação de sede contra o Anexo A, o 8211-3/00 é **excluído da conferência**. Sem essa exceção, toda sede seria indeferida pelo próprio CNAE que a define.

Os demais CNAEs do Anexo A **não** caracterizam sede por si só. Um estabelecimento que peça apenas contabilidade (6920-6/01) sem o 8211-3/00 e sem responder "Sim" à pergunta vinculada não é sede.

### RN-EV-05b — Consultas operam sobre vínculo ativo

Desvinculação **não apaga** o histórico (auditoria / "já se abrigou nesta inscrição"). Consultas e relatórios por inscrição retornam só o vínculo **ativo**. Lisa (~00:26:34): *"quando eu for fazer uma pesquisa e colocar a inscrição, não é pra eu localizar a sede antiga."*

### RN-EV-06 — Saída da sede da inscrição

Quando a sede sai da inscrição — por mudança de endereço, encerramento da prestação do serviço ou exclusão do CNAE 8211-3/00:

1. Desvincular a viabilidade da inscrição (vínculo ativo some; histórico permanece — RN-EV-05b).
2. **Cada abrigado vinculado é desvinculado e notificado.** Não há cassação automática do abrigado, e **não** há transferência automática para o novo endereço: cada abrigado precisa solicitar sua própria Alteração de Endereço.
3. Comunicar à SEFAZ (RN-EV-09).

> **`[OPEN-EV-1]` FECHADO (pacote SEDUR 2026-08-28).** `Alteração de Endereço - Virtual.pdf` §3.2.3 é explícito: a alteração de endereço da sede **não** transfere os abrigados; eles são notificados para solicitar individualmente. O "Não" da Lisa em ~00:24:10 respondia a "precisa pedir o vínculo de novo?" — o vínculo não é pedido isolado, mas a Alteração de Endereço é. As duas coisas são compatíveis.

> **Serviço de desvinculação compartilhado.** Desvincular inscrição + abrigados + SEFAZ acontece por três gatilhos: mudança de endereço, encerramento da sede e exclusão do CNAE 8211-3/00 — mais os desfechos da spec 2026-07-14 (indeferido/cassado/revogado/desativado). Todos DEVEM chamar **um único** serviço (`DesvincularInscricaoService`). A notificação ao abrigado é parte dele.

### RN-EV-07 — Parametrização

- CNAE gatilho de sede (default 8211-3/00) parametrizável.
- Enunciados das perguntas e textos de indeferimento parametrizáveis.
- Listas de atividade versionadas por `rule_version` (RN-002).

> **`[OPEN-EV-2-bis]` ABERTO.** `[OPEN-EV-2]` fechou o endpoint SEDUR `AtividadesPermitidasEmEscritorioVirtual.php` como fonte oficial, mas ele aparenta cobrir só o **Anexo B**. O Anexo A (6 CNAEs) só existe no PDF `docs/artefatos/Atividades permitidas para sede de Escritório Virtual.pdf`. Confirmar se há endpoint para o Anexo A; enquanto não houver, ele entra por importação administrativa versionada e o PDF fica registrado como origem — não como fonte automatizada.

### RN-EV-08 — Identificação da sede vem do REGIN, não de consulta nossa (REVISTA)

**A revisão 5 retira esta regra do nosso escopo.** A SEDUR informou em 2026-08-31 que a solicitação de abrigado **não é feita no nosso sistema**: ela ocorre no REGIN, e é lá que o abrigado informa o CNPJ da empresa sede.

O papel do nosso sistema passa a ser apenas **identificar a viabilidade da sede** à qual o abrigado está vinculado, a partir do que o REGIN nos entrega. Não há campo de CNPJ para o requerente preencher, não há consulta à SEFAZ por CNPJ, e não há os sete tratamentos de retorno.

O que sai do escopo, por consequência: `Constituição` §7.2.1 itens 1 a 3, §8 inteiro (os sete tratamentos), §11 inteiro (registro das consultas) e os critérios de aceite 13.5, 13.7, 13.8 e 13.9.

> **`[OPEN-EV-12]` ABERTO — CONTRADIZ O REQUISITO ESCRITO. Confirmação por escrito antes de implementar.** A `Constituição - Virtual.pdf` §7.2.1 diz literalmente que o sistema deve "disponibilizar campo para informação do CNPJ da empresa sede", "receber o CNPJ informado pelo usuário" e "realizar consulta à SEFAZ por meio de API", e dedica as seções §8 e §11 a isso. A resposta de 2026-08-31 diz o oposto. Estamos tratando a resposta como vigente por ser posterior e específica, mas **retirar um subsistema inteiro do escopo com base numa resposta de mensagem é risco alto** — se estivermos errados, falta ao produto uma integração que o requisito exige. Pedir confirmação formal e, idealmente, revisão do documento.

> **`[OPEN-EV-13]` ABERTO — TRAVA MODELO DE DADOS.** Qual dado o REGIN nos entrega para identificar a sede? O CNPJ da sede (e nós resolvemos a viabilidade dela por esse CNPJ), ou apenas a inscrição imobiliária (e nós resolvemos pela sede ativa naquela inscrição)? A pergunta de modelagem que `[OPEN-EV-10]` fazia não desapareceu — mudou de fonte. Hoje `AbrigadoResolver` resolve pela inscrição; se o REGIN mandar o CNPJ, precisamos conferir os dois e tratar divergência.

### RN-EV-09 — Comunicação SEFAZ (sentido de saída)### RN-EV-09 — Comunicação SEFAZ (sentido de saída)

Após deferimento, o sistema comunica à SEFAZ os eventos que mudam a condição cadastral: encerramento da sede, mudança de endereço da sede, perda da condição de sede por exclusão do 8211-3/00, entrada/saída da condição de abrigado.

**Falha na comunicação não desfaz o deferimento** (`Alteração de Endereço` §4.3.3). Registra a ocorrência, guarda o código de erro, disponibiliza para acompanhamento e permite reprocessamento.

> **`[OPEN-EV-6]` FECHADO (pacote SEDUR 2026-08-28), com correção de rumo.** A revisão 3 tratava a indisponibilidade SEFAZ como **bloqueio explícito** do passo. O pacote normativo inverte isso para o sentido de saída: o deferimento vale e a comunicação é reprocessável. O bloqueio continua valendo para o sentido de **entrada** (RN-EV-08), onde a consulta é pré-requisito da decisão. Os dois sentidos têm tratamentos opostos de propósito — não unificar.

### RN-EV-10 — Rastreabilidade das integrações — NOVO

Toda consulta e toda comunicação são registradas. Campos mínimos exigidos (`Constituição` §11 e `Alteração de Endereço` §4.3.2) no §5.

## 5. Modelo de dados

### 5.1 Alterações no que existe

| Campo / entidade | Situação | Mudança da revisão 4 |
|---|---|---|
| `viability_requests.wants_virtual_office_hq` (bool) | Existe | **Substituir.** Booleano não expressa três estados nem preserva as duas respostas. Vira campo de intenção (`abrigado` \| `sede` \| `nenhum`) derivado, mais as duas respostas cruas persistidas para auditoria |
| `viability_decisions.is_virtual_office_hq` (bool) | Existe | Mantém — confirmação do analista |
| `viability_decisions.is_virtual_office_tenant` + `virtual_office_hq_tvl_number` | Existe | Mantém; ganha FK explícita à viabilidade da sede |
| `virtual_office_inscription_locks` | Existe | Mantém; passa a ser consultada nos três pontos da RN-EV-03 |
| `virtual_office_activity_cnaes` | Existe, lista única | **Ganha discriminador `anexo`** (`A` \| `B`); unique passa a `(rule_version_id, anexo, cnae_code)` |
| `viability_requests` — tipo de espaço | **Não existe** | Ver spec do motor de risco; o protocolo legado traz o dado e nós não capturamos |

### 5.2 Tabelas novas

**Log de consulta SEFAZ** (`Constituição` §11): número da solicitação, CNPJ consultado, data/hora, resultado, viabilidade retornada, inscrição retornada, resultado da validação, código de retorno da API, mensagem de erro. Os quatro últimos são condicionais ("quando disponível").

**Log de comunicação SEFAZ** (`Alteração de Endereço` §4.3.2): número da solicitação, CNPJ, endereço anterior e novo, inscrição anterior e nova, tipo de alteração, data/hora, resultado do envio, retorno da integração, mais o **estado de reprocessamento** exigido pelo §4.3.3.

Notificação ao abrigado reaproveita `notifications` + `NotificationDispatcher`. O que falta é a consulta "abrigados ativos de uma sede", que depende da FK do §5.1.

> **`[OPEN-EV-3]`** permanece decidido: separar sede (`*_hq`) de abrigado (`*_tenant`); `is_virtual_office` continua sendo a CATEGORIA derivada dos dois, sem quebrar T01/relatórios.

### 5.3 Contratos de serviço

`SefazViabilidadeGateway` já cobre o sentido de saída (`sendViabilidade`), com `UnavailableSefazViabilidadeGateway` degradando honesto. Estender com os eventos da RN-EV-09, mantendo o binding.

O sentido de entrada é **contrato novo** (consulta CNPJ → viabilidade), mesmo padrão de binding e mesma implementação `Unavailable` explícita.

## 6. Critérios de aceite (BDD prioritários)

**CA-01 — Gatilho sede**
DADO CNAE 8211-3/00, pergunta geral "Não" e pergunta vinculada "Sim"
QUANDO o motor classifica o fluxo
ENTÃO cai em análise humana com a flag do §2º art. 6º do Decreto 35.062/2021.

**CA-02 — Trava só com flag**
DADO CNAE 8211-3/00 e analista marca sede=Não, QUANDO defere, ENTÃO inscrição **não** fica travada.

**CA-03 — Trava com sede=Sim**
DADO CNAE 8211-3/00 e sede=Sim, QUANDO defere, ENTÃO inscrição fica vinculada à sede **e** o produto expõe o campo estruturado (RN-EV-04).

**CA-04 — Abrigado fora do Anexo B**
DADO inscrição com sede ativa, QUANDO abrigado pede CNAE fora do Anexo B, ENTÃO indefere automaticamente identificando o(s) CNAE(s).

**CA-05 — Produto abrigado**
DADO abrigado deferido, ENTÃO produto contém End. Virtual = TVL da sede e validade ≤ validade da sede.

**CA-06 — Saída da sede**
DADO sede sai da inscrição A, QUANDO a transição conclui, ENTÃO A fica livre de vínculo de sede, os abrigados são desvinculados e notificados, e a auditoria registra.

**CA-07 — Auditoria**
Toda marcação sede, trava, vínculo abrigado, desvinculação, consulta e comunicação SEFAZ gera trilha RN-002.

**CA-08 — "Não" em inscrição travada**
DADO inscrição travada por sede ativa, QUANDO o requerente responde "Não" à pergunta geral, ENTÃO indefere com orientação para se abrigar.

**CA-09 — Cadeia CNPJ × inscrição**
DADO abrigado informa CNPJ da sede, QUANDO a viabilidade desse CNPJ não é a vinculada à inscrição, ENTÃO bloqueia e permite corrigir.

**CA-10 — Consulta por inscrição após desvinculação**
DADO sede saiu da inscrição A, QUANDO pesquisar A, ENTÃO a sede antiga não aparece no recorte operacional.

**CA-11 — Indisponibilidade da consulta SEFAZ**
DADO a API de consulta indisponível, QUANDO o requerente tenta se enquadrar como abrigado, ENTÃO o sistema não defere nem indefere, informa, permite nova tentativa e registra a ocorrência.

**CA-12 — Falha da comunicação SEFAZ**
DADO um deferimento já concluído, QUANDO a comunicação à SEFAZ falha, ENTÃO o deferimento permanece, a ocorrência fica registrada com o código de erro e o reprocessamento fica disponível.

## 7. Integrações e bloqueios

| Dependência | Impacto | Tratamento |
|---|---|---|
| Anexo B (SEDUR) | RN-EV-05 | Endpoint `AtividadesPermitidasEmEscritorioVirtual.php`, snapshot versionado |
| Anexo A (SEDUR) | RN-EV-05 | `[OPEN-EV-2-bis]` — sem endpoint conhecido; importação administrativa versionada |
| SEFAZ consulta (entrada) | RN-EV-08 | Contrato novo. Sem endpoint homologado, o fluxo de abrigado fica **bloqueado e visível** |
| SEFAZ comunicação (saída) | RN-EV-09 | Estende `SefazViabilidadeGateway`; falha não desfaz deferimento |
| REDESIM (revisões) | origem da mudança de endereço | Spec telas; motor consome evento quando existir |

## 8. Fontes

- Pacote normativo: `docs/artefatos/` — requisitos de Constituição, Alteração de Endereço e Alteração de Atividade; Anexo A e Anexo B; planilha de regras 20.08.26; 11 protocolos de teste.
- Ata: `docs/reunioes/2026-07-16-cliente/ata-itens-para-specs.md` §1 e §3.A
- Prints: `02b`, `03`, `03b`, `04`, `05`
- Transcrição: ~03:46–12:00 (conceito), ~13:37–16:40 (ficha), ~18:00–25:00 (produto)
- Código: `ViabilityRequest.is_virtual_office`, `EscritorioVirtualReportSource`, `SefazViabilidadeGateway`, `DesvincularInscricaoService`, `AbrigadoResolver`

## 9. Questões abertas

- ~~`[OPEN-EV-1]`~~ **FECHADO (SEDUR 2026-08-28):** abrigado não é transferido automaticamente; é notificado e solicita a própria Alteração de Endereço. Ver RN-EV-06.
- ~~`[OPEN-EV-2]`~~ **FECHADO (SEDUR 2026-07-16):** endpoint oficial para a lista EV. Ver RN-EV-07.
- `[OPEN-EV-2-bis]` **ABERTO:** o endpoint cobre também o Anexo A ou só o Anexo B?
- ~~`[OPEN-EV-3]`~~ **DECIDIDO (nossa):** separar sede de abrigado; `is_virtual_office` vira categoria derivada.
- ~~`[OPEN-EV-4]`~~ **CONFIRMADO (SEDUR):** viabilidade da sede só interna + SEFAZ via API.
- ~~`[OPEN-EV-5]`~~ **FECHADO (SEDUR 2026-08-28):** gatilho é CNAE + resposta "Sim" à pergunta **vinculada**. Ver RN-EV-01.
- ~~`[OPEN-EV-6]`~~ **FECHADO (SEDUR 2026-08-28):** SEFAZ tem dois sentidos, com tratamentos opostos de indisponibilidade. Ver RN-EV-08/09.
- ~~`[OPEN-EV-7]`~~ **FECHADO (SEDUR 2026-08-31):** a ausência do 8211-3/00 nos dois anexos é deliberada. Ele caracteriza a sede em vez de ser atividade dela; o Anexo A lista o que a sede pode acumular. A validação da sede exclui o 8211-3/00 da conferência contra o Anexo A. Ver RN-EV-05c.
- `[OPEN-EV-8]` **ABERTO:** enunciado oficial da pergunta geral — três redações nos artefatos. Ver RN-EV-01.
- ~~`[OPEN-EV-9]`~~ **FECHADO (SEDUR 2026-08-31):** sim. CNAE 8211-3/00 **mais** resposta "Sim" à pergunta vinculada ⇒ sempre análise. Resposta "Não" ⇒ segue o expresso, salvo outra condição que puxe para análise. Confirma a RN-EV-01 como implementada.
- ~~`[OPEN-EV-10]`~~ **FECHADO (SEDUR 2026-08-31):** nenhum dos dois, no nosso sistema. A identificação acontece no **REGIN**, onde o abrigado informa o CNPJ da sede. Nosso papel é só resolver a viabilidade da sede vinculada. Ver RN-EV-08 revista, e as pendências novas `[OPEN-EV-12]` e `[OPEN-EV-13]` que isso abriu.
- `[OPEN-EV-12]` **ABERTO — CONFIRMAÇÃO FORMAL.** A resposta acima contradiz `Constituição` §7.2.1/§8/§11 e os CA 13.5/13.7/13.8/13.9. Ver RN-EV-08.
- `[OPEN-EV-13]` **ABERTO — TRAVA MODELO DE DADOS.** Que dado o REGIN entrega para identificar a sede — CNPJ da sede ou inscrição imobiliária? Ver RN-EV-08.
- `[OPEN-EV-14]` **ABERTO:** `Alteração de Atividade` §3.3 e §12.3 continuam mandando indeferir a inclusão do 8211-3/00 numa **Sede**, citando o Anexo A. Pela resposta de 2026-08-31 isso parece texto deslocado — a vedação faz sentido para o **abrigado** (Anexo B), impedindo sede dentro de escritório virtual, não para a sede. Confirmar se os itens se referem ao abrigado.
- `[OPEN-EV-11]` **ABERTO:** comportamento quando a inscrição imobiliária é ausente ou zero. `docs/artefatos/Processo 33072.pdf` tem `Inscrição Imobiliária: 0`, e todo o conjunto de regras de EV é chaveado por ela.
