# HU-150 — Atendimento presencial assistido

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Inclusão digital: cidadão sem acesso/habilidade digital é atendido no balcão por operador que age "em nome de", com auditoria.

## Épica
**EP08 — Solicitação de Viabilidade**

## Objetivo
Permitir que atendente autorizado da SEDUR execute ações no portal em nome do cidadão presente no balcão — abrir solicitação direta (renovação), consultar protocolo, responder convite — com identificação de ambos e trilha completa.

## História de Usuário
**Como** atendente SEDUR,  
**quero** operar o sistema em nome do cidadão presente,  
**para** garantir atendimento a quem não consegue usar o canal digital.

## Contexto de Negócio
Órgão público não pode condicionar o serviço à habilidade digital do cidadão. O mecanismo de representação já existe no SILE desde a Fase 1 (procuração — ações "em nome de" identificadas); esta HU estende o padrão ao perfil atendente, com salvaguardas próprias.

## Fluxo Principal
1. Atendente autenticado inicia atendimento informando CPF do cidadão presente.
2. O sistema localiza/cria o vínculo de atendimento e exibe banner permanente "Atendendo: [nome] — CPF [•••]".
3. Atendente executa as ações permitidas ao cidadão (abrir solicitação direta, consultar, responder convite, anexar documento), dentro do escopo do perfil.
4. Toda ação registra ator real (atendente) e beneficiário (cidadão) — padrão "em nome de" da auditoria da Fase 1.
5. Encerrado o atendimento, o vínculo expira; comprovante das ações pode ser impresso/enviado ao cidadão.

## Regras de Negócio
- RN-001: Ações em atendimento registram sempre os dois CPFs (atendente e cidadão) — nunca aparecem como ação do próprio cidadão.
- RN-002: Escopo do atendente é limitado por permissão (sem ações de análise/decisão); vínculo de atendimento tem expiração curta parametrizável.
- RN-003: Fluxos via Regin permanecem no canal próprio — o atendimento presencial cobre fluxos diretos do portal (renovação etc.) e apoio a consulta/pendência.
- RN-004: Termos/declarações do processo permanecem do cidadão — coleta de ciência presencial conforme procedimento da SEDUR (definir instrumento: assinatura em tela, impresso ou gov.br).
- RN-005: Relatórios distinguem origem "balcão" (EP15) para dimensionar a demanda presencial.

## Critérios de Aceite — BDD

### CA-01 — Ação em nome do cidadão
**Dado** um atendimento ativo,  
**Quando** o atendente protocolar uma solicitação,  
**Então** o processo registra o cidadão como requerente e o atendente como executor, com ambos na auditoria.

### CA-02 — Escopo limitado
**Dado** um perfil atendente,  
**Quando** tentar ação fora do escopo (ex.: decidir processo),  
**Então** o sistema bloqueia e registra a tentativa.

### CA-03 — Expiração do vínculo
**Dado** um atendimento sem atividade além do tempo parametrizado,  
**Quando** o atendente tentar nova ação,  
**Então** o vínculo deve exigir reabertura explícita.

## Dependências
- Fase 1 (representação "em nome de", auditoria), HU-061 (solicitação direta), HU-069 (consulta), HU-091 (responder pendência), HU-013 (perfis).

## Prioridade
Média

## Observações
Confirmar com a SEDUR o procedimento de ciência presencial (RN-004) e o volume esperado de balcão. Reusa integralmente o mecanismo de procuração da Fase 1 — custo de implementação baixo.
