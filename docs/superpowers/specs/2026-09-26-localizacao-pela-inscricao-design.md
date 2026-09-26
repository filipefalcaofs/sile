# Spec — Localização do imóvel pela inscrição imobiliária

Data: 2026-09-26
Origem: processo 5921000030-00990026/2026 (#92, simulação REGIN), deferido quando deveria ter sido indeferido pela RT-01 (logradouro 212 × CNAE 4681-8/02). Investigação feita contra o banco e o GeoServer de produção.

## 1. Problema

O motor decide a localização do imóvel a partir do polígono. Três falhas apareceram:

1. **Simulação REGIN usa polígono fictício.** Sem `ponto` no catálogo, `ReginProtocoloSimulacaoService::poligonoDoProtocolo()` devolve `POLIGONO_HOMOLOGACAO`, um quadrado fixo no Comércio (−12,9710, −38,5107). O processo #92 foi avaliado a 459 m do imóvel real.
2. **REGIN real não tem localização.** O RUC da Juceb não traz polígono nem coordenada (Guia Técnico v2.10 e OpenAPI de produção: `RUC_ESTAB` só tem endereço em texto; `RES_QUAD_LOTE` e `RES_NUM_REG_PROP` "não estão sendo utilizados"). Sem ponto, as regras de poligonal (TAC, ZEM, ZPAM, ABM, ZUE) degradam para "camada indisponível" e **todo** processo REGIN vai à análise; zona, bairro e CODLOG ficam vazios.
3. **CODLOG pela via mais próxima não é confiável.** Mesmo com o ponto correto da SEFAZ para a inscrição 344672, a via mais próxima é a Rua Luiz Murat (25,9 m), não a Av. Lafayete Coutinho (26,6 m). Em quarteirões estreitos e lotes de esquina, geometria não decide o logradouro.

## 2. Fontes verificadas (produção, 2026-09-26)

| Fonte | O que oferece | Cobertura |
|---|---|---|
| Cadastro Imobiliário SEFAZ (`SedurSefazPropertyRegistryLookup`) | `CoordenadaX/Y` (UTM 24S → WGS84 já convertido), `CodigoLogradouroTributario`, endereço, área do terreno | Toda inscrição válida |
| GeoServer `parcelamento_lotes:VM_PARCELAMENTO_LOTES_EDIF` | Polígono do lote | Só loteamentos aprovados (o Comércio não está coberto) |
| GeoServer `enderecamento:VM_ENDERECAMENTO` | Polígono por CODLOG + numeração métrica | Só endereços que passaram por licenciamento (nº 6 da via 212 não existe) |
| GeoServer | Camada com inscrição imobiliária | Não existe (só no `ws-sigs`, sem acesso) |

Conclusão: a SEFAZ é a única fonte com cobertura total para **ponto** e **logradouro**. Não há fonte com cobertura total para o **polígono do lote**.

## 3. Decisões

1. **Logradouro = logradouro tributário da SEFAZ.** Quando o processo tem inscrição e a SEFAZ devolve `CodigoLogradouroTributario`, esse é o `cod_log` do processo, sobrepondo o da via mais próxima. A via mais próxima só vale sem inscrição ou com SEFAZ sem o código. A mesma numeração é usada pela SEDUR nas regras territoriais.
2. **Ponto do imóvel sem polígono inventado.** Novas colunas `property_point_lat`, `property_point_lng` e `property_location_source` em `viability_requests`. O ponto do imóvel passa a ser: centroide do polígono, quando houver; senão, o ponto gravado. Nunca se desenha quadrado em volta de um ponto.
3. **Origem da localização explícita** (`PropertyLocationSource`): `poligono` (desenhado pelo requerente/operador), `cadastro_imobiliario` (ponto SEFAZ), `catalogo_simulacao` (ponto do catálogo da simulação). A tela mostra a origem.
4. **REGIN e simulação localizam pela inscrição no protocolo.** Sem polígono e sem ponto, o protocolo consulta a SEFAZ e grava o ponto. SEFAZ indisponível ou inscrição sem coordenada: fica sem ponto, com auditoria — o comportamento atual (degradar para análise) continua valendo, nunca inventa local.
5. **Simulação perde o polígono fixo.** `POLIGONO_HOMOLOGACAO` e o quadrado do `ponto` do catálogo saem. Ordem: ponto SEFAZ; senão, ponto do catálogo (`catalogo_simulacao`); senão, sem localização. A zona/via da decisão simulada continua vindo do catálogo (`territorioDoSnapshot`), como hoje.
6. **HU-037 RN-005 — divergência de localização.** Quando o polígono foi desenhado (`poligono`) e a inscrição tem ponto na SEFAZ, compara a **zona** do centroide do polígono com a zona do ponto SEFAZ. Divergindo, a decisão automática é bloqueada e o processo vai à análise com o motivo das duas zonas (CA-03 da HU-037). A **via** não é comparada pela geometria: a via mais próxima provou-se não confiável (§1.3) e o logradouro já passa a vir da SEFAZ (decisão 1), o que neutraliza a divergência de via. Sem parâmetro novo: a regra é binária (mesma zona ou não). Verificação impossível (SEFAZ ou zona indisponível): não bloqueia, registra na auditoria que não foi feita.
7. **Precedência no expresso:** indeferimento territorial (RT-01) → divergência de localização (análise) → demais regras territoriais (análise) → fluxo atual. A RT-01 vem antes porque se apoia no logradouro tributário, independente do polígono.
8. **A proteção já entregue na RT-01 fica.** `TerritorialRuleService::logradouroTributarioCasa()` continua como segunda fonte para processos protocolados antes desta mudança.
9. **Consulta à SEFAZ uma vez por processo e por execução.** `LocalizacaoInscricaoService` memoriza por inscrição durante a requisição/job; protocolo, CODLOG, RT-01 e divergência reaproveitam o resultado.

## 4. Fora de escopo

- Polígono do lote com cobertura total: depende de base de lotes vinculada à inscrição (cadastro multifinalitário ou `inscricoes_imobiliaria` do `ws-sigs`). **Pendência SEDUR** registrada no `STATE.md`. HU-037 RN-004 (sobreposição com lote oficial) segue bloqueada pelo mesmo motivo.
- Usar `parcelamento_lotes` e `enderecamento` como fonte parcial de polígono: possível depois, com a cobertura documentada; não entra agora.
- Devolver a coordenada ao REGIN no bloco `ANALISES.AREA.COORDENADAS` do parecer.
- Reprocessar o processo #92 (simulação já decidida; refazer a simulação após o deploy).
- Portal: o cidadão continua desenhando o polígono; esta spec só acrescenta o CODLOG tributário e a checagem RN-005.

## 5. Critérios de aceite (viram feature tests)

- **CA-L1** Processo REGIN com inscrição localizada na SEFAZ e sem polígono protocola com `property_point_*` preenchido, `property_location_source = cadastro_imobiliario`, zona e bairro materializados a partir do ponto.
- **CA-L2** Processo REGIN com SEFAZ indisponível protocola sem ponto, audita a indisponibilidade e segue o comportamento atual (sem localização inventada).
- **CA-L3** Processo com inscrição cujo logradouro tributário é 212 grava `cod_log = 212` mesmo que a via mais próxima do ponto/polígono seja outra; a divergência entre as duas é auditada.
- **CA-L4** Processo sem inscrição mantém `cod_log` da via mais próxima (comportamento atual).
- **CA-L5** Simulação REGIN sem ponto no catálogo e com SEFAZ localizando: processo sem polígono, com ponto SEFAZ. Sem SEFAZ e sem ponto no catálogo: sem polígono e sem ponto (nunca o quadrado fixo).
- **CA-L6** Reprodução do #92 pela simulação: inscrição 344672 (tributário 212) + CNAE 4681-8/02 → indeferido pela RT-01.
- **CA-L7** Com ponto SEFAZ, as regras de poligonal são avaliadas no ponto (não degradam para "camada indisponível" só por falta de polígono).
- **CA-L8** Polígono desenhado em zona diferente da zona do ponto SEFAZ → encaminhado à análise com motivo citando as duas zonas; queda registrada com gatilho `localizacao_divergente`.
- **CA-L9** Mesma zona → segue o fluxo normal. SEFAZ ou zona indisponível → segue o fluxo, com auditoria "verificação de localização não realizada".
- **CA-L10** RT-01 incidente tem precedência sobre a divergência (indefere).
- **CA-L11** A tela do processo mostra o ponto quando não há polígono, com a origem da localização.

## 6. Riscos

- **Precisão do ponto SEFAZ:** coordenada cadastral pode estar deslocada. Mitigação: a RN-005 aponta divergência quando há polígono; sem polígono, o ponto é a melhor fonte disponível e a origem fica explícita.
- **Latência:** uma consulta SEFAZ a mais no protocolo (timeout 12 s, retry 3). Mitigação: memo por execução; protocolo não bloqueia por indisponibilidade.
- **Divisa de zona:** ponto e polígono corretos podem cair em zonas diferentes perto de divisas, gerando análise desnecessária. Aceito: CA-03 da HU-037 manda bloquear quando não há precisão garantida.
