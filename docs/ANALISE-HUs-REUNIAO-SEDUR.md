# Análise — HUs × Reunião SEDUR × Fontes Oficiais

Data: 2026-06-09
Fontes: transcrição da reunião Lisa (SEDUR) × Filipe (Sudoeste); Carta de Serviços do Portal Simplifica; hotsite do SLI; páginas oficiais da LOUOS no site da SEDUR.

## 1. Pontos da reunião confirmados pelas HUs

| Ponto da reunião | HUs que cobrem |
|---|---|
| Entrada pelo integrador federal (REDESIM), que direciona para cada órgão | HU-022, HU-103 |
| Dois destinos do processo: fluxo expresso ou análise humana | EP09 (HU-073 a HU-078), EP10 |
| Quadro 7 enquadra a atividade usando a área informada pelo requerente | HU-038, HU-063 |
| Quadros 10/11/11A verificam se a atividade pode na zona, na via, ou ambas | HU-039, HU-040, HU-041, HU-031, HU-032 |
| Baixo risco exige finalização expressa (obrigação legal) | HU-048, HU-073 |
| Alto risco sempre vai para análise humana; médio risco depende do CNAE | HU-050, HU-049 |
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
9. O SILE emite o TVL formal (documento com validade) ou apenas responde a viabilidade ao integrador? Existe validade/renovação do TVL?
10. Geração de DAM, conciliação de pagamento e indeferimento por não pagamento (Decreto 32.155/2020) ficam dentro do SILE ou permanecem no Simplifica/SEFAZ?

### Integrações
11. API da SEFAZ: a autenticação (JWT/SenhaWeb) e as consultas já são conhecidas via SIGVISA. Qual é o endpoint/payload específico para ENVIAR o deferimento da viabilidade? Podem providenciar credenciais SenhaWeb para o SILE e acesso à homologação?
12. Qual o protocolo de comunicação com o integrador federal/Junta (entrada da solicitação e devolução do parecer)? Webservice REDESIM? Podem compartilhar o contrato?
13. Qual a base GIS usada para zona/via/lote (camadas, formato, acesso)? É o GIS municipal citado no EP13?

### Legado e transição
14. O sistema .NET atual será desligado? Há dados a migrar (processos históricos, TVLs emitidos)? O proxy criado por vocês continua no caminho?
15. Como o SILE se posiciona em relação ao SLI e ao Simplifica — substitui, convive ou se integra?

### Acesso
16. Conseguem dar acesso ao ambiente de homologação (sistema atual + junta) para acompanharmos uma abertura de processo ponta a ponta, como sugerido na reunião?

## 6. Impacto no início do desenvolvimento

Nenhum item acima bloqueia o início. EP01 (identidade/acesso), EP02 base (CNAEs, usuários, perfis), EP03 (cadastro empresarial) e a infraestrutura de auditoria independem das respostas. O motor de regras (EP05/EP06) deve ser construído **parametrizável** (regras como dados, não como código), de modo que as planilhas e quadros oficiais alimentem o sistema quando forem entregues. As respostas são necessárias antes de **executar** EP05/EP06/EP09 com dados reais e EP13 (contratos das APIs).
