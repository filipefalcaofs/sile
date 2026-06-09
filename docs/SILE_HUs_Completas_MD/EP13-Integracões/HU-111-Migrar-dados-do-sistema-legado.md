# HU-111 — Migrar dados do sistema legado

> **Status: Proposta** — o sistema atual (.NET) apresenta instabilidade recorrente; a estratégia de migração/convivência precisa ser definida com a SEDUR.

## Épica
**EP13 — Integrações**

## Objetivo
Migrar para o SILE os dados relevantes do sistema legado (processos, viabilidades emitidas, cadastros) e definir a estratégia de convivência durante a transição.

## História de Usuário
**Como** gestor SEDUR,  
**quero** migrar os dados do sistema atual para o SILE,  
**para** preservar o histórico de processos e permitir a desativação do legado sem perda de informação.

## Contexto de Negócio
O sistema atual de viabilidade da SEDUR, desenvolvido em .NET, apresenta instabilidade recorrente e ficou indisponível por dias seguidos. A equipe da SEDUR chegou a criar um proxy para viabilizar o consumo de serviços. A substituição pelo SILE exige decidir o que migrar (histórico de processos, TVLs emitidos, cadastros, parametrizações) e como operar durante a transição.

## Atores Envolvidos
- Gestor SEDUR e administrador do sistema.
- Equipe técnica responsável pelo legado.
- Sistema SILE.

## Pré-condições
- Acesso à base de dados ou a serviços de exportação do sistema legado.
- Mapeamento de-para entre os modelos de dados do legado e do SILE.
- Estratégia de transição aprovada pela SEDUR.

## Fluxo Principal
1. O administrador inicia a carga de migração (total ou incremental).
2. O sistema extrai os dados do legado conforme o mapeamento definido.
3. O sistema valida, transforma e normaliza os dados.
4. O sistema importa os dados, registrando origem legada em cada registro.
5. O sistema gera relatório de conciliação (totais, sucessos, rejeições).
6. O sistema registra a operação em trilha de auditoria.

## Fluxos Alternativos
### FA-01 — Registro inválido ou inconsistente
1. O sistema identifica registro que viola as validações do SILE.
2. O registro é rejeitado e listado no relatório de conciliação com o motivo.
3. A carga prossegue para os demais registros.

### FA-02 — Fonte legada indisponível
1. A base ou serviço do legado fica indisponível.
2. O sistema registra a falha e permite retomada do ponto de parada.

### FA-03 — Carga incremental
1. Nova execução identifica registros já migrados.
2. Registros existentes são ignorados ou atualizados conforme política definida, sem duplicação.

## Regras de Negócio
- RN-001: Nenhum dado migrado pode ser alterado silenciosamente — transformações devem estar documentadas no mapeamento de-para.
- RN-002: Toda carga deve gerar relatório de conciliação com totais de origem, importados e rejeitados.
- RN-003: Registros migrados devem manter referência ao identificador de origem no legado.
- RN-004: A migração deve ser idempotente — reexecuções não podem duplicar registros.
- RN-005: Dados pessoais migrados devem respeitar a LGPD (minimização e base legal).

## Critérios de Aceite — BDD

### CA-01 — Carga com sucesso
**Dado** que o mapeamento de-para está definido e a fonte legada acessível,  
**Quando** a carga for executada,  
**Então** os dados válidos devem ser importados com referência à origem e o relatório de conciliação gerado.

### CA-02 — Conciliação obrigatória
**Dado** que uma carga foi executada,  
**Quando** houver registros rejeitados,  
**Então** cada rejeição deve constar no relatório com identificador de origem e motivo.

### CA-03 — Idempotência
**Dado** que uma carga já foi executada,  
**Quando** for reexecutada,  
**Então** não pode haver duplicação de registros.

### CA-04 — Auditoria obrigatória
**Dado** que houve execução de carga,  
**Quando** a operação concluir (sucesso ou falha),  
**Então** o sistema deve registrar responsável, data, hora, parâmetros, totais e resultado.

## Campos/Dados Envolvidos
- Processos e solicitações históricas, com status e resultados.
- TVLs/viabilidades emitidas.
- Cadastros de empresas e vínculos.
- Parametrizações reaproveitáveis (CNAEs, condicionantes, quadros), quando aplicável.
- Identificadores de origem, logs e metadados de auditoria.

## Permissões
- **Administrador:** execução e configuração das cargas.
- **Gestor SEDUR:** consulta dos relatórios de conciliação.

## Exceções e Tratamentos
- Fonte legada indisponível ou com esquema divergente do mapeado.
- Registros órfãos ou com integridade referencial quebrada.
- Volumes acima do previsto (execução em lotes).

## Auditoria
O sistema deve manter registro completo da execução desta HU, incluindo:
- responsável pela execução;
- data e hora;
- parâmetros da carga;
- totais de origem, importados e rejeitados;
- erros ou exceções.

## Dependências
- Acesso à base/serviços do sistema legado (definir com a SEDUR).
- Modelos de dados do SILE consolidados (EP01, EP03, EP08).
- Módulo de auditoria.

## Prioridade
A definir (depende da estratégia de transição com a SEDUR)

## Observações
Definir com a SEDUR: escopo da migração (histórico completo ou apenas processos ativos), janela de transição, papel do proxy existente e data de desligamento do legado.

Implementação de referência no projeto SIGVISA (`sls-sms`), que executou migração real de sistema legado (PGLS, incluindo 146 mil DAMs): framework de importação em `app/Services/CsvImport/` com mappers registráveis por entidade, normalização de dados, detecção de duplicatas com exceções tipadas, resolução de upsert, rollback de lote, contadores e registro estruturado de erros (`ImportBatch`, `ImportRecord`, `ImportError`), além de serviços de deduplicação pós-carga e documentação da metodologia em `docs/LEVANTAMENTO-DADOS-MIGRACAO.html`.
