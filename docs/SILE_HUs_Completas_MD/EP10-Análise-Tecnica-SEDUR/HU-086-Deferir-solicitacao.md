# HU-086 — Deferir solicitação

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Permitir deferimento manual.

## História de Usuário
**Como** analista,  
**quero** deferir solicitação,  
**para** aprovar viabilidade.

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
1. Analista finaliza ficha de análise (HU-135) com todas as atividades deferidas.
2. O sistema valida permissões e consistência da decisão.
3. O sistema registra deferimento, fundamentação e número de produto (TVL).
4. O sistema comunica parecer ao Regin (HU-104) e envia dados à SEFAZ (HU-110).
5. O sistema **não** entrega PDF/TVL ao requerente; analista pode emitir relatório PDF no backoffice (HU-132).
6. O sistema registra operação em trilha de auditoria.

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
- RN-004: Deferimento exige **todas** as atividades (CNAEs) deferidas na ficha de análise.
- RN-005: Após deferimento, integrações Regin (HU-104) e SEFAZ (HU-110) são disparadas automaticamente, independentemente da emissão de PDF no backoffice.

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o usuário possui permissão e informou os dados obrigatórios,  
**Quando** executar a funcionalidade **Deferir solicitação**,  
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
