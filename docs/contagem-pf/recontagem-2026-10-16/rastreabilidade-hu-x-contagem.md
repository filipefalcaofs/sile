# Rastreabilidade HU x recontagem de PF — Viabiliza

Base: 187 HUs da versão 2.1 de 16/10/2026 (HUs-SILE-CONSOLIDADO.pdf, mesmo conteúdo de docs/VIABILIZA_HUs_Completas_MD) contra a recontagem de 16/10/2026 (1.713 PF, 334 funções). A matriz completa, HU a HU, está em rastreabilidade-hu-x-contagem.xlsx.

## Resultado

| Veredito | HUs | Leitura |
|---|---:|---|
| Coberta | 122 | O que a HU descreve está nas funções contadas |
| Sem função própria | 35 | Regra, cálculo ou efeito automático já contado dentro de outra função (motor da LOUOS, risco, decisão expressa, logs, e-mails) |
| Não implementada | 14 | HU Bloqueada ou Não evidenciada e nada contado (DAM, WhatsApp, BAP, SEFAZ, Receita, Protocolo Municipal, Portal do Contribuinte, migração, assistente do cidadão, Quadro 11, painel de saúde das integrações) |
| Excluída pela regra do CPM | 11 | Implementada, mas fora da contagem de propósito; validar com o fiscal |
| Inconsistência | 4 | HU e código/contagem dizem coisas diferentes |
| Coberta parcialmente | 1 | HU-052 |

Do lado da contagem, 327 das 334 funções são citadas por alguma HU. As 7 sem HU (36 PF) são funções implementadas que nenhuma HU descreve: central de notificações (dados e marcar como lida), painel do cidadão, desvinculação manual de escritório virtual (e o arquivo de bloqueio de inscrição), alerta de falta de tabela TLL do exercício e o dado externo de contato cadastral da SEDUR por CPF.

## Lacunas já corrigidas

- HU-171 Reprocessar processo: incluída "Processos - reprocessar etapas do motor" (EE Média, 4 PF).
- HU-116 Gerar resumo da solicitação: incluída "Solicitação - obter resumo para conferência" (SE Média, 5 PF).

Com isso a recontagem passou de 1.704 para 1.713 PF.

## Pontos que ainda podem mudar o total

| HU | Situação | Efeito estimado |
|---|---|---|
| HU-052 Consultar tabela de risco | Nível vigente só aparece na ficha do CNAE, tratada como abertura de formulário | CE, cerca de +3 a +4 PF se o fiscal exigir |
| HU-022 Importar dados da REDESIM | Comando só por linha de comando | EE se reconhecida como carga do usuário |
| HU-104 Integrar com Junta Comercial | Parecer à JUCEB tratado como efeito automático da decisão | SE se o fiscal entender como saída própria |
| HU-153, HU-165, HU-166 | Tipos de serviço, zonas e vias excluídos como dado de código | Voltariam cerca de 61 PF se o fiscal não aceitar a P2 |
| HU-151 | Login GOV.BR desligado e sem credencial | Entra quando ligado |
| HU-112 a HU-115, HU-121 | Serviços de IA sem tela que os dispare | Entram quando ligados a uma tela |

## HUs a corrigir (a contagem segue o código)

- HU-033: o lote já é identificado pelo ponto no endereçamento do GeoServer e pela inscrição no SEMAP; só a camada local de lotes segue bloqueada.
- HU-046 e HU-038: a planilha de tratamento publica sem quatro olhos; PUT /gestao/louos/publicar não tem tela.
- HU-135 e HU-085: "finalizar" isolado não tem botão desde 17/09; a finalização ocorre dentro da decisão.
- HU-147, HU-091 e HU-094: aviso de SLA e de convite expirado só no sistema, sem e-mail (commit f185ebb6).
- HU-085, HU-087 e HU-132: documento de indeferimento sem botão desde 22/09.
- HU-011 a HU-014: critério de aceite cita exportação que as telas não oferecem.
- HU-123 e HU-130: filtros descritos não existem nas telas (indicadores sem setor/analista; produtividade só por período).
- HU-148 e HU-150: não citam as consultas de CPF, CNPJ e inscrição na base da SEDUR, que existem e estão contadas.
- HU-054 a HU-056 e HU-058: commits 4c0e4d89 e 7ad6ee95 (16/10, 17:26 e 17:30) passaram a receber tipo de imóvel, categoria e vagas e a devolver confiabilidade; as HUs dizem o contrário.
- HU-139: complemento é texto livre dentro do imóvel; avaliar status Não evidenciada.

## Ajustes na contagem sem efeito no total

- Commits de 17:26 e 17:30 acrescentaram campos às três consultas prévias (tipo de imóvel, categoria, vagas, confiabilidade) e passaram a ler Tipo de imóvel e Gatilho de risco; as funções já são Alta.
- Protocolo e recebimento REGIN não listam Classificação de risco municipal, Gatilho de risco e Parâmetro, que a decisão expressa lê; já são Alta.
- "Território - identificar imóvel" e "Território - localizar endereço no mapa" não listam Chamada de integração; avaliar pela premissa P4.
- A observação das consultas prévias ainda cita o parâmetro features.consulta_viabilidade, retirado pela migration 2026_09_22_224506.
