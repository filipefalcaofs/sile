# Análise — HUs × Reunião SEDUR × Fontes Oficiais

Data: 2026-06-09 (atualizado em 2026-06-11)
Fontes: transcrição da reunião Lisa (SEDUR) × Filipe (Sudoeste); Carta de Serviços do Portal Simplifica; hotsite do SLI; páginas oficiais da LOUOS no site da SEDUR; segunda reunião com demonstração do sistema legado SAPS em 2026-06-11 (`docs/transcricao-reuniao-sedur.rtf` + gravação de tela — frames em `docs/legado-saps/`).

## 1. Pontos da reunião confirmados pelas HUs

| Ponto da reunião | HUs que cobrem |
|---|---|
| Entrada pelo integrador federal (REDESIM), que direciona para cada órgão | HU-022, HU-103 |
| Dois destinos do processo: fluxo expresso ou análise humana | EP09 (HU-073 a HU-078), EP10 |
| Quadro 7 enquadra a atividade usando a área informada pelo requerente | HU-038, HU-063 |
| Quadros 10/11/11A verificam se a atividade pode na zona, na via, ou ambas | HU-039, HU-040, HU-041, HU-031, HU-032 |
| Baixo risco exige finalização expressa (obrigação legal) | HU-048, HU-073 |
| Alto risco sempre vai para análise humana; **médio risco → expresso automático** (refinado 2026-06-12) | HU-049, HU-050 |
| CNAE alto risco por lei liberado expressamente conforme localização | HU-051 |
| Expresso pode deferir ou indeferir | HU-074, HU-075 |
| Resposta volta para a Junta Comercial (integrador) com parecer | HU-104, HU-085 |
| Planilha de condicionantes por CNAE mantida pela SEDUR | HU-019, HU-020 |

## 2. Itens confirmados após a reunião

- **Integração SEFAZ via API** (confirmado pela Lisa em 2026-06-09): hoje o deferimento da viabilidade chega à SEFAZ via API. Criada a HU-110 — Integrar com SEFAZ municipal. A base da API (autenticação JWT SenhaWeb, URLs, padrões de resiliência) já é conhecida pela implementação de referência do SIGVISA (seção 4B); pendente apenas o endpoint específico de envio do deferimento de viabilidade e credenciais para o SILE.
- **Planilha Unificada CNAE recebida** (30.04.26): arquivada em `docs/dados-oficiais/` (xlsx + csv). Análise na seção 4A abaixo.

## 3. Lacunas identificadas (tratadas nesta revisão)

| Lacuna | Origem | Tratamento |
|---|---|---|
| Envio do deferimento à SEFAZ municipal | Reunião (confirmado via API) | Nova HU-110 (EP13) |
| Parâmetros concretos de baixo risco (edificação não residencial, área ≤ 1.250 m²) | Reunião | RNs adicionadas à HU-048 |
| TVL como documento formal emitido | Carta de Serviços Simplifica | HU-076 refinada |
| Geração de DAM (taxa TLL) | Carta de Serviços Simplifica | Nova HU-071 (EP08) — escopo a confirmar |
| Confirmação de pagamento do DAM e liberação do TVL; indeferimento por não pagamento (Decreto 32.155/2020) | Carta de Serviços + Dúvidas Frequentes Simplifica | Nova HU-072 (EP08) — escopo a confirmar |
| Migração/convivência com o sistema legado .NET (parado, com proxy improvisado) | Reunião | Nova HU-111 (EP13) |
| Campos reais do formulário de viabilidade (escritório virtual, área pública/concessão, acessos independentes, anuência de condomínio, vagas) | Formulário do Simplifica | Campos adicionados à HU-062 |
| "Quadro 11" não existe isolado na LOUOS oficial (existem 11A e 11B) | Site SEDUR (Lei 9.148/2016) | Observações nas HU-040, HU-041, HU-017, HU-018 |

## 4. Divergências e correções de referência

- **Número da lei**: a LOUOS vigente é a **Lei nº 9.148/2016**. A própria Carta de Serviços do Simplifica cita "9.146/2016" — usar 9.148/2016 como referência oficial (confirmada no site da SEDUR).
- **Quadros oficiais da LOUOS (Anexo 01, Lei 9.148/2016)**:
  - Quadro 07 — Enquadramento de usos por grupo e subgrupo nR1, nR2, nR3
  - Quadro 08 — Enquadramento de usos nR4, nRa
  - Quadro 09 — Enquadramento de usos ID1, ID2, ID3
  - Quadro 10 — Usos permitidos por zonas de uso
  - Quadro 11A — Condições de instalação em função da classificação viária
  - Quadro 11B — Condições de instalação por subcategoria de uso
  - Quadro 12 — Parâmetros de incomodidade
- Na reunião foi citado "quadro 11 e quadro 10" e depois "quadros 11, 11A e 10". O "Quadro 11" das HUs provavelmente corresponde ao **11B** — confirmar.
- **Volumetria divergente na reunião**: 1.332 CNAEs no total; "990 são baixo risco" e, em seguida, "178 CNAEs são baixo risco". Os números não fecham — confirmar (hipótese: 990 ocorrências de CNAE × condicionante na planilha vs 178 CNAEs distintos).

## 4A. Planilha Unificada CNAE 30.04.26 — análise

Arquivos: `docs/dados-oficiais/Planilha-Unificada-CNAE-30.04.26.xlsx` e `planilha-unificada-cnae-30-04-26.csv`.

**Estrutura (15 colunas):** CNAE, Descrição, Fator Multiplicador, Risco, Há condicionante?, Condicionante, Pergunta relacionada à condicionante, Pergunta complementar (direcionadora), Autorizado para Escritório Virtual?, Autorizado para MEI?, Macroárea, Exige RT?, Exige PBA?, Documentação específica por CNAE, Base legal.

