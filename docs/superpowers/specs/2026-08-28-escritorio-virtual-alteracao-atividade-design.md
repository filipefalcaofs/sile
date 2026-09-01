# Escritório virtual — alteração de atividade econômica — design

**Data:** 2026-08-28 · **Revisão:** 2 (respostas SEDUR 2026-08-31 + auditoria de código)
**Origem:** `docs/artefatos/Alteração de Atividade  - Virtual.pdf` (pacote normativo SEDUR 2026-08-28).
**Status:** RASCUNHO — `[OPEN-EV-7]` fechado pela SEDUR em 2026-08-31. A auditoria de código para o plano abriu `[OPEN-AA-3]` e `[OPEN-AA-4]`, ambos sobre lacunas do sistema, não do requisito. O fluxo de **exclusão** está desbloqueado; a **isenção de TLL** não tem ponto de aplicação.
**Relacionado:** `2026-07-16-escritorio-virtual-motor-design.md` (domínio), `2026-08-28-escritorio-virtual-constituicao-design.md`, `2026-08-28-escritorio-virtual-alteracao-endereco-design.md`

---

## 1. Problema

Alteração de atividade tem duas operações (inclusão e exclusão) que podem vir na mesma solicitação e seguem regras opostas: inclusão valida lista e zoneamento; exclusão defere automaticamente e não valida zoneamento. Uma exclusão específica — a do CNAE 8211-3/00 numa sede — dispara a perda da condição de sede, com todos os efeitos colaterais da saída de sede.

Há ainda uma regra fiscal: solicitação exclusivamente de exclusão não gera TLL.

## 2. Objetivos / Não-objetivos

**Objetivos**
- Processar inclusões e exclusões de forma individualizada dentro da mesma solicitação.
- Tratar a exclusão do 8211-3/00 como evento de domínio, com confirmação do requerente.
- Isentar de TLL as solicitações exclusivamente de exclusão.

**Não-objetivos**
- Alterar a base de cálculo da TLL fora deste caso.
- Redefinir as listas ou o contrato SEFAZ (motor).

## 3. Regras de negócio

### RN-AA-01 — Inclusão em sede

A atividade é verificada contra o **Anexo A**. Não permitida → indeferimento automático, inclusão barrada, mensagem parametrizada. Permitida → valida zona e via pelos Quadros 10 e 11A; permitida defere, não permitida indefere.

A atividade é verificada contra **{8211-3/00} ∪ Anexo A**, com o 8211-3/00 excluído da conferência contra o Anexo A — mesma regra da constituição (RN-EV-05c do motor).

> **`[OPEN-EV-7]` fechado (SEDUR 2026-08-31).** A ausência do 8211-3/00 no Anexo A é deliberada: ele caracteriza a sede em vez de ser atividade dela. Resta `[OPEN-EV-14]`: os §3.3 e §12.3 continuam mandando indeferir a inclusão do 8211-3/00 numa **Sede**, o que pela resposta da SEDUR parece texto deslocado — a vedação faz sentido para o **abrigado** (Anexo B), impedindo sede dentro de escritório virtual. Confirmar antes de implementar o CA 12.3.

### RN-AA-02 — Inclusão em abrigado

A atividade é verificada contra o **Anexo B**. Não permitida → indeferimento automático com mensagem citando o Anexo B. Permitida → valida zona e via; permitida defere, não permitida indefere.

### RN-AA-03 — Exclusão em sede, CNAE diferente do 8211-3/00

Exclui o CNAE, **defere automaticamente** e mantém o enquadramento de sede, desde que o 8211-3/00 permaneça ativo. Sem validação de zoneamento.

### RN-AA-04 — Exclusão do 8211-3/00 em sede

Antes de concluir, o sistema informa a consequência e pede confirmação:

> "A exclusão do CNAE 8211-3/00 fará com que a empresa deixe de ser caracterizada como Sede de Escritório Virtual. Deseja prosseguir com a exclusão?"

**Sim** → excluir o CNAE; deferir automaticamente; retirar o enquadramento de sede; desvincular a inscrição da condição de sede; deixar o endereço livre para outras atividades; notificar os abrigados de que a sede não existe mais e precisam solicitar alteração de endereço; comunicar à SEFAZ.

