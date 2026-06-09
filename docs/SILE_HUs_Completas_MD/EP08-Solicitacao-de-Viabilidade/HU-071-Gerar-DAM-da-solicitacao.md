# HU-071 — Gerar DAM da solicitação

> **Status: Proposta** — escopo pendente de confirmação com a SEDUR (a geração do DAM pode permanecer no Simplifica/SEFAZ). Especificação técnica baseada na implementação de referência do projeto SIGVISA (sls-sms), que possui módulo DAM completo e operacional com a SEFAZ Salvador (`sls-sms/docs/DAM-SEFAZ-IMPLEMENTACAO.md`).

## Épica
**EP08 — Solicitação de Viabilidade**

## Objetivo
Gerar o Documento de Arrecadação Municipal (DAM) referente à Taxa de Licença de Localização (TLL) da solicitação de viabilidade, com número sequencial, código de barras padrão FEBRABAN e PDF para pagamento na rede bancária.

## História de Usuário
**Como** cidadão/requerente,  
**quero** gerar o DAM da minha solicitação,  
**para** efetuar o pagamento e dar andamento à emissão do TVL.

## Contexto de Negócio
No fluxo atual do Portal Simplifica, o TVL só é liberado após o pagamento do DAM, cobrado com base na atividade de maior valor correlacionado à TLL mais taxa de serviço, conforme o Código Tributário e de Rendas do Município (Lei nº 7.186/2006, alterada pela Lei nº 9.417/2018). A Prefeitura de Salvador já possui padrão consolidado de DAM (código de barras FEBRABAN, PDF de guia de arrecadação, baixa via API SEFAZ), implementado no SIGVISA para o licenciamento sanitário.

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
1. Usuário ou sistema inicia a funcionalidade **Gerar DAM da solicitação**.
2. O sistema valida permissões, dados obrigatórios e contexto do processo.
3. O sistema calcula o valor com base na atividade de maior valor correlacionado à TLL, acrescido da taxa de serviço, considerando fator multiplicador quando o CNAE exigir (ex.: por cômodo, por consultório).
4. O sistema gera o DAM com número sequencial por exercício (formato `DAM-AAAA-NNNNNN`), data de vencimento (data de geração + N dias parametrizáveis) e código de barras FEBRABAN de 44 dígitos (produto arrecadação, segmento prefeitura, DV módulo 10).
5. O sistema gera o PDF da guia (padrão municipal: bloco titular, vencimento, valor, detalhamento, código de barras ITF com representação numérica em 4 blocos, canhoto).
6. O sistema disponibiliza o DAM ao requerente e envia e-mail com número, valor, vencimento e instruções.
7. O sistema registra a operação em trilha de auditoria.

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
A definir (depende da confirmação de escopo com a SEDUR)

## Observações
Implementação de referência completa no projeto SIGVISA (`sls-sms`): `docs/DAM-SEFAZ-IMPLEMENTACAO.md` (API SEFAZ, modelo de dados, código de barras com layout validado por engenharia reversa de 146.711 DAMs reais — ver também `docs/CORRECAO-CODIGO-BARRAS-DAM.md`), `app/Services/DamService.php`, `app/Helpers/DamBarcodeHelper.php`, `app/Http/Controllers/Visa/DamController.php`. Diferença de domínio: no SIGVISA a taxa é a TVS (por CNAE); no SILE é a TLL (atividade de maior valor + taxa de serviço) — confirmar fórmula exata com a SEDUR. Confirmar também se a geração ocorre no SILE ou permanece no Simplifica/SEFAZ.
