# API de consulta do legado Simplifica (viabilidade) — design

## Intenção

O Viabiliza precisa consultar os processos de viabilidade do sistema legado Simplifica sem
importá-los. O primeiro uso é a renovação de TVL provisória no portal: quando o cidadão abre a
renovação, o portal pergunta à API se a empresa, o imóvel ou o próprio cidadão têm provisória
vencida ou a vencer e recebe os dados da TVL para pré-preencher o pedido. A API é um serviço
independente e somente leitura. A SEDUR pode consumi-la em outros projetos.

## Contexto verificado

- O dump é de SQL Server (banco `GR_SalvadorSimplifica`, 222 tabelas + 3 de apoio). Está carregado
  no SQL Server 2022 Developer do Portainer (stack `legado-simplifica`, ambiente 3), com porta
  externa 8082. A carga usa o Loader portado em `legado-simplifica/loader`; resultado em
  `legado-simplifica/resultado_carga.csv` e linhas rejeitadas em `legado-simplifica/rejeitados/`.
- `TVL` tem 334.453 linhas. Tipos (`TIPO_TVL`): 1 Definitivo, 2 Provisório (24 meses), 3
  Preoperacional (3 meses). Há 55.852 provisórias, 50.366 deferidas, 35.840 já vencidas.
- Identificação do titular das provisórias deferidas: CNPJ na própria TVL em 33%; com o CNPJ do
  requerente/outorgante do processo, 36%. Em 96% dos casos o requerente do processo é um CPF
  (`PROCESSO.PRO_USU_ID` → `USUARIO.USU_CPF_CNPJ`). A inscrição imobiliária existe em quase todas.
  `PROCESSO_REGIN` (processos vindos da JUCEB) ainda estava carregando na medição.
- Semântica verificada no banco (provisórias deferidas = 50.366):
  - O vencimento é gravado em `TVL_DATA_VALIDADE` (nunca nulo); `TVL_DATA_EMISSAO_TVL` falta em 27%.
    Portanto o vencimento vem da coluna, nunca de emissão + 24 meses.
  - "Deferida" é `TVL_DEFERIDO = 1`. `TVL_STATUS_TVL_ID` é nulo em 97% (só 1.041 têm valor) e não serve
    de filtro; `TVL_ATIVO` separa 33.349 ativas de 15.976 inativas.
  - O número da TVL (`TVL_NUM_TVL_ID`) é o identificador do termo: 27.689 números aparecem em mais de
    uma linha, porque cada renovação cria uma nova linha de `TVL` com o mesmo número.
  - `RENOVACAO_TVL` (10.280 linhas) liga o número ao registro da renovação: `REN_TVL_TVL_ID` e
    `REN_TVL_PRO_ID` apontam para a TVL/processo da renovação, não para o original.
- Hoje o portal só renova TVL emitida pelo próprio Viabiliza (`RenovacaoController`,
  `RenovacaoElegibilidadeService`): exige deferida e provisória, bloqueia renovação já deferida há
  menos de `renovacao.intervalo_meses` (padrão 3) e renovação em análise. TVL legada hoje fica fora.

## Objetivos

1. API HTTP somente leitura, versionada (`/v1`), documentada em OpenAPI, sobre o banco legado.
2. Consulta de provisórias por CNPJ, CPF ou inscrição imobiliária, com situação
   (vencida, a vencer, vigente) e indicação de renovação já feita no legado.
3. Detalhe de uma TVL com tudo o que o assistente de renovação precisa: titular, endereço,
   inscrição, zona/via, atividades (CNAE), tipo, datas, status, condicionantes.
4. Consulta geral de processo legado (por número, inscrição ou documento) com situação, TVLs e
   histórico de status, para outros usos do Viabiliza e da SEDUR.
5. Integração no portal: as provisórias legadas elegíveis aparecem na lista de renovação e na
   consulta por CNPJ, marcadas como "legado", e o assistente é pré-preenchido pela API.

## Não objetivos

- Importar ou migrar dados legados para o banco do Viabiliza.
- Escrever no banco legado, ou renovar TVL no sistema antigo.
- Renovar TVL definitiva ou preoperacional.
- Tratar a renovação vinda da JUCEB (fica para outra etapa; foco é o portal).
- Busca livre ou listagem navegável por nome (só consulta por chave exata).

## Anti-objetivos (falha mesmo que "funcione")

- Mostrar TVL de outro titular ao cidadão.
- Derrubar a renovação das TVLs do Viabiliza quando a API estiver fora do ar.
- Expor CPF/CNPJ de terceiros a clientes sem escopo.

## Regras decididas pelo usuário

- Titular: a renovação é sempre de um CNPJ. O cidadão só renova a TVL legada cuja titular é uma
  empresa vinculada a ele no portal. Não há renovação por mera coincidência de inscrição
  imobiliária, nem por CPF.
- TVL legada sem CNPJ gravado (a maioria): o cidadão informa o CNPJ da empresa, que precisa estar
  vinculada a ele no portal. O sistema consulta o CNPJ (SEDUR, com BrasilAPI de reserva) e só libera
  se a razão social e o endereço ou a inscrição imobiliária baterem com os da TVL legada.
- O legado não é importado; o portal consulta a API na hora da viabilidade.
- Foco no portal de renovação.

## Decisões do projetista (minhas, a confirmar)

