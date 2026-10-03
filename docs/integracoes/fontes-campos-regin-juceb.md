# Fontes dos campos do recebimento REGIN/JUCEB

De onde sai cada dado que o Viabiliza pede à JUCEB. Verificado em 3 de outubro de 2026.

## 1. Formulário da Prefeitura (SEDUR) no REGIN

Fonte: tutorial **INTEGRAÇÃO SEDUR - REDESIM**, publicado pela SEDUR e pelo CRC-BA.

- https://servicos.sedur.salvador.ba.gov.br/storage/portal-servicos/fique-por-dentro/01JZWWS6WS8B9FMDREB9WXDBHY.pdf
- https://www.crcba.org.br/wp-content/uploads/2021/07/Passo-a-passo-REDESIM-13072021.pdf

O que o tutorial confirma, com as palavras dele:

- "Clique em Preencher Formulário para preencher os dados específicos da Prefeitura e da Viabilidade de Localização."
- "Caso o polígono do imóvel não apareça, marque o polígono no mapa e clique em Validar polígono." — o requerente desenha o polígono.
- "Responda as perguntas referente a cada CNAE."
- "Anexe a foto da fachada."
- "Assinale as declarações obrigatórias."
- "Este Termo só deve ser aceito em caso de extrema necessidade. A empresa nascerá com CNPJ e CGA." — o Termo de Dispensa de Viabilidade Locacional.
- "Através do REDESIM podem ser solicitada viabilidade de Localização com até 100 CNAES (1 principal e mais 99 secundárias)."
- "A viabilidade de localização só será deferida, caso todas as atividades solicitadas sejam deferidas."
- "Para Solicitação de atividades abrigadas em escritórios virtuais será necessário que seja informado o CNPJ do escritório sede."

## 2. A tela do formulário (campos da SEDUR)

Fonte: a própria página `https://simplifica.salvador.ba.gov.br/integracao/sedur/TVL/ps001_Regin.aspx`, lida pelo índice público de busca. O acesso direto hoje responde "Página Bloqueada" pela política de segurança da Prefeitura — o conteúdo abaixo vem do índice, não de uma leitura autenticada.

O índice registra na tela:

- "Sua atividade será abrigada em um escritório virtual?" (Sim/Não) e "Selecione o CGA da empresa onde sua empresa será sediada".
- "Possui área de embarque/desembarque?" (Sim/Não).
- "Imóvel em condomínio?" (Sim/Não), com a convenção ou anuência do condomínio em PDF.
- "É necessário que o imóvel possua acessos independentes (a atividade comercial não pode ter acesso por dentro da área residencial e vice-versa). Seu imóvel atende este requisito?" (Sim/Não).
- "Atividade está estabelecida em área pública?" (Sim/Não) e "Possui Concessão de Uso?" (Sim/Não), com os dados e o termo/contrato de concessão em PDF.
- Anexos condicionais: Declaração de Utilidade Pública, Autorização de Uso e/ou Termo de Cessão de Posse.

## 3. Telas de ajuda do REGIN (JUCEB)

Públicas, em `regin.juceb.ba.gov.br`.

- **Apresentação do Pedido de Viabilidade** — https://regin.juceb.ba.gov.br/regin.externo/help/viab/apresentacao.html — o pedido é o formulário eletrônico com nome, endereço e atividade, avaliado pela Prefeitura; gera protocolo.
- **Dados do Solicitante / Pessoa Jurídica** — https://regin.juceb.ba.gov.br/regin.externo/help/viab/solicitante_matriz.html e https://regin.juceb.ba.gov.br/regin.ba/help/viab/solicitante_imunicipal.html — contador (nome, CRC, endereço), CPF ou CNPJ, nome e e-mail do solicitante; inscrição imobiliária (0 em zona rural); tipo de empresa e de inscrição; **área construída em m²**; CEP com busca, bairro, tipo de logradouro, logradouro, número; **coordenada geográfica preenchida ao clicar em "Visualizar Mapa"**; complemento e ponto de referência.
- **Nome Empresarial / Objeto Social e CNAE** — https://regin.juceb.ba.gov.br/regin.ba/help/viab/nome_cnae_alter_nome.html — três nomes empresariais e objeto social.
- **Informações Complementares** — https://regin.juceb.ba.gov.br/regin.ba/help/viab/infcomp_matriz.html — "campos de preenchimento obrigatórios necessários para avaliar o Pedido de Viabilidade de acordo com cada entidade". É o ponto onde cada entidade define as próprias perguntas.
- **Alteração de endereço** — https://regin.juceb.ba.gov.br/regin.ba/help/viab/viabilidade_alter_end.html — NIRE e CNPJ obrigatórios; evento de alteração.

## 4. Atividade exercida no local (padrão REDESIM)

O campo por CNAE é do fluxo REDESIM, não só de Salvador:

- Apresentação da 1ª fase do REDESIM na JUCE-GO — https://crcgo.org.br/wp-content/uploads/2022/12/Apresentacao-redesim2.pdf — "Para todos os CNAEs selecionados, responda se a atividade será exercida no local."
- Empreenda Fácil (SP) e Valinhos (SP) descrevem o mesmo campo "Atividade exercida/estabelecida no local".

## 5. O envelope técnico (EnvioDto)

Fonte: a OpenAPI pública da integração, da ProSolution (desenvolvedora do REGIN), em `http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao/v3/api-docs` (verificado em 3/10/2026, HTTP 200).

O schema `EnvioDto` tem `cnpjDestino`, `cnpjEmpresa`, `cnpjOrigem`, `codFuncao` ("Codigo de funcao"), `dataGeracao`, `json`, `nire`, `protocolo`, `servico` e `xml`. Obrigatórios: `dataGeracao`, `json` e `protocolo`.

No `json` (schema `Ruc` → `rowset`):

- `RUC_ACTV_ECON.RAE_CALIF_ACTV` — "Classificação da atividade: 1 - principal, 2 - secundária".
- `RUC_ACTV_ECON.RAE_IND_EXERCIDA` — "1 - Exercida" (a atividade exercida no local).
- `RUC_GEN_PROTOCOLO.RGP_TGE_COD_TIP_TAB` — "5 - Inscrição no Cadastro Imobiliário (IPTU)".
- `RUC_GEN_PROTOCOLO.RGP_VALOR` — "Valor da informação adicional informada na viabilidade".

## 6. MEI desenquadrado e BAP anterior

Fonte: tutorial **REGULARIZAÇÃO DE VIABILIDADE LOCACIONAL**, da SEDUR — https://servicos.sedur.salvador.ba.gov.br/storage/portal-servicos/fique-por-dentro/01JZWX4YQAV8Z507WT8C2D99JB.pdf — "Se for MEI desenquadrado ou BAP cujo TVL deu indeferido, faça o evento de alteração e selecione alteração de endereço e/ou atividade."

## O que não tem fonte pública

- **"Será sede de escritório virtual?"** — a tela pergunta só "será abrigada?". Sede é o CNAE 8211-3/00 com os anexos do Decreto 35.062/2021; ou a SEDUR inclui a pergunta nas Informações Complementares, ou o Viabiliza deriva pelo CNAE.
- **Natureza jurídica e porte** — o REGIN coleta tipo de empresa e de inscrição; a natureza jurídica formal vem do ato na Junta, não de um campo do formulário de viabilidade.
