# HU-072 — Confirmar pagamento do DAM

> **Status: Proposta** — escopo pendente de confirmação com a SEDUR. Especificação técnica baseada na implementação de referência do projeto SIGVISA (sls-sms), que opera baixa automática de DAMs via API da SEFAZ Salvador em produção (`sls-sms/docs/DAM-SEFAZ-IMPLEMENTACAO.md`).

## Épica
**EP08 — Solicitação de Viabilidade**

## Objetivo
Confirmar o pagamento do DAM — por baixa automática via API da SEFAZ ou baixa manual por operador — e liberar a continuidade do processo, incluindo a disponibilização do TVL; tratar o não pagamento.

## História de Usuário
**Como** sistema,  
**quero** confirmar o pagamento do DAM,  
**para** liberar o TVL ao requerente ou indeferir o processo por não pagamento.

## Contexto de Negócio
No fluxo atual, o TVL é disponibilizado após o pagamento do DAM mediante compensação bancária (prazo de até 48 horas). Conforme o Decreto Municipal nº 32.155/2020 (art. 6º), o primeiro DAM do processo, se não quitado até a data de validade, é automaticamente cancelado, com o consequente indeferimento do processo, sem gerar dívida ativa. A SEFAZ Salvador disponibiliza API de consulta de pagamentos por código de barras (`POST /DAM/ConsultarDamsSEMOP`), já utilizada em produção pelo SIGVISA para baixa automática horária.

## Atores Envolvidos
- Cidadão / empresário / contador / procurador.
- Operador SEDUR (baixa manual).
- Sistema SILE e API da SEFAZ Salvador.

## Pré-condições
- DAM gerado para a solicitação (HU-071).
- Integração SEFAZ configurada e ativa (HU-110) para baixa automática; ou operador com permissão para baixa manual.

## Fluxo Principal
1. O scheduler executa a rotina de baixa automática (periodicidade horária).
2. O sistema busca os DAMs pendentes e gera o código de barras de cada um.
3. O sistema consulta a API da SEFAZ em lotes de até 100 códigos de barras (`POST /DAM/ConsultarDamsSEMOP`).
4. Para cada retorno com situação de pago (`PAGO`, `LIQUIDADO`, `QUITADO` e variações), o sistema atualiza o DAM: status pago, tipo de baixa automática, data e valor do pagamento.
5. O sistema verifica se todos os DAMs da solicitação estão pagos; em caso positivo e com solicitação deferida, libera o TVL automaticamente.
6. O sistema notifica o requerente.
7. O sistema registra a operação em trilha de auditoria.

## Fluxos Alternativos
### FA-01 — Baixa manual pelo operador
1. O operador acessa o DAM pendente e informa valor pago e data do pagamento.
2. O sistema valida: status pendente, data não futura (máximo 2 anos no passado), valor maior que zero.
3. Se o valor pago difere do valor do DAM, registra observação automática.
4. O DAM é baixado com tipo de baixa manual e o fluxo segue do passo 5 do fluxo principal.

### FA-02 — DAM não pago até a validade (primeiro DAM)
1. O sistema identifica DAM vencido sem pagamento.
2. O DAM é cancelado automaticamente.
3. O processo é indeferido por não pagamento, sem gerar dívida ativa (Decreto nº 32.155/2020).
4. O requerente é notificado.

### FA-03 — DAM subsequente ou complementar não pago
1. O sistema identifica DAM subsequente vencido sem pagamento.
2. O sistema registra a situação para encaminhamento à dívida ativa, conforme regras da SEFAZ.

### FA-04 — Falha de integração SEFAZ
1. A API fica indisponível, expira o token ou retorna erro.
2. Em 401, o sistema renova o token e repete a requisição; em falha de rede, aplica retentativas.
3. Persistindo a falha, registra o erro e tenta novamente no próximo ciclo do scheduler, sem perder DAMs pendentes.
4. A baixa manual permanece disponível como contingência.

### FA-05 — Disparo manual da baixa automática
1. O operador aciona a verificação de pagamentos pela interface.
2. O sistema executa o mesmo processo do fluxo principal sob demanda.

