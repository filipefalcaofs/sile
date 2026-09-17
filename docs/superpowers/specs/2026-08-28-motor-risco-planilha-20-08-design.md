# Motor de risco — planilha de regras 20.08.26 — design

**Data:** 2026-08-28 · **Revisão:** 3 (PO Lisa, 2026-09-15)
**Origem:** `docs/artefatos/Planilha de regras - versão 20.08.26.xlsx` (arquivo operacional em `/Users/filipefalcao/Downloads/Planilha de regras - versão 20.08.26.xlsx`).
**Status:** APROVADO PELA PO — carga da matriz **e** motor por ramo da regra. Tipo de imóvel pressuposto do REGIN.
**Relacionado:** `2026-06-13-classificacao-de-risco-design.md`, `2026-06-14-motor-louos-design.md`, `2026-06-14-fluxo-expresso-design.md`

---

## 1. Problema

A planilha 20.08.26 traz 47 regras de tratamento, das quais **33 foram atualizadas em 21/08/2026** e 2 em 21/07/2026. A SEDUR descreveu a entrega como "alterações nas regras de médio e alto risco".

À primeira vista isso é reparametrização: trocar níveis de risco em tabela. Não é. A revisão muda **de que o nível de risco depende**.

Hoje `RiscoClassificationService` recebe um `RiscoInput` com CNAE, respostas de condicionante e gatilhos de contexto, e resolve as dimensões municipal e sanitária a partir de classificações versionadas por CNAE. O nível é um atributo **do CNAE**.

Na versão 20.08.26, o nível é atributo **do ramo da regra**. A mesma atividade é médio ou alto risco conforme quatro variáveis combinadas:

| Variável | Exemplo na planilha | Situação no código |
|---|---|---|
| Resposta à pergunta condicionante | "Se a resposta for NÃO na pergunta 2" | Existe (`respostasCondicionantes`) |
| Área construída, corte em 1.250 m² | "área menor ou igual a 1.250 m²" | Existe, mas em `EnquadramentoInput.area` |
| Tipo de imóvel | "GALPÃO", "CONTAINER", "EDIFICAÇÃO RESIDENCIAL" | **Não existe em lugar nenhum.** Vem do REGIN (SEDUR 2026-08-31) |
| Subcategoria de uso | "ALTO RISCO SE ENQUADRADO COMO ID" | Existe, em `EnquadramentoResult.quadro7.subgrupo` |

Duas dessas variáveis são **saída do motor de enquadramento LOUOS**, e uma não é capturada em canto nenhum do sistema.

## 2. Objetivos / Não-objetivos

**Objetivos**
- Capturar o tipo de imóvel na solicitação.
- Permitir que o motor de risco decida por ramo de regra, não só por CNAE.
- Absorver a diferença da versão 20.08.26 contra a parametrização vigente.

**Não-objetivos**
- Reescrever o motor de risco. O escopo é a diferença, não o motor.
- Alterar a dimensão sanitária (VISA), que a planilha não toca.
- Escritório virtual e regras territoriais — specs próprias.

## 3. Achados

### MR-01 — Estrutura uniforme das regras

Todas as 47 regras seguem a mesma forma: uma pergunta condicionante, um ramo de fluxo expresso e um ramo de fluxo semiexpresso, cada ramo com nível de risco e código de enquadramento LOUOS.

A `Regra 24` é representativa:

- resposta "Não" à pergunta 2 e área ≤ 1.250 m² → expresso, médio risco, enquadra 07.12.13;
- resposta "Não" e área > 1.250 m² → expresso com crítica do analista, médio risco;
- imóvel galpão/container/edificação residencial e resposta "Não" → semiexpresso, médio risco.

A `Regra 25` mostra a variação de nível **dentro** da mesma regra: resposta "Sim" à pergunta 3 é médio risco em todos os ramos; resposta "Não" é alto risco em todos os ramos.

A `Regra 1` é a única que condiciona o nível à subcategoria: *"MÉDIO RISCO / ALTO RISCO SE ENQUADRADO COMO ID"*.

### MR-02 — Tipo de imóvel não é capturado, e vem do REGIN

