# Spec — Justificativa em todo indeferimento e reconhecimento das camadas

Data: 2026-09-26
Origem: pedido do usuário após o caso do processo 5921000030-00990026/2026 e o documento SEDUR "Especificação de Regras de Negócio e Tratamentos do Sistema" (pacote de 2026-08-28). Complementa `2026-09-26-localizacao-pela-inscricao-design.md`.

## 1. Problema (verificado no código e em produção)

### 1.1 Indeferimento sem justificativa legível

| Caminho | `reason` gravado hoje | O que quem consulta vê |
|---|---|---|
| Expresso, Quadro 10 / Quadro 11A (`FluxoExpressoService::emitir`) | `null` | Só referências legais soltas (`fundamentacao`: "Lei nº 9.148/2016 — Quadro 10") |
| Expresso, RT-01 (`indeferirTerritorial`) | texto do art. 172-A | Aparece só em "Resultados do expresso" |
| Expresso, sem BAP (`IndeferirSemBapService`) | `"indeferido sem atuação"` | Frase interna, sem fundamento |
| Convite expirado (`IndeferirPorPrazoConviteService`) | frase fixa no código | Frase sem fundamento, fora do catálogo |
| Análise técnica (`AnaliseTecnicaDecisionService`) | `null` (parecer fica na ficha, opcional) | Nada no portal nem no REGIN |

Onde a justificativa deveria aparecer e não aparece:

- Detalhe do processo na gestão: não mostra `reason`.
- Portal do cidadão (protocolo): só status e timeline.
- Parecer ao REGIN (`JUSTIFICATIVA_ANALISE`): só a lista de referências legais.
- PDF de indeferimento: usa `fundamentacao` e o parecer da ficha, não o `reason`.

Os textos padrão de indeferimento já fornecidos existem no catálogo administrável, mas **nenhum serviço os usa**: `justificativa.conclusao.nao_permitido` (Quadro 10) e `justificativa.conclusao.nao_permitido_via` (Quadro 11-A). O texto da RT-01 (art. 172-A) está em `territorial.indeferimento.logradouro_cnae`.

### 1.2 Camadas não reconhecidas

- **Logradouros:** a camada local de vias em produção é `geosalvador-logradouros-centro` — **800 feições, só o Centro** (extensão −38,529 a −38,480; −13,009 a −12,946). Fora dessa caixa não há logradouro identificado.
- **Zona fictícia em produção:** a camada `zona-ficticia-dev` (seeder de demonstração, "NUNCA produção") está **vigente em produção** e cobre um retângulo do Centro/Comércio com a zona fictícia. Com ela vigente, o `TerritoryService` não consulta o GeoServer para zona nessa área. Foi de onde saiu a zona `ZCN-1` do processo #92.
- **Camadas incidentes não registradas:** o processo guarda só zona, bairro e CODLOG. Quando a regra de borda marítima (trechos 5 e 6), ZEM, ZPAM, ZUE ou loteamento TAC incide, isso aparece só como motivo de queda. Não há registro de "o imóvel está no Trecho 5 da ABM", nem das camadas que **não** incidiram. A consulta WFS usa `maxFeatures=1` e o filtro do trecho, então o atributo `TRECHOS` não é guardado.

## 2. Decisões

### Justificativa

1. **Invariante:** toda `ViabilityDecision` com `outcome = indeferida` tem `reason` não vazio. Garantido no model (exceção ao salvar sem `reason`) e coberto por teste de cada caminho.
2. **Expresso Quadro 10 / 11A:** `reason` montado a partir dos textos padrão do catálogo, **um parágrafo por CNAE não permitido**, identificando o CNAE e usando `justificativa.conclusao.nao_permitido` (placeholder `:zona`) ou `justificativa.conclusao.nao_permitido_via` (placeholder `:classe_via`) conforme o quadro que vetou (lido de `consulta_array.louos`). Quando os dois vetam, entram os dois textos.
3. **RT-01:** mantém o texto do documento (art. 172-A), já em `reason`.
4. **Sem BAP e convite expirado:** textos passam ao catálogo (`justificativa.indeferimento.sem_bap`, `justificativa.indeferimento.convite_expirado`) com a redação atual como padrão, editáveis na tela de textos de decisão. **Pendência SEDUR:** redação oficial desses dois casos (não consta no documento de 2026-08-28).
5. **Análise técnica:** indeferir exige parecer (validação no request de conclusão/decisão); o parecer vira o `reason` da decisão.
6. **Onde aparece:**
   - Gestão, detalhe do processo: card "Motivo do indeferimento" com `reason` e a fundamentação legal.
   - Portal, protocolo do cidadão: mesmo texto (é o parecer oficial do seu pedido; não há dado pessoal de terceiro).
   - REGIN `JUSTIFICATIVA_ANALISE`: `reason` seguido da fundamentação legal (limite de 10.000 caracteres do campo).
   - PDF de indeferimento: `reason` como justificativa principal.
   - A consulta pública (sem login) continua sem motivo (LGPD, já decidido).

### Camadas

