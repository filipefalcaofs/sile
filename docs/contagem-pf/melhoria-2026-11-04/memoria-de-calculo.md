# Memória de cálculo: Viabiliza e API do legado Simplifica, de 16/10/2026 a 04/11/2026

| Item | Valor |
|---|---|
| Método | IFPUG CPM 4.3.1, Parte 3 Cap. 4 (projeto de melhoria); deflatores do PF Local iguais aos da planilha entregue em 16/10/2026; Roteiro SISP 2.3 §4.2 como comparação |
| Baseline | recontagem de 16/10/2026, 1.719 PF, código no commit d2ebeb663 (docs/contagem-pf/recontagem-2026-10-16) |
| Código medido | Gestão: branch feat/alteracao-atividade-zona-linear (HEAD 8fa8c1e08) com as alterações sem commit; Portal e legado Simplifica: integra/portal-tres-servicos-dev (820600123), que contém integra/renovacao-legada-dev, feat/entrada-portal-servicos e feat/portal-cidadao-tres-servicos |
| API do legado Simplifica | Aplicação própria, repositório legado-simplifica/api (criado em 23/10/2026, versão 0.8.0 de 29/10/2026), contada como projeto de desenvolvimento em fronteira separada (seção 2) |
| Fonte e corte | Código publicado no SEMIT (main = produção), de 9cec2c3f0 (v0.84.1, 16/10/2026 17:10) a 845edb5c1 (04/11/2026 11:03). O que existia até o corte está nos 1.719 PF da recontagem, já cobrados, e não entra aqui |
| Premissas | P1 a P9 da recontagem de 16/10, sem mudança |
| Nível | Detalhada (DER e FTR listados por função em melhoria-funcoes.json e na coluna Observações) |

## 1. Resultado

| Situação | Funções | PF |
|---|---:|---:|
| Fronteira | Situação | Funções | PF |
|---|---|---:|---:|
| Viabiliza (melhoria) | Incluída | 86 | 487 |
| Viabiliza (melhoria) | Alterada | 41 | 258 |
| Viabiliza (melhoria) | Excluída | 35 | 185 |
| API do legado Simplifica (desenvolvimento) | Incluída | 17 | 94 |
| **Total não ajustado** | | **179** | **1024** |

- PF Local com os deflatores da planilha entregue em 16/10/2026 (contagem-viabiliza-recontagem-2026-10-16.xlsx, Contagem!W15:W18: inclusão 1,00; alteração 0,80; exclusão 0,30; teste 0,50): 581 + 0,80 × 258 + 0,30 × 185 = **842,90 PF** (Viabiliza 748,90 + API do legado 94). A primeira versão desta contagem saiu com exclusão 1,00, valor padrão do modelo XLSX sem base em contrato, e foi corrigida por decisão do usuário em 04/11/2026.
- Pelo SISP 2.3: Viabiliza 487 + 0,50 × 258 + 0,30 × 185 = 671,50 PF, mais 94 PF da API do legado = **765,50 PF** (FI 50%, funcionalidade desenvolvida pela própria contratada).
- Dos 487 PF incluídos no Viabiliza, 277 são os 43 indicadores da API do BI (critério adotado). Se fossem uma SE parametrizada (7 PF), o não ajustado total cairia para 754 PF.

### 1.1 Reconciliação com o SEMIT

A contagem foi medida primeiro nas branches locais e depois conferida, função por função, contra o SEMIT main entre o corte e 04/11 (209 commits, inclusive os que entraram por merge da homologa).

