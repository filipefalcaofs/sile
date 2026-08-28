# Regras territoriais e legais da LOUOS — design

**Data:** 2026-08-28 · **Revisão:** 1
**Origem:** `docs/artefatos/Especificação de Regras de Negócio e Tratamentos do Sistema.pdf` (pacote normativo SEDUR 2026-08-28).
**Status:** RASCUNHO — depende da base geográfica (Fase 4) para as poligonais.
**Relacionado:** `2026-06-13-georreferenciamento-design.md`, `2026-06-14-motor-louos-design.md`, `2026-08-18-louos-quadros-crud-rascunho-design.md`

---

## 1. Problema

O pacote normativo traz um documento que **não** é de escritório virtual: são cinco famílias de regras territoriais e legais que hoje o motor não aplica. Todas dependem de localização — logradouro, número de porta ou poligonal — e produzem um de dois desfechos: indeferimento automático ou encaminhamento à SEDUR com motivo de análise específico.

O que as une, e justifica uma spec só: são regras **de lugar**, não de atividade. O motor LOUOS hoje decide por CNAE × zona × via. Estas decidem por CNAE × endereço e por poligonal × qualquer atividade.

## 2. Objetivos / Não-objetivos

**Objetivos**
- Parametrizar as cinco famílias como dado versionado, não como código.
- Produzir os motivos de análise com o texto exato do requisito.
- Degradar honesto quando a camada geográfica não estiver disponível.

**Não-objetivos**
- Escritório virtual (specs próprias).
- Mudanças de classificação de risco (spec do motor de risco).
- Carga das poligonais em si — é dado da base geográfica.

## 3. Famílias de regra

### RT-01 — Logradouro 212 (Avenida Lafayette Coutinho)

Solicitação **no logradouro 212** que contenha qualquer dos 24 CNAEs listados no requisito → **indeferimento automático**, com o texto do art. 172-A da Lei Municipal nº 9.148/2016, na redação da Lei Municipal nº 9.879/2025.

Os 24 CNAEs cobrem borracharia, desmanche, sucatas e resíduos, bebidas, combustíveis, lubrificantes e recuperação de materiais. Lista integral no documento de origem — entra como dado versionado, não como constante em código.

> A lista tem duas entradas do CNAE **3831-9/99** com descrições divergentes ("Recuperação de materiais metálicos, seleção e corte de sucatas para reciclagem" e "Recuperação de materiais metálicos, exceto alumínio") e repete o **4687-7/03**. Deduplicar na importação por código, preservando a descrição oficial do cadastro de CNAE. Ver `[OPEN-RT-1]`.

### RT-02 — Loteamentos com TAC vigente

O §3º do art. 162 do PDDU faz as disposições da LOUOS prevalecerem sobre as restrições convencionais dos TAC, **exceto** em Vela Branca e Itaigara, onde o TAC permanece integralmente vigente.

**Vela Branca** — toda solicitação na poligonal vai à análise da SEDUR, com motivo *"Solicitação cadastrada no Loteamento Vela Branca."*

**Itaigara** — há uma lista de 11 localizações onde o deferimento ou indeferimento expresso é permitido. Solicitação na poligonal **fora** dessa lista vai à análise, com motivo *"Solicitação cadastrada no Loteamento Itaigara."*

A lista de Itaigara é casada por **código de logradouro + número de porta**:

| # | Local | Logradouro | Nº |
|---|---|---|---|
| 1 | Shopping Paseo | 518 — Rua Rubens Guelli | 135 |
| 2 | Empresarial Elvira Vidal Orge | 518 — Rua Rubens Guelli | 68 |
| 3 | Empresarial Itaigara | 518 — Rua Rubens Guelli | 134 |
| 4 | Posto de Combustível | 2631 — Av. Antônio Carlos Magalhães | 1370 |
| 5 | Shopping da Cidade | 2631 — Av. Antônio Carlos Magalhães | 1298 |
| 6 | Tropical Center | 2631 — Av. Antônio Carlos Magalhães | 1116 |
| 7 | Pituba Parque Center | 2631 — Av. Antônio Carlos Magalhães | 1034 |
| 8 | Max Center | 2631 — Av. Antônio Carlos Magalhães | 846 |
| 9 | Shopping Itaigara | 2631 — Av. Antônio Carlos Magalhães | 656 |
| 10 | Boulevard 161 | 5209 — Rua Anísio Teixeira | 221 |
| 11 | Hiperideal | Rua Anísio Teixeira (**sem código**) | 347 |

> A entrada 11 vem sem código de logradouro no documento. As entradas 10 e 11 são a mesma rua, o que sugere código 5209 — mas sugerir não é confirmar. Ver `[OPEN-RT-2]`.

Semanticamente, Itaigara é uma **allow-list de expresso** dentro da poligonal: estar na lista não defere; apenas devolve a solicitação ao fluxo normal.

### RT-03 — Zonas que sempre vão à análise

Solicitação cadastrada nestas camadas vai à análise com o motivo correspondente:

| Camada | Motivo de análise |
|---|---|
| ZEM — Zona de Exploração Mineral | "Solicitação cadastrada na Zona de Exploração Mineral." |
| ZPAM — Zona de Proteção Ambiental | "Solicitação cadastrada na Zona de Proteção Ambiental." |
| ABM — Área de Borda Marítima, trechos 5 e 6 | "Solicitação cadastrada nos Trechos 5 e 6 da AMB – Área de Borda Marítima, em conformidade com as disposições estabelecidas pelo Decreto Municipal nº 31.168/2019." |

> O texto do motivo de ABM traz "AMB" onde o restante do documento usa "ABM". Reproduzir como está ou corrigir? Ver `[OPEN-RT-3]`.

### RT-04 — Zonas de Uso Especial

