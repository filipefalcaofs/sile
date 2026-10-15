# HU-143 — Simular impacto de alteração de parametrização

> **Status: Aceita (2026-06-12)** — melhoria além do legado. Autonomia com segurança: o gestor vê o impacto antes de ativar a mudança de regra.

## Épica
**EP02 — Administração**

## Objetivo
Permitir simular uma alteração de parametrização do motor (enquadramentos, condicionantes, classificação de risco, gatilhos) contra processos reais recentes, exibindo quais teriam resultado diferente, antes de publicar a mudança.

## História de Usuário
**Como** gestor/administrador SEDUR,  
**quero** simular o impacto de uma mudança de regra antes de ativá-la,  
**para** ampliar o expresso e ajustar parametrizações sem quebrar decisões silenciosamente.

## Contexto de Negócio
A SEDUR quer autonomia (HU-014), mas no legado mudanças dependem de desenvolvedor justamente porque errar parametrização tem consequência invisível. O sandbox dá segurança: "esta mudança teria alterado o resultado de 12 dos últimos 500 processos — ver quais" — e só então o gestor publica.

## Fluxo Principal
1. O gestor edita uma regra parametrizada (ex.: novo enquadramento no Quadro 7, condicionante, gatilho) e salva como **rascunho/versão candidata**.
2. Aciona "Simular impacto": o sistema reexecuta o motor sobre os últimos N processos (parametrizável) com a versão candidata.
3. O resultado lista: total reprocessado, quantos mudariam de resultado, distribuição (deferido→indeferido, análise→expresso etc.) e o detalhe de cada caso divergente.
4. O gestor decide: publicar a versão (passa a vigorar com nova versão de regras) ou ajustar o rascunho.
5. Simulação e publicação registradas em auditoria (quem, quando, diff da regra, resumo do impacto).

## Regras de Negócio
- RN-001: A simulação roda em sandbox — nunca altera processos reais nem dispara integrações/notificações.
- RN-002: Publicação cria nova versão das regras (HU-046/HU-053); decisões passadas permanecem vinculadas à versão da época.
- RN-003: O tamanho da amostra (N processos / janela temporal) é parametrizável; o resumo deve indicar a amostra usada.
- RN-004: O diff da regra (antes × depois) integra o registro de auditoria da publicação.
- RN-005: Segregação de funções obrigatória para regras sensíveis (enquadramentos, risco, gatilhos): quem edita/simula **não** pode ser quem publica — aprovação de segunda pessoa autorizada ("4 olhos"), com ambos registrados na auditoria da publicação; a lista de domínios sujeitos a 4 olhos é parametrizável.

## Critérios de Aceite — BDD

### CA-01 — Simulação sem efeito colateral
**Dado** uma versão candidata de regra,  
**Quando** o gestor simular o impacto,  
**Então** o sistema deve reprocessar a amostra em sandbox e nenhum processo real, integração ou notificação pode ser afetado.

### CA-02 — Relatório de divergência
**Dado** uma simulação concluída,  
**Quando** houver casos com resultado diferente,  
**Então** o sistema deve listar cada caso com resultado atual × resultado simulado e motivo.

### CA-03 — Publicação versionada
**Dado** uma versão candidata aprovada,  
**Quando** o gestor publicar,  
**Então** nova versão de regras entra em vigor, com auditoria completa (diff, responsável, impacto estimado).

## Dependências
- HU-014 (parâmetros), HU-015 a HU-020 (mantenedores), HU-046 (versionar LOUOS), HU-053 (atualizar risco), motores EP05/EP06.

## Prioridade
Média-alta (entregar junto com os mantenedores das Fases 5–6)

## Observações
É o mecanismo que torna segura a meta de ampliar o expresso continuamente (HU-145): propor mudança → simular → publicar → medir.