**Números:**
- 285 linhas com CNAE; 261 CNAEs distintos (14 CNAEs repetem em mais de uma linha — variações de descrição/subatividade e de macroárea, ex.: 5611-2/01 aparece como churrascaria, pizzaria, restaurante e rotisseria).
- Risco: 89 Baixo, 128 Médio, 68 Alto.
- Condicionante: 69 Sim, 216 Não. Cruzamento: Baixo com condicionante 18; Médio com condicionante 51; Alto nunca tem condicionante (decisão direta).
- Macroáreas: Alimentos (89), Interesse da saúde (145), Serviços de saúde (47), Medicamentos (4).

**Importante — esta NÃO parece ser a planilha completa citada na reunião.** A reunião citou 1.332 CNAEs (com 990 ou 178 de baixo risco); esta planilha tem 285 linhas e todas as macroáreas são de vigilância sanitária (alimentos/saúde/medicamentos). Hipótese: é o recorte da VISA/sanitário ("unificada" entre órgãos), não a tabela completa de viabilidade urbanística. Confirmar com a SEDUR e solicitar a tabela completa.

**Insights para o modelo de dados (EP06/EP02):**
- A condicionante é operacionalizada como **pergunta dirigida ao requerente** (autodeclaração) que pode reclassificar o risco — ex.: "O resultado será diferente de produto artesanal?" Se sim, o CNAE baixo risco vira Alto. O modelo precisa de: texto da condicionante, pergunta, pergunta complementar direcionadora e efeito da resposta (reclassificação).
- **Fator Multiplicador** (por cômodo, por consultório, por box) existe para 26 linhas — afeta o cálculo de taxa (relevante para HU-071/DAM).
- **Autorizado para Escritório Virtual?** conecta com o campo de escritório virtual do formulário (HU-062).
- **Autorizado para MEI?** é dimensão nova, não citada em nenhuma HU.
- **Exige RT?** (responsável técnico) tem valores condicionais ("Sim, caso seja Alto Risco", "Sim, caso haja fracionamento de produtos") — regra dependente do resultado da classificação.
- **Exige PBA?** e **Documentação específica** estão 100% vazias nesta versão — confirmar se serão preenchidas.
- Dados precisam de normalização na importação: "Baixo " com espaço, "NÃO"/"Não", quebras de linha dentro de células, "−" como vazio.
- Um CNAE pode ter N linhas (subatividades/macroáreas) — a chave não é o CNAE sozinho.

## 4B. SIGVISA (sls-sms) como implementação de referência

O projeto `sls-sms` (SIGVISA — licenciamento sanitário de Salvador, mesma prefeitura) possui módulos prontos e operacionais que aceleram o SILE:

| Módulo SIGVISA | O que oferece | Onde aproveita no SILE |
|---|---|---|
| DAM completo (`docs/DAM-SEFAZ-IMPLEMENTACAO.md`) | Cálculo, número sequencial, código de barras FEBRABAN validado contra 146.711 DAMs reais, PDF, e-mail, parametrização bancária | HU-071 |
| Baixa automática SEFAZ (`dam:baixar-pagos`, hourly) | Consulta de pagamentos em lotes de 100 via `POST /DAM/ConsultarDamsSEMOP`, baixa manual de contingência, emissão automática do documento quando tudo pago | HU-072 |
| `SefazService` | Autenticação JWT SenhaWeb com cache, retry, renovação em 401, normalização de respostas; URLs de produção e homologação conhecidas | HU-110 |
| `Cnae` + `CnaePergunta` | CNAE com grau de risco, fator multiplicador, exigência de RT; pergunta condicionante com `grau_risco_sim`/`grau_risco_nao` (reclassificação pela resposta) | HU-047, HU-048, HU-011 |
| `Requisito` (N:N com CNAE) | Documentação exigida por CNAE com instruções de validação por IA | HU-066, HU-067, EP14 |
| Trilha de auditoria (`HasAuditoria` + spatie/laravel-activitylog) | Auditoria transversal por trait nos models | EP12 / infraestrutura |
| Assinatura digital (`AssinaturaDigitalService` + `GovBrAssinaturaClient`) | Assinatura ICP-Brasil via PFX e via API gov.br (OAuth, certificadoPublico, assinarPKCS7), geração de termo, hash, validação de certificado | HU-076 (TVL), HU-085 (parecer), confirmação de RT |
| Verificação pública com QR code | Rota pública `/verificar-alvara/{codigo}`; QR no PDF aponta para página de autenticidade | HU-076 — TVL verificável |
| Geocodificação (`NominatimClient`, `GeocodingService`, job assíncrono, comando em lote, `CepService`) | Geocodificação via Nominatim/OSM, enriquecimento de endereço, frontend Leaflet + leaflet.heat (mapa de calor) | HU-029, HU-030, EP15 (dashboard geográfico) |
| Módulo de IA multi-provider (`ConfigIa`, `AnaliseDocumentoIaService`, `IaPromptBuilder`, `IaResponseParser`) | Múltiplas configurações de IA administráveis (provider, base_url, api_key criptografada, modelo, temperatura, timeout, toggle ativo/padrão); análise documental guiada por instruções por requisito | EP14 (HU-112 a HU-121), HU-014 |
| Framework de importação CSV (`CsvImport/` + `ImportBatch`/`ImportRecord`/`ImportError`) | Mappers registráveis, normalização, dedup com exceções tipadas, upsert, rollback, contadores e relatório de erros — usado na migração real do legado PGLS (146 mil DAMs) | HU-111 |
| Deduplicação (`EstabelecimentoDeduplicacaoService`, `ResponsavelTecnicoDeduplicacaoService`) | Identificação e fusão de registros duplicados pós-migração | HU-111, EP03 |
| Ciclo de vida agendado (scheduler) | Baixa de DAMs (hourly), inativação de usuários inativos, alvarás vencidos, notificação de vencimento, sincronização SEFAZ noturna com `withoutOverlapping` | HU-072, HU-093, EP13 |
| Permissões (spatie/laravel-permission) | Perfis e permissões granulares por funcionalidade | HU-013, EP01 |
| `SecurityHeaders` middleware, rotas segregadas `portal.php`/`visa.php`, Enums de status | Padrões de segurança e organização Portal do Cidadão × Retaguarda | EP01, arquitetura |