`viability_requests` não tem campo de tipo de imóvel — nenhuma migration o cria. Os protocolos legados trazem o dado (`Tipo de imóvel: Galpão` em `Processo 43747.pdf`, `Edificação Comercial` na maioria, `Sala` no de sede).

A SEDUR confirmou em 2026-08-31 que o dado **vem do REGIN** para o nosso sistema — não é campo que o requerente preenche — e que **só três valores dirigem regra**: galpão, container e edificação residencial.

Sem esse campo, o ramo semiexpresso de **33 regras atualizadas** é inaplicável. É a lacuna que trava a entrega.

### MR-03 — Dependência entre motores

Área e subcategoria são produto do enquadramento LOUOS. Hoje risco e enquadramento são motores irmãos independentes, ambos espelhando `TerritoryService`. A versão 20.08.26 cria a dependência `território → enquadramento → risco`.

## 4. Desenho

### 4.1 Captura

Campo de tipo de imóvel em `viability_requests`, alimentado pelo **REGIN** na entrada da solicitação.

Só três valores dirigem regra: galpão, container e edificação residencial. Os demais que os protocolos mostram — "Edificação Comercial", "Sala" — não acionam o ramo semiexpresso; caem no ramo comum.

Isso simplifica a captura, mas cria um risco que precisa de tratamento explícito. Se a regra for "é um dos três? senão, ramo comum", qualquer valor que o REGIN mande fora do esperado — variante de grafia, acento diferente, valor novo — é silenciosamente tratado como "não é galpão", e o processo segue pelo expresso. Isso é fachada: decisão automática tomada sobre dado que não foi reconhecido.

O tratamento: normalizar o valor recebido e compará-lo ao conjunto conhecido. Valor **não reconhecido** não vira "não é um dos três" — vira encaminhamento à análise com o motivo do valor desconhecido. Só valor reconhecido, esteja dentro ou fora dos três, decide automaticamente. Ver `[OPEN-MR-6]`.

### 4.2 Composição, não acoplamento

`RiscoInput` ganha três campos **opcionais**: `area`, `tipoImovel`, `subcategoriaUso`. Quem orquestra o fluxo passa o que o enquadramento já produziu.

O motor de risco continua sem conhecer o de enquadramento — não importa `LouosEnquadramentoService`, não o chama, não depende dele. Recebe valores. Isso preserva a simetria com `TerritoryService` e mantém os dois motores testáveis isoladamente.

Campos nulos significam "não sei", não "não se aplica": a regra que depende deles não é avaliada e a decisão segue para análise com motivo. É o mesmo princípio anti-fachada de `EnquadramentoResult::STATUS_INDISPONIVEL` e do tratamento de CNAE sem classificação vigente que o serviço já pratica.

### 4.3 O ramo como unidade parametrizada

Hoje a parametrização associa nível de risco a CNAE. A versão 20.08.26 exige associar nível a **ramo de regra**: uma condição composta (pergunta, faixa de área, tipo de imóvel, subcategoria) que produz nível, fluxo e código de enquadramento.

Isso é dado versionado por `rule_version`, como o resto da parametrização de risco — o admin edita, o quatro-olhos aprova, o motor só aplica. A alternativa (traduzir 47 regras em código) foi descartada: seria a mesma regra escrita duas vezes, e a planilha muda a cada revisão da SEDUR.

### 4.4 Escopo da diferença

Das 47 regras, 35 têm data de atualização (33 em 21/08/2026, 2 em 21/07/2026). O plano precisa começar por um **diff** contra a parametrização vigente, para separar o que é mudança real do que é reescrita de texto sem efeito. Não assumir que 35 regras mudaram de comportamento só porque foram carimbadas.

## 5. Critérios de aceite

**CA-MR-01** — DADO uma solicitação sem tipo de imóvel informado, QUANDO uma regra depende do tipo, ENTÃO a decisão vai à análise com o motivo da ausência; nunca é classificada por omissão.

