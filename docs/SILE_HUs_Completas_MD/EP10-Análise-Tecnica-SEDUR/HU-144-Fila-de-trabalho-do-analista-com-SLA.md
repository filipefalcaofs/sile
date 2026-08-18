# HU-144 — Fila de trabalho do analista com SLA visual

> **Status: Aceita (2026-06-12)** — melhoria além do legado. O SAPS é orientado a "consultar processo" (tela de filtros); o SILE entrega o trabalho ordenado por prioridade, com prazo visível.

## Épica
**EP10 — Análise Técnica SEDUR**

## Objetivo
Oferecer painel de fila de trabalho ("meus processos" e "processos do setor") ordenado por prazo restante, com semáforo de SLA por processo e por etapa, contadores por status e alertas de vencimento.

## História de Usuário
**Como** analista SEDUR,  
**quero** ver minha fila priorizada por prazo,  
**para** trabalhar no que urge sem precisar caçar processos em telas de filtro.

## Contexto de Negócio
No legado, o analista usa a consulta com filtros para achar trabalho; o prazo é invisível (origem da distorção "19 dias reportados vs 42h medidos"). A fila priorizada com semáforo torna o prazo um elemento operacional do dia a dia, não um relatório a posteriori.

## Fluxo Principal
1. O analista acessa o painel de análise técnica.
2. Abas "Meus processos" e "Caixa do setor" (HU-138), com contadores por status (aguardando análise, em análise, aguardando convite, vencendo hoje).
3. Lista ordenada por prazo restante (calculado com as regras de prazo corretas — úteis/feriados, HU-137), com semáforo: verde (no prazo), amarelo (limiar parametrizável), vermelho (estourado).
4. Cada item mostra etapa atual e tempo na etapa; clique abre o processo/ficha.
5. Gestor vê visão agregada do setor (carga por analista, processos em vermelho) — ponte com HU-130.

## Regras de Negócio
- RN-001: O prazo usa exclusivamente as regras parametrizadas de contagem (HU-014/HU-137) — mesma fonte da medição da HU-129 (sem dupla contagem).
- RN-002: Limiares do semáforo (ex.: amarelo a 80% do prazo) parametrizáveis por tipo de serviço.
- RN-003: A ordenação padrão é por prazo restante; o analista pode reordenar sem perder o padrão ao recarregar.
- RN-004: Processos em vermelho geram alerta ao gestor do setor conforme escalonamento (HU-147).
- RN-005: Acesso restrito por perfil; visão "setor" respeita vínculo analista ↔ setor (HU-138).

## Critérios de Aceite — BDD

### CA-01 — Fila priorizada
**Dado** processos atribuídos ao analista e ao seu setor,  
**Quando** abrir o painel,  
**Então** as listas devem vir ordenadas por prazo restante com semáforo e contadores corretos.

### CA-02 — Prazo correto
**Dado** um processo com prazo atravessando fim de semana e feriado cadastrado,  
**Quando** o semáforo for calculado,  
**Então** a contagem deve respeitar as regras parametrizadas (não contar dias não úteis quando a regra assim definir).

### CA-03 — Visão do gestor
**Dado** um gestor do setor,  
**Quando** abrir o painel,  
**Então** deve ver carga por analista e processos em vermelho do setor.

## Dependências
- HU-080/HU-081/HU-138 (distribuição e setores), HU-137 (feriados), HU-014 (parâmetros), HU-147 (escalonamento).

## Prioridade
Alta

## Observações
Complementa (não substitui) a consulta avançada da HU-082. Padrões de UI: DataTable/KPI cards já estabelecidos nas Fases 2.x.