Ressalva de domínio: no SIGVISA a taxa é a TVS (por CNAE, com cobrança retroativa por exercício); no SILE é a TLL (atividade de maior valor + taxa de serviço). A mecânica (DAM, código de barras, baixa) é reaproveitável; a fórmula de cálculo não.

Ressalva de stack: o frontend do SIGVISA é Vue 3 + PrimeVue + Inertia v2 (com Tiptap, Leaflet, driver.js) e os testes são Pest + Playwright (~1.800 casos); o SILE é React 19 + Inertia v3 + PHPUnit. Padrões de tela e fluxos servem de referência; código de frontend não é portável diretamente. As libs de backend são todas aplicáveis: dompdf, picqer/barcode, simple-qrcode, maatwebsite/excel, smalot/pdfparser, spatie/permission, spatie/activitylog.

Documentos úteis no repositório SIGVISA: `docs/ESCOPO-TECNICO-SIGVISA.html` (modelo de escopo técnico), `docs/Novo Modelo_ Documento de História de Usuário.docx` (template de HU), `docs/LEVANTAMENTO-DADOS-MIGRACAO.html` e planilhas `MIGRACAO-*.xlsx` (metodologia de levantamento para migração), `docs/GUIA-OKD.docx` (deploy OKD/OpenShift).

## 4C. Fontes oficiais de CNAE e classificação de risco (pesquisa 2026-06-09)

A pesquisa resolveu a divergência de volumetria. Arquivos baixados em `docs/dados-oficiais/`.

