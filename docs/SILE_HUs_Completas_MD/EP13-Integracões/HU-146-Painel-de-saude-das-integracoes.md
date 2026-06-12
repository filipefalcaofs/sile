# HU-146 — Painel de saúde das integrações

> **Status: Aceita (2026-06-12)** — melhoria além do legado. "Falhas de comunicação entre sistemas" foi dor explícita da reunião; pendência operacional ganha cara de painel, não de log.

## Épica
**EP13 — Integrações**

## Objetivo
Oferecer painel administrativo com o estado de cada integração (Regin/Junta, SEFAZ, GIS/SIGIS, Receita, Cadastro Imobiliário), últimas falhas, fila de retentativas e ação de reprocessamento manual.

## História de Usuário
**Como** gestor/administrador SEDUR,  
**quero** ver a saúde das integrações e reprocessar falhas,  
**para** detectar e resolver problemas de comunicação sem depender de desenvolvedor ou de reclamação do requerente.

## Contexto de Negócio
No legado, mensagens que não chegam (BAP, respostas) viram processos travados descobertos tarde (ex.: processos de janeiro sem BAP não indeferidos). As RNs de integração já exigem registro de payload/status e pendência operacional; este painel materializa essa exigência em ferramenta de operação diária.

## Fluxo Principal
1. O administrador acessa o painel de integrações na retaguarda.
2. Para cada integração: status (operacional/instável/indisponível, derivado das últimas chamadas), volume e taxa de erro na janela, data/hora da última troca com sucesso.
3. Lista de pendências: mensagens com falha, com payload, erro, número de tentativas e próxima retentativa.
4. Ações: reprocessar item, reprocessar em lote, marcar como tratado (com justificativa); teste de conexão (HU-014 RN-010).
5. Falha persistente acima de limiar parametrizado notifica responsáveis (EP11).
6. Tudo auditado.

## Regras de Negócio
- RN-001: O status deriva de evidência real (últimas chamadas registradas) — nunca estado declarado manualmente.
- RN-002: Reprocessamento é idempotente e respeita as regras da integração de destino (ex.: HU-110 RN-003).
- RN-003: Limiar de alerta e janela de cálculo parametrizáveis (HU-014).
- RN-004: Credenciais nunca exibidas; ações restritas por perfil.
- RN-005: Item "marcado como tratado" exige justificativa e fica auditado.

## Critérios de Aceite — BDD

### CA-01 — Estado por integração
**Dado** integrações com chamadas registradas,  
**Quando** o painel carregar,  
**Então** cada integração deve exibir status derivado das chamadas reais, taxa de erro e última troca com sucesso.

### CA-02 — Reprocessamento
**Dado** uma mensagem em falha na fila,  
**Quando** o administrador reprocessar,  
**Então** o sistema deve reexecutar com idempotência e atualizar o resultado no painel.

### CA-03 — Alerta de degradação
**Dado** taxa de erro acima do limiar parametrizado,  
**Quando** a janela de cálculo fechar,  
**Então** os responsáveis devem ser notificados pelos canais habilitados.

## Dependências
- HU-103/104/105/106/107/110 (integrações), HU-133 (BAP), HU-014 (parâmetros/teste de conexão), EP11 (notificações).

## Prioridade
Alta (entra junto com as primeiras integrações vivas)

## Observações
Cobre também o monitoramento do caso "BAP que não chegou": a fila de espera de BAP com idade acima do parâmetro aparece como pendência aqui antes de virar indeferimento automático (HU-134).
