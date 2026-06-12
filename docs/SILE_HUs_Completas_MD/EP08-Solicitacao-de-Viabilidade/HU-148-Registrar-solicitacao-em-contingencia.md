# HU-148 — Registrar solicitação em contingência

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Evita paralisia total da operação quando o Regin/integração falha por período prolongado; também permite operar o fluxo completo antes de o contrato Regin ser entregue.

## Épica
**EP08 — Solicitação de Viabilidade**

## Objetivo
Permitir que operador autorizado da SEDUR registre manualmente uma solicitação de viabilidade no backoffice, com origem "contingência" auditada, mantendo o processo no mesmo fluxo decisório padrão.

## História de Usuário
**Como** operador/gestor SEDUR,  
**quero** registrar solicitação manualmente em contingência,  
**para** manter o atendimento quando o canal Regin estiver indisponível ou a mensagem não chegar.

## Contexto de Negócio
O legado sofre com "mensagens que não chegam" e processos travados na origem. Com recepção durável (HU-103) o risco cai, mas falha prolongada do integrador não pode parar a cidade. A entrada de contingência registra o processo com os mesmos dados do formulário oficial e o BAP/identificadores externos são vinculados a posteriori, quando disponíveis.

## Fluxo Principal
1. Operador autorizado aciona "Nova solicitação (contingência)" na retaguarda.
2. Preenche o mesmo conjunto de dados do formulário oficial (imóvel, polígono, CNAEs, condicionantes, anexos), informando motivo da contingência e referência externa quando houver (nº de protocolo Regin informado pelo requerente, e-mail, atendimento presencial).
3. O sistema protocola com origem "contingência" e segue o fluxo padrão (classificação, expresso ou análise).
4. Quando o canal normaliza, identificadores externos (BAP) são vinculados ao processo (HU-133) e a resposta ao integrador segue o fluxo normal (HU-104).
5. Tudo auditado: operador, motivo, dados, vínculos posteriores.

## Regras de Negócio
- RN-001: Acesso restrito a perfil específico; motivo da contingência é obrigatório.
- RN-002: O processo de contingência executa exatamente o mesmo motor e regras do fluxo normal — nunca um atalho decisório.
- RN-003: Origem "contingência" permanece visível no processo e nos relatórios (dimensão de origem).
- RN-004: Vinculação posterior de BAP/identificadores externos não pode duplicar processo (verificação de duplicidade — HU-061 RN-007).
- RN-005: Volume de contingência é monitorado (HU-146/EP15) — uso recorrente indica problema de integração a tratar, não rotina aceitável.

## Critérios de Aceite — BDD

### CA-01 — Registro com fluxo padrão
**Dado** um operador autorizado com o canal Regin indisponível,  
**Quando** registrar solicitação em contingência,  
**Então** o processo deve ser protocolado com origem "contingência" e seguir o fluxo decisório padrão.

### CA-02 — Vinculação posterior
**Dado** um processo de contingência com BAP chegando depois,  
**Quando** o vínculo for realizado,  
**Então** o processo não pode ser duplicado e a resposta ao integrador segue o contrato normal.

### CA-03 — Auditoria
**Dado** qualquer registro em contingência,  
**Quando** consultar a trilha,  
**Então** operador, motivo, dados e vínculos posteriores devem estar registrados.

## Dependências
- HU-061/HU-062 (formulário), HU-133 (BAP), HU-104 (resposta ao integrador), HU-146 (monitoramento).

## Prioridade
Média-alta

## Observações
Benefício adicional: enquanto o contrato técnico do Regin não for entregue (bloqueio EP13), a entrada de contingência permite à SEDUR operar e homologar o fluxo completo de ponta a ponta com processos reais — sem nenhum adaptador simulado.