**1. Tabela CNAE nacional (IBGE/CONCLA)** — os "1.332 CNAEs" da reunião são o total de subclasses da **CNAE-Subclasses 2.3** (vigente desde 01/01/2019; a revisão seguinte está prevista apenas para 2027). Fonte oficial: [concla.ibge.gov.br](https://concla.ibge.gov.br/classificacoes/download-concla.html). Arquivo: `CNAE_Subclasses_2_3_Estrutura_Detalhada.xlsx` (a estrutura baixada contém 1.331 códigos; a publicação oficial cita 1.332 — diferença de 1 a verificar na importação).

**2. Classificação de risco municipal unificada (a lista "de uso comum aos órgãos")** — é o **Decreto Municipal nº 32.636/2020**, com anexos na redação dada pelos **Decretos nº 37.407/2023 e nº 38.673/2024**. Classifica **todas as 1.331 subclasses** da CNAE em três anexos, com condicionantes por CNAE. Extraído para `decreto-32636-2020-risco-municipal-unificado-cnae.csv`:
- BAIXO RISCO A: 767 CNAEs (todos com condicionantes)
- BAIXO RISCO B: 328 CNAEs
- ALTO RISCO: 236 CNAEs
- 1.045 CNAEs possuem condicionantes; as mais frequentes confirmam a reunião: "área utilizada não ultrapasse 1.250 m²" (700x), "não esteja em imóvel residencial" (693x), "seja escritório da empresa" (529x).
- O decreto também prevê o mecanismo "depende de informações (DI)": perguntas respondidas no registro/licenciamento direcionam a classificação — exatamente o modelo de condicionante-pergunta da planilha VISA e do SIGVISA.

**3. Cruzamentos realizados:**
- Decreto × IBGE 2.3: cobertura 1:1 (0 códigos fora, 0 faltantes).
- Planilha VISA (260 CNAEs distintos) × decreto: todos os 260 estão no decreto; 52 divergem de classificação (esperado — a planilha VISA é risco **sanitário**, o decreto é risco **municipal unificado**; são dimensões diferentes que o SILE precisa manter separadas).
- Os números "990" e "178" citados na reunião não batem com a redação atual do decreto (767 A / 328 B / 236 Alto) — provavelmente referem-se a uma redação anterior dos anexos. Confirmar qual versão o sistema atual usa.

**4. Contexto normativo nacional** — Lei 13.874/2019 (Liberdade Econômica), Resoluções CGSIM nº 51/2019 (alterada pela 68/2022 — baixo risco A nacional) e nº 66 (sanitário). O decreto municipal segue essa taxonomia (risco I/A, II/B, III/alto).

**Implicação para o SILE:** o seed oficial de CNAEs vem do IBGE/CONCLA (HU-011) e a classificação de risco municipal vem do anexo do Decreto 32.636/2020 na redação vigente (HU-020), com as condicionantes como perguntas dirigidas ao requerente (HU-019, HU-048). A planilha VISA é dimensão sanitária complementar, não substituta.

## 5. Pauta de perguntas — visita de quinta-feira

### Regras e quadros da LOUOS
1. O "Quadro 11" usado por vocês corresponde ao Quadro 11B oficial (condições por subcategoria de uso)? Os Quadros 08, 09 e 12 entram no escopo do motor de regras?
2. Podem disponibilizar os quadros parametrizados (planilhas) que o sistema atual usa, com versão vigente?

### Classificação de risco
3. ~~Quais são as condicionantes gerais de baixo risco?~~ **Respondido pela pesquisa**: estão no anexo do Decreto 32.636/2020 (redação 38.673/2024) — área ≤ 1.250 m², imóvel não residencial, escritório da empresa. Confirmar apenas se o sistema atual usa essa redação vigente.
4. Os números citados na reunião (990 e 178 de baixo risco) não batem com a redação atual do decreto (767 Baixo A / 328 Baixo B / 236 Alto). Qual versão dos anexos o sistema atual usa? Há lista interna diferente da publicada?
5. Para médio risco (Baixo B) com liberação expressa: a regra de decisão é exatamente a condicionante do anexo do decreto, ou existe parametrização adicional interna?
6. ~~A planilha recebida é um recorte da VISA?~~ **Confirmado pelo usuário**: a Planilha Unificada CNAE 30.04.26 veio da Vigilância Sanitária (dimensão sanitária). 52 dos 260 CNAEs dela divergem do risco municipal unificado do decreto — o SILE deve tratar risco sanitário e risco municipal como dimensões separadas? Qual prevalece para o TVL?
7. Na planilha, as colunas "Exige PBA?" e "Documentação específica por CNAE" estão vazias — serão preenchidas? O que significa PBA nesse contexto?
8. O "Fator Multiplicador" (por cômodo, consultório, box) é usado no cálculo de qual taxa? Entra no escopo do SILE?

### Fluxo, TVL e pagamento
9. ~~O SILE emite o TVL formal (documento com validade)?~~ **Respondido em 2026-06-11, refinado em 2026-06-12**: o documento **não é entregue ao requerente** — dados do deferimento via API SEFAZ + parecer Regin. O **PDF/TVL permanece no backoffice** para emissão sob demanda pelo analista (HU-132). Formato/assinatura a definir com Anderson.
10. ~~Geração de DAM fica dentro do SILE?~~ **Parcialmente respondido em 2026-06-11**: o DAM de viabilidade é emitido/pago pela SEFAZ (o Simplifica só trata DAM de construção). O SAPS possui aba "Visualizar DAM" no processo. Confirmar o que o SILE precisa exibir/integrar (HU-071/HU-072).

### Integrações
11. API da SEFAZ: a autenticação (JWT/SenhaWeb) e as consultas já são conhecidas via SIGVISA. Qual é o endpoint/payload específico para ENVIAR o deferimento da viabilidade? Podem providenciar credenciais SenhaWeb para o SILE e acesso à homologação?
12. ~~Qual o protocolo de comunicação com o integrador federal/Junta?~~ **Parcialmente respondido por pesquisa (2026-06-11)**: o integrador estadual da Bahia é o **Regin** (produto da Prosolution, operado pela JUCEB); a SEDUR está integrada desde 13/07/2021. O modelo é o da Resolução CGSIM nº 61/2020 (art. 6º-7º): o Regin coleta os dados (inclusive o formulário específico do município), disponibiliza ao município e recebe a resposta da viabilidade; no pós-registro, a JUCEB envia XML do ato à prefeitura. Existe um "Manual de Integração REDESIM" nacional com webservices numerados (WS01, WS02, WS15, WS29...), porém sem fonte oficial aberta; a Prosolution só entrega a especificação na implantação. **Pendente**: solicitar à SEDUR/JUCEB o contrato técnico da integração Regin↔SAPS em uso (payloads, endpoints, autenticação) — a especificação já existe e está operando hoje. Ver seção 7.8.
13. ~~Qual a base GIS usada para zona/via/lote?~~ **Parcialmente respondido em 2026-06-11**: o GIS é o SIGIS (campo "Zona e Via SIGIS" na ficha de análise). A base cartográfica em uso é a antiga ("S69") e a SEDUR exige migrar para a base atual ("CA 2000") — classificado como "problema gravíssimo". Pendente: acesso, camadas e formato.

### Legado e transição
14. ~~Qual o sistema atual?~~ **Parcialmente respondido em 2026-06-11**: o sistema de análise em uso é o **SAPS — Sistema de Análise de Processos SIMPLIFICA** (acesso restrito à rede interna), considerado lento e "uma dor de cabeça". Pendente: confirmar se será desligado, dados a migrar (processos históricos, TVLs) e papel do proxy.
15. ~~Como o SILE se posiciona em relação ao SLI e ao Simplifica?~~ **Respondido em 2026-06-11**: o SILE substitui apenas a **viabilidade** ("a gente precisa de algo pra viabilidade que a gente realmente não tem nada"). Construção já está sendo coberta por outro sistema novo (com Anderson). O portal Simplifica cidadão permanece como canal de acompanhamento (e de entrada para renovação, que não passa pelo Regin).
16. ~~Conseguem dar acesso ao ambiente?~~ **Respondido em 2026-06-11**: sim — a demonstração foi feita ao vivo e ficou acordado acesso para acompanhamento. Em contrapartida, será disponibilizado ambiente de desenvolvimento do SILE para a SEDUR acompanhar as versões.

## 6. Impacto no início do desenvolvimento

Nenhum item acima bloqueia o início. EP01 (identidade/acesso), EP02 base (CNAEs, usuários, perfis), EP03 (cadastro empresarial) e a infraestrutura de auditoria independem das respostas. O motor de regras (EP05/EP06) deve ser construído **parametrizável** (regras como dados, não como código), de modo que as planilhas e quadros oficiais alimentem o sistema quando forem entregues. As respostas são necessárias antes de **executar** EP05/EP06/EP09 com dados reais e EP13 (contratos das APIs).

## 7. Segunda reunião (2026-06-11) — demonstração do sistema legado SAPS

Fontes: `docs/transcricao-reuniao-sedur.rtf` (transcrição) e gravação de tela da demonstração (frames-chave em `docs/legado-saps/`, numerados na ordem do fluxo).

### 7.1 Fluxo de entrada detalhado (Regin/Junta Comercial → SAPS)

1. Requerente dá entrada no Regin (Junta Comercial): município, órgão de registro, evento (matriz, filial ou alteração — endereço/atividade).
2. Preenche o formulário próprio da SEDUR (aberto via token dentro do fluxo da junta) → gera o **número de processo** da SEDUR. Campos observados: inscrição imobiliária (pré-preenche logradouro/dados em cinza), complemento (pré-preenchido quando a inscrição tem edifício comercial; descrição obrigatória), ponto de referência obrigatório, área, CNAEs (até 99, uma principal), objeto social (copiado da descrição do CNAE), escritório virtual (se sim: valida existência de sede na inscrição imobiliária e CNPJ com viabilidade de sede), área pública (se sim: exige concessão de uso, senão bloqueia o cadastro), perguntas condicionantes por atividade, **polígono de 4 pontos no mapa** (validado contra o logradouro informado) → extrai **zona e via**.
3. Ao concluir o termo de responsabilidade no site da junta → gera o **protocolo BAP**.
4. O SAPS recebe e **vincula BAP ↔ processo** (10–20 min) e então: resposta automática (expresso) ou envio para análise.
5. Sem vinculação do BAP em **48h** → status **"indeferido sem atuação"**. Causas possíveis: falha no envio, falha na recepção ou requerente não gerou o BAP.
6. Documentos anexos: foto da fachada (obrigatória), contrato de locação (opcional — pode ser proprietário), termo de concessão de uso (obrigatório se área pública).

O processo carrega **três identificadores**: número do processo (SEDUR), protocolo BAP (junta) e número do produto (TVL emitido). O cidadão acompanha pela junta ou pelo portal Simplifica cidadão (onde também responde "convites" do analista — a interação analista ↔ requerente é por lá).

### 7.2 O SAPS por dentro (telas mapeadas do vídeo)

**Categorias de processo** (checkboxes na consulta): **Expresso, Semi-Expresso, Malha Fina, Sede de Escritório**. "Semi-Expresso" é categoria nova não mapeada nas HUs — o processo demonstrado ("Revisão TVL - Inclusão de Atividade") era semi-expresso com ficha de análise humana. Confirmar a regra que separa expresso de semi-expresso.

**Consulta de processo** (`16-consultar-processo-filtros.jpg`): Grupo Status, Status do Processo, Status Tramitação, Número do Produto, Número do Processo, BAP; filtros avançados: grupo de serviço, serviço, setor, zona, datas, inscrição imobiliária, nome, CPF, CNPJ, CEP, código do logradouro, logradouro, bairro, nº porta.

**Detalhe do processo** (`01-detalhar-processo-cabecalho-abas.jpg`): abas **Tramitação, Informações do Processo, Polígono, Anexos, Histórico, Visualizar DAM, Vistoria**. Cabeçalho: processo, data de abertura, serviço, requerente (CPF/contatos), status (ex.: INATIVO). O histórico mostra a timeline (abertura → preenchimento → espera do BAP → resposta) — é como medem o tempo de cada etapa.

**Ficha de análise** (`02` a `08-*.jpg`) — o coração da análise humana:
- Dados do TVL: CodLog, logradouro, nº métrico, bairro, CEP, ponto de referência, **Zona** (ex.: ZPR 3), **Via** (ex.: VA-I), campo "Zona e Via SIGIS".
- **Por atividade (CNAE)**: radio **Deferida / Indeferida / Análise**; pergunta condicionante respondida pelo requerente (ex.: "A atividade será desenvolvida no local?" → "Não, no local funcionará o escritório da empresa"); **Descrição LOUOS** (código + descrição, ex.: 07.12.13 "Escritório (inclusive virtual), sede de empresa..."); **Grupo Uso** (ex.: nR1-12 / 1250.00); **Descrição TLL** (ex.: 1.01 "Administração, Organização e Planejamento") e **Valor TLL** (ex.: R$ 1.111,78); painel **"Gatilhos CNAE"** com o motivo de a atividade exigir análise.
- Gatilhos CNAE observados: "Necessário enquadramento pelo analista" (quando a atividade será exercida no local e não há enquadramento automático), "Atividades em zona ZEIS especial", "Informações do processo (...)". Levantar a lista completa.
- "Incluir Atividade" (analista pode adicionar CNAE) e "Confirmar dados".
- **Condicionantes**: checkboxes de textos que sairão no documento (vagas mínimas, "trata-se de escritório da empresa...", Lei Municipal 5.354/1998 — poluição sonora, licença da Vigilância Sanitária/Alvará de Saúde), busca de condicionantes e "Condicionantes Adicionais" (texto livre).
- **Vagas Estacionamento**: comparação "Dados Requerente" × "Exigido LOUOS/Análise CNLU" (vagas, carga e descarga, pátio, embarque/desembarque) → veredito **"Imóvel Não Conforme"**; seção "Vagas vistoria" com recálculo ("Recalcular resultado vagas").
- **Parecer Análise** (texto livre), **Salvar Ficha** (rascunho) e **Finalizar Ficha**; bloco "Motivo de Análise"; **Fichas de Revisão** versionadas (select "Revisão - data - analista", "Nova Ficha", "Imprimir Ficha").
- Regra confirmada: deferir exige **todas** as atividades deferidas; **uma** indeferida indefere o processo.

**Menu de serviços** (`09` a `12-*.jpg`): Consultar Processo, Anotação Alvará (não usado em viabilidade — é de construção), Dados Processo, Malha Fina, Caixa de Entrada, Configurações, Edifício Comercial.
- Configurações → **Permissões e Parâmetros**: Permissões do Perfil, **Parâmetros para indeferimento automático**, Parâmetro dos serviços, Permissões de Alvará.
- Configurações → **Cadastrar**: Perfil, Usuário Saps, Setor, **Condicionantes**, Complemento, **Enquadramentos**, **Feriado**.
- Configurações → Usuário Simplifica; **Relatórios Administrativos**: Tempo de Emissão de TVL, Sede de Escritório Virtual.

**Parametrização do motor (telas-chave para o SILE)**:
- **Cadastro CNAE** (`13-cadastro-cnae.jpg`): lista pesquisável de CNAEs com edição individual (regras por CNAE).
- **Enquadramento TVL** (`14-enquadramento-tvl-louos-faixas-area.jpg`): por código LOUOS → Classificação, código TLL, flag "Classificação de risco" e **até 3 enquadramentos por faixa de área** ("Até m²") + "Qualquer Área". Exemplos reais: LOUOS 07.01.05 (comércio de gêneros alimentícios) → TLL 2.02, nR1-01 até 350,00 m², nR2-01 acima; LOUOS 07.12.13 (escritório) → TLL 1.01, nR1-12 até 1.250,00 m², nR2-12 acima. **É o Quadro 7 da LOUOS operacionalizado como dados** — modelo de referência direto para o EP06.
- **Cadastro de Condicionantes** (`15-cadastro-condicionante-pergunta-regra.jpg`): por CNAE, com **Pergunta** (reutilizável, select + adicionar), **Regra** (numérica, ex.: 7) e **Crítica** (select). Levantar a semântica de "Regra" e "Crítica".

### 7.3 Problemas relatados (motivações do SILE)

| Problema | Detalhe |
|---|---|
| Prazo inflado | Diretora reporta média de 19 dias; medição interna deu 42h (já foi 4h). Causa suspeita: contagem corrida incluindo fins de semana (+48h cada) e feriados cadastrados (+24h cada). SILE deve contar prazos em regime correto e parametrizável |
| Expresso subutilizado | Só ~405 CNAEs têm resposta expressa hoje; atividades de baixo risco caem em análise humana indevidamente. Automatizar o enquadramento é a aposta central para reduzir prazo |
| Volume de vistorias | Alto — problema interno relacionado |
| Performance/UX | Sistema "muito lento", usabilidade ruim, filtros insuficientes (ex.: querem buscar por analista) |
| Base geográfica defasada | Usa a base antiga ("S69"); exigem a base atual ("CA 2000") — "problema gravíssimo" |
| Falhas de comunicação | Mensagens/avisos entre sistemas que não chegam |
| Bugs observados | Processos de janeiro sem BAP que não foram indeferidos automaticamente; processo deferido recusado ao ser enviado para malha fina (qualquer processo deveria poder ir) |
| Autonomia | Já existem telas de parâmetros, mas mudanças ainda dependem do desenvolvedor — reforça a HU-014 (parametrização máxima) |

### 7.4 Decisões e definições novas

- **Regra-alvo do fluxo de decisão (definida pelo Filipe em 2026-06-11)**: liberação automática (expressa) para **baixo e médio risco**; análise humana focada em **alto risco**. Quando o sistema der o resultado automático (baixo/médio), a **SEFAZ é informada via API** como parte do próprio fluxo expresso. Implicações:
  - Refina HU-049/HU-050 (que tratavam médio risco como "depende do CNAE"): a diretriz é decidir automaticamente também o médio risco, usando as condicionantes parametrizadas.
  - Mapeamento com a taxonomia do Decreto 32.636/2020: Baixo Risco A e Baixo Risco B → expresso; Alto Risco → análise humana. Confirmar com a SEDUR se o "médio risco" da operação corresponde ao Baixo Risco B do decreto.
  - O expresso pressupõe parametrização suficiente: os gatilhos observados no SAPS (enquadramento ausente, zona ZEIS especial etc.) continuarão derrubando casos pontuais de baixo/médio para análise (o "semi-expresso") — a meta é que isso seja exceção registrada com motivo, não regra.
  - A confirmar: o deferimento de alto risco pela analista também notifica a SEFAZ via API ao finalizar (presumido que sim — hoje todo "produto" é enviado via API à SEFAZ, conforme reunião de 2026-06-11).
- **Escopo**: SILE cobre somente **viabilidade**. Construção fica no sistema novo do Anderson. Renovação entra direto pelo Simplifica (sem Regin).
- **Documento de viabilidade**: não é entregue ao requerente; os dados vão **via API para a SEFAZ** e o parecer via **Regin**. O **PDF/TVL permanece no backoffice** — relatório emitível pelo analista (HU-132), útil para arquivo e malha fina. Assinatura hoje é imagem do diretor (não ICP-Brasil). Impacta HU-076, HU-086, HU-132.
- **Malha fina**: é provocação humana (botão dentro do processo), distinta da caixa de entrada (chegada automática). Qualquer processo, mesmo finalizado, pode ser enviado.
- **Atribuição**: processo cai na caixa do setor e é atribuído a um analista, sem sair da caixa do setor (cobre férias/ausências).

### 7.5 Acordos operacionais

1. Lisa envia planilha com ~20 itens de melhorias.
2. Filipe inicia o desenvolvimento e disponibiliza ambiente de dev com acesso para a SEDUR acompanhar versão a versão.
3. Conversar com Anderson: melhorias de consulta, formato do documento/assinatura e fronteira com o sistema de construção.

### 7.6 Novas perguntas para a SEDUR

1. Qual regra separa **Expresso** de **Semi-Expresso**? (O semi-expresso passou por ficha de análise.)
2. Lista completa de **Gatilhos CNAE** (vimos: enquadramento pelo analista, zona ZEIS especial, informações do processo).
3. Semântica de **"Regra"** e **"Crítica"** no cadastro de condicionantes.
4. De onde vem a exigência de vagas ("Exigido LOUOS/Análise CNLU") — tabela da LOUOS? Pareceres da CNLU?
5. O que contemplam os **"Parâmetros para indeferimento automático"**?
6. Papel do cadastro **"Edifício Comercial"** no fluxo (relação com o complemento pré-preenchido).
7. Tabela TLL (códigos 1.01, 2.02 etc.) e origem dos valores (ex.: R$ 1.111,78).
8. Podem exportar as parametrizações atuais (CNAEs, enquadramentos, condicionantes, feriados) para servir de seed real do SILE?
9. O que é exatamente a base "CA 2000" e quem fornece acesso (SIGIS)?
10. A aba **Vistoria** do processo e a seção "Vagas vistoria" da ficha: vistoria de viabilidade entra no escopo do SILE (agendamento, registro, resultado) ou permanece em sistema/fluxo externo? (Reunião citou volume alto de vistorias como problema.)
11. Lista oficial de **tipos de serviço** (grupo de serviço/serviço): 1º estabelecimento, alteração de endereço/atividade, "Revisão TVL — Inclusão de Atividade", renovação, TVL MEI, AOP — quais o SILE deve cobrir e quais permanecem fora?
12. Existe **recurso administrativo** formal contra indeferimento de viabilidade (prazo, instância, rito)? Se sim, como é tratado hoje — reabertura no SAPS, processo físico, novo pedido? (Nenhuma HU cobre; não inventar fluxo jurídico sem confirmar.)
13. Interesse em **painel público de transparência** (estatísticas de volume e tempo médio sem login)? Tecnicamente simples; decisão é política — só implementar com aval da SEDUR.
14. Casos reais de **fraude/abuso** já observados (declarações falsas para obter expresso, cadeias de escritório virtual)? Quais padrões a equipe conhece — insumo para calibrar a HU-149.
15. Procedimento de **ciência presencial** no atendimento de balcão (HU-150): assinatura em tela, impresso ou gov.br?

### 7.7 Impacto nas HUs

> **Atualizado em 2026-06-12** — alterações aplicadas em `docs/SILE_HUs_Completas_MD/`.

| Descoberta | HU/EP afetado | Status |
|---|---|---|
| Médio risco → expresso automático | HU-049 | Alterada |
| Resultado expresso: Regin + SEFAZ; PDF só backoffice | HU-076, HU-077, HU-132 (nova) | Alterada + nova |
| SEFAZ no deferimento humano | HU-110, HU-086 | Alterada |
| DAM viabilidade Regin = SEFAZ (só visualizar) | HU-071, HU-072 | Escopo revisado |
| Integração Regin/BAP/formulário embed | HU-103, HU-133 (nova), HU-134 (nova) | Alterada + novas |
| Parecer Regin; todas CNAEs deferidas | HU-104 | Alterada |
| Formulário imóvel/polígono/fachada | HU-062, HU-107 | Alterada |
| Enquadramento TVL LOUOS × faixa × TLL | HU-015, HU-038 | Alterada |
| Condicionantes pergunta/regra/crítica | HU-019 | Alterada |
| Ficha análise + vagas + revisões | HU-135 (nova) | Nova |
| Malha fina | HU-136 (nova) | Nova |
| Convites via portal | HU-083, HU-091 | Alterada |
| Feriados + prazos + indeferimento auto | HU-014, HU-137 (nova) | Alterada + nova |
| Gatilhos CNAE → semi-expresso | HU-049, HU-135 | Alterada |
| Base SIGIS CA 2000 | HU-107 | Alterada |
| Relatórios tempo TVL por etapa / escritório virtual / regras de prazo | HU-129 | Alterada |
| Tipos de serviço (1º estab., alteração, revisão TVL, renovação direta) | HU-061 | Alterada |
| Caixa do setor + atribuição sem sair da caixa | HU-080, HU-138 (nova) | Alterada + nova |
| Filtros de consulta do SAPS + busca por analista + categorias | HU-082 | Alterada |
| Vagas estacionamento LOUOS/CNLU no motor | HU-042 | Alterada |
| Elegibilidade expresso: baixo+médio, gatilhos mensuráveis | HU-073 | Alterada |
| Risco municipal × sanitário; flags CNAE (MEI, escr. virtual, fator) | HU-047 | Alterada |
| Convite respondido no portal | HU-091 | Alterada |
| Edifício comercial / complemento pré-preenchido | HU-139 (nova — proposta) | Nova |
| Vistoria (aba do processo; vagas vistoria) | Pergunta 10 (seção 7.6) | A confirmar |
| Contrato Regin↔SAPS | EP13 | Bloqueio externo |

### 7.8 Integração Regin/REDESIM — fontes públicas (pesquisa 2026-06-11)

A integração que a Lisa demonstrou (formulário da SEDUR dentro do fluxo da junta) está documentada publicamente em nível funcional e normativo; a especificação técnica do webservice não é pública.

**Arquitetura e base normativa:**
- **Resolução CGSIM nº 61/2020** ([DOU](https://www.in.gov.br/en/web/dou/-/resolucao-cgsim-n-61-de-12-agosto-de-2020-271970565), [PDF gov.br](https://www.gov.br/empresas-e-negocios/pt-br/drei/cgsim/arquivos/Resoluo61de2020.pdf)) — define os modelos de integração da REDESIM. Papéis: **Integrador Nacional** (Receita Federal) ↔ **Integrador Estadual** (responsabilidade da Junta Comercial) ↔ municípios. Art. 6º: o Integrador Estadual coleta os dados da pesquisa prévia, "disponibiliza os dados das solicitações para os municípios e recebe as respectivas respostas relativas à viabilidade de localização". Art. 7º: cabe ao município **definir os dados a serem coletados** e **dar resposta no prazo definido, incluindo orientações, requisitos condicionantes e os respectivos motivos, caso negativa** — exatamente o contrato funcional do SILE com o Regin.
- O **Regin** é o Integrador Estadual da Bahia, operado pela JUCEB — produto da **Prosolution** ([pscs.com.br/regin_instituicao](https://www.pscs.com.br/regin_instituicao)). O "Módulo Instituição" entrega às entidades conveniadas os dados de viabilidade e alterações; a página é explícita: "os detalhes técnicos para a integração do REGIN da Junta Comercial com cada Instituição serão obtidos no processo de implantação" — ou seja, especificação não pública.
- A SEDUR integrou-se à REDESIM em **13/07/2021** ([notícia da Prefeitura](https://comunicacao.salvador.ba.gov.br/abertura-de-empresas-em-salvador-ficara-mais-agil-a-partir-desta-terca-13/)): o Regin envia os dados simultaneamente à JUCEB (nome/objeto/CNAE) e à SEDUR (viabilidade de localização).

**Observação ao vivo (2026-06-11, navegador do Filipe)**: o formulário do Regin roda em `regin.juceb.ba.gov.br/regin.externo/ViabilidadePedidoAlteracaoV4.aspx?tipoViabilidade=101&idMunicipio=38490...` com etapas **Integrantes e Nome Empresarial → Endereço → Atividade → Definições Grau de Risco → Informações Complementares** (esta última com perguntas da prefeitura: classificação ME/EPP/NORMAL, contato do solicitante, processo na JUCEB) e termos finais (Balcão Único; Dispensa de Viabilidade Locacional — orientação é "Não Aceito"; Termo de Responsabilidade). O **formulário específico da SEDUR é hospedado pelo próprio Simplifica** em `simplifica.salvador.ba.gov.br/integracao/sedur/TVL/ps001_Regin.aspx` — ou seja, o município hospeda a página e o Regin direciona para ela (o "token" citado na reunião). Implicação para o SILE: ele deverá servir o equivalente dessa página/etapa da integração. Capturas em `docs/legado-saps/17-*.jpg` e `18-*.jpg`.

**Fluxo documentado (passo a passo oficial SEDUR/CRC-BA, salvo em `docs/dados-oficiais/Passo-a-passo-REDESIM-SEDUR-CRCBA-13072021.pdf`):**
- Viabilidade: site da junta → seleciona município/instituição → viabilidade de 1º estabelecimento ou alteração → busca por inscrição imobiliária + "buscar complemento" (ex.: Sala 101) → sócios → 3 opções de nome → objeto social → CNAEs → **"Preencher Formulário"** abre o formulário específico da prefeitura (polígono com "Validar polígono", perguntas por CNAE, foto da fachada, declarações obrigatórias) → exibe o nº do processo SEDUR → volta à tela da junta → termo de responsabilidade → **protocolo** (o "BAP" citado na reunião).
- Convites/pendências: acompanhamento no site da junta; o convite direciona o requerente ao **Simplifica** para responder (bate com a demonstração).
- Alvará (pós-registro): JUCEB defere o ato e **envia XML à prefeitura**; a prefeitura recepciona, insere a inscrição municipal no Regin e emite o alvará.
- Regras confirmadas no documento: até 100 CNAEs (1 principal + 99); deferimento exige todas as atividades deferidas; escritório virtual exige CNPJ da sede; TVL não é mais emitido como documento (dados enviados diretamente à junta e órgãos integrados); renovação de TVL, TVL MEI e AOP continuam no site da SEDUR.

**Documentação técnica (webservices):**
- Existe o "Manual de Integração REDESIM" nacional (protocolo entre integradores, e.g. versão 2.2.28 com serviços WS01/WS02/WS15/WS29), mas não localizei fonte oficial aberta — apenas cópias de terceiros. O portal de monitoramento ([redesim.gestao.receita.fazenda.gov.br](https://www.redesim.gestao.receita.fazenda.gov.br/monitoramento-web/private/index.jsf)) exige certificado digital de órgão partícipe.
- **Caminho prático para o SILE**: a integração Regin↔SAPS já opera em produção — a especificação existe dentro da SEDUR/JUCEB. Pedir: documentação do webservice/endpoint que o SAPS expõe ou consome, exemplos de payload (solicitação de viabilidade recebida e resposta enviada), mecanismo de autenticação e ambiente de homologação do Regin. Sem isso, EP13 (integração com o integrador) fica bloqueado — registrar como dependência externa, nunca simular.

### 7.9 Melhorias além do legado — aceitas em 2026-06-12

Propostas do time de desenvolvimento aceitas pelo Filipe para o SILE superar o SAPS (não vieram de demanda direta da SEDUR; validar nas demos):

**Novas HUs (140–147):**

| HU | Melhoria | EP | Fase |
|---|---|---|---|
| HU-140 | Pré-análise pelo motor — ficha chega pré-preenchida; analista revisa e divergências são registradas | EP10 | 10 |
| HU-141 | Simulação de viabilidade embutida no formulário antes do protocolo (previne indeferimento; não bloqueia) | EP08 | 8 |
| HU-142 | Precedentes na ficha: histórico decisório do imóvel e do CNAE na zona | EP10 | 10 |
| HU-143 | Sandbox de parametrização: simular impacto contra processos reais antes de publicar regra | EP02 | 5–6 |
| HU-144 | Fila de trabalho do analista com semáforo de SLA por etapa | EP10 | 10 |
| HU-145 | Relatório de quedas por gatilho — feedback loop para ampliar o expresso | EP15 | 15 |
| HU-146 | Painel de saúde das integrações com reprocessamento (responde a "falhas de comunicação") | EP13 | 13 |
| HU-147 | Escalonamento por SLA de etapa (generaliza o padrão do BAP/48h) | EP11 | 11 |

**RNs adicionadas em HUs existentes:** HU-061 (duplicidade/reincidência), HU-063 (área × polígono), HU-037 (polígono × lote oficial), HU-069 (status em linguagem simples + prazo estimado real + timeline cidadão), HU-080 (distribuição em lote), HU-082 (busca global Cmd+K, timeline visual, mini-mapa), HU-099 (decisão explicável), HU-105 (cruzamento Receita × informado), HU-132 (assinatura gov.br), HU-135 (pré-análise, precedentes, mini-mapa, diff de revisões, autosave), HU-136 (malha fina em lote).

**Transversal:** mobile-first e acessibilidade WCAG/eMAG como critério de pronto de todas as fases com UI (registrado no ROADMAP).

**Segunda rodada (2026-06-12, também aceita):**

| Item | Tratamento |
|---|---|
| Versionamento da base geográfica (camadas como dados versionados; decisão registra versão da camada) | HU-107 RN-009, HU-036 RN-004/005 |
| Golden cases do motor (suíte de regressão de domínio validada pela SEDUR) | ROADMAP Fases 5–6 (critério de sucesso) |
| Recepção durável do Regin (fila com ack, dead-letter, replay) | HU-103 RN-009 |
| Proteção do endpoint público do formulário (token, rate limit, anti-bot) | HU-103 RN-010 |
| Entrada manual de contingência (origem auditada; permite operar antes do contrato Regin) | **HU-148** (nova, EP08) |
| Detecção de padrões de abuso/fraude (alerta + malha fina; nunca punição automática) | **HU-149** (nova, EP12) |
| 4 olhos na publicação de parametrização sensível | HU-143 RN-005 reforçada |
| Verificação fachada × declaração via IA | HU-115 RN-004/005 |
| Biblioteca de textos-padrão de parecer | HU-085 RN-004/005, HU-135 RN-009 |
| Atendimento presencial assistido ("em nome de" no balcão) | **HU-150** (nova, EP08) |
| Observabilidade com SLO (p95 por rota, telemetria de uso) | ROADMAP critério de pronto (nota de engenharia) |
| Recurso administrativo, transparência pública, padrões de fraude reais, ciência presencial | Perguntas 12–15 (seção 7.6) |
| Exportação CSV/XLSX/PDF em toda datatable da gestão (conjunto filtrado, assíncrona acima de limiar, auditada) — pedido do Filipe em 2026-06-12 | HU-131 RN-004 a RN-009 (padrão transversal), HU-082 RN-011, ROADMAP critério de pronto nº 8 |
