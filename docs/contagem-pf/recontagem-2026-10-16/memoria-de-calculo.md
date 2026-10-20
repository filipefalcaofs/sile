# Memória de cálculo — Recontagem de Pontos de Função do Viabiliza

| Item | Valor |
|---|---|
| Aplicação | Viabiliza — Sistema de Licenciamento Eletrônico da SEDUR |
| Data da recontagem | 16/10/2026 |
| Código-fonte de referência | repositório sile, branch feat/portal-servicos-navegacao, árvore de trabalho lida entre 16:40 e 17:10 de 16/10/2026, HEAD final d2ebeb66; a lista de 303 rotas foi conferida de novo no HEAD final, sem diferença, e os commits feitos durante a recontagem não alteram TD/AR contados |
| Método | IFPUG CPM 4.3.1 (PT-BR); Roteiro SISP 2.3 quando citado |
| Tipo de contagem | Projeto de desenvolvimento, aplicação completa (o resultado coincide com a baseline da aplicação, pois não há funções de conversão) |
| Contagem anterior | contagem-viabiliza-completa (2).xlsx, versão 3.3 de 01/10/2026 |
| Modelo da planilha | contagem-2391.xlsx (abas Contagem, Funções e Sumário preservadas) |
| Nível | Detalhada (TD e AR/TR contados função a função, com lista de DER e de arquivos na coluna Observações) |

## 1. Resumo comparativo

| Indicador | Contagem anterior (01/10) | Recontagem (16/10) | Diferença |
|---|---:|---:|---:|
| PF não ajustados | 1618 | 1719 | +101 (+6.2%) |
| Funções contáveis | 328 | 335 | +7 |
| ALI (qtd / PF) | 54 / 408 | 46 / 361 | -8 / -47 |
| AIE (qtd / PF) | 4 / 20 | 10 / 50 | +6 / +30 |
| EE (qtd / PF) | 152 / 615 | 155 / 646 | +3 / +31 |
| SE (qtd / PF) | 59 / 358 | 87 / 530 | +28 / +172 |
| CE (qtd / PF) | 59 / 217 | 37 / 132 | -22 / -85 |

Situação das funções:

| Situação | Quantidade | Efeito em PF |
|---|---:|---:|
| Mantidas (mesmo nome, tipo, TD e AR) | 127 | 0 |
| Alteradas com mudança de tipo, complexidade ou PF | 71 | +71 (saldo) |
| Alteradas só em TD, AR ou nome, sem mudança de PF | 93 | 0 |
| Novas | 44 | +201 |
| Removidas (linhas da contagem anterior) | 37 | −171 |
| **Total** | | **1618 → 1719** |

Conciliação: 1618 − 171 (removidas) + 201 (novas) +71 (saldo das alteradas) = 1719 PF.

Análise de sensibilidade (premissa P4): sem contar como arquivo referenciado os registros automáticos em arquivos de log, a recontagem daria **1584 PF**.

Distribuição por complexidade (Sumário da planilha):

| Tipo | Baixa | Média | Alta |
|---|---:|---:|---:|
| ALI | 38 | 5 | 3 |
| AIE | 10 | 0 | 0 |
| EE | 44 | 76 | 35 |
| SE | 5 | 32 | 50 |
| CE | 22 | 12 | 3 |

Validação da planilha (recalculada no LibreOffice): Sumário!G49 = Sumário!E57 = Contagem!Y8 = 1719 PF; PF de cada linha (coluna O) igual ao calculado pelas Tabelas do CPM; nenhuma linha de grupo pontua; o nome CF e as fórmulas E57:E60 do Sumário foram ampliados de 12:101 (modelo) para 12:1000, porque o modelo deixaria de fora as funções abaixo da linha 101.

## 2. Premissas adotadas

- **P1 — Fronteira.** Aplicação Viabiliza completa (gestão, portal, consultas públicas, recebimento REGIN, rotinas agendadas). Login gov.br fica fora: está implementado, mas desligado (features.govbr_login = 0) e sem client_id/secret; o AIE de identidade gov.br também saiu, pois nenhuma transação contada o lê. Envio à SEFAZ continua fora (HU-109 bloqueada).
- **P2 — Dados de código.** CPM Parte 1 §5.4.2 d e Parte 3 Cap. 1: entidades de substituição código/descrição ou de valores válidos não são arquivo lógico e as transações que só as mantêm não contam. Aplicado a Tipo de serviço, Zona urbanística e Via, com os atributos de cada tabela registrados. Tipo de imóvel, Feriado, Setor e Texto decisório continuam contáveis porque têm atributos que mudam o comportamento ou entram em cálculo.
- **P3 — Teste de independência.** CPM Parte 3 Cap. 2: entidades que só existem com o pai (FK obrigatória com exclusão em cascata e sem ciclo próprio) passam a subgrupo (RLR): versão das regras, documento anexado, aceite de declaração, aceite de termo, TVL emitido, solicitação de acesso. Ficha de análise, Decisão, Ficha de vistoria e Pendência foram mantidas como ALI porque têm ciclo de vida e manutenção próprios (ver pendência 4).
- **P4 — Registros automáticos de log.** CPM Parte 1 §5.5.4 (conta-se um ALR para cada função de dados lida ou mantida) e §5.5.3, Tabela 5 (uma CE não atualiza ALI). Quando a transação grava no mesmo processamento registro em Trilha de auditoria, Registro de acesso, Registro de envio de e-mail, Chamada de integração, Notificação ao usuário ou Comunicação ao requerente, esse arquivo conta como AR, e uma consulta que grava o próprio registro passa a SE. Só com evidência no código (chamada explícita, trait HasAuditoria, listener síncrono); gravações enfileiradas não contam. A contagem anterior aplicava isso de forma parcial.
- **P5 — Efeitos automáticos.** Não são função própria: decisão do fluxo expresso disparada pelo protocolo (os arquivos que ela lê e grava entram no AR do protocolo e do recebimento REGIN), envio do parecer à JUCEB, notificações, e-mails e reprocessamentos técnicos agendados (processos:reprocessar-etapas, expresso:reavaliar, regin:reenviar-pareceres). O reprocessamento acionado pelo analista no botão "Reprocessar" da ficha do expresso é contado (HU-084), assim como o resumo de conferência do portal (HU-179); as duas funções entraram após o cruzamento com as HUs, por decisão do responsável pela aplicação em 16/10/2026.
- **P6 — Unicidade.** CPM Parte 1 §5.5.2.2: mesma ação no portal e na gestão com os mesmos DER, AR e lógica conta uma vez (termo LGPD, notificações); ações com DER, AR ou lógica diferentes contam separadas (login do portal x login da gestão, abas de alteração das APIs, funções do Quadro 10 x Quadro 11A, aprovar x recusar solicitação de acesso).
- **P7 — Abas e telas.** Abas que são cadastros independentes contam como funções separadas, mesmo quando a tela as carrega juntas: na configuração das APIs, cada integração é consultada e alterada como cadastro próprio (decisão do responsável pela aplicação em 16/10/2026; a tela única é solução de interface). Telas GET que só abrem formulário, seletores de lista, menus, downloads estáticos, documentação OpenAPI, testes de conexão, metadados de interface e logout não contam.
- **P8 — Funções sem acesso do usuário.** Rotas implementadas mas sem tela ou botão que as chame não contam: emissão do documento de indeferimento (botão retirado em 22/09), exportações por ?formato= sem botão (usuários, perfis, CNAEs, parâmetros), PUT /gestao/louos/publicar, telas legadas config-regin e config-inscricao-imobiliaria fora do menu, comandos artisan manuais.
- **P9 — Importação de dados externos.** Dado recebido por carga é entrada (EE), não AIE: importação mensal do benchmark REDESIM conta como EE e alimenta o ALI de benchmark. Os AIE são dados mantidos por outra aplicação e lidos pelo Viabiliza (GeoServer da SEDUR, contatos da SEDUR, cadastro imobiliário da SEFAZ, lotes do SEMAP, diretório da SEDUR, geocodificação).

## 3. Correções em classificação, complexidade ou pontuação

Funções que já existiam e mudaram de tipo, complexidade ou PF (as que mudaram só TD/AR sem efeito em PF estão na coluna Observações da planilha, com o texto "Recontagem 16/10/2026: alterada").