## Regras de Negócio
- RN-001: O sistema deve validar dados obrigatórios antes de avançar o fluxo.
- RN-002: Toda ação relevante deve ser registrada em auditoria com usuário, data, hora e origem.
- RN-003: A liberação do TVL deve ocorrer somente após confirmação do pagamento de todos os DAMs da solicitação.
- RN-004: O primeiro DAM não quitado até a validade deve ser cancelado automaticamente com indeferimento do processo, sem dívida ativa (Decreto nº 32.155/2020, art. 6º).
- RN-005: DAM subsequente ou complementar não quitado deve seguir o tratamento de dívida ativa definido pela SEFAZ.
- RN-006: Eventos de pagamento devem ser idempotentes — confirmações duplicadas não podem gerar efeitos duplicados.
- RN-007: A baixa deve registrar o tipo (manual ou automática) e o responsável (operador ou serviço).
- RN-008: A consulta à SEFAZ deve ser em lotes de até 100 códigos de barras, com normalização das variações de campos e status retornados pela API.
- RN-009: Baixa manual exige valor pago maior que zero e data não futura, limitada a 2 anos no passado; divergência de valor gera observação automática.

## Critérios de Aceite — BDD

### CA-01 — Baixa automática
**Dado** que existe DAM pendente vinculado à solicitação e a SEFAZ retorna situação de pago,  
**Quando** a rotina de baixa automática executar,  
**Então** o DAM deve ser baixado (tipo automática, com data e valor), o TVL liberado quando todos os DAMs estiverem pagos e o requerente notificado.

### CA-02 — Baixa manual
**Dado** que o operador possui permissão e informa valor e data válidos,  
**Quando** confirmar o pagamento manualmente,  
**Então** o DAM deve ser baixado (tipo manual, com responsável registrado) e o fluxo de liberação executado.

### CA-03 — Indeferimento por não pagamento
**Dado** que o primeiro DAM do processo venceu sem quitação,  
**Quando** o sistema processar o vencimento,  
**Então** o DAM deve ser cancelado, o processo indeferido sem dívida ativa e o requerente notificado.

### CA-04 — Resiliência da integração
**Dado** que a API da SEFAZ está indisponível ou o token expirou,  
**Quando** a rotina de baixa executar,  
**Então** o sistema deve renovar token/retentar, registrar a falha sem perder DAMs pendentes e manter a baixa manual disponível.

### CA-05 — Auditoria obrigatória
**Dado** que houve baixa, cancelamento ou indeferimento,  
**Quando** o evento for processado,  
**Então** o sistema deve registrar usuário/serviço, data, hora, origem, ação e resultado.

## Campos/Dados Envolvidos
- Identificador da solicitação/processo e do DAM.
- Código de barras consultado e resposta da SEFAZ (status, data, valor pago).
- Tipo de baixa (manual/automática), responsável, observação de divergência.
- Status do processo e do documento TVL.
- Logs, integrações e metadados de auditoria.

## Permissões
- **Cidadão/Contador/Procurador:** consulta do status de pagamento das próprias solicitações.
- **Operador SEDUR (perfil específico):** baixa manual e disparo da verificação automática.
- **Gestor SEDUR:** acompanhamento e tratamento de casos não conciliados.
- **Administrador:** configuração da integração SEFAZ.

## Exceções e Tratamentos
- Pagamento duplicado ou com valor divergente.
- DAM cancelado ou vencido.
- API SEFAZ indisponível, token expirado, timeout.
- Resposta da SEFAZ com campos em formatos variados (normalizar).
- Processo já encerrado.

## Auditoria
O sistema deve manter registro completo da execução desta HU, incluindo:
- usuário ou serviço responsável;
- data e hora;
- dados do pagamento e da resposta da SEFAZ;
- ação executada (baixa manual/automática, cancelamento, indeferimento);
- resultado;
- erros ou exceções.

## Dependências
- HU-071 (Gerar DAM da solicitação).
- HU-110 (Integrar com SEFAZ municipal) — endpoint de consulta de pagamentos.
- HU-076 (Emitir resultado expresso / TVL) e HU-086 (Deferir solicitação).
- Módulo de auditoria e notificações (EP11).

## Prioridade
A definir (depende da confirmação de escopo com a SEDUR)

## Observações
Implementação de referência no projeto SIGVISA (`sls-sms`): comando `dam:baixar-pagos` agendado de hora em hora (`app/Console/Commands/BaixarDamsPagos.php`, `app/Jobs/BaixarDamsPagosJob.php`), consulta em lotes de 100 à API `POST /DAM/ConsultarDamsSEMOP`, baixa manual com validações e emissão automática do alvará quando todos os DAMs da solicitação estão pagos — no SILE, o equivalente é a liberação do TVL. Confirmar com a SEDUR se a conciliação ocorre no SILE ou permanece no Simplifica/SEFAZ.
