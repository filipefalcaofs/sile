# HU-131 — Exportar relatórios e listagens

> **Escopo ampliado (2026-06-12)**: além dos relatórios do EP15, esta HU define o **padrão transversal de exportação** de toda tela com datatable na retaguarda de gestão.

## Épica
**EP15 — Relatórios e Indicadores**

## Objetivo
Exportar relatórios e qualquer listagem (datatable) da gestão em múltiplos formatos, respeitando filtros aplicados e permissões.

## História de Usuário
**Como** gestor/analista,  
**quero** exportar relatórios e listagens em diversos formatos,  
**para** apoiar prestação de contas, análises externas e rotinas administrativas.

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
1. Usuário ou sistema inicia a funcionalidade **Exportar relatórios**.
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
- RN-004: **Padrão transversal**: toda tela com datatable na retaguarda de gestão (processos, CNAEs, usuários, perfis, parâmetros, condicionantes, enquadramentos, setores, feriados, auditoria, alertas, relatórios etc.) deve oferecer ação de exportação nos formatos **CSV, XLSX e PDF**.
- RN-005: A exportação reflete exatamente o **conjunto filtrado vigente** (busca, filtros, ordenação aplicados) — todas as linhas do resultado, não apenas a página exibida.
- RN-006: Exportações acima de limiar parametrizável (HU-014) são processadas de forma **assíncrona** (job em fila) com notificação/download quando prontas — a tela nunca trava nem estoura timeout.
- RN-007: A exportação respeita as permissões do usuário (exporta somente o que pode ver) e minimiza dados pessoais conforme LGPD; colunas sensíveis exigem permissão específica.
- RN-008: Toda exportação é **auditada**: usuário, data/hora, tela de origem, filtros aplicados, formato e volume de linhas (relevante para LGPD em órgão público).
- RN-009: O componente de exportação é único e reutilizável (frontend e backend) — novas listagens o herdam sem reimplementação; implementação de referência: `maatwebsite/excel` (CSV/XLSX) e `dompdf` (PDF), já validados no SIGVISA.
- RN-010: O PDF exportado deve exibir, ao final do documento, o **total de registros** ("Total de registros: N"), junto com data/hora de geração e filtros aplicados; CSV e XLSX não recebem linha de total no corpo dos dados (preservar integridade tabular para importação) — o total vai em metadados/aba própria no XLSX quando aplicável.

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o usuário possui permissão e informou os dados obrigatórios,  
**Quando** executar a funcionalidade **Exportar relatórios**,  
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

### CA-05 — Exportação do conjunto filtrado
**Dado** uma listagem da gestão com busca, filtros e ordenação aplicados,  
**Quando** o usuário exportar em CSV, XLSX ou PDF,  
**Então** o arquivo deve conter todas as linhas do resultado filtrado, na ordenação vigente, e a exportação deve ser auditada.

### CA-06 — Grande volume assíncrono
**Dado** um resultado acima do limiar parametrizado,  
**Quando** o usuário exportar,  
**Então** o processamento deve ocorrer em background e o arquivo ser disponibilizado ao concluir, sem travar a tela.

### CA-07 — Total de registros no PDF
**Dado** uma listagem com N registros no conjunto filtrado,  
**Quando** o usuário exportar em PDF,  
**Então** o final do documento deve exibir "Total de registros: N", coerente com as linhas exportadas, além de data/hora de geração e filtros aplicados.

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
O padrão transversal (RN-004 a RN-009) vale para todas as fases: telas novas nascem com exportação (critério de pronto no ROADMAP); listagens já entregues nas Fases 1–2 (CNAEs, usuários, perfis, parâmetros, acessos) recebem retrofit quando o componente compartilhado for construído. A HU-101 (exportar auditoria) segue como caso específico e adota o mesmo componente.