| Linha anterior | Função anterior | De | Para | Motivo |
|---:|---|---|---|---|
| 19 | Documentos oficiais - verificar autenticidade | CE Média 4 PF (TD 7/AR 3) | SE Média 5 PF (TD 7/AR 3) | CE→SE e AR 3→5: a verificação recalcula o HMAC do código do comprovante (cálculo) e grava a Trilha de auditoria a cada consulta, o que pela regra do §5.5.3 impede CE; entram Decisão de viabilidade (o protocolo do TVL e do indeferimento é lido pela decisão) e Trilha de auditoria. PF 4→7. Catálogo: retirado Termo de Viabilidade de Localização emitido, Documento de indeferimento emitido (subgrupo de outro arquivo). |
| 22 | Notificações - consultar | CE Média 4 PF (TD 8/AR 2) | SE Média 5 PF (TD 7/AR 2) | Tipo CE→SE e TD 8→7: a tela não mostra tipo nem data de leitura; mostra a quantidade de não lidas, contagem calculada no servidor (badge do sininho), o que torna a função SE. |
| 27 | Comunicações - consultar histórico na gestão | CE Média 4 PF (TD 13/AR 3) | SE Alta 7 PF (TD 10/AR 4) | Tipo CE→SE, TD 13→10, AR 3→4 e nome: a consulta grava a trilha 'historico-consultado' (atualiza ALI, logo não pode ser CE); a tela mostra uma única data do evento, não as quatro; a rota do portal é a mesma função. |
| 30 | Perfil do usuário - alterar dados pessoais | EE Média 4 PF (TD 5/AR 2) | EE Alta 6 PF (TD 5/AR 3) | AR 2→3: com e-mail novo, User::sendEmailVerificationNotification grava no mesmo request o Registro de envio de e-mail. |
| 34 | Senha - alterar senha | EE Baixa 3 PF (TD 5/AR 1) | EE Média 4 PF (TD 5/AR 2) | AR 1→2: UpdateUserPassword grava o evento 'senha-alterada' na trilha (AuditService), verificado em teste; a contagem anterior não o considerou. |
| 62 | Consultas de viabilidade - consultar histórico | CE Baixa 3 PF (TD 6/AR 1) | SE Média 5 PF (TD 6/AR 2) | CE→SE e AR 1→2: a abertura do histórico grava a Trilha de auditoria (AuditService::log 'consulta-historico'), o que pela regra do §5.5.3 impede CE; a Trilha entra como FTR. PF 3→5. |
| 87 | Termo LGPD - aceitar no portal | EE Baixa 3 PF (TD 3/AR 2) | EE Média 4 PF (TD 3/AR 3) | Nome passa a cobrir também a gestão (linha 405 removida por duplicidade); Aceite de termo legal substituído por Usuário (subgrupo); AR 2→3: o aceite é auditado (HasAuditoria em LegalTermAcceptance), verificado em teste. |
| 90 | Protocolo - consultar publicamente | CE Média 4 PF (TD 8/AR 2) | SE Média 5 PF (TD 8/AR 3) | CE→SE e AR 2→3: a consulta grava a Trilha de auditoria com causer nulo (teste test_auditoria_publica_com_causer_null), o que pela regra do §5.5.3 impede CE; a Trilha entra como FTR. PF 4→5. |
| 100 | Solicitação - baixar documento anexo | CE Baixa 3 PF (TD 4/AR 2) | SE Baixa 4 PF (TD 4/AR 2) | CE→SE e AR 2→2 com outros arquivos: o download grava a Trilha de auditoria (AuditService::log 'documento-download'), o que pela regra do §5.5.3 impede CE; o Documento anexado passa a subgrupo da Solicitação. PF 3→4. |
| 108 | Solicitações - consultar minhas solicitações | CE Alta 6 PF (TD 10/AR 4) | CE Média 4 PF (TD 11/AR 3) | Catálogo: Tipo de serviço retirado do AR (dado de código). AR 4→3; PF 6→4 (CE FTR 3, DER 11 = Média). |
| 112 | Empresas - consultar CNPJ na base de contatos da SEDUR | CE Média 4 PF (TD 21/AR 1) | SE Média 5 PF (TD 7/AR 3) | Tipo CE→SE, TD 21→7, AR 1→3: o único provedor ligado (SedurCnpjLookup) só devolve CNPJ, razão social, nome fantasia, telefone e endereço (os demais campos vêm sempre nulos); cada consulta grava a trilha 'consulta-cnpj' e a Chamada de integração (atualiza ALI). |
| 122 | REGIN - receber solicitação | EE Média 4 PF (TD 11/AR 2) | EE Alta 6 PF (TD 45/AR 19) | Nome, TD 11→45 e AR 2→13 (Média 4 PF → Alta 6 PF). A declaração plana da JUCEB passou a ser recebida (RecebimentoProcesso, commits a1601885, fa6948a4, 8ac04e49) e o mesmo processo elementar abre o processo de viabilidade em fila (ProtocolarRecebimentoReginJob): o envelope só deixa o negócio consistente quando vira processo. A fila é divisão técnica, não processo elementar separado. Tipo de serviço não é FTR (dado de código, revisão do catálogo). Consolidação: incluídos no AR os arquivos lidos e gravados pela decisão expressa consequente. |
| 137 | Pendências - cancelar pendência | EE Baixa 3 PF (TD 3/AR 2) | EE Média 4 PF (TD 3/AR 3) | Premissa P4: grava a trilha; AR 2→3 eleva a EE a Média (3→4 PF). |
| 150 | Recuperação de senha - solicitar | EE Baixa 3 PF (TD 3/AR 1) | EE Média 4 PF (TD 3/AR 3) | AR 1→3: o disparo grava no mesmo request o Registro de envio de e-mail (User::sendPasswordResetNotification e sendGestaoPasswordResetNotification) e, na gestão, a elegibilidade lê a permissão do Perfil de acesso; passa a cobrir também a rota do portal (mesma função). |
| 156 | Processos - consultar tramitação | CE Média 4 PF (TD 7/AR 2) | SE Média 5 PF (TD 12/AR 3) | CE→SE, TD 7→12, AR 2→3: a linha do tempo passou a juntar situação da análise, atribuições (quem distribuiu e a quem), pendências, vistoria, malha fina, documentos e os envios à JUCEB e à SEFAZ lidos da Trilha de auditoria; a tela calcula a duração de cada etapa (já existia e não fora considerada), o que pela regra é cálculo e leva a SE. PF 4→5. Harmonização: CE→SE, TD 7→12, AR 2→3 em relação a 01/10 (PF 4→5); motivo nas observações (premissa P4/revisão do catálogo). |
| 181 | Setores - vincular analistas | EE Baixa 3 PF (TD 4/AR 2) | EE Média 4 PF (TD 4/AR 3) | AR 2→3: inclui a Trilha de auditoria gravada com antes/depois do vínculo. PF 3→4 (EE Média). |
| 189 | Contingência - consultar inscrição imobiliária | CE Baixa 3 PF (TD 10/AR 1) | SE Média 5 PF (TD 10/AR 3) | Harmonização: CE→SE, AR 1→3 em relação a 01/10 (PF 3→5); motivo nas observações (premissa P4/revisão do catálogo). |
| 197 | Caixa do setor - distribuir processo | EE Média 4 PF (TD 4/AR 4) | EE Alta 6 PF (TD 5/AR 6) | TD 4→5 e AR 4→5: o apoio passou a distribuir em qualquer setor e escolhe o setor de destino quando o analista está em mais de um (sector_id); a distribuição também reatribui quem conclui na aba Para conclusão. O registro da distribuição na trilha passou a ser lido pela tramitação (quem distribuiu e a quem), por isso Trilha de auditoria entra no AR. PF 4→6. Harmonização: TD 4→5, AR 4→6 em relação a 01/10 (PF 4→6); motivo nas observações (premissa P4/revisão do catálogo). |
| 200 | Caixa do setor - sugerir distribuição pela carga | SE Média 5 PF (TD 9/AR 3) | SE Alta 7 PF (TD 9/AR 5) | Harmonização: AR 3→5 em relação a 01/10 (PF 5→7); motivo nas observações (premissa P4/revisão do catálogo). |
| 202 | Caixa do setor - redistribuir processo | EE Média 4 PF (TD 4/AR 3) | EE Alta 6 PF (TD 5/AR 5) | TD 4→5 e AR 3→4: aceita o setor de destino (sector_id) para o apoio que redistribui a analista de outro setor; o registro da redistribuição na trilha passou a ser lido pela tramitação. PF 4→6. Harmonização: TD 4→5, AR 3→5 em relação a 01/10 (PF 4→6); motivo nas observações (premissa P4/revisão do catálogo). |
| 209 | Perfis de acesso - consultar | SE Baixa 4 PF (TD 7/AR 1) | SE Média 5 PF (TD 8/AR 3) | TD 7→8: busca por nome passou a ser server-driven. AR 1→3: o perfil virou ALI próprio (Perfil de acesso); a contagem de usuários lê Usuário e a marca de protegido lê o parâmetro portal.perfil_cidadao (Parâmetro). PF 4→5 (SE Média). |
| 211 | Perfis de acesso - alterar | EE Baixa 3 PF (TD 4/AR 2) | EE Média 4 PF (TD 4/AR 3) | AR 2→3: grava Perfil de acesso (antes Usuário) e lê o parâmetro portal.perfil_cidadao para impedir renomear o perfil do cidadão (commit 78cf5671). PF 3→4 (EE Média). |
| 212 | Perfis de acesso - excluir | EE Baixa 3 PF (TD 3/AR 2) | EE Média 4 PF (TD 3/AR 4) | AR 2→4: exclui de Perfil de acesso, lê Usuário (vínculo) e Parâmetro (perfil do cidadão protegido) e grava a Trilha. PF 3→4 (EE Média). |
| 218 | Usuários - consultar | SE Baixa 4 PF (TD 10/AR 1) | SE Média 5 PF (TD 11/AR 2) | TD 10→11: +1 DER de ação. AR 1→2: a separação das abas lê as permissões do perfil (acessar-gestao), agora no ALI Perfil de acesso. PF 4→5 (SE Média). |
| 220 | Usuários - alterar perfil de acesso | EE Baixa 3 PF (TD 4/AR 2) | EE Média 4 PF (TD 4/AR 3) | AR 2→3: o perfil escolhido é validado contra o ALI Perfil de acesso (antes embutido em Usuário). PF 3→4 (EE Média). |
| 221 | Usuários - alterar forma de autenticação | EE Média 4 PF (TD 5/AR 2) | EE Alta 6 PF (TD 5/AR 3) | AR 2→3: ao converter conta AD em local, o processo grava o Registro de envio de e-mail do link de nova senha (User::sendGestaoPasswordResetNotification cria EmailLog). PF 4→6 (EE Alta). |
| 224 | Quadros LOUOS - consultar rascunho | SE Alta 7 PF (TD 15/AR 4) | SE Média 5 PF (TD 13/AR 2) | Desmembrada por quadro: a linha anterior juntava Quadro 10 e 11A com DER e FTR somados, mas cada execução trata um só quadro, com campos e arquivo diferentes (unicidade, CPM 4.3.1 Parte 1 §5.5.2.2). A parte do Quadro 11A virou função própria. Versão de regras deixa de ser FTR (passou a RLR do Quadro 10). TD 15→13, AR 4→2; SE Alta→Média (7→5). |
| 225 | Quadros LOUOS - abrir rascunho | EE Média 4 PF (TD 4/AR 4) | EE Baixa 3 PF (TD 3/AR 2) | Desmembrada por quadro (unicidade §5.5.2.2). Versão de regras passa a RLR do Quadro 10. TD 4→3 (o seletor de quadro deixa de ser DER, pois a função é de um só quadro), AR 4→2; Média→Baixa (4→3). |
| 226 | Quadros LOUOS - incluir linha | EE Alta 6 PF (TD 12/AR 4) | EE Média 4 PF (TD 8/AR 2) | Desmembrada por quadro (unicidade §5.5.2.2): campos do Quadro 10 (zona, subgrupo, permissão, condicionante) diferem dos do 11A. Observação saiu dos DER: o formulário (resources/js/pages/gestao/louos/quadro-fields.ts) não a oferece. TD 12→8, AR 4→2; Alta→Média (6→4). |
| 227 | Quadros LOUOS - alterar linha | EE Alta 6 PF (TD 12/AR 4) | EE Média 4 PF (TD 8/AR 2) | Desmembrada por quadro (unicidade §5.5.2.2). TD 12→8, AR 4→2; Alta→Média (6→4). |
| 228 | Quadros LOUOS - excluir linha | EE Média 4 PF (TD 3/AR 4) | EE Baixa 3 PF (TD 3/AR 2) | Desmembrada por quadro (unicidade §5.5.2.2). AR 4→2; Média→Baixa (4→3). |
| 229 | Quadros LOUOS - importar planilha | EE Alta 6 PF (TD 10/AR 4) | EE Média 4 PF (TD 14/AR 2) | Desmembrada por quadro (unicidade §5.5.2.2): layout do CSV e serviço de importação próprios (LouosQuadro10ImportService). Passa a contar os campos do arquivo que cruzam a fronteira (cabeçalho em LouosDraftController::CSV_HEADERS). TD 10→14, AR 4→2; Alta→Média (6→4). |
| 230 | Quadros LOUOS - publicar rascunho | EE Média 4 PF (TD 3/AR 6) | EE Baixa 3 PF (TD 3/AR 2) | Desmembrada por quadro (unicidade §5.5.2.2): a guarda de zonas é exclusiva do Quadro 10. Zona urbanística e Via deixam de ser FTR (dados de código, removidos) e Versão de regras passou a RLR. AR 6→2; Média→Baixa (4→3). A publicação feita pela tela de simulação (PUT /gestao/louos/simulacao/publicar) chama o mesmo LouosDraftService::publicar e é o mesmo processo elementar. |
| 231 | Quadros LOUOS - descartar rascunho | EE Média 4 PF (TD 3/AR 4) | EE Baixa 3 PF (TD 3/AR 2) | Desmembrada por quadro (unicidade §5.5.2.2). AR 4→2; Média→Baixa (4→3). |
| 236 | Parâmetros - alterar | EE Baixa 3 PF (TD 4/AR 2) | EE Média 4 PF (TD 4/AR 3) | AR 2 para 3: desde 14/10/2026 (commit 78cf5671) a validação do parâmetro portal.perfil_cidadao lê o perfil e suas permissões (regra app/Rules/PerfilSemAcessoAGestao.php, Role::query e permissions), dado do ALI Perfil de acesso. PF 3 para 4. |
| 245 | Resultados do expresso - consultar lista | CE Média 4 PF (TD 13/AR 3) | SE Alta 7 PF (TD 13/AR 4) | Premissa P4: grava a trilha da consulta; por gravar ALI passa de CE a SE (AR 3→4, 4→7 PF). |
| 256 | Redefinição de senha - redefinir | EE Baixa 3 PF (TD 5/AR 1) | EE Média 4 PF (TD 5/AR 2) | AR 1→2: o evento PasswordReset grava 'senha-redefinida' na trilha (RecordPasswordResetActivity), verificado em teste; passa a cobrir também a rota do portal (mesma função). |
| 259 | Território - localizar endereço no mapa | SE Baixa 4 PF (TD 7/AR 1) | SE Média 5 PF (TD 7/AR 2) | Entra como FTR o AIE Endereços geocodificados do OpenStreetMap (catálogo harmonizado): o Geocoder é ligado a NominatimGeocoder em app/Providers/AppServiceProvider.php. AR 1→2; Baixa→Média (4→5). |
| 272 | Camadas geográficas - incluir | EE Baixa 3 PF (TD 6/AR 1) | EE Média 4 PF (TD 8/AR 2) | A carga audita o diff (GeoLayerService::auditLoad, chamado por GeoJsonLayerImporter): Trilha de auditoria entra como FTR. Contam-se geometria e propriedades das feições do arquivo. TD 6→8, AR 1→2; Baixa→Média (3→4). |
| 276 | TVL - emitir termo | EE Alta 6 PF (TD 6/AR 8) | SE Alta 7 PF (TD 21/AR 7) | Unifica "TVL - emitir termo" (276, EE) e "TVL - baixar termo emitido" (277, SE): o botão Abrir PDF / Visualizar Viabilidade chama POST /tvl e abre na hora a URL assinada (5 min) devolvida; não há tela que liste ou baixe um termo emitido antes. Reclassificada EE→SE pela intenção primária (ver o documento). AR sem o TVL emitido (agora subgrupo da Decisão). Total 13 PF → 7 PF. |
| 287 | Anexos de escritório virtual - carregar rascunho | EE Alta 6 PF (TD 7/AR 3) | EE Média 4 PF (TD 7/AR 2) | Versão de regras passou a RLR do arquivo de atividades. AR 3→2; Alta→Média (6→4). |
| 296 | Atendimento - consultar CPF na base da SEDUR | CE Baixa 3 PF (TD 5/AR 2) | SE Baixa 4 PF (TD 5/AR 3) | Harmonização: CE→SE, AR 2→3 em relação a 01/10 (PF 3→4); motivo nas observações (premissa P4/revisão do catálogo). |
| 314 | Relatórios - consultar produtividade | SE Média 5 PF (TD 15/AR 3) | SE Alta 7 PF (TD 12/AR 4) | TD 15→12: a tela só filtra por período (setor, analista, bairro, CNAE e categoria não existem nela); entram os dois totais exibidos nos cartões. AR 3→4 com a Trilha de auditoria gravada pela consulta. SE Média→Alta (5→7 PF). |
| 315 | Relatórios - consultar quedas do expresso | SE Média 5 PF (TD 18/AR 3) | SE Alta 7 PF (TD 11/AR 4) | TD 18→11: a tela só filtra por período; a exportação desta tela entrega a lista de quedas (outra função: Relatórios - exportar quedas do expresso), então formato sai daqui. AR 3→4 com a Trilha de auditoria. SE Média→Alta (5→7 PF). |
| 316 | Relatórios - consultar painel por bairro | SE Média 5 PF (TD 17/AR 3) | SE Alta 7 PF (TD 16/AR 4) | TD 17→16: formato sai porque a exportação desta tela é a lista de solicitações (outra função). AR 3→4 com a Trilha de auditoria. SE Média→Alta (5→7 PF). |
| 318 | Relatórios - consultar escritório virtual (sede e abrigados) | CE Alta 6 PF (TD 12/AR 4) | SE Alta 7 PF (TD 9/AR 5) | CE→SE: a consulta grava a Trilha de auditoria (mantém ALI). TD 12→9: o nº da viabilidade da sede (filtro) e o da linha são o mesmo DER; a coluna Vencimento e o filtro Exibir Expirados ficam sempre vazios/desabilitados (validade não modelada) e não contam; o recorte de sedes virou função própria de exportação. AR 4→5 com a Trilha. 6→7 PF. |
| 321 | Relatórios - consultar pendências e exigências | SE Média 5 PF (TD 19/AR 3) | SE Alta 7 PF (TD 18/AR 4) | TD 19→18: situação do filtro e da coluna é o mesmo DER. AR 3→4 com a Trilha de auditoria. SE Média→Alta (5→7 PF). |
| 324 | Relatórios - consultar trilha por processo | CE Alta 6 PF (TD 10/AR 5) | SE Alta 7 PF (TD 10/AR 5) | CE→SE: com protocolo informado a consulta grava a Trilha de auditoria (mantém ALI), e CE não pode manter ALI. TD 10 mantido (protocolo de busca e protocolo exibido são um DER; entra a mensagem de processo não encontrado). 6→7 PF. |
| 329 | Ficha de análise - consultar | CE Alta 6 PF (TD 40/AR 13) | SE Alta 7 PF (TD 42/AR 15) | TD 40→42 e AR 13→14: a consulta passa a exibir as sugestões de IA gravadas (cards da ficha, carregados sob demanda na mesma tela) e o motivo de análise passa a apontar o ponto da planilha que tirou a atividade do expresso (commits 6db1255e e 5a3c4469, 14/10). Premissa P4: a abertura grava a trilha (eventos ficha-consulta e ficha-cadastro-consulta) e a consulta ao cadastro imobiliário grava a chamada de integração (SedurSefazPropertyRegistryLookup via Http, RegistrarChamadaIntegracao); por gravar ALI passa de CE a SE (AR 14→16, 6→7 PF). Catálogo: retirado Versão de regras (subgrupo de outro arquivo). |
| 330 | Ficha de análise - salvar rascunho | EE Média 4 PF (TD 13/AR 2) | EE Alta 6 PF (TD 14/AR 3) | TD 13→14: motivos de análise (analysis_reasons) também são recebidos no autosave (AnalysisRecordRequest). Premissa P4: grava a trilha (ficha-autosave); AR 2→3 eleva a complexidade a Alta (4→6 PF). |
| 333 | Ficha de análise - abrir nova revisão | EE Baixa 3 PF (TD 5/AR 1) | EE Média 4 PF (TD 5/AR 2) | Premissa P4: AnalysisRecordService::novaRevisao grava a trilha (ficha-nova-revisao); AR 1→2 eleva a EE a Média (3→4 PF). |
| 334 | Ficha de análise - sugerir minuta de parecer | EE Média 4 PF (TD 4/AR 6) | EE Alta 6 PF (TD 7/AR 7) | TD 4→7 (fundamentação, fonte e confiança da minuta recebidas do provedor e gravadas). Complexidade Média→Alta: 4→6 PF. |
| 335 | Ficha de análise - obter resumo do processo | EE Média 4 PF (TD 4/AR 6) | EE Alta 6 PF (TD 6/AR 8) | TD 4→6 (pontos-chave e fonte do resumo gravados e exibidos). Complexidade Média→Alta: 4→6 PF. |
| 336 | Ficha de análise - comparar revisões | SE Baixa 4 PF (TD 6/AR 1) | SE Média 5 PF (TD 6/AR 2) | Premissa P4: grava a trilha (ficha-diff); AR 1→2 eleva a SE a Média (4→5 PF). |
| 379 | Território - identificar imóvel | SE Média 5 PF (TD 12/AR 2) | SE Alta 7 PF (TD 12/AR 5) | FTR revisados: TerritoryService audita a identificação (evento identificacao), GeoServerWfsZonaClient lê as camadas ativas do catálogo Camada do GeoServer e TerritoryService::comClasseViaria consulta GeoServerClassificacaoViariaClient (AIE Classificação viária dos logradouros do GeoServer da SEDUR). Não lê lotes do endereçamento nem poligonais territoriais do GeoServer (o lote vem da camada geográfica local). AR 2→5; Média→Alta (5→7). As mudanças de 14/10 (lote pelo ponto, classe viária pelo CODLOG) estão em TerritorioProcessoService e PoligonoEnderecamentoService, usados pelo motor do processo, não por esta tela. |
| 385 | Textos-padrão - incluir | EE Baixa 3 PF (TD 5/AR 1) | EE Média 4 PF (TD 5/AR 2) | AR 1→2: o controller grava a Trilha de auditoria (AuditService, ação criar). PF 3→4 (EE Média). |
| 386 | Textos-padrão - alterar | EE Baixa 3 PF (TD 6/AR 1) | EE Média 4 PF (TD 6/AR 2) | AR 1→2: o controller grava a Trilha de auditoria (AuditService, ação atualizar). PF 3→4 (EE Média). |
| 417 | Vistoria - consultar ficha | CE Alta 6 PF (TD 75/AR 4) | SE Alta 7 PF (TD 75/AR 5) | A criação da ficha no primeiro acesso passou para a função nova "Vistoria - abrir ficha de vistoria". Premissa P4: show e visualizar gravam a trilha (ficha-consulta / ficha-visualizacao); por gravar ALI passa de CE a SE (AR 4→5, 6→7 PF). |
| 420 | Vistoria - validar polígono do imóvel | EE Média 4 PF (TD 5/AR 2) | EE Alta 6 PF (TD 5/AR 3) | Premissa P4: grava a trilha (poligono-validado); AR 2→3, EE Alta (4→6 PF). |
| 421 | Vistoria - anexar foto ou documento | EE Média 4 PF (TD 6/AR 2) | EE Alta 6 PF (TD 6/AR 3) | Premissa P4: grava a trilha (anexo-adicionado); AR 2→3, EE Alta (4→6 PF). |
| 422 | Vistoria - baixar anexo | CE Baixa 3 PF (TD 4/AR 2) | SE Baixa 4 PF (TD 4/AR 3) | Premissa P4: grava a trilha (anexo-download); por gravar ALI passa de CE a SE (AR 2→3, 3→4 PF). |
| 423 | Vistoria - remover anexo | EE Baixa 3 PF (TD 3/AR 2) | EE Média 4 PF (TD 3/AR 3) | Premissa P4: grava a trilha (anexo-removido); AR 2→3, EE Média (3→4 PF). |
| 430 | TLL - incluir valor | EE Baixa 3 PF (TD 11/AR 1) | EE Média 4 PF (TD 11/AR 2) | AR 1 para 2: TllValor usa HasAuditoria, então a inclusão grava a Trilha de auditoria (o próprio teste se chama test_cria_valor_auditado). Critério igual ao aplicado a e-mail, IA e parâmetros. PF 3 para 4. |
| 431 | TLL - alterar valor | EE Baixa 3 PF (TD 11/AR 1) | EE Média 4 PF (TD 11/AR 2) | AR 1 para 2: a alteração grava a Trilha de auditoria pelo HasAuditoria do model TllValor. PF 3 para 4. |
| 444 | Dados - Regra territorial | ALI Baixa 7 PF (TD 18/AR 3) | ALI Média 10 PF (TD 25/AR 3) | Absorve o cabeçalho de versão do domínio territorial (linha 510 removida) como RLR. O subgrupo de CNAEs (territorial_rule_cnaes) tem um só atributo (cnae) e deixa de ser RLR; o CNAE alcançado fica como DER. TD 18→25, TR 3; Baixa→Média (7→10) pelo total de DER. |
| 447 | Regras territoriais - descartar rascunho | EE Média 4 PF (TD 3/AR 3) | EE Baixa 3 PF (TD 3/AR 2) | Versão de regras passou a RLR da Regra territorial. AR 3→2; Média→Baixa (4→3). |
| 457 | Textos decisórios - alterar | EE Média 4 PF (TD 5/AR 2) | EE Baixa 3 PF (TD 4/AR 2) | TD 5→4: a chave é identificador técnico na URL e não é exibida. PF 4→3 (EE Baixa). |
| 468 | Integrações - consultar chamadas | CE Baixa 3 PF (TD 10/AR 1) | SE Média 5 PF (TD 10/AR 2) | CE→SE e AR 1→2: o IntegrationCallController@index grava a consulta na Trilha de auditoria (consulta-integracoes, dado pessoal), o que mantém ALI. 3→5 PF. |
| 469 | Integrações - consultar detalhe da chamada | CE Baixa 3 PF (TD 18/AR 1) | SE Média 5 PF (TD 18/AR 2) | CE→SE e AR 1→2: o IntegrationCallController@show grava a consulta na Trilha de auditoria (consulta-integracao, dado pessoal). 3→5 PF. |
| 505 | Dados - Usuário | ALI Baixa 7 PF (TD 15/AR 2) | ALI Média 10 PF (TD 26/AR 4) | TD 15→26 e TR 2→4: entram os subgrupos Solicitação de acesso (access_requests, criada em 14/10/2026) e Aceite de termo legal (antes ALI próprio, linha 502), ambos dependentes da conta e com atributos não-chave. PF 7→10 (ALI Média). |
| 512 | Dados - Decisão de viabilidade | ALI Baixa 7 PF (TD 17/AR 2) | ALI Média 10 PF (TD 21/AR 3) | TD 17→21 e TR 2→3: o TVL emitido em PDF (tvl_documents) deixa de ser ALI próprio e entra como subgrupo da decisão (teste de independência: cada emissão só existe com a decisão deferida e é apagada com ela, cascadeOnDelete). Complexidade Baixa→Média: 7→10 PF. |

