# HU-014 — Manter parâmetros do sistema

## Épica
**EP02 — Administração**

## Objetivo
Gerenciar parâmetros gerais e funcionalidades ativáveis do sistema, de forma que o administrador ajuste o comportamento do SILE sem depender de desenvolvedor ou de novo deploy.

## História de Usuário
**Como** administrador,  
**quero** manter parâmetros e ativar/desativar funcionalidades,  
**para** ajustar o comportamento do sistema sem intervenção técnica.

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
1. Usuário ou sistema inicia a funcionalidade **Manter parâmetros do sistema**.
2. O sistema valida permissões, dados obrigatórios e contexto do processo.
3. O sistema executa as validações e regras relacionadas à funcionalidade.
4. Quando aplicável, o sistema consulta bases internas, motor de regras, GIS, REDESIM ou demais integrações.
5. O sistema apresenta o resultado ao usuário ou atualiza o processo automaticamente.
6. O sistema registra a operação em trilha de auditoria.

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
- RN-004: Nenhum valor de negócio passível de mudança (limiares, prazos, taxas, textos, mensagens, e-mails, termos) pode ser fixo em código — todos devem ser parâmetros administráveis por interface.
- RN-005: Funcionalidades acopláveis (integrações externas, fluxo expresso, validação por IA, canais de notificação como e-mail e WhatsApp, aprovação automática) devem possuir chave de ativação/desativação (feature toggle) administrável por interface.
- RN-006: Alterações de parâmetros e toggles devem ter efeito sem novo deploy, respeitando no máximo o tempo de expiração de cache definido.
- RN-007: Cada parâmetro deve ter tipo, descrição, valor padrão e regras de validação (faixa, formato, obrigatoriedade); valores inválidos devem ser rejeitados na gravação.
- RN-008: Alterações de parâmetros e toggles devem ser versionadas e auditadas (valor anterior, valor novo, responsável, data/hora), com possibilidade de consulta ao histórico.
- RN-009: Parâmetros sensíveis (credenciais, senhas, tokens de integração) devem ser armazenados criptografados e nunca exibidos em texto claro após gravados.
- RN-010: Telas de parametrização de integrações devem oferecer ação de teste de conexão para o administrador validar a configuração sem acionar o fluxo real.
- RN-011: A desativação de uma funcionalidade deve degradar o comportamento de forma controlada e comunicada (ex.: integração desativada gera pendência operacional, não erro silencioso).
- RN-012: Calendário de **feriados** municipais/nacionais deve ser administrável (HU-137) e impactar contagem de prazos operacionais.
- RN-013: Regras de **contagem de prazo** (dias úteis vs corridos; inclusão/exclusão de fins de semana e feriados) devem ser parametrizáveis — problema identificado no SAPS legado (prazos inflados).
- RN-014: Parâmetros de **indeferimento automático** (ex.: prazo sem BAP vinculado, padrão 48h) devem ser administráveis, conforme tela "Parâmetros para indeferimento automático" do SAPS.

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o usuário possui permissão e informou os dados obrigatórios,  
**Quando** executar a funcionalidade **Manter parâmetros do sistema**,  
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

### CA-05 — Efeito sem deploy
**Dado** que o administrador alterou um parâmetro ou toggle válido,  
**Quando** a alteração for gravada,  
**Então** o novo comportamento deve valer na aplicação sem novo deploy, dentro do tempo de expiração de cache definido.

### CA-06 — Desativação controlada
**Dado** que uma funcionalidade foi desativada por toggle,  
**Quando** um fluxo que dela depende for executado,  
**Então** o sistema deve seguir o comportamento degradado definido (pendência, aviso ou bloqueio explícito), sem falha silenciosa.

### CA-07 — Histórico de alterações
**Dado** que parâmetros foram alterados ao longo do tempo,  
**Quando** o administrador consultar o histórico,  
**Então** o sistema deve exibir valor anterior, valor novo, responsável e data/hora de cada alteração.

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
Esta HU deverá ser refinada com a equipe da SEDUR quando forem disponibilizadas as tabelas oficiais, planilhas, parâmetros da LOUOS, regras de risco e integrações existentes.

Exemplos de domínios parametrizáveis no SILE: prazos (vencimento de DAM, resposta de pendência), valores e tabelas de taxa por exercício, textos de e-mails e notificações, termos LGPD, credenciais e URLs de integrações (SEFAZ, REDESIM, GIS), limiares do fluxo expresso, configuração de IA (modelo, prompts, ativação) e canais de comunicação. Modelo de referência no projeto SIGVISA (`sls-sms`): entidades de configuração segregadas por domínio (`ConfigDam`, `ConfigEmail`, `ConfigIa`, `ConfigPortal`, `ConfigGovBr`, `ConfigAprovacaoAutomatica`, `ConfigRt`), cada uma com tela própria de administração, credenciais criptografadas e botões de teste de conexão.