- 161 das 162 funções medidas nas branches estão publicadas; nenhuma já existia em 16/10 com o mesmo comportamento.
- Saiu "Dados - Ficha de vistoria" (ALI alterado, 15 PF): a coluna devolvido_em só existe no dev, não foi publicada.
- "TVL - emitir e abrir o termo em PDF" passou de alterada para EXCLUIDA (7 PF): o botão saiu da ficha (4e6349378) e o "Abrir PDF" das demais telas (cd76db83a); nenhuma tela publicada chega mais ao PDF (P8).
- Entraram 8 funções publicadas no SEMIT que não estavam nas branches medidas (33 PF):
  - APIs - consultar e alterar configuração do Portal de Serviços SEDUR (CE 3 + EE 4) e de Edificações complementares (CE 3 + EE 4), abas da tela de APIs (P7).
  - Dados - Edificações complementares (ALI 7) e importações agendadas das bases da Overture e da Microsoft (EE 4 cada), do polígono complementar publicado em 20/10 (d7c024319, 186b36e5e).
  - Processos - baixar documento do processo na gestão (SE 4), dos pedidos da SEDUR de 22 a 26/10 (e366cdd20).
- Não contados por já estarem no SEMIT em 16/10 (cobrados nos 1.719 PF): Cidadão em tabela própria e inativação de cidadão; Usuários - cadastrar usuário pela gestão, que já constava da baseline. A galeria do treino da IA foi revertida (c5785579f) e não está em produção.
- 18 mudanças publicadas reforçam funções já contadas como alteradas sem mudar a complexidade (cards da caixa de entrada, pareceres na tramitação, Host da aba do legado, busca do Simplifica pelo BAP, entre outras) e não somam PF.

## 2. API do legado Simplifica

Fronteira própria: a API é o único acesso ao SQL Server do Simplifica e atende o Viabiliza, o SAD e a consulta web do legado. Usuários: sistemas consumidores (token de cliente com escopos e validade de 90 dias), usuários da consulta web (sessão de 8 horas) e o operador da API. Toda chamada grava o registro de uso no mesmo request, por isso as consultas são SE e o registro conta como FTR (P4).

| Função | Tipo | TD | AR/TR | PF | FTR / RLR |
|---|---|---:|---:|---:|---|
| Dados - Credencial de acesso à API | ALI | 8 | 2 | 7 | Cliente (sistema consumidor), Sessão de usuário |
| Dados - Registro de uso da API | ALI | 11 | 1 | 7 | Consulta registrada |
| Dados externos - Conta de usuário da consulta do legado | AIE | 4 | 1 | 5 | Conta |
| API do legado - autenticar usuário | EE | 6 | 2 | 4 | Credencial de acesso à API, Conta de usuário da consulta do legado |
| Clientes da API - consultar | SE | 7 | 2 | 5 | Credencial de acesso à API, Registro de uso da API |
| Clientes da API - incluir | EE | 5 | 2 | 4 | Credencial de acesso à API, Registro de uso da API |
| Clientes da API - alterar escopos | EE | 3 | 2 | 3 | Credencial de acesso à API, Registro de uso da API |
| Clientes da API - revogar | EE | 2 | 2 | 3 | Credencial de acesso à API, Registro de uso da API |
| Sessões de usuário - revogar por usuário | EE | 2 | 2 | 3 | Credencial de acesso à API, Registro de uso da API |
| Registro de uso da API - consultar | SE | 16 | 1 | 4 | Registro de uso da API |
| Dados externos - Processo do legado Simplifica | AIE | 21 | 5 | 7 | Processo, Processo REGIN, Item do serviço, Histórico de status, Ficha do processo |
| Dados externos - TVL do legado Simplifica | AIE | 24 | 4 | 7 | TVL, Renovação da TVL, Atividade da TVL, Requerente/titular |
| TVLs do legado - consultar por CNPJ, inscrição ou número | SE | 22 | 2 | 7 | TVL do legado Simplifica, Registro de uso da API |
| TVL do legado - consultar detalhe por número | SE | 21 | 3 | 7 | TVL do legado Simplifica, Processo do legado Simplifica, Registro de uso da API |
| Processos do legado - consultar por número, documento, inscrição ou TVL | SE | 20 | 3 | 7 | Processo do legado Simplifica, TVL do legado Simplifica, Registro de uso da API |
| Processos do legado - pesquisar com filtros | SE | 25 | 3 | 7 | Processo do legado Simplifica, TVL do legado Simplifica, Registro de uso da API |
| Processo do legado - consultar detalhe | SE | 20 | 3 | 7 | Processo do legado Simplifica, TVL do legado Simplifica, Registro de uso da API |

