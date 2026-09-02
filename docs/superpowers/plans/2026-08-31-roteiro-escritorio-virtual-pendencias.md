# Escritório Virtual e trilhos adjacentes — roteiro do que falta

**Data:** 2026-08-31
**Natureza:** documento de decomposição e sequenciamento. **Não é** um plano de implementação — cada frente executável abaixo gera o seu próprio, pela skill de planos.
**Specs de referência:** as oito em `docs/superpowers/specs/` com prefixo `2026-07-16-escritorio-virtual-*`, `2026-08-28-escritorio-virtual-*`, `2026-08-28-motor-risco-planilha-20-08` e `2026-08-28-regras-territoriais-louos`.

---

## 1. Por que este documento existe

"O que falta" são cinco frentes independentes, e três delas não podem ser planejadas de forma executável hoje. Escrever um plano de implementação para o fluxo de abrigado, por exemplo, produziria um plano que não roda — a integração de que ele depende é um stub.

Este documento separa o que está bloqueado do que está pronto para planejar, e diz **por que** cada bloqueio existe. Dois deles não se resolvem com código.

## 2. Estado atual

Entregue e revisado, com 108 testes de Escritório Virtual verdes:

| Serviço | Do que consiste |
|---|---|
| Fundação do motor | Listas de atividade por anexo do Decreto 35.062/2021, trava de inscrição nos três pontos, os dois sentidos da SEFAZ com rastreabilidade e reprocessamento, notificação às abrigadas |
| Constituição de sede | Atividades permitidas à sede, indeferimentos automáticos parametrizados, encaminhamento à análise com a flag do §2º art. 6º |
| Exclusão de atividade | Marcação de intenção por atividade, deferimento sem zoneamento, cascata da perda da condição de sede |

## 3. As cinco frentes

### F1 — Camada de tela dos serviços já entregues

**Entrega:** as três regras implementadas passam a ser alcançáveis por quem usa o sistema.

**Por que é a de maior valor:** as três estão corretas no dado e cobertas por teste, e **nenhuma** é alcançável pela interface. Nenhuma tela preenche a pergunta geral de escritório virtual, a marcação de intenção por atividade ou a confirmação de perda da condição de sede. Pior: `UpdateSolicitacaoAtividadesRequest` **bloqueia** o CNAE gatilho em inscrição travada, então nem a própria sede consegue compor hoje a solicitação de exclusão que o serviço sabe processar.

Do ponto de vista de homologação, um analista que abrir o sistema hoje não exercita quase nada do que foi construído.

**Depende de:** o enunciado oficial das perguntas (pergunta 5 do e-mail à SEDUR). **Não bloqueia:** os textos são parâmetro administrável, então dá para subir com a redação do documento de Constituição como padrão e a SEDUR ajusta na gestão.

**Status: EXECUTÁVEL.**

### F2 — Regras territoriais que casam por endereço

**Entrega:** indeferimento automático no logradouro 212 (Avenida Lafayette Coutinho) para os CNAEs do art. 172-A, e as allow-lists de via das observações (a) e (b) do Quadro 10.

**Depende de:** nada de bloqueante. Duas perguntas menores abertas — a lista canônica do logradouro 212, que tem CNAE duplicado com descrições divergentes, e o código de logradouro do Hiperideal — e as duas degradam para o lado seguro: dedupe determinístico por código, e um endereço fora da allow-list vai à análise à toa em vez de ser deferido errado.

**Status: EXECUTÁVEL.**

### F3 — Alteração de atividade: a metade de inclusão

**Entrega:** completa o serviço cuja exclusão já está pronta. Validação da atividade incluída contra a lista do anexo aplicável, mais zona e via.

**Depende de:** `[OPEN-EV-14]` para o critério de aceite 12.3 — os §3.3 e §12.3 mandam indeferir a inclusão do CNAE gatilho numa **sede**, o que pela resposta da SEDUR de 31/08 parece texto deslocado. O resto da inclusão não depende disso. E `[OPEN-AA-1]` para o resultado consolidado da solicitação mista.

**Status: EXECUTÁVEL** com dois critérios de aceite condicionados.

### F4 — Alteração de endereço: o lado da sede

**Entrega:** encerramento da prestação do serviço de sede e mudança de endereço da sede.

**Por que é mais barato do que parece:** os efeitos colaterais — desvinculação, notificação às abrigadas, comunicação reprocessável à SEFAZ — já estão construídos e testados. Falta o fluxo que os aciona, e a mudança de endereço reaproveita integralmente o fluxo de constituição de sede.