**CA-MR-01b** — DADO um tipo de imóvel que o REGIN enviou e o sistema não reconhece, QUANDO uma regra depende do tipo, ENTÃO a decisão vai à análise com o motivo do valor desconhecido; nunca é tratada como "não é um dos três".

**CA-MR-02** — DADO a `Regra 24`, resposta "Não" à pergunta 2 e área ≤ 1.250 m², ENTÃO expresso, médio risco, enquadramento 07.12.13.

**CA-MR-03** — DADO a `Regra 24`, imóvel galpão e resposta "Não" à pergunta 2, ENTÃO semiexpresso, médio risco, remetido à crítica do analista.

**CA-MR-04** — DADO a `Regra 25`, resposta "Não" à pergunta 3, ENTÃO alto risco em qualquer dos três ramos (galpão/container/residencial, área ≤ 1.250, área > 1.250).

**CA-MR-05** — DADO a `Regra 1`, resposta "Sim" à pergunta 11 e subcategoria ID, ENTÃO alto risco. Mesma regra e resposta com outra subcategoria → médio risco.

**CA-MR-06** — DADO subcategoria não informada numa regra que dela depende, ENTÃO análise com motivo; não assumir médio.

**CA-MR-07** — Toda classificação registra a versão da regra aplicada (RN-002) e permite reprodução por época.

**CA-MR-08** — O motor de risco não referencia o de enquadramento: teste unitário do `RiscoClassificationService` roda sem instanciar `LouosEnquadramentoService`.

## 6. Questões abertas

- ~~`[OPEN-MR-1]`~~ **FECHADO (SEDUR 2026-08-31):** só galpão, container e edificação residencial dirigem regra, e o dado vem do REGIN — não é campo do requerente. Ver §4.1.
- `[OPEN-MR-6]` **ABERTO (não bloqueia):** enumeração completa dos valores que o REGIN envia. Pressuposto de implementação (2026-09-15): o campo chega do REGIN; valor reconhecido decide; valor desconhecido ou ausente, quando a regra depende do tipo, vai à análise com motivo — nunca assume “não é galpão”.
- ~~`[OPEN-MR-2]`~~ **FECHADO (PO Lisa 2026-09-15):** o corte de 1.250 m² usa a **área utilizada** informada pelo requerente no processo (onde a atividade será exercida). No sistema: `viability_requests.used_area_m2`.
- ~~`[OPEN-MR-3]`~~ **FECHADO (PO Lisa 2026-09-15 + texto da Regra 1):** “ID” é a **família de subcategoria de uso** (`ID1-*`, `ID2-*`, `ID3-*`) da coluna de enquadramento com letras menores — não o código LOUOS `07.12.13`. Resposta “Sim” + subcategoria ID → alto risco. A aba CNLU (nR3, nRa, nR4 e ID3 do art. 132) é roteamento à comissão, critério distinto.
- `[OPEN-MR-4]` **ABERTO (não bloqueia):** conferência textual das regras carimbadas vs. comportamento. A PO validou o modelo (risco não é fixo no CNAE).
- ~~`[OPEN-MR-5]`~~ **FECHADO (PO Lisa 2026-09-15):** a **Regra 45 foi excluída**. O furo 44→46 é proposital para não renumerar a planilha. R46–R60 permanecem. Nenhum CNAE marca a coluna R45.

## 7. Confirmações da PO (2026-09-15)

1. **Modelo:** o cadastro da planilha entra no sistema **e** o risco deixa de ser atributo fixo do CNAE — é o resultado da regra de tratamento (pergunta + área utilizada + tipo de imóvel + subcategoria).
2. **Exceção:** médio e alto **já classificados sem condicionante** permanecem; não “viram baixo”. Baixo só se mantém se atender as condicionantes/perguntas; senão sobe para médio ou alto conforme a resposta.
3. **TLL** varia pelo mesmo caminho da regra (não só pelo CNAE).
4. **Universo oficial:** **1.332** CNAEs (o 1.334 do cabeçalho está errado).
5. **`9900-8/00` entra** e está ativo — é o CNAE que falta no IBGE 2.3 carregado hoje (1.331 → 1.332).
6. Tipo de imóvel: **pressuposto REGIN** nesta implementação.
