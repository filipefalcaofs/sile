# HU-103 — Integrar com REDESIM/Regin

> **Status: Refinada (2026-06-11)** — Integrador estadual da Bahia é o **Regin** (JUCEB/Prosolution), conforme Resolução CGSIM nº 61/2020. O formulário municipal é hospedado pelo Simplifica (`/integracao/sedur/TVL/ps001_Regin.aspx`). Especificação técnica do webservice **não é pública** — dependência externa bloqueante até contrato SEDUR/JUCEB (ver `docs/ANALISE-HUs-REUNIAO-SEDUR.md` seção 7.8).

## Épica
**EP13 — Integrações**

## Objetivo
Receber solicitações de viabilidade do Regin, hospedar o formulário complementar da SEDUR, vincular protocolo BAP ao processo e devolver parecer ao integrador.

## História de Usuário
**Como** sistema,  
**quero** integrar com REDESIM/Regin,  
**para** manter o fluxo nacional/municipal conectado de ponta a ponta.

## Contexto de Negócio
O SILE deverá apoiar a SEDUR na gestão da viabilidade locacional de atividades econômicas, priorizando automação, precisão, rastreabilidade e redução de análise manual. Esta HU faz parte do fluxo de Portal do Cidadão, Retaguarda SEDUR, Motor de Regras da LOUOS, integrações, auditoria ou indicadores, conforme sua épica.

## Atores Envolvidos
- Cidadão / empresário / contador / procurador, quando aplicável.
- Servidor ou analista da SEDUR, quando aplicável.
- Administrador do sistema, quando aplicável.
- Sistema SILE e serviços integrados.

## Pré-condições
- Usuário autenticado quando a funcionalidade exigir identificação.
- Perfil com permissão compatível.
- Parâmetros e cadastros básicos disponíveis.
- Regras, integrações ou bases oficiais configuradas quando aplicável.

## Fluxo Principal
1. O Regin redireciona o requerente ao formulário da SEDUR no Simplifica (token de sessão), equivalente ao `ps001_Regin.aspx`.
2. O requerente preenche dados complementares (imóvel, polígono, CNAEs, condicionantes, anexos) — ver EP08.
3. O sistema gera o **número de processo SEDUR** e mantém o processo aguardando **protocolo BAP** (gerado na Junta após termo de responsabilidade).
4. O Regin/Junta envia o BAP ao Simplifica; o sistema vincula BAP ↔ processo (HU-133).
5. Após vinculação, o motor executa fluxo expresso ou encaminha à análise técnica.
6. Ao concluir, o sistema devolve parecer (deferido/indeferido) ao Regin conforme contrato (HU-104).
7. Toda troca de mensagens registra payload, protocolo externo, status e erros em auditoria.

## Fluxos Alternativos
### FA-01 — Dados incompletos
1. O sistema identifica ausência de dados obrigatórios.
2. O sistema informa os campos pendentes.
3. O usuário complementa as informações.
4. O sistema permite nova tentativa.

### FA-02 — Regra não encontrada
1. O sistema não encontra regra parametrizada suficiente.
2. O processo não deve ser decidido automaticamente.
3. O sistema encaminha para análise técnica, quando aplicável.

### FA-03 — Falha de integração
1. Serviço externo fica indisponível ou retorna erro.
2. O sistema registra a falha.
3. O sistema permite retentativa, processamento posterior ou encaminhamento para análise.

### FA-04 — Usuário sem permissão
1. O sistema identifica ausência de permissão.
2. O acesso é bloqueado.
3. A tentativa é registrada para auditoria.