Não contados na API: documentação `/docs` e `openapi.yaml` (estáticas), saúde `/up`, comandos `clientes:criar`, `clientes:escopos` e `clientes:revogar` (mesma lógica das rotas de operação, executados pela equipe), limites de requisição e tabelas de código STATUS_PROCESSO e CNAE (P2). Provisórias e deferidas (`/provisorias` e `/tvls`) contam como uma consulta: mesmos DER e arquivos, só o filtro muda (P6); separadas dariam +7 PF. A aplicação web da consulta do legado (legado-simplifica/web) não entrou nesta contagem.

## 3. Decisões da consolidação

- Funções tocadas por mais de uma frente contam uma vez: ALI Solicitação de viabilidade (alterado, 15), REGIN receber declaração (alterada, 6), corrigir CNAE inválido (incluída, 4), Painel inicial, Resultados do expresso e Ficha do expresso (alteradas, 7 cada).
- O legado Simplifica é um AIE só (66 DER, 5 RLR, 10 PF), lido pelo portal, pela gestão e pela comparação de atividades.
- Processos - reprocessar etapas do motor: EXCLUIDA. O botão saiu da ficha do expresso em ec36400de (17/10) e a rota ficou sem acesso pela tela (P8).
- Consulta prévia do portal: a confiabilidade entrou em 16-17/10, mas as três consultas, o histórico e o ALI ficaram atrás de features.portal_servicos_extras, desligada em produção. Conta só a exclusão; a alteração de 16-17/10 não soma, porque uma função não pode ser alterada e excluída no mesmo projeto.
- Funções do portal atrás da flag de extras (Empresas, Procurações e representação, Solicitação no portal, Consulta prévia, localizar renovação por CNPJ): EXCLUIDAS. Se a SEDUR religar a flag, saem da exclusão.
- Portal - entrar no sistema e Cadastro do cidadão - criar conta: EXCLUIDAS, porque features.login_portal_servicos está ligado em produção desde 27-28/10 e a entrada local fica bloqueada.
- Nomenclatura dos serviços (De x Para) é ALI, não dado de código (P2): os campos exibir e condição mudam o comportamento, como Feriado e Tipo de imóvel na baseline.
- Autenticação das APIs da SEFAZ (TVL) e do BI: uma EE só (mesmos DER, FTR e lógica, P6).

## 4. Pontos para validar com o fiscal

1. Indicadores do BI contados como 43 SE distintas (277 PF), por decisão do responsável pela aplicação em 04/11/2026. Se o fiscal exigir uma SE parametrizada (7 PF), o não ajustado cai para 754 PF.
2. Exclusões por flag (164 PF): valem enquanto features.portal_servicos_extras estiver desligada em produção.
3. Sincronização de feriados pela FeriadosAPI contada como EE (rotina agendada, como a importação da REDESIM na baseline). Por P5 sairiam 3 PF.
4. Correções sem DER novo deixadas de fora: distribuir e distribuir em lote (+12 se contadas), abrir convite (+4), consulta de setores (+7), saturação por bairro oficial (+7).
5. TD das consultas muito grandes (ficha de análise 62, informações do processo 46, PDF da vistoria 35) é estimado; o PF não muda, porque já estão em Alta.
6. Importações agendadas das edificações (8 PF): dependem do interruptor de atualização agendada, com padrão desligado; se estiver desligado em produção, sobra só o comando manual e as duas EE saem.
7. API do legado contada como aplicação própria. Se o fiscal a considerar parte do Viabiliza, os AIE do legado precisam ser revistos para não duplicar com o AIE "Processos e viabilidades do Simplifica".

## 5. Não contados

