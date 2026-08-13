# HU-139 — Manter edifícios comerciais e complementos

> **Status: Proposta (2026-06-11)** — cadastros existentes no SAPS legado (menu Serviços → Edifício Comercial; Configurações → Cadastrar → Complemento). Semântica exata pendente de confirmação com a SEDUR (pergunta 6 da pauta).

## Épica
**EP02 — Administração**

## Objetivo
Administrar o cadastro de edifícios comerciais e seus complementos (salas, lojas), usados para pré-preencher e validar o campo complemento no formulário de viabilidade.

## História de Usuário
**Como** administrador/operador SEDUR,  
**quero** manter edifícios comerciais e complementos,  
**para** que o formulário Regin pré-preencha e valide o complemento por inscrição imobiliária.

## Contexto de Negócio
Na demonstração do Regin (2026-06-11), ao informar inscrição imobiliária com edifício comercial cadastrado, o complemento aparece pré-preenchido (com descrição obrigatória); o passo a passo oficial mostra a busca "buscar complemento" (ex.: Sala 101). Esse cadastro evita erro de endereçamento e duplicidade de processos por sala.

## Regras de Negócio
- RN-001: O sistema deve validar dados obrigatórios antes de avançar o fluxo.
- RN-002: Toda ação relevante deve ser registrada em auditoria com usuário, data, hora e origem.
- RN-003: O usuário somente poderá executar a ação se possuir permissão compatível com seu perfil.
- RN-004: Edifício comercial vincula-se à inscrição imobiliária e possui N complementos (sala/loja/andar) pesquisáveis no formulário (HU-062).
- RN-005: Alterações versionadas; processos existentes mantêm o complemento da época.

## Critérios de Aceite — BDD

### CA-01 — Pré-preenchimento no formulário
**Dado** uma inscrição imobiliária com edifício comercial cadastrado,  
**Quando** o requerente a informar no formulário de viabilidade,  
**Então** o sistema deve oferecer os complementos cadastrados para seleção/busca.

### CA-02 — Auditoria obrigatória
**Dado** que a funcionalidade foi executada,  
**Quando** houver alteração,  
**Então** o sistema deve registrar usuário, data, hora, origem, ação executada e resultado.

## Dependências
- HU-062 (informar imóvel), HU-106 (Cadastro Imobiliário — fonte da inscrição).

## Prioridade
Média

## Observações
Confirmar com a SEDUR: origem dos dados (manual vs importado do Cadastro Imobiliário) e papel exato do cadastro no fluxo (pergunta 6, seção 7.6 da análise).
