# HU-058 — Simular classificação de risco

## Épica
**EP07 — Consulta Prévia de Viabilidade**

## Situação na implementação
- **Status:** Implementada
- **Revisão:** 16/10/2026, com o código-fonte como referência.

## Objetivo
Exibir, na consulta prévia, a classificação de risco municipal e sanitário e o encaminhamento calculados pelo motor de risco.

## História de Usuário
**Como** cidadão que consulta a viabilidade,  
**quero** ver o risco da atividade,  
**para** conhecer o nível e o encaminhamento que o motor atribuiu, sem abrir processo.

## Atores e permissões
- Não há rota separada. O bloco `risco` vem no JSON das três consultas públicas.
- Acesso público, com o throttle da consulta.

## Fluxo evidenciado
1. Cada consulta chama `RiscoClassificationService::classify` com CNAE, área e respostas da planilha. Na rota pública, tipo de imóvel e categoria de empresa não são enviados.
2. O resultado traz risco municipal, risco sanitário, fundamentação e encaminhamento (`fluxo`, dimensão decisiva, motivo, gatilhos).
3. A tela "Classificação de risco" mostra os níveis municipal e sanitário e o encaminhamento.
4. A fundamentação do risco entra na lista única de fundamentação legal da consulta.
5. A consulta não protocola solicitação nem dispara o fluxo expresso. O encaminhamento fica no snapshot.

## Regras de negócio evidenciadas
- A classificação é a do motor de risco já usado pelo sistema. A consulta não tem tabela de risco própria.
- O encaminhamento é registrado na auditoria da consulta (`encaminhamento_risco`).
- Consulta por CNAE também classifica o risco, mesmo sem território.

## Validações
- As da entrada da consulta. CNAE obrigatório. Área opcional, numérica, de 0 a 9999999.

## Critérios de aceite
### CA-01 — Risco no resultado
**Dado** uma consulta prévia concluída com CNAE,  
**Quando** o resultado é exibido,  
**Então** a seção de classificação mostra risco municipal, risco sanitário e encaminhamento devolvidos pelo motor.

### CA-02 — Sem abertura de processo
**Dado** o encaminhamento calculado,  
**Quando** a consulta termina,  
**Então** nenhum pedido de viabilidade é criado por causa dessa classificação.

## Divergências da redação anterior
A redação anterior dizia classificar o risco preliminar para indicar a possibilidade de fluxo expresso. O código mostra o encaminhamento do motor na consulta e não abre nem decide o fluxo expresso neste passo.

## Evidências
- `app/Services/Viabilidade/ConsultaViabilidadeService.php`
- `app/Services/Viabilidade/ConsultaViabilidadeResult.php`
- `app/Services/Risco/RiscoClassificationService.php`
- `app/Services/Risco/RiscoResult.php`
- `resources/js/components/viabilidade/resultado-viabilidade.tsx`

## Não evidenciado
- Tela exclusiva de simulação de risco.
- Deferimento ou indeferimento automático a partir da consulta prévia.
- Envio de tipo de imóvel ou categoria de empresa pelas rotas públicas de consulta.