| Item | Motivo |
|---|---|
| Ficha de análise - sugestão de condicionantes da biblioteca enquanto digita (bc6cca32a, 91700c90e) | Filtra no navegador os textos-padrão já entregues pela consulta da ficha (sugestoes-condicionante.ts); sem novo request nem DER. Coberto pela consulta da ficha. |
| Ficha de análise - Resumo do processo (card de síntese, 1deeae1f3, sintese.ts) | Montado no front a partir dos dados da consulta da ficha; os DER entram em "Ficha de análise - consultar". A geração de resumo por IA já é função da baseline, sem mudança. |
| Capa de consulta do processo na vistoria (GET /gestao/vistorias/{id}/consulta) | Reutiliza ProcessoController::detalhe com os mesmos DER/FTR/lógica de "Processos - consultar informações do processo" (P6); muda só a permissão. |
| Vistoria - anexar foto ou documento com vários arquivos (047c78934) | Mesmo DER (arquivo) e mesma lógica por arquivo; multiplicidade não altera a função. |
| Vistoria - consultar ficha mostra o tipo do imóvel do cadastro como alternativa (297da247c) | Mesmo DER tipo de imóvel com outra origem; sem DER novo para o usuário. |
| Malha fina - consultar caixa (rótulo do serviço e situação de tramitação) | Só troca a fonte do rótulo do filtro de serviço e o texto da situação; sem DER novo. Limítrofe: se Nomenclatura for aceita como FTR em toda consulta, passaria a ALTERADA (SE 31/7, 7 PF). |
| Caixa do setor - distribuir processo / distribuir em lote / assumir (4817b0928, 5fdfc7d30, 5475bb98b, 0e52b6d1d, 28eff56f5) | Correções da regra Para conclusão e mensagens de bloqueio (commits fix), sem DER/FTR novo. Limítrofe: a distribuição de Para conclusão passou a gravar o destinatário da conclusão; se o responsável considerar mudança de requisito, contar distribuir (EE 5/6, 6 PF) e lote (EE 7/6, 6 PF) como ALTERADA. |
| Vistoria - abrir ficha de vistoria deixa de atribuir o processo a quem abre (092effe25) | Commit fix (vistoriador só abre processo atribuído a ele); sem DER/FTR novo. Limítrofe. |
| Pendências - abrir/cancelar convite (2824ffba1, b53acbdab, b3e9f5e5a, 8ac33285e) | Quem abre o convite assume o processo, eixo da análise acompanha o convite e recusa sem requerente; mesmos DER e FTR (Solicitação e Trilha já contados). Tratado como ajuste da máquina de estados. Limítrofe: abrir convite ALTERADA seria EE 4/6, 4 PF. |
| Perfis - permissão encaminhar-para-conclusao e rótulos no catálogo (2b85b13e7, migration 2026_10_26_100000) | Registro de permissão é dado (linha) do ALI Perfil, não atributo; a checagem entra como FTR Perfil no encaminhar à chefia. |
| Parâmetros novos de gestão (CNAEs vedados em Via Local, tolerância da zona linear, portal, legado, consulta prévia) e padronização das descrições | Linhas do ALI Parâmetro (dado), sem mudança estrutural; o efeito é da frente motor/portal. |
| Setores - lista de vínculo com toda a equipe interna, login, perfis e setores (4cdac523d) | Lista de apoio do formulário de vínculo (a baseline já a tratou como apoio, P7). Limítrofe: se considerada DER da consulta de setores, "Setores - consultar" passaria a SE 10/4 (7 PF). |
| Tipos de imóvel novos da vistoria (2026_10_27_150529_carrega_tipos_de_imovel_da_vistoria) e carga da nomenclatura pelo PDF (migration) | Carga de dados por migration/seed, sem ação do usuário. |
| Cadastro automático de nomenclatura para serviço novo recebido da JUCEB (787713e41, bb0cac8dc) | Efeito do recebimento REGIN (P5). Para a frente REGIN: "REGIN - receber declaração" passa a gravar Nomenclatura do serviço (FTR novo). |
| AIE Logradouros (eixo viário) do GeoServer da SEDUR (VM_LOGRADOURO, GeoServerEixoViarioClient/GeoServerEntornoClient) | Camada nova lida pelo mapa e pela validação do ponto da inscrição (53052161d, frente REGIN/localização). Contar o AIE uma vez no consolidado (provável AIE 4-5 DER, 1 RLR, 5 PF); aqui só como FTR do mapa. |
| Relatórios gerenciais sem a meta da taxa expressa (0e629ee02) | Fora da lista desta frente; avisar o responsável pelos relatórios (DER removido em quedas/indicadores). |
| Comandos artisan rodados pela equipe: processos:corrigir-ponto-inscricao, CorrigirCodLogInscricao, AuditarCodLogInscricao, IndeferirPerguntaSemResposta, InscricaoImobiliariaTestar, ReginAuditarPerguntas, ReginReenviarPareceres (--forcar/--area-errada), PerfisAtribuirPermissao, UsuariosLiberarAcessoGestao, PreencherTipoImovelCadastro, ReginCorrigirSolicitantes, ReginCorrigirCnpjEmpresa, DetectarCondicoesServicos, CompararAtividadesAnteriores, TvlRenumerar, scripts (4feea391a, 27c45aa02) | Correção/auditoria pontual de dados ou ferramenta técnica rodada pela equipe; não é função do usuário (P8). |
| Fluxo expresso decide só no fim (f7f3c2012) e indeferimentos automáticos (inscrição inativa, estabilidade SEFAZ) | Efeito automático do protocolo/recebimento (P5); frente REGIN/motor. |
| Layout da ficha (barra fixa, coluna lateral, menu Mais ações, parecer ampliado, botão laranja), calendário DayPicker, busca nos dropdowns, textos sem travessão, modais com rolagem | Interface e rótulos, sem mudança de requisito. |
| Correções sem mudança de requisito: justificativa à JUCEB (d3e2129d2, 6ca80cc32, 9b8e6ed3e), CODLOG pelo endereço de Localização (275cf5a1a), mapa da ficha enquadra o polígono (c69ba4929), contadores Aguardando/Em análise (aea0b5ffd), 422 com mensagem (8ac33285e), etc. | Correção de defeito; não conta em projeto de melhoria. |
| GET /api_integracao/bi/indicadores (catálogo) | Lista metadados estáticos do código (slug, grupo, título, descrição, parâmetros, URL); não lê ALI/AIE. Metadado de interface/documentação (P7). |
| Swagger/openapi das APIs (/api_integracao/docs, tvl/docs, bi/docs/openapi.yaml) e cabeçalho do Swagger UI (873715a33) | Documentação estática (P7). |
| Proteção do POST /api_integracao/recebe (6082fe69f, 28/10): segredo compartilhado, lista de IPs, limite de corpo | Requisito não funcional de segurança; não cria processo elementar nem muda DER de negócio. Mesmo se o segredo fosse contado como DER, a EE já é Alta (45 DER). |
| Parâmetro liga/desliga da consulta prévia (9a9386017, dff9a954c) | Novo registro no ALI Parâmetro mantido pela função existente Parâmetros - alterar; o middleware só libera ou bloqueia o acesso (a baseline não contou o interruptor features.consulta_viabilidade como FTR). |
| Parâmetros novos: tolerância da zona linear (06f7d412a), CNAEs vedados em via local (18c2e49be, 952486ad4), legado Simplifica (90c0886cd), portal (cria_parametro_portal_servicos), padronização de descrições | Dados no ALI Parâmetro (chave/valor, sem mudança de estrutura) mantidos pela função existente; o efeito entra como FTR Parâmetro no recebimento REGIN alterado. |
| Teste de conexão do legado Simplifica (POST /gestao/config-legado-simplifica/testar) | Teste de conexão não conta (P7). |
| Simulador da consulta de viabilidade na SEFAZ (criado em a03b8c3e4 23/10, removido em a55d241af 24/10) | Stub técnico criado e removido depois da baseline; nunca foi função contada. Nada a excluir. |
| Envio da viabilidade à SEFAZ (removido em 4e63688d7, 29/10) | Fora da fronteira na baseline (P1); a remoção não gera EXCLUIDA. A tela de resultado só perdeu um canal da lista de transmissão (mesmos tipos de DER). |
| Fonte SEFAZ da cadeia de viabilidade anterior (SefazViabilidadeFonte) | Ligada a UnavailableSefazViabilidadeConsultaGateway: sem implementação real, não há AIE nem FTR. |
| Meta da taxa expressa (0e629ee02) | Não é EXCLUIDA: era DER do Painel e do relatório de quedas (contados como ALTERADA) e um registro de Parâmetro removido por migration. |
| Comandos artisan: CompararAtividadesAnterioresCommand, ReginCorrigirCnpjEmpresaCommand, IndeferirPerguntaSemRespostaCommand (f7f3c2012), auditoria de perguntas do REGIN (a05a61914, 177fc6583), corrigir ponto da inscrição (cf111b10d), corrigir CODLOG (432044065), simulação do enquadramento (09ffe579b), reenvio de parecer --forcar/--area-errada (3ee49e0ac), TvlRenumerar, scripts de CODIGO_AREA (27c45aa02) | Correção/auditoria pontual por operador técnico (P8 e regra da frente). |
| Agenda da importação REDESIM dia 1 a 10 e sincronização da FeriadosAPI (routes/console.php) | Agendamento é efeito automático (P5). A regra de reimportação por hash entrou como ALTERADA na importação; a FeriadosAPI é da frente de Feriados. |
| Exportação: download refaz o arquivo ausente, coluna source em export_files, PDF em outra guia (966f05ae8, 03/11) | Robustez técnica do armazenamento no contêiner e layout; export_files é tabela técnica, sem mudança de DER/FTR de negócio. |
| Gargalos da Sala em lotes (794bb149a), tradução TVL→Viabilidade em comentários/rótulos (árvore de trabalho), nomeExibido da empresa | Desempenho/texto, sem mudança funcional. |
| Saturação agrupa pelo bairro oficial e tira aviso de zona pendente (89c896575) | Commit de correção; tratado como bug fix. Se o responsável considerar mudança de requisito: ALTERADA de Relatórios - consultar saturação locacional (SE Alta 7). |
| Ressalva do tempo de análise pelo calendário municipal (03c03b4cb) | Depende da sincronização de feriados (frente de Feriados); sem DER novo no relatório. |
| Classificação viária dos logradouros (AIE) lida também para o eixo como reserva | Conservador: não marcada como alterada; o eixo é contado no AIE novo de logradouros. |
| Correção do CODIGO_AREA 70 e justificativa do expresso no parecer à JUCEB (27b5e219f, 6ca80cc32, d3e2129d2) | Envio do parecer é efeito automático (P5). |
| Tela de resultado do expresso sem o canal SEFAZ (4e63688d7) | Mesmos tipos de DER (canal, situação, data); sem mudança de complexidade nem de requisito contável. |
| PARA AS OUTRAS FRENTES: Solicitação - protocolar e Solicitação - simular viabilidade (portal) e Registro em contingência executam o mesmo FluxoExpressoService alterado; a Ficha de análise - consultar passou a exibir a comparação de CNAEs (AnalysisRecordController comparacaoCnaes, novo FTR AIE legado via base gravada); o ALI Solicitação de viabilidade também foi alterado pela Gestão | Não contados aqui para evitar dupla contagem; o consolidador deve decidir se o portal e a contingência entram como ALTERADA por lógica compartilhada (mesmo critério usado no reprocessar). |
| feat/entrada-portal-servicos (worktree .claude/worktrees/entrada-portal-servicos, HEAD 32077497f) | Absorvida: os 22 commits (be8ccc15e..32077497f) estao todos na historia de integra/portal-tres-servicos-dev; funcoes contadas uma vez a partir desta. |
| integra/renovacao-legada-dev (HEAD 4b80e1bc6) e feat/portal-cidadao-tres-servicos (HEAD 2e46545f5) | Ancestrais de integra/portal-tres-servicos-dev (mesmos SHAs); sem funcao exclusiva. A "decisao da renovacao na gestao" so tem commit de teste (33a5cbc97); a decisao usa a rota decidir ja existente, sem mudanca funcional. |
| integra/legado-simplifica-unificado: 47ac6c55c, 24f01f1cf, b351ddbd6, a60be19b7 | 47ac6c55c e patch-equivalente a f55e34f28 do checkout principal; 24f01f1cf corresponde a 90c0886cd (principal); b351ddbd6 e a60be19b7 so ajustam a aba Simplifica da tela de APIs, que existe no principal: frente principal. 2bac22eb9 (filtro e tempo da consulta do Simplifica) foi atribuido a Processos do Simplifica - consultar lista, sem funcao nova. |
| Aba Simplifica (legado) da tela de APIs (portal-dev 475b01bd8, cria_parametros_api_legado, POST config-apis/legado/testar) | O checkout principal tem a configuracao do Simplifica na tela de APIs (90c0886cd): frente principal. Teste de conexao nao conta (P7). |
| Aba Portal do cidadao em Parametros e interruptor por servico (e65a1c3cc, migration cria_aba_portal_dos_parametros, middleware portal.servico) | Parametros novos sao linhas do mesmo ALI Parametro (sem DER/RLR novo, como a baseline registrou para features.portal_servicos); a aba e organizacao de interface; Parametros - consultar/alterar sem mudanca de DER/FTR. O desvio da rota desligada e controle tecnico. |
| Parametros renovacao.legado.*, renovacao.antecedencia_meses, integrations.portal_servicos.*, integrations.legado_api.* | Linhas do ALI Parametro; o efeito da antecedencia entrou nas renovacoes alteradas. |
| LIMITROFE NAO EXCLUIDO: Portal - entrar no sistema (EE 5/2, 4 PF) e Cadastro do cidadao - criar conta (EE 8/6, 6 PF) | BloqueiaEntradaLocalDoPortal (f433fc42f, 23/10) desvia login, cadastro e recuperacao de senha para o Portal de Servicos quando o PARAMETRO features.login_portal_servicos esta ligado e as URLs do BFF preenchidas. Nao verifiquei o valor em producao (sem acesso ao banco) e e reversivel pelo gestor; nao e a flag portal_servicos_extras. Se confirmado ligado em producao, as duas viram EXCLUIDA (+10 PF). Senha - alterar senha continua (contada uma vez com a gestao). |
| Middleware ConfereSessaoPortalServicos (f12b19ae8) e saida para a Carta de Servicos (76cb8bd8d) | Conferencia periodica da sessao e logout: controle tecnico/P7, sem processo elementar. |
| Comando artisan regin:vincular-cpfs (ReginVincularCpfsCommand) | Comando manual (P8). |
| Politica view com solicitante declarado (ViabilityRequestPolicy) em Solicitacoes - consultar andamento, Pendencias - consultar no portal, Protocolo - emitir comprovante | So regra de autorizacao, sem DER/FTR/saida nova: conservador, nao contadas como ALTERADA. |
| Anexo do convite exibido em Pendencias - consultar no portal e na ficha/processo da gestao | LIMITROFE: o link do anexo e um DER a mais na saida dessas consultas; nao contado como alteracao (conservador). Se contar: Pendencias - consultar no portal CE 8/2 (Media 4, ALTERADA). |
| Minhas solicitacoes de volta ao menu (5eecbd490), logo do Portal de Servicos, alinhamento de botoes, visual do assistente e do convite, ressalva do prazo no link publico, cartao/alerta da renovacao, selo de origem | Layout/menu/correcao sem mudanca de requisito. |
| Destinos nova/autonomo da entrada (orientacao para a JUCEB) e Detalhar | Parte do processo Portal - entrar pelo Portal de Servicos (redirecionamento), sem processo proprio. |
| Consulta legada por numero dentro da empresa (POST /portal/renovacao/legado/consultar) | Rota atras de portal.extras: nunca disponivel ao usuario, nao contada. |
| Docs/specs/HUs (b1f251290, 2e84c281b, aff3c0475, 3621172c5, 8e15a296c, 23779b88b, 4bab7f02f, e1181024a, c8a2b40cb, 32077497f) e builds de assets | Sem funcao. |
