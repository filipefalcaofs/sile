# Rastreabilidade HU x recontagem de PF — Viabiliza

Base: as 181 HUs da versão 2.2 (19 e 20/10/2026) (docs/requisitos/hus e docs/VIABILIZA_HUs_Completas_MD), depois de retirar 11 HUs que o sistema não oferece, criar a HU-004 (Cadastrar usuário pela gestão), reordenar as demais (primeiro a retaguarda, depois o portal, por funcionalidade) e criar em 20/10/2026 as HUs 011, 086, 149 e 181, contra a recontagem de 16/10/2026 (1.719 PF, 335 funções). Os números de HU deste arquivo são os atuais; a matriz traz também o número da manhã de 19/10/2026 (usado nos comentários do código) e o de até 18/10/2026. O de-para completo está em docs/requisitos/hus/mapa-renumeracao-hus.md. A matriz completa, HU a HU, está em rastreabilidade-hu-x-contagem.xlsx.

## Resultado

| Veredito | HUs | Leitura |
|---|---:|---|
| Coberta | 127 | O que a HU descreve está nas funções contadas |
| Sem função própria | 34 | Regra, cálculo ou efeito automático já contado dentro de outra função (motor da LOUOS, risco, decisão expressa, logs, e-mails) |
| Não implementada | 4 | HU Bloqueada e nada contado (WhatsApp, SEFAZ, vínculo do protocolo BAP e indeferimento por prazo BAP) |
| Excluída pela regra do CPM | 11 | Implementada, mas fora da contagem de propósito; validar com o fiscal |
| Inconsistência | 4 | HU e código/contagem dizem coisas diferentes |
| Coberta parcialmente | 1 | HU-058 |

Do lado da contagem, as 335 funções são citadas por alguma HU. Em 20/10/2026, as 7 funções que nenhuma HU descrevia (36 PF) foram vinculadas: o alerta de tabela TLL (HU-011), a desvinculação de escritório virtual e seu bloqueio de inscrição (HU-086, sem botão em tela), o painel do cidadão (HU-149), a central de notificações (HU-181) e o contato cadastral da SEDUR por CPF, citado pelas HUs de contingência e de atendimento presencial (HU-067 e HU-068).

## Lacunas já corrigidas

- HU-084 Reprocessar processo: incluída "Processos - reprocessar etapas do motor" (EE Média, 4 PF).
- HU-179 Gerar resumo da solicitação: incluída "Solicitação - obter resumo para conferência" (SE Média, 5 PF).

Com isso a recontagem passou de 1.704 para 1.713 PF. Em 19/10/2026 entrou também "Usuários - cadastrar usuário pela gestão" (EE Alta, 6 PF), da tela Novo usuário da gestão, coberta pela HU-004 (criada nessa data, separada de Manter usuários), e o total passou a 1.719 PF.

## Pontos que ainda podem mudar o total

| HU | Situação | Efeito estimado |
|---|---|---|
| HU-058 Consultar tabela de risco | Nível vigente só aparece na ficha do CNAE, tratada como abertura de formulário | CE, cerca de +3 a +4 PF se o fiscal exigir |
| HU-105 Importar dados da REDESIM | Comando só por linha de comando | EE se reconhecida como carga do usuário |
| HU-106 Integrar com Junta Comercial | Parecer à JUCEB tratado como efeito automático da decisão | SE se o fiscal entender como saída própria |
| HU-020, HU-023, HU-024 | Tipos de serviço, zonas e vias excluídos como dado de código | Voltariam cerca de 61 PF se o fiscal não aceitar a P2 |
| HU-142 | Login GOV.BR desligado e sem credencial | Entra quando ligado |
| HU-111 a HU-114 e HU-117 | Serviços de IA sem tela que os dispare | Entram quando ligados a uma tela |

## HUs a corrigir (a contagem segue o código)

- HU-042: o lote já é identificado pelo ponto no endereçamento do GeoServer e pela inscrição no SEMAP; só a camada local de lotes segue bloqueada.
- HU-032 e HU-046: a planilha de tratamento publica sem quatro olhos; PUT /gestao/louos/publicar não tem tela.
- HU-074 e HU-078: "finalizar" isolado não tem botão desde 17/09; a finalização ocorre dentro da decisão.
- HU-091, HU-177 e HU-092: aviso de SLA e de convite expirado só no sistema, sem e-mail (commit f185ebb6).
- HU-078, HU-080 e HU-083: documento de indeferimento sem botão desde 22/09.
- HU-034, HU-005, HU-006 e HU-008: critério de aceite cita exportação que as telas não oferecem.
- HU-119 e HU-126: filtros descritos não existem nas telas (indicadores sem setor/analista; produtividade só por período).
- HU-067 e HU-068: não citam as consultas de CPF, CNPJ e inscrição na base da SEDUR, que existem e estão contadas.
- HU-157 a HU-159 e HU-161: commits 4c0e4d89 e 7ad6ee95 (16/10, 17:26 e 17:30) passaram a receber tipo de imóvel, categoria e vagas e a devolver confiabilidade; as HUs dizem o contrário.

## Ajustes na contagem sem efeito no total

- Commits de 17:26 e 17:30 acrescentaram campos às três consultas prévias (tipo de imóvel, categoria, vagas, confiabilidade) e passaram a ler Tipo de imóvel e Gatilho de risco; as funções já são Alta.
- Protocolo e recebimento REGIN não listam Classificação de risco municipal, Gatilho de risco e Parâmetro, que a decisão expressa lê; já são Alta.
- "Território - identificar imóvel" e "Território - localizar endereço no mapa" não listam Chamada de integração; avaliar pela premissa P4.
- A observação das consultas prévias ainda cita o parâmetro features.consulta_viabilidade, retirado pela migration 2026_09_22_224506.

## HUs retiradas em 19/10/2026

Saíram 11 HUs que o sistema não oferece. Os números abaixo são os anteriores à renumeração; o de-para completo está em docs/requisitos/hus/mapa-renumeracao-hus.md.

| HU anterior | Motivo |
|---|---|
| HU-017 | O sistema não oferece manutenção do Quadro 11 (só 10 e 11A). |
| HU-040 | O motor não aplica um Quadro 11 distinto do 11A. |
| HU-071 | A visualização do DAM não é oferecida. |
| HU-072 | A confirmação de pagamento do DAM não é oferecida. |
| HU-105 | A consulta à Receita Federal não é oferecida. |
| HU-108 | A integração com o protocolo municipal não é oferecida. |
| HU-109 | A integração com o Portal do Contribuinte não é oferecida. |
| HU-111 | A migração do sistema legado não é oferecida. |
| HU-120 | O assistente virtual do cidadão não é oferecido. |
| HU-139 | Não há cadastro de edifícios; o complemento é texto livre. |
| HU-146 | O painel de saúde das integrações não é oferecido. |