**Depende de:** `[OPEN-AE-1]`, se a verificação de "outra sede" na inscrição deve desconsiderar a própria solicitação. A leitura óbvia é que sim, e dá para implementar sob premissa declarada.

**Status: EXECUTÁVEL.**

### F5 — Motor de risco da planilha 20.08.26

**Entrega:** as 47 regras, com o nível de risco passando a ser atributo do ramo da regra em vez de atributo do CNAE.

**Bloqueado por três coisas, e nenhuma é código nosso:**

1. **Dados dos Quadros 7 e 10.** O Quadro 7 cobre 24 CNAEs; a planilha declara 1334. O Quadro 10 tem 5 zonas, com a coluna de subgrupo vazia em todas as linhas — e só os protocolos de teste da SEDUR já trazem ZCMe-1/01, ZCMe-1/03, ZEIS 1, ZPR 3, ZPR 1. Como duas das quatro entradas das regras novas (área e subcategoria de uso) são **saída** do motor de enquadramento, que lê esses quadros, construir o motor de risco antes dos dados produz um sistema que degrada para análise em quase todo processo. Tecnicamente correto pelo anti-fachada, inútil na prática.

2. **A subcategoria "ID" da Regra 1.** Os subgrupos no nosso Quadro 7 vão de `nR1-01` a `nR3-10`; não existe "ID". A própria planilha, noutra aba, fala em `nR3` e `nRa`.

3. **Tipo de imóvel.** A SEDUR respondeu que vem do REGIN, e o REGIN é stub.

**O que NÃO é bloqueio:** o veículo de importação. `LouosMaintenanceService::publishNewVersion`, as rotas sob permissão `manter-louos` e a tela em `gestao/louos` **já existem**. Não há o que construir para carregar os quadros — falta o dado.

**Status: BLOQUEADO POR DADO.** A ação aqui é pedir os quadros completos à SEDUR, não codar.

### F6 — Fluxo de abrigado

**Entrega:** constituição e alteração de endereço de empresa abrigada.

**Bloqueado por integração.** A SEDUR informou em 31/08 que a identificação da sede acontece no REGIN, e que o nosso papel é resolver a viabilidade da sede vinculada. `app/Services/Regin/` tem apenas `UnavailableBapRegistry` e `UnavailableReginParecerNotifier` — a integração não está homologada.

Nem a confirmação de `[OPEN-EV-12]` destrava: se a SEDUR confirmar que a consulta por CNPJ é toda do REGIN, continuamos sem o REGIN. Antes, ao menos, o requisito descrevia algo que nós construiríamos.

**Status: BLOQUEADO POR INTEGRAÇÃO.** A ação é obter o prazo do REGIN.

## 4. Dependências

```
F1 (telas) ──────────────────► independente
F2 (territorial/endereço) ───► independente
F3 (inclusão) ───────────────► usa a marcação de intenção (entregue)
F4 (endereço/sede) ──────────► usa constituição de sede + desvinculação (entregues)
F5 (motor de risco) ─────────► Quadros 7 e 10 completos ──► SEDUR
                             └► subcategoria "ID" ────────► SEDUR
                             └► tipo de imóvel ───────────► REGIN
F6 (abrigado) ───────────────► REGIN
```

F1 a F4 não dependem umas das outras e podem ser feitas em qualquer ordem.

## 5. Sequência recomendada

1. **F1 — telas.** É o que transforma trabalho testado em trabalho demonstrável, e é o que a SEDUR consegue olhar. Costuma destravar respostas pendentes mais rápido que cobrança por e-mail.
2. **F3 — inclusão de atividade.** Fecha um serviço inteiro, e o cliente cobra por serviço, não por metade de serviço.
3. **F4 — alteração de endereço da sede.** Barata pelo reaproveitamento.
4. **F2 — territorial por endereço.** Independente das outras, boa para paralelizar.
5. **F5 e F6** quando os bloqueios externos caírem.

## 6. O que não é problema de código

Vale isolar, porque muda quem precisa agir:

| Pendência | Quem resolve |
|---|---|
| Quadros 7 e 10 completos | SEDUR fornece o dado; o veículo de importação existe |
| Prazo do REGIN | Integração — decisão de terceiros |
| Enunciado oficial das perguntas | SEDUR; mitigável por parâmetro com default |
| Subcategoria "ID" | SEDUR |
| Onde a TLL é gerada | SEDUR; hoje o Viabiliza não cobra taxa |
| Layout do TVL para exclusão | SEDUR; hoje a seção fica vazia |
| Critério de titularidade da sede | SEDUR; usamos `company_id`, isolado para trocar fácil |