- **Titular com CNPJ gravado:** documento da TVL (`TVL_CNPJ_EMPRESA`), senão o do requerente
  (`PROCESSO.PRO_USU_ID`) ou do outorgante quando tiver 14 dígitos. O portal compara com os CNPJs
  das empresas vinculadas ao usuário efetivo (`CompanyUser` ativo). Cobre cerca de 36% das provisórias
  deferidas.
- **Titular sem CNPJ gravado (CPF ou vazio):** a API devolve a razão social, o logradouro, o número,
  o bairro, o CEP e a inscrição imobiliária da TVL; o portal compara com o cadastro do CNPJ informado
  (razão social normalizada sem acento, caixa, pontuação e sufixo societário; e inscrição imobiliária
  igual, ou logradouro e número iguais). Falhando, a TVL aparece como não elegível com o motivo.
  A razão social existe em 90% das provisórias deferidas.
- **"A vencer":** janela configurável no portal, padrão 90 dias antes do vencimento.
- **Vencida pode renovar:** sim, desde que provisória deferida. Parâmetro no portal para travar,
  se a SEDUR definir prazo máximo.
- **Elegibilidade legada:** provisória, deferida, sem renovação posterior no legado
  (`RENOVACAO_TVL`) nem no Viabiliza, sem renovação em análise, e respeitando o mesmo intervalo
  mínimo de `renovacao.intervalo_meses`.
- **Identificador e TVL vigente:** a API expõe a TVL pelo número (`TVL_NUM_TVL_ID`). Dentre as
  linhas do mesmo número, a vigente é a mais recente (maior `TVL_DATA_VALIDADE`, desempate pelo
  maior `TVL_ID`). "Renovação posterior no legado" é existir linha mais nova com o mesmo número ou
  registro em `RENOVACAO_TVL` com o número; renovação em cadeia resolve para a última.
- **Vínculo da renovação:** o novo processo no Viabiliza guarda o número da TVL legada e um
  retrato (JSON) dos dados usados, em vez de um `renews_viability_request_id` (que exige processo
  local). É uma coluna nova em `viability_requests`, com índice. O intervalo mínimo conta da data
  da última renovação, legada (`REN_TVL_DAT_SOL`) ou do Viabiliza (decisão).
- **Quem age:** vale o "usuário efetivo" do portal (`CurrentRepresentation`), como hoje; procurador
  atuando pelo outorgante usa as empresas do outorgante.
- **Chaves de consulta (exatas, sem busca por nome):** a API aceita CNPJ, inscrição imobiliária e
  número da TVL. O portal só pede ao cidadão o número da viabilidade, que no legado tem dois
  formatos que não se confundem: número da TVL (7 dígitos, `TVL_NUM_TVL_ID`) ou número do processo
  (`PRO_NUMERO`, 24 caracteres, como `5921000030-00062208/2026`). Na lista de renovação aparecem
  automaticamente as legadas com CNPJ gravado das empresas vinculadas ao usuário efetivo. As sem
  CNPJ entram por uma ação "Renovar viabilidade do sistema anterior": o cidadão escolhe a empresa
  vinculada e informa o número da viabilidade; a TVL aparece marcada como "legado" e a liberação
  exige a conferência acima, mesmo com o número certo (o número, sozinho, não prova titularidade).
- **Consulta por CNPJ:** já exige login e só devolve empresas vinculadas ao cidadão; a parte legada
  segue a mesma restrição e só devolve TVL cujo titular passa na regra.
- **Pré-preenchimento:** igual à renovação de hoje, endereço e atividades vêm da TVL e não são
  editáveis. Se alguma atividade (CNAE) legada não constar do catálogo atual, a TVL fica não
  elegível, com o motivo e a orientação de procurar a SEDUR.
- **Implantação:** API no mesmo Portainer do Viabiliza (ambiente 3), na rede Docker compartilhada
  com o `sile-app`; o portal a chama por URL interna. Clientes da SEDUR na v1: tokens e escopos
  emitidos por comando artisan; sem tela administrativa.
- **Stack da API:** Laravel (mesma stack do Viabiliza), driver `pdo_sqlsrv`, repositório e stack
  Portainer próprios (`legado-simplifica-api`), falando com o SQL Server pela rede interna do
  Docker. A porta externa 8082 serve ao desenvolvimento e deve ser fechada na entrega.
- **Autenticação:** token Bearer por cliente, com escopos (`provisorias:ler`, `processos:ler`),
  limite de taxa e registro de cada consulta (cliente, chave consultada, resultado).
- **Degradação:** se a API falhar ou estourar o tempo, o portal mostra um aviso nas provisórias
  legadas e mantém as do Viabiliza.

## Pontos em aberto para o construtor

- Mapear de qual tabela vêm as atividades da TVL legada (`FICHA_TVL`, `FICHA_MATRIZ_REQUERENTE`)
  e como `TVL_FLAG_SEDE` e inclusão/exclusão de CNAE entram no detalhe.
- Reavaliar a cobertura de titular depois de `PROCESSO_REGIN` carregar, e tratar os registros
  rejeitados da carga (`FICHA_TVL`, `FICHA_MATRIZ_REQUERENTE`, `log_atualizacao_matriz_requerente`).
- Verificar disponibilidade do `pdo_sqlsrv` para o PHP usado no Dockerfile do Viabiliza.
