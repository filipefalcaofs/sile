# HU-071 — Visualizar DAM da solicitação

> **Status: Escopo revisado (2026-06-11)** — para viabilidade via Regin, o DAM de TLL é **emitido e pago pela SEFAZ**, não pelo Simplifica. O SAPS legado possui aba "Visualizar DAM" no processo. O SILE deve **consultar/exibir** o DAM quando disponível na SEFAZ, não gerar guia de arrecadação para viabilidade via integrador.

## Épica
**EP08 — Solicitação de Viabilidade**

## Objetivo
Consultar e exibir o Documento de Arrecadação Municipal (DAM) da Taxa de Licença de Localização (TLL) vinculado ao processo, quando emitido pela SEFAZ.

## História de Usuário
**Como** analista ou gestor SEDUR,  
**quero** visualizar o DAM da solicitação,  
**para** acompanhar pagamento e situação tributária sem sair do processo.

## Contexto de Negócio
Conforme reunião SEDUR (2026-06-11), o DAM de viabilidade no fluxo Regin é tratado pela SEFAZ. O Simplifica/SAPS apenas exibe o DAM na aba do processo. Renovações e fluxos diretos pelo portal Simplifica podem ter regras distintas — confirmar com a SEDUR. A implementação de referência do SIGVISA (`sls-sms/docs/DAM-SEFAZ-IMPLEMENTACAO.md`) cobre consulta de pagamento via API SEFAZ, reutilizável aqui.

## Atores Envolvidos
- Cidadão / empresário / contador / procurador.
- Operador/gestor SEDUR (geração presencial, quando aplicável).
- Sistema SILE e serviços integrados (SEFAZ municipal).

## Pré-condições
- Solicitação de viabilidade protocolada.
- Atividades (CNAEs) informadas na solicitação.
- Tabela de valores da TLL parametrizada por exercício.
- Parametrização bancária do DAM configurada (identificação da rede, código de receita, produto/segmento, dias de vencimento).

## Fluxo Principal
1. Analista ou gestor acessa o detalhe do processo na retaguarda (aba equivalente a "Visualizar DAM" do SAPS).
2. O sistema consulta a API SEFAZ (HU-110 / serviço compartilhado) pelos identificadores do processo/estabelecimento.
3. O sistema exibe número, valor, vencimento, status (pendente/pago/cancelado) e link ou PDF quando disponível na SEFAZ.
4. O sistema registra a consulta em trilha de auditoria.

## Fluxos Alternativos
### FA-01 — Dados incompletos
1. O sistema identifica ausência de dados obrigatórios.
2. O sistema informa os campos pendentes.
3. O usuário complementa as informações.
4. O sistema permite nova tentativa.

### FA-02 — Tabela de valores indisponível
1. O sistema não encontra parametrização de valores da TLL para o exercício.
2. A geração é bloqueada.
3. O sistema registra a falha e notifica o administrador.

### FA-03 — Parametrização bancária ausente
1. O sistema não encontra a configuração do código de barras (rede, receita, produto/segmento).
2. A geração é bloqueada e o administrador notificado.

### FA-04 — Usuário sem permissão
1. O sistema identifica ausência de permissão.
2. O acesso é bloqueado.
3. A tentativa é registrada para auditoria.

