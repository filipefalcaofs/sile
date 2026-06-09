# HU-116 — Gerar resumo da solicitação

## Épica
**EP14 — Inteligência Artificial**

## Objetivo
Gerar resumo para revisão do cidadão.

## História de Usuário
**Como** IA,  
**quero** resumir solicitação,  
**para** facilitar conferência antes do protocolo.

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
1. Usuário ou sistema inicia a funcionalidade **Gerar resumo da solicitação**.
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

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o usuário possui permissão e informou os dados obrigatórios,  
**Quando** executar a funcionalidade **Gerar resumo da solicitação**,  
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
Esta HU deverá ser refinada com a equipe da SEDUR quando forem disponibilizadas as tabelas oficiais, planilhas, parâmetros da LOUOS, regras de risco e integrações existentes.
