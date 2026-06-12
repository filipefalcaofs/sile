# HU-138 — Manter setores

> **Status: Confirmada (2026-06-11)** — cadastro existente no SAPS legado (Configurações → Cadastrar → Setor). Base do modelo de caixas de análise do EP10.

## Épica
**EP02 — Administração**

## Objetivo
Administrar os setores da SEDUR que recebem processos para análise técnica, com vínculo de analistas e regras de roteamento.

## História de Usuário
**Como** administrador,  
**quero** manter setores,  
**para** organizar caixas de análise e roteamento de processos.

## Contexto de Negócio
No SAPS, o processo cai na **caixa do setor** e é atribuído a um analista sem sair da caixa — qualquer analista do setor mantém visibilidade (cobre férias e ausências). O setor também é filtro de consulta de processos e dimensão de relatórios.

## Fluxo Principal
1. Administrador acessa o cadastro de setores na retaguarda.
2. Cria/edita setor com nome, situação (ativo/inativo) e analistas vinculados.
3. Define regras de roteamento quando aplicável (tipo de serviço, zona ou gatilho → setor).
4. Sistema registra alterações em auditoria.

## Regras de Negócio
- RN-001: O sistema deve validar dados obrigatórios antes de avançar o fluxo.
- RN-002: Toda ação relevante deve ser registrada em auditoria com usuário, data, hora e origem.
- RN-003: O usuário somente poderá executar a ação se possuir permissão compatível com seu perfil.
- RN-004: Setor com processos pendentes não pode ser excluído — apenas inativado, com destino definido para os processos em aberto.
- RN-005: Vínculo analista ↔ setor administrável sem deploy; um analista pode pertencer a mais de um setor.

## Critérios de Aceite — BDD

### CA-01 — Execução com sucesso
**Dado** que o administrador possui permissão,  
**Quando** criar ou editar um setor,  
**Então** o setor fica disponível como caixa de análise e filtro de consulta.

### CA-02 — Auditoria obrigatória
**Dado** que a funcionalidade foi executada,  
**Quando** houver alteração,  
**Então** o sistema deve registrar usuário, data, hora, origem, ação executada e resultado.

### CA-03 — Proteção de integridade
**Dado** um setor com processos pendentes,  
**Quando** o administrador tentar excluí-lo,  
**Então** o sistema deve impedir a exclusão e oferecer inativação com redistribuição.

## Dependências
- HU-012 (usuários), HU-013 (perfis), HU-080 (distribuição).

## Prioridade
Média (necessária antes da Fase 10)