## 4. Funções removidas da contagem

| Linha anterior | Grupo anterior | Função | PF anterior | Motivo | Absorvida por |
|---:|---|---|---:|---|---|
| 24 | Central de notificações | Notificações - marcar todas como lidas | 3 | POST /portal/notificacoes/ler-todas e /gestao/notificacoes/ler-todas (NotificationCenterController@markAllAsRead) aplicam a mesma marcação de leitura (read_at) no mesmo arquivo (Notificação ao usuário), só que em lote sobre as não lidas. É variação do processo de marcar como lida, não processo novo: CPM Parte 2, Cap. 7, nota da unicidade ('não divida um processo elementar com múltiplas formas de processamento lógico'; o teste de unicidade não serve para dividir um PE por variação) e Roteiro SISP 2.3 §4.8 (permitir exclusão múltipla numa funcionalidade que só tratava um item é adaptação não funcional). | Notificações - marcar como lida |
| 74 | Pesquisa de CNAE | CNAEs - pesquisar atividade | 3 | GET /portal/cnaes, /gestao/contingencia/cnaes-disponiveis e /gestao/requisitos-documentais/cnaes-disponiveis (Portal\CnaeSearchController) devolvem em JSON até 20 CNAEs ativos (código e descrição) para o seletor com busca dos formulários; o próprio controller declara o limite como 'limite técnico de itens do select com busca'. Não há tela nem consulta própria completa: é lista de valores auxiliar das funções que escolhem CNAE (a consulta completa de CNAEs é a da administração). REGRAS da recontagem: 'Busca auxiliar de lista de valores (lookup/autocomplete) só conta se entregar consulta própria completa'. | Empresas - definir CNAEs secundários |
| 125 | Exportação de relatórios | Relatórios - baixar exportação | 4 | O download (GET /gestao/relatorios/exportacoes/{exportFile}/download, ExportacaoController@download) só entrega o arquivo que a própria exportação gerou quando o volume passa do limiar técnico relatorios.export.assincrono_limiar_linhas (ReportExporter despacha o GerarExportacaoJob, que grava ExportFile e avisa pela notificação ExportacaoPronta). Abaixo do limiar o mesmo arquivo sai na hora, sem esse passo. O usuário pediu uma exportação: fila, arquivo temporário e link assinado são a forma técnica de entregar a mesma saída, não processo elementar autocontido (CPM Parte 1 §5.5.2: não contar filas, jobs técnicos e baixar arquivo já gerado). O ExportFile é artefato técnico podado em relatorios. | Relatórios - exportar solicitações do recorte; Relatórios - exportar tempo por etapa por processo; Relatórios - exportar quedas do expresso; Relatórios - exportar sedes de escritório virtual; demais consultas de relatórios com exportação do mesmo recorte; Auditoria - exportar trilha |
| 185 | Acesso à gestão | Gestão - sair do sistema | 3 | Rota POST /gestao/logout (Gestao\LoginController@destroy) só encerra o guard gestão e regenera o token; o Registro de acesso 'logout' é gravado pelo listener RecordLogout como efeito automático. Encerrar sessão não é processo elementar com significado de negócio: REGRAS da recontagem ('Não conte separadamente: ... sessão (logout)') e CPM Parte 1 §5.5.2 (o PE precisa ser significativo para o usuário e deixar o negócio consistente; o fim de sessão não altera dado de negócio). Pelo mesmo motivo o logout do portal (POST /portal/logout, Fortify) também não foi contado. |  |
| 192 | Simulação de regras da LOUOS | Simulação de regras - abrir simulação | 3 | A tela gestao/louos/sandbox só preenche o seletor "Rascunho a simular" com rótulo e versão dos rascunhos (resources/js/pages/gestao/louos/sandbox.tsx, rascunhoOptions); autor e data não são exibidos. É lista de valores de apoio à função de simular, sem consulta própria completa (regra do roteiro: lookup só conta com consulta própria; CPM 4.3.1 Parte 1 §5.5.2). Os rascunhos com autor já são vistos em Quadro 10/11A - consultar rascunho. | Simulação de regras - simular aplicação de rascunho |
| 215 | Decisão técnica do processo | Análise - registrar decisão técnica | 6 | POST /decidir (ProcessoDecisaoController) e POST /ficha/concluir-processo (AnalysisRecordController@concluirProcesso) executam o mesmo AnaliseTecnicaDecisionService::decide, com os mesmos arquivos lógicos e o mesmo resultado (decisão, situação, TVL, bloqueio de sede, notificação). A primeira só exige a ficha já finalizada e recebe os dados de renovação; a segunda finaliza antes. Pela unicidade (CPM Parte 1 §5.5.2.2) e por serem passos sequenciais do mesmo processo (Roteiro SISP §5.3), conta uma vez, com a união dos DER. | Análise - decidir o processo conforme a ficha |
| 275 | Termo de Viabilidade de Localização (TVL) | Dados - Termo de Viabilidade de Localização emitido | 7 | Teste de independência (CPM Parte 3 Cap. 2): cada emissão do PDF do TVL só existe ligada a uma decisão deferida (FK viability_decision_id com cascadeOnDelete na migration 2026_06_14_215048_create_tvl_documents_table) e cada reimpressão é nova linha da mesma decisão, como versões de um documento. Não tem significado para o negócio sem a decisão: é subgrupo (RLR) de Decisão de viabilidade, não ALI separado. | Dados - Decisão de viabilidade |
| 277 | Termo de Viabilidade de Localização (TVL) | TVL - baixar termo emitido | 7 | O download por URL assinada (TTL de 5 min) só é alcançado pela URL que a emissão devolve; o componente baixar-tvl e a ficha chamam POST /tvl e abrem a URL no mesmo clique. Não há tela que liste ou baixe termos emitidos antes. Emissão e download são passos sequenciais do mesmo processo elementar (Roteiro SISP §5.3; CPM §5.5.2), contados uma vez. | TVL - emitir e abrir o termo em PDF |
| 331 | Ficha de análise técnica | Ficha de análise - finalizar | 3 | Nenhuma tela chama POST /ficha/finalizar desde 17/09 (commit 97226c68): o botão Finalizar processo chama /ficha/concluir-processo, que finaliza a revisão (imutável, com divergências) e decide na mesma ação. A finalização isolada é endpoint sem acesso do usuário (CPM Parte 1 §5.5.2: não contar rotas/endpoints) e sua lógica já está no processo de decisão. | Análise - decidir o processo conforme a ficha |
| 349 | Documento de indeferimento | Dados - Documento de indeferimento emitido | 7 | Nenhum processo elementar acessível ao usuário mantém mais o registro (a emissão perdeu a tela em 22/09). Além disso, pelo teste de independência é subgrupo da decisão indeferida (FK com cascadeOnDelete na migration 2026_09_18_100000_create_indeferimento_documents_table), não ALI separado. Registros antigos só são lidos pela verificação pública de documentos. |  |
| 350 | Documento de indeferimento | Indeferimento - emitir documento | 6 | O botão "Emitir / baixar documento de indeferimento" foi retirado da ficha em 22/09 (commit 1b89e1d2) e nenhuma tela chama POST /gestao/processos/{id}/indeferimento (busca em resources/js). A rota é endpoint sem acesso do usuário: não é processo elementar significativo para o usuário (CPM Parte 1 §5.5.2). |  |
| 351 | Documento de indeferimento | Indeferimento - baixar documento emitido | 3 | Download por URL assinada que só existe como resposta da emissão de indeferimento, que não tem mais tela (commit 1b89e1d2, 22/09). Sem acesso do usuário, não conta (CPM Parte 1 §5.5.2). |  |
| 354 | Simulação de protocolo REGIN | Simulação REGIN - consultar | 3 | Simulador retirado: commit f6ad05ad (14/10) apagou ReginProtocoloSimulacaoController, ReginProtocoloSimulacaoService, ReginProtocoloCatalog, o model ReginSimulacaoExecucao, a página resources/js/pages/gestao/risco/simulacao-regin.tsx, o item de menu e os testes; commit 33946c60 removeu as rotas /gestao/risco/simulacao-regin (não constam em routes/gestao.php nem na lista de rotas). Sem rota nem tela não há processo elementar (CPM Parte 1 §5.5.2). |  |
| 355 | Simulação de protocolo REGIN | Simulação REGIN - simular protocolo | 6 | Rota POST /gestao/risco/simulacao-regin e controller removidos (commits f6ad05ad e 33946c60); processos REGIN agora só nascem pelo POST /api_integracao/recebe. Função inexistente no código atual (CPM Parte 1 §5.5.2). |  |
| 356 | Simulação de protocolo REGIN | Simulação REGIN - excluir simulação | 4 | Rota DELETE /gestao/risco/simulacao-regin/{codigo} e controller removidos (commits f6ad05ad e 33946c60). Função inexistente no código atual (CPM Parte 1 §5.5.2). |  |
| 359 | Tipos de serviço | Dados - Tipo de serviço | 7 | Tipo de serviço é dado de código (CPM Parte 1 §5.4.2 d, itens 1 e 5; Parte 3 Cap. 1). Evidência: migration database/migrations/2026_06_14_104826_create_viability_service_types_table.php tem só code, name, flow_hint e active; flow_hint é texto livre ("pista de fluxo") que só é exibido (ViabilityServiceTypeController, ContingenciaController) e não entra em nenhuma regra; o comportamento por tipo está fixo no código pelo valor de code (Portal\SolicitacaoController e SolicitacaoAtividadeController comparam com 'alteracao-atividade'; RenovacaoProtocoloService busca 'renovacao-tvl'); a integração REGIN cria tipos sozinha (ReginProcessoProtocolador::firstOrCreate). A tabela é de substituição código |  |
| 360 | Tipos de serviço | Tipos de serviço - consultar | 3 | Consulta de dado de código (Tipo de serviço). Tipo de serviço é dado de código (CPM Parte 1 §5.4.2 d, itens 1 e 5; Parte 3 Cap. 1). Evidência: migration database/migrations/2026_06_14_104826_create_viability_service_types_table.php tem só code, name, flow_hint e active; flow_hint é texto livre ("pista de fluxo") que só é exibido (ViabilityServiceTypeController, ContingenciaController) e não entra em nenhuma regra; o comportamento por tipo está fixo no código pelo valor de code (Portal\SolicitacaoController e SolicitacaoAtividadeController comparam com 'alteracao-atividade'; RenovacaoProtocoloService busca 'renovacao-tvl'); a integração REGIN cria tipos sozinha (ReginProcessoProtocolador::fir |  |
| 361 | Tipos de serviço | Tipos de serviço - incluir | 3 | Manutenção de dado de código (Tipo de serviço). Tipo de serviço é dado de código (CPM Parte 1 §5.4.2 d, itens 1 e 5; Parte 3 Cap. 1). Evidência: migration database/migrations/2026_06_14_104826_create_viability_service_types_table.php tem só code, name, flow_hint e active; flow_hint é texto livre ("pista de fluxo") que só é exibido (ViabilityServiceTypeController, ContingenciaController) e não entra em nenhuma regra; o comportamento por tipo está fixo no código pelo valor de code (Portal\SolicitacaoController e SolicitacaoAtividadeController comparam com 'alteracao-atividade'; RenovacaoProtocoloService busca 'renovacao-tvl'); a integração REGIN cria tipos sozinha (ReginProcessoProtocolador::f |  |
| 362 | Tipos de serviço | Tipos de serviço - alterar | 3 | Manutenção de dado de código (Tipo de serviço). Tipo de serviço é dado de código (CPM Parte 1 §5.4.2 d, itens 1 e 5; Parte 3 Cap. 1). Evidência: migration database/migrations/2026_06_14_104826_create_viability_service_types_table.php tem só code, name, flow_hint e active; flow_hint é texto livre ("pista de fluxo") que só é exibido (ViabilityServiceTypeController, ContingenciaController) e não entra em nenhuma regra; o comportamento por tipo está fixo no código pelo valor de code (Portal\SolicitacaoController e SolicitacaoAtividadeController comparam com 'alteracao-atividade'; RenovacaoProtocoloService busca 'renovacao-tvl'); a integração REGIN cria tipos sozinha (ReginProcessoProtocolador::f |  |
| 363 | Tipos de serviço | Tipos de serviço - ativar ou inativar | 3 | Manutenção de dado de código (Tipo de serviço). Tipo de serviço é dado de código (CPM Parte 1 §5.4.2 d, itens 1 e 5; Parte 3 Cap. 1). Evidência: migration database/migrations/2026_06_14_104826_create_viability_service_types_table.php tem só code, name, flow_hint e active; flow_hint é texto livre ("pista de fluxo") que só é exibido (ViabilityServiceTypeController, ContingenciaController) e não entra em nenhuma regra; o comportamento por tipo está fixo no código pelo valor de code (Portal\SolicitacaoController e SolicitacaoAtividadeController comparam com 'alteracao-atividade'; RenovacaoProtocoloService busca 'renovacao-tvl'); a integração REGIN cria tipos sozinha (ReginProcessoProtocolador::f |  |
| 390 | Zonas urbanísticas | Dados - Zona urbanística | 7 | Dado de código. Migration database/migrations/2026_09_18_162010_create_zonas_table.php tem só codigo (único), nome, macrozona e ativo. O único uso fora do próprio CRUD é LouosDraftService::assertZonasCadastradas, que confere se as zonas do rascunho do Quadro 10 estão na lista de zonas ativas: é lista de valores válidos para validação. O motor não lê a tabela (usa a zona gravada no Quadro 10 e a geometria do AIE da SEDUR); macrozona só é exibida. CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consu |  |
| 391 | Zonas urbanísticas | Zonas urbanísticas - consultar | 3 | Consulta de dado de código (Zona urbanística). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 392 | Zonas urbanísticas | Zonas urbanísticas - incluir | 4 | Manutenção de dado de código (Zona urbanística). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 393 | Zonas urbanísticas | Zonas urbanísticas - alterar | 4 | Manutenção de dado de código (Zona urbanística). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 394 | Zonas urbanísticas | Zonas urbanísticas - ativar ou inativar | 3 | Manutenção de dado de código (Zona urbanística); ativar/inativar não muda a natureza (NOTA 3). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 404 | Termo de consentimento LGPD na gestão | Termo LGPD - consultar na gestão | 3 | Gestao\TermoLgpdController@show tem os mesmos DER (versão, título, conteúdo), os mesmos FTR (Termo legal, Usuário com o subgrupo de aceite) e a mesma lógica de Portal\LgpdTermController@show (exibe o termo vigente só a quem não aceitou); muda apenas o guard e o destino do redirecionamento. Mesmo processo elementar contado duas vezes: CPM Parte 1 §5.5.2.2 (unicidade). A própria observação anterior registrava que deveria ser contado uma vez só. | Termo LGPD - consultar termo vigente |
| 405 | Termo de consentimento LGPD na gestão | Termo LGPD - aceitar na gestão | 4 | Gestao\TermoLgpdController@accept repete Portal\LgpdTermController@accept: mesma validação do aceite, mesmo firstOrCreate do aceite (subgrupo do Usuário) com IP, navegador e data, mesmos FTR. Versão e data do aceite não são informadas pelo usuário (são gravadas pelo sistema), então os DER são os mesmos. Mesmo processo elementar: CPM Parte 1 §5.5.2.2 (unicidade). | Termo LGPD - aceitar termo vigente |
| 437 | Vias | Dados - Via | 7 | Dado de código. Migration database/migrations/2026_09_21_195747_create_vias_table.php tem só codigo (único), nome e ativo. O único uso fora do CRUD é LouosDraftService::assertViasCadastradas (valores válidos de classe de via na publicação do Quadro 11A). A classe viária do imóvel (inclusive pelo CODLOG, mudança de 14/10) vem do GeoServer e é normalizada pelo enum Quadro11aClasseVia (GeoServerClassificacaoViariaClient), sem ler a tabela vias; a condição de instalação está no Quadro 11A. Não há atributo de negócio na tabela. CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Part |  |
| 438 | Vias | Vias - consultar | 3 | Consulta de dado de código (Via). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 439 | Vias | Vias - incluir | 4 | Manutenção de dado de código (Via). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 440 | Vias | Vias - alterar | 4 | Manutenção de dado de código (Via). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 441 | Vias | Vias - ativar ou inativar | 3 | Manutenção de dado de código (Via); ativar/inativar não muda a natureza (NOTA 3). CPM 4.3.1 Parte 1 §5.4.2 d (itens 1 e 5: entidade de substituição código-nome e entidade de valores válidos; NOTA 3: ativo/inativo e auditoria não mudam a natureza) e Parte 3 Cap. 1 (Dados de Código). Sem o ALI, as transações que só o mantêm ou consultam também não contam. |  |
| 460 | Renovação do TVL | Dados - Aceite de declaração de serviço | 7 | Teste de independência (Parte 3, Cap. 1; REGRAS: associativa com atributos não-chave é RLR). Migration 2026_09_28_170600_create_service_declaration_acceptances_table: associa solicitação (cascadeOnDelete) e declaração, com versão, usuário e data do aceite. A declaração (ServiceDeclaration) é texto quase estático carregado por ServiceDeclarationSeeder, sem manutenção na aplicação (dado de código). O aceite só tem sentido como parte do pedido de renovação; passa a RLR 'aceites de declaração de serviço' da Solicitação de viabilidade (4 DER incorporados). | Dados - Solicitação de viabilidade |
| 499 | Dados de negócio complementares | Dados - Execução de simulação REGIN | 7 | Model ReginSimulacaoExecucao e factory apagados no commit f6ad05ad; nenhuma classe em app/, resources/ ou tests/ lê ou grava a tabela. A tabela física regin_simulacao_execucoes ainda é criada pelas migrations 2026_09_16_190000 e 2026_09_17_140000 (não há migration de drop), mas sem nenhuma transação que a mantenha ou leia não é arquivo lógico reconhecido pelo usuário (CPM Parte 1 §5.4.2; Roteiro SISP: arquivos físicos não contam). |  |
| 502 | Dados de negócio complementares | Dados - Aceite de termo legal | 7 | Teste de independência (CPM Parte 1 §5.4.2 f e Parte 3 Cap. 2): legal_term_acceptances (migration database/migrations/2026_06_10_022241_create_legal_term_acceptances_table.php) é entidade associativa usuário x termo legal, com user_id e legal_term_id em cascadeOnDelete e unique(user_id, legal_term_id), e atributos não-chave ip_address, user_agent e accepted_at. Não existe sem a conta (User::termAcceptances hasMany) e não é mantida por processo próprio além do aceite do titular. Associativa com atributos não-chave é RLR, não ALI separado: passa a subgrupo de Usuário (TD e TR somados em Dados - Usuário). | Dados - Usuário |
| 507 | Dados de negócio complementares | Dados - Documento anexado à solicitação | 7 | Teste de independência (Parte 3, Cap. 1; REGRAS: 'anexos' são RLR do pai). Migration 2026_06_14_104833_create_viability_request_documents_table: viability_request_id com cascadeOnDelete; o documento só existe para cumprir um requisito dentro de uma solicitação e não tem significado de negócio sem ela. Ter processos próprios de anexar e remover não torna o grupo independente: essas EE mantêm o subgrupo. Passa a RLR 'documentos anexados' da Solicitação de viabilidade (7 DER incorporados). | Dados - Solicitação de viabilidade |
| 510 | Dados de negócio complementares | Dados - Versão de regras | 7 | Não é grupo lógico independente. rule_versions é cabeçalho genérico tipado pela coluna domain (app/Enums/RuleDomain.php); as linhas de cada domínio têm rule_version_id NOT NULL e, em quase todas, cascadeOnDelete (louos_quadro10_permissoes, louos_quadro11_condicoes_via, treatment_*, virtual_office_activity_cnaes, risk_classifications, risk_sanitary_classifications, risk_condicionantes; territorial_rules NOT NULL sem cascade). Pelo Método de (In)Dependência de Entidades (CPM 4.3.1 Parte 3 Cap. 2, Situação 1; Parte 1 §5.4.2 c) versão e conteúdo formam um só arquivo lógico, e a versão de um domínio não tem sentido de negócio fora dele. O cabeçalho virou RLR de cada arquivo versionado: Quadro 10, | Dados - Permissão de uso por zona (Quadro 10); Dados - Condição de instalação por via (Quadro 11A); Dados - Planilha de enquadramento; Dados - Regra territorial; Dados - Atividade permitida em escritório virtual |

Também foram descartadas na consolidação, sem linha na contagem anterior: "Parecer à JUCEB - alterar justificativa" (duplicava "Processos - alterar justificativa do parecer à JUCEB") e o AIE "Identidade do cidadão no gov.br" (sem transação contada que o leia, premissa P1).

## 5. Funções novas

| Grupo | Função | Tipo | TD | AR/TR | PF |
|---|---|---|---:|---:|---:|
| Perfis de acesso | Dados - Perfil de acesso | ALI | 3 | 1 | 7 |
| Usuários e solicitações de acesso | Solicitação de acesso - solicitar perfil | EE | 4 | 3 | 4 |
| Usuários e solicitações de acesso | Solicitação de acesso - consultar situação do pedido | CE | 6 | 2 | 4 |
| Usuários e solicitações de acesso | Solicitações de acesso - consultar pendentes | CE | 7 | 2 | 4 |
| Usuários e solicitações de acesso | Solicitações de acesso - aprovar | EE | 4 | 3 | 4 |
| Usuários e solicitações de acesso | Solicitações de acesso - recusar | EE | 4 | 2 | 3 |
| Cadastrar usuário pela gestão | Usuários - cadastrar usuário pela gestão (tela Novo usuário, incluída em 19/10/2026) | EE | 7 | 4 | 6 |
| Configuração das APIs | APIs - consultar configuração Cadastro municipal (SEMAP) | CE | 7 | 1 | 3 |
| Configuração das APIs | APIs - alterar configuração Cadastro municipal (SEMAP) | EE | 5 | 2 | 4 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - consultar rascunho | SE | 11 | 2 | 5 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - abrir rascunho | EE | 3 | 2 | 3 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - incluir linha no rascunho | EE | 6 | 2 | 4 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - alterar linha do rascunho | EE | 6 | 2 | 4 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - excluir linha do rascunho | EE | 3 | 2 | 3 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - importar CSV no rascunho | EE | 12 | 2 | 4 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - publicar rascunho | EE | 3 | 2 | 3 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - descartar rascunho | EE | 3 | 2 | 3 |
| Quadro 11A da LOUOS (condição de instalação por via) | Quadro 11A da LOUOS - ativar versão publicada | EE | 3 | 2 | 3 |
| Regras territoriais | Regras territoriais - ativar versão publicada | EE | 3 | 2 | 3 |
| Consulta de processos | Processos - alterar justificativa do parecer à JUCEB | EE | 3 | 3 | 4 |
| Registro em contingência | Contingência - consultar CNPJ na base da SEDUR | SE | 6 | 3 | 5 |
| Vistoria | Vistoria - abrir ficha de vistoria | EE | 3 | 3 | 4 |
| Resultados do fluxo expresso | Ficha do expresso - consultar decisão somente leitura | SE | 31 | 7 | 7 |
| Resultados do fluxo expresso | Processos - reprocessar etapas do motor | EE | 2 | 18 | 4 |
| Exportação de relatórios | Relatórios - exportar solicitações do recorte | SE | 16 | 6 | 7 |
| Exportação de relatórios | Relatórios - exportar tempo por etapa por processo | SE | 11 | 3 | 5 |
| Exportação de relatórios | Relatórios - exportar quedas do expresso | SE | 11 | 2 | 5 |
| Exportação de relatórios | Relatórios - exportar sedes de escritório virtual | SE | 12 | 4 | 7 |
| Benchmark nacional e sala de controle | Dados - Benchmark nacional de tempo de viabilidade | ALI | 18 | 3 | 7 |
| Benchmark nacional e sala de controle | Relatórios - consultar benchmark nacional | SE | 11 | 2 | 5 |
| Benchmark nacional e sala de controle | Sala de controle - consultar visão executiva | SE | 19 | 5 | 7 |
| Conta do cidadão no portal | Cadastro do cidadão - criar conta | EE | 8 | 6 | 6 |
| Conta do cidadão no portal | Portal - entrar no sistema | EE | 5 | 2 | 4 |
| Conta do cidadão no portal | Confirmação de e-mail - confirmar e-mail | EE | 3 | 2 | 3 |
| Histórico de acessos | Histórico de acessos - consultar os próprios acessos | CE | 5 | 1 | 3 |
| Painel do cidadão | Painel do cidadão - consultar | SE | 11 | 3 | 5 |
| Solicitação de viabilidade no portal | Solicitação - obter resumo para conferência | SE | 5 | 5 | 5 |
| Dados externos consultados pelo Viabiliza | Dados externos - Classificação viária dos logradouros do GeoServer da SEDUR | AIE | 3 | 1 | 5 |
| Dados externos consultados pelo Viabiliza | Dados externos - Lotes do endereçamento do GeoServer da SEDUR | AIE | 5 | 1 | 5 |
| Dados externos consultados pelo Viabiliza | Dados externos - Lotes por inscrição do SEMAP | AIE | 4 | 1 | 5 |
| Dados externos consultados pelo Viabiliza | Dados externos - Poligonais territoriais do GeoServer da SEDUR | AIE | 3 | 1 | 5 |
| Dados externos consultados pelo Viabiliza | Dados externos - Servidores e lotação do diretório da SEDUR | AIE | 7 | 1 | 5 |
| Dados externos consultados pelo Viabiliza | Dados externos - Endereços geocodificados do OpenStreetMap | AIE | 5 | 1 | 5 |
| Benchmark nacional de viabilidade | Benchmark - importar microdados mensais da REDESIM | EE | 12 | 2 | 4 |

## 6. Catálogo final de arquivos lógicos

| Tipo | Arquivo | TD | TR | Complexidade | PF |
|---|---|---:|---:|---|---:|
| ALI | Dados - CNAE | 15 | 1 | Baixa | 7 |
| ALI | Dados - Requisito documental | 7 | 1 | Baixa | 7 |
| ALI | Dados - Setor | 4 | 1 | Baixa | 7 |
| ALI | Dados - Perfil de acesso | 3 | 1 | Baixa | 7 |
| ALI | Dados - Usuário | 26 | 4 | Média | 10 |
| ALI | Dados - Feriado | 4 | 1 | Baixa | 7 |
| ALI | Dados - Tipo de imóvel | 5 | 1 | Baixa | 7 |
| ALI | Dados - Termo legal | 5 | 1 | Baixa | 7 |
| ALI | Dados - Texto-padrão | 4 | 1 | Baixa | 7 |
| ALI | Dados - Texto decisório | 3 | 1 | Baixa | 7 |
| ALI | Dados - Parâmetro | 15 | 2 | Baixa | 7 |
| ALI | Dados - Servidor de e-mail | 12 | 1 | Baixa | 7 |
| ALI | Dados - Provedor de inteligência artificial | 11 | 1 | Baixa | 7 |
| ALI | Dados - Valor da TLL | 10 | 1 | Baixa | 7 |
| ALI | Dados - Gatilho de risco | 5 | 1 | Baixa | 7 |
| ALI | Dados - Classificação de risco municipal | 5 | 1 | Baixa | 7 |
| ALI | Dados - Permissão de uso por zona (Quadro 10) | 15 | 2 | Baixa | 7 |
| ALI | Dados - Condição de instalação por via (Quadro 11A) | 13 | 2 | Baixa | 7 |
| ALI | Dados - Planilha de enquadramento | 52 | 10 | Alta | 15 |
| ALI | Dados - Regra territorial | 25 | 3 | Média | 10 |
| ALI | Dados - Atividade permitida em escritório virtual | 11 | 2 | Baixa | 7 |
| ALI | Dados - Bloqueio de inscrição de escritório virtual | 5 | 1 | Baixa | 7 |
| ALI | Dados - Camada do GeoServer | 5 | 1 | Baixa | 7 |
| ALI | Dados - Camada geográfica | 9 | 2 | Baixa | 7 |
| ALI | Dados - Encaminhamento à malha fina | 8 | 1 | Baixa | 7 |
| ALI | Dados - Atendimento presencial | 5 | 1 | Baixa | 7 |
| ALI | Dados - Ficha de análise técnica | 21 | 3 | Média | 10 |
| ALI | Dados - Sugestão de texto por IA | 12 | 1 | Baixa | 7 |
| ALI | Dados - Decisão de viabilidade | 21 | 3 | Média | 10 |
| ALI | Dados - Pendência da análise | 11 | 1 | Baixa | 7 |
| ALI | Dados - Ficha de vistoria | 79 | 3 | Alta | 15 |
| ALI | Dados - Benchmark nacional de tempo de viabilidade | 18 | 3 | Baixa | 7 |
| ALI | Dados - Trilha de auditoria | 15 | 1 | Baixa | 7 |
| ALI | Dados - Registro de acesso | 8 | 1 | Baixa | 7 |
| ALI | Dados - Chamada de integração | 16 | 1 | Baixa | 7 |
| ALI | Dados - Registro de envio de e-mail | 8 | 1 | Baixa | 7 |
| ALI | Dados - Alerta de abuso | 13 | 1 | Baixa | 7 |
| ALI | Dados - Anomalia preditiva | 12 | 1 | Baixa | 7 |
| ALI | Dados - Notificação ao usuário | 8 | 1 | Baixa | 7 |
| ALI | Dados - Comunicação ao requerente | 11 | 1 | Baixa | 7 |
| ALI | Dados - Empresa | 21 | 2 | Média | 10 |
| ALI | Dados - Vínculo de usuário com empresa | 6 | 1 | Baixa | 7 |
| ALI | Dados - Procuração | 6 | 1 | Baixa | 7 |
| ALI | Dados - Solicitação de viabilidade | 102 | 9 | Alta | 15 |
| ALI | Dados - Consulta prévia de viabilidade realizada | 8 | 1 | Baixa | 7 |
| AIE | Dados externos - Zonas urbanísticas do GeoServer da SEDUR | 5 | 1 | Baixa | 5 |
| AIE | Dados externos - Contato cadastral da SEDUR (CPF) | 2 | 1 | Baixa | 5 |
| AIE | Dados externos - Cadastro imobiliário da SEFAZ | 24 | 1 | Baixa | 5 |
| AIE | Dados externos - Contato cadastral da SEDUR (CNPJ) | 5 | 1 | Baixa | 5 |
| AIE | Dados externos - Classificação viária dos logradouros do GeoServer da SEDUR | 3 | 1 | Baixa | 5 |
| AIE | Dados externos - Lotes do endereçamento do GeoServer da SEDUR | 5 | 1 | Baixa | 5 |
| AIE | Dados externos - Lotes por inscrição do SEMAP | 4 | 1 | Baixa | 5 |
| AIE | Dados externos - Poligonais territoriais do GeoServer da SEDUR | 3 | 1 | Baixa | 5 |
| AIE | Dados externos - Servidores e lotação do diretório da SEDUR | 7 | 1 | Baixa | 5 |
| AIE | Dados externos - Endereços geocodificados do OpenStreetMap | 5 | 1 | Baixa | 5 |
| ALI | Dados - Recebimento do REGIN | 11 | 1 | Baixa | 7 |

## 7. Pendências e pontos para validação com o fiscal

1. Critério de log (P4): é a maior fonte de variação. Sem ele, a contagem daria 1584 PF (-135). Confirmar com o fiscal antes do aceite.
2. Documento de indeferimento: rota e teste existem, mas sem botão desde 22/09. Se a SEDUR ainda exigir o documento, o botão precisa voltar e entra uma SE de cerca de 7 PF.
3. Independência de Ficha de análise, Decisão, Ficha de vistoria e Pendência: também são excluídas em cascata com a Solicitação. Foram mantidas como ALI (ciclo e manutenção próprios). Aplicar o teste ao pé da letra reduziria a contagem; a decisão precisa ser uniforme.
4. Seletor de rascunhos da simulação LOUOS (linha 192): removido como lista auxiliar. Pelo exemplo "Drop-Down List Box" do CPM Parte 4 voltaria como CE Baixa (3 PF).
5. AIE de serviço público externo (geocodificação pelo OpenStreetMap) e lotes do SEMAP (pode exigir token ArcGIS em produção): validar aceitação como AIE e a demonstração.
6. Importação REDESIM de empresas (redesim:importar) roda só por linha de comando, sem agendamento: não contada; seria EE (Empresa, CNAE) se o contratante a reconhecer como carga do usuário.
7. Exportações por ?formato= sem botão na tela (usuários, perfis, CNAEs, parâmetros) e PUT /gestao/louos/publicar sem tela: não contadas; entram se forem expostas ao usuário.
8. Parâmetro features.portal_servicos (commit df19754c, 16/10/2026) desliga por padrão os serviços do portal (solicitação, renovação, empresas, procurações, representação); o gestor religa em Parâmetros. As funções continuam entregues e contadas, mas a demonstração exige o parâmetro ligado. Procurações saíram do menu lateral e seguem acessíveis pelo catálogo de serviços do portal.
9. Comentário de routes/console.php diz que pendencias:expirar não indefere; o código indefere. A contagem segue o código.
10. Observações da contagem anterior citavam o teste ApiIntegracaoControllerTest::test_cada_aba_grava_seus_parametros_e_audita, que não existe; quatro abas de APIs (Autenticação SEDUR, GOV.BR, Geocodificação e GeoServer) não têm teste de gravação próprio.
11. Registro de comunicação à SEFAZ (SefazNotification), gravado ao desvincular inscrição e ao concluir malha fina, não tem arquivo lógico no catálogo: o envio à SEFAZ está fora da fronteira (P1).
12. Identificador do documento (DV-SILE-003) foi mantido da contagem anterior para rastreabilidade.

## 8. Como a recontagem foi feita

1. Leitura da contagem anterior (328 funções, 1.618 PF) e do inventário do código: 303 rotas da aplicação, 17 rotas de autenticação do portal (Fortify), 87 models, 163 migrations, 38 comandos e as rotinas de routes/console.php.
2. Divisão em nove áreas funcionais (identidade e portal; solicitação; gestão de processos; análise e decisão; cadastros; configuração; LOUOS e território; relatórios e auditoria; integrações e rotinas). Em cada área, cada função anterior foi conferida no código (rota, controller, serviço, tela, migration e teste) e classificada como mantida, alterada ou removida, e as funções novas foram identificadas.
3. Harmonização entre áreas: catálogo único de arquivos lógicos, critério único para registros de log (P4), atribuição única de funções compartilhadas e substituição dos arquivos que viraram subgrupo.
4. Consolidação automática com conferências: cada linha anterior com um único destino, TD e AR do texto iguais aos das colunas, todo AR referindo arquivos do catálogo, todo ALI mantido por alguma transação e todo AIE lido por alguma transação.
5. Preenchimento da aba Funções sobre o modelo contagem-2391.xlsx, só nas colunas de entrada (C, H, I, J, K, Q), mantendo as fórmulas de A, B, L a P, e recálculo da planilha para conferir Sumário e Contagem.

## 9. Vínculo das funções às HUs (20/10/2026)

Sete funções (36 PF) já estavam contadas e nenhuma HU as descrevia. Em 20/10/2026 foram vinculadas, sem alterar o total de 1.719 PF nem a lista de funções:

| HU | Funções vinculadas |
|---|---|
| HU-011 Alertar falta de tabela TLL do exercício | TLL - alertar falta de tabela do exercício (SE, 5 PF) |
| HU-086 Desvincular inscrição de escritório virtual | Dados - Bloqueio de inscrição de escritório virtual (ALI, 7 PF); Escritório virtual - desvincular inscrição (EE, 4 PF) |
| HU-149 Consultar painel do cidadão | Painel do cidadão - consultar (SE, 5 PF) |
| HU-181 Consultar central de notificações | Dados - Notificação ao usuário (ALI, 7 PF); Notificações - marcar como lida (EE, 3 PF); Notificações - consultar (SE, 5 PF), já citada pelas HUs de aviso |
| HU-067 e HU-068 (contingência e atendimento presencial) | Dados externos - Contato cadastral da SEDUR (CPF) (AIE, 5 PF), lido pela consulta de CPF que essas HUs já citavam |

A HU-086 descreve uma rota da gestão sem botão em tela (status Parcial). Os números de HU deste documento são os de 20/10/2026.

## 10. Planilhas por parte (20/10/2026)

Além da planilha integral, há uma planilha para cada documento de HUs. Cada função foi contada uma só vez: a transação, na parte cujas HUs a citam (quando as duas citam, pela rota `/portal` ou `/gestao`), e o arquivo lógico, na parte que o mantém (`Dados - CNAE` fica na Retaguarda, por causa de Manter CNAEs).

| Planilha | HUs | Funções | PF |
|---|---|---:|---:|
| contagem-viabiliza-retaguarda-2026-10-16.xlsx | HU-001 a HU-139 | 278 | 1.413 |
| contagem-viabiliza-portal-2026-10-16.xlsx | HU-140 a HU-181 | 57 | 306 |
| Soma (igual à planilha integral) | HU-001 a HU-181 | 335 | 1.719 |

As funções de uma parte que usam arquivos da outra continuam referenciando esses arquivos em TD e AR. Os arquivos recontagem-funcoes-retaguarda.json e recontagem-funcoes-portal.json geram as duas planilhas sobre o modelo contagem-2391.xlsx.

