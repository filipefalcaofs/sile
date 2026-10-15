# HU-149 — Detectar padrões de abuso e fraude

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Nenhuma HU cobria antifraude; a automação ampliada (expresso para baixo e médio risco) aumenta o incentivo a declarações falsas.

## Épica
**EP12 — Auditoria e Compliance**

## Objetivo
Detectar e sinalizar padrões suspeitos nas solicitações — reincidência atípica, cadeias de escritório virtual, declarações incompatíveis — gerando alertas para revisão humana (malha fina), sem decisão automática punitiva.

## História de Usuário
**Como** gestor SEDUR,  
**quero** ser alertado de padrões de abuso,  
**para** direcionar a malha fina para onde há risco real, protegendo a credibilidade do fluxo expresso.

## Contexto de Negócio
Com o expresso decidindo sem intervenção humana, a autodeclaração vira o ponto fraco: requerente que aprende "a resposta certa" pode obter deferimento indevido. A defesa não é frear a automação, e sim vigiá-la: regras determinísticas de detecção + amostragem dirigida para malha fina (HU-136).

## Fluxo Principal
1. Rotina avalia solicitações contra regras de detecção parametrizáveis.
2. Padrões mínimos: mesmo CPF/CNPJ/contador com volume atípico de processos na janela; múltiplos escritórios virtuais encadeados no mesmo endereço/sede; mesma inscrição imobiliária com atividades incompatíveis simultâneas; respostas de condicionantes estatisticamente improváveis (sempre a resposta que evita análise); polígonos repetidos em endereços distintos.
3. Alerta criado com severidade e evidências; casos acima do limiar entram automaticamente na fila de malha fina (HU-136) com motivo "suspeita de abuso".
4. Gestor consulta painel de alertas, confirma (encaminha/cassa conforme regra legal) ou descarta com justificativa.
5. Tudo auditado; falsos positivos alimentam ajuste dos limiares.

## Regras de Negócio
- RN-001: Detecção **nunca indefere automaticamente** — gera alerta e/ou envio à malha fina; decisão é humana.
- RN-002: Regras e limiares de detecção parametrizáveis (HU-014), com simulação de impacto antes de ativar (HU-143).
- RN-003: Alertas e descartes auditados com justificativa (LGPD: acesso restrito, dados minimizados).
- RN-004: Verificação polígono × foto da fachada por IA é caso de uso da HU-115 (EP14) e alimenta esta HU quando habilitada.
- RN-005: Indicador de efetividade (alertas confirmados ÷ gerados) consultável para calibrar regras (EP15).

## Critérios de Aceite — BDD

### CA-01 — Alerta por padrão suspeito
**Dado** regras de detecção ativas e uma solicitação que dispara o padrão,  
**Quando** a rotina executar,  
**Então** alerta com evidências deve ser criado e, acima do limiar, o processo entra na malha fina com motivo registrado.

### CA-02 — Sem punição automática
**Dado** um alerta de qualquer severidade,  
**Quando** processado,  
**Então** nenhum indeferimento ou cassação ocorre sem ação humana registrada.

### CA-03 — Auditoria e calibragem
**Dado** alertas confirmados e descartados,  
**Quando** o gestor consultar o painel,  
**Então** o indicador de efetividade e o histórico de justificativas devem estar disponíveis.

## Dependências
- HU-136 (malha fina), HU-014 (parâmetros), HU-143 (simulação), HU-100 (trilha), HU-115 (IA — opcional).

## Prioridade
Média (entra na Fase 12; regras simples podem antecipar junto à Fase 9 se a SEDUR priorizar)

## Observações
Começar com 3–5 regras determinísticas simples e medir efetividade antes de sofisticar. A reunião não trouxe casos de fraude explícitos — validar padrões reais com a equipe da SEDUR (acrescentado à pauta).