As treze ZUE do art. 32 (Centro Administrativo, Parque Tecnológico, Porto, Aeroporto, Base Naval de Aratu, CEASA, Setor Militar Urbano, UFBA, UNEB, Parque de Exposições, Aterro Sanitário, Centro de Convenções, Arena Fonte Nova) compartilham **um único** motivo: *"Solicitação cadastrada em Zona de Uso Especial."*

Diferente da RT-03, aqui a subcategoria não muda o motivo. A camada geográfica precisa distinguir as treze para fins de relatório, mas a regra é uma só.

### RT-05 — Observações do Quadro 10

Três observações do Quadro 10 restringem onde certos usos são permitidos:

**(a)** Permitido somente nas ZCMu-2 de Estrada Velha do Aeroporto (578 — Av. Aliomar Baleeiro), Calçada e São Cristóvão (art. 24, alíneas f, g, i).

**(b)** Permitido somente nas ZCLMu de: 2075 Av. Heitor Dias; 4440 Av. Afrânio Peixoto (Av. Suburbana); 1387 Av. General San Martin; 2289 Av. São Rafael; 4926 Rodovia BA-528; 1469 Av. Ulysses Guimarães; 216 Estrada das Barreiras; 4661 Av. Cardeal Brandão Vilela (art. 27, incisos XV, XX, XXI, XXIV, XXVII, XLV, XLVI, XLVII).

**(c)** Depósitos de inflamáveis, combustíveis, álcool, inseticidas, lubrificantes, resinas, gomas, tintas, vernizes e outros produtos químicos perigosos: somente nas centralidades.

As observações (a) e (b) são **allow-lists de via** dentro de uma zona — o mesmo formato de dado, com zona alvo diferente. A (c) é qualitativa e depende de quais subcategorias de uso do Quadro 7 correspondem a "depósitos de produtos perigosos". Ver `[OPEN-RT-4]`.

## 4. Desenho

Duas formas de casamento, e é isso que estrutura o dado:

**Por endereço** (RT-01, RT-02/Itaigara, RT-05 a/b) — casa código de logradouro e, quando houver, número de porta. Não precisa de geometria; precisa do cadastro de logradouros. É o que dá para implementar **hoje**.

**Por poligonal** (RT-02/Vela Branca, RT-03, RT-04) — casa o ponto do imóvel contra camadas da base geográfica. Depende da Fase 4, hoje pendente SEDUR.

A separação importa porque metade das regras não está bloqueada. Proponho tratá-las como duas entregas, não como uma que espera a base geográfica.

O resultado de qualquer regra é um de dois efeitos: `indeferir` com texto legal, ou `encaminhar_analise` com motivo. Ambos já existem no motor — o que falta é a fonte que os dispara por localização.

**Degradação honesta.** Camada geográfica indisponível não pode virar "não se aplica". No padrão de `EnquadramentoResult::STATUS_INDISPONIVEL`, a ausência da camada produz encaminhamento à análise com o motivo da indisponibilidade — nunca deferimento automático por omissão.

## 5. Critérios de aceite

**CA-RT-01** — DADO solicitação no logradouro 212 com CNAE da lista, QUANDO o motor avalia, ENTÃO indefere com o texto do art. 172-A.

**CA-RT-02** — DADO solicitação no logradouro 212 com CNAE fora da lista, ENTÃO a regra não se aplica e o fluxo segue normal.

**CA-RT-03** — DADO solicitação na poligonal de Vela Branca, ENTÃO encaminha à análise com o motivo de Vela Branca.

**CA-RT-04** — DADO solicitação na poligonal de Itaigara em endereço fora da lista de 11, ENTÃO encaminha à análise com o motivo de Itaigara.

**CA-RT-05** — DADO solicitação na poligonal de Itaigara em endereço da lista, ENTÃO a regra não encaminha e o fluxo segue normal.

**CA-RT-06** — DADO solicitação em ZEM, ZPAM ou ABM trechos 5/6, ENTÃO encaminha à análise com o motivo exato daquela camada.

**CA-RT-07** — DADO solicitação em qualquer das treze ZUE, ENTÃO encaminha à análise com o motivo único de Zona de Uso Especial.

**CA-RT-08** — DADO uso sujeito à observação (a) ou (b) fora das vias listadas, ENTÃO não é permitido.

**CA-RT-09** — DADO camada geográfica indisponível, ENTÃO encaminha à análise registrando a indisponibilidade; nunca defere por omissão.

**CA-RT-10** — Toda aplicação de regra territorial registra trilha com a versão do dado (RN-002).

## 6. Questões abertas

- `[OPEN-RT-1]` **ABERTO:** a lista do logradouro 212 tem 3831-9/99 duplicado com descrições divergentes e 4687-7/03 repetido. Confirmar a lista canônica.
- `[OPEN-RT-2]` **ABERTO:** o item 11 da allow-list de Itaigara (Hiperideal, nº 347) não traz código de logradouro. É 5209 (Rua Anísio Teixeira, como o item 10)?
- `[OPEN-RT-3]` **ABERTO:** o motivo de ABM grafa "AMB". Reproduzir literalmente ou corrigir para ABM?
- `[OPEN-RT-4]` **ABERTO:** a observação (c) do Quadro 10 é qualitativa. Quais subcategorias de uso do Quadro 7 são "depósitos de produtos perigosos", e o que conta como "centralidade" para esse fim?
- `[OPEN-RT-5]` **ABERTO:** as poligonais de Vela Branca, Itaigara, ZEM, ZPAM, ABM trechos 5/6 e as treze ZUE existem na base geográfica atual? O documento diz "para consulta na base geográfica", o que pressupõe que sim — confirmar antes de planejar a entrega por poligonal.