## Regras de Negócio
- RN-001: O sistema deve validar dados obrigatórios antes de avançar o fluxo.
- RN-002: Toda ação relevante deve ser registrada em auditoria com usuário, data, hora e origem.
- RN-003: O usuário somente poderá executar a ação se possuir permissão compatível com seu perfil.
- RN-004: O valor do DAM deve ser calculado com base na atividade de maior valor correlacionado à TLL, acrescido da taxa de serviço, aplicando fator multiplicador quando o CNAE exigir.
- RN-005: O número do DAM deve ser sequencial e único por exercício, no formato `DAM-AAAA-NNNNNN`.
- RN-006: O código de barras deve seguir o padrão FEBRABAN de arrecadação (44 dígitos, DV módulo 10, simbologia ITF — Interleaved 2 of 5), com layout validado contra DAMs reais da SEFAZ Salvador (ver implementação de referência).
- RN-007: O DAM deve possuir data de vencimento parametrizável (padrão: data de geração + 5 dias); DAM vencido não pode ser pago.
- RN-008: Apenas DAMs pendentes podem ser cancelados, com motivo obrigatório.
- RN-009: O cálculo deve referenciar a versão vigente da tabela de valores, com rastreabilidade da versão utilizada.
- RN-010: O PDF deve exibir watermark e selo de status quando o DAM estiver pago ou cancelado.

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o usuário possui permissão e a solicitação está protocolada com atividades informadas,  
**Quando** executar a funcionalidade **Gerar DAM da solicitação**,  
**Então** o sistema deve gerar o DAM com valor correto, número sequencial, código de barras FEBRABAN válido, PDF disponível e notificação enviada.

### CA-02 — Código de barras válido
**Dado** que um DAM foi gerado,  
**Quando** o código de barras for validado (DV módulo 10 geral e por bloco),  
**Então** todos os dígitos verificadores devem estar corretos e a representação numérica deve ter 4 blocos de 11 dígitos + DV.

### CA-03 — Auditoria obrigatória
**Dado** que a funcionalidade foi executada,  
**Quando** houver geração, recálculo ou cancelamento de DAM,  
**Então** o sistema deve registrar usuário, data, hora, origem, ação executada e resultado.

### CA-04 — Bloqueio por inconsistência
**Dado** que a tabela de valores ou a parametrização bancária esteja ausente ou inconsistente,  
**Quando** o sistema não puder garantir o cálculo ou o código de barras correto,  
**Então** a geração do DAM deve ser bloqueada e o caso registrado para tratamento.

### CA-05 — Segurança de acesso
**Dado** que o usuário não possui permissão,  
**Quando** tentar acessar ou executar a funcionalidade,  
**Então** o sistema deve impedir a ação e registrar o evento.

## Campos/Dados Envolvidos
- Identificador da solicitação/processo e do estabelecimento.
- Exercício, número do DAM (único), valores (taxa, total), status (pendente/pago/cancelado), origem (portal/presencial).
- Data de vencimento, código de barras, representação numérica.
- Dados de cancelamento (data, motivo, usuário), quando aplicável.
- Parametrização bancária: identificação da rede, código de receita, tipo de lançamento, produto/segmento, valor/referência, dias de vencimento, mensagem do PDF.
- Logs, integrações e metadados de auditoria.

## Permissões
- **Cidadão/Contador/Procurador:** geração e download do DAM das próprias solicitações.
- **Operador/Gestor SEDUR:** geração presencial, cancelamento, consulta e exportação.
- **Administrador:** parametrização bancária e da tabela de valores.

## Exceções e Tratamentos
- Dados obrigatórios ausentes.
- Tabela de valores da TLL não parametrizada ou desatualizada para o exercício.
- Parametrização bancária ausente.
- Solicitação cancelada ou já encerrada.
- DAM duplicado para a mesma solicitação/exercício.

## Auditoria
O sistema deve manter registro completo da execução desta HU, incluindo:
- usuário ou serviço responsável;
- data e hora;
- dados de entrada;
- versão da tabela de valores aplicada;
- valor calculado, número do DAM e código de barras;
- erros ou exceções.

## Dependências
- HU-068 (Protocolar solicitação).
- Tabela de valores da TLL por exercício (parametrização).
- Parametrização bancária do DAM (tela de configuração).
- HU-072 (Confirmar pagamento do DAM).
- Módulo de auditoria e notificações (EP11).

## Prioridade
Média

## Observações
Escopo reduzido em 2026-06-11: **não gerar** DAM de viabilidade Regin no SILE. Geração completa de DAM (código FEBRABAN, PDF) permanece como referência técnica do SIGVISA caso a SEDUR exija geração apenas para fluxos diretos pelo portal (renovação etc.).