**Não** → não excluir o CNAE; manter a sede; prosseguir com as demais atividades da solicitação.

O caminho do "Sim" é o mesmo `DesvincularInscricaoService` da RN-EV-06 do motor — terceiro gatilho do serviço compartilhado.

### RN-AA-05 — Exclusão em abrigado

Exclui a atividade, defere automaticamente e mantém o enquadramento de abrigado, desde que as demais condições cadastrais o sustentem. **Sem** validação de zoneamento.

### RN-AA-05b — Como o sistema sabe o que está sendo excluído — NOVO

O requisito assume que o sistema distingue as atividades **incluídas** das **excluídas** numa solicitação (RN-AA-06 item 1). Hoje ele não distingue: `viability_request_cnaes` guarda só os CNAEs pedidos, com `is_primary`, e não há marcação de intenção por atividade.

Duas formas de resolver, e a escolha não é indiferente:

**Derivar do delta** contra `company_cnae` (o cadastro atual da empresa): excluídas = estão no cadastro e não na solicitação. Não exige mudança de schema, mas é frágil de um jeito perigoso — se o requerente informar apenas as atividades que quer acrescentar, o delta leria **todo o cadastro atual como exclusão**. E não distingue "não mexi nisso" de "quero excluir".

**Marcar a intenção por atividade** na solicitação. Exige coluna nova no pivot, e é a única forma que expressa os casos que o requisito pede: a RN-AA-06 descreve uma solicitação com inclusão **e** exclusão simultâneas, o que o delta não consegue representar sem conhecer o conjunto completo pretendido.

**Decisão: marcação explícita.** É a leitura que o requisito sustenta e a única que expressa a solicitação mista. O custo é uma coluna e o preenchimento dela no portal.

### RN-AA-06 — Inclusão e exclusão simultâneas

As duas operações são aplicadas **concomitante e independentemente**, na mesma solicitação:

1. Separar as atividades a incluir das a excluir.
2. Aplicar às incluídas as regras de inclusão (lista, sede/abrigado, zona e via).
3. Aplicar às excluídas as regras de exclusão, inclusive a do 8211-3/00.
4. Registrar o resultado de **cada** operação.

Havendo operação que impeça o processamento conjunto das demais, aplicar a regra correspondente e registrar o motivo, preservando a rastreabilidade.

> O documento não define o resultado consolidado da solicitação quando as operações divergem — uma inclusão indeferida ao lado de uma exclusão deferida. Ele diz apenas "considerar o resultado de cada operação". Ver `[OPEN-AA-1]`.

### RN-AA-07 — Exclusão do 8211-3/00 dentro de solicitação simultânea

A regra da RN-AA-04 se aplica integralmente, inclusive a confirmação. Resposta "Não" mantém o CNAE e as demais alterações seguem normalmente.

### RN-AA-08 — Isenção de TLL (SEM PONTO DE APLICAÇÃO)

Solicitação **exclusivamente** de exclusão de atividade não gera cobrança de TLL. Vale para sede e para abrigado.

Consequência da RN-AA-06: uma solicitação com inclusão **e** exclusão não é exclusivamente de exclusão, logo **não** é isenta. Está implícito no requisito; a spec explicita. Ver `[OPEN-AA-2]`.

> **`[OPEN-AA-3]` ABERTO — O SISTEMA NÃO COBRA TLL.** A auditoria de código para o plano encontrou que **não existe módulo de taxa** no Viabiliza: nenhuma migration de taxa, cobrança ou pagamento, e as ocorrências de "taxa" no código são taxa de deferimento em relatórios (percentual), não emolumento. O único "TLL" é o campo `codigo_tll` da ficha, que é código LOUOS, não valor a pagar.
>
> Uma regra que "impede a geração da taxa" não tem do que isentar, e nenhum teste pode provar que nada foi cobrado quando nada cobra. Implementar um marcador de isenção é defensável — o módulo futuro o consome —, mas o **CA 12.11 não pode ser declarado entregue** até existir o ponto de aplicação. Confirmar com a SEDUR onde a TLL é gerada hoje: é o Viabiliza que deveria gerar, ou outro sistema?

### RN-AA-09 — Atualização cadastral e comunicação

Deferida a inclusão, incorporar o CNAE. Deferida a exclusão, remover. Confirmada a exclusão do 8211-3/00, remover a condição de sede e desvincular a inscrição.