## Regras de Negócio
- RN-001: O sistema deve validar dados obrigatórios antes de avançar o fluxo.
- RN-002: Toda ação relevante deve ser registrada em auditoria com usuário, data, hora e origem.
- RN-003: O usuário somente poderá executar a ação se possuir permissão compatível com seu perfil.
- RN-004: Integrações devem registrar payload, status, protocolo externo (BAP, id Regin) e erros de comunicação.
- RN-005: Falha de integração não deve gerar decisão inconsistente; deve permitir retentativa ou análise técnica.
- RN-006: O sistema deve tratar indisponibilidade do serviço externo com fila de retentativa e pendência operacional visível.
- RN-007: Processo sem BAP vinculado dentro do prazo parametrizado (padrão 48h) deve ser indeferido automaticamente por "sem atuação" (HU-134).
- RN-008: Renovação de TVL e fluxos diretos pelo portal Simplifica **não** passam pelo Regin — rotas distintas (ver HU-061).
- RN-009: **Recepção durável**: mensagens recebidas do Regin entram em fila durável com confirmação (ack) somente após persistência; falha de processamento envia para dead-letter com replay manual/automático — indisponibilidade momentânea do SILE não pode perder solicitação (causa raiz do "mensagem que não chega" do legado).
- RN-010: **Proteção do endpoint público**: a página/endpoint do formulário (equivalente ao `ps001_Regin`) deve validar token de sessão emitido no fluxo Regin, aplicar rate limiting e proteção anti-bot — é superfície exposta na internet.

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o usuário possui permissão e informou os dados obrigatórios,  
**Quando** executar a funcionalidade **Integrar com REDESIM**,  
**Então** o sistema deve processar a solicitação e apresentar ou registrar o resultado esperado.

### CA-02 — Auditoria obrigatória
**Dado** que a funcionalidade foi executada,  
**Quando** houver alteração, decisão, consulta relevante ou integração,  
**Então** o sistema deve registrar usuário, data, hora, origem, ação executada e resultado.

### CA-03 — Bloqueio por inconsistência
**Dado** que existam dados inconsistentes, regra ausente, conflito ou falha crítica,  
**Quando** o sistema não puder garantir precisão,  
**Então** a decisão automática deve ser bloqueada e o caso deve seguir para tratamento adequado.

### CA-04 — Segurança de acesso
**Dado** que o usuário não possui permissão,  
**Quando** tentar acessar ou executar a funcionalidade,  
**Então** o sistema deve impedir a ação e registrar o evento.

## Campos/Dados Envolvidos
- Identificador da solicitação/processo.
- Identificação do usuário responsável.
- Dados da empresa, CNPJ, CNAE e atividade econômica, quando aplicável.
- Dados do imóvel, endereço, inscrição imobiliária, zona, via e área ocupada, quando aplicável.
- Resultado, status, parecer, condicionantes e fundamentação legal, quando aplicável.
- Logs, integrações e metadados de auditoria.

## Permissões
- **Cidadão/Contador/Procurador:** acesso às próprias empresas e solicitações.
- **Analista SEDUR:** consulta e análise dos processos atribuídos ou disponíveis.
- **Gestor SEDUR:** acompanhamento, distribuição, parametrização e relatórios.
- **Administrador:** configuração de regras, usuários, perfis e parâmetros.

## Exceções e Tratamentos
- Dados obrigatórios ausentes.
- Endereço não localizado.
- CNAE inexistente ou desatualizado.
- Área ocupada inválida.
- Regra da LOUOS não parametrizada.
- Conflito entre regras.
- Serviço externo indisponível.
- Documento ilegível ou incompatível, quando aplicável.
- Processo duplicado ou já encerrado.

## Auditoria
O sistema deve manter registro completo da execução desta HU, incluindo:
- usuário ou serviço responsável;
- data e hora;
- dados de entrada;
- regras aplicadas;
- resultado;
- erros ou exceções;
- versão das regras, quando aplicável.

## Dependências
- Cadastro de usuários e perfis.
- Cadastro empresarial e CNAEs.
- Motor de regras da LOUOS.
- Base geográfica/GIS.
- Classificação de risco.
- Integrações externas, quando aplicável.
- Módulo de auditoria.

## Prioridade
Alta

## Observações
Passo a passo oficial arquivado em `docs/dados-oficiais/Passo-a-passo-REDESIM-SEDUR-CRCBA-13072021.pdf`. **Bloqueio**: contrato técnico Regin↔SAPS em produção — solicitar à SEDUR/JUCEB antes de implementar adaptador.