7. **Logradouros de Salvador inteira:** novo comando `geo:importar-wfs` que baixa uma FeatureType do GeoServer SEDUR paginada (WFS 2.0, `count`/`startIndex`, `sortBy`) e grava como nova versão vigente da camada local, pelo `GeoJsonLayerImporter` existente. Para vias: `logradouro:VM_LOGRADOURO` (`CODLOG`, `NOME_LOGRADOURO`, `HIERARQUIA`). Roda no host de produção (a rede de dev é barrada pelo firewall). A versão `geosalvador-logradouros-centro` fica substituída.
8. **Zona fictícia fora de produção:** comando `geo:encerrar-camada {tipo} {versao}` fecha a camada fictícia (status substituída, `valid_to` hoje) sem apagar dado, com auditoria. Rodado em produção após aprovação. O `ZonaFicticiaDevSeeder` passa a recusar ambiente `production` além do `DemoMode`. Sem camada local vigente, a zona vem do GeoServer (`GeoServerWfsZonaClient`), que é o comportamento já previsto.
9. **Camadas incidentes do imóvel:** `CamadasIncidentesService` identifica, para o ponto do imóvel (`pontoDoImovel()`):
   - logradouro tributário da inscrição (SEFAZ) e os logradouros da camada de vias num raio curto do ponto (todos, não só o mais próximo), com CODLOG e nome;
   - bairro e zona;
   - cada camada poligonal das regras territoriais ativas (ZEM, ZPAM, ABM, ZUE, loteamentos TAC), consultada **sem o filtro da regra** para devolver o atributo da feição (ex.: `TRECHOS = 5`), marcando se a regra incide.
   Resultado gravado em `viability_requests.camadas_incidentes` (jsonb) com a versão/fonte de cada camada e a data da identificação. Materializado no protocolo e refeito na decisão expressa (reaproveitado pela avaliação das regras territoriais, sem consulta dupla).
10. **Exibição:** ficha de análise e detalhe do processo mostram "Camadas do imóvel" (camada → elemento identificado, ex.: "Logradouros → 212 — Avenida Lafayete Coutinho"; "Área de Borda Marítima → Trecho 5"). Camada indisponível aparece como indisponível, nunca omitida.
11. **Sem parâmetro novo.** O raio de logradouros próximos é constante técnica em `config/viabiliza.php` (mesmo padrão de `via_max_metros`).

## 3. Fora de escopo

- Polígono do lote com cobertura total (pendência SEDUR já registrada).
- Importar localmente as camadas ZEM/ZPAM/ABM/ZUE (continuam consultadas ao vivo no GeoServer).
- Redação oficial dos textos de indeferimento sem BAP e convite expirado (pendência SEDUR; entram editáveis).
- Reprocessar decisões já emitidas.

## 4. Critérios de aceite

- **CA-J1** Indeferimento expresso por Quadro 10 grava `reason` com o texto padrão `justificativa.conclusao.nao_permitido`, com o CNAE e a zona.
- **CA-J2** Indeferimento expresso por Quadro 11A grava o texto `justificativa.conclusao.nao_permitido_via` com a classe viária.
- **CA-J3** Vários CNAEs vetados: um parágrafo por CNAE.
- **CA-J4** RT-01 mantém o texto do art. 172-A.
- **CA-J5** Sem BAP e convite expirado gravam os textos do catálogo.
- **CA-J6** Análise técnica não indefere sem parecer; com parecer, `reason` = parecer.
- **CA-J7** Salvar decisão indeferida sem `reason` lança exceção.
- **CA-J8** O detalhe do processo (gestão), o protocolo do portal, o PDF de indeferimento e o `JUSTIFICATIVA_ANALISE` do REGIN trazem o `reason`.
- **CA-C1** `geo:importar-wfs via logradouro:VM_LOGRADOURO` pagina até o fim, grava nova versão vigente e audita a contagem (testado com `Http::fake`).
- **CA-C2** `geo:encerrar-camada zona zona-ficticia-dev` fecha a camada e a zona passa a vir do GeoServer.
- **CA-C3** Processo protocolado grava `camadas_incidentes` com logradouro tributário, logradouros próximos, bairro, zona e cada camada poligonal (dentro/fora, atributos da feição).
- **CA-C4** Imóvel no Trecho 5 da ABM registra "Área de Borda Marítima → Trecho 5" e a regra RT-03-ABM incide; imóvel no Trecho 3 registra o trecho, sem incidência.
- **CA-C5** Camada WFS indisponível fica registrada como indisponível e a regra continua degradando para análise (CA-RT-09).
- **CA-C6** Ficha e detalhe do processo mostram as camadas do imóvel.

## 5. Riscos

- **Volume do WFS de logradouros:** dezenas de milhares de feições; paginação com timeout técnico e retomada por página. A importação é operação manual, não roda no request.
- **Zona após remover a fictícia:** processos do Centro passam a depender do WFS de zona; GeoServer fora do ar degrada para pendente (análise), como já é no resto da cidade.
- **Texto ao cidadão:** o `reason` da análise técnica é escrito pelo analista e vai ao portal — o request passa a avisar que o parecer é visível ao requerente.