Comunicar à SEFAZ na perda da condição de sede, com: CNPJ, número da solicitação, CNAE excluído, inscrição imobiliária, endereço, condição anterior, nova condição e data/hora do deferimento. Registro conforme RN-EV-10 do motor.

## 4. Critérios de aceite

| CA | Enunciado | RN |
|---|---|---|
| 12.1 | Sede inclui atividade permitida, zona e via permitidas → defere e atualiza cadastro | RN-AA-01 |
| 12.2 | Sede inclui atividade não permitida → indefere com a mensagem correspondente | RN-AA-01 |
| 12.3 | Inclusão contém 8211-3/00 não permitido em sede → indefere automaticamente | RN-AA-01 / `[OPEN-EV-7]` |
| 12.4 | Abrigado inclui atividade permitida, zona e via permitidas → defere | RN-AA-02 |
| 12.5 | Abrigado inclui atividade não permitida em EV → indefere | RN-AA-02 |
| 12.6 | Sede exclui CNAE ≠ 8211-3/00 → exclui e defere automaticamente | RN-AA-03 |
| 12.7 | Sede exclui 8211-3/00 → informa a consequência e pede confirmação | RN-AA-04 |
| 12.8 | Confirmação "Sim" → exclui, retira a condição de sede, desvincula a inscrição, defere e comunica SEFAZ | RN-AA-04 |
| 12.9 | Confirmação "Não" → não exclui o CNAE | RN-AA-04 |
| 12.10 | Abrigado exclui atividade → exclui e defere automaticamente | RN-AA-05 |
| 12.11 | Solicitação exclusivamente de exclusão → não gera TLL | RN-AA-08 |
| 12.12 | Exclusão do 8211-3/00 confirmada e deferida → comunica SEFAZ | RN-AA-09 |

## 5. Matriz de rastreabilidade

| CA SEDUR | RN desta spec | Onde testar |
|---|---|---|
| 12.1, 12.2, 12.3 | RN-AA-01 | Feature de inclusão em sede |
| 12.4, 12.5 | RN-AA-02 | Feature de inclusão em abrigado |
| 12.6 | RN-AA-03 | Feature de exclusão em sede |
| 12.7, 12.8, 12.9 | RN-AA-04 | Feature de exclusão do 8211-3/00 + `DesvincularInscricaoService` |
| 12.10 | RN-AA-05 | Feature de exclusão em abrigado |
| 12.11 | RN-AA-08 | Teste de geração de TLL |
| 12.12 | RN-AA-09 | Log de comunicação SEFAZ |

## 6. Questões abertas

- ~~`[OPEN-EV-7]`~~ **FECHADO (SEDUR 2026-08-31):** o 8211-3/00 caracteriza a sede; a conferência contra o Anexo A o exclui. Ver RN-AA-01.
- `[OPEN-EV-14]` **ABERTO:** os §3.3 e §12.3 se referem ao abrigado, não à sede? Bloqueia o CA 12.3. Ver RN-AA-01.
- `[OPEN-AA-1]` **ABERTO:** qual o resultado consolidado de uma solicitação com inclusão indeferida e exclusão deferida? O documento manda registrar o resultado de cada operação, mas o produto (TVL) e o status do processo são únicos. Confirmar se a solicitação fica parcialmente deferida, se o indeferimento de uma inclusão derruba a solicitação inteira, ou se as operações geram desfechos separados.
- `[OPEN-AA-2]` **ABERTO:** a isenção de TLL vale para solicitação mista (inclusão + exclusão)? A leitura literal diz que não — "exclusivamente" —, mas convém confirmar, porque é regra de arrecadação.
- `[OPEN-AA-3]` **ABERTO — SEM PONTO DE APLICAÇÃO.** O Viabiliza não tem módulo de taxa. Onde a TLL é gerada hoje? Ver RN-AA-08.
- `[OPEN-AA-4]` **DECIDIDO (nossa, 2026-08-31):** a intenção por atividade (incluir ou excluir) passa a ser marcada explicitamente na solicitação, em vez de derivada do delta contra o cadastro. Ver RN-AA-05b. Registrar como decisão nossa a confirmar com a SEDUR no desenho da tela.
